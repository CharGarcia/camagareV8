<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Tablas de códigos del Anexo RDEP (retenciones en la fuente bajo relación de
 * dependencia), según el catálogo y la ficha técnica del SRI para el ejercicio
 * 2024 (vigentes para 2025) y el esquema rdep.xsd (2023).
 *
 * Los valores son los que viajan tal cual en el XML; las etiquetas son para la
 * pantalla. No cambiar los códigos.
 */
class CatalogoRdep
{
    public const TIPO_EMPLEADOR = [
        'PRIVADO_MIXTO' => 'Privado o mixto',
        'PUBLICO'       => 'Público o IFIS',
    ];

    public const ENTE_SEG_SOCIAL = [
        'IESS'         => 'IESS',
        'ISSFA_ISSPOL' => 'ISSFA o ISSPOL',
    ];

    /** Tipo de identificación del trabajador. */
    public const TIPO_ID = [
        'C' => 'Cédula',
        'P' => 'Pasaporte',
        'E' => 'Identificación tributaria del exterior',
    ];

    /** Mapa empleados.tipo_id → código del anexo. */
    public const TIPO_ID_DESDE_FICHA = [
        'cedula'    => 'C',
        'pasaporte' => 'P',
        'exterior'  => 'E',
        'ruc'       => 'C',
    ];

    public const RESIDENCIA = [
        '01' => 'Residente local',
        '02' => 'Residente del exterior',
    ];

    public const CONVENIO = [
        'NA' => 'No aplica (residente local)',
        'NO' => 'Sin convenio',
        'SI' => 'Con convenio',
    ];

    /** Condición respecto a discapacidades (desde 2024 ya no existe el código 04). */
    public const TIPO_DISCAP = [
        '01' => 'No aplica',
        '02' => 'Trabajador con discapacidad',
        '03' => 'Sustituto de una persona con discapacidad',
    ];

    public const TIPO_ID_DISCAP = [
        'N' => 'Sin dato (no aplica)',
        'C' => 'Cédula',
        'P' => 'Pasaporte',
        'E' => 'Identificación tributaria del exterior',
    ];

    public const SI_NO = ['NO' => 'No', 'SI' => 'Sí'];

    public const SISTEMA_SALARIO_NETO = [
        1 => 'Sin sistema de salario neto',
        2 => 'Con sistema de salario neto',
    ];

    /** Porcentaje mínimo de discapacidad para la exoneración (desde 2017). */
    public const DISCAP_MINIMO = 30;

    /**
     * Exoneración por discapacidad: % de la exoneración máxima (2 fracciones
     * básicas desgravadas) según el grado de discapacidad (desde 2017).
     * @return int porcentaje aplicable (0 si no alcanza el mínimo)
     */
    public static function porcentajeExoneracionDiscapacidad(int $grado): int
    {
        if ($grado >= 85) return 100;
        if ($grado >= 75) return 80;
        if ($grado >= 50) return 70;
        if ($grado >= self::DISCAP_MINIMO) return 60;
        return 0;
    }

    /**
     * Nombres y apellidos como los exige el SRI: solo letras A-Z y espacios
     * simples; la ñ pasa a n y las tildes se quitan.
     */
    public static function limpiarNombre(?string $s): string
    {
        $s = trim((string) $s);
        if ($s === '') return '';
        static $mapa = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'Â' => 'A', 'Ê' => 'E', 'Î' => 'I', 'Ô' => 'O', 'Û' => 'U',
            'ç' => 'c', 'Ç' => 'C',
        ];
        $s = strtr($s, $mapa);
        $s = (string) preg_replace('/[^A-Za-z ]/', ' ', $s);
        $s = (string) preg_replace('/\s+/', ' ', $s);
        return strtoupper(trim($s));
    }

    /** Solo letras y números, sin símbolos (identificaciones). */
    public static function limpiarIdentificacion(?string $s): string
    {
        return (string) preg_replace('/[^0-9A-Za-z]/', '', (string) $s);
    }
}
