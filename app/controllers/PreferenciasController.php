<?php

declare(strict_types=1);

namespace App\controllers;

use App\repositories\UsuarioPreferenciaRepository;
use App\Services\UsuarioPreferenciaService;

use App\core\Controller;

class PreferenciasController extends Controller
{
    private UsuarioPreferenciaService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new UsuarioPreferenciaService(new UsuarioPreferenciaRepository());
    }

    /**
     * Endpoint asíncrono para guardar una preferencia
     */
    public function guardarAjax(): void
    {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['id_usuario']) || !isset($_SESSION['id_empresa'])) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }

        $idUsuario = (int) $_SESSION['id_usuario'];
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $modulo = str_replace('-', '_', basename(trim($_POST['modulo'] ?? '')));
        $campo  = trim($_POST['campo'] ?? '');
        $valor  = trim($_POST['valor'] ?? '');

        try {
            if ($modulo === '' || $campo === '') {
                throw new \Exception('Datos incompletos.');
            }

            $this->service->guardarPreferencia($idUsuario, $idEmpresa, $modulo, $campo, $valor);
            
            // Si el cliente pide recargar la cache en sesión, la actualizamos
            if (!isset($_SESSION['favoritos'])) {
                $_SESSION['favoritos'] = [];
            }
            if (!isset($_SESSION['favoritos'][$modulo])) {
                $_SESSION['favoritos'][$modulo] = [];
            }
            $_SESSION['favoritos'][$modulo][$campo] = $valor;

            echo json_encode(['ok' => true, 'msg' => 'Favorito guardado']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * Endpoint asíncrono para guardar las preferencias de vista de un módulo
     */
    public function guardarVistaAjax(): void
    {
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['id_usuario']) || !isset($_SESSION['id_empresa'])) {
            echo json_encode(['ok' => false, 'error' => 'No autorizado']);
            exit;
        }

        $idUsuario = (int) $_SESSION['id_usuario'];
        $idEmpresa = (int) $_SESSION['id_empresa'];
        
        $modulo = str_replace('-', '_', basename(trim($_POST['modulo'] ?? '')));
        $payloadRaw = $_POST['vistaPayload'] ?? '{}';
        
        try {
            if ($modulo === '') {
                throw new \Exception('Módulo no especificado.');
            }

            $vistaPayload = json_decode((string)$payloadRaw, true);
            if (!is_array($vistaPayload)) {
                $vistaPayload = [];
            }

            // 1. Obtener lo que ya existe
            $prefsActuales = $this->service->obtenerPreferencias($idUsuario, $idEmpresa, $modulo);
            $vistaFinal = $prefsActuales['__vista__'] ?? [];

            // 2. Mezclar con lo nuevo
            foreach ($vistaPayload as $k => $v) {
                $vistaFinal[$k] = $v;
            }

            // 3. Guardar en BD
            $this->service->guardarPreferencia($idUsuario, $idEmpresa, $modulo, '__vista__', $vistaFinal);
            
            // 4. Limpiar sesión (forzar recarga de BD)
            if (isset($_SESSION['favoritos'][$modulo])) {
                unset($_SESSION['favoritos'][$modulo]);
            }

            echo json_encode(['ok' => true, 'msg' => 'Preferencias guardadas en base de datos']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * Endpoint asíncrono para guardar la empresa favorita (global) del usuario
     */
    public function guardarEmpresaFavoritaAjax(): void
    {
        header('Content-Type: application/json');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['id_usuario'])) {
            echo json_encode(['ok' => false, 'error' => 'Sesión expirada o no válida.']);
            exit;
        }

        $idUsuario = (int) $_SESSION['id_usuario'];
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);

        // Registro de depuración en el log de errores de PHP
        error_log("Intentando guardar empresa favorita: Usuario $idUsuario, Empresa $idEmpresa");

        try {
            if ($idEmpresa <= 0) {
                throw new \Exception('ID de empresa inválido.');
            }

            $model = new \App\models\Usuario();
            if ($model->setEmpresaFavorita($idUsuario, $idEmpresa)) {
                // Actualizar la variable en sesión para que persista en esta navegación
                $_SESSION['id_empresa_favorita'] = $idEmpresa;
                echo json_encode(['ok' => true, 'id_favorita' => $idEmpresa]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Error al ejecutar la actualización en BD.']);
            }
        } catch (\Throwable $e) {
            error_log("Excepción en guardarEmpresaFavoritaAjax: " . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}
