<?php
declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\modulos\FirmaElectronicaService;

/**
 * Controlador PÚBLICO — sin autenticación (está en $publicControllers).
 * Portal del cliente para solicitar su firma electrónica:
 *   GET  /solicitud-firma/{token}                 portal (datos → documentos → pago → seguimiento)
 *   POST /solicitud-firma/{token}/guardar         datos del titular
 *   POST /solicitud-firma/{token}/adjunto         sube un documento
 *   POST /solicitud-firma/{token}/quitar-adjunto  quita un documento
 *   POST /solicitud-firma/{token}/documentos      envía los documentos (pasa a pago)
 *   POST /solicitud-firma/{token}/pagar           inicia el pago con Payphone
 *   POST /solicitud-firma/{token}/comprobante     sube el comprobante de transferencia
 *   GET|POST /solicitud-firma/e/{codigo}          enlace fijo de la empresa
 *   GET  /solicitud-firma/confirmar-correo/{t}    confirma el correo (botón del correo con la factura)
 * La empresa se resuelve siempre por el token/código con la empresa activa (CLAUDE.md §6).
 * Toda la lógica vive en FirmaElectronicaService.
 */
class SolicitudFirmaController extends Controller
{
    private FirmaElectronicaService $service;

    /** Consultas al SRI permitidas por sesión y hora (el endpoint es público). */
    private const MAX_SRI_HORA = 20;

    public function __construct()
    {
        parent::__construct();
        $this->service = FirmaElectronicaService::instancia();
    }

    // ── Páginas ───────────────────────────────────────────────

    public function index(): void
    {
        $portal = $this->service->resolverPortal(trim($_GET['token'] ?? ''));
        if (!$portal) {
            $this->renderError('El enlace no existe o ya no está disponible.');
            return;
        }
        if (in_array($portal['solicitud']['estado'], ['expirado', 'cancelado'], true) && !$portal['firma']) {
            $this->renderError('Este enlace ya no está disponible. Solicite uno nuevo a la empresa.');
            return;
        }
        $this->renderPortal($portal);
    }

    /** Enlace fijo: GET muestra el formulario vacío; POST crea la solicitud y devuelve su enlace. */
    public function enlace(): void
    {
        $codigo = trim($_GET['codigo'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->responder(function () use ($codigo) {
                $token = $this->service->crearDesdeEnlaceFijo($codigo, $this->recogerPost(), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                return ['url' => FirmaElectronicaService::urlPortal($token)];
            });
            return;
        }
        $portal = $this->service->resolverEnlaceFijo($codigo);
        if (!$portal) {
            $this->renderError('Este enlace ya no está disponible.');
            return;
        }
        $this->renderPortal($portal);
    }

    public function confirmarCorreo(): void
    {
        try {
            $firma = $this->service->confirmarCorreo(trim($_GET['token'] ?? ''));
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            $firma = null;
        }
        if (!$firma) {
            $this->renderError('El enlace de confirmación no es válido o ya no está disponible.');
            return;
        }
        $this->view('publica.solicitud_firma_exito', [
            'titulo'    => '¡Correo confirmado!',
            'mensaje'   => 'Gracias. Confirmaste que ' . $firma['correo'] . ' es tu correo: ahí te llegará la activación de tu firma electrónica.',
            'urlPortal' => !empty($firma['token_portal']) ? FirmaElectronicaService::urlPortal((string) $firma['token_portal']) : null,
            'empresa'   => $firma['empresa_nombre'] ?? '',
        ]);
    }

    // ── Acciones del portal (AJAX, JSON) ──────────────────────

    public function guardar(): void
    {
        $this->responder(function () {
            $this->service->guardarDatosPortal($this->token(), $this->recogerPost());
            return ['msg' => 'Datos guardados.'];
        });
    }

    public function adjunto(): void
    {
        $this->responder(function () {
            $tipo = trim($_POST['tipo'] ?? '');
            $res  = $this->service->subirAdjuntoPortal($this->token(), $tipo, $_FILES['archivo'] ?? []);
            return ['adjunto' => $res];
        });
    }

    public function quitarAdjunto(): void
    {
        $this->responder(function () {
            $this->service->quitarAdjuntoPortal($this->token(), (int) ($_POST['id'] ?? 0));
            return ['msg' => 'Documento quitado.'];
        });
    }

    public function documentos(): void
    {
        $this->responder(function () {
            $this->service->finalizarDocumentosPortal($this->token());
            return ['msg' => 'Documentos enviados.'];
        });
    }

    public function pagar(): void
    {
        $this->responder(function () {
            return ['url' => $this->service->iniciarPagoPayphone($this->token())];
        });
    }

    public function comprobante(): void
    {
        $this->responder(function () {
            $this->service->subirComprobantePortal($this->token(), $_FILES['archivo'] ?? []);
            return ['msg' => 'Comprobante recibido. Te avisaremos cuando lo revisemos.'];
        });
    }

    // ── AJAX auxiliares ───────────────────────────────────────

    public function ciudades(): void
    {
        header('Content-Type: application/json');
        $codProv = trim($_GET['cod_prov'] ?? '');
        if ($codProv === '') { echo json_encode([]); return; }

        $rows = (new \App\models\Ciudad())->getPorProvincia($codProv);
        echo json_encode(array_map(fn($c) => ['codigo' => $c['codigo'], 'nombre' => $c['nombre']], $rows));
    }

    /**
     * Autocompleta nombres desde el SRI. Público, así que exige un enlace válido
     * (token o código del enlace fijo) y un tope por sesión.
     */
    public function sri(): void
    {
        header('Content-Type: application/json');
        $id     = trim($_GET['id'] ?? '');
        $token  = trim($_GET['token'] ?? '');
        $codigo = trim($_GET['codigo'] ?? '');
        $valido = ($token !== '' && $this->service->resolverPortal($token))
               || ($codigo !== '' && $this->service->resolverEnlaceFijo($codigo));
        if (!$valido || !preg_match('/^\d{10}(\d{3})?$/', $id)) {
            echo json_encode(['ok' => false, 'error' => 'Consulta no permitida.']);
            return;
        }
        $hora = date('YmdH');
        $cont = $_SESSION['firma_sri_consultas'] ?? ['hora' => $hora, 'n' => 0];
        if ($cont['hora'] !== $hora) {
            $cont = ['hora' => $hora, 'n' => 0];
        }
        if (++$cont['n'] > self::MAX_SRI_HORA) {
            echo json_encode(['ok' => false, 'error' => 'Demasiadas consultas. Escriba sus datos a mano.']);
            return;
        }
        $_SESSION['firma_sri_consultas'] = $cont;
        session_write_close();

        $svc = new \App\Services\SriIdentificationService();
        echo json_encode($svc->consultar($id));
    }

    // ── Helpers privados ──────────────────────────────────────

    private function token(): string
    {
        return trim($_GET['token'] ?? '');
    }

    /** Ejecuta la acción y responde JSON; los errores de negocio se muestran al cliente tal cual. */
    private function responder(callable $fn): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
            return;
        }
        try {
            echo json_encode(['ok' => true] + $fn());
        } catch (\PDOException $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'portal']);
            echo json_encode(['ok' => false, 'error' => 'Ocurrió un error al guardar. Intente nuevamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function recogerPost(): array
    {
        $str = fn($k) => trim((string) ($_POST[$k] ?? ''));
        $fn  = fn($v) => $v === '' ? null : $v;

        $fechaNac = $str('fecha_nacimiento');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $fechaNac, $m)) {
            $fechaNac = "{$m[3]}-{$m[2]}-{$m[1]}";
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
            $fechaNac = null;
        }
        $mismos = !isset($_POST['facturacion_mismos_datos']) || !empty($_POST['facturacion_mismos_datos']);

        return [
            'tipo_persona'          => in_array($str('tipo_persona'), ['natural', 'juridica', 'miembro'], true) ? $str('tipo_persona') : 'natural',
            'con_ruc'               => !empty($_POST['con_ruc']),
            'ruc_empresa'           => $fn($str('ruc_empresa')),
            'nombre_empresa'        => $fn($str('nombre_empresa')),
            'cargo'                 => $fn($str('cargo')),
            'tipo_identificacion'   => $str('tipo_identificacion'),
            'numero_identificacion' => $str('numero_identificacion'),
            'codigo_dactilar'       => $fn(mb_strtoupper($str('codigo_dactilar'), 'UTF-8')),
            'nombres'               => $str('nombres'),
            'apellidos'             => trim($str('apellido_paterno') . ' ' . $str('apellido_materno')),
            'apellido_paterno'      => $str('apellido_paterno'),
            'apellido_materno'      => $str('apellido_materno'),
            'telefono2'             => $str('telefono2'),
            'correo2'               => mb_strtolower($str('correo2'), 'UTF-8'),
            'departamento'          => $str('departamento'),
            'motivo'                => $str('motivo'),
            'rep_tipo_identificacion' => in_array($str('rep_tipo_identificacion'), ['cedula', 'pasaporte'], true) ? $str('rep_tipo_identificacion') : 'cedula',
            'rep_identificacion'    => $str('rep_identificacion'),
            'rep_nombres'           => $str('rep_nombres'),
            'rep_apellidos'         => $str('rep_apellidos'),
            'sexo'                  => $fn($str('sexo')),
            'fecha_nacimiento'      => $fechaNac,
            'nacionalidad'          => $fn($str('nacionalidad')) ?: 'Ecuatoriana',
            'telefono'              => $str('telefono'),
            'correo'                => mb_strtolower($str('correo'), 'UTF-8'),
            'cod_prov'              => $fn($str('cod_prov')),
            'cod_ciudad'            => $fn($str('cod_ciudad')),
            'direccion'             => $str('direccion'),
            'id_producto'           => (int) $str('id_producto') ?: null,
            'observaciones'         => $fn($str('observaciones')),
            'facturacion_mismos_datos' => $mismos,
            'facturacion_tipo_id'   => $mismos ? null : $fn($str('facturacion_tipo_id')),
            'facturacion_num_id'    => $mismos ? null : $fn($str('facturacion_num_id')),
            'facturacion_nombres'   => $mismos ? null : $fn($str('facturacion_nombres')),
            'facturacion_direccion' => $mismos ? null : $fn($str('facturacion_direccion')),
            'facturacion_correo'    => $mismos ? null : $fn($str('facturacion_correo')),
            'facturacion_telefono'  => $mismos ? null : $fn($str('facturacion_telefono')),
        ];
    }

    private function renderPortal(array $portal): void
    {
        $this->view('publica.solicitud_firma', [
            'portal'     => $portal,
            'provincias' => (new \App\models\Provincia())->getTodas(),
        ]);
    }

    private function renderError(string $mensaje): void
    {
        $this->view('publica.solicitud_firma_error', [
            'mensaje' => $mensaje,
        ]);
    }
}
