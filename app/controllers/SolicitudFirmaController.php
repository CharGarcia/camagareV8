<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\repositories\modulos\FirmaSolicitudRepository;
use App\repositories\modulos\FirmaElectronicaRepository;

/**
 * Controlador PÚBLICO — sin autenticación.
 * Muestra y procesa el formulario de firma electrónica enviado por email.
 */
class SolicitudFirmaController extends Controller
{
    private FirmaSolicitudRepository $solRepo;
    private FirmaElectronicaRepository $firmaRepo;

    private const EXTS_IMG = ['jpg', 'jpeg', 'png', 'webp'];
    private const EXTS_PDF = ['pdf'];
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct()
    {
        parent::__construct();
        $this->solRepo   = new FirmaSolicitudRepository();
        $this->firmaRepo = new FirmaElectronicaRepository();
    }

    // ── GET: mostrar formulario ───────────────────────────────

    public function index(): void
    {
        $token     = trim($_GET['token'] ?? '');
        $solicitud = $this->validarToken($token);
        if (!$solicitud) return;

        $idEmpresa = (int) $solicitud['id_empresa'];

        $this->solRepo->expirarVencidos();

        // Recargar para reflejar posible expiración
        $solicitud = $this->solRepo->getByToken($token);
        if (!$solicitud || $solicitud['estado'] !== 'pendiente') {
            $this->renderError('Este enlace ya no está disponible. Puede que haya expirado o ya fue utilizado.');
            return;
        }

        $provincias  = (new \App\models\Provincia())->getTodas();
        $tiposFirma  = $this->getTiposFirmaPublico($idEmpresa);

        $this->renderForm($solicitud, $provincias, $tiposFirma, [], null);
    }

    // ── POST: procesar envío ──────────────────────────────────

    public function enviar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/');
            exit;
        }

        $token     = trim($_POST['token'] ?? '');
        $solicitud = $this->validarToken($token);
        if (!$solicitud) return;

        if ($solicitud['estado'] !== 'pendiente') {
            $this->renderError('Este enlace ya no está disponible o ya fue utilizado.');
            return;
        }

        $idEmpresa = (int) $solicitud['id_empresa'];
        $provincias = (new \App\models\Provincia())->getTodas();
        $tiposFirma = $this->getTiposFirmaPublico($idEmpresa);

        // ── Validar campos ────────────────────────────────────
        $errores = [];
        $data    = $this->recogerPost();

        if (empty($data['id_producto']))           $errores[] = 'Seleccione la validez de la firma.';
        if (empty($data['tipo_identificacion']))   $errores[] = 'Seleccione el tipo de identificación.';
        if (empty($data['numero_identificacion'])) $errores[] = 'Ingrese el número de identificación.';
        if (empty($data['nombres']))               $errores[] = 'Ingrese los nombres.';
        if (empty($data['apellidos']))             $errores[] = 'Ingrese los apellidos.';
        if (empty($data['telefono']))              $errores[] = 'Ingrese el teléfono.';
        if (empty($data['correo']))                $errores[] = 'Ingrese el correo electrónico.';
        if (!filter_var($data['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electrónico no es válido.';
        if (empty($data['cod_prov']))              $errores[] = 'Seleccione la provincia.';
        if (empty($data['cod_ciudad']))            $errores[] = 'Seleccione la ciudad.';
        if (empty($data['direccion']))             $errores[] = 'Ingrese la dirección.';

        // Validar adjuntos obligatorios
        $adjuntosRequeridos = ['cedula_frontal', 'cedula_posterior', 'selfie_cedula'];
        if ($data['tipo_persona'] === 'juridica') {
            $adjuntosRequeridos = array_merge($adjuntosRequeridos, ['ruc_empresa', 'constitucion', 'nombramiento', 'aceptacion_nombramiento']);
        }

        foreach ($adjuntosRequeridos as $campo) {
            if (empty($_FILES[$campo]['tmp_name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
                $labels = [
                    'cedula_frontal'         => 'Foto cédula (frente)',
                    'cedula_posterior'       => 'Foto cédula (posterior)',
                    'selfie_cedula'          => 'Selfie con cédula',
                    'ruc_empresa'            => 'RUC de la empresa',
                    'constitucion'           => 'Constitución de la compañía',
                    'nombramiento'           => 'Nombramiento',
                    'aceptacion_nombramiento'=> 'Aceptación del nombramiento',
                ];
                $errores[] = 'El documento "' . ($labels[$campo] ?? $campo) . '" es obligatorio.';
            }
        }

        if (!empty($errores)) {
            $this->renderForm($solicitud, $provincias, $tiposFirma, $errores, $data);
            return;
        }

        // ── Buscar nombre del producto ────────────────────────
        $nombreProducto = '';
        foreach ($tiposFirma as $t) {
            if ((int)$t['id'] === (int)$data['id_producto']) {
                $nombreProducto = $t['nombre'];
                break;
            }
        }

        // ── Insertar firma ────────────────────────────────────
        $firmaData = array_merge($data, [
            'id_empresa'             => $idEmpresa,
            'id_usuario'             => null,
            'nombre_producto'        => $nombreProducto,
            'estado'                 => 'pendiente',
            'estado_pago'            => 'pendiente',
            'facturacion_mismos_datos' => true,
        ]);

        try {
            $db = \App\core\Database::getConnection();
            $db->beginTransaction();

            $idFirma = $this->firmaRepo->create($firmaData);

            // ── Guardar adjuntos ──────────────────────────────
            $storageBase = MVC_ROOT . '/storage/firmas/' . $idEmpresa;
            if (!is_dir($storageBase)) {
                mkdir($storageBase, 0755, true);
            }

            // Clave = nombre del campo en $_FILES, valor = [tipo guardado en BD, extensiones permitidas]
            // Los tipos deben coincidir exactamente con los que espera el modal interno
            $tiposAdj = [
                'cedula_frontal'          => ['cedula_frontal',          self::EXTS_IMG],
                'cedula_posterior'        => ['cedula_posterior',        self::EXTS_IMG],
                'selfie_cedula'           => ['selfie',                  self::EXTS_IMG],
                'ruc_empresa'             => ['ruc_empresa',             self::EXTS_PDF],
                'constitucion'            => ['constitucion',            self::EXTS_PDF],
                'nombramiento'            => ['nombramiento',            self::EXTS_PDF],
                'aceptacion_nombramiento' => ['aceptacion_nombramiento', self::EXTS_PDF],
            ];

            foreach ($tiposAdj as $campo => [$tipo, $extsPermitidas]) {
                if (empty($_FILES[$campo]['tmp_name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) continue;

                $file      = $_FILES[$campo];
                $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $extsPermitidas, true)) continue;
                if ($file['size'] > self::MAX_BYTES) continue;

                $nombreArchivo = $idFirma . '_' . $tipo . '_' . time() . '.' . $ext;
                $destino       = $storageBase . '/' . $nombreArchivo;

                if (move_uploaded_file($file['tmp_name'], $destino)) {
                    $this->firmaRepo->createAdjunto([
                        'id_firma'       => $idFirma,
                        'id_empresa'     => $idEmpresa,
                        'tipo'           => $tipo,
                        'nombre_original'=> $file['name'],
                        'nombre_archivo' => $nombreArchivo,
                        'ruta_relativa'  => 'firmas/' . $idEmpresa . '/' . $nombreArchivo,
                        'mime_type'      => $file['type'],
                        'tamano_bytes'   => $file['size'],
                        'created_by'     => null,
                    ]);
                }
            }

            $this->solRepo->marcarCompletado((int)$solicitud['id'], $idFirma);

            $db->commit();

            $this->renderExito($solicitud);

        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) $db->rollBack();
            error_log('SolicitudFirma::enviar error: ' . $e->getMessage());
            $errores[] = 'Ocurrió un error al guardar los datos. Por favor intente nuevamente.';
            $this->renderForm($solicitud, $provincias, $tiposFirma, $errores, $data);
        }
    }

    // ── AJAX: ciudades por provincia ──────────────────────────

    public function ciudades(): void
    {
        header('Content-Type: application/json');
        $codProv = trim($_GET['cod_prov'] ?? '');
        if ($codProv === '') { echo json_encode([]); return; }

        $rows = (new \App\models\Ciudad())->getPorProvincia($codProv);
        echo json_encode(array_map(fn($c) => ['codigo' => $c['codigo'], 'nombre' => $c['nombre']], $rows));
    }

    // ── AJAX: consultar SRI ───────────────────────────────────

    public function sri(): void
    {
        header('Content-Type: application/json');
        $id  = trim($_GET['id'] ?? '');
        if ($id === '') { echo json_encode(['ok' => false, 'error' => 'Identificación vacía']); return; }

        $svc = new \App\Services\SriIdentificationService();
        echo json_encode($svc->consultar($id));
    }

    // ── Helpers privados ──────────────────────────────────────

    private function validarToken(string $token): ?array
    {
        if (strlen($token) !== 64 || !preg_match('/^[a-f0-9]+$/i', $token)) {
            $this->renderError('Enlace inválido.');
            return null;
        }
        $solicitud = $this->solRepo->getByToken($token);
        if (!$solicitud) {
            $this->renderError('El enlace no existe o ha expirado.');
            return null;
        }
        return $solicitud;
    }

    private function recogerPost(): array
    {
        $str = fn($k) => trim($_POST[$k] ?? '');
        $fn  = fn($v) => $v === '' ? null : $v;

        $fechaNac = $str('fecha_nacimiento');
        if ($fechaNac !== '' && preg_match('/^\d{2}-\d{2}-\d{4}$/', $fechaNac)) {
            $fechaNac = \DateTime::createFromFormat('d-m-Y', $fechaNac)->format('Y-m-d');
        } elseif ($fechaNac !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
            // ya en formato Y-m-d
        } else {
            $fechaNac = null;
        }

        return [
            'tipo_persona'          => in_array($str('tipo_persona'), ['natural', 'juridica'], true) ? $str('tipo_persona') : 'natural',
            'con_ruc'               => !empty($_POST['con_ruc']),
            'ruc_empresa'           => $fn($str('ruc_empresa')),
            'nombre_empresa'        => $fn($str('nombre_empresa')),
            'cargo'                 => $fn($str('cargo')),
            'tipo_identificacion'   => $fn($str('tipo_identificacion')),
            'numero_identificacion' => $fn($str('numero_identificacion')),
            'codigo_dactilar'       => $fn($str('codigo_dactilar')),
            'nombres'               => $fn($str('nombres')),
            'apellidos'             => $fn($str('apellidos')),
            'sexo'                  => $fn($str('sexo')),
            'fecha_nacimiento'      => $fechaNac,
            'nacionalidad'          => $fn($str('nacionalidad')) ?: 'Ecuatoriana',
            'telefono'              => $fn($str('telefono')),
            'correo'                => $fn($str('correo')),
            'cod_prov'              => $fn($str('cod_prov')),
            'cod_ciudad'            => $fn($str('cod_ciudad')),
            'direccion'             => $fn($str('direccion')),
            'id_producto'           => $fn($str('id_producto')),
            'tipo_pago'             => null,
            'fecha_caducidad'       => null,
            'observaciones'         => $fn($str('observaciones')),
            'facturacion_mismos_datos' => true,
            'facturacion_tipo_id'   => null,
            'facturacion_num_id'    => null,
            'facturacion_nombres'   => null,
            'facturacion_direccion' => null,
            'facturacion_correo'    => null,
            'facturacion_telefono'  => null,
        ];
    }

    private function getTiposFirmaPublico(int $idEmpresa): array
    {
        try {
            $st = $this->db->prepare(
                "SELECT p.id, p.nombre,
                        ROUND((p.precio_base + COALESCE(p.valor_ice, 0))
                            * (1 + COALESCE(ti.porcentaje_iva, 0)::numeric / 100), 2) AS pvp
                 FROM productos p
                 INNER JOIN categorias c ON c.id = p.id_categoria
                 LEFT  JOIN tarifa_iva ti ON ti.id = p.tarifa_iva
                 WHERE p.id_empresa = :ie AND p.eliminado = false AND p.status = 1
                   AND UPPER(c.nombre) LIKE '%FIRMA%'
                 ORDER BY p.nombre ASC"
            );
            $st->execute([':ie' => $idEmpresa]);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    private function renderForm(array $solicitud, array $provincias, array $tiposFirma, array $errores, ?array $post): void
    {
        $this->view('publica.solicitud_firma', [
            'solicitud'  => $solicitud,
            'provincias' => $provincias,
            'tiposFirma' => $tiposFirma,
            'errores'    => $errores,
            'post'       => $post ?? [],
        ]);
    }

    private function renderExito(array $solicitud): void
    {
        $this->view('publica.solicitud_firma_exito', [
            'solicitud' => $solicitud,
        ]);
    }

    private function renderError(string $mensaje): void
    {
        $this->view('publica.solicitud_firma_error', [
            'mensaje' => $mensaje,
        ]);
    }
}
