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
     * @param int    $idPuntoEmision  ID del punto de emisión
     * @param string $tipoDocumento   Tipo de documento (ej: 'Facturas de venta')
     * @return array ['secuencial' => int, 'formateado' => string, 'es_gap' => bool, 'detalle' => string]
     */
    public function obtenerSiguienteSecuencial(int $idPuntoEmision, string $tipoDocumento): array
    {
        // Bloqueo por punto de emisión + tipo de documento: evita que dos documentos
        // emitidos casi al mismo tiempo calculen el mismo "siguiente número" (ver
        // CLAUDE.md §8). Solo protege de verdad si el llamador ya abrió su transacción
        // ANTES de llegar aquí y no la cierra hasta insertar la cabecera del documento
        // (pg_advisory_xact_lock se libera al COMMIT/ROLLBACK, no antes).
        $this->repository->lockSecuencial($idPuntoEmision, $tipoDocumento);

        // 1. Obtener configuración del secuencial inicial
        $config = $this->repository->getConfigSecuencial($idPuntoEmision, $tipoDocumento);
        // ¿Existe realmente una configuración de secuencial para este punto+tipo?
        // (getConfigSecuencial devuelve id=null y secuencial_inicial=1 cuando NO hay.)
        $configurado = !empty($config['id']);
        $secuencialInicial = max(1, (int) $config['secuencial_inicial']);

        // 2. El cálculo (hueco o siguiente al máximo) lo resuelve el motor en una sola consulta.
        //    Antes se traían TODOS los secuenciales del punto a memoria y se recorría el rango
        //    [inicial .. máximo] número por número en PHP: con un punto que ya llegó al 500.000,
        //    medio millón de iteraciones en cada emisión.
        $res = $this->repository->getSiguienteDisponible($idPuntoEmision, $tipoDocumento, $secuencialInicial);

        $siguiente = max($secuencialInicial, (int) $res['siguiente']);

        if ((int) $res['total_usados'] === 0) {
            $detalle = 'Primer documento - secuencial inicial';
        } elseif (!empty($res['es_gap'])) {
            $detalle = 'Número faltante detectado (gap) en la secuencia';
        } else {
            $detalle = 'Siguiente número consecutivo';
        }

        return [
            'secuencial'  => $siguiente,
            'formateado'  => str_pad((string) $siguiente, 9, '0', STR_PAD_LEFT),
            'es_gap'      => (bool) $res['es_gap'],
            'configurado' => $configurado,
            'detalle'     => $detalle,
        ];
    }

    /**
     * Valida que un secuencial específico esté disponible para uso.
     * 
     * @param int    $idPuntoEmision  ID del punto de emisión
     * @param string $tipoDocumento   Tipo de documento
     * @param int    $secuencial      Número secuencial a validar
     * @return array ['disponible' => bool, 'mensaje' => string]
     */
    public function validarSecuencial(int $idPuntoEmision, string $tipoDocumento, int $secuencial): array
    {
        $config = $this->repository->getConfigSecuencial($idPuntoEmision, $tipoDocumento);
        $secuencialInicial = max(1, (int) $config['secuencial_inicial']);

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
     * Retorna la lista de tipos de documentos soportados.
     */
    public function getTiposDocumento(): array
    {
        return $this->repository->getTiposDocumentoSoportados();
    }
}
