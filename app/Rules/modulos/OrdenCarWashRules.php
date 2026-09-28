<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio para el módulo Servicio Car-Wash.
 */
class OrdenCarWashRules
{
    /** Estados válidos: nace 'borrador'; pasa a 'facturado' al emitir; 'anulado' si se anula. */
    public const ESTADOS = ['borrador', 'facturado', 'anulado'];

    public function validarCreacion(array $data): void
    {
        if (empty($data['id_vehiculo'])) {
            throw new Exception("Debe seleccionar un vehículo.");
        }
        // El cliente NO es obligatorio al registrar la orden (sí al facturar).
        if (empty($data['fecha_ingreso'])) {
            throw new Exception("La fecha de ingreso es obligatoria.");
        }
        if (empty($data['id_punto_emision']) || (string)($data['secuencial'] ?? '') === '') {
            throw new Exception("Falta la serie / secuencial. Seleccione el punto de emisión.");
        }
        if (empty($data['detalles']) || !is_array($data['detalles'])) {
            throw new Exception("Debe agregar al menos un servicio o producto.");
        }

        $tieneLineas = false;
        foreach ($data['detalles'] as $idx => $det) {
            $cant = (float) ($det['cantidad'] ?? 0);
            $desc = trim((string) ($det['descripcion'] ?? ''));
            if ($cant <= 0 && $desc === '') {
                continue; // línea vacía, se ignora
            }
            if ($desc === '') {
                throw new Exception("Hay una línea sin descripción en la fila " . ($idx + 1) . ".");
            }
            if ($cant <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0 en \"" . $desc . "\".");
            }
            $tieneLineas = true;
        }
        if (!$tieneLineas) {
            throw new Exception("Debe agregar al menos un servicio o producto con cantidad mayor a 0.");
        }

        // Solo al crear: una orden antigua (o migrada) con su cita ya pasada debe poder editarse.
        if (empty($data['id']) && !empty($data['proxima_cita'])) {
            $cita = strtotime((string) $data['proxima_cita']);
            $hoy  = strtotime(date('Y-m-d'));
            if ($cita !== false && $cita < $hoy) {
                throw new Exception("La fecha de la próxima cita no puede ser anterior a hoy.");
            }
        }
    }

    /**
     * Valida que se pueda generar un documento de venta desde la orden.
     */
    public function validarGeneracionDocumento(array $orden, string $tipo, array $extra): void
    {
        $tipo = strtoupper($tipo);
        if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) {
            throw new Exception("Tipo de documento no válido.");
        }
        $estado = (string) ($orden['estado'] ?? 'borrador');
        if ($estado === 'anulado') {
            throw new Exception("La orden está anulada; no se puede facturar.");
        }
        // puede_facturar lo calcula el Service: sin documento, o con su documento anulado/eliminado.
        if (empty($orden['puede_facturar'])) {
            throw new Exception("Esta orden ya generó un documento vigente (" . ($orden['numero_documento'] ?? '') . "). Para volver a facturarla, anule primero ese documento.");
        }
        if (empty($orden['id_cliente'])) {
            throw new Exception("Debe asignar un cliente a la orden antes de facturar.");
        }
    }

    /**
     * Configuración de facturación del establecimiento, IGUAL que FacturaVentaRules
     * (validarReglasEstablecimiento / validarItemInventario), para que la orden no acepte
     * nada que luego la factura o el recibo rechazarían:
     *  - facturacion_libre = false → no se permiten ítems libres (fuera del catálogo).
     *  - obligatorio_lotes / obligatorio_caducidad / obligatorio_nup → exigidos en los
     *    productos inventariables (no en servicios '02', ítems libres ni no inventariables).
     *
     * @param array $detalles líneas enriquecidas con `inventariable` y `tipo_produccion`.
     */
    public function validarConfiguracionFacturacion(array $detalles, array $estConfig): void
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $facturacionLibre = $toBool($estConfig['facturacion_libre'] ?? true);

        $num = 0;
        foreach ($detalles as $d) {
            if ((float) ($d['cantidad'] ?? 0) <= 0 || trim((string) ($d['descripcion'] ?? '')) === '') continue;
            $num++;
            $esLibre = empty($d['id_producto']);
            $nombre  = trim((string) ($d['descripcion'] ?? '')) ?: "Línea #{$num}";

            if (!$facturacionLibre && $esLibre) {
                throw new Exception("Línea #{$num}: No se permite el ingreso de ítems libres. Debe seleccionar productos del catálogo.");
            }
            if ($esLibre || trim((string) ($d['tipo_produccion'] ?? '')) === '02' || !$toBool($d['inventariable'] ?? false)) {
                continue;
            }
            $lote = trim((string) ($d['lote'] ?? ''));
            if ($toBool($estConfig['obligatorio_lotes'] ?? false) && ($lote === '' || $lote === 'sin_lote')) {
                throw new Exception("{$nombre}: El número de lote es obligatorio para productos inventariables.");
            }
            if ($toBool($estConfig['obligatorio_caducidad'] ?? false) && empty($d['caducidad'])) {
                throw new Exception("{$nombre}: La fecha de caducidad es obligatoria para productos inventariables.");
            }
            if ($toBool($estConfig['obligatorio_nup'] ?? false) && trim((string) ($d['nup'] ?? '')) === '') {
                throw new Exception("{$nombre}: El número de serie (NUP) es obligatorio para productos inventariables.");
            }
        }
    }

    public function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new Exception("Estado no válido.");
        }
    }
}
