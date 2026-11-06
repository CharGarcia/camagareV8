<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\SecuencialRepository;

/**
 * Servicio centralizado para la gestión inteligente de secuenciales
 * de documentos electrónicos (SRI Ecuador).
 * 
 * Funcionalidades:
 * 1. Obtiene el siguiente secuencial disponible para cualquier tipo de documento.
 * 2. Detecta huecos (gaps) en la numeración a partir del secuencial inicial configurado.
 * 3. Si no hay huecos, retorna el siguiente número después del máximo utilizado.
 * 4. Nunca retorna un número menor al secuencial inicial configurado.
 * 5. Soporta todos los tipos de documentos: facturas, retenciones, notas de crédito, etc.
 *
 * MODOS DE NUMERACIÓN (Empresa → Secuenciales, por tipo de documento)
 *   - 'consecutivo' (el de siempre, y el de todos los tipos por defecto): un correlativo
 *     corrido por punto de emisión que nunca se reinicia.
 *   - 'por_fecha': el correlativo arranca de cero en cada periodo según la FECHA DE EMISIÓN
 *     del documento, y el periodo va como prefijo dentro de los mismos 9 dígitos:
 *         anual   → AAAA   (4) + 5 dígitos  →  202600017
 *         mensual → AAAAMM (6) + 3 dígitos  →  202609001
 *     Solo lo admiten los documentos internos; los electrónicos quedan fuera porque su
 *     secuencial forma parte de la clave de acceso del SRI (ver TIPOS_SIN_MODO_PERIODO).
 *
 * Patrón: Controller → Service → Repository → Base de datos
 */
class SecuencialService
{
    private SecuencialRepository $repository;

    public function __construct(?SecuencialRepository $repository = null)
    {
        $this->repository = $repository ?? new SecuencialRepository();
    }

    /**
     * Obtiene el siguiente secuencial disponible para un punto de emisión y tipo de documento.
     * 
     * Algoritmo:
     * 1. Obtener el secuencial_inicial configurado (ej: 100)
     * 2. Desde ese inicial, buscar el primer número NO usado en la tabla del documento
     * 3. Si no hay huecos, retornar max_usado + 1
     * 4. Nunca retornar un número inferior al secuencial_inicial
     *
     * Ejemplos (el cálculo lo resuelve SecuencialRepository::getSiguienteDisponible):
     *   inicial 5, existe solo el 11         → 5   (el 5 está libre: se rellena el hueco)
     *   inicial 1, existen del 1 al 10       → 11  (no hay huecos: sigue al máximo)
     *   inicial 5, existen 5, 6 y 11         → 7   (primer hueco por encima del inicial)
     *   inicial 20, existen 1, 2 y 3         → 20  (nunca por debajo del inicial)
     *
     * En modo 'por_fecha' el cálculo es el mismo, pero acotado al periodo de $fechaEmision:
     *   periodo anual, inicial 1, existen 202600001..202600004  → 202600005
     *   se emite con fecha del año siguiente                    → 202700001 (arranca de cero)
     *
     * @param int         $idPuntoEmision ID del punto de emisión
     * @param string      $tipoDocumento  Tipo de documento (ej: 'Facturas de venta')
     * @param string|null $fechaEmision   Fecha de emisión del documento (Y-m-d). Solo se usa en
     *                                    modo 'por_fecha'; si no se pasa, se numera en el periodo
     *                                    de hoy. Los tipos en modo 'consecutivo' la ignoran, que
     *                                    es por qué todos los llamadores anteriores siguen valiendo.
     * @return array ['secuencial' => int, 'formateado' => string, 'es_gap' => bool, 'detalle' => string,
     *                'modo' => string, 'periodo' => string|null]
     */
    public function obtenerSiguienteSecuencial(int $idPuntoEmision, string $tipoDocumento, ?string $fechaEmision = null): array
    {
        // Bloqueo por punto de emisión + tipo de documento: evita que dos documentos
        // emitidos casi al mismo tiempo calculen el mismo "siguiente número" (ver
        // CLAUDE.md §8). Solo protege de verdad si el llamador ya abrió su transacción
        // ANTES de llegar aquí y no la cierra hasta insertar la cabecera del documento
        // (pg_advisory_xact_lock se libera al COMMIT/ROLLBACK, no antes).
        // El candado NO distingue periodo a propósito: serializar todo el tipo+punto es
        // más conservador y cubre igual a dos documentos de periodos distintos emitidos
        // a la vez, a cambio de una concurrencia que en la práctica no se nota.
        $this->repository->lockSecuencial($idPuntoEmision, $tipoDocumento);

        // 1. Obtener configuración: número inicial y cómo debe numerarse este tipo.
        $config = $this->repository->getConfigSecuencial($idPuntoEmision, $tipoDocumento);
        // ¿Existe realmente una configuración de secuencial para este punto+tipo?
        // (getConfigSecuencial devuelve id=null y secuencial_inicial=1 cuando NO hay.)
        $configurado = !empty($config['id']);
        $secuencialInicial = max(1, (int) $config['secuencial_inicial']);
        $modo    = (string) ($config['modo_numeracion'] ?? SecuencialRepository::MODO_CONSECUTIVO);
        $periodo = $config['periodo_reinicio'] ?? null;

        // 2. El cálculo (hueco o siguiente al máximo) lo resuelve el motor en una sola consulta.
        //    Antes se traían TODOS los secuenciales del punto a memoria y se recorría el rango
        //    [inicial .. máximo] número por número en PHP: con un punto que ya llegó al 500.000,
        //    medio millón de iteraciones en cada emisión.
        //    Lo que cambia entre modos es sobre qué números se busca el hueco: la serie entera
        //    en 'consecutivo', o solo el correlativo dentro del periodo en 'por_fecha'.
        if ($modo === SecuencialRepository::MODO_POR_FECHA) {
            $prefijo = $this->repository->getPrefijoPeriodo($fechaEmision, (string) $periodo);
            $res     = $this->repository->getSiguienteCorrelativoPeriodo(
                $idPuntoEmision,
                $tipoDocumento,
                $prefijo,
                $secuencialInicial
            );
            // El número final se arma concatenando prefijo + correlativo, no sumando: si el
            // periodo agota sus dígitos, el número crece en vez de invadir el periodo siguiente.
            $formateado = $this->repository->componerSecuencialPeriodo(
                $prefijo,
                max($secuencialInicial, (int) $res['siguiente'])
            );
            $siguiente  = (int) $formateado;
        } else {
            // Techo solo en los tipos que admiten numeración por fecha: así, si este punto
            // estuvo en modo 'por_fecha' y volvió a 'consecutivo', los números con prefijo
            // de periodo que quedaron no arrastran la serie (ver TECHO_CONSECUTIVO).
            $techo = $this->repository->tipoPermiteModoPeriodo($tipoDocumento)
                ? $this->repository->getTechoConsecutivo()
                : null;

            $res        = $this->repository->getSiguienteDisponible($idPuntoEmision, $tipoDocumento, $secuencialInicial, $techo);
            $siguiente  = max($secuencialInicial, (int) $res['siguiente']);
            $formateado = str_pad((string) $siguiente, 9, '0', STR_PAD_LEFT);
        }

        if ((int) $res['total_usados'] === 0) {
            $detalle = $modo === SecuencialRepository::MODO_POR_FECHA
                ? 'Primer documento del periodo ' . $this->describirPeriodo($fechaEmision, (string) $periodo)
                : 'Primer documento - secuencial inicial';
        } elseif (!empty($res['es_gap'])) {
            $detalle = 'Número faltante detectado (gap) en la secuencia';
        } else {
            $detalle = $modo === SecuencialRepository::MODO_POR_FECHA
                ? 'Siguiente número del periodo ' . $this->describirPeriodo($fechaEmision, (string) $periodo)
                : 'Siguiente número consecutivo';
        }

        return [
            'secuencial'  => $siguiente,
            'formateado'  => $formateado,
            'es_gap'      => (bool) $res['es_gap'],
            'configurado' => $configurado,
            'detalle'     => $detalle,
            'modo'        => $modo,
            'periodo'     => $periodo,
        ];
    }

    /**
     * Etiqueta legible del periodo al que pertenece una fecha ("2026", "septiembre de 2026"),
     * para los mensajes que ve el usuario.
     */
    private function describirPeriodo(?string $fecha, string $periodo): string
    {
        $ts = ($fecha !== null && trim($fecha) !== '') ? strtotime($fecha) : false;
        if ($ts === false) {
            $ts = time();
        }

        if ($periodo !== SecuencialRepository::PERIODO_MENSUAL) {
            return date('Y', $ts);
        }

        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $meses[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
    }

    /**
     * Valida que un secuencial específico esté disponible para uso.
     * 
     * @param int         $idPuntoEmision ID del punto de emisión
     * @param string      $tipoDocumento  Tipo de documento
     * @param int         $secuencial     Número secuencial a validar
     * @param string|null $fechaEmision   Fecha del documento, para validar contra el piso del
     *                                    periodo cuando el tipo numera por fecha
     * @return array ['disponible' => bool, 'mensaje' => string]
     */
    public function validarSecuencial(int $idPuntoEmision, string $tipoDocumento, int $secuencial, ?string $fechaEmision = null): array
    {
        $config = $this->repository->getConfigSecuencial($idPuntoEmision, $tipoDocumento);
        $secuencialInicial = max(1, (int) $config['secuencial_inicial']);

        // En modo 'por_fecha' el mínimo no es el inicial pelado sino el primer número del
        // periodo al que pertenece la fecha (ej. 202609001, no 1).
        if (($config['modo_numeracion'] ?? '') === SecuencialRepository::MODO_POR_FECHA) {
            $secuencialInicial = (int) $this->repository->componerSecuencialPeriodo(
                $this->repository->getPrefijoPeriodo($fechaEmision, (string) $config['periodo_reinicio']),
                $secuencialInicial
            );
        }

        // No puede ser menor al inicial
        if ($secuencial < $secuencialInicial) {
            return [
                'disponible' => false,
                'mensaje'    => "El secuencial no puede ser menor al inicial configurado ({$secuencialInicial}).",
            ];
        }

        // Verificar si ya está en uso
        $enUso = $this->repository->secuencialEnUso($idPuntoEmision, $tipoDocumento, $secuencial);

        if ($enUso) {
            return [
                'disponible' => false,
                'mensaje'    => "El secuencial {$secuencial} ya está en uso.",
            ];
        }

        return [
            'disponible' => true,
            'mensaje'    => "Secuencial disponible.",
        ];
    }

    /**
     * Obtiene un resumen del estado de secuenciales para un punto de emisión.
     * Útil para mostrar información en la pestaña de configuración de la empresa.
     * 
     * @param int $idPuntoEmision ID del punto de emisión
     * @return array Array con info de cada tipo de documento
     */
    public function obtenerResumenPorPunto(int $idPuntoEmision): array
    {
        $configs = $this->repository->getAllConfigByPunto($idPuntoEmision);
        $resumen = [];

        foreach ($configs as $cfg) {
            $tipo = $cfg['tipo_documento'];
            $inicial = max(1, (int) $cfg['secuencial_inicial']);

            $siguiente = $this->obtenerSiguienteSecuencial($idPuntoEmision, $tipo);
            $maxUsado = $this->repository->getMaxSecuencialUsado($idPuntoEmision, $tipo);

            $resumen[] = [
                'tipo_documento'     => $tipo,
                'secuencial_inicial' => $inicial,
                'max_usado'          => $maxUsado,
                'siguiente'          => $siguiente['secuencial'],
                'siguiente_fmt'      => $siguiente['formateado'],
                'tiene_gaps'         => $siguiente['es_gap'],
                'modo_numeracion'    => $siguiente['modo'],
                'periodo_reinicio'   => $siguiente['periodo'],
            ];
        }

        return $resumen;
    }

    /**
     * Verifica si el tipo de documento está soportado para consulta automática.
     */
    public function tipoSoportado(string $tipoDocumento): bool
    {
        return $this->repository->tipoDocumentoSoportado($tipoDocumento);
    }

    /**
     * ¿Este tipo de documento puede numerar por fecha de emisión? (falso en los electrónicos).
     */
    public function tipoPermiteModoPeriodo(string $tipoDocumento): bool
    {
        return $this->repository->tipoPermiteModoPeriodo($tipoDocumento);
    }

    /**
     * Tipos que ofrecen la opción de numerar por fecha, para pintar los selectores en
     * Empresa → Secuenciales.
     *
     * @return string[]
     */
    public function getTiposConModoPeriodo(): array
    {
        return $this->repository->getTiposConModoPeriodo();
    }

    /**
     * ¿La base ya tiene las columnas de modo/periodo? Mientras la migración
     * 20260909_secuencial_modo_periodo.sql no se haya ejecutado, la opción no se ofrece.
     */
    public function soportaModoPeriodo(): bool
    {
        return $this->repository->soportaModoPeriodo();
    }

    /**
     * Retorna la lista de tipos de documentos soportados.
     */
    public function getTiposDocumento(): array
    {
        return $this->repository->getTiposDocumentoSoportados();
    }
}
