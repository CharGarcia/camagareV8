<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Límites y validaciones de la Ficha Técnica de Comprobantes Electrónicos del
 * SRI, para poder rechazar un dato ANTES de armar el XML.
 *
 * Por qué existe: `App\Services\Xml\XmlFacturaVentaService` (y sus hermanos) NO
 * validan nada — escriben en el XML lo que reciben. Un texto de 400 caracteres o
 * una identificación incoherente con su tipo pasan sin ruido y el comprobante se
 * rechaza recién al enviarlo al SRI, cuando ya está creado y numerado. Estas
 * comprobaciones permiten avisar antes, sobre todo en cargas masivas, donde no
 * hay un formulario con `maxlength` que contenga al usuario.
 *
 * Solo cubre lo que la ficha define como restricción de FORMATO (longitud,
 * decimales, coherencia del identificador). Las reglas de negocio siguen donde
 * les corresponde: FacturaVentaRules y los Rules de cada módulo.
 *
 * Referencia: Ficha Técnica del SRI, esquema `factura` (codDoc 01).
 */
class SriFichaTecnica
{
    // ── Longitudes máximas (esquema factura) ─────────────────────────────────
    public const MAX_RAZON_SOCIAL_COMPRADOR = 300;
    public const MAX_IDENTIFICACION         = 20;
    public const MAX_DIRECCION              = 300;
    public const MAX_DESCRIPCION_DETALLE    = 300;
    public const MAX_CODIGO_PRINCIPAL       = 25;
    public const MAX_CODIGO_AUXILIAR        = 25;
    public const MAX_INFO_ADICIONAL_NOMBRE  = 300;
    public const MAX_INFO_ADICIONAL_VALOR   = 300;

    /** El bloque infoAdicional admite como máximo 15 campoAdicional. */
    public const MAX_CAMPOS_ADICIONALES = 15;

    // ── Tamaño del comprobante ───────────────────────────────────────────────
    /**
     * Tamaño máximo por comprobante en el envío INDIVIDUAL (Ficha Técnica v2.34):
     * "para el envío individual, el tamaño máximo por comprobante será de 320 Kb".
     * Por lote el tope es 500 Kb o 50 comprobantes.
     */
    public const MAX_BYTES_COMPROBANTE = 320 * 1024;

    /** A partir de esta fracción del máximo se avisa, sin bloquear todavía. */
    public const UMBRAL_AVISO_TAMANO = 0.90;

    // Constantes calibradas midiendo XML reales generados por XmlFacturaVentaService
    // (desvío < 0,3% frente al archivo final). Ver la nota de pesoEstimadoXml().
    private const XML_BYTES_BASE           = 1450;  // cabecera, totales y pagos
    private const XML_BYTES_POR_LINEA      = 404;   // marcado y números de un <detalle>
    private const XML_BYTES_POR_IMPUESTO   = 150;   // cada <impuesto> extra de la línea
    private const XML_BYTES_POR_ADICIONAL  = 40;    // marcado de un <campoAdicional>
    /**
     * La firma XAdES añade el certificado en base64 y los digests. Se toma un
     * valor holgado: quedarse corto dejaría pasar comprobantes que el SRI
     * rechazaría por tamaño, que es justo lo que se quiere evitar.
     */
    private const XML_BYTES_FIRMA          = 10 * 1024;

    /** Decimales que admite el XML en cantidad y precio unitario. */
    public const MAX_DECIMALES_CANTIDAD = 6;
    public const MAX_DECIMALES_PRECIO   = 6;

    // ── Tipos de identificación del comprador (catálogo del SRI) ─────────────
    public const TIPO_ID_RUC              = '04';
    public const TIPO_ID_CEDULA           = '05';
    public const TIPO_ID_PASAPORTE        = '06';
    public const TIPO_ID_CONSUMIDOR_FINAL = '07';
    public const TIPO_ID_EXTERIOR         = '08';
    public const TIPO_ID_PLACA            = '09';

    /** Identificación que el SRI exige para el consumidor final. */
    public const IDENTIFICACION_CONSUMIDOR_FINAL = '9999999999999';

    /**
     * Comprueba que un texto no exceda el máximo de la ficha.
     *
     * @param string $etiqueta Nombre legible del campo, para el mensaje.
     * @return string|null Mensaje de error, o null si está bien.
     */
    public static function excedeLongitud(string $etiqueta, string $valor, int $maximo): ?string
    {
        $largo = mb_strlen(trim($valor));
        if ($largo <= $maximo) {
            return null;
        }
        return $etiqueta . ' tiene ' . $largo . ' caracteres y el SRI admite como máximo '
            . $maximo . '. El comprobante sería rechazado.';
    }

    /**
     * Comprueba que la identificación del comprador sea coherente con su tipo.
     *
     * El SRI valida esta correspondencia: una cédula de 9 dígitos declarada como
     * tipo 05, o un RUC de 10, hacen que el comprobante se devuelva.
     *
     * @return string|null Mensaje de error, o null si está bien.
     */
    public static function identificacionIncoherente(string $tipoId, string $identificacion): ?string
    {
        $tipoId = trim($tipoId);
        $id     = trim($identificacion);

        if ($id === '') {
            return 'El cliente no tiene número de identificación.';
        }
        if (mb_strlen($id) > self::MAX_IDENTIFICACION) {
            return self::excedeLongitud('La identificación del cliente', $id, self::MAX_IDENTIFICACION);
        }

        switch ($tipoId) {
            case self::TIPO_ID_RUC:
                if (!preg_match('/^[0-9]{13}$/', $id)) {
                    return 'El cliente está registrado como RUC pero su identificación "' . $id
                        . '" no tiene 13 dígitos.';
                }
                break;

            case self::TIPO_ID_CEDULA:
                if (!preg_match('/^[0-9]{10}$/', $id)) {
                    return 'El cliente está registrado como cédula pero su identificación "' . $id
                        . '" no tiene 10 dígitos.';
                }
                break;

            case self::TIPO_ID_CONSUMIDOR_FINAL:
                if ($id !== self::IDENTIFICACION_CONSUMIDOR_FINAL) {
                    return 'El cliente está registrado como Consumidor Final pero su identificación '
                        . 'no es ' . self::IDENTIFICACION_CONSUMIDOR_FINAL . '.';
                }
                break;

            case '':
                return 'El cliente no tiene tipo de identificación registrado; el SRI lo exige.';

            case self::TIPO_ID_PASAPORTE:
            case self::TIPO_ID_EXTERIOR:
            case self::TIPO_ID_PLACA:
                // Sin formato fijo: basta el límite de longitud ya comprobado.
                break;

            default:
                return 'El tipo de identificación "' . $tipoId . '" del cliente no pertenece al '
                    . 'catálogo del SRI.';
        }

        return null;
    }

    /**
     * Decimales realmente presentes en un número (hasta un tope razonable).
     *
     * El XML escribe cantidad y precio con 6 decimales como máximo. Si el dato
     * de origen trae más, el SRI recalcula `cantidad × precioUnitario` con lo que
     * ve en el XML y ya no le cuadra el `precioTotalSinImpuesto` — el clásico
     * "ERROR EN DIFERENCIAS".
     */
    public static function decimales(float $valor): int
    {
        $limpio = rtrim(rtrim(number_format($valor, 10, '.', ''), '0'), '.');
        $pos    = strpos($limpio, '.');
        return $pos === false ? 0 : strlen($limpio) - $pos - 1;
    }

    /**
     * @return string|null Mensaje de error si el número excede los decimales admitidos.
     */
    public static function excedeDecimales(string $etiqueta, float $valor, int $maximo): ?string
    {
        $dec = self::decimales($valor);
        if ($dec <= $maximo) {
            return null;
        }
        return $etiqueta . ' tiene ' . $dec . ' decimales y el SRI admite como máximo ' . $maximo
            . '. Redondéelo, o el total de la línea no cuadrará con el comprobante.';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tamaño del comprobante
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Estima cuánto pesará el XML firmado de un comprobante, en bytes.
     *
     * El SRI rechaza por tamaño en la RECEPCIÓN, cuando el documento ya está
     * creado y con su secuencial consumido. Estimarlo antes permite avisar a
     * tiempo. La fórmula se calibró generando XML reales con
     * XmlFacturaVentaService y midiendo: acierta con menos del 0,3% de desvío
     * frente al archivo final, más la firma, que va estimada al alza.
     *
     * @param array $detalles      Líneas del documento.
     * @param array $infoAdicional Campos de infoAdicional de la cabecera.
     */
    public static function pesoEstimadoXml(array $detalles, array $infoAdicional = []): int
    {
        $bytes = self::XML_BYTES_BASE + self::XML_BYTES_FIRMA;

        foreach ($detalles as $d) {
            $bytes += self::XML_BYTES_POR_LINEA;

            // Los textos pesan 1 byte por carácter (los tags ya van en el fijo).
            $bytes += mb_strlen((string) ($d['descripcion'] ?? $d['nombre'] ?? ''));
            $bytes += mb_strlen((string) ($d['codigo_principal'] ?? $d['codigo_interno'] ?? $d['codigo'] ?? ''));
            $bytes += mb_strlen((string) ($d['codigo_auxiliar'] ?? ''));

            // El primer impuesto ya está contado en el fijo por línea.
            $nImpuestos = count($d['impuestos'] ?? []);
            if ($nImpuestos > 1) {
                $bytes += ($nImpuestos - 1) * self::XML_BYTES_POR_IMPUESTO;
            }
        }

        foreach ($infoAdicional as $ia) {
            $bytes += self::XML_BYTES_POR_ADICIONAL
                + mb_strlen((string) ($ia['nombre'] ?? ''))
                + mb_strlen((string) ($ia['valor'] ?? ''));
        }

        return $bytes;
    }

    /**
     * ¿El comprobante se pasa —o se acerca— al tope de tamaño del SRI?
     *
     * @return array{nivel:string,mensaje:string}|null
     *         nivel 'error' si excede el máximo, 'aviso' a partir del 90%,
     *         null si va holgado.
     */
    public static function problemaTamanoXml(array $detalles, array $infoAdicional = []): ?array
    {
        $bytes = self::pesoEstimadoXml($detalles, $infoAdicional);
        $pct   = $bytes / self::MAX_BYTES_COMPROBANTE;

        if ($pct <= self::UMBRAL_AVISO_TAMANO) {
            return null;
        }

        $kb    = round($bytes / 1024);
        $maxKb = round(self::MAX_BYTES_COMPROBANTE / 1024);
        $tam   = 'El comprobante pesará unos ' . $kb . ' Kb y el SRI admite como máximo '
               . $maxKb . ' Kb por comprobante (' . round($pct * 100) . '% del límite), con '
               . count($detalles) . ' línea(s).';

        if ($pct > 1) {
            return [
                'nivel'   => 'error',
                'mensaje' => $tam . ' El SRI lo rechazaría al enviarlo. Divida el documento en '
                    . 'varias facturas con menos líneas.',
            ];
        }

        return [
            'nivel'   => 'aviso',
            'mensaje' => $tam . ' Está cerca del límite: si añade más líneas o descripciones '
                . 'más largas, el SRI lo rechazará.',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validación de bloques completos, para reutilizar desde cualquier Rules
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Revisa las líneas de un comprobante (el bloque `detalles`).
     *
     * Sirve para factura, nota de crédito, nota de débito, liquidación de compra,
     * factura de reembolso y cualquier otro documento cuyo detalle lleve
     * descripción, código, cantidad y precio. Se aceptan los distintos nombres de
     * la clave del código porque no todos los módulos la llaman igual
     * (`codigo_principal` en factura y liquidación, `codigo_interno` en NC/ND).
     *
     * @param array $detalles Líneas tal como llegan al Service.
     * @return string[] Errores encontrados, vacío si todo está bien.
     */
    public static function erroresDetalles(array $detalles): array
    {
        $errores = [];

        foreach (array_values($detalles) as $i => $d) {
            $n = $i + 1;

            $descripcion = (string) ($d['descripcion'] ?? $d['nombre'] ?? '');
            $error = self::excedeLongitud("Línea #{$n}: la descripción", $descripcion, self::MAX_DESCRIPCION_DETALLE);
            if ($error !== null) {
                $errores[] = $error;
            }

            $codigo = (string) ($d['codigo_principal'] ?? $d['codigo_interno'] ?? $d['codigo'] ?? '');
            if ($codigo !== '') {
                $error = self::excedeLongitud("Línea #{$n}: el código", $codigo, self::MAX_CODIGO_PRINCIPAL);
                if ($error !== null) {
                    $errores[] = $error;
                }
            }

            $codigoAux = (string) ($d['codigo_auxiliar'] ?? '');
            if ($codigoAux !== '') {
                $error = self::excedeLongitud("Línea #{$n}: el código auxiliar", $codigoAux, self::MAX_CODIGO_AUXILIAR);
                if ($error !== null) {
                    $errores[] = $error;
                }
            }

            if (isset($d['cantidad']) && is_numeric($d['cantidad'])) {
                $error = self::excedeDecimales("Línea #{$n}: la cantidad", (float) $d['cantidad'], self::MAX_DECIMALES_CANTIDAD);
                if ($error !== null) {
                    $errores[] = $error;
                }
            }

            if (isset($d['precio_unitario']) && is_numeric($d['precio_unitario'])) {
                $error = self::excedeDecimales("Línea #{$n}: el precio unitario", (float) $d['precio_unitario'], self::MAX_DECIMALES_PRECIO);
                if ($error !== null) {
                    $errores[] = $error;
                }
            }
        }

        return $errores;
    }

    /**
     * Revisa el bloque `infoAdicional`.
     *
     * @param array $infoAdicional Campos [['nombre'=>..., 'valor'=>...], ...].
     * @param int   $reservados    Campos que el sistema añadirá después por su
     *                             cuenta (p. ej. el correo del cliente y el RUC
     *                             del proveedor en las facturas de venta), y que
     *                             hay que descontar del tope.
     * @return string[]
     */
    public static function erroresInfoAdicional(array $infoAdicional, int $reservados = 0): array
    {
        $errores = [];
        $margen  = self::MAX_CAMPOS_ADICIONALES - max(0, $reservados);

        if (count($infoAdicional) > $margen) {
            $errores[] = 'La información adicional tiene ' . count($infoAdicional) . ' campos. El SRI '
                . 'admite ' . self::MAX_CAMPOS_ADICIONALES
                . ($reservados > 0
                    ? ' y el sistema reserva ' . $reservados . ', así que puede usar hasta ' . $margen . '.'
                    : '.');
        }

        foreach (array_values($infoAdicional) as $i => $campo) {
            $n = $i + 1;

            $error = self::excedeLongitud(
                "Información adicional #{$n}: el nombre",
                (string) ($campo['nombre'] ?? ''),
                self::MAX_INFO_ADICIONAL_NOMBRE
            );
            if ($error !== null) {
                $errores[] = $error;
            }

            $error = self::excedeLongitud(
                "Información adicional #{$n}: el valor",
                (string) ($campo['valor'] ?? ''),
                self::MAX_INFO_ADICIONAL_VALOR
            );
            if ($error !== null) {
                $errores[] = $error;
            }
        }

        return $errores;
    }
}
