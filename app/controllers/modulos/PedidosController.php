<?php

namespace App\Controllers\Modulos;

use App\Services\Modulos\PedidoService;
use App\Repositories\Modulos\PedidoRepository;
use App\Repositories\Modulos\ResponsableTrasladoRepository;
use App\models\Empresa;
use Exception;

class PedidosController extends BaseModuloController {
    private $service;
    private $repository;

    public function __construct() {
        parent::__construct();
        $this->repository = new PedidoRepository();
        $this->service = new PedidoService();
    }

    protected function getRutaModulo(): string {
        return 'modulos/pedidos';
    }

    public function index() {
        try {
            $db = \App\core\Database::getConnection();
            $db->exec("ALTER TABLE responsables_traslado ADD COLUMN IF NOT EXISTS email VARCHAR(150)");
            $db->exec("ALTER TABLE pedidos_detalle DROP COLUMN IF EXISTS id_empresa");
        } catch (\Throwable $e) {}

        $this->requireLeer();

        $perm = $this->getPermisos();
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        
        $buscar   = trim($_GET['b'] ?? $_POST['b'] ?? $_GET['buscar'] ?? $_POST['buscar'] ?? '');
        $page     = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'numero_pedido');
        $ordenDir = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage  = 20;

        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $empresaModel = new Empresa();
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        $puntos = [];
        $empresaData = $this->repository->getEmpresaConfig($idEmpresa);

        if (!empty($establecimientos)) {
            $puntos = $empresaModel->getPuntosEmision((int) $establecimientos[0]['id']);
            try {
                $estRepo   = new \App\repositories\modulos\EmpresaRepository();
                $estConfig = $estRepo->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData ?? [], $estConfig);
                }
            } catch (\Throwable $e) {}
        }

        $responsableRepo = new ResponsableTrasladoRepository();
        $responsables = $responsableRepo->listarPorEmpresa($idEmpresa);

        $this->viewWithLayout('layouts.main', 'modulos/pedidos/index', [
            'titulo' => 'Pedidos de Ventas',
            'perm' => $perm,
            'rutaModulo' => $this->getRutaModulo(),
            'puntos' => $puntos,
            'responsables' => $responsables,
            'empresa' => $empresaData,
            'tarifasIva' => $this->repository->getTarifasIva(),
            'unidades' => $this->repository->getUnidadesMedida(),
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'buscar' => $buscar,
            'ordenCol' => $ordenCol,
            'ordenDir' => $ordenDir,
            'vistaConfig' => $prefsVista,
            'fullWidth' => true
        ]);
    }

    public function searchAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idEmpresa = (int) $_SESSION['id_empresa'];
        $prefsVista = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $buscar    = trim($_GET['b'] ?? $_GET['q'] ?? $_POST['b'] ?? $_POST['q'] ?? '');
        $page      = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
        $ordenCol  = trim($_GET['sort'] ?? $_POST['sort'] ?? $prefsVista['__ordenCol__'] ?? 'numero_pedido');
        $ordenDir  = strtoupper(trim($_GET['dir'] ?? $_POST['dir'] ?? $prefsVista['__ordenDir__'] ?? 'asc'));
        $perPage   = 20;

        $perm = $this->getPermisos();
        $idUsuarioFiltro = empty($perm['todo']) ? (int)$_SESSION['id_usuario'] : null;

        $result = $this->service->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
        $rows = $result['rows'];
        $total = $result['total'];
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to   = $total > 0 ? min($page * $perPage, $total) : 0;

        ob_start();
        if (empty($rows)) {
            echo '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-cart-x fs-3 d-block mb-2"></i>No se encontraron pedidos.</td></tr>';
        } else {
            foreach ($rows as $r) {
                if (!empty($r['fecha_pedido'])) {
                    $r['fecha_pedido'] = date('d-m-Y', strtotime($r['fecha_pedido']));
                } else {
                    $r['fecha_pedido'] = '';
                }
                
                $estadoVal = $r['estado'] ?? 'Pendiente';
                $badgeColor = match(strtoupper($estadoVal)) {
                    'PENDIENTE' => 'warning',
                    'FACTURADO', 'PROCESADO' => 'success',
                    'ANULADO'   => 'danger',
                    default     => 'secondary',
                };
                
                $fechaEntrega = !empty($r['fecha_entrega']) ? date('d-m-Y', strtotime($r['fecha_entrega'])) : '';
                $rangoHorario = '';
                if (!empty($r['hora_inicial_entrega']) || !empty($r['hora_maxima_entrega'])) {
                    $ini = !empty($r['hora_inicial_entrega']) ? date('H:i', strtotime($r['hora_inicial_entrega'])) : '--:--';
                    $max = !empty($r['hora_maxima_entrega']) ? date('H:i', strtotime($r['hora_maxima_entrega'])) : '--:--';
                    $rangoHorario = "$ini - $max";
                }

                echo '<tr class="pedido-row" role="button" tabindex="0" onclick="editarPedido(' . $r['id'] . ')">
                        <td class="ps-3" data-col="numero_pedido"><code class="text-secondary">' . htmlspecialchars($r['numero_pedido'] ?? '') . '</code></td>
                        <td data-col="fecha_pedido">' . htmlspecialchars($r['fecha_pedido']) . '</td>
                        <td data-col="fecha_entrega">' . htmlspecialchars($fechaEntrega) . '</td>
                        <td data-col="rango_horario">' . htmlspecialchars($rangoHorario) . '</td>
                        <td class="fw-medium text-truncate" data-col="cliente_nombre" style="max-width:250px">' . htmlspecialchars($r['cliente_nombre'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="responsable_entrega">' . htmlspecialchars($r['responsable_entrega'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="observaciones">' . htmlspecialchars($r['observaciones'] ?? '') . '</td>
                        <td class="text-truncate" style="max-width:200px" data-col="observaciones_internas">' . htmlspecialchars($r['observaciones_internas'] ?? '') . '</td>
                        <td class="text-center" data-col="estado">
                            <span class="badge bg-' . $badgeColor . ' bg-opacity-10 text-' . $badgeColor . ' border border-' . $badgeColor . ' border-opacity-25">
                                ' . htmlspecialchars($estadoVal) . '
                            </span>
                        </td>
                      </tr>';
            }
        }
        $rowsHtml = ob_get_clean();

        ob_start();
        $prevDisabled = ($page <= 1) ? 'disabled' : '';
        $nextDisabled = ($page >= $totalPages) ? 'disabled' : '';
        echo '<div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary border-end-0 rounded-end-0" ' . $prevDisabled . ' onclick="PED_cambiarPaginaAjax(' . ($page - 1) . ')"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="btn btn-outline-secondary rounded-start-0" ' . $nextDisabled . ' onclick="PED_cambiarPaginaAjax(' . ($page + 1) . ')"><i class="bi bi-chevron-right"></i></button>
              </div>';
        $paginationHtml = ob_get_clean();

        echo json_encode([
            'ok'        => true,
            'rows'      => $rowsHtml,
            'pagination' => $paginationHtml,
            'info'      => "$from-$to/$total",
            'total'     => $total,
            'pdf_url'   => BASE_URL . '/' . $this->getRutaModulo() . '/export-pdf?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir",
            'excel_url' => BASE_URL . '/' . $this->getRutaModulo() . '/export-excel?b=' . urlencode($buscar) . "&sort=$ordenCol&dir=$ordenDir"
        ]);
        exit;
    }

    public function listarAjax() {
        $this->requireLeer();
        try {
            $buscar = $_POST['buscar'] ?? '';
            $filtros = ['buscar' => $buscar];
            $pedidos = $this->repository->listar($_SESSION['id_empresa'], $filtros);

            $this->json([
                'status' => true,
                'data' => $pedidos
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function obtenerPedidoAjax() {
        $this->requireLeer();
        try {
            $id = $_POST['id'];
            $pedido = $this->repository->obtenerPorId($id, $_SESSION['id_empresa']);
            $detalles = $this->repository->obtenerDetalles($id, $_SESSION['id_empresa']);

            $this->json([
                'status' => true,
                'data' => [
                    'cabecera' => $pedido,
                    'detalles' => $detalles
                ]
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function guardarAjax() {
        $this->requireCrear(); // O requireActualizar dependiendo de si es nuevo
        try {
            $datos = $_POST['cabecera'];
            $detalles = $_POST['detalles'] ?? [];
            
            // Validaciones de Fecha y Horas de Entrega
            $fecha_entrega = $datos['fecha_entrega'] ?? '';
            $hora_inicial = $datos['hora_inicial_entrega'] ?? '';
            $hora_maxima = $datos['hora_maxima_entrega'] ?? '';

            if (!empty($fecha_entrega)) {
                $today = date('Y-m-d');
                if ($fecha_entrega < $today) {
                    throw new Exception('La fecha de entrega no puede ser menor a la fecha actual.');
                }
            }

            if (!empty($hora_inicial) && !empty($hora_maxima)) {
                if ($hora_inicial > $hora_maxima) {
                    throw new Exception('La hora inicial no puede ser mayor a la hora máxima de entrega.');
                }
                if ($hora_inicial === $hora_maxima) {
                    throw new Exception('La hora máxima no puede ser igual a la hora inicial.');
                }
            }

            $res = $this->service->guardarPedido($datos, $detalles, $_SESSION['id_empresa'], $_SESSION['id_usuario']);

            $this->json([
                'status' => true,
                'message' => 'Pedido guardado con éxito',
                'id' => $res
            ]);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function eliminarAjax() {
        $this->requireEliminar();
        try {
            $id = $_POST['id'];
            $this->service->eliminarPedido($id, $_SESSION['id_empresa'], $_SESSION['id_usuario']);
            $this->json(['status' => true, 'message' => 'Pedido eliminado con éxito']);
        } catch (Exception $e) {
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getSecuencialAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');

        $idPunto = (int) ($_GET['id_punto_emision'] ?? 0);
        $tipoDoc = 'Pedidos';

        $secuencialService = new \App\Services\SecuencialService();
        $res = $secuencialService->obtenerSiguienteSecuencial($idPunto, $tipoDoc);

        echo json_encode(array_merge(['status' => true], $res));
        exit;
    }

    public function buscarProductosAjax() {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $buscar = trim($_GET['term'] ?? $_GET['q'] ?? '');

            $db = \App\core\Database::getConnection();
            $sql = "SELECT p.id, p.codigo, p.nombre
                    FROM productos p
                    WHERE p.id_empresa = :id_empresa 
                      AND p.eliminado = false 
                      AND p.status = 1
                      AND (p.codigo ILIKE :q OR p.nombre ILIKE :q)
                    ORDER BY p.nombre ASC 
                    LIMIT 15";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':q' => '%' . $buscar . '%'
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode(['ok' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'data' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function buscarClientesAjax() {
        $this->requireLeer();
        try {
            $term = $_GET['term'] ?? '';
            $db = \App\core\Database::getConnection();
            $sql = "SELECT id, identificacion, nombre 
                    FROM clientes 
                    WHERE (nombre ILIKE :term OR identificacion ILIKE :term) 
                    AND id_empresa = :id_empresa 
                    AND status = '1' 
                    LIMIT 10";
            $stmt = $db->prepare($sql);
            $stmt->execute(['term' => "%$term%", 'id_empresa' => $_SESSION['id_empresa']]);
            echo json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
    }

    public function guardarResponsableAjax() {
        $this->requireCrear();
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            $nombre = trim($_POST['nombre'] ?? '');
            $identificacion = trim($_POST['identificacion'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($nombre)) {
                throw new Exception('El nombre es obligatorio');
            }

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El formato del correo electrónico no es válido');
            }

            $db = \App\core\Database::getConnection();
            $db->beginTransaction();

            $sql = "INSERT INTO responsables_traslado (id_empresa, nombre, identificacion, telefono, email, estado, created_by, updated_by, created_at, updated_at, eliminado)
                    VALUES (:id_empresa, :nombre, :identificacion, :telefono, :email, 'activo', :id_usuario, :id_usuario, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, false)
                    RETURNING id, nombre, email";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':nombre' => $nombre,
                ':identificacion' => $identificacion,
                ':telefono' => $telefono,
                ':email' => $email,
                ':id_usuario' => $idUsuario
            ]);

            $newRow = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Audit log
            try {
                $sqlLog = "INSERT INTO log_sistema (id_usuario, id_empresa, accion, tabla_afectada, datos_nuevos)
                           VALUES (:id_usuario, :id_empresa, 'CREAR', 'responsables_traslado', :datos_nuevos)";
                $stmtLog = $db->prepare($sqlLog);
                $stmtLog->execute([
                    ':id_usuario' => $idUsuario,
                    ':id_empresa' => $idEmpresa,
                    ':datos_nuevos' => json_encode($newRow)
                ]);
            } catch (\Throwable $e) {}

            $db->commit();

            $this->json([
                'status' => true,
                'message' => 'Responsable creado con éxito',
                'data' => $newRow
            ]);
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->json(['status' => false, 'message' => $e->getMessage()]);
        }
    }

    public function countPendientesAjax(): void
    {
        $this->requireLeer();
        header('Content-Type: application/json');
        try {
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $db  = \App\core\Database::getConnection();
            $sql = "SELECT COUNT(*) FROM pedidos_cabecera
                    WHERE id_empresa = :id_empresa AND estado = 'Pendiente' AND eliminado = false";
            $st  = $db->prepare($sql);
            $st->execute([':id_empresa' => $idEmpresa]);
            $count = (int) $st->fetchColumn();
            echo json_encode(['ok' => true, 'count' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'count' => 0]);
        }
        exit;
    }
}
