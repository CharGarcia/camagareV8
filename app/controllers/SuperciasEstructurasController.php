<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\models\SuperciasEstructura;
use App\Services\UsuarioPreferenciaService;
use App\repositories\UsuarioPreferenciaRepository;

class SuperciasEstructurasController extends Controller
{
    private SuperciasEstructura $model;
    private UsuarioPreferenciaService $preferencias;

    public function __construct()
    {
        parent::__construct();
        $this->model = new SuperciasEstructura();
        $this->preferencias = new UsuarioPreferenciaService(new UsuarioPreferenciaRepository());
    }

    public function index(): void
    {
        $id_usuario = $_SESSION['id_usuario'] ?? 0;
        $id_empresa = $_SESSION['id_empresa'] ?? 0;
        
        $pref = $this->preferencias->obtenerPreferencias($id_usuario, $id_empresa, 'supercias_estructuras');
        $tabActivo = $_GET['tab'] ?? ($pref['tabActivo'] ?? 'ESF');

        $todos = $this->model->obtenerTodos();
        $datosGrid = ['ESF' => [], 'ERI' => [], 'ECP' => [], 'EFE' => []];
        foreach ($todos as $row) {
            $datosGrid[$row['tipo']][] = $row;
        }

        $this->viewWithLayout('layouts.main', 'config.supercias.index', [
            'tabActivo' => $tabActivo,
            'datosGrid' => $datosGrid
        ]);
    }

    public function listAjax(): void
    {
        header('Content-Type: application/json');
        try {
            $tipo = $_GET['tipo'] ?? null;
            $data = $this->model->obtenerTodos($tipo);
            echo json_encode(['data' => $data]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function updateAjax(): void
    {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Método no permitido');
            }

            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                throw new \Exception('ID inválido');
            }

            $datos = [
                'tipo' => $_POST['tipo'] ?? '',
                'codigo' => $_POST['codigo'] ?? '',
                'subcodigo' => $_POST['subcodigo'] ?? '',
                'nombre' => $_POST['nombre'] ?? '',
                'formula' => $_POST['formula'] ?? '',
                'codigo_anterior' => $_POST['codigo_anterior'] ?? ''
            ];

            if (empty($datos['tipo']) || empty($datos['codigo']) || empty($datos['nombre'])) {
                throw new \Exception('Tipo, código y descripción son obligatorios');
            }

            $usuarioId = $_SESSION['id_usuario'] ?? 0;

            $ok = $this->model->actualizar($id, $datos, $usuarioId);
            
            if ($ok) {
                echo json_encode(['ok' => true, 'mensaje' => 'Casillero actualizado correctamente']);
            } else {
                throw new \Exception('Error al actualizar');
            }
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }
    public function storeAjax(): void
    {
        header('Content-Type: application/json');
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Método no permitido');
            }

            $datos = [
                'tipo' => $_POST['tipo'] ?? '',
                'codigo' => $_POST['codigo'] ?? '',
                'subcodigo' => $_POST['subcodigo'] ?? '',
                'nombre' => $_POST['nombre'] ?? '',
                'formula' => $_POST['formula'] ?? '',
                'codigo_anterior' => $_POST['codigo_anterior'] ?? ''
            ];
            
            if (empty($datos['tipo']) || empty($datos['codigo']) || empty($datos['nombre'])) {
                throw new \Exception('Tipo, código y descripción son obligatorios');
            }

            $usuarioId = $_SESSION['id_usuario'] ?? 0;

            $ok = $this->model->crear($datos, $usuarioId);
            
            if ($ok) {
                echo json_encode(['ok' => true, 'mensaje' => 'Casillero creado correctamente']);
            } else {
                throw new \Exception('Error al crear casillero');
            }
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function exportarTxt(): void
    {
        $id_empresa = $_SESSION['id_empresa'] ?? 0;
        $anio = $_GET['anio'] ?? date('Y');
        $tipo = $_GET['tipo'] ?? 'ESF';

        if (!in_array($tipo, ['ESF', 'ERI', 'ECP', 'EFE'])) {
            die('Tipo inválido');
        }

        $service = new \App\Services\SuperciasEvaluatorService($this->db);
        $resultados = $service->evaluar((int)$id_empresa, (int)$anio);
        
        $datosTipo = $resultados[$tipo] ?? [];

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="SUPERCIAS_' . $tipo . '_' . $anio . '.txt"');

        $out = fopen('php://output', 'w');
        foreach ($datosTipo as $key => $casillero) {
            $valor = $casillero['valor'];
            
            if (abs($valor) >= 0.01) {
                if ($tipo === 'ECP') {
                    $partes = explode('.', (string)$key);
                    $codigo = $partes[0];
                    $subcodigo = $partes[1] ?? '';
                    if ($subcodigo !== '') {
                        fwrite($out, $codigo . "\t" . $subcodigo . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                    } else {
                        fwrite($out, $codigo . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                    }
                } else {
                    fwrite($out, $key . "\t" . number_format((float)$valor, 2, '.', '') . "\r\n");
                }
            }
        }
        fclose($out);
        exit;
    }
}
