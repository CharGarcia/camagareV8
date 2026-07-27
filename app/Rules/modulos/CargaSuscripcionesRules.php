<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Services\modulos\CargaSuscripcionesEsquema;

/**
 * Validaciones de negocio para la carga masiva de suscripciones.
 *
 * Solo reglas puras (obligatorios, formatos, coherencia entre campos). La
 * resolución de catálogos contra la base de datos la hace el Service, que tiene
 * los mapas precargados.
 *
 * Cada método devuelve un array de mensajes de error; vacío = válido.
 */
class CargaSuscripcionesRules
{
    public const MAX_CLAVE       = 50;
    public const MAX_OBSERVACION  = 500;
    public const MAX_INFO         = 300;
    public const MAX_DESCRIPCION  = 300;

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Suscripciones (cabecera)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $f Fila normalizada de la hoja Suscripciones.
     * @return string[]
     */
    public function validarSuscripcion(array $f): array
    {
        $e = [];

        if ($f['clave'] === '') {
            $e[] = 'CLAVE es obligatoria.';
        } elseif (mb_strlen($f['clave']) > self::MAX_CLAVE) {
            $e[] = 'CLAVE no puede exceder ' . self::MAX_CLAVE . ' caracteres.';
        }

        if ($f['ruc_cliente'] === '') {
            $e[] = 'RUC_CLIENTE es obligatorio.';
        }

        if ($f['periodicidad'] === '') {
            $e[] = 'PERIODICIDAD es obligatoria.';
        }

        if ($f['fecha_inicio'] === '') {
            $e[] = 'FECHA_INICIO es obligatoria.';
        } elseif ($f['fecha_inicio'] === false) {
            $e[] = 'FECHA_INICIO no tiene un formato válido (use AAAA-MM-DD).';
        }

        if ($f['fecha_fin'] === false) {
            $e[] = 'FECHA_FIN no tiene un formato válido (use AAAA-MM-DD).';
        } elseif (!empty($f['fecha_fin']) && !empty($f['fecha_inicio'])
            && $f['fecha_inicio'] !== false && $f['fecha_fin'] <= $f['fecha_inicio']) {
            $e[] = 'FECHA_FIN debe ser posterior a FECHA_INICIO.';
        }

        if ($f['proximo_cobro'] === false) {
            $e[] = 'PROXIMO_COBRO no tiene un formato válido (use AAAA-MM-DD).';
        }

        if ($f['forma_cobro'] === null) {
            $e[] = 'FORMA_COBRO debe ser "Credito" o "Tarjeta".';
        }

        if ($f['tipo_comprobante'] === null) {
            $e[] = 'TIPO_COMPROBANTE debe ser "Factura" o "Recibo".';
        }

        if ($f['estado'] === null) {
            $e[] = 'ESTADO debe ser Activo, Pausado, Suspendido o Cancelado.';
        }

        if (mb_strlen($f['observaciones']) > self::MAX_OBSERVACION) {
            $e[] = 'OBSERVACIONES no puede exceder ' . self::MAX_OBSERVACION . ' caracteres.';
        }

        // La info adicional es opcional, pero si se llena uno de los dos, el otro
        // también (concepto y detalle van juntos).
        if (($f['info_concepto'] === '') !== ($f['info_detalle'] === '')) {
            $e[] = 'INFO_CONCEPTO e INFO_DETALLE deben llenarse juntos o dejarse ambos vacíos.';
        }

        return $e;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Detalle
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $f Fila normalizada de la hoja Detalle.
     * @return string[]
     */
    public function validarDetalle(array $f): array
    {
        $e = [];

        if ($f['codigo_producto'] === '') {
            $e[] = 'CODIGO_PRODUCTO es obligatorio.';
        }

        if ($f['cantidad'] === null) {
            $e[] = 'CANTIDAD debe ser un número.';
        } elseif ($f['cantidad'] <= 0) {
            $e[] = 'CANTIDAD debe ser mayor que cero.';
        }

        // El precio es opcional (se hereda del producto). Si viene, debe ser válido.
        if ($f['precio_unitario'] === null) {
            $e[] = 'PRECIO_UNITARIO debe ser un número.';
        } elseif ($f['precio_unitario'] < 0) {
            $e[] = 'PRECIO_UNITARIO no puede ser negativo.';
        }

        if (mb_strlen($f['descripcion']) > self::MAX_DESCRIPCION) {
            $e[] = 'DESCRIPCION no puede exceder ' . self::MAX_DESCRIPCION . ' caracteres.';
        }

        return $e;
    }
}
