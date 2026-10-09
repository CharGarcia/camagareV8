<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de los cobros desde la pestaña Condóminos (Configuración de condominios):
 * emisión en bloque de recibos/facturas y alta de cobros recurrentes en Suscripciones.
 * Los mensajes llevan «|#id» para que la vista enfoque el control (prefijo cob_).
 */
class CondominioCobroRules
{
    public const MODOS     = ['emitir', 'suscripcion'];
    public const MAX_DESTINATARIOS = 3000;

    /** Normaliza y valida lo común a los dos modos; devuelve los datos limpios. */
    public function normalizar(array $d): array
    {
        $n = [
            'modo'             => in_array($d['modo'] ?? '', self::MODOS, true) ? $d['modo'] : '',
            'id_producto'      => (int) ($d['id_producto'] ?? 0),
            'forma_valor'      => ($d['forma_valor'] ?? 'fijo') === 'inmueble' ? 'inmueble' : 'fijo',
            'valor'            => round((float) str_replace(',', '.', (string) ($d['valor'] ?? 0)), 2),
            'agrupar'          => ($d['agrupar'] ?? 'cliente') === 'inmueble' ? 'inmueble' : 'cliente',
            'tipo_comprobante' => ($d['tipo_comprobante'] ?? 'recibo') === 'factura' ? 'factura' : 'recibo',
            'id_punto_emision' => (int) ($d['id_punto_emision'] ?? 0),
            'descripcion'      => mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 200),
            'texto_item'       => mb_substr(trim((string) ($d['texto_item'] ?? '')), 0, 300),
            'enviar_correo'    => in_array((string) ($d['enviar_correo'] ?? ''), ['1', 'true', 'si', 'on'], true),
            'id_periodicidad'  => (int) ($d['id_periodicidad'] ?? 0),
            'fecha_inicio'     => trim((string) ($d['fecha_inicio'] ?? '')),
            'destino'          => ($d['destino'] ?? 'marcados') === 'filtro' ? 'filtro' : 'marcados',
            'ids'              => array_values(array_filter(array_map('intval', explode(',', (string) ($d['ids'] ?? ''))), fn($i) => $i > 0)),
            'buscar'           => trim((string) ($d['buscar'] ?? '')),
            'seleccion'        => array_values(array_filter(array_map('trim', explode(',', (string) ($d['seleccion'] ?? ''))), fn($s) => $s !== '')),
        ];

        if ($n['modo'] === '') {
            throw new \InvalidArgumentException('Elija qué desea hacer: emitir ahora o agregar a suscripciones.|#cob_modo');
        }
        if ($n['id_producto'] <= 0) {
            throw new \InvalidArgumentException('Elija el concepto (servicio de Productos) que se cobra.|#cob_producto_txt');
        }
        if ($n['forma_valor'] === 'inmueble' && $n['agrupar'] !== 'inmueble') {
            throw new \InvalidArgumentException('La cuota del inmueble solo aplica cuando se genera uno por inmueble.|#cob_agrupar');
        }
        if ($n['forma_valor'] === 'fijo' && ($n['valor'] <= 0 || $n['valor'] > 999999)) {
            throw new \InvalidArgumentException('Indique un valor mayor que cero.|#cob_valor');
        }
        if ($n['destino'] === 'marcados' && !$n['ids']) {
            throw new \InvalidArgumentException('Marque al menos un condómino en el listado, o elija «todos los del filtro actual».|#cob_destino');
        }

        if ($n['modo'] === 'emitir') {
            if ($n['id_punto_emision'] <= 0) {
                throw new \InvalidArgumentException('Elija la serie con la que se emiten los documentos.|#cob_id_punto_emision');
            }
            if ($n['descripcion'] === '') {
                throw new \InvalidArgumentException('Escriba una descripción (p. ej. «Multa por ruido, octubre 2026»).|#cob_descripcion');
            }
        } else {
            if ($n['id_periodicidad'] <= 0) {
                throw new \InvalidArgumentException('Elija la periodicidad de la suscripción.|#cob_id_periodicidad');
            }
            $f = \DateTime::createFromFormat('Y-m-d', $n['fecha_inicio']);
            if (!$f || $f->format('Y-m-d') !== $n['fecha_inicio']) {
                throw new \InvalidArgumentException('Indique la fecha del primer cobro.|#cob_fecha_inicio');
            }
        }
        return $n;
    }
}
