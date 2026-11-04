<?php

declare(strict_types=1);

namespace App\Helpers;

use App\models\Empresa;
use App\repositories\modulos\EmpresaRepository;

/**
 * Datos de la empresa con la configuración del establecimiento DE LA SERIE del
 * documento (decimales de cantidad/precio, configuración de facturación, logo,
 * dirección del establecimiento, leyendas del PDF).
 *
 * Antes cada módulo copiaba un bloque que tomaba el PRIMER establecimiento de la
 * empresa ($establecimientos[0]): un documento de otro establecimiento se autorizaba
 * con sus decimales (SriEnvioService sí usa el del documento), pero el PDF, el correo
 * o el XML regenerado salían con los del primero. Misma regla que el IVA
 * (CLAUDE.md §9): la serie del documento manda.
 *
 * Resolución: por id_establecimiento; si falta (documentos antiguos), por el punto
 * de emisión; y solo como último recurso, el primer establecimiento.
 *
 * Uso:
 *     $empresa = ConfigEstablecimiento::empresa($idEmpresa, $idEst, $idPunto);
 *     $empresa = ConfigEstablecimiento::paraDocumento($idEmpresa, $cabecera);
 *
 * Este helper NO aplica el logo del punto de emisión: el llamador debe hacer siempre
 * LogoPuntoEmision::aplicar($empresa, $idEmpresa, $doc['id_punto_emision']) DESPUÉS,
 * para que el logo propio del punto gane sobre el del establecimiento.
 */
final class ConfigEstablecimiento
{
    /**
     * @param array|null $base Datos de empresa ya cargados (si no, se leen).
     * @param bool       $conLeyendas Copiar las leyendas del PDF del establecimiento (los
     *                         recibos no las llevan, igual que antes).
     */
    public static function empresa(int $idEmpresa, ?int $idEstablecimiento, ?int $idPunto = null, ?array $base = null, bool $conLeyendas = true): array
    {
        $empresaModel = new Empresa();
        $empresa      = $base ?? ($empresaModel->getPorId($idEmpresa) ?? []);

        $est = self::establecimiento($idEmpresa, $idEstablecimiento, $idPunto);
        if ($est === null) {
            return $empresa;
        }

        $propios = $conLeyendas ? ['logo_ruta', 'leyenda_pdf_titulo', 'leyenda_pdf_mensaje'] : ['logo_ruta'];

        foreach ($propios as $k) {
            if (!empty($est[$k])) $empresa[$k] = $est[$k];
        }
        if (!empty($est['direccion'])) {
            $empresa['direccion_establecimiento'] = $est['direccion'];
        }

        try {
            $estConfig = (new EmpresaRepository())->getEstablecimientoConfig((int) $est['id']);
            if ($estConfig) {
                $estConfig['direccion_matriz']          = $empresa['direccion'] ?? '';
                $estConfig['direccion_establecimiento'] = $est['direccion'] ?? '';
                foreach ($propios as $k) {
                    if (!empty($est[$k])) $estConfig[$k] = $est[$k];
                }
                $empresa = array_merge($empresa, $estConfig);
            }
        } catch (\Throwable $e) {
            // Migración pendiente: se quedan los valores por defecto.
        }

        return $empresa;
    }

    /** empresa() a partir de la cabecera de un documento (id_establecimiento / id_punto_emision). */
    public static function paraDocumento(int $idEmpresa, array $doc, ?array $base = null, bool $conLeyendas = true): array
    {
        return self::empresa(
            $idEmpresa,
            !empty($doc['id_establecimiento']) ? (int) $doc['id_establecimiento'] : null,
            !empty($doc['id_punto_emision'])   ? (int) $doc['id_punto_emision']   : null,
            $base,
            $conLeyendas
        );
    }

    /**
     * Fila del establecimiento que corresponde a la serie (null si la empresa no tiene
     * establecimientos). Solo establecimientos de la propia empresa.
     */
    public static function establecimiento(int $idEmpresa, ?int $idEstablecimiento, ?int $idPunto = null): ?array
    {
        $empresaModel     = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (empty($establecimientos)) {
            return null;
        }

        if ($idEstablecimiento) {
            foreach ($establecimientos as $e) {
                if ((int) $e['id'] === $idEstablecimiento) return $e;
            }
        }
        if ($idPunto) {
            foreach ($establecimientos as $e) {
                foreach ($empresaModel->getPuntosEmision((int) $e['id'], false) as $p) {
                    if ((int) $p['id'] === $idPunto) return $e;
                }
            }
        }
        return $establecimientos[0];
    }
}
