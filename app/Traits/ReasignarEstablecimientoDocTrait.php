<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\modulos\ReasignarEstablecimientoService;

/**
 * Reasignar UN documento a otro establecimiento del mismo RUC desde el modal del documento
 * (Compras, Retenciones de venta). Reutiliza ReasignarEstablecimientoService: mismas reglas que
 * el módulo Documentos › Reasignar establecimientos (que hace lo mismo en lote) y exige su
 * permiso de modificar, además del permiso de modificar del módulo del documento.
 *
 * El controlador que lo usa (extiende BaseModuloController) implementa:
 *   - tipoReasignarEst(): tipo del servicio ('compras' | 'retenciones_venta').
 *   - nombreDocReasignarEst(): nombre del documento para los mensajes ('compra', 'retención').
 * Y en index() pasa a la vista datosReasignarEst($idEmpresa, $perm) → window.*_REASIGNAR_EST
 * (ver public/js/components/reasignar_establecimiento_doc.js).
 */
trait ReasignarEstablecimientoDocTrait
{
    private static string $RUTA_REASIGNAR_EST = 'modulos/reasignar-establecimiento';

    abstract protected function tipoReasignarEst(): string;

    abstract protected function nombreDocReasignarEst(): string;

    /**
     * Datos para el botón y el aviso del modal, o null si el RUC tiene un solo establecimiento
     * (en ese caso no hay a dónde reasignar y el modal no muestra nada).
     */
    protected function datosReasignarEst(int $idEmpresa, array $perm): ?array
    {
        $ests = (new ReasignarEstablecimientoService())->establecimientosGrupoRuc($idEmpresa);
        if (count($ests) <= 1) {
            return null;
        }
        return [
            'establecimientos' => $ests,
            'id_empresa'       => $idEmpresa,
            'puede'            => !empty($perm['actualizar']) && \App\Helpers\Permisos::puedeActualizar(self::$RUTA_REASIGNAR_EST),
            'puede_ver_modulo' => \App\Helpers\Permisos::puedeVer(self::$RUTA_REASIGNAR_EST),
        ];
    }

    /**
     * ¿El documento NO se puede reasignar por estar atado a otro (p. ej. retención de venta
     * atada a una factura)? Para ocultar el botón al abrir el modal. Devuelve el motivo o null.
     */
    protected function bloqueoReasignarEst(int $id, int $idEmpresa): ?string
    {
        if ($id <= 0) { return null; }
        return (new ReasignarEstablecimientoService())->motivoBloqueoFijo($idEmpresa, $this->tipoReasignarEst(), $id);
    }

    /** GET ?id=: vínculos del documento (asiento, pagos, inventario, retención) para avisar antes de reasignar. */
    public function verificarReasignacionEstAjax(): void
    {
        $this->requireLeer();
        try {
            $this->requirePermisoReasignarEst();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $id = (int) ($_GET['id'] ?? 0);
            if ($id <= 0) {
                throw new \InvalidArgumentException('Documento no válido.');
            }
            $data = (new ReasignarEstablecimientoService())->verificarVinculos($idEmpresa, $this->tipoReasignarEst(), [$id]);
            $this->json(['ok' => true, 'data' => $data]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo revisar el documento.']);
        }
    }

    /** POST id, id_establecimiento_destino, anular_vinculos: reasigna el documento. */
    public function reasignarEstablecimientoAjax(): void
    {
        $this->requireActualizar();
        $doc = $this->nombreDocReasignarEst();
        try {
            $this->requirePermisoReasignarEst();
            $idEmpresa    = (int) $_SESSION['id_empresa'];
            $idUsuario    = (int) $_SESSION['id_usuario'];
            $id           = (int) ($_POST['id'] ?? 0);
            $idEstDestino = (int) ($_POST['id_establecimiento_destino'] ?? 0);
            $anular       = !empty($_POST['anular_vinculos']);
            if ($id <= 0 || $idEstDestino <= 0) {
                throw new \InvalidArgumentException('Seleccione el establecimiento destino.');
            }
            // Registros propios (§6): sin acceso total solo puede mover los documentos que creó.
            $perm = $this->getPermisos();
            $idUsuarioFiltro = empty($perm['todo']) ? $idUsuario : null;

            $res = (new ReasignarEstablecimientoService())
                ->reasignar($idEmpresa, $this->tipoReasignarEst(), [$id], $idEstDestino, $idUsuario, $idUsuarioFiltro, $anular);

            if (empty($res['ok'])) {
                $this->json(['ok' => false, 'mensaje' => $res['mensaje'] ?? 'No se pudo reasignar.']);
            }
            if (($res['reasignados'] ?? 0) > 0) {
                $this->json(['ok' => true, 'mensaje' => 'Se reasignó la ' . $doc . ' al establecimiento seleccionado.', 'anulados' => $res['anulados'] ?? []]);
            }
            if (!empty($res['bloqueados_factura'])) {
                $mensaje = ReasignarEstablecimientoService::textoBloqueoFactura($this->tipoReasignarEst());
            } elseif (!empty($res['bloqueados_retencion'])) {
                $mensaje = 'La ' . $doc . ' tiene una retención emitida. Anule la retención desde su módulo y vuelva a intentar.';
            } elseif (!empty($res['bloqueados_colision'])) {
                $mensaje = 'La empresa destino ya tiene un documento con este mismo número; no se puede mover allá.';
            } elseif (!empty($res['omitidos_con_vinculos'])) {
                $mensaje = 'La ' . $doc . ' tiene contabilidad, pagos o inventario: confirme que desea anularlos para reasignarla.';
            } elseif (!empty($res['errores'])) {
                $mensaje = 'No se pudo reasignar: ' . ($res['errores'][0]['mensaje'] ?? 'error desconocido.');
            } else {
                $mensaje = 'La ' . $doc . ' ya está en ese establecimiento o no tiene permiso para modificarla.';
            }
            $this->json(['ok' => false, 'mensaje' => $mensaje]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo reasignar la ' . $doc . '.']);
        }
    }

    /** La reasignación exige permiso de modificar en Documentos › Reasignar establecimientos. */
    private function requirePermisoReasignarEst(): void
    {
        if (!\App\Helpers\Permisos::puedeActualizar(self::$RUTA_REASIGNAR_EST)) {
            throw new \InvalidArgumentException('No tiene permiso para reasignar establecimientos (Documentos › Reasignar establecimientos).');
        }
    }
}
