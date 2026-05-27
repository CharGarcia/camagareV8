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
     * 2. Obtener todos los secuenciales ya usados en la tabla del documento
     * 3. Desde el secuencial_inicial, buscar el primer número NO usado (gap detection)
     * 4. Si no hay gaps, retornar max_usado + 1
     * 5. Nunca retornar un número inferior al secuencial_inicial
     * 
     * @param int    $idPuntoEmision  ID del punto de emisión
     * @param string $tipoDocumento   Tipo de documento (ej: 'Facturas de venta')
     * @return array ['secuencial' => int, 'formateado' => string, 'es_gap' => bool, 'detalle' => string]
     */
    public function obtenerSiguienteSecuencial(int $idPuntoEmision, string $tipoDocumento): array
    {
        // 1. Obtener configuración del secuencial inicial
        $config = $this->repository->getConfigSecuencial($idPuntoEmision, $tipoDocumento);
        $secuencialInicial = max(1, (int) $config['secuencial_inicial']);

        // 2. Obtener secuenciales ya usados
        $usados = $this->repository->getSecuencialesUsados($idPuntoEmision, $tipoDocumento);

        // Si no hay documentos emitidos, el siguiente es el inicial
        if (empty($usados)) {
            return [
                'secuencial'  => $secuencialInicial,
                'formateado'  => str_pad((string) $secuencialInicial, 9, '0', STR_PAD_LEFT),
                'es_gap'      => false,
                'detalle'     => 'Primer documento - secuencial inicial',
            ];
        }

        // 3. Normalizar los secuenciales usados (pueden estar guardados como "000000100" o "100")
        $usadosNormalizados = array_map(function ($sec) {
            return (int) ltrim((string) $sec, '0') ?: 0;
        }, $usados);

        // Eliminar duplicados y ordenar
        $usadosSet = array_unique($usadosNormalizados);
        sort($usadosSet);

        // 4. Buscar el primer hueco desde el secuencial_inicial
        $gap = $this->encontrarPrimerGap($secuencialInicial, $usadosSet);

        if ($gap !== null) {
            return [
                'secuencial'  => $gap,
                'formateado'  => str_pad((string) $gap, 9, '0', STR_PAD_LEFT),
                'es_gap'      => true,
                'detalle'     => "Número faltante detectado (gap) en la secuencia",
            ];
        }

        // 5. Sin gaps → siguiente después del máximo
        $maxUsado = end($usadosSet);
        $siguiente = max($maxUsado + 1, $secuencialInicial);

        return [
            'secuencial'  => $siguiente,
            'formateado'  => str_pad((string) $siguiente, 9, '0', STR_PAD_LEFT),
            'es_gap'      => false,
            'detalle'     => 'Siguiente número consecutivo',
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
     * Busca el primer número faltante (gap) en una secuencia a partir de un inicio dado.
     * 
     * @param int   $inicio    Número desde el cual empezar a buscar
     * @param array $usadosSet Conjunto ordenado de números ya utilizados
     * @return int|null El número del gap, o null si no hay gaps
     */
    private function encontrarPrimerGap(int $inicio, array $usadosSet): ?int
    {
        if (empty($usadosSet)) {
            return null;
        }

        // Crear un set para búsqueda O(1)
        $usadosFlip = array_flip($usadosSet);

        // Obtener el máximo para saber hasta dónde buscar
        $max = end($usadosSet);

        // Solo buscar gaps si el inicial es <= al máximo usado
        // (si es mayor, no hay gaps, simplemente se empieza desde el inicial)
        if ($inicio > $max) {
            return null;
        }

        // Buscar desde el inicio hasta el máximo usado
        for ($i = $inicio; $i <= $max; $i++) {
            if (!isset($usadosFlip[$i])) {
                return $i;
            }
        }

        return null;
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
