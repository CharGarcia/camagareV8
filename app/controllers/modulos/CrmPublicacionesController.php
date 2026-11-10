<?php

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Services\modulos\CrmMedioService;
use App\Traits\CrmControllerTrait;

/**
 * Publicaciones en redes (modulos/crm_publicaciones), fase 3 del CRM.
 * Diseño: docs/diseno/crm-redes-fase3.md.
 *
 * Paso 2: biblioteca de medios. La pantalla (calendario, lista, modal) llega en
 * el paso 4. Registrar la ruta en config/modulos_mvc.php y el submódulo con sus
 * permisos lo hace el administrador.
 */
class CrmPublicacionesController extends BaseModuloController
{
    use CrmControllerTrait;

    private CrmMedioService $medios;

    protected function getRutaModulo(): string
    {
        return 'modulos/crm_publicaciones';
    }

    public function __construct()
    {
        parent::__construct();
        $this->medios = CrmMedioService::crear();
    }

    /** El SQL de la fase 3 está aplicado; si no, 503 con un mensaje claro. */
    private function requireFase3(): void
    {
        $this->requireCrmDisponible();
        if (!$this->medios->disponible()) {
            $this->json(['ok' => false, 'error' => 'Las publicaciones todavía no están instaladas en la base de datos (SQL de la fase 3).'], 503);
        }
    }

    // ── Pantalla ─────────────────────────────────────────────────────────────

    /** Columnas de la Lista (data-col / data-sort; claves de MAPA_ORDEN salvo destinos). */
    public const COLUMNAS_LISTA = [
        'titulo' => 'Publicación', 'destinos' => 'Dónde', 'estado' => 'Estado', 'programada_at' => 'Programada',
        'publicada_at' => 'Publicada', 'responsable' => 'Responsable', 'created_at' => 'Creada',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador', 'en_revision' => 'En revisión', 'aprobada' => 'Aprobada', 'programada' => 'Programada',
        'publicando' => 'Publicando', 'publicada' => 'Publicada', 'parcial' => 'Publicada en parte', 'con_error' => 'Con error',
        'cancelada' => 'Cancelada',
    ];

    public function index(): void
    {
        $this->requireLeer();
        $prefs = \App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo());
        $orden = \App\Helpers\OrdenListado::leer($prefs, 'created_at', 'DESC');
        $perPage = $this->porPagina();
        $disponible = \App\Services\modulos\CrmConfiguracionService::crear()->disponible() && $this->medios->disponible();
        $this->viewWithLayout('layouts.main', 'modulos/crm_publicaciones/index', [
            'titulo'        => 'Publicaciones',
            'fullWidth'     => true,
            'perm'          => $this->getPermisos(),
            'rutaModulo'    => $this->getRutaModulo(),
            'vistaConfig'   => $prefs,
            'ordenJson'     => \App\Helpers\OrdenListado::aJson($orden),
            'perPage'       => $perPage,
            'disponible'    => $disponible,
            'columnasLista' => self::COLUMNAS_LISTA,
            'estados'       => self::ESTADOS,
            'idUsuario'     => $this->idUsuario(),
        ]);
    }

    public function resumenAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        $this->json(['ok' => true, 'resumen' => $this->pubs()->resumen($this->idEmpresa(), $this->idUsuarioFiltro())]);
    }

    /** Filas para exportar: los filtros y el orden de la pantalla, sin paginar (tope 5000). */
    private function filasExportacion(): array
    {
        $orden = \App\Helpers\OrdenListado::leer(\App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()), 'created_at', 'DESC');
        return $this->pubs()->listar($this->idEmpresa(), ['estado' => (string) ($_GET['estado'] ?? ''), 'b' => (string) ($_GET['b'] ?? '')],
            1, 5000, $orden, $this->idUsuarioFiltro())['rows'];
    }

    private static function fecha(?string $f): string
    {
        return $f ? date('d-m-Y H:i:s', strtotime($f)) : '-';
    }

    public function exportExcel(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        try {
            $datos = array_map(fn($r) => [
                (string) $r['titulo'], (string) ($r['destinos'] ?? ''), self::ESTADOS[$r['estado']] ?? $r['estado'],
                self::fecha($r['programada_at']), self::fecha($r['publicada_at']), (string) ($r['responsable'] ?? '-'), self::fecha($r['created_at']),
            ], $this->filasExportacion());
            $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
            (new \App\Services\ReportService())->exportToExcel('CRM_Publicaciones', array_values(self::COLUMNAS_LISTA), $datos, 'Publicaciones', $empresa['nombre'] ?? null);
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'No se pudo generar el Excel.';
            exit;
        }
    }

    public function exportPdf(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        try {
            $rows = $this->filasExportacion();
            $empresa = (new \App\models\Empresa())->getPorId($this->idEmpresa());
            $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
            // Anchos en % en cada td (Html2Pdf no ajusta el texto si solo van en el th).
            $cols = [['Publicación', 24], ['Dónde', 24], ['Estado', 11], ['Programada', 11], ['Publicada', 11], ['Responsable', 10], ['Creada', 9]];
            $html = '<style>table{border-collapse:collapse;font-family:helvetica;font-size:7.5pt;width:100%}'
                  . 'th{background:#f2f2f2;border:0.5px solid #bbb;padding:3px;text-align:left}td{border:0.5px solid #ccc;padding:3px}'
                  . 'h1{font-size:13pt;margin:0}h2{font-size:9pt;color:#666;margin:2px 0 8px 0}</style>'
                  . '<page backtop="8mm" backbottom="7mm" backleft="3mm" backright="3mm" footer="page">'
                  . '<h1>' . $h($empresa['nombre'] ?? '') . '</h1><h2>CRM · Publicaciones · ' . date('d-m-Y H:i:s') . '</h2><table><thead><tr>';
            foreach ($cols as [$t, $w]) {
                $html .= '<th style="width:' . $w . '%">' . $h($t) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $r) {
                $celdas = [$r['titulo'], $r['destinos'] ?? '', self::ESTADOS[$r['estado']] ?? $r['estado'], self::fecha($r['programada_at']),
                           self::fecha($r['publicada_at']), $r['responsable'] ?? '-', self::fecha($r['created_at'])];
                $html .= '<tr>';
                foreach ($celdas as $i => $v) {
                    $html .= '<td style="width:' . $cols[$i][1] . '%">' . $h($v) . '</td>';
                }
                $html .= '</tr>';
            }
            if (!$rows) {
                $html .= '<tr><td colspan="7" style="width:100%">Sin publicaciones con los filtros elegidos.</td></tr>';
            }
            $html .= '</tbody></table><p style="font-size:8pt;font-weight:bold">' . count($rows) . ' publicación(es)</p></page>';
            $pdf = new \Spipu\Html2Pdf\Html2Pdf('L', 'A4', 'es');
            $pdf->writeHTML($html);
            $pdf->output('CRM_Publicaciones_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            header('Content-Type: text/html; charset=utf-8');
            echo 'No se pudo generar el PDF.';
            exit;
        }
    }

    // ── Publicaciones ────────────────────────────────────────────────────────

    private ?\App\Services\modulos\CrmPublicacionService $pubs = null;

    private function pubs(): \App\Services\modulos\CrmPublicacionService
    {
        return $this->pubs ??= \App\Services\modulos\CrmPublicacionService::crear();
    }

    /**
     * Publicación accesible para el usuario (registros propios, CLAUDE.md §6):
     * sin acceso total, solo las que creó o tiene a cargo. 404/403 si no.
     */
    private function publicacionAccesible(int $id): array
    {
        $p = $this->pubs()->obtener($id, $this->idEmpresa());
        if (!$p) {
            $this->json(['ok' => false, 'error' => 'Publicación no encontrada.'], 404);
        }
        $u = $this->idUsuarioFiltro();
        if ($u !== null && (int) $p['created_by'] !== $u && (int) $p['id_responsable'] !== $u) {
            $this->json(['ok' => false, 'error' => 'No tiene acceso a esta publicación.'], 403);
        }
        return $p;
    }

    /** Datos para armar la pantalla: cuentas, configuración y permisos. */
    public function datosAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        $this->json(['ok' => true, 'canales' => $this->pubs()->canales($this->idEmpresa()), 'config' => $this->pubs()->config($this->idEmpresa())]);
    }

    public function listarAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        try {
            $perPage = $this->porPagina();
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $orden = \App\Helpers\OrdenListado::leer(\App\Helpers\PreferenciasHelper::getPreferenciasVista($this->getRutaModulo()), 'created_at', 'DESC');
            $r = $this->pubs()->listar($this->idEmpresa(), ['estado' => (string) ($_GET['estado'] ?? ''), 'b' => (string) ($_GET['b'] ?? '')],
                $page, $perPage, $orden, $this->idUsuarioFiltro());
            $this->json(['ok' => true, 'rows' => $r['rows'], 'total' => $r['total'], 'page' => $page, 'perPage' => $perPage,
                         'totalPages' => (int) ceil($r['total'] / max(1, $perPage))]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function calendarioAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        try {
            $this->json(['ok' => true, 'rows' => $this->pubs()->calendario($this->idEmpresa(), (string) ($_GET['desde'] ?? ''),
                (string) ($_GET['hasta'] ?? ''), $this->idUsuarioFiltro())]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function publicacionAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        $p = $this->publicacionAccesible((int) ($_GET['id'] ?? 0));
        foreach ($p['destinos'] as &$d) {
            unset($d['canal_permisos']);
        }
        unset($d);
        try {
            // Métricas y lo que trajo al CRM (paso 6); por id de destino.
            $p['resultados'] = (object) \App\Services\modulos\CrmMetricaService::crear()->resultados($p['destinos']);
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $p['resultados'] = new \stdClass();
        }
        $this->json(['ok' => true, 'publicacion' => $p]);
    }

    /** Crear (id vacío, con token_guardado) o actualizar. medios[] y destinos (JSON). */
    public function guardarAjax(): void
    {
        $id = $this->postInt('id');
        $id > 0 ? $this->requireActualizar() : $this->requireCrear();
        $this->requireFase3();
        try {
            $d = [
                'titulo' => (string) ($_POST['titulo'] ?? ''), 'texto' => (string) ($_POST['texto'] ?? ''),
                'es_contenido_ia' => $_POST['es_contenido_ia'] ?? '0',
                'medios' => (array) ($_POST['medios'] ?? []),
                'destinos' => json_decode((string) ($_POST['destinos'] ?? '[]'), true) ?: [],
                'token_guardado' => (string) ($_POST['token_guardado'] ?? ''),
            ];
            if ($id > 0) {
                $this->publicacionAccesible($id);
                $this->pubs()->actualizar($id, $d, $this->idEmpresa(), $this->idUsuario());
                $this->json(['ok' => true, 'id' => $id, 'nuevo' => false, 'mensaje' => 'Publicación guardada.']);
            }
            $nuevo = $this->pubs()->crearPublicacion($d, $this->idEmpresa(), $this->idUsuario());
            $previo = $this->pubs()->ultimoGuardadoPrevio;
            $this->json(['ok' => true, 'id' => $nuevo, 'nuevo' => !$previo,
                         'mensaje' => $previo ? 'La publicación ya estaba registrada; no se creó otra.' : 'Publicación creada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Respuesta automática a comentarios de Instagram: palabra clave y mensaje (vacíos = quitarla). */
    public function guardarRespuestaAutoAjax(): void
    {
        $this->requireCrear();
        $this->requireFase3();
        $p = $this->publicacionAccesible($this->postInt('id'));
        try {
            $palabra = (string) ($_POST['palabra'] ?? '');
            $this->pubs()->guardarRespuestaAuto((int) $p['id'], $palabra, (string) ($_POST['mensaje'] ?? ''), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => trim($palabra) === '' ? 'Respuesta automática quitada.' : 'Respuesta automática guardada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** accion: revision | aprobar | devolver | programar | desprogramar | cancelar | reintentar */
    public function accionAjax(): void
    {
        $accion = (string) ($_POST['accion'] ?? '');
        in_array($accion, ['aprobar', 'devolver'], true) ? $this->requireActualizar() : $this->requireCrear();
        $this->requireFase3();
        $p = $this->publicacionAccesible($this->postInt('id'));
        $id = (int) $p['id'];
        $e = $this->idEmpresa();
        $u = $this->idUsuario();
        try {
            switch ($accion) {
                case 'revision':
                    $this->pubs()->enviarARevision($id, $e, $u);
                    $msg = 'Enviada a revisión.';
                    break;
                case 'aprobar':
                    $this->pubs()->aprobar($id, $e, $u);
                    $msg = 'Publicación aprobada.';
                    break;
                case 'devolver':
                    $this->pubs()->devolver($id, $e, $u);
                    $msg = 'Devuelta a borrador.';
                    break;
                case 'programar':
                    $cuando = $this->pubs()->programar($id, (string) ($_POST['fecha'] ?? ''), $e, $u);
                    $msg = strtotime($cuando) <= time() + 60
                        ? 'Programada: sale en el próximo minuto.'
                        : 'Programada para el ' . date('d-m-Y H:i:s', strtotime($cuando)) . '.';
                    break;
                case 'desprogramar':
                    $this->pubs()->desprogramar($id, $e, $u);
                    $msg = 'Ya no está programada.';
                    break;
                case 'cancelar':
                    $n = $this->pubs()->cancelarPendientes($id, $e, $u);
                    $msg = $n . ' destino(s) cancelado(s).';
                    break;
                case 'reintentar':
                    $this->pubs()->reintentarDestino($id, $this->postInt('id_destino'), $e, $u);
                    $msg = 'Se volverá a intentar en el próximo minuto.';
                    break;
                default:
                    throw new \InvalidArgumentException('Acción no válida.');
            }
            $this->json(['ok' => true, 'mensaje' => $msg]);
        } catch (\Throwable $ex) {
            $this->fallo($ex, __FUNCTION__);
        }
    }

    public function eliminarAjax(): void
    {
        $this->requireEliminar();
        $this->requireFase3();
        $p = $this->publicacionAccesible($this->postInt('id'));
        try {
            $n = $this->pubs()->eliminar((int) $p['id'], $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => $n > 0
                ? 'Publicación eliminada del sistema. Lo que ya salió sigue en la red: bórrelo allí si hace falta.'
                : 'Publicación eliminada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /**
     * Propuesta de texto con la IA de la empresa. No guarda nada: la pantalla
     * la muestra y la persona decide qué usar. medios[] y redes[].
     */
    public function proponerIaAjax(): void
    {
        $this->requireCrear();
        $this->requireFase3();
        $idEmpresa = $this->idEmpresa();
        $idUsuario = $this->idUsuario();
        $idPub = $this->postInt('id');
        if ($idPub > 0) {
            $this->publicacionAccesible($idPub);
        }
        session_write_close();   // la IA puede tardar: no retener la sesión
        try {
            $r = \App\Services\modulos\CrmRedaccionService::crear()->proponer([
                'indicaciones' => (string) ($_POST['indicaciones'] ?? ''), 'texto_actual' => (string) ($_POST['texto_actual'] ?? ''),
                'redes' => (array) ($_POST['redes'] ?? []), 'medios' => (array) ($_POST['medios'] ?? []), 'alt' => !empty($_POST['alt']),
                'id_publicacion' => $idPub ?: null,
            ], $idEmpresa, $idUsuario);
            $this->json(['ok' => true, 'propuesta' => $r]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function guardarConfigAjax(): void
    {
        $this->requireActualizar();
        $this->requireFase3();
        try {
            $this->pubs()->guardarConfig($this->idEmpresa(), !empty($_POST['requiere_aprobacion']), (int) ($_POST['max_publicaciones_dia'] ?? 25), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Configuración guardada.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    // ── Biblioteca de medios ─────────────────────────────────────────────────

    public function mediosAjax(): void
    {
        $this->requireLeer();
        $this->requireFase3();
        try {
            $perPage = 48;
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $r = $this->medios->listar($this->idEmpresa(), (string) ($_GET['tipo'] ?? ''), $page, $perPage);
            $this->json(['ok' => true, 'rows' => $r['rows'], 'total' => $r['total'], 'page' => $page,
                         'totalPages' => (int) ceil($r['total'] / $perPage)]);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Campo de archivo `archivo`; opcionales `texto_alternativo` y `es_ia`. */
    public function subirMedioAjax(): void
    {
        $this->requireCrear();
        $this->requireFase3();
        $f = $_FILES['archivo'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
            $this->json(['ok' => false, 'error' => in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo es demasiado grande.' : 'No se recibió el archivo.'], 400);
        }
        try {
            $r = $this->medios->subir((string) $f['tmp_name'], (string) ($f['name'] ?? ''), (int) $f['size'], $this->idEmpresa(), $this->idUsuario(),
                (string) ($_POST['texto_alternativo'] ?? ''), !empty($_POST['es_ia']));
            $this->json(['ok' => true, 'id' => $r['id'], 'tipo' => $r['tipo'], 'avisos' => $r['avisos'],
                         'mensaje' => $r['tipo'] === 'imagen' ? 'Imagen guardada.' : 'Video guardado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function guardarMedioAjax(): void
    {
        $this->requireActualizar();
        $this->requireFase3();
        try {
            $this->medios->actualizar($this->postInt('id'), (string) ($_POST['texto_alternativo'] ?? ''), !empty($_POST['es_ia']),
                $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Guardado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    public function eliminarMedioAjax(): void
    {
        $this->requireEliminar();
        $this->requireFase3();
        try {
            $this->medios->eliminar($this->postInt('id'), $this->idEmpresa(), $this->idUsuario());
            $this->json(['ok' => true, 'mensaje' => 'Archivo eliminado.']);
        } catch (\Throwable $e) {
            $this->fallo($e, __FUNCTION__);
        }
    }

    /** Archivo de la biblioteca para verlo en pantalla (con sesión y de la empresa activa). */
    public function medio(): void
    {
        $this->requireLeer();
        $m = $this->medios->disponible() ? $this->medios->getPorId((int) ($_GET['id'] ?? 0), $this->idEmpresa()) : null;
        $ruta = $m ? $this->medios->rutaAbsoluta($m) : null;
        if (!$ruta) {
            http_response_code(404);
            exit;
        }
        session_write_close();
        header('Content-Type: ' . $m['mime']);
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        readfile($ruta);
        exit;
    }
}
