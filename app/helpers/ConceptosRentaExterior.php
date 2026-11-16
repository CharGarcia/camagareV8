<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Conceptos de retención en la fuente de Renta para PAGOS A NO RESIDENTES
 * (Tabla 3.10 del Catálogo ATS, módulo "Pago a no residente", vigente desde
 * 01-07-2024). Catálogo GLOBAL del SRI: no depende de empresa.
 *
 * Uso: en una compra o liquidación pagada al exterior SIN retención de Renta
 * (p. ej. compra de bienes a un proveedor de un país con convenio de doble
 * tributación, como Colombia por la Decisión 578 de la CAN), el ATS necesita
 * igual una línea de Renta. El 332 ("no sujeto") solo vale para pagos a
 * residentes y el SRI lo rechaza con pagoLocExt = 02; por eso el usuario elige
 * aquí el concepto y el ATS lo reporta con la base del documento, 0 % y 0,00
 * (AtsService). No genera ninguna retención: es solo el dato del anexo.
 */
final class ConceptosRentaExterior
{
    public const CATALOGO = [
        '500'  => 'Rentas inmobiliarias',
        '501'  => 'Beneficios / servicios empresariales',
        '501A' => 'Servicios técnicos, administrativos o de consultoría y regalías',
        '503'  => 'Navegación marítima y/o aérea',
        '504'  => 'Dividendos',
        '505'  => 'Rendimientos financieros',
        '505A' => 'Intereses de créditos de instituciones financieras del exterior',
        '505B' => 'Intereses de créditos de gobierno a gobierno',
        '505C' => 'Intereses de créditos de organismos multilaterales',
        '505D' => 'Intereses por financiamiento de proveedores externos',
        '505E' => 'Intereses de otros créditos externos',
        '505F' => 'Otros intereses y rendimientos financieros',
        '509'  => 'Cánones, derechos de autor, marcas, patentes y similares',
        '509A' => 'Regalías por concepto de franquicias',
        '510'  => 'Otras ganancias de capital',
        '511'  => 'Servicios profesionales independientes',
        '512'  => 'Servicios profesionales dependientes',
        '513A' => 'Deportistas',
        '514'  => 'Participación de consejeros',
        '515'  => 'Artistas y espectáculos artísticos y culturales en Ecuador',
        '516'  => 'Pensiones',
        '517'  => 'Reembolso de gastos',
        '518'  => 'Funciones públicas',
        '519'  => 'Estudiantes',
        '520A' => 'Servicios hoteleros y turísticos en el exterior',
        '520B' => 'Arrendamiento mercantil internacional',
        '520D' => 'Comisiones por exportaciones y promoción de turismo receptivo',
        '520E' => 'Empresas de transporte marítimo o aéreo y pesqueras de alta mar',
        '520F' => 'Agencias internacionales de prensa',
        '520G' => 'Contratos de fletamento de naves',
        '521'  => 'Enajenación de derechos representativos de capital',
        '523A' => 'Seguros y reaseguros (primas y cesiones)',
        '525'  => 'Donaciones en dinero',
    ];

    public static function existe(?string $codigo): bool
    {
        return $codigo !== null && isset(self::CATALOGO[strtoupper(trim($codigo))]);
    }

    /** Código válido en mayúsculas, o null. */
    public static function normalizar($codigo): ?string
    {
        $c = strtoupper(trim((string) ($codigo ?? '')));
        return isset(self::CATALOGO[$c]) ? $c : null;
    }

    /** <option> del selector (con la opción vacía). */
    public static function opcionesHtml(): string
    {
        $h = '<option value="">-- Sin concepto --</option>';
        foreach (self::CATALOGO as $cod => $nombre) {
            $c = htmlspecialchars($cod, ENT_QUOTES, 'UTF-8');
            $h .= '<option value="' . $c . '">' . $c . ' - ' . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $h;
    }
}
