<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

class AlumnoRules
{
    public const CEDULA = '05';
    public const PASAPORTE = '06';

    /**
     * El alumno es siempre una persona natural: solo cédula o pasaporte del
     * catálogo global identificador_comprador_vendedor. No aplica RUC (04),
     * consumidor final (07) ni identificación del exterior (08); esos sí valen
     * para el representante, que es un Cliente y se registra en su propio modal.
     */
    public const TIPOS_IDENTIFICACION = [self::CEDULA, self::PASAPORTE];

    public function validar(array $data): void
    {
        if (trim($data['nombres'] ?? '') === '') {
            throw new Exception('Los nombres del alumno son obligatorios.');
        }
        if (trim($data['apellidos'] ?? '') === '') {
            throw new Exception('Los apellidos del alumno son obligatorios.');
        }
        if (empty($data['id_cliente'])) {
            throw new Exception('El cliente que factura (pestaña Facturación) es obligatorio.');
        }

        $tipoId = trim($data['tipo_identificacion'] ?? '');
        $identificacion = trim($data['numero_identificacion'] ?? '');
        if ($tipoId !== '' && !in_array($tipoId, self::TIPOS_IDENTIFICACION, true)) {
            throw new Exception('El tipo de identificación del alumno solo puede ser cédula o pasaporte.');
        }
        if ($tipoId !== '' && $identificacion !== '') {
            if ($tipoId === self::CEDULA && !preg_match('/^[0-9]{10}$/', $identificacion)) {
                throw new Exception('La cédula del alumno debe tener exactamente 10 dígitos numéricos.');
            }
            if ($tipoId === self::PASAPORTE && mb_strlen($identificacion) > 20) {
                throw new Exception('El pasaporte del alumno no puede exceder 20 caracteres.');
            }
        }

        if (!empty($data['sexo']) && !in_array($data['sexo'], ['M', 'F', 'O'], true)) {
            throw new Exception('El sexo del alumno no es válido.');
        }

        $estadosValidos = ['activo', 'retirado', 'egresado', 'suspendido'];
        if (!empty($data['estado_academico']) && !in_array($data['estado_academico'], $estadosValidos, true)) {
            throw new Exception('El estado académico no es válido.');
        }

        if (!empty($data['periodos']) && is_array($data['periodos'])) {
            $this->validarPeriodos($data['periodos']);
        }
        if (!empty($data['horarios']) && is_array($data['horarios'])) {
            $this->validarHorarios($data['horarios']);
        }
        if (!empty($data['representantes']) && is_array($data['representantes'])) {
            $this->validarRepresentantes($data['representantes']);
        }
        foreach ($data['servicios'] ?? [] as $s) {
            if (mb_strlen(trim((string) ($s['detalle'] ?? ''))) > 300) {
                throw new Exception('El detalle de un servicio no puede exceder 300 caracteres.');
            }
        }
        if (!empty($data['servicios']) && is_array($data['servicios'])) {
            $this->validarServicios($data['servicios'], $data['config_facturacion'] ?? []);
        }
        if (isset($data['info_adicional']) && is_array($data['info_adicional'])) {
            $this->validarInfoAdicional($data['info_adicional']);
        }
    }

    /**
     * Servicios a facturar, con las mismas reglas que la Factura de Venta
     * (FacturaVentaRules::validarReglasEstablecimiento):
     *  - un ítem libre (concepto escrito a mano, sin producto) solo si el
     *    establecimiento tiene «Permitir ingreso de registros libremente»;
     *  - el concepto es obligatorio y llega normalizado (espacios/saltos de línea
     *    colapsados, ver normalizarConcepto()); máx. 300 caracteres (productos.nombre);
     *  - cambiar el IVA de la línea solo si «editar IVA en la factura» está activo.
     */
    public function validarServicios(array $servicios, array $config): void
    {
        $libre      = !empty($config['facturacion_libre']);
        $editarIva  = !empty($config['editar_iva_factura']);
        foreach (array_values($servicios) as $i => $s) {
            $num = $i + 1;
            if (!empty($s['es_libre'])) {
                if (!$libre) {
                    throw new Exception("Servicio #{$num}: No se permite el ingreso de ítems libres. Debe seleccionar productos del catálogo.");
                }
                $concepto = self::normalizarConcepto((string) ($s['nombre_libre'] ?? ''));
                if ($concepto === '') {
                    throw new Exception("Servicio #{$num}: El nombre o descripción del producto/servicio es obligatorio.");
                }
                if (mb_strlen($concepto) > 300) {
                    throw new Exception("Servicio #{$num}: El concepto no puede exceder 300 caracteres.");
                }
            } elseif (empty($s['id_producto'])) {
                continue; // fila vacía: se descarta
            }
            if (!empty($s['id_tarifa_iva']) && !$editarIva && empty($s['es_libre'])) {
                throw new Exception("Servicio #{$num}: La configuración de facturación no permite cambiar el IVA del producto.");
            }
            if (($s['precio_override'] ?? '') !== '' && (float) $s['precio_override'] < 0) {
                throw new Exception("Servicio #{$num}: El precio no puede ser negativo.");
            }
        }
    }

    /** Mismo criterio que la Factura de Venta: un solo espacio, sin saltos de línea ni bordes. */
    public static function normalizarConcepto(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    /**
     * Información adicional de las facturas (concepto / detalle). Topes del SRI:
     * 300 caracteres por campo; se deja margen para el correo del cliente que el
     * generador agrega solo (máx. 15 campos adicionales por comprobante).
     */
    public function validarInfoAdicional(array $filas): void
    {
        if (count($filas) > 10) {
            throw new Exception('La información adicional admite máximo 10 filas.');
        }
        foreach ($filas as $f) {
            $concepto = trim((string) ($f['concepto'] ?? ''));
            $detalle  = trim((string) ($f['detalle'] ?? ''));
            if ($concepto === '' || $detalle === '') {
                throw new Exception('Cada fila de información adicional necesita concepto y detalle.');
            }
            if (mb_strlen($concepto) > 300 || mb_strlen($detalle) > 300) {
                throw new Exception('El concepto y el detalle de la información adicional no pueden exceder 300 caracteres.');
            }
        }
    }

    /**
     * Representantes / autorizados a retirar: datos libres (no son Clientes).
     * Una fila sin nombre se descarta; los topes son los VARCHAR de la tabla.
     */
    public function validarRepresentantes(array $representantes): void
    {
        foreach ($representantes as $r) {
            $nombres = trim((string) ($r['nombres'] ?? ''));
            $otros = trim((string) ($r['identificacion'] ?? '')) . trim((string) ($r['telefono'] ?? ''));
            if ($nombres === '') {
                if ($otros !== '') {
                    throw new Exception('Cada representante debe tener nombres y apellidos.');
                }
                continue;
            }
            if (mb_strlen($nombres) > 200) {
                throw new Exception("El nombre del representante «{$nombres}» no puede exceder 200 caracteres.");
            }
            if (mb_strlen(trim((string) ($r['identificacion'] ?? ''))) > 20) {
                throw new Exception("La identificación del representante «{$nombres}» no puede exceder 20 caracteres.");
            }
            if (mb_strlen(trim((string) ($r['telefono'] ?? ''))) > 30) {
                throw new Exception("El teléfono del representante «{$nombres}» no puede exceder 30 caracteres.");
            }
            if (mb_strlen(trim((string) ($r['observacion'] ?? ''))) > 200) {
                throw new Exception("La observación del representante «{$nombres}» no puede exceder 200 caracteres.");
            }
        }
    }

    /**
     * Máximo un período abierto (matrícula vigente) y sin solapes entre
     * períodos del mismo alumno. Mismo criterio que EmpleadoRules::validatePeriodos.
     */
    public function validarPeriodos(array $periodos): void
    {
        $ps = array_values(array_filter($periodos, fn($p) => !empty($p['fecha_ingreso'])));
        if (count($ps) === 0) {
            return;
        }

        usort($ps, fn($a, $b) => strcmp($a['fecha_ingreso'], $b['fecha_ingreso']));

        $abiertos = 0;
        foreach ($ps as $p) {
            if (!empty($p['fecha_salida']) && $p['fecha_salida'] < $p['fecha_ingreso']) {
                throw new Exception('La fecha de salida de una matrícula no puede ser anterior a la fecha de ingreso.');
            }
            if (empty($p['fecha_salida'])) {
                $abiertos++;
            }
        }
        if ($abiertos > 1) {
            throw new Exception('El alumno no puede tener más de una matrícula vigente (sin fecha de salida) al mismo tiempo.');
        }

        $n = count($ps);
        for ($i = 1; $i < $n; $i++) {
            $prevSalida = $ps[$i - 1]['fecha_salida'] ?? null;
            if (!empty($prevSalida) && $ps[$i]['fecha_ingreso'] <= $prevSalida) {
                throw new Exception('Los períodos de matrícula no pueden solaparse entre sí.');
            }
        }
    }

    public function validarHorarios(array $horarios): void
    {
        foreach ($horarios as $h) {
            if (empty($h['dia_semana']) || empty($h['hora_inicio']) || empty($h['hora_fin'])) {
                continue;
            }
            if ($h['hora_fin'] <= $h['hora_inicio']) {
                throw new Exception('La hora de fin del horario debe ser posterior a la hora de inicio.');
            }
        }
    }
}
