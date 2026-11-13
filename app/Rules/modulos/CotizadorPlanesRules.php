<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio del Cotizador de Planes. Lanzan \InvalidArgumentException
 * con un mensaje para el usuario.
 */
class CotizadorPlanesRules
{
    public const MAX_ESTABLECIMIENTOS = 50;
    public const MAX_USUARIOS = 10000;

    public function validarConfig(array $d): void
    {
        $pct = [
            'recargo_mensual_pct'           => 'El recargo del plan mensual',
            'descuento_establecimiento_pct' => 'El descuento por establecimiento adicional',
            'descuento_vendedor_max_pct'    => 'El descuento máximo del vendedor',
        ];
        foreach ($pct as $k => $txt) {
            $v = (float) ($d[$k] ?? 0);
            if ($v < 0 || $v > 100) {
                throw new \InvalidArgumentException("{$txt} debe estar entre 0 y 100 %.");
            }
        }
        $upe = (int) ($d['usuarios_por_establecimiento'] ?? 0);
        if ($upe < 0 || $upe > 1000) {
            throw new \InvalidArgumentException('Los usuarios incluidos por establecimiento deben estar entre 0 y 1000.');
        }
        if ((float) ($d['precio_usuario_adicional_anual'] ?? 0) < 0) {
            throw new \InvalidArgumentException('El precio del usuario adicional no puede ser negativo.');
        }
        $dv = (int) ($d['dias_vigencia'] ?? 0);
        if ($dv < 1 || $dv > 3650) {
            throw new \InvalidArgumentException('Los días de vigencia de la proforma deben estar entre 1 y 3650.');
        }
    }

    public function validarItem(array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del ítem es obligatorio.');
        }
        if (mb_strlen($nombre) > 150) {
            throw new \InvalidArgumentException('El nombre no puede superar 150 caracteres.');
        }
        if (!in_array($d['tipo'] ?? '', ['modulo', 'servicio'], true)) {
            throw new \InvalidArgumentException('Tipo de ítem no válido.');
        }
        if (($d['tipo'] ?? '') === 'modulo' && (int) ($d['id_submodulo'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('Elija el submódulo del menú que corresponde a este módulo.');
        }
        if (!in_array($d['cobro'] ?? '', ['recurrente', 'unico'], true)) {
            throw new \InvalidArgumentException('Indique si el ítem se cobra cada periodo o una sola vez.');
        }
        $precio = $d['precio_anual'] ?? null;
        if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > 9999999) {
            throw new \InvalidArgumentException('El precio debe ser un número mayor o igual a cero.');
        }
        if (!in_array($d['estado'] ?? '', ['activo', 'inactivo'], true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
    }

    /**
     * Cotización a guardar (ya con los establecimientos resueltos por el Service).
     * $parametros: configuración vigente (permite o no el descuento del vendedor y su tope).
     */
    public function validarCotizacion(array $d, array $parametros): void
    {
        $ident = (string) ($d['identificacion'] ?? '');
        if (!preg_match('/^\d{10}$|^\d{13}$/', $ident)) {
            throw new \InvalidArgumentException('Escriba un RUC (13 dígitos) o una cédula (10 dígitos).');
        }
        if (strlen($ident) === 13 && !preg_match('/(001|002)$/', $ident)) {
            throw new \InvalidArgumentException('El RUC debe terminar en 001 o 002.');
        }
        if (trim((string) ($d['razon_social'] ?? '')) === '') {
            throw new \InvalidArgumentException('La razón social es obligatoria. Use «Consultar SRI» o escríbala.');
        }
        $correo = trim((string) ($d['correo'] ?? ''));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no tiene un formato válido.');
        }
        $tel = trim((string) ($d['telefono'] ?? ''));
        if ($tel !== '' && !preg_match('/^[0-9+()\-\s]{6,30}$/', $tel)) {
            throw new \InvalidArgumentException('El teléfono solo puede tener números, espacios y los signos + ( ) -.');
        }
        if (!in_array($d['modalidad'] ?? '', ['anual', 'mensual'], true)) {
            throw new \InvalidArgumentException('Elija la modalidad: anual o mensual.');
        }
        $usuarios = (int) ($d['usuarios'] ?? 0);
        if ($usuarios < 1 || $usuarios > self::MAX_USUARIOS) {
            throw new \InvalidArgumentException('Indique cuántos usuarios necesita el cliente (al menos 1).');
        }

        $est = $d['establecimientos'] ?? [];
        if (!is_array($est) || $est === []) {
            throw new \InvalidArgumentException('Agregue al menos un establecimiento.');
        }
        if (count($est) > self::MAX_ESTABLECIMIENTOS) {
            throw new \InvalidArgumentException('Una cotización admite hasta ' . self::MAX_ESTABLECIMIENTOS . ' establecimientos.');
        }
        $codigos = [];
        foreach ($est as $e) {
            $cod = (string) ($e['codigo'] ?? '');
            if (!preg_match('/^\d{3}$/', $cod) || $cod === '000') {
                throw new \InvalidArgumentException("El código de establecimiento «{$cod}» debe tener 3 dígitos (001, 002…).");
            }
            if (isset($codigos[$cod])) {
                throw new \InvalidArgumentException("El establecimiento {$cod} está repetido.");
            }
            $codigos[$cod] = true;
            if (empty($e['items'])) {
                throw new \InvalidArgumentException("El establecimiento {$cod} no tiene módulos ni servicios.");
            }
        }

        $pctV = (float) ($d['descuento_vendedor_pct'] ?? 0);
        if ($pctV < 0) {
            throw new \InvalidArgumentException('El descuento no puede ser negativo.');
        }
        if ($pctV > 0) {
            if (empty($parametros['permitir_descuento_vendedor'])) {
                throw new \InvalidArgumentException('La configuración del cotizador no permite que el vendedor dé descuentos.');
            }
            $max = (float) ($parametros['descuento_vendedor_max_pct'] ?? 0);
            if ($pctV > $max) {
                throw new \InvalidArgumentException('El descuento máximo permitido es ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . ' %.');
            }
        }
    }

    /** Lo que hace falta, además de lo guardado, para generar la proforma. */
    public function validarParaProforma(array $cot, array $config): void
    {
        if (($cot['estado'] ?? '') !== 'borrador') {
            throw new \InvalidArgumentException('Esta cotización ya generó su proforma o está anulada.');
        }
        if (trim((string) ($cot['correo'] ?? '')) === '') {
            throw new \InvalidArgumentException('Escriba el correo del cliente: hace falta para registrarlo y enviarle la proforma.');
        }
        $campo = ($cot['modalidad'] ?? 'anual') === 'mensual' ? 'id_producto_resumen_mensual' : 'id_producto_resumen_anual';
        if (empty($config[$campo])) {
            $txt = ($cot['modalidad'] ?? 'anual') === 'mensual' ? 'mensual' : 'anual';
            throw new \InvalidArgumentException("Falta configurar el producto del plan {$txt} (engranaje → Parámetros). Es la línea que va en la proforma.");
        }
    }
}
