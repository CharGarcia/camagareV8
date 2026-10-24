<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class FirmaElectronicaRules
{
    /** Estados del trámite, en el orden en que avanza. */
    public const ESTADOS = ['borrador', 'pendiente', 'por_registrar', 'en_proceso', 'aprobada', 'observada', 'emitida', 'cancelada'];
    /** Desde aquí la solicitud ya no se edita: Uanataca la aprobó o ya emitió la firma. */
    public const ESTADOS_CERRADOS = ['aprobada', 'emitida'];

    public const ESTADOS_PAGO = ['pendiente', 'en_revision', 'confirmado', 'rechazado'];

    /** Estados en que el cliente todavía puede corregir sus datos y documentos desde el portal. */
    public const ESTADOS_EDITABLES_PORTAL = ['borrador', 'pendiente', 'observada'];

    /** Tipos de adjunto válidos (los mismos en el modal interno y en el portal). */
    public const TIPOS_ADJUNTO = [
        'cedula_frontal', 'cedula_posterior', 'selfie', 'comprobante_transferencia',
        'ruc_empresa', 'constitucion_compania', 'nombramiento', 'aceptacion_nombramiento',
        'cedula_representante', 'autorizacion', 'otro',
    ];

    /** natural | juridica (representante legal) | miembro (miembro de empresa). */
    public const TIPOS_PERSONA = ['natural', 'juridica', 'miembro'];

    public const ETIQUETAS_ADJUNTO = [
        'cedula_frontal'            => 'Cédula (frente)',
        'cedula_posterior'          => 'Cédula (posterior)',
        'selfie'                    => 'Selfie con la cédula',
        'comprobante_transferencia' => 'Comprobante de pago',
        'ruc_empresa'               => 'RUC de la empresa',
        'constitucion_compania'     => 'Constitución de la compañía',
        'nombramiento'              => 'Nombramiento',
        'aceptacion_nombramiento'   => 'Aceptación del nombramiento',
        'cedula_representante'      => 'Cédula del representante legal',
        'autorizacion'              => 'Autorización del representante legal',
        'otro'                      => 'Otro documento',
    ];

    private const MIME_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
    private const MIME_PDF    = ['application/pdf'];
    public const MAX_BYTES    = 5 * 1024 * 1024;
    /** Motivo de uso que se envía a Uanataca para representante legal si no se indicó otro. */
    public const MOTIVO_POR_DEFECTO = 'Facturación electrónica y firma de documentos';
    /** Mínimo de caracteres de la dirección del titular (pantalla y servidor). */
    public const MIN_DIRECCION = 10;

    public function validar(array $data): void
    {
        $errores = [];

        if (empty($data['id_empresa'])) {
            $errores[] = 'El identificador de empresa es obligatorio.';
        }
        if (empty($data['id_usuario'])) {
            $errores[] = 'El usuario responsable es obligatorio.';
        }

        $errores = array_merge($errores, $this->erroresTitular($data));

        $estado = trim($data['estado'] ?? 'pendiente');
        if (!in_array($estado, self::ESTADOS, true)) {
            $errores[] = 'El estado del trámite no es válido.';
        }
        if (!in_array(trim($data['estado_pago'] ?? 'pendiente'), self::ESTADOS_PAGO, true)) {
            $errores[] = 'El estado del pago no es válido.';
        }
        // La caducidad la da el certificado emitido: antes de eso no existe.
        if ($estado === 'emitida' && empty(trim((string) ($data['fecha_caducidad'] ?? '')))) {
            $errores[] = 'Para marcar la firma como emitida se necesita su fecha de vencimiento.';
        }

        $tipoPago = trim($data['tipo_pago'] ?? '');
        if ($tipoPago !== '' && !in_array($tipoPago, ['transferencia', 'tarjeta', 'payphone'], true)) {
            $errores[] = 'El tipo de pago no es válido.';
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }

    /** Datos que el cliente llena en el portal: además del titular, lo que Uanataca pide. */
    public function validarPortal(array $data, array $idsProductosValidos): void
    {
        $errores = $this->erroresTitular($data);

        if (empty($data['id_producto']) || !in_array((int) $data['id_producto'], $idsProductosValidos, true)) {
            $errores[] = 'Seleccione la validez de la firma.';
        }
        foreach ([
            'telefono'  => 'Ingrese el teléfono.',
            'correo'    => 'Ingrese el correo electrónico.',
            'cod_prov'  => 'Seleccione la provincia.',
            'cod_ciudad'=> 'Seleccione la ciudad.',
        ] as $campo => $msg) {
            if (trim((string) ($data[$campo] ?? '')) === '') {
                $errores[] = $msg;
            }
        }
        if (trim((string) ($data['apellido_paterno'] ?? '')) === '') {
            $errores[] = 'Ingrese el primer apellido.';
        }
        if (empty($data['fecha_nacimiento']) || strtotime((string) $data['fecha_nacimiento']) === false
            || strtotime((string) $data['fecha_nacimiento']) > strtotime('-18 years')) {
            $errores[] = 'Ingrese una fecha de nacimiento válida (mayor de edad).';
        }
        if (!in_array($data['sexo'] ?? '', ['hombre', 'mujer'], true)) {
            $errores[] = 'Seleccione el sexo.';
        }
        if (($data['tipo_identificacion'] ?? '') === 'cedula'
            && !preg_match('/^[A-Z]\d{4}[A-Z]\d{4}$/i', (string) ($data['codigo_dactilar'] ?? ''))) {
            $errores[] = 'Ingrese el código dactilar (está al reverso de la cédula, ej. V4443V4442).';
        }
        $tel = preg_replace('/\D/', '', (string) ($data['telefono'] ?? ''));
        if ($tel !== '' && (strlen($tel) < 9 || strlen($tel) > 12)) {
            $errores[] = 'El teléfono debe tener entre 9 y 12 dígitos.';
        }
        $tipoPersona = $data['tipo_persona'] ?? 'natural';
        if (in_array($tipoPersona, ['juridica', 'miembro'], true)) {
            if (!preg_match('/^\d{13}$/', (string) ($data['ruc_empresa'] ?? ''))) {
                $errores[] = 'El RUC de la empresa debe tener 13 dígitos.';
            }
            if (trim((string) ($data['nombre_empresa'] ?? '')) === '') {
                $errores[] = 'Ingrese el nombre de la empresa.';
            }
            if (trim((string) ($data['cargo'] ?? '')) === '') {
                $errores[] = 'Ingrese su cargo en la empresa.';
            }
        }
        if ($tipoPersona === 'miembro') {
            if (trim((string) ($data['departamento'] ?? '')) === '') {
                $errores[] = 'Ingrese el departamento o área donde trabaja.';
            }
            if (trim((string) ($data['rep_identificacion'] ?? '')) === '' || trim((string) ($data['rep_nombres'] ?? '')) === ''
                || trim((string) ($data['rep_apellidos'] ?? '')) === '') {
                $errores[] = 'Ingrese la identificación, nombres y apellidos del representante legal.';
            }
        }

        if (empty($data['facturacion_mismos_datos'])) {
            $tipoFac = (string) ($data['facturacion_tipo_id'] ?? '');
            $numFac  = (string) ($data['facturacion_num_id'] ?? '');
            if (($tipoFac === 'cedula' && !preg_match('/^\d{10}$/', $numFac))
                || ($tipoFac === 'ruc' && !preg_match('/^\d{13}$/', $numFac))
                || $numFac === '') {
                $errores[] = 'La identificación para la factura no es válida.';
            }
            if (trim((string) ($data['facturacion_nombres'] ?? '')) === '') {
                $errores[] = 'Ingrese el nombre o razón social para la factura.';
            }
            $correoFac = trim((string) ($data['facturacion_correo'] ?? ''));
            if ($correoFac !== '' && !filter_var($correoFac, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El correo para la factura no es válido.';
            }
        }

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }
    }

    /**
     * Datos y documentos que faltan para enviar la solicitud a Uanataca.
     * @return string[] mensajes; vacío = se puede enviar
     */
    public function faltantesUanataca(array $firma, array $tiposAdjuntos): array
    {
        $f = [];
        $req = function (string $campo, string $msg) use (&$f, $firma): void {
            if (trim((string) ($firma[$campo] ?? '')) === '') {
                $f[] = $msg;
            }
        };
        $req('numero_identificacion', 'identificación');
        $req('nombres', 'nombres');
        $req('apellido_paterno', 'primer apellido');
        $req('fecha_nacimiento', 'fecha de nacimiento');
        $req('nacionalidad', 'nacionalidad');
        $req('telefono', 'celular');
        $req('correo', 'correo');
        $req('cod_prov', 'provincia');
        $req('cod_ciudad', 'ciudad');
        $req('direccion', 'dirección');
        if (!in_array($firma['sexo'] ?? '', ['hombre', 'mujer'], true)) {
            $f[] = 'sexo';
        }
        if (($firma['tipo_identificacion'] ?? '') === 'cedula') {
            $req('codigo_dactilar', 'código dactilar');
        }
        $tipo = (string) ($firma['tipo_persona'] ?? 'natural');
        if (in_array($tipo, ['juridica', 'miembro'], true)) {
            $req('ruc_empresa', 'RUC de la empresa');
            $req('nombre_empresa', 'razón social');
            $req('cargo', 'cargo');
        }
        if ($tipo === 'miembro') {
            $req('departamento', 'departamento');
            $req('rep_identificacion', 'identificación del representante legal');
            $req('rep_nombres', 'nombres del representante legal');
            $req('rep_apellidos', 'apellidos del representante legal');
        }
        foreach (array_diff($this->adjuntosRequeridos($tipo), $tiposAdjuntos) as $t) {
            $f[] = self::ETIQUETAS_ADJUNTO[$t] ?? $t;
        }
        return $f;
    }

    public const UNIDADES_VIGENCIA = ['dias' => 'días', 'meses' => 'meses', 'anios' => 'años'];

    /**
     * Vigencia escrita en el nombre del producto: "Firma 1 año", "2 AÑOS", "30 días",
     * "6 meses", "una semana". null si el nombre no la dice.
     * @return array{cantidad:int, unidad:string}|null
     */
    public static function vigenciaDesdeNombre(string $nombre): ?array
    {
        $n = mb_strtolower($nombre, 'UTF-8');
        $palabras = ['un' => 1, 'una' => 1, 'uno' => 1, 'dos' => 2, 'tres' => 3, 'cuatro' => 4, 'cinco' => 5, 'seis' => 6];
        if (!preg_match('/\b(\d{1,4}|un|una|uno|dos|tres|cuatro|cinco|seis)\s*(años?|anios?|anos?|mes(?:es)?|d[ií]as?|semanas?)\b/u', $n, $m)) {
            return null;
        }
        $cant = ctype_digit($m[1]) ? (int) $m[1] : $palabras[$m[1]];
        $u = $m[2];
        if (str_starts_with($u, 'mes')) {
            return ['cantidad' => $cant, 'unidad' => 'meses'];
        }
        if (str_starts_with($u, 'semana')) {
            return ['cantidad' => $cant * 7, 'unidad' => 'dias'];
        }
        if (str_starts_with($u, 'd')) {
            return ['cantidad' => $cant, 'unidad' => 'dias'];
        }
        return ['cantidad' => $cant, 'unidad' => 'anios'];
    }

    /** Fecha (Y-m-d) en que vence una vigencia contada desde $desde. */
    public static function sumarVigencia(string $desde, int $cantidad, string $unidad): string
    {
        $d = new \DateTimeImmutable($desde);
        if ($unidad === 'dias') {
            return $d->modify("+{$cantidad} days")->format('Y-m-d');
        }
        // Meses/años: mismo día del mes destino o su último día (31-01 + 1 mes = 28/29-02,
        // 29-02 + 1 año = 28-02), sin desbordar al mes siguiente.
        $meses = $unidad === 'meses' ? $cantidad : $cantidad * 12;
        $total = (int) $d->format('Y') * 12 + (int) $d->format('n') - 1 + $meses;
        $anio  = intdiv($total, 12);
        $mes   = $total % 12 + 1;
        $dia   = min((int) $d->format('j'), (int) date('t', mktime(0, 0, 0, $mes, 1, $anio)));
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /** Documentos obligatorios según el tipo de persona. */
    public function adjuntosRequeridos(string $tipoPersona): array
    {
        $req = ['cedula_frontal', 'cedula_posterior', 'selfie'];
        if ($tipoPersona === 'juridica') {
            $req = array_merge($req, ['ruc_empresa', 'constitucion_compania', 'nombramiento', 'aceptacion_nombramiento']);
        } elseif ($tipoPersona === 'miembro') {
            $req = array_merge($req, ['ruc_empresa', 'nombramiento', 'cedula_representante', 'autorizacion']);
        }
        return $req;
    }

    /**
     * Valida un archivo subido por el contenido real (finfo), no por la extensión.
     * Devuelve [mime, extensión].
     */
    public function validarArchivo(array $file, string $tipo): array
    {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No se recibió el archivo o hubo un error al subirlo.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new Exception('El archivo supera el tamaño máximo permitido (5 MB).');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        $soloPdf    = in_array($tipo, ['ruc_empresa', 'constitucion_compania', 'nombramiento', 'aceptacion_nombramiento', 'autorizacion'], true);
        $soloImagen = in_array($tipo, ['cedula_frontal', 'cedula_posterior', 'selfie'], true);
        $permitidos = $soloPdf ? self::MIME_PDF : ($soloImagen ? self::MIME_IMAGEN : array_merge(self::MIME_IMAGEN, self::MIME_PDF));

        if (!in_array($mime, $permitidos, true)) {
            throw new Exception($soloPdf
                ? 'Este documento debe ser un PDF.'
                : ($soloImagen ? 'Este documento debe ser una foto (JPG, PNG o WEBP).' : 'Use una foto (JPG, PNG, WEBP) o un PDF.'));
        }
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'][$mime];
        return [$mime, $ext];
    }

    private function erroresTitular(array $data): array
    {
        $errores = [];
        $tipoId = trim($data['tipo_identificacion'] ?? '');
        if (!in_array($tipoId, ['cedula', 'ruc', 'pasaporte'], true)) {
            $errores[] = 'El tipo de identificación no es válido.';
        }

        $numId = trim($data['numero_identificacion'] ?? '');
        if ($numId === '') {
            $errores[] = 'El número de identificación es obligatorio.';
        } elseif ($tipoId === 'cedula' && !preg_match('/^\d{10}$/', $numId)) {
            $errores[] = 'La cédula debe tener exactamente 10 dígitos numéricos.';
        } elseif ($tipoId === 'ruc' && !preg_match('/^\d{13}$/', $numId)) {
            $errores[] = 'El RUC debe tener exactamente 13 dígitos numéricos.';
        } elseif ($tipoId === 'pasaporte' && !preg_match('/^[a-zA-Z0-9]{1,20}$/', $numId)) {
            $errores[] = 'El pasaporte solo puede contener letras y números (máx. 20).';
        }

        if (empty(trim($data['nombres'] ?? ''))) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (empty(trim($data['apellidos'] ?? '')) && empty(trim($data['apellido_paterno'] ?? ''))) {
            $errores[] = 'El apellido es obligatorio.';
        }

        $correo = trim($data['correo'] ?? '');
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no tiene un formato válido.';
        }

        // Uanataca necesita una dirección real (calle, número, sector), no solo la ciudad.
        if (mb_strlen(trim((string) ($data['direccion'] ?? '')), 'UTF-8') < self::MIN_DIRECCION) {
            $errores[] = 'La dirección debe tener al menos ' . self::MIN_DIRECCION . ' caracteres (calle, número, sector).';
        }

        $sexo = trim($data['sexo'] ?? '');
        if ($sexo !== '' && !in_array($sexo, ['hombre', 'mujer'], true)) {
            $errores[] = 'El sexo debe ser hombre o mujer.';
        }
        return $errores;
    }
}
