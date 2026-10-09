<?php
declare(strict_types=1);

namespace App\Traits;

use App\repositories\modulos\PeriodosContablesRepository;
use App\Rules\modulos\PeriodosContablesRules;
use App\Services\LogSistemaService;
use App\Services\modulos\PeriodosContablesService;

/**
 * Control de períodos contables cerrados para los Services de documentos.
 *
 * Un documento que mueve cartera o genera asiento no puede crearse, modificarse,
 * anularse ni eliminarse dentro de un período ya cerrado
 * (`periodos_contables.status = 0`). Compras, Ingresos, Egresos, Traspasos y
 * Asientos ya lo comprobaban cada uno por su cuenta; este trait es la misma
 * regla, escrita una sola vez, para el resto de módulos de documentos.
 *
 * **Al modificar hay que validar las DOS fechas**: la nueva y aquella con la que
 * el documento está registrado. Comprobar solo la nueva deja pasar el caso de
 * sacar un documento de un mes cerrado hacia uno abierto, que altera igualmente
 * el período cerrado.
 */
trait PeriodoContableTrait
{
    /**
     * Desactiva la comprobación para procesos de carga histórica: migración desde
     * el sistema anterior e importación de comprobantes ya emitidos. Ahí los
     * documentos son un hecho consumado —ya existieron y ya se declararon— y
     * rechazarlos solo dejaría al sistema sin ellos. Lo activa el proceso que
     * carga, nunca la pantalla de un módulo.
     */
    public bool $omitirValidacionPeriodo = false;

    private ?PeriodosContablesService $periodoContableService = null;

    /**
     * Corta con excepción si la fecha cae en un período cerrado.
     *
     * @param string|null $fecha   Fecha del documento (admite fecha u hora completa).
     * @param string      $mensaje Qué se estaba intentando hacer, en palabras del usuario.
     */
    protected function validarPeriodoContable(?string $fecha, int $idEmpresa, string $mensaje): void
    {
        if ($this->omitirValidacionPeriodo) {
            return;
        }

        $fecha = trim((string) $fecha);
        if ($fecha === '' || $idEmpresa <= 0) {
            return;
        }

        $this->periodoContableService()->validarFechaPermitida(substr($fecha, 0, 10), $idEmpresa, $mensaje);
    }

    /**
     * Las dos fechas de una modificación: la que tiene el documento guardado y la
     * que quedaría al guardar. Cualquiera de las dos en un período cerrado corta.
     */
    protected function validarPeriodoContableAlModificar(
        ?string $fechaActual,
        ?string $fechaNueva,
        int $idEmpresa,
        string $documento
    ): void {
        $this->validarPeriodoContable(
            $fechaActual,
            $idEmpresa,
            "No se puede modificar {$documento} porque el período contable en el que está registrado ya está cerrado."
        );
        $this->validarPeriodoContable(
            $fechaNueva,
            $idEmpresa,
            "No se puede guardar {$documento} en esa fecha porque su período contable está cerrado."
        );
    }

    /**
     * Antes de anular, en pasos separados, el egreso y el asiento de un documento (p. ej. al
     * reabrir una declaración): comprueba de entrada que NINGUNO esté en un período cerrado.
     * Cada anulación lleva su propia transacción; si la segunda fallaba por el período, la
     * primera ya había quedado hecha y el documento a medio reabrir.
     */
    protected function validarPeriodoEgresoYAsiento(?int $idEgreso, ?int $idAsiento, int $idEmpresa, string $documento): void
    {
        if ($idEgreso) {
            $egreso = (new \App\repositories\modulos\EgresoRepository())->getPorId($idEgreso, $idEmpresa);
            if ($egreso && ($egreso['estado'] ?? '') !== 'anulado') {
                $this->validarPeriodoContable(
                    (string) ($egreso['fecha_emision'] ?? ''),
                    $idEmpresa,
                    "No se puede reabrir {$documento}: su egreso de pago " . trim((string) ($egreso['numero_egreso'] ?? ''))
                        . ' es de un período contable cerrado.'
                );
            }
        }
        if ($idAsiento) {
            $asiento = (new \App\repositories\modulos\AsientoContableRepository())->getDetalleAsiento($idAsiento, $idEmpresa);
            if ($asiento && ($asiento['estado'] ?? '') !== 'anulado') {
                $this->validarPeriodoContable(
                    (string) ($asiento['fecha_asiento'] ?? ''),
                    $idEmpresa,
                    "No se puede reabrir {$documento}: su asiento " . trim((string) ($asiento['numero_comprobante'] ?? ''))
                        . ' es de un período contable cerrado.'
                );
            }
        }
    }

    private function periodoContableService(): PeriodosContablesService
    {
        return $this->periodoContableService ??= new PeriodosContablesService(
            new PeriodosContablesRepository(),
            new PeriodosContablesRules(),
            new LogSistemaService()
        );
    }
}
