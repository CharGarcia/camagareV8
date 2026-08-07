<?php
/**
 * Catálogo global de Formatos de Transferencia Bancaria (nivel 3).
 * Ver database/migrations/20260801_transferencia_formatos.sql y
 * app/Services/modulos/Transferencias/Formatters/TransferenciaFormatoConfigurable.php.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\TransferenciaFormatoService;
use App\models\BancoEcuador;

class TransferenciaFormatoController extends Controller
{
    private const BASE_PATH = '/config/transferencia-formatos';
    private TransferenciaFormatoService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new TransferenciaFormatoService();
    }

    private function requireNivel(int $min): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta sección.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireNivel(3);

        $buscar = trim($_GET['b'] ?? '');
        $rows = $this->service->listar($buscar);

        $this->viewWithLayout('layouts.main', 'transferenciaFormatos.index', [
            'titulo'      => 'Formatos de Transferencia Bancaria',
            'fullWidth'   => true,
            'rows'        => $rows,
            'rowsHtml'    => $this->renderFilasHtml($rows),
            'buscar'      => $buscar,
            'bancos'      => (new BancoEcuador())->getAll(),
            'origenDato'  => TransferenciaFormatoService::ORIGEN_DATO,
            'tiposArchivo'=> TransferenciaFormatoService::TIPOS_ARCHIVO,
        ]);
    }

    /**
     * AJAX: listado de formatos de transferencia (tabla), para búsqueda en
     * tiempo real sin recargar la página. Mismo patrón que
     * ConfigController::asientosTipoListAjax.
     */
    public function searchAjax(): void
    {
        $this->requireNivel(3);
        header('Content-Type: application/json');

        $buscar = trim($_GET['b'] ?? $_POST['b'] ?? '');
        $rows = $this->service->listar($buscar);

        echo json_encode([
            'ok' => true,
            'rows' => $this->renderFilasHtml($rows),
        ]);
        exit;
    }

    private const ETIQUETAS_TIPO_ARCHIVO = [
        'xlsx' => 'Excel (.xlsx)',
        'csv' => 'CSV',
        'txt_delimitado' => 'TXT delimitado',
        'txt_ancho_fijo' => 'TXT ancho fijo',
    ];

    /**
     * Renderiza el <tbody> completo (filas o mensaje de "sin resultados").
     * Usado tanto por la carga inicial (vista) como por searchAjax.
     */
    private function renderFilasHtml(array $rows): string
    {
        if (empty($rows)) {
            return '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox d-block mb-2" style="font-size:1.5rem;"></i>No hay formatos configurados.</td></tr>';
        }
        $base = BASE_URL;
        $html = '';
        foreach ($rows as $f) {
            $tipoArchivo = self::ETIQUETAS_TIPO_ARCHIVO[$f['tipo_archivo']] ?? $f['tipo_archivo'];
            $esActivo = $f['estado'] === 'activo';
            $html .= '<tr>';
            $html .= '<td class="ps-3">' . htmlspecialchars($f['nombre']);
            if (!empty($f['clase_formatter'])) {
                $html .= ' <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 ms-1" title="' . htmlspecialchars($f['clase_formatter']) . '">clase PHP</span>';
            }
            $html .= '</td>';
            $html .= '<td>' . htmlspecialchars($f['nombre_banco'] ?? 'Genérico') . '</td>';
            $html .= '<td><span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">' . htmlspecialchars($tipoArchivo) . '</span></td>';
            $html .= '<td class="text-center">' . count($f['campos'] ?? []) . '</td>';
            $html .= '<td class="text-center">' . ($esActivo ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>') . '</td>';
            $html .= '<td class="text-center pe-3">';
            $html .= '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 border-0" title="Editar" onclick=\'TF_abrirModalEditar(' . json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT) . ')\'><i class="bi bi-pencil"></i></button>';
            $html .= '<a href="' . $base . '/config/transferenciaFormatos' . ($esActivo ? 'Desactivar' : 'Activar') . '?id=' . (int) $f['id'] . '" class="btn btn-sm btn-outline-' . ($esActivo ? 'secondary' : 'success') . ' py-0 px-1 border-0" title="' . ($esActivo ? 'Desactivar' : 'Activar') . '"><i class="bi bi-' . ($esActivo ? 'pause-circle' : 'play-circle') . '"></i></a>';
            $html .= '<a href="' . $base . '/config/transferenciaFormatosDelete?id=' . (int) $f['id'] . '" class="btn btn-sm btn-outline-danger py-0 px-1 border-0" title="Eliminar" onclick="return confirm(&quot;¿Eliminar este formato?&quot;);"><i class="bi bi-trash"></i></a>';
            $html .= '</td></tr>';
        }
        return $html;
    }

    public function store(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        try {
            $this->service->crear($this->datosDelPost(), (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato creado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al crear: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function update(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            $this->service->actualizar($id, $this->datosDelPost(), (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato actualizado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al actualizar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function delete(): void
    {
        $this->requireNivel(3);
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $this->service->eliminar($id, (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato eliminado.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    public function activar(): void
    {
        $this->cambiarEstado('activo');
    }

    public function desactivar(): void
    {
        $this->cambiarEstado('inactivo');
    }

    private function cambiarEstado(string $estado): void
    {
        $this->requireNivel(3);
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $this->service->cambiarEstado($id, $estado, (int) ($_SESSION['id_usuario'] ?? 0));
            $_SESSION['config_msg'] = ['success', 'Formato ' . ($estado === 'activo' ? 'activado' : 'desactivado') . '.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    private function datosDelPost(): array
    {
        $campos = json_decode((string) ($_POST['campos_json'] ?? '[]'), true);
        return [
            'id_banco'           => (int) ($_POST['id_banco'] ?? 0) ?: null,
            'nombre'             => trim($_POST['nombre'] ?? ''),
            'descripcion'        => trim($_POST['descripcion'] ?? ''),
            'tipo_archivo'       => trim($_POST['tipo_archivo'] ?? ''),
            'delimitador'        => trim($_POST['delimitador'] ?? ''),
            'incluye_encabezado' => !empty($_POST['incluye_encabezado']),
            'nombre_hoja'        => trim($_POST['nombre_hoja'] ?? ''),
            'estado'             => trim($_POST['estado'] ?? 'activo'),
            'campos'             => is_array($campos) ? $campos : [],
        ];
    }
}
