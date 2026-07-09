<?php

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\BiometriaRepository;
use App\repositories\modulos\AsistenciaPuntoRepository;
use App\repositories\modulos\MarcacionRepository;
use App\Rules\modulos\MarcacionRules;
use App\Services\LogSistemaService;
use App\Services\modulos\MarcacionService;

/**
 * Endpoint PÚBLICO de marcación (sin sesión). Registrado en la lista de
 * $publicControllers de Application.
 *
 * Flujo:
 *   /asistencia/app?e={tokenEmpleado}   → vincula el celular del empleado (guarda su token).
 *   /asistencia/marcar?p={tokenPunto}   → página de marcación (lee token del empleado + escaneo del punto).
 *   POST /asistencia/registrar          → registra la marca (selfie + GPS + anti-fraude).
 *
 * La identidad viaja por token opaco (no datos personales en el QR).
 */
class AsistenciaController extends Controller
{
    /** Vinculación del celular del empleado con su credencial personal. */
    public function app(): void
    {
        $token = trim($_GET['e'] ?? '');
        $empleado = $token !== '' ? (new BiometriaRepository())->getByQrToken($token) : null;

        $this->view('publica.asistencia.app', [
            'base'     => rtrim(BASE_URL, '/'),
            'token'    => $token,
            'nombre'   => $empleado['nombres_apellidos'] ?? '',
            'valido'   => (bool) $empleado,
        ]);
    }

    /** Página de marcación al escanear el QR del punto de servicio. */
    public function marcar(): void
    {
        $token = trim($_GET['p'] ?? '');
        $punto = $token !== '' ? (new AsistenciaPuntoRepository())->getByQrToken($token) : null;

        $this->view('publica.asistencia.marcar', [
            'base'       => rtrim(BASE_URL, '/'),
            'tokenPunto' => $token,
            'puntoNombre' => $punto['nombre'] ?? '',
            'exigeGps'   => !empty($punto['exige_gps']),
            'valido'     => (bool) $punto,
        ]);
    }

    /** POST: devuelve el descriptor facial del empleado (por su token) para reconocimiento 1:1. */
    public function descriptor(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $token = trim($_POST['tokenEmpleado'] ?? '');
        $desc = $token !== '' ? (new BiometriaRepository())->getDescriptorByQrToken($token) : null;
        echo json_encode(['ok' => true, 'descriptor' => $desc]);
        exit;
    }

    /** POST: registra la marca. Responde JSON. */
    public function registrar(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $tokenEmpleado = trim($_POST['tokenEmpleado'] ?? '');
            $tokenPunto    = trim($_POST['tokenPunto'] ?? '');
            $tipo          = trim($_POST['tipo'] ?? '');
            $lat           = $_POST['latitud'] ?? '';
            $lng           = $_POST['longitud'] ?? '';
            $selfie        = $_POST['selfie'] ?? '';

            // Resolver empresa (para archivar la selfie) desde el token del empleado.
            $bio = (new BiometriaRepository())->getByQrToken($tokenEmpleado);
            $idEmpresa = $bio ? (int) $bio['id_empresa'] : 0;
            $selfiePath = $this->guardarSelfie($selfie, $idEmpresa);

            $service = new MarcacionService(
                new MarcacionRepository(),
                new BiometriaRepository(),
                new AsistenciaPuntoRepository(),
                new MarcacionRules(),
                new LogSistemaService()
            );

            $res = $service->marcarPorQr([
                'tokenEmpleado' => $tokenEmpleado,
                'tokenPunto'    => $tokenPunto,
                'tipo'          => $tipo,
                'latitud'       => $lat,
                'longitud'      => $lng,
                'selfie_path'   => $selfiePath,
                'confianza'     => $_POST['confianza'] ?? '',
                'face_sospechosa' => !empty($_POST['face_sospechosa']),
                'dispositivo_id' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120),
            ]);

            echo json_encode(['ok' => true] + $res);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Guarda la selfie (dataURL base64) en storage y devuelve la ruta relativa. */
    private function guardarSelfie(string $dataUrl, int $idEmpresa): ?string
    {
        if ($dataUrl === '' || !preg_match('#^data:image/(png|jpe?g);base64,#', $dataUrl, $m)) {
            return null;
        }
        $ext = ($m[1] === 'png') ? 'png' : 'jpg';
        $bin = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1) ?: '');
        if ($bin === false || strlen($bin) < 500) {
            return null;
        }
        $rel = 'asistencia_selfies/' . date('Y/m');
        $dir = MVC_ROOT . '/storage/' . $rel;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = $idEmpresa . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bin) === false) {
            return null;
        }
        return $rel . '/' . $name;
    }
}
