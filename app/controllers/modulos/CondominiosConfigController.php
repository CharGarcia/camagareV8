<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\Rules\modulos\CondominioRules;
use App\Services\modulos\CondominioService;

/**
 * Configuración de condominios (modulos/condominios-config): datos del condominio, emisión,
 * alícuota y fondo de reserva, mora y catálogo de multas, descuentos. Es un submódulo propio,
 * con sus propios permisos (ver = consultar; actualizar = guardar la configuración; crear /
 * actualizar / eliminar = catálogo de multas). Guardar la configuración ACTIVA el módulo
 * Condominios para la empresa. Sin lógica de negocio: delega a CondominioService.
 */
class CondominiosConfigController extends BaseModuloController
{
    private const RUTA_MODULO = 'modulos/condominios-config';

    private CondominioService $service;

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function __construct()
    {
        parent::__construct();
        $this->service = CondominioService::crear();
    }

    private function sesion(): array
    {
        return [(int) $_SESSION['id_empresa'], (int) $_SESSION['id_usuario']];
    }

    private function error(\Throwable $e, string $accion): never
    {
        if (!($e instanceof \DomainException || $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => $accion]);
            $this->json(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado.']);
        }
        $this->json(['ok' => false, 'mensaje' => $e->getMessage()]);
    }

    public function index(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $instalado = $this->service->instalado();
        $cfg = $instalado ? $this->service->getConfig($idEmpresa) : null;
        // Valores por defecto para un condominio aún sin configurar: el nombre de la empresa y la
        // dirección de su establecimiento principal (el condominio ES la empresa).
        $empresaModel = new \App\models\Empresa();
        $empresa = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $defaults = [
            'nombre_condominio' => (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? '')),
            'direccion'         => (string) (($establecimientos[0]['direccion'] ?? '') ?: ($empresa['direccion'] ?? '')),
            // El representante legal de la empresa suele ser quien administra el condominio.
            'administrador_nombre' => (string) ($empresa['nom_rep_legal'] ?? ''),
            'administrador_cedula' => (string) ($empresa['ced_rep_legal'] ?? ''),
        ];
        $this->viewWithLayout('layouts.main', 'modulos/condominios_config/index', [
            'titulo'        => 'Configuración de condominios',
            'perm'          => $this->getPermisos(),
            'rutaModulo'    => self::RUTA_MODULO,
            'vistaConfig'   => \App\Helpers\PreferenciasHelper::getPreferenciasVista(self::RUTA_MODULO),
            'instalado'     => $instalado,
            'config'        => $cfg,
            'defaults'      => $defaults,
            'pendientes'    => $instalado ? $this->service->pendientesConfig($cfg) : [],
            'multas'        => $instalado && $cfg ? $this->service->repo()->getMultas($idEmpresa) : [],
            'metodos'       => CondominioRules::METODOS_LABEL,
            'base'          => BASE_URL,
        ]);
    }

    public function guardarAjax(): void
    {
        $this->requireActualizar();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $cfg = $this->service->guardarConfig($_POST, $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'config' => $cfg, 'pendientes' => $this->service->pendientesConfig($cfg),
                         'mensaje' => 'Configuración guardada. El módulo Condominios está activo para esta empresa.']);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Catálogo de multas: listar (r), guardar (w nueva / u existente), eliminar (d). */
    public function multasAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa, $idUsuario] = $this->sesion();
        try {
            $accion = (string) ($_POST['accion'] ?? 'listar');
            if ($accion === 'guardar') {
                (int) ($_POST['id'] ?? 0) > 0 ? $this->requireActualizar() : $this->requireCrear();
                $id = $this->service->guardarMulta($_POST, $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'id' => $id, 'multas' => $this->service->repo()->getMultas($idEmpresa), 'mensaje' => 'Multa guardada.']);
            }
            if ($accion === 'eliminar') {
                $this->requireEliminar();
                $this->service->eliminarMulta((int) ($_POST['id'] ?? 0), $idEmpresa, $idUsuario);
                $this->json(['ok' => true, 'multas' => $this->service->repo()->getMultas($idEmpresa), 'mensaje' => 'Multa eliminada.']);
            }
            $this->json(['ok' => true, 'multas' => $this->service->repo()->getMultas($idEmpresa)]);
        } catch (\Throwable $e) {
            $this->error($e, __FUNCTION__);
        }
    }

    /** Servicios activos de la empresa para los selectores de concepto (buscador tipo chip). */
    public function buscarServiciosAjax(): void
    {
        $this->requireLeer();
        [$idEmpresa] = $this->sesion();
        $this->json(['ok' => true, 'rows' => $this->service->repo()->buscarServicios($idEmpresa, (string) ($_GET['q'] ?? ''))]);
    }
}
