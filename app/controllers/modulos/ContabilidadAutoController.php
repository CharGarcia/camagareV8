<?php
/**
 * Endpoint único de la generación automática de asientos contables.
 *
 * Lo llama la propia pantalla del módulo al terminar de cargar (ver
 * app/views/partials/contabilidad_auto.php). No devuelve nada útil a propósito:
 * el navegador descarta la respuesta y el usuario nunca se entera de que esto
 * ocurrió. Si no hay nada que generar, o falta configuración contable, o hay
 * otra sesión trabajando, la respuesta es igual de silenciosa.
 *
 * Es UN solo endpoint para los 15 módulos, en vez de una acción en cada
 * controlador: el módulo viaja en el parámetro `modulo` y se valida contra
 * config/contabilidad_modulos.php, así que solo se aceptan rutas declaradas
 * ahí. Los permisos se verifican sobre el MÓDULO DE ORIGEN, no sobre
 * contabilidad: quien puede ver Facturas de Venta puede disparar la generación
 * de sus asientos, sin necesitar acceso al módulo de Asientos Contables.
 */

declare(strict_types=1);

namespace App\controllers\modulos;

use App\Helpers\ContabilidadModulos;
use App\Services\ErrorLogService;
use App\Services\modulos\ContabilidadAutoService;

class ContabilidadAutoController extends BaseModuloController
{
    /** Ruta del módulo de origen, validada contra el mapa. */
    private string $rutaSolicitada = '';

    protected function getRutaModulo(): string
    {
        return $this->rutaSolicitada;
    }

    /**
     * No tiene pantalla propia: es un endpoint. Entrar por el navegador manda
     * al menú, igual que cualquier otra ruta sin permisos.
     */
    public function index(): void
    {
        $this->redirect(rtrim(BASE_URL, '/') . '/home/index');
    }

    public function generarAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $solicitada = trim((string) ($_POST['modulo'] ?? $_GET['modulo'] ?? ''));

            // Solo rutas declaradas en config/contabilidad_modulos.php, y siempre
            // en su forma canónica (de ahí salen los permisos). Cualquier otra
            // cosa —incluido un intento de pasar una ruta arbitraria— se responde
            // igual de vacío, sin pistas.
            $ruta = $solicitada !== '' ? ContabilidadModulos::rutaCanonica($solicitada) : null;
            if ($ruta === null) {
                echo json_encode(['ok' => true]);
                return;
            }

            $this->rutaSolicitada = $ruta;
            $this->requireLeer(); // sesión + empresa activa + permiso de lectura del módulo de origen

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];

            // La generación puede tardar: liberar el lock de sesión para no
            // bloquear el resto de peticiones del usuario mientras corre.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            @set_time_limit(120);
            @ignore_user_abort(true);

            ContabilidadAutoService::crear()->generarPorRuta($idEmpresa, $idUsuario, $ruta);

            echo json_encode(['ok' => true]);
        } catch (\Throwable $th) {
            // Silencioso también en el error: se registra en el log del servidor
            // y la pantalla del usuario sigue como si nada.
            ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => true]);
        }
    }

    /**
     * Genera el asiento de UN documento que aún no lo tiene. Lo llama la pestaña «Asiento
     * contable» al abrirse (public/js/modulos/asiento_contable_tab.js). POST: modulo_origen, id.
     * Permiso: ver el módulo dueño del documento, igual que la pasada al abrir el módulo.
     * Responde {ok, generado, motivo?}; la pestaña recarga el asiento si se generó.
     */
    public function generarDocumentoAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $origen = trim((string) ($_POST['modulo_origen'] ?? ''));
            $idDoc  = (int) ($_POST['id'] ?? 0);
            $clave  = $origen !== '' ? ContabilidadModulos::claveDeOrigen($origen) : null;
            $def    = $clave !== null ? ContabilidadModulos::definicion($clave) : null;
            $ruta   = $def !== null ? ContabilidadModulos::rutaCanonica((string) (((array) ($def['rutas'] ?? []))[0] ?? '')) : null;
            if ($clave === null || $ruta === null || $idDoc <= 0) {
                echo json_encode(['ok' => true, 'generado' => false]);
                return;
            }

            $this->rutaSolicitada = $ruta;
            $this->requireLeer();

            $idEmpresa = (int) $_SESSION['id_empresa'];
            $idUsuario = (int) $_SESSION['id_usuario'];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $res = ContabilidadAutoService::crear()->generarDocumento($idEmpresa, $idUsuario, $clave, $idDoc);
            echo json_encode(['ok' => true] + $res);
        } catch (\Throwable $th) {
            ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            echo json_encode(['ok' => false, 'generado' => false]);
        }
    }
}
