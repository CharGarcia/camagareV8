<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\AsientoContableRepository;
use App\Rules\modulos\AsientoContableRules;
use App\Services\LogSistemaService;

/**
 * Registro A MANO del asiento de un documento que no tiene asiento, desde la pestaña «Asiento
 * contable» de su modal (Ingresos y Egresos). Cubre los casos en que el sistema no lo generó:
 * un migrado que el sistema anterior nunca contabilizó, una cuenta sin configurar, un documento
 * de un módulo con la contabilidad apagada, etc.
 *
 * La pestaña propone las líneas que arman las reglas contables (vistaPrevia) y el usuario las
 * completa o corrige; registrar() guarda el asiento con el modulo_origen del documento, lo enlaza
 * (id_asiento_contable, así el aviso de pendientes deja de contarlo) y lo marca como editado a
 * mano (las regeneraciones automáticas lo respetan). Período cerrado y documento enlazado a un
 * asiento migrado los rechaza AsientoContableService::guardarAsiento(), como en cualquier asiento.
 *
 * Compras tiene su propio registro manual, más restringido (ComprasService::registrarAsientoManual).
 */
class AsientoManualDocumentoService
{
    private LogSistemaService $logService;

    public function __construct(LogSistemaService $logService)
    {
        $this->logService = $logService;
    }

    private function asientoService(): AsientoContableService
    {
        return new AsientoContableService(new AsientoContableRepository(), new AsientoContableRules(), $this->logService);
    }

    /** Motivo por el que el documento NO admite registrar su asiento a mano, o null si lo admite. */
    public function motivoNoManual(array $documento): ?string
    {
        if (strtoupper(trim((string) ($documento['estado'] ?? ''))) === 'ANULADO') {
            return 'El documento está anulado: no lleva asiento contable.';
        }
        if ((int) ($documento['id_asiento_contable'] ?? 0) > 0) {
            return 'El documento ya tiene asiento contable: corríjalo desde esta pestaña.';
        }
        return null;
    }

    /**
     * Respuesta para la pestaña de un documento SIN asiento (formato del previewUrl de
     * asiento_contable_tab.js): las líneas que arman las reglas contables y si se puede registrar
     * a mano. Si las reglas no alcanzan (cuenta sin configurar, descuadre), devuelve el motivo y
     * las líneas que sí se pudieron armar, para completarlas.
     *
     * @param callable $armarLineas fn(): array  líneas del builder ya con sus referencias; puede lanzar.
     * @param callable $verificar   fn(array $lineas): void  lanza el motivo si las líneas no alcanzan
     *                              (cuenta sin configurar, descuadre); las líneas igual se muestran.
     */
    public function vistaPrevia(array $documento, callable $armarLineas, callable $verificar, bool $puedeRegistrar): array
    {
        $motivo = $this->motivoNoManual($documento);
        if ($motivo !== null) {
            return ['ok' => true, 'detalles' => [], 'aviso' => $motivo];
        }

        $detalles = [];
        $error = null;
        try {
            $detalles = $armarLineas();
            $verificar($detalles);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            if ($e instanceof AsientoDescuadreDocumentoException) {
                $detalles = $e->getDetalles();
            }
        }

        $debe = 0.0;
        $haber = 0.0;
        foreach ($detalles as $d) {
            $debe  += (float) ($d['debe'] ?? 0);
            $haber += (float) ($d['haber'] ?? 0);
        }

        return [
            'ok'             => $error === null,
            'error'          => $error,
            'detalles'       => $detalles,
            'diferencia'     => round($debe - $haber, 2),
            'permite_manual' => $puedeRegistrar,
            'mensaje_manual' => 'Este documento no tiene asiento contable. Revise las líneas que propone la configuración contable, '
                . 'complételas si hace falta y pulse «Guardar asiento»: queda enlazado al documento y marcado como editado a mano.',
        ];
    }

    /**
     * Guarda el asiento a mano y lo enlaza al documento.
     *
     * Un guardado = un registro (§8): bajo el candado del documento se vuelve a mirar si ya tiene
     * asiento; un doble clic o un reintento tras perderse la respuesta devuelve el existente.
     *
     * @param array    $documento  fila del documento (estado, id_asiento_contable)
     * @param array    $cabecera   cabecera del asiento (modulo_origen, id_referencia_origen, fecha…)
     * @param array    $detalles   líneas de la pestaña {id_cuenta_contable, debe, haber, referencia_detalle}
     * @param array    $tercero    ['id_entidad' => ?int, 'tipo_entidad' => ?string] para las líneas sin tercero
     * @param string   $referencia documento_referencia de cada línea ("Egreso 18")
     * @param callable $enlazar    fn(int $idAsiento): void  fija id_asiento_contable en el documento
     * @param array    $log        ['accion' => …, 'tabla' => …]
     * @return array{ok: bool, id?: int, ya_existia?: bool, requiere_confirmacion?: bool, mensaje?: string}
     */
    public function registrar(
        array $documento,
        array $cabecera,
        array $detalles,
        array $tercero,
        string $referencia,
        callable $enlazar,
        int $idEmpresa,
        int $idUsuario,
        bool $confirmarDescuadre,
        array $log
    ): array {
        $modulo = (string) $cabecera['modulo_origen'];
        $idDoc  = (int) $cabecera['id_referencia_origen'];

        // Reintento de un guardado que sí llegó: devuelve el asiento que ya tiene.
        $idActual = (int) ($documento['id_asiento_contable'] ?? 0);
        if ($idActual > 0) {
            return ['ok' => true, 'id' => $idActual, 'ya_existia' => true];
        }
        $motivo = $this->motivoNoManual($documento);
        if ($motivo !== null) {
            throw new \Exception($motivo);
        }

        $lineas = $this->normalizarLineas($detalles, $tercero, $referencia);
        $cabecera['id'] = null;
        $cabecera['estado'] = 'contabilizado';

        $asientoService = $this->asientoService();

        // Misma comprobación que el módulo de Asientos: el asiento debe reflejar el importe del
        // documento. Sin línea de cartera no se guarda; con diferencia se avisa y se deja confirmar.
        $cuadre = $asientoService->evaluarCuadreDocumento($cabecera, $lineas, $idEmpresa);
        if ($cuadre !== null && !empty($cuadre['sin_linea_cartera'])) {
            throw new \Exception($cuadre['mensaje']);
        }
        $hayDescuadre = $cuadre !== null && empty($cuadre['cuadra']);
        if ($hayDescuadre && !$confirmarDescuadre) {
            return ['ok' => false, 'requiere_confirmacion' => true, 'mensaje' => $cuadre['mensaje']];
        }

        $db = Database::getConnection();
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        try {
            (new AsientoContableRepository())->lockAsientoOrigen($idEmpresa, $modulo, $idDoc);
            $previo = $asientoService->getAsientoPorOrigen($modulo, $idDoc, $idEmpresa);
            if ($previo) {
                if ($propia) {
                    $db->rollBack();
                }
                return ['ok' => true, 'id' => (int) $previo['id'], 'ya_existia' => true];
            }

            $idAsiento = $asientoService->guardarAsiento($cabecera, $lineas, $idEmpresa, $idUsuario, true);
            $enlazar($idAsiento);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, $log['accion'], $log['tabla'], $idDoc,
                ['id_asiento_contable' => null],
                ['id_asiento_contable' => $idAsiento, 'lineas' => $lineas]
            );
            if ($hayDescuadre) {
                $asientoService->registrarDescuadreConfirmado($idAsiento, $cuadre, $idEmpresa, $idUsuario);
            }

            if ($propia) {
                $db->commit();
            }
            return ['ok' => true, 'id' => $idAsiento, 'ya_existia' => false];
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Valida y normaliza las líneas de la pestaña: cuenta, un solo lado, Debe = Haber. */
    private function normalizarLineas(array $detalles, array $tercero, string $referencia): array
    {
        $lineas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($detalles as $i => $d) {
            $idCuenta = (int) ($d['id_cuenta_contable'] ?? 0);
            $debe  = round((float) ($d['debe'] ?? 0), 2);
            $haber = round((float) ($d['haber'] ?? 0), 2);
            if ($debe == 0.0 && $haber == 0.0) {
                continue;
            }
            if ($idCuenta <= 0) {
                throw new \Exception('La línea ' . ($i + 1) . ' del asiento no tiene cuenta contable.');
            }
            if ($debe < 0 || $haber < 0 || ($debe > 0 && $haber > 0)) {
                throw new \Exception('La línea ' . ($i + 1) . ' del asiento debe tener valor solo en el Debe o solo en el Haber.');
            }
            $ref = mb_substr(trim((string) ($d['referencia_detalle'] ?? '')), 0, 500);
            $docRef = trim((string) ($d['documento_referencia'] ?? ''));
            $lineas[] = [
                'id_cuenta_contable'   => $idCuenta,
                'debe'                 => $debe,
                'haber'                => $haber,
                'referencia_detalle'   => $ref !== '' ? $ref : $referencia,
                'documento_referencia' => mb_substr($docRef !== '' ? $docRef : $referencia, 0, 100),
                'id_entidad'           => $tercero['id_entidad'] ?? null,
                'tipo_entidad'         => $tercero['tipo_entidad'] ?? null,
                'id_centro_costo'      => !empty($d['id_centro_costo']) ? (int) $d['id_centro_costo'] : null,
                'id_proyecto'          => !empty($d['id_proyecto']) ? (int) $d['id_proyecto'] : null,
            ];
            $totalDebe  += $debe;
            $totalHaber += $haber;
        }
        if (count($lineas) < 2) {
            throw new \Exception('El asiento necesita al menos una línea al Debe y una al Haber.');
        }
        if (abs(round($totalDebe - $totalHaber, 2)) >= 0.005) {
            throw new \Exception('El asiento no cuadra: Debe ' . number_format($totalDebe, 2) . ' y Haber '
                . number_format($totalHaber, 2) . ' deben ser iguales.');
        }
        return $lineas;
    }
}
