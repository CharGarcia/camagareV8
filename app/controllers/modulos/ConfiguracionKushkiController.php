<?php
declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\KushkiRepository;
use App\Services\modulos\KushkiService;

class ConfiguracionKushkiController extends BaseModuloController
{
    private KushkiRepository $repo;
    private const RUTA_MODULO = 'modulos/configuracion-kushki';

    public function __construct()
    {
        parent::__construct();
        $this->repo = new KushkiRepository();
    }

    protected function getRutaModulo(): string
    {
        return self::RUTA_MODULO;
    }

    public function index(): void
    {
        $this->requireLeer();

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $config    = $this->repo->getConfig($idEmpresa);
        $permisos  = $this->getPermisos();

        $this->viewWithLayout('layouts.main', 'modulos/configuracion_kushki/index', [
            'titulo'   => 'Configuración Kushki',
            'config'   => $config,
            'permisos' => $permisos,
            'urlBase'  => rtrim(BASE_URL, '/') . '/' . self::RUTA_MODULO,
        ]);
    }

    public function guardar(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            $publicKey  = trim($_POST['public_key']  ?? '');
            $privateKey = trim($_POST['private_key'] ?? '');
            $ambiente   = in_array($_POST['ambiente'] ?? '', ['uat', 'production'])
                ? $_POST['ambiente']
                : 'uat';
            $moneda  = trim($_POST['moneda'] ?? 'USD') ?: 'USD';
            $activo  = (($_POST['activo'] ?? '0') === '1');

            if ($publicKey === '' || $privateKey === '') {
                echo json_encode(['ok' => false, 'mensaje' => 'La clave pública y privada son obligatorias.']);
                exit;
            }

            $this->repo->upsertConfig([
                'id_empresa'  => $idEmpresa,
                'public_key'  => $publicKey,
                'private_key' => $privateKey,
                'ambiente'    => $ambiente,
                'moneda'      => $moneda,
                'activo'      => $activo,
                'id_usuario'  => $idUsuario,
            ]);

            echo json_encode(['ok' => true, 'mensaje' => 'Configuración guardada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    public function probarConexion(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $kushki    = new KushkiService($idEmpresa);
            $resultado = $kushki->testConexion();
            echo json_encode($resultado);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
