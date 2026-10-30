<?php
/**
 * Endpoints del control de mantenimientos por kilometraje, compartidos por Car-Wash
 * (pestaña Mantenimiento de la orden) y Vehículos (pestaña Mantenimiento de la ficha).
 * Cada controlador aplica SUS permisos: desde Car-Wash basta el permiso de Car-Wash.
 */
declare(strict_types=1);

namespace App\Traits;

use App\Services\modulos\VehiculoMantenimientoService;

trait MantenimientoVehiculoTrait
{
    private function mantResponder(callable $fn): void
    {
        header('Content-Type: application/json');
        try {
            echo json_encode(['ok' => true] + $fn(), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (!($e instanceof \Exception) || $e instanceof \PDOException) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'mantenimientos']);
            }
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** ¿Puede cambiar intervalos y el catálogo de servicios? (permiso de modificar del módulo) */
    private function mantPuedeConfigurar(): bool
    {
        $p = $this->getPermisos();
        return !empty($p['actualizar']) || !empty($p['todo']);
    }

    /** Estado de los mantenimientos del vehículo. GET id_vehiculo, id_orden (opcional), historial=1. */
    public function mantEstadoAjax(): void
    {
        $this->requireLeer();
        $this->mantResponder(function () {
            $idVehiculo = (int) ($_GET['id_vehiculo'] ?? 0);
            if ($idVehiculo <= 0) throw new \Exception('Vehículo no válido.');
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $svc = new VehiculoMantenimientoService();
            $data = $svc->estado($idVehiculo, $idEmpresa, (int) ($_GET['id_orden'] ?? 0));
            if (!empty($_GET['historial'])) {
                $data['historial'] = array_map(function ($h) {
                    $h['fecha_txt']   = !empty($h['fecha']) ? date('d-m-Y', strtotime((string) $h['fecha'])) : '';
                    $h['creado_txt']  = !empty($h['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $h['created_at'])) : '';
                    return $h;
                }, $svc->historial($idVehiculo, $idEmpresa));
                $av = new \App\Services\modulos\MantenimientoAvisoService();
                $data['avisos_disponible'] = $av->disponible();
                $data['avisos'] = array_map(function ($a) {
                    $a['creado_txt'] = !empty($a['created_at']) ? date('d-m-Y H:i:s', strtotime((string) $a['created_at'])) : '';
                    return $a;
                }, $av->avisosVehiculo($idVehiculo, $idEmpresa));
            }
            $data['puede_configurar'] = $this->mantPuedeConfigurar();
            return ['data' => $data];
        });
    }

    /** Catálogo de servicios de la empresa (incluye los inactivos). */
    public function mantServiciosAjax(): void
    {
        $this->requireLeer();
        $this->mantResponder(function () {
            $svc = new VehiculoMantenimientoService();
            if (!$svc->disponible()) return ['disponible' => false, 'data' => []];
            return ['disponible' => true, 'puede_configurar' => $this->mantPuedeConfigurar(), 'data' => $svc->servicios((int) $_SESSION['id_empresa'])];
        });
    }

    public function mantGuardarServicioAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            $id = (int) ($_POST['id'] ?? 0);
            $s = (new VehiculoMantenimientoService())->guardarServicio($id, (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'nombre' => $_POST['nombre'] ?? '', 'intervalo_km' => $_POST['intervalo_km'] ?? 0, 'aviso_km' => $_POST['aviso_km'] ?? 0,
                'orden' => $_POST['orden'] ?? 0, 'activo' => $_POST['activo'] ?? '1',
            ]);
            return ['data' => $s, 'nuevo' => $id === 0, 'msg' => $id ? 'Servicio actualizado.' : 'Servicio creado.'];
        });
    }

    public function mantEliminarServicioAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            (new VehiculoMantenimientoService())->eliminarServicio((int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']);
            return ['msg' => 'Servicio eliminado.'];
        });
    }

    /** Vehículos con datos en el servicio (para avisar antes de eliminarlo). */
    public function mantUsoServicioAjax(): void
    {
        $this->requireLeer();
        $this->mantResponder(fn() => ['n' => (new VehiculoMantenimientoService())->vehiculosConServicio((int) ($_GET['id'] ?? 0), (int) $_SESSION['id_empresa'])]);
    }

    /** Intervalo propio del vehículo para un servicio (vacío = volver al sugerido). */
    public function mantGuardarIntervaloAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            $v = trim((string) ($_POST['intervalo_km'] ?? ''));
            (new VehiculoMantenimientoService())->guardarIntervalo(
                (int) ($_POST['id_vehiculo'] ?? 0), (int) ($_POST['id_servicio'] ?? 0),
                (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $v === '' ? null : (int) $v
            );
            return ['msg' => 'Intervalo guardado.'];
        });
    }

    /** "No aplica a este vehículo" (no_aplica=1) o "vuelve a aplicar" (no_aplica=0). */
    public function mantNoAplicaAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            $no = ($_POST['no_aplica'] ?? '1') === '1';
            (new VehiculoMantenimientoService())->marcarNoAplica(
                (int) ($_POST['id_vehiculo'] ?? 0), (int) ($_POST['id_servicio'] ?? 0),
                (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], $no
            );
            return ['msg' => $no ? 'El servicio ya no aplica a este vehículo.' : 'El servicio vuelve a aplicar a este vehículo.'];
        });
    }

    /** Cuántos vehículos más hay de la misma marca y modelo / marca. */
    public function mantAlcanceMarcaAjax(): void
    {
        $this->requireLeer();
        $this->mantResponder(fn() => ['data' => (new VehiculoMantenimientoService())->alcanceMismaMarca((int) ($_GET['id_vehiculo'] ?? 0), (int) $_SESSION['id_empresa'])]);
    }

    public function mantAplicarMarcaAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            $n = (new VehiculoMantenimientoService())->aplicarIntervaloMismaMarca(
                (int) ($_POST['id_vehiculo'] ?? 0), (int) ($_POST['id_servicio'] ?? 0), (int) $_SESSION['id_empresa'],
                (int) $_SESSION['id_usuario'], (string) ($_POST['alcance'] ?? 'modelo'), (int) ($_POST['intervalo_km'] ?? 0)
            );
            return ['n' => $n, 'msg' => "Intervalo aplicado a {$n} " . ($n === 1 ? 'vehículo' : 'vehículos') . '.'];
        });
    }

    /** Aviso manual al cliente: servicios a avisar, destinatarios y mensaje por defecto. */
    public function mantAvisoPreviewAjax(): void
    {
        $this->requireLeer();
        $this->mantResponder(fn() => ['data' => (new \App\Services\modulos\MantenimientoAvisoService())
            ->grupoManual((int) ($_GET['id_vehiculo'] ?? 0), (int) $_SESSION['id_empresa'])]);
    }

    /** Envía por correo el aviso de próximo mantenimiento (manual). */
    public function mantAvisoCorreoAjax(): void
    {
        $this->requireActualizar();
        session_write_close(); // el envío de correo no debe bloquear las demás peticiones del usuario
        $this->mantResponder(function () {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $svc = new \App\Services\modulos\MantenimientoAvisoService();
            $g = $svc->grupoManual((int) ($_POST['id_vehiculo'] ?? 0), $idEmpresa);
            $r = $svc->enviarCorreo($g, $idEmpresa, (int) $_SESSION['id_usuario'], trim((string) ($_POST['destinatarios'] ?? '')),
                trim((string) ($_POST['asunto'] ?? '')), trim((string) ($_POST['mensaje'] ?? '')), 'manual');
            if (!$r['ok']) throw new \Exception($r['mensaje']);
            return ['msg' => $r['mensaje']];
        });
    }

    /** Deja constancia del aviso abierto en WhatsApp (wa.me) desde la ficha. */
    public function mantAvisoWhatsappAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            (new \App\Services\modulos\MantenimientoAvisoService())->registrarWhatsappManual(
                (int) ($_POST['id_vehiculo'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'],
                trim((string) ($_POST['telefono'] ?? '')), trim((string) ($_POST['mensaje'] ?? ''))
            );
            return ['msg' => 'Aviso registrado.'];
        });
    }

    /** Cambio registrado a mano (hecho en otro lugar o sin orden). */
    public function mantRegistrarCambioAjax(): void
    {
        $this->requireActualizar();
        $this->mantResponder(function () {
            $id = (new VehiculoMantenimientoService())->registrarManual((int) ($_POST['id_vehiculo'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], [
                'id_servicio' => $_POST['id_servicio'] ?? 0, 'km' => $_POST['km'] ?? '', 'fecha' => $_POST['fecha'] ?? '',
                'intervalo_km' => $_POST['intervalo_km'] ?? 0, 'proximo_km' => $_POST['proximo_km'] ?? 0, 'observacion' => $_POST['observacion'] ?? '',
            ]);
            return ['id' => $id, 'msg' => 'Cambio registrado.'];
        });
    }

    public function mantEliminarCambioAjax(): void
    {
        $this->requireEliminar();
        $this->mantResponder(function () {
            (new VehiculoMantenimientoService())->eliminarCambioManual(
                (int) ($_POST['id'] ?? 0), (int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario'], (int) ($_POST['id_vehiculo'] ?? 0) ?: null
            );
            return ['msg' => 'Cambio eliminado.'];
        });
    }
}
