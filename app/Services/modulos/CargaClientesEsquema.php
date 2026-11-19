<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Esquema del libro de Excel para la carga masiva de clientes.
 *
 * Única fuente de verdad sobre hojas y columnas: la usan el generador de la
 * plantilla, el validador y la vista, de modo que nunca se desincronicen.
 *
 * REGLA para el usuario: el libro debe conservar EXACTAMENTE estas hojas.
 * No se pueden borrar ni agregar hojas, ni renombrar o reordenar columnas.
 */
class CargaClientesEsquema
{
    // ── Hoja de datos (el usuario la edita) ─────────────────────────────────
    public const HOJA_CLIENTES = 'Clientes';

    // ── Hojas de referencia (bloqueadas, solo consulta) ──────────────────────
    public const HOJA_INSTRUCCIONES   = 'Instrucciones';
    public const HOJA_REF_TIPOS       = 'Ref_Tipos';
    public const HOJA_REF_PROVINCIAS  = 'Ref_Provincias';
    public const HOJA_REF_CIUDADES    = 'Ref_Ciudades';
    public const HOJA_REF_VENDEDORES  = 'Ref_Vendedores';
    public const HOJA_REF_FORMAS_PAGO = 'Ref_FormasPago';

    // ── Hoja oculta de control ───────────────────────────────────────────────
    public const HOJA_CONFIG = '_Config';

    /** Columnas de la hoja Clientes, en orden. La primera es la llave. */
    public const COLUMNAS = [
        'IDENTIFICACION',
        'TIPO_IDENTIFICACION',
        'NOMBRE',
        'EMAIL',
        'TELEFONO',
        'DIRECCION',
        'PROVINCIA',
        'CIUDAD',
        'PLAZO',
        'VENDEDOR',
        'FORMA_PAGO_SRI',
        'ESTADO',
        'OBSERVACIONES',
        'DIAS_VISITA',
        'FRECUENCIA_VISITA',
        'SEMANAS_VISITA',
        'HORA_VISITA_DESDE',
        'HORA_VISITA_HASTA',
        'ORDEN_VISITA',
        'OBSERVACION_VISITA',
    ];

    /** Columnas que viajan como texto (no se deben convertir en número/fecha/hora). */
    public const COLUMNAS_TEXTO = [
        'IDENTIFICACION',
        'TELEFONO',
        'FORMA_PAGO_SRI',
        'DIAS_VISITA',
        'SEMANAS_VISITA',
        'HORA_VISITA_DESDE',
        'HORA_VISITA_HASTA',
    ];

    /**
     * Tipos de identificación de cliente (identificador_comprador_vendedor, tipo = 1):
     * código SRI => rótulo que se escribe en el Excel.
     */
    public const TIPOS = [
        '04' => 'RUC',
        '05' => 'Cedula',
        '06' => 'Pasaporte',
        '07' => 'Consumidor final',
        '08' => 'Exterior',
    ];

    /** Identificación del consumidor final (SRI). */
    public const CONSUMIDOR_FINAL = '9999999999999';

    /**
     * Columnas que se pueden elegir en "Actualizar solo algunas columnas", con su
     * rótulo. IDENTIFICACION no figura: es la llave y nunca se modifica.
     */
    public static function columnasElegibles(): array
    {
        return [
            'TIPO_IDENTIFICACION' => 'Tipo de identificación',
            'NOMBRE'              => 'Nombre / razón social',
            'EMAIL'               => 'Correo electrónico',
            'TELEFONO'            => 'Teléfono',
            'DIRECCION'           => 'Dirección',
            'PROVINCIA'           => 'Provincia',
            'CIUDAD'              => 'Ciudad',
            'PLAZO'               => 'Plazo de crédito (días)',
            'VENDEDOR'            => 'Vendedor',
            'FORMA_PAGO_SRI'      => 'Forma de pago SRI',
            'ESTADO'              => 'Estado (Activo/Inactivo)',
            'OBSERVACIONES'       => 'Observaciones',
            'DIAS_VISITA'         => 'Días de visita',
            'FRECUENCIA_VISITA'   => 'Frecuencia de visita',
            'SEMANAS_VISITA'      => 'Semanas de visita',
            'HORA_VISITA_DESDE'   => 'Hora de visita desde',
            'HORA_VISITA_HASTA'   => 'Hora de visita hasta',
            'ORDEN_VISITA'        => 'Orden de visita',
            'OBSERVACION_VISITA'  => 'Observación de visita',
        ];
    }

    /** Índice (0-based) de una columna de la hoja Clientes. */
    public static function indice(string $columna): int
    {
        $i = array_search($columna, self::COLUMNAS, true);
        if ($i === false) {
            throw new \InvalidArgumentException('Columna desconocida: ' . $columna);
        }
        return $i;
    }

    /** Hojas de referencia con sus encabezados. */
    public static function hojasReferencia(): array
    {
        return [
            self::HOJA_REF_TIPOS       => ['TIPO_IDENTIFICACION', 'CODIGO_SRI'],
            self::HOJA_REF_PROVINCIAS  => ['PROVINCIA', 'CODIGO'],
            self::HOJA_REF_CIUDADES    => ['PROVINCIA', 'CIUDAD', 'CODIGO'],
            self::HOJA_REF_VENDEDORES  => ['VENDEDOR', 'IDENTIFICACION', 'ESTADO'],
            self::HOJA_REF_FORMAS_PAGO => ['FORMA_PAGO_SRI', 'NOMBRE'],
        ];
    }

    /** Nombres de TODAS las hojas que debe tener el libro, en orden. */
    public static function todasLasHojas(): array
    {
        return array_merge(
            [self::HOJA_INSTRUCCIONES, self::HOJA_CLIENTES],
            array_keys(self::hojasReferencia()),
            [self::HOJA_CONFIG]
        );
    }

    /** Texto de la hoja Instrucciones (una línea por elemento). */
    public static function textoInstrucciones(): array
    {
        return [
            'CARGA Y ACTUALIZACIÓN DE CLIENTES',
            '',
            'REGLAS IMPORTANTES',
            '1. NO borre ni agregue hojas a este libro. Debe conservar exactamente las hojas originales.',
            '2. NO renombre, elimine ni cambie el orden de las columnas.',
            '3. Escriba únicamente en la hoja Clientes. Las hojas que empiezan con "Ref_" son de consulta.',
            '',
            'CÓMO FUNCIONA',
            '- La columna IDENTIFICACION (cédula, RUC o pasaporte) identifica al cliente.',
            '  Si ya existe, el cliente se ACTUALIZA; si no existe, se CREA.',
            '- La IDENTIFICACION no se puede cambiar desde este archivo.',
            '- Las filas que no cambió se ignoran: no se reescriben ni quedan en la auditoría.',
            '- En la carga completa, una celda vacía BORRA ese dato del cliente',
            '  (por ejemplo, un VENDEDOR vacío deja al cliente sin vendedor).',
            '- Al subir el archivo puede elegir "Actualizar solo algunas columnas": entonces',
            '  solo cambian las columnas marcadas, el resto se conserva, y no se crean clientes.',
            '- Desde este archivo NO se elimina. Para dejar de usar un cliente, ponga',
            '  ESTADO = Inactivo.',
            '- La forma de cobro predeterminada, el cobro automático y la ubicación en el mapa',
            '  no están en la plantilla y nunca se tocan.',
            '',
            'COLUMNAS',
            '- TIPO_IDENTIFICACION: RUC, Cedula, Pasaporte, Consumidor final o Exterior.',
            '  Si la deja vacía, se deduce: 10 dígitos = Cedula, 13 dígitos = RUC.',
            '- EMAIL: obligatorio. Varios correos se separan con coma.',
            '- PROVINCIA y CIUDAD: escríbalas como aparecen en Ref_Provincias y Ref_Ciudades.',
            '- PLAZO: días de crédito (0 = contado).',
            '- VENDEDOR: nombre como aparece en Ref_Vendedores (o su identificación).',
            '- FORMA_PAGO_SRI: código de la hoja Ref_FormasPago (por ejemplo 20).',
            '- ESTADO: Activo o Inactivo.',
            '',
            'VISITAS DEL VENDEDOR (opcional)',
            '- DIAS_VISITA: días separados por coma: Lun, Mar, Mie, Jue, Vie, Sab, Dom (o 1 a 7).',
            '- FRECUENCIA_VISITA: Semanal, Quincenal o Mensual (obligatoria si hay días).',
            '- SEMANAS_VISITA: semanas del mes separadas por coma (1 a 5). Obligatoria en',
            '  Quincenal y Mensual; en Semanal se ignora.',
            '- HORA_VISITA_DESDE / HORA_VISITA_HASTA: formato HH:MM (por ejemplo 08:30).',
            '- Sin DIAS_VISITA no hay ruta: se borran la frecuencia, semanas, horario y orden.',
            '',
            'CUIDADO CON EXCEL',
            '- Las columnas de identificación y teléfono están en formato texto para no perder',
            '  el cero inicial. Si pega datos de otro archivo, use "Pegar solo valores".',
        ];
    }
}
