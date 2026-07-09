<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\Services\MigracionMysql\MigracionMysqlService;
use App\Services\MigracionMysql\LegacyMysqlConnection;
use PDO;
use Throwable;

/**
 * Tarjeta de /config (solo superadmin): "Migrar desde base anterior (MySQL)".
 * Conecta a la BD MySQL vieja, muestra un resumen por entidad para la empresa
 * seleccionada, y (por fases) migra el dato al sistema nuevo.
 */
class MigrarMysqlController extends Controller
{
    private MigracionMysqlService $service;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        if ((int) ($_SESSION['nivel'] ?? 0) < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el superadministrador puede acceder a la migración.'];
            $this->redirect(BASE_URL . '/config');
        }
        $this->service = new MigracionMysqlService();
    }

    public function index(): void
    {
        $db = Database::getConnection();
        $empresas = $db->query(
            "SELECT id, ruc, establecimiento,
                    COALESCE(NULLIF(nombre_comercial,''), nombre) AS razon_social
               FROM empresas
              WHERE eliminado = false
              ORDER BY razon_social, ruc"
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->viewWithLayout('layouts.main', 'config.migrar_mysql', [
            'titulo'          => 'Migrar desde base anterior (MySQL)',
            'empresasMigrar'  => $empresas, // NO 'empresas': choca con la variable del navbar
            'entidades'       => MigracionMysqlService::ENTIDADES,
        ]);
    }

    /** GET: prueba de conexión a la BD anterior. */
    public function probarAjax(): void
    {
        header('Content-Type: application/json');
        echo json_encode(LegacyMysqlConnection::probar(), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** POST: resumen de cuántos registros hay por entidad para la empresa. */
    public function analizarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            [, $ruc] = $this->resolverEmpresa();
            $entidades = $this->entidadesPost();
            $data = $this->service->analizar($ruc, $entidades);
            echo json_encode(['ok' => true, 'ruc' => $ruc, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** Devuelve [idEmpresa, ruc] de la empresa seleccionada. */
    private function resolverEmpresa(): array
    {
        $idEmpresa = (int) ($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            throw new \RuntimeException('Debe seleccionar una empresa.');
        }
        $db = Database::getConnection();
        $st = $db->prepare("SELECT ruc FROM empresas WHERE id = ? AND eliminado = false LIMIT 1");
        $st->execute([$idEmpresa]);
        $ruc = $st->fetchColumn();
        if ($ruc === false) {
            throw new \RuntimeException('Empresa no encontrada.');
        }
        return [$idEmpresa, (string) $ruc];
    }

    /** Entidades seleccionadas válidas; vacío = todas. */
    private function entidadesPost(): array
    {
        $e = $_POST['entidades'] ?? $_GET['entidades'] ?? [];
        if (!is_array($e)) {
            $e = array_filter(array_map('trim', explode(',', (string) $e)));
        }
        $validas = array_keys(MigracionMysqlService::ENTIDADES);
        return array_values(array_intersect($e, $validas));
    }
}
