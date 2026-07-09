<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\core\Database;
use App\Services\ImportarAntiguo\ImportarAntiguoService;
use App\Services\ImportarAntiguo\FtpDocumentosScanner;
use PDO;
use Throwable;

/**
 * Hub "Importar desde antiguo CaMaGaRe" (tarjeta de /config, solo superadmin).
 * Primera herramienta: importación de comprobantes XML desde el servidor FTP legacy.
 */
class ImportarAntiguoController extends Controller
{
    private ImportarAntiguoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        if ((int) ($_SESSION['nivel'] ?? 0) < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el superadministrador puede acceder al importador.'];
            $this->redirect(BASE_URL . '/config');
        }
        $this->service = new ImportarAntiguoService();
    }

    /** Etiquetas de los tipos de documento emitidos (codDoc => label). */
    private function tiposDisponibles(): array
    {
        return [
            '01' => 'Facturas',
            '04' => 'Notas de crédito',
            '05' => 'Notas de débito',
            '07' => 'Retenciones',
            '03' => 'Liquidaciones de compra',
            '06' => 'Guías de remisión',
        ];
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

        $this->viewWithLayout('layouts.main', 'config.importar_antiguo', [
            'titulo'          => 'Importar desde antiguo CaMaGaRe',
            'empresasImport'  => $empresas, // NO usar 'empresas': choca con la variable del navbar
            'tipos'           => $this->tiposDisponibles(),
        ]);
    }

    /** POST: escanea la carpeta del RUC (rápido) y arma el manifiesto. */
    public function escanearAjax(): void
    {
        header('Content-Type: application/json');
        try {
            [$idEmpresa, $ruc, $est] = $this->resolverEmpresa();
            $codDocs = $this->codDocsPost();
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

            $r = $this->service->escanear($idEmpresa, $ruc, $est, $codDocs, $idUsuario);
            echo json_encode(['ok' => true, 'data' => $r], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** POST: importa un bloque de pendientes (el frontend llama en bucle hasta restantes=0). */
    public function importarAjax(): void
    {
        header('Content-Type: application/json');
        try {
            [$idEmpresa] = $this->resolverEmpresa();
            $codDocs = $this->codDocsPost();
            $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
            $limite = max(1, min(100, (int) ($_POST['limite'] ?? 25)));
            $desde = !empty($_POST['desde']) ? (string) $_POST['desde'] : null;
            $hasta = !empty($_POST['hasta']) ? (string) $_POST['hasta'] : null;
            $verificar = !empty($_POST['verificar']);

            // Con verificación SRI conviene un bloque más pequeño (una llamada web por documento).
            if ($verificar) {
                $limite = min($limite, 15);
            }

            $r = $this->service->importarBloque($idEmpresa, $idUsuario, $limite, $codDocs, $desde, $hasta, $verificar);
            echo json_encode(['ok' => true, 'data' => $r], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** GET: historial de lotes de una empresa. */
    public function lotesAjax(): void
    {
        header('Content-Type: application/json');
        try {
            [$idEmpresa] = $this->resolverEmpresa();
            $repo = new \App\repositories\modulos\ImportacionXmlRepository();
            echo json_encode(['ok' => true, 'data' => $repo->listarLotes($idEmpresa)], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    /** Resuelve y valida la empresa seleccionada; devuelve [idEmpresa, ruc, establecimiento]. */
    private function resolverEmpresa(): array
    {
        $idEmpresa = (int) ($_POST['id_empresa'] ?? $_GET['id_empresa'] ?? 0);
        if ($idEmpresa <= 0) {
            throw new \RuntimeException('Debe seleccionar una empresa.');
        }
        $db = Database::getConnection();
        $st = $db->prepare("SELECT ruc, establecimiento FROM empresas WHERE id = ? AND eliminado = false LIMIT 1");
        $st->execute([$idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('Empresa no encontrada.');
        }
        $est = str_pad((string) ($row['establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
        return [$idEmpresa, (string) $row['ruc'], $est];
    }

    /** Lee y valida los tipos (codDoc) seleccionados; vacío = todos los emitidos. */
    private function codDocsPost(): array
    {
        $tipos = $_POST['tipos'] ?? $_GET['tipos'] ?? [];
        if (!is_array($tipos)) {
            $tipos = array_filter(array_map('trim', explode(',', (string) $tipos)));
        }
        // CARPETAS_EMITIDOS = [carpeta => codDoc]; los códigos válidos son los VALORES.
        $validos = array_values(FtpDocumentosScanner::CARPETAS_EMITIDOS);
        return array_values(array_intersect($tipos, $validos));
    }
}
