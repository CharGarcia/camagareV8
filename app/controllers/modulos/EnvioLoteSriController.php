<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\repositories\modulos\EnvioLoteSriRepository;
use App\Services\modulos\EnvioLoteSriService;

/**
 * Envío en lote de comprobantes electrónicos al SRI.
 *
 * Permite filtrar comprobantes pendientes (facturas, notas de crédito,
 * retenciones y liquidaciones de compra) por ambiente, rango de fechas y tipo,
 * seleccionarlos y enviarlos al SRI en segundo plano (cola + worker CLI).
 */
class EnvioLoteSriController extends BaseModuloController
{
    private EnvioLoteSriRepository $repo;
    private EnvioLoteSriService $service;

    protected function getRutaModulo(): string
    {
        return 'modulos/envio-lote-sri';
    }

    public function __construct()
    {
        parent::__construct();
        $this->repo    = new EnvioLoteSriRepository();
        $this->service = new EnvioLoteSriService($this->repo);
    }

    // ── Vista principal ───────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireLeer();
        $idEmpresa = (int) $_SESSION['id_empresa'];

        $this->viewWithLayout('layouts.main', 'modulos/envio_lote_sri/index', [
            'titulo'          => 'Envío en lote al SRI',
            'perm'            => $this->getPermisos(),
            'ambienteEmpresa' => $this->getAmbienteEmpresa($idEmpresa),
            'base'            => BASE_URL,
            'rutaModulo'      => $this->getRutaModulo(),
        ]);
    }

    // ── Listado de comprobantes enviables ─────────────────────────────────────

    public function buscarAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];

        $tipos   = $_GET['tipos'] ?? $_POST['tipos'] ?? [];
        if (is_string($tipos)) {
            $tipos = array_filter(array_map('trim', explode(',', $tipos)));
        }
        // El ambiente se determina SIEMPRE por la configuración de la empresa.
        $ambiente = $this->getAmbienteEmpresa($idEmpresa);

        $hoy    = (new \DateTime())->format('Y-m-d');
        $desde  = $this->fechaValida($_GET['desde'] ?? $_POST['desde'] ?? '') ?? $hoy;
        $hasta  = $this->fechaValida($_GET['hasta'] ?? $_POST['hasta'] ?? '') ?? $hoy;
        $buscar = trim((string) ($_GET['b'] ?? $_POST['b'] ?? ''));

        $perm            = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int) $_SESSION['id_usuario'] : null;

        $rows = $this->repo->getComprobantesEnviables(
            $idEmpresa, $tipos, $ambiente, $desde, $hasta, $buscar, $idUsuarioFiltro
        );

        echo json_encode(['ok' => true, 'data' => $rows, 'total' => count($rows)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Crear lote y lanzar el worker ─────────────────────────────────────────

    public function crearLoteAjax(): void
    {
        $this->requireCrear();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idUsuario = (int) $_SESSION['id_usuario'];

        $itemsRaw = $_POST['items'] ?? '';
        $items    = is_string($itemsRaw) ? json_decode($itemsRaw, true) : $itemsRaw;
        if (!is_array($items) || empty($items)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se seleccionaron comprobantes.']);
            exit;
        }

        // El ambiente se determina SIEMPRE por la configuración de la empresa.
        $ambiente = $this->getAmbienteEmpresa($idEmpresa);

        $filtros = [
            'ambiente' => $ambiente,
            'desde'    => $this->fechaValida($_POST['desde'] ?? ''),
            'hasta'    => $this->fechaValida($_POST['hasta'] ?? ''),
            'tipos'    => $_POST['tipos'] ?? '',
        ];

        try {
            $idLote = $this->service->crearLote($idEmpresa, $idUsuario, $ambiente, $items, $filtros);

            $lanzado = $this->lanzarWorker($idLote);

            echo json_encode([
                'ok'      => true,
                'id_lote' => $idLote,
                'lanzado' => $lanzado,
                'mensaje' => $lanzado
                    ? 'Lote creado. Procesando en segundo plano…'
                    : 'Lote creado, pero no se pudo iniciar el proceso automáticamente. '
                      . 'Ejecute el worker manualmente: php scripts/procesar_lote_sri.php --lote=' . $idLote,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    // ── Progreso / historial ──────────────────────────────────────────────────

    public function estadoLoteAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idLote    = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        $estado = $this->service->getEstado($idLote, $idEmpresa);
        if (!$estado) {
            echo json_encode(['ok' => false, 'mensaje' => 'Lote no encontrado.']);
            exit;
        }
        echo json_encode(['ok' => true, 'data' => $estado], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function historialLotesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $rows = $this->repo->getHistorialLotes($idEmpresa);

        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function cancelarLoteAjax(): void
    {
        $this->requireActualizar();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $idLote    = (int) ($_POST['id'] ?? 0);

        $lote = $this->repo->getLote($idLote, $idEmpresa);
        if (!$lote) {
            echo json_encode(['ok' => false, 'mensaje' => 'Lote no encontrado.']);
            exit;
        }
        if (in_array($lote['estado'], ['completado', 'completado_con_errores', 'cancelado'], true)) {
            echo json_encode(['ok' => false, 'mensaje' => 'El lote ya finalizó.']);
            exit;
        }

        // El worker respeta el flag 'cancelado' entre ítems y se detiene.
        $this->repo->marcarLoteEstado($idLote, 'cancelado');
        echo json_encode(['ok' => true, 'mensaje' => 'Se solicitó la cancelación del lote.']);
        exit;
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /** Ambiente activo de la empresa ('1' pruebas | '2' producción). */
    private function getAmbienteEmpresa(int $idEmpresa): string
    {
        try {
            $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR(1)) FROM empresas WHERE id = ?");
            $st->execute([$idEmpresa]);
            $amb = (string) $st->fetchColumn();
            return in_array($amb, ['1', '2'], true) ? $amb : '1';
        } catch (\Throwable) {
            return '1';
        }
    }

    private function fechaValida(string $fecha): ?string
    {
        $fecha = trim($fecha);
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        return ($d && $d->format('Y-m-d') === $fecha) ? $fecha : null;
    }

    /**
     * Lanza el worker CLI desligado del request (no bloquea).
     * Windows: start /B ; Linux/Unix: nohup ... &
     */
    private function lanzarWorker(int $idLote): bool
    {
        $phpBin = $this->resolverPhpBin();
        $script = MVC_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'procesar_lote_sri.php';

        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                $cmd = 'start /B "" ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' --lote=' . $idLote;
                $handle = popen($cmd, 'r');
                if ($handle === false) { return false; }
                pclose($handle);
                return true;
            }

            // Linux / Unix (producción)
            $cmd = 'nohup ' . escapeshellarg($phpBin) . ' ' . escapeshellarg($script)
                 . ' --lote=' . $idLote . ' > /dev/null 2>&1 &';
            @exec($cmd);
            return true;
        } catch (\Throwable $e) {
            error_log('[EnvioLoteSri] No se pudo lanzar el worker del lote ' . $idLote . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Resuelve el binario de PHP CLI para lanzar el worker. */
    private function resolverPhpBin(): string
    {
        $cfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        $bin = trim((string) ($cfg['sri_lote_php_bin'] ?? ''));
        if ($bin !== '') {
            return $bin;
        }
        // XAMPP en Windows: php.exe junto a la instalación
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            foreach (['C:\\xampp\\php\\php.exe'] as $cand) {
                if (is_file($cand)) { return $cand; }
            }
        }
        return 'php';
    }
}
