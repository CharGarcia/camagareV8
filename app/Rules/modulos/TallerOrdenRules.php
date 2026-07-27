<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use App\Helpers\Booleano;
use Exception;

/**
 * Validaciones de negocio del módulo Taller Mecánico.
 *
 * La regla central del módulo: NADA se ejecuta ni se factura sin la aprobación
 * del presupuesto por parte del cliente. Un operario puede sugerir repuestos y
 * mano de obra desde su departamento, pero recién pasan a ejecutarse cuando el
 * asesor registra quién aprobó, cuándo y por qué medio.
 */
class TallerOrdenRules
{
    /** Estados de la orden, en el orden natural del flujo. */
    public const ESTADOS = [
        'recepcion', 'diagnostico', 'presupuesto', 'aprobada', 'en_proceso',
        'control_calidad', 'terminada', 'entregada', 'facturada', 'anulada',
    ];

    /** Estados en los que la orden ya no admite cambios operativos. */
    public const ESTADOS_CERRADOS = ['entregada', 'facturada', 'anulada'];

    public const TIPOS_SERVICIO = ['mantenimiento', 'correctivo', 'colision', 'garantia', 'revision'];
    public const PRIORIDADES    = ['baja', 'normal', 'alta', 'urgente'];
    public const TIPOS_LINEA    = ['repuesto', 'mano_obra', 'insumo', 'tercero'];
    public const ESTADOS_LINEA  = ['sugerida', 'aprobada', 'rechazada', 'ejecutada'];
    public const MEDIOS_APROBACION = ['presencial', 'telefono', 'whatsapp', 'correo', 'sistema'];

    // ─── Recepción de la orden ────────────────────────────────────────────────

    /**
     * La recepción NO exige líneas: el vehículo entra primero y el diagnóstico
     * viene después. Lo que sí exige es identificar el vehículo, la fecha y la
     * numeración, además del motivo por el que el cliente lo trae.
     */
    public function validarRecepcion(array $data): void
    {
        if (empty($data['id_vehiculo'])) {
            throw new Exception("Debe seleccionar el vehículo que ingresa al taller.");
        }
        if (empty($data['fecha_ingreso'])) {
            throw new Exception("La fecha de ingreso es obligatoria.");
        }
        if (empty($data['id_punto_emision']) || (string) ($data['secuencial'] ?? '') === '') {
            throw new Exception("Falta la serie / secuencial. Seleccione el punto de emisión.");
        }
        if (trim((string) ($data['motivo_ingreso'] ?? '')) === '') {
            throw new Exception("Indique el motivo de ingreso: qué reporta el cliente o qué se le va a hacer al vehículo.");
        }

        $tipo = (string) ($data['tipo_servicio'] ?? 'correctivo');
        if (!in_array($tipo, self::TIPOS_SERVICIO, true)) {
            throw new Exception("Tipo de servicio no válido.");
        }
        $prio = (string) ($data['prioridad'] ?? 'normal');
        if (!in_array($prio, self::PRIORIDADES, true)) {
            throw new Exception("Prioridad no válida.");
        }

        if (($data['kilometraje'] ?? '') !== '' && (int) $data['kilometraje'] < 0) {
            throw new Exception("El kilometraje no puede ser negativo.");
        }

        if (!empty($data['fecha_estimada_entrega']) && !empty($data['fecha_ingreso'])) {
            $ing = strtotime((string) $data['fecha_ingreso']);
            $ent = strtotime((string) $data['fecha_estimada_entrega']);
            if ($ing !== false && $ent !== false && $ent < $ing) {
                throw new Exception("La fecha estimada de entrega no puede ser anterior al ingreso.");
            }
        }

        if (!empty($data['es_siniestro'])) {
            if (trim((string) ($data['aseguradora'] ?? '')) === '') {
                throw new Exception("Si la orden es por siniestro, indique la aseguradora.");
            }
            if (trim((string) ($data['numero_siniestro'] ?? '')) === '') {
                throw new Exception("Si la orden es por siniestro, indique el número de siniestro.");
            }
        }

        if (!empty($data['proxima_cita'])) {
            $cita = strtotime((string) $data['proxima_cita']);
            $hoy  = strtotime(date('Y-m-d'));
            if ($cita !== false && $cita < $hoy) {
                throw new Exception("La fecha de la próxima cita no puede ser anterior a hoy.");
            }
        }
    }

    /** La orden debe estar abierta para poder modificarla. */
    public function validarOrdenEditable(array $orden): void
    {
        $estado = (string) ($orden['estado'] ?? '');
        if (!empty($orden['id_documento'])) {
            throw new Exception("La orden ya generó el documento " . ($orden['numero_documento'] ?? '') . "; no puede modificarse.");
        }
        if (in_array($estado, self::ESTADOS_CERRADOS, true)) {
            throw new Exception("La orden está " . $estado . "; no puede modificarse.");
        }
    }

    // ─── Líneas (repuestos y mano de obra) ────────────────────────────────────

    public function validarLinea(array $d): void
    {
        $desc = trim((string) ($d['descripcion'] ?? ''));
        if ($desc === '') {
            throw new Exception("La descripción del repuesto o trabajo es obligatoria.");
        }
        $tipo = (string) ($d['tipo_linea'] ?? 'repuesto');
        if (!in_array($tipo, self::TIPOS_LINEA, true)) {
            throw new Exception("Tipo de línea no válido.");
        }
        $cant = (float) ($d['cantidad'] ?? 0);
        if ($cant <= 0) {
            throw new Exception("La cantidad de \"" . $desc . "\" debe ser mayor a 0.");
        }
        if ((float) ($d['precio_unitario'] ?? 0) < 0) {
            throw new Exception("El precio de \"" . $desc . "\" no puede ser negativo.");
        }
        if ((float) ($d['descuento'] ?? 0) < 0) {
            throw new Exception("El descuento de \"" . $desc . "\" no puede ser negativo.");
        }
        if ($tipo === 'mano_obra' && (float) ($d['horas'] ?? 0) < 0) {
            throw new Exception("Las horas de \"" . $desc . "\" no pueden ser negativas.");
        }
        // Un repuesto que trae el cliente se registra para el informe, pero no se cobra.
        if (Booleano::es($d['provisto_cliente'] ?? false) && Booleano::es($d['facturable'] ?? false)) {
            throw new Exception("Un repuesto provisto por el cliente no puede marcarse como facturable.");
        }
    }

    /**
     * Una línea solo se ejecuta si el presupuesto de la orden fue aprobado.
     * Esta es la regla que el taller pidió que sea obligatoria.
     */
    public function validarEjecucionLinea(array $orden, array $linea): void
    {
        if (Booleano::no($orden['aprobado'] ?? false)) {
            throw new Exception("El presupuesto no está aprobado por el cliente. Registre la aprobación antes de ejecutar trabajos.");
        }
        $estado = (string) ($linea['estado_linea'] ?? 'sugerida');
        if ($estado === 'rechazada') {
            throw new Exception("La línea \"" . ($linea['descripcion'] ?? '') . "\" fue rechazada por el cliente; no puede ejecutarse.");
        }
    }

    public function validarEstadoLinea(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_LINEA, true)) {
            throw new Exception("Estado de línea no válido.");
        }
    }

    // ─── Aprobación del presupuesto ───────────────────────────────────────────

    public function validarAprobacion(array $orden, array $d): void
    {
        if (Booleano::es($orden['aprobado'] ?? false)) {
            throw new Exception("El presupuesto de esta orden ya fue aprobado.");
        }
        $this->validarOrdenEditable($orden);

        if (trim((string) ($d['aprobado_por'] ?? '')) === '') {
            throw new Exception("Indique el nombre de quién aprobó el presupuesto por parte del cliente.");
        }
        $medio = (string) ($d['aprobado_medio'] ?? '');
        if (!in_array($medio, self::MEDIOS_APROBACION, true)) {
            throw new Exception("Indique por qué medio aprobó el cliente (presencial, teléfono, WhatsApp, correo).");
        }
    }

    // ─── Etapas / departamentos ───────────────────────────────────────────────

    public function validarEnvioDepartamento(array $orden, int $idDepartamento): void
    {
        $this->validarOrdenEditable($orden);
        if ($idDepartamento <= 0) {
            throw new Exception("Seleccione el departamento al que pasa el vehículo.");
        }
    }

    /**
     * Para trabajar en un departamento el presupuesto debe estar aprobado.
     * Única excepción: el departamento de diagnóstico, que justamente existe
     * para producir el presupuesto que el cliente va a aprobar.
     */
    public function validarInicioEtapa(array $orden, array $departamento): void
    {
        $this->validarOrdenEditable($orden);
        $esDiagnostico = Booleano::es($departamento['es_diagnostico'] ?? false);
        if (!$esDiagnostico && Booleano::no($orden['aprobado'] ?? false)) {
            throw new Exception("No se puede iniciar el trabajo en " . ($departamento['nombre'] ?? 'este departamento')
                . ": el cliente todavía no aprueba el presupuesto.");
        }
    }

    public function validarCierreEtapa(array $orden, array $etapa, array $d): void
    {
        if (($etapa['estado'] ?? '') === 'terminada') {
            throw new Exception("Esta etapa ya fue cerrada.");
        }
        if (trim((string) ($d['trabajo_realizado'] ?? '')) === '') {
            throw new Exception("Describa el trabajo realizado antes de cerrar la etapa. Es lo que se imprime en el informe técnico.");
        }
    }

    // ─── Cierre de la orden ───────────────────────────────────────────────────

    public function validarEntrega(array $orden, bool $tieneEtapasAbiertas): void
    {
        $estado = (string) ($orden['estado'] ?? '');
        if (in_array($estado, ['entregada', 'facturada', 'anulada'], true)) {
            throw new Exception("La orden ya está " . $estado . ".");
        }
        if ($tieneEtapasAbiertas) {
            throw new Exception("Hay departamentos con trabajo sin cerrar. Cierre todas las etapas antes de entregar el vehículo.");
        }
        if (Booleano::no($orden['aprobado'] ?? false)) {
            throw new Exception("La orden no tiene el presupuesto aprobado por el cliente.");
        }
    }

    public function validarEstado(string $estado): void
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new Exception("Estado no válido.");
        }
    }

    // ─── Facturación ──────────────────────────────────────────────────────────

    public function validarGeneracionDocumento(array $orden, string $tipo, array $extra): void
    {
        $tipo = strtoupper($tipo);
        if (!in_array($tipo, ['FACTURA', 'RECIBO'], true)) {
            throw new Exception("Tipo de documento no válido.");
        }
        if (!empty($orden['id_documento'])) {
            throw new Exception("Esta orden ya generó un documento (" . ($orden['numero_documento'] ?? '') . ").");
        }
        if (empty($orden['id_cliente'])) {
            throw new Exception("Debe asignar un cliente a la orden antes de facturar.");
        }
        if (($orden['estado'] ?? '') === 'anulada') {
            throw new Exception("La orden está anulada; no se puede facturar.");
        }
        if (Booleano::no($orden['aprobado'] ?? false)) {
            throw new Exception("No se puede facturar una orden sin la aprobación del cliente.");
        }
    }

    public function validarEliminacion(array $orden): void
    {
        if (!empty($orden['id_documento'])) {
            throw new Exception("No se puede eliminar una orden que ya generó un documento. Anule primero el documento.");
        }
    }
}
