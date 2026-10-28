<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\FirmaElectronicaRepository;
use App\repositories\modulos\FirmaSolicitudRepository;
use App\Rules\modulos\FirmaElectronicaRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Firmas electrónicas que la empresa vende a sus clientes (trámite con Uanataca).
 *
 * Flujo de autoservicio:
 *   1. El operador envía un enlace (correo y/o WhatsApp) o el cliente entra por el
 *      enlace fijo de la empresa → portal público /solicitud-firma/{token}.
 *   2. El cliente llena sus datos (firma en 'borrador') y sube sus documentos
 *      (→ 'pendiente' de pago).
 *   3. Paga con Payphone (aprobación automática) o sube el comprobante de transferencia
 *      (un operador lo aprueba). Al confirmarse el pago se crea la factura, el cobro
 *      (ingreso) y se envía al SRI → firma 'por_registrar'.
 *   4. Con la factura autorizada se envía al cliente un correo con la factura y un botón
 *      para confirmar que el correo es suyo (Uanataca le envía ahí la activación).
 *   5. El operador registra la solicitud en el portal de Uanataca a mano ('en_proceso')
 *      y, cuando se emite, la marca 'emitida' con su caducidad.
 * Cada paso deja un evento en firmas_electronicas_eventos: el cliente ve los visibles
 * en su enlace y el sistema todos.
 */
class FirmaElectronicaService
{
    private const MODULO_LOG    = 'firmas_electronicas';
    private const STORAGE_DIR   = 'firmas_electronicas';
    private const DIAS_ENLACE   = 15;
    private const MAX_POR_IP_HORA = 5;
    public  const MODULO_PAGO   = 'firma_electronica';
    public  const PLANTILLA_WA  = 'solicitud_firma';
    /**
     * Pago y datos de facturación: no se cambian mientras la factura siga activa.
     * El tipo de firma (producto) sí, hasta que se emita: la factura queda con el anterior.
     */
    private const CAMPOS_FACTURADOS = [
        'tipo_pago', 'estado_pago',
        'facturacion_tipo_id', 'facturacion_num_id', 'facturacion_nombres',
        'facturacion_direccion', 'facturacion_correo', 'facturacion_telefono',
    ];

    private FirmaElectronicaRepository $repository;
    private FirmaElectronicaRules      $rules;
    private LogSistemaService          $logService;
    private FirmaSolicitudRepository   $solRepo;

    public function __construct(
        FirmaElectronicaRepository $repository,
        FirmaElectronicaRules      $rules,
        LogSistemaService          $logService,
        ?FirmaSolicitudRepository  $solRepo = null
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
        $this->solRepo    = $solRepo ?? new FirmaSolicitudRepository();
    }

    /** Instancia con sus dependencias (controladores públicos, Payphone, cron). */
    public static function instancia(): self
    {
        return new self(new FirmaElectronicaRepository(), new FirmaElectronicaRules(), new LogSistemaService());
    }

    // ═════════════════════════════════════════════════════════════════════
    // LISTADO Y CRUD INTERNO
    // ═════════════════════════════════════════════════════════════════════

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Tipos de firma y usuarios usados (selects del modal de filtros del listado). */
    public function getOpcionesFiltroListado(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getOpcionesFiltroListado($idEmpresa, $idUsuarioFiltro);
    }

    public function crear(array $data): int
    {
        $this->rules->validar($data);
        $this->validarTipoPersonaFirma((int) $data['id_empresa'], (int) ($data['id_producto'] ?? 0) ?: null, (string) ($data['tipo_persona'] ?? 'natural'));
        // El motivo ya no se escribe en la ficha: el de por defecto para representante legal.
        $data['motivo'] = self::motivoParaTipo((string) ($data['tipo_persona'] ?? 'natural'), null);

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->repository->crearEvento((int) $data['id_empresa'], $id, 'creada', 'Solicitud registrada', null, true, (int) $data['id_usuario']);

            $this->logService->registrar(
                (int)$data['id_usuario'],
                (int)$data['id_empresa'],
                'crear',
                'firmas_electronicas',
                $id,
                null,
                $data
            );

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $this->rules->validar($data);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La firma electrónica no existe o ha sido eliminada.');
        }
        // Aprobada por Uanataca o emitida: la solicitud ya no se edita (el avance va por la pestaña Trámite).
        if (in_array($antes['estado'] ?? '', FirmaElectronicaRules::ESTADOS_CERRADOS, true)) {
            throw new Exception('Esta solicitud ya está ' . mb_strtolower(self::etiquetaEstado((string) $antes['estado']), 'UTF-8')
                . ' y no se puede editar. El avance del trámite se lleva desde la pestaña Trámite.');
        }
        // Con factura activa se pueden corregir los datos del titular, el tipo de firma y el
        // trámite; el pago y los datos de facturación se conservan tal cual.
        if ($this->errorFacturaActiva($id, $idEmpresa)) {
            foreach (self::CAMPOS_FACTURADOS as $campo) {
                $data[$campo] = $antes[$campo] ?? null;
            }
            $data['facturacion_mismos_datos'] = !empty($antes['facturacion_mismos_datos']) && $antes['facturacion_mismos_datos'] !== 'f';
        }
        $this->validarTipoPersonaFirma($idEmpresa, (int) ($data['id_producto'] ?? 0) ?: null, (string) ($data['tipo_persona'] ?? 'natural'));
        // El motivo ya no se escribe en la ficha: se conserva el que tenía o va el de por defecto.
        $data['motivo'] = self::motivoParaTipo((string) ($data['tipo_persona'] ?? 'natural'), $antes['motivo'] ?? null);
        // El pago confirmado lo deja el flujo de pago (factura + cobro): el formulario no
        // puede deshacerlo ni marcarlo a mano.
        if (($antes['estado_pago'] ?? '') === 'confirmado' && ($data['estado_pago'] ?? '') !== 'confirmado' && !empty($antes['id_ingreso'])) {
            throw new Exception('El pago de esta firma ya se registró con su cobro; no se puede cambiar desde el formulario.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            if (($antes['estado'] ?? '') !== ($data['estado'] ?? '')) {
                $this->repository->crearEvento($idEmpresa, $id, 'estado', 'Estado del trámite: ' . self::etiquetaEstado((string) $data['estado']), null, true, (int) $data['id_usuario']);
            }

            $this->logService->registrar(
                (int)$data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'firmas_electronicas',
                $id,
                $antes,
                $data
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('La firma electrónica no existe o ya ha sido eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idEmpresa, $idUsuario);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'eliminar',
                'firmas_electronicas',
                $id,
                $antes,
                ['eliminado' => true]
            );

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getEventos(int $idFirma, int $idEmpresa): array
    {
        return $this->repository->getEventos($idFirma, $idEmpresa);
    }

    /** Pestaña Trámite / Uanataca del modal: estado, historial, enlace y documentos. */
    public function getDatosTramite(int $idFirma, int $idEmpresa): array
    {
        $firma = $this->repository->getDetalleCompleto($idFirma, $idEmpresa);
        if (!$firma) {
            throw new Exception('Firma no encontrada.');
        }
        $urlPortal = null;
        if (!empty($firma['id_solicitud']) && ($sol = $this->solRepo->getById((int) $firma['id_solicitud'], $idEmpresa))) {
            $urlPortal = self::urlPortal((string) $sol['token']);
        }
        $total = null;
        if (!empty($firma['id_producto'])) {
            try {
                $total = $this->totalesProducto((int) $firma['id_producto'], $idEmpresa, $this->getConfigModulo($idEmpresa)['id_punto_emision'] ?: null)['total'];
            } catch (\Throwable) {
            }
        }
        return [
            'firma'      => $firma,
            'eventos'    => $this->repository->getEventos($idFirma, $idEmpresa),
            'adjuntos'   => $this->repository->getAdjuntos($idFirma, $idEmpresa),
            'url_portal' => $urlPortal,
            'total'      => $total,
        ];
    }

    /** Mensaje de bloqueo si la firma tiene una factura activa (borrador o autorizada). */
    public function errorFacturaActiva(int $idFirma, int $idEmpresa): ?string
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || !($idFact = $this->facturaActiva($firma))) {
            return null;
        }
        $f = $this->repository->getEstadoFactura($idFact, $idEmpresa);
        if (!in_array($f['estado'] ?? '', ['borrador', 'autorizado'], true)) {
            return null;
        }
        return 'Esta firma tiene una factura activa (estado: ' . ucfirst((string) $f['estado']) . '). Primero debe eliminar o anular la factura desde el módulo de Facturas de Venta.';
    }

    /** Adjunto subido desde el modal interno (mismas validaciones que el portal). */
    public function subirAdjuntoInterno(int $idFirma, int $idEmpresa, int $idUsuario, string $tipo, array $file): array
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma) {
            throw new Exception('Firma no encontrada o no pertenece a esta empresa.');
        }
        if (!in_array($tipo, FirmaElectronicaRules::TIPOS_ADJUNTO, true)) {
            $tipo = 'otro';
        }
        return $this->guardarAdjunto($firma, $tipo, $file, $idUsuario, $tipo !== 'comprobante_transferencia');
    }

    public function getSeriesFacturacion(int $idEmpresa): array
    {
        return $this->repository->getSeriesFacturacion($idEmpresa);
    }

    public function getFormasCobro(int $idEmpresa): array
    {
        return $this->repository->getFormasCobro($idEmpresa);
    }

    public function getUsuariosEmpresa(int $idEmpresa): array
    {
        return (new \App\repositories\modulos\EmpresaRepository())->getUsuariosAsignados($idEmpresa);
    }

    /** Productos de firma con el PVP calculado igual que la factura (IVA del servidor). */
    public function getTiposFirma(int $idEmpresa): array
    {
        $cfg   = $this->getConfigModulo($idEmpresa);
        $ajustes = $this->repository->getVigencias($idEmpresa);
        $paraPersona = $this->repository->getTiposPersonaProductos($idEmpresa);
        $tipos = [];
        foreach ($this->repository->getTiposFirma($idEmpresa) as $t) {
            // null = sirve para persona natural y para representante legal.
            $t['tipo_persona'] = $paraPersona[(int) $t['id']] ?? null;
            try {
                $tot = $this->totalesProducto((int) $t['id'], $idEmpresa, $cfg['id_punto_emision'] ?: null);
                $t['pvp'] = $tot['total'];
            } catch (\Throwable) {
                // Producto sin datos completos: se muestra con el PVP del catálogo.
            }
            $deducida = FirmaElectronicaRules::vigenciaDesdeNombre((string) $t['nombre']);
            $vig = $ajustes[(int) $t['id']] ?? $deducida;
            $t['vigencia_cantidad'] = $vig['cantidad'] ?? null;
            $t['vigencia_unidad']   = $vig['unidad'] ?? null;
            $t['vigencia_origen']   = isset($ajustes[(int) $t['id']]) ? 'ajuste' : ($deducida ? 'nombre' : null);
            $t['vigencia_deducida'] = $deducida;
            $tipos[] = $t;
        }
        return $tipos;
    }

    /** Vigencia del producto: el ajuste guardado o, si no hay, la que dice su nombre. */
    public function vigenciaProducto(int $idProducto, int $idEmpresa): ?array
    {
        $ajuste = $this->repository->getVigencias($idEmpresa)[$idProducto] ?? null;
        if ($ajuste) {
            return $ajuste;
        }
        $prod = $this->repository->getProductoFacturable($idProducto, $idEmpresa);
        return $prod ? FirmaElectronicaRules::vigenciaDesdeNombre((string) $prod['nombre']) : null;
    }

    /** Fecha de caducidad (Y-m-d) contando la vigencia desde $desde; null si no se conoce la vigencia. */
    public function caducidadEstimada(int $idProducto, int $idEmpresa, string $desde = 'today'): ?string
    {
        $vig = $idProducto > 0 ? $this->vigenciaProducto($idProducto, $idEmpresa) : null;
        return $vig ? FirmaElectronicaRules::sumarVigencia($desde, $vig['cantidad'], $vig['unidad']) : null;
    }

    /**
     * Ajuste de vigencia por producto desde la configuración.
     * @param array<int, array{cantidad?:mixed, unidad?:string}> $vigencias  cantidad vacía = usar la del nombre
     */
    public function guardarVigencias(int $idEmpresa, int $idUsuario, array $vigencias): void
    {
        $propios = array_map(static fn($t) => (int) $t['id'], $this->repository->getTiposFirma($idEmpresa));
        $antes   = $this->repository->getVigencias($idEmpresa);
        $this->repository->beginTransaction();
        try {
            foreach ($vigencias as $idProducto => $v) {
                $idProducto = (int) $idProducto;
                if (!in_array($idProducto, $propios, true)) {
                    continue;
                }
                $cant   = trim((string) ($v['cantidad'] ?? ''));
                $unidad = (string) ($v['unidad'] ?? 'anios');
                if ($cant !== '' && (!ctype_digit($cant) || (int) $cant < 1 || (int) $cant > 3650)) {
                    throw new Exception('La vigencia debe ser un número entero mayor a cero.');
                }
                if (!isset(FirmaElectronicaRules::UNIDADES_VIGENCIA[$unidad])) {
                    throw new Exception('Unidad de vigencia no válida.');
                }
                $this->repository->guardarVigencia($idEmpresa, $idProducto, $cant === '' ? null : (int) $cant, $unidad, $idUsuario);
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'vigencias_firma', 'firmas_productos_vigencia', null, $antes, $vigencias);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Para qué tipo de persona es cada firma: [id_producto => 'natural'|'juridica'|''] ('' = ambos). */
    public function guardarTiposPersona(int $idEmpresa, int $idUsuario, array $tipos): void
    {
        $propios = array_map(static fn($t) => (int) $t['id'], $this->repository->getTiposFirma($idEmpresa));
        $antes   = $this->repository->getTiposPersonaProductos($idEmpresa);
        $this->repository->beginTransaction();
        try {
            foreach ($tipos as $idProducto => $tipo) {
                $idProducto = (int) $idProducto;
                if (!in_array($idProducto, $propios, true)) {
                    continue;
                }
                $tipo = (string) $tipo;
                if ($tipo !== '' && !in_array($tipo, ['natural', 'juridica'], true)) {
                    throw new Exception('Tipo de persona no válido para la firma.');
                }
                $this->repository->guardarTipoPersonaProducto($idEmpresa, $idProducto, $tipo === '' ? null : $tipo, $idUsuario);
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'tipos_persona_firma', 'firmas_productos_tipo_persona', null, $antes, $tipos);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * La firma elegida debe servir para el tipo de persona elegido (pestaña Tipo de persona
     * de la configuración). Representante legal y miembro de empresa cuentan como empresa.
     */
    public function validarTipoPersonaFirma(int $idEmpresa, ?int $idProducto, string $tipoPersona): void
    {
        if (!$idProducto) {
            return;
        }
        $para = $this->repository->getTiposPersonaProductos($idEmpresa)[$idProducto] ?? null;
        if ($para === null) {
            return;
        }
        $esEmpresa = in_array($tipoPersona, ['juridica', 'miembro'], true);
        if ($para === 'juridica' && !$esEmpresa) {
            throw new Exception('La firma elegida es para representante legal. Cambie el tipo de persona a «Representante legal» o elija una firma para persona natural.');
        }
        if ($para === 'natural' && $esEmpresa) {
            throw new Exception('La firma elegida es para persona natural. Cambie el tipo de persona a «Persona natural» o elija una firma para representante legal.');
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // CONFIGURACIÓN DEL MÓDULO
    // ═════════════════════════════════════════════════════════════════════

    public function getConfigModulo(int $idEmpresa): array
    {
        $cfg = $this->repository->getConfig($idEmpresa) ?? [];
        $bool = static fn($v, bool $def) => $v === null ? $def : ($v === true || $v === 't' || $v === 1 || $v === '1');
        return [
            'existe'                 => !empty($cfg),
            'id_punto_emision'       => !empty($cfg['id_punto_emision']) ? (int) $cfg['id_punto_emision'] : 0,
            'id_usuario_responsable' => !empty($cfg['id_usuario_responsable']) ? (int) $cfg['id_usuario_responsable'] : 0,
            'payphone_activo'        => $bool($cfg['payphone_activo'] ?? null, true),
            'transferencia_activa'   => $bool($cfg['transferencia_activa'] ?? null, true),
            'datos_transferencia'    => (string) ($cfg['datos_transferencia'] ?? ''),
            'enlace_fijo_activo'     => $bool($cfg['enlace_fijo_activo'] ?? null, false),
            'enlace_fijo_codigo'     => trim((string) ($cfg['enlace_fijo_codigo'] ?? '')),
            'uanataca_activo'           => $bool($cfg['uanataca_activo'] ?? null, false),
            'uanataca_ambiente'         => ($cfg['uanataca_ambiente'] ?? '') === 'produccion' ? 'produccion' : 'pruebas',
            'uanataca_usuario'          => (string) ($cfg['uanataca_usuario'] ?? ''),
            'uanataca_tiene_clave'      => !empty($cfg['uanataca_clave_cifrada']),
            'uanataca_envio_automatico' => $bool($cfg['uanataca_envio_automatico'] ?? null, false),
            'uanataca_exigir_correo'    => $bool($cfg['uanataca_exigir_correo'] ?? null, true),
            'avisos_vencimiento_activo' => $bool($cfg['avisos_vencimiento_activo'] ?? null, false),
            'avisos_dias_correo'        => (string) ($cfg['avisos_dias_correo'] ?? '30,15,5,0'),
            'avisos_dias_whatsapp'      => (string) ($cfg['avisos_dias_whatsapp'] ?? '30,5,0'),
        ];
    }

    public function guardarConfigModulo(int $idEmpresa, int $idUsuario, array $d, bool $regenerarEnlace = false): array
    {
        $antes = $this->getConfigModulo($idEmpresa);

        $idPunto = (int) ($d['id_punto_emision'] ?? 0);
        if ($idPunto > 0 && !$this->repository->getSerieFacturacion($idEmpresa, $idPunto)) {
            throw new Exception('La serie elegida no está activa o no pertenece a esta empresa.');
        }
        $payphone      = !empty($d['payphone_activo']);
        $transferencia = !empty($d['transferencia_activa']);
        if (!$payphone && !$transferencia) {
            throw new Exception('Active al menos una forma de pago para el cliente (Payphone o transferencia).');
        }

        $codigo = $antes['enlace_fijo_codigo'];
        if ($codigo === '' || $regenerarEnlace) {
            $codigo = bin2hex(random_bytes(16));
        }

        $nuevo = [
            'id_punto_emision'       => $idPunto,
            'id_usuario_responsable' => (int) ($d['id_usuario_responsable'] ?? 0) ?: $idUsuario,
            'payphone_activo'        => $payphone,
            'transferencia_activa'   => $transferencia,
            'datos_transferencia'    => trim((string) ($d['datos_transferencia'] ?? '')),
            'enlace_fijo_activo'     => !empty($d['enlace_fijo_activo']),
            'enlace_fijo_codigo'     => $codigo,
            'uanataca_activo'           => !empty($d['uanataca_activo']),
            'uanataca_ambiente'         => ($d['uanataca_ambiente'] ?? '') === 'produccion' ? 'produccion' : 'pruebas',
            'uanataca_usuario'          => trim((string) ($d['uanataca_usuario'] ?? '')),
            'uanataca_envio_automatico' => !empty($d['uanataca_envio_automatico']),
            'uanataca_exigir_correo'    => !empty($d['uanataca_exigir_correo']),
            'avisos_vencimiento_activo' => array_key_exists('avisos_vencimiento_activo', $d) ? !empty($d['avisos_vencimiento_activo']) : $antes['avisos_vencimiento_activo'],
            'avisos_dias_correo'        => array_key_exists('avisos_dias_correo', $d) ? self::normalizarDiasAviso((string) $d['avisos_dias_correo']) : $antes['avisos_dias_correo'],
            'avisos_dias_whatsapp'      => array_key_exists('avisos_dias_whatsapp', $d) ? self::normalizarDiasAviso((string) $d['avisos_dias_whatsapp'], true) : $antes['avisos_dias_whatsapp'],
        ];
        if ($nuevo['avisos_vencimiento_activo'] && $nuevo['avisos_dias_correo'] === '' && $nuevo['avisos_dias_whatsapp'] === '') {
            throw new Exception('Indique en qué días antes del vencimiento se avisa al cliente (ej. 30,15,5,0).');
        }
        if ($nuevo['uanataca_activo'] && ($nuevo['uanataca_usuario'] === ''
            || (($d['uanataca_clave'] ?? '') === '' && !$antes['uanataca_tiene_clave']))) {
            throw new Exception('Para activar la API de Uanataca ingrese el usuario y la clave que le entregó Uanataca.');
        }
        // La clave no entra en $nuevo (auditoría): va aparte y cifrada.
        $guardar = $nuevo + ['uanataca_clave' => (string) ($d['uanataca_clave'] ?? '')];

        $this->repository->beginTransaction();
        try {
            $this->repository->guardarConfig($idEmpresa, $guardar, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'configurar', 'firmas_electronicas_config', null, $antes, $nuevo);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return $this->getConfigModulo($idEmpresa);
    }

    // ═════════════════════════════════════════════════════════════════════
    // ENVÍO DEL ENLACE AL CLIENTE (correo / WhatsApp)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Crea (o reutiliza, si ya es de una firma) el enlace del portal y lo envía.
     * WhatsApp: con la plantilla aprobada 'solicitud_firma' se envía por la API; si la
     * empresa no la tiene o falla, se devuelve un enlace wa.me para que el operador lo
     * envíe desde su WhatsApp.
     *
     * @return array{url:string, correo:?bool, correo_error:?string, whatsapp:?string, wa_me:?string}
     */
    public function enviarEnlace(int $idEmpresa, int $idUsuario, array $d): array
    {
        $correo   = trim((string) ($d['correo'] ?? ''));
        $celular  = preg_replace('/\s+/', '', (string) ($d['telefono'] ?? ''));
        // Celular de Ecuador tal como lo escribe el usuario: 10 dígitos (ej. 0958924835).
        if ($celular !== '' && !preg_match('/^09\d{8}$/', $celular)) {
            throw new Exception('El celular debe tener 10 dígitos numéricos y empezar con 09 (ej. 0958924835).');
        }
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido.');
        }
        $telefono = self::normalizarTelefono($celular);
        $nombre   = trim((string) ($d['nombre'] ?? ''));
        $porCorreo   = !empty($d['por_correo']);
        $porWhatsapp = !empty($d['por_whatsapp']);
        $idFirma     = (int) ($d['id_firma'] ?? 0);

        if (!$porCorreo && !$porWhatsapp) {
            throw new Exception('Elija al menos un canal: correo o WhatsApp.');
        }
        if ($porCorreo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Ingrese un correo electrónico válido para enviar por correo.');
        }
        if ($porWhatsapp && $telefono === '') {
            throw new Exception('Ingrese el celular para enviar por WhatsApp: 10 dígitos numéricos (ej. 0958924835).');
        }

        $this->repository->beginTransaction();
        try {
            if ($idFirma > 0) {
                $firma = $this->repository->findById($idFirma, $idEmpresa);
                if (!$firma) {
                    throw new Exception('Firma no encontrada.');
                }
                $sol = $this->asegurarSolicitudDeFirma($firma, $idUsuario, $correo, $telefono, $nombre);
                $nombre = $nombre !== '' ? $nombre : trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? ''));
            } else {
                $token = bin2hex(random_bytes(32));
                $idSol = $this->solRepo->crear([
                    'id_empresa'       => $idEmpresa,
                    'token'            => $token,
                    'correo_destino'   => $correo,
                    'nombre_destino'   => $nombre ?: null,
                    'telefono_destino' => $telefono,
                    'origen'           => 'operador',
                    'expira_at'        => date('Y-m-d H:i:s', strtotime('+' . self::DIAS_ENLACE . ' days')),
                    'created_by'       => $idUsuario,
                ]);
                $sol = $this->solRepo->getById($idSol, $idEmpresa);
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'enviar_enlace', 'firma_solicitudes', (int) $sol['id'], null,
                ['correo' => $porCorreo ? $correo : null, 'whatsapp' => $porWhatsapp ? $telefono : null, 'id_firma' => $idFirma ?: null]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        $url     = self::urlPortal((string) $sol['token']);
        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? ''));
        $res = ['url' => $url, 'correo' => null, 'correo_error' => null, 'whatsapp' => null, 'wa_me' => null];

        if ($porCorreo) {
            $ok = enviar_correo_solicitud_firma($correo, [
                'nombre_destino' => $nombre,
                'empresa_nombre' => $empresaNombre,
                'url_formulario' => $url,
                'expira'         => $sol['estado'] === 'pendiente' ? date('d-m-Y H:i', strtotime((string) $sol['expira_at'])) : '',
            ]);
            $res['correo'] = $ok;
            if ($ok) {
                $this->solRepo->marcarEnvio((int) $sol['id'], $idEmpresa, 'correo');
            } else {
                $res['correo_error'] = $GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo.';
            }
        }

        if ($porWhatsapp) {
            $vars = [$nombre !== '' ? $nombre : 'cliente', $empresaNombre, $url];
            if ($this->enviarWhatsappPlantilla($idEmpresa, $telefono, $vars, (string) $sol['token'])) {
                $res['whatsapp'] = 'api';
            } else {
                $texto = "Hola {$vars[0]}, {$empresaNombre} te envía el formulario para solicitar tu firma electrónica. "
                       . "Completa tus datos, sube tus documentos y realiza el pago aquí: {$url}";
                $res['whatsapp'] = 'wa_me';
                $res['wa_me']    = 'https://wa.me/' . $telefono . '?text=' . rawurlencode($texto);
            }
            $this->solRepo->marcarEnvio((int) $sol['id'], $idEmpresa, 'whatsapp');
        }

        if ($idFirma > 0) {
            $canales = array_filter([$porCorreo ? 'correo' : null, $porWhatsapp ? 'WhatsApp' : null]);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'enlace_enviado', 'Enlace enviado al cliente por ' . implode(' y ', $canales), null, false, $idUsuario);
        }
        return $res;
    }

    /** Enlace del portal de una firma (lo crea si la firma nació en el sistema). */
    public function getUrlPortalFirma(int $idFirma, int $idEmpresa, int $idUsuario): string
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma) {
            throw new Exception('Firma no encontrada.');
        }
        $this->repository->beginTransaction();
        try {
            $sol = $this->asegurarSolicitudDeFirma($firma, $idUsuario, (string) ($firma['correo'] ?? ''), (string) ($firma['telefono'] ?? ''), '');
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return self::urlPortal((string) $sol['token']);
    }

    /**
     * Enlace personal para que una empresa del sistema, cuya firma está por vencer, solicite
     * la nueva firma a la empresa que se las vende (aviso del cron, FirmaCaducidadAvisoService).
     * La solicitud queda en "Enlaces enviados" de la vendedora, a nombre de su usuario
     * responsable. null si la vendedora no tiene configurado el módulo de firmas o está inactiva.
     */
    public function crearEnlaceAvisoVencimiento(int $idVendedora, string $correo, string $nombre, string $telefono, int $idEmpresaCliente): ?string
    {
        $vendedora = (new \App\models\Empresa())->getPorId($idVendedora);
        $eliminada = in_array($vendedora['eliminado'] ?? false, [true, 't', 1, '1'], true);
        if (!$vendedora || $eliminada || (string) ($vendedora['estado'] ?? '') !== '1') {
            return null;
        }
        $cfg = $this->getConfigModulo($idVendedora);
        if (empty($cfg['existe'])) {
            return null;
        }
        $owner = (int) ($cfg['id_usuario_responsable'] ?? 0) ?: null;
        $token = bin2hex(random_bytes(32));
        $this->repository->beginTransaction();
        try {
            $idSol = $this->solRepo->crear([
                'id_empresa'       => $idVendedora,
                'token'            => $token,
                'correo_destino'   => filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '',
                'nombre_destino'   => mb_substr(trim($nombre), 0, 150) ?: null,
                'telefono_destino' => self::normalizarTelefono($telefono),
                'origen'           => 'aviso_vencimiento',
                'expira_at'        => date('Y-m-d H:i:s', strtotime('+' . self::DIAS_ENLACE . ' days')),
                'observaciones'    => 'Aviso automático: la firma de la empresa #' . $idEmpresaCliente . ' está por vencer.',
                'created_by'       => $owner,
            ]);
            $this->logService->registrar((int) $owner, $idVendedora, 'enlace_aviso_vencimiento', 'firma_solicitudes', $idSol, null,
                ['id_empresa_cliente' => $idEmpresaCliente, 'correo' => $correo]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return self::urlPortal($token);
    }

    private function asegurarSolicitudDeFirma(array $firma, int $idUsuario, string $correo, string $telefono, string $nombre): array
    {
        $idEmpresa = (int) $firma['id_empresa'];
        if (!empty($firma['id_solicitud'])) {
            $sol = $this->solRepo->getById((int) $firma['id_solicitud'], $idEmpresa);
            if ($sol) {
                return $sol;
            }
        }
        $idSol = $this->solRepo->crear([
            'id_empresa'       => $idEmpresa,
            'token'            => bin2hex(random_bytes(32)),
            'correo_destino'   => $correo !== '' ? $correo : ($firma['correo'] ?? null),
            'nombre_destino'   => $nombre !== '' ? $nombre : trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? '')),
            'telefono_destino' => $telefono,
            'origen'           => 'operador',
            'expira_at'        => date('Y-m-d H:i:s', strtotime('+' . self::DIAS_ENLACE . ' days')),
            'created_by'       => $idUsuario,
        ]);
        $this->solRepo->marcarCompletado($idSol, (int) $firma['id']);
        Database::getConnection()
            ->prepare("UPDATE firmas_electronicas SET id_solicitud = :s WHERE id = :id AND id_empresa = :ie")
            ->execute([':s' => $idSol, ':id' => (int) $firma['id'], ':ie' => $idEmpresa]);
        return $this->solRepo->getById($idSol, $idEmpresa);
    }

    /**
     * Envía el enlace con la plantilla solicitud_firma. Dos versiones aprobadas conviven:
     * la antigua lleva el enlace como {{3}} en el texto; la nueva, como botón «Completar
     * solicitud» cuya URL termina en {{1}} = token (y el texto solo {{1}} y {{2}}).
     */
    private function enviarWhatsappPlantilla(int $idEmpresa, string $telefono, array $vars, string $token): bool
    {
        try {
            $plantilla = $this->plantillaAprobada($idEmpresa, self::PLANTILLA_WA);
            if (!$plantilla) {
                return false;
            }
            // Meta rechaza el mensaje si se mandan más variables de las que tiene el cuerpo.
            $params = array_map(static fn($v) => ['type' => 'text', 'text' => (string) $v], array_slice($vars, 0, $plantilla['variables']));
            $componentes = $params ? [['type' => 'body', 'parameters' => $params]] : [];
            if ($plantilla['boton_url'] !== null) {
                $componentes[] = [
                    'type' => 'button', 'sub_type' => 'url', 'index' => (string) $plantilla['boton_url'],
                    'parameters' => [['type' => 'text', 'text' => $token]],
                ];
            }
            $resp = (new \App\services\WhatsappService())->sendTemplateMessage(
                $idEmpresa, $telefono, $plantilla['nombre'], $plantilla['idioma'], $componentes
            );
            if (empty($resp['success'])) {
                return false;
            }
            $this->guardarEnChatWhatsapp($idEmpresa, $telefono, (string) ($vars[0] ?? ''), $plantilla, $vars, $resp, 'Solicitud de firma electrónica');
            return true;
        } catch (\Throwable $e) {
            error_log('[Firmas] WhatsApp no enviado: ' . $e->getMessage());
            return false;
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // PORTAL PÚBLICO DEL CLIENTE
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Todo lo que el portal necesita para pintarse. null si el token no existe o la
     * empresa está inactiva (mismo mensaje en ambos casos, CLAUDE.md §6).
     */
    public function resolverPortal(string $token): ?array
    {
        if (!self::tokenValido($token)) {
            return null;
        }
        $this->solRepo->expirarVencidos();
        $sol = $this->solRepo->getByToken($token);
        if (!$sol) {
            return null;
        }
        $idEmpresa = (int) $sol['id_empresa'];
        $firma     = $this->repository->getPorSolicitud((int) $sol['id'], $idEmpresa);

        return $this->armarPortal($idEmpresa, $sol, $firma);
    }

    /** Portal del enlace fijo de la empresa (todavía sin solicitud). */
    public function resolverEnlaceFijo(string $codigo): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $codigo)) {
            return null;
        }
        $cfg = $this->repository->getConfigPorEnlace($codigo);
        if (!$cfg) {
            return null;
        }
        $sol = [
            'id' => 0, 'token' => '', 'estado' => 'pendiente', 'origen' => 'enlace_fijo',
            'id_empresa' => (int) $cfg['id_empresa'], 'empresa_nombre' => $cfg['empresa_nombre'],
            'correo_destino' => '', 'nombre_destino' => '', 'enlace_codigo' => $codigo,
        ];
        return $this->armarPortal((int) $cfg['id_empresa'], $sol, null);
    }

    private function armarPortal(int $idEmpresa, array $sol, ?array $firma): array
    {
        $cfg   = $this->getConfigModulo($idEmpresa);
        $idFirma = $firma ? (int) $firma['id'] : 0;
        $total = null;
        if ($firma && !empty($firma['id_producto'])) {
            try {
                $total = $this->totalesProducto((int) $firma['id_producto'], $idEmpresa, $cfg['id_punto_emision'] ?: null)['total'];
            } catch (\Throwable) {
            }
        }
        return [
            'solicitud'  => $sol,
            'firma'      => $firma,
            'adjuntos'   => $idFirma ? $this->repository->getAdjuntos($idFirma, $idEmpresa) : [],
            'eventos'    => $idFirma ? $this->repository->getEventos($idFirma, $idEmpresa, true) : [],
            'tiposFirma' => $this->getTiposFirma($idEmpresa),
            'requeridos' => $this->rules->adjuntosRequeridos((string) ($firma['tipo_persona'] ?? 'natural')),
            'total'      => $total,
            'pago'       => [
                'payphone'      => $cfg['payphone_activo'] && $this->payphoneDisponible($idEmpresa),
                'transferencia' => $cfg['transferencia_activa'],
                'datos'         => $cfg['datos_transferencia'],
            ],
            'editable'   => !$firma || in_array((string) $firma['estado'], FirmaElectronicaRules::ESTADOS_EDITABLES_PORTAL, true),
        ];
    }

    /**
     * Paso 1 del portal: datos del titular. Crea la firma la primera vez y la actualiza
     * mientras el trámite siga editable.
     */
    public function guardarDatosPortal(string $token, array $data): void
    {
        $sol = $this->solicitudActiva($token);
        $idEmpresa = (int) $sol['id_empresa'];

        $tipos = $this->repository->getTiposFirma($idEmpresa);
        $this->rules->validarPortal($data, array_map(static fn($t) => (int) $t['id'], $tipos));
        $this->validarTipoPersonaFirma($idEmpresa, (int) ($data['id_producto'] ?? 0) ?: null, (string) ($data['tipo_persona'] ?? 'natural'));
        $data['nombre_producto'] = $this->nombreProducto($tipos, (int) $data['id_producto']);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            // Un enlace = una firma: dos pestañas enviando a la vez no crean dos.
            $st = $db->prepare("SELECT pg_advisory_xact_lock(hashtext('firma_solicitud:' || :id))");
            $st->execute([':id' => (int) $sol['id']]);

            $firma = $this->repository->getPorSolicitud((int) $sol['id'], $idEmpresa);
            if ($firma) {
                $this->repository->lockFirma((int) $firma['id']);
                $this->exigirEditable($firma);
                $pagoEnCurso = in_array((string) $firma['estado_pago'], ['en_revision', 'confirmado'], true);
                if ($pagoEnCurso && (int) $firma['id_producto'] !== (int) $data['id_producto']) {
                    throw new Exception('Ya registró el pago de esta firma: no puede cambiar la validez elegida.');
                }
                $data['id_empresa'] = $idEmpresa;
                // El portal no pide observaciones ni motivo: se conserva lo que tenía la ficha.
                $data['observaciones'] = $firma['observaciones'] ?? null;
                $data['motivo']        = self::motivoParaTipo((string) $data['tipo_persona'], $firma['motivo'] ?? null);
                $this->repository->actualizarDatosPortal((int) $firma['id'], $idEmpresa, $data);
                // Cambió la validez elegida (o la firma no tenía fecha): se vuelve a estimar.
                if ((int) $firma['id_producto'] !== (int) $data['id_producto'] || empty($firma['fecha_caducidad'])) {
                    $estimada = $this->caducidadEstimada((int) $data['id_producto'], $idEmpresa);
                    if ($estimada) {
                        $this->repository->actualizarTramite((int) $firma['id'], $idEmpresa, ['fecha_caducidad' => $estimada], null);
                    }
                }
                $this->logService->registrar(0, $idEmpresa, 'actualizar_portal', 'firmas_electronicas', (int) $firma['id'], $firma, $data);
            } else {
                $this->crearFirmaDesdePortal($sol, $data);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Primer envío desde el enlace fijo: crea la solicitud y la firma juntas. Devuelve el token. */
    public function crearDesdeEnlaceFijo(string $codigo, array $data, string $ip): string
    {
        $portal = $this->resolverEnlaceFijo($codigo);
        if (!$portal) {
            throw new Exception('Este enlace ya no está disponible.');
        }
        $idEmpresa = (int) $portal['solicitud']['id_empresa'];
        if ($ip !== '' && $this->solRepo->contarRecientesPorIp($ip) >= self::MAX_POR_IP_HORA) {
            throw new Exception('Se registraron demasiadas solicitudes desde esta conexión. Intente de nuevo en una hora.');
        }

        $tipos = $this->repository->getTiposFirma($idEmpresa);
        $this->rules->validarPortal($data, array_map(static fn($t) => (int) $t['id'], $tipos));
        $this->validarTipoPersonaFirma($idEmpresa, (int) ($data['id_producto'] ?? 0) ?: null, (string) ($data['tipo_persona'] ?? 'natural'));
        $data['nombre_producto'] = $this->nombreProducto($tipos, (int) $data['id_producto']);

        $cfg   = $this->getConfigModulo($idEmpresa);
        $token = bin2hex(random_bytes(32));

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $idSol = $this->solRepo->crear([
                'id_empresa'       => $idEmpresa,
                'token'            => $token,
                'correo_destino'   => $data['correo'] ?? '',
                'nombre_destino'   => trim(($data['nombres'] ?? '') . ' ' . ($data['apellidos'] ?? '')),
                'telefono_destino' => self::normalizarTelefono((string) ($data['telefono'] ?? '')),
                'origen'           => 'enlace_fijo',
                'ip_origen'        => $ip !== '' ? mb_substr($ip, 0, 45) : null,
                'expira_at'        => date('Y-m-d H:i:s', strtotime('+' . self::DIAS_ENLACE . ' days')),
                'created_by'       => $cfg['id_usuario_responsable'] ?: null,
            ]);
            $sol = $this->solRepo->getById($idSol, $idEmpresa);
            $this->crearFirmaDesdePortal($sol, $data);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        return $token;
    }

    private function crearFirmaDesdePortal(array $sol, array $data): int
    {
        $idEmpresa = (int) $sol['id_empresa'];
        // Dueño de la firma: quien envió el enlace o el responsable del módulo. Así la ve
        // también un usuario sin "acceso total" (registros propios, CLAUDE.md §6).
        $owner = (int) ($sol['created_by'] ?? 0) ?: $this->getConfigModulo($idEmpresa)['id_usuario_responsable'];

        $firmaData = array_merge($data, [
            'motivo'                   => self::motivoParaTipo((string) ($data['tipo_persona'] ?? 'natural'), null),
            'id_empresa'               => $idEmpresa,
            'id_usuario'               => $owner ?: null,
            'estado'                   => 'borrador',
            'estado_pago'              => 'pendiente',
            'tipo_pago'                => null,
            // Igual que el modal interno: hoy + vigencia del tipo de firma (al emitirla se recalcula).
            'fecha_caducidad'          => $this->caducidadEstimada((int) ($data['id_producto'] ?? 0), $idEmpresa),
        ]);
        $idFirma = $this->repository->createDesdePortal($firmaData, (int) $sol['id']);
        $this->solRepo->marcarCompletado((int) $sol['id'], $idFirma);
        $this->repository->crearEvento($idEmpresa, $idFirma, 'datos', 'Datos del titular registrados', 'Falta subir los documentos.', true, null);
        $this->logService->registrar(0, $idEmpresa, 'crear_portal', 'firmas_electronicas', $idFirma, null, $firmaData);
        return $idFirma;
    }

    /** Sube (o reemplaza) un documento del cliente. */
    public function subirAdjuntoPortal(string $token, string $tipo, array $file): array
    {
        [$sol, $firma] = $this->firmaDelPortal($token);
        $this->exigirEditable($firma);
        if ($tipo === 'comprobante_transferencia' || !in_array($tipo, FirmaElectronicaRules::TIPOS_ADJUNTO, true)) {
            throw new Exception('Tipo de documento no válido.');
        }
        return $this->guardarAdjunto($firma, $tipo, $file, null, true);
    }

    public function quitarAdjuntoPortal(string $token, int $idAdjunto): void
    {
        [$sol, $firma] = $this->firmaDelPortal($token);
        $this->exigirEditable($firma);
        $idEmpresa = (int) $firma['id_empresa'];
        $adj = $this->repository->getAdjuntoPorId($idAdjunto, $idEmpresa);
        if (!$adj || (int) $adj['id_firma'] !== (int) $firma['id'] || $adj['tipo'] === 'comprobante_transferencia') {
            throw new Exception('Documento no encontrado.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->deleteAdjunto($idAdjunto, $idEmpresa, 0);
            $this->logService->registrar(0, $idEmpresa, 'eliminar_adjunto_portal', 'firmas_electronicas_adjuntos', $idAdjunto, $adj, null);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Paso 2 del portal: confirma que subió todo. Pasa a pendiente de pago (o vuelve a revisión si ya pagó). */
    public function finalizarDocumentosPortal(string $token): void
    {
        [$sol, $firma] = $this->firmaDelPortal($token);
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];

        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            $this->exigirEditable($firma);

            $tiposSubidos = array_column($this->repository->getAdjuntos($idFirma, $idEmpresa), 'tipo');
            $faltan = array_diff($this->rules->adjuntosRequeridos((string) $firma['tipo_persona']), $tiposSubidos);
            if ($faltan) {
                $nombres = array_map(static fn($t) => FirmaElectronicaRules::ETIQUETAS_ADJUNTO[$t] ?? $t, $faltan);
                throw new Exception('Falta subir: ' . implode(', ', $nombres) . '.');
            }

            $estadoAntes = (string) $firma['estado'];
            if ($estadoAntes === 'observada') {
                $nuevo = $firma['estado_pago'] === 'confirmado' ? 'por_registrar' : 'pendiente';
                $this->repository->actualizarTramite($idFirma, $idEmpresa, ['estado' => $nuevo, 'observacion_cliente' => null], null);
                $this->repository->crearEvento($idEmpresa, $idFirma, 'correccion', 'Corrección enviada por el cliente', null, true, null);
            } elseif ($estadoAntes === 'borrador') {
                $nuevo = $firma['estado_pago'] === 'confirmado' ? 'por_registrar' : 'pendiente';
                $this->repository->actualizarTramite($idFirma, $idEmpresa, ['estado' => $nuevo], null);
                $this->repository->crearEvento($idEmpresa, $idFirma, 'documentos', 'Documentos completos', $nuevo === 'pendiente' ? 'Falta el pago.' : null, true, null);
            }
            $this->logService->registrar(0, $idEmpresa, 'documentos_portal', 'firmas_electronicas', $idFirma, ['estado' => $estadoAntes], ['estado' => $nuevo ?? $estadoAntes]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Paso 3a: pago con Payphone. Devuelve la URL de la página de pago (Cajita).
     * Reutiliza un intento pendiente reciente para no abrir un cobro por cada clic.
     */
    public function iniciarPagoPayphone(string $token): string
    {
        [$sol, $firma] = $this->firmaDelPortal($token);
        $idEmpresa = (int) $firma['id_empresa'];
        $this->exigirPagable($firma);
        if (!(new \App\models\Empresa())->estaActiva($idEmpresa)) {
            throw new Exception('Este enlace ya no está disponible.');
        }

        $cfg = $this->getConfigModulo($idEmpresa);
        $ppRepo = new \App\repositories\PayphoneRepository();
        $forma  = $ppRepo->getFormaCobroPayphone($idEmpresa);
        if (!$cfg['payphone_activo'] || !$forma || !$this->payphoneDisponible($idEmpresa)) {
            throw new Exception('El pago con tarjeta no está disponible. Use la opción de transferencia.');
        }

        $pp = new \App\Services\PayphoneService($ppRepo);
        foreach ($pp->getTransaccionesPorReferencia($idEmpresa, self::MODULO_PAGO, (int) $firma['id']) as $t) {
            if (($t['estado'] ?? '') === 'pendiente' && strtotime((string) $t['created_at']) > time() - 600) {
                return url_absoluta('pago/' . $t['client_transaction_id']);
            }
        }

        $tot = $this->totalesProducto((int) $firma['id_producto'], $idEmpresa, $cfg['id_punto_emision'] ?: null);
        $res = $pp->prepararCajita($idEmpresa, [
            'monto'           => \App\Services\PayphoneService::dolaresACentavos($tot['total']),
            'email'           => filter_var((string) $firma['correo'], FILTER_VALIDATE_EMAIL) ? $firma['correo'] : null,
            'descripcion'     => 'Firma electrónica ' . trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? '')),
            'modulo'          => self::MODULO_PAGO,
            'id_referencia'   => (int) $firma['id'],
            'url_retorno'     => url_absoluta('payphone/retorno'),
            'url_cancelacion' => url_absoluta('payphone/cancelacion'),
            'url_exito'       => self::urlPortal((string) $sol['token']),
            'id_usuario'      => 0,
            'id_forma_cobro'  => (int) $forma['id'],
        ]);
        if (empty($res['ok'])) {
            throw new Exception($res['mensaje'] ?? 'No se pudo iniciar el pago con Payphone.');
        }
        $this->repository->crearEvento($idEmpresa, (int) $firma['id'], 'pago_iniciado', 'Pago con tarjeta iniciado', null, false, null);
        return url_absoluta('pago/' . $res['client_transaction_id']);
    }

    /** Paso 3b: el cliente sube el comprobante de transferencia. Queda en revisión. */
    public function subirComprobantePortal(string $token, array $file): void
    {
        [$sol, $firma] = $this->firmaDelPortal($token);
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];
        $this->exigirPagable($firma);
        if (!$this->getConfigModulo($idEmpresa)['transferencia_activa']) {
            throw new Exception('El pago por transferencia no está disponible.');
        }

        $this->guardarAdjunto($firma, 'comprobante_transferencia', $file, null, false);

        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'estado_pago' => 'en_revision', 'tipo_pago' => 'transferencia', 'pago_motivo_rechazo' => null,
            ], null);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'comprobante', 'Comprobante de pago recibido', 'Lo estamos revisando.', true, null);
            $this->logService->registrar(0, $idEmpresa, 'comprobante_portal', 'firmas_electronicas', $idFirma,
                ['estado_pago' => $firma['estado_pago']], ['estado_pago' => 'en_revision']);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Botón "Confirmo que recibí mi factura" del correo. */
    public function confirmarCorreo(string $tokenConfirmacion): ?array
    {
        if (!self::tokenValido($tokenConfirmacion)) {
            return null;
        }
        $firma = $this->repository->getPorTokenConfirmacion($tokenConfirmacion);
        if (!$firma) {
            return null;
        }
        if (empty($firma['correo_confirmado_at'])) {
            $idEmpresa = (int) $firma['id_empresa'];
            $this->repository->beginTransaction();
            try {
                $this->repository->actualizarTramite((int) $firma['id'], $idEmpresa, ['correo_confirmado_at' => date('Y-m-d H:i:s')], null);
                $this->repository->crearEvento($idEmpresa, (int) $firma['id'], 'correo_confirmado', 'Correo confirmado', (string) $firma['correo'], true, null);
                $this->logService->registrar(0, $idEmpresa, 'confirmar_correo', 'firmas_electronicas', (int) $firma['id'], null, ['correo' => $firma['correo']]);
                $this->repository->commit();
            } catch (\Throwable $e) {
                $this->repository->rollBack();
                throw $e;
            }
        }
        return $firma;
    }

    // ═════════════════════════════════════════════════════════════════════
    // PAGO → FACTURA → COBRO → SRI
    // ═════════════════════════════════════════════════════════════════════

    /** Payphone aprobó el cobro (PayphoneController::procesarAprobacion). */
    public function procesarPagoPayphone(array $trans): void
    {
        $idEmpresa = (int) $trans['id_empresa'];
        $idFirma   = (int) ($trans['id_referencia'] ?? 0);
        $firma     = $idFirma ? $this->repository->findById($idFirma, $idEmpresa) : null;
        if (!$firma) {
            error_log('[Firmas] Pago Payphone aprobado sin firma: ' . ($trans['client_transaction_id'] ?? ''));
            return;
        }
        $forma = !empty($trans['id_forma_cobro'])
            ? $this->repository->getFormaCobro((int) $trans['id_forma_cobro'], $idEmpresa)
            : null;
        $forma ??= (new \App\repositories\PayphoneRepository())->getFormaCobroPayphone($idEmpresa);
        if (!$forma) {
            throw new Exception('No hay forma de cobro Payphone activa para registrar el cobro.');
        }
        $forma['tipo'] = 'PAYPHONE';

        $this->registrarPagoYFacturar(
            $firma,
            $this->usuarioDelTramite($firma),
            $forma,
            round(((int) $trans['monto']) / 100, 2),
            (string) ($trans['authorization_code'] ?? ''),
            'payphone',
            (string) $trans['client_transaction_id']
        );
    }

    /** El operador aprueba el comprobante (o registra un pago recibido por otro medio). */
    public function aprobarPago(int $idFirma, int $idEmpresa, int $idUsuario, int $idFormaCobro, string $referencia): array
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma) {
            throw new Exception('Firma no encontrada.');
        }
        if (!in_array((string) $firma['estado_pago'], ['pendiente', 'en_revision', 'rechazado'], true)) {
            throw new Exception('El pago de esta firma ya fue registrado.');
        }
        $forma = $this->repository->getFormaCobro($idFormaCobro, $idEmpresa);
        if (!$forma) {
            throw new Exception('Elija la forma de cobro (cuenta) donde se recibió el pago.');
        }
        $cfg = $this->getConfigModulo($idEmpresa);
        $tot = $this->totalesProducto((int) $firma['id_producto'], $idEmpresa, $cfg['id_punto_emision'] ?: null);

        return $this->registrarPagoYFacturar($firma, $idUsuario, $forma, $tot['total'], $referencia, 'transferencia', null);
    }

    public function rechazarPago(int $idFirma, int $idEmpresa, int $idUsuario, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new Exception('Indique el motivo del rechazo: el cliente lo verá en su enlace.');
        }
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || $firma['estado_pago'] !== 'en_revision') {
            throw new Exception('Solo se puede rechazar un comprobante que está en revisión.');
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'estado_pago' => 'rechazado', 'pago_motivo_rechazo' => $motivo,
                'pago_revisado_by' => $idUsuario, 'pago_revisado_at' => date('Y-m-d H:i:s'),
            ], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'pago_rechazado', 'Comprobante de pago rechazado', $motivo, true, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'rechazar_pago', 'firmas_electronicas', $idFirma,
                ['estado_pago' => 'en_revision'], ['estado_pago' => 'rechazado', 'motivo' => $motivo]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $this->notificarCliente($idFirma, $idEmpresa, 'Revisamos tu comprobante de pago',
            '<p>No pudimos validar el comprobante de pago de tu firma electrónica.</p><p><b>Motivo:</b> ' . self::h($motivo) . '</p>'
            . '<p>Puedes subir otro comprobante o pagar con tarjeta desde tu enlace.</p>');
    }

    /**
     * Pago confirmado: crea la factura y su cobro (ingreso) en una sola transacción y,
     * después del commit, la envía al SRI y manda el correo de confirmación.
     * Idempotente: si la firma ya tiene cobro, no vuelve a facturar.
     */
    private function registrarPagoYFacturar(array $firma, int $idUsuario, array $forma, float $monto, string $referencia, string $tipoPago, ?string $ctid): array
    {
        if ($idUsuario <= 0) {
            throw new Exception('Configure el usuario responsable del módulo de firmas: se necesita para emitir la factura.');
        }
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) {
            $db->beginTransaction();
        }
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            if (!empty($firma['id_ingreso'])) {
                if ($managed) {
                    $db->commit();
                }
                return ['id_factura' => (int) $firma['id_factura'], 'id_ingreso' => (int) $firma['id_ingreso'], 'ya_existia' => true];
            }

            $idFactura = $this->facturaActiva($firma);
            $creoFactura = false;
            if (!$idFactura) {
                $codigoSri = \App\Services\modulos\PosVentaService::codigoSriDeterminante($forma)
                    ?? (($forma['tipo'] ?? '') === 'EFECTIVO' ? '01' : '20');
                [$idFactura, $facturaData] = $this->crearFactura($firma, $idUsuario, $codigoSri);
                $creoFactura = true;
            }
            [$idIngreso, $ingresoData] = $this->crearIngreso($idFactura, $idEmpresa, $idUsuario, $forma, $monto, $referencia, $tipoPago);
            if ($ctid) {
                (new \App\repositories\PayphoneRepository())->vincularIngreso($ctid, $idIngreso);
            }

            $nuevoEstado = in_array((string) $firma['estado'], ['borrador', 'pendiente'], true)
                ? ($firma['estado'] === 'pendiente' ? 'por_registrar' : 'borrador')
                : (string) $firma['estado'];
            $estimada = empty($firma['fecha_caducidad'])
                ? $this->caducidadEstimada((int) $firma['id_producto'], $idEmpresa)
                : null;
            $this->repository->actualizarTramite($idFirma, $idEmpresa, ($estimada ? ['fecha_caducidad' => $estimada] : []) + [
                'estado_pago' => 'confirmado', 'tipo_pago' => $tipoPago, 'estado' => $nuevoEstado,
                'id_factura' => $idFactura, 'id_ingreso' => $idIngreso, 'pago_motivo_rechazo' => null,
                'pago_revisado_by' => $tipoPago === 'payphone' ? null : $idUsuario, 'pago_revisado_at' => date('Y-m-d H:i:s'),
            ], $tipoPago === 'payphone' ? null : $idUsuario);

            $numFact = $this->numeroFactura($idFactura, $idEmpresa);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'pago_confirmado',
                $tipoPago === 'payphone' ? 'Pago con tarjeta aprobado' : 'Pago confirmado',
                '$' . number_format($monto, 2) . ($referencia !== '' ? ' · ref. ' . $referencia : ''), true, $tipoPago === 'payphone' ? null : $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'factura', 'Factura ' . $numFact . ' generada', null, true, null);
            $this->logService->registrar($idUsuario, $idEmpresa, 'pago_confirmado', 'firmas_electronicas', $idFirma,
                ['estado_pago' => $firma['estado_pago'], 'estado' => $firma['estado']],
                ['estado_pago' => 'confirmado', 'estado' => $nuevoEstado, 'id_factura' => $idFactura, 'id_ingreso' => $idIngreso, 'forma' => $forma['nombre'] ?? '', 'referencia' => $referencia]);

            if ($managed) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($managed) {
            // Lo que FacturaVentaService/IngresoService hacen tras SU commit cuando la
            // transacción es nuestra.
            $factService = $this->facturaService();
            if ($creoFactura) {
                try {
                    $factService->generarYGuardarXml($idFactura, $facturaData['empresa_config'] ?? []);
                } catch (\Throwable $e) {
                    error_log('[Firmas] XML de la factura #' . $idFactura . ': ' . $e->getMessage());
                }
            }
            try {
                $this->ingresoService()->tareasPostCommit($idIngreso, $ingresoData);
            } catch (\Throwable $e) {
                error_log('[Firmas] Asiento del ingreso #' . $idIngreso . ': ' . $e->getMessage());
            }
            $this->emitirFacturaYConfirmar($idFirma, $idEmpresa);
        }

        return ['id_factura' => $idFactura, 'id_ingreso' => $idIngreso, 'ya_existia' => false];
    }

    /**
     * Factura en borrador desde el botón del modal (sin cobro). La crea igual que el flujo
     * de pago: serie de la configuración, IVA del Service.
     */
    public function generarFacturaBorrador(int $idFirma, int $idEmpresa, int $idUsuario): array
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            if (!$firma) {
                throw new Exception('Firma no encontrada.');
            }
            if ($this->facturaActiva($firma)) {
                throw new Exception('Ya existe una factura activa para esta firma. No se puede generar otra.');
            }
            [$idFactura, $facturaData] = $this->crearFactura($firma, $idUsuario, '01');
            $this->repository->actualizarTramite($idFirma, $idEmpresa, ['id_factura' => $idFactura], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'factura', 'Factura ' . $this->numeroFactura($idFactura, $idEmpresa) . ' generada (borrador)', null, false, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'generar_factura', 'firmas_electronicas', $idFirma, null, ['id_factura' => $idFactura]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        try {
            $this->facturaService()->generarYGuardarXml($idFactura, $facturaData['empresa_config'] ?? []);
        } catch (\Throwable $e) {
            error_log('[Firmas] XML de la factura #' . $idFactura . ': ' . $e->getMessage());
        }
        $f = $this->facturaService()->getPorId($idFactura, $idEmpresa) ?? [];
        return [
            'id_factura'              => $idFactura,
            'factura_estado'          => $f['estado'] ?? 'borrador',
            'factura_establecimiento' => $f['establecimiento'] ?? '',
            'factura_punto_emision'   => $f['punto_emision'] ?? '',
            'factura_secuencial'      => $f['secuencial'] ?? '',
            'factura_importe_total'   => (float) ($f['importe_total'] ?? 0),
        ];
    }

    /** Envía la factura al SRI (si sigue en borrador) y, autorizada, el correo de confirmación. */
    public function emitirFacturaYConfirmar(int $idFirma, int $idEmpresa): void
    {
        try {
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            if (!$firma || empty($firma['id_factura'])) {
                return;
            }
            $fact = $this->repository->getEstadoFactura((int) $firma['id_factura'], $idEmpresa);
            if (!$fact || $fact['eliminado'] === true || $fact['eliminado'] === 't') {
                return;
            }
            if ($fact['estado'] === 'borrador') {
                // Tiempos cortos: el cliente espera en la página de retorno del pago. Si el
                // SRI no responde a tiempo, los reintentos del cron la terminan y el correo
                // de confirmación sale desde procesarCorreosPendientes().
                $sri = new \App\Services\Sri\SriEnvioService(2, 3, 2);
                $res = $sri->enviarFacturaVenta((int) $firma['id_factura'], $idEmpresa, $this->usuarioDelTramite($firma));
                if (($res['estado'] ?? '') === 'autorizado') {
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'factura_autorizada', 'Factura autorizada por el SRI', null, true, null);
                } elseif (!empty($res['mensaje'])) {
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'factura_sri', 'Factura enviada al SRI: ' . ($res['estado'] ?? ''), (string) $res['mensaje'], false, null);
                }
                $fact = $this->repository->getEstadoFactura((int) $firma['id_factura'], $idEmpresa);
            }
            if (($fact['estado'] ?? '') === 'autorizado' && empty($firma['correo_confirmacion_enviado_at'])) {
                $this->enviarCorreoConfirmacion($idFirma, $idEmpresa);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
        }
    }

    /**
     * Correo con la factura (PDF + XML) y el botón para confirmar el correo.
     * Devuelve false si la factura aún no está autorizada o el correo no salió.
     */
    public function enviarCorreoConfirmacion(int $idFirma, int $idEmpresa, ?int $idUsuario = null): bool
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || empty($firma['id_factura'])) {
            throw new Exception('La firma todavía no tiene factura.');
        }
        $correo = trim((string) $firma['correo']);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo del titular no es válido.');
        }
        $f = $this->facturaService()->getPorId((int) $firma['id_factura'], $idEmpresa);
        if (!$f || ($f['estado'] ?? '') !== 'autorizado') {
            throw new Exception('La factura todavía no está autorizada por el SRI.');
        }

        $token = (string) ($firma['correo_confirmacion_token'] ?? '');
        if (!self::tokenValido($token)) {
            $token = bin2hex(random_bytes(32));
        }

        $empresa = $this->empresaPdf($idEmpresa, isset($f['id_punto_emision']) ? (int) $f['id_punto_emision'] : null);
        $renderer  = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');
        $pdf = $plantilla
            ? $renderer->generar($plantilla, $f, $f['detalles'], $f['pagos'], $f['info_adicional'], $empresa, 'S')
            : (new FacturaVentaPdfService())->generar($f, $f['detalles'], $f['pagos'], $f['info_adicional'], $empresa, 'S');

        $numero  = $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . $f['secuencial'];
        $nomEmp  = (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? ''));
        $nombre  = trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? ''));
        $urlConf = url_absoluta('solicitud-firma/confirmar-correo/' . $token);
        $cuerpo = '<p>Hola ' . self::h($nombre) . ':</p>'
            . '<p>Recibimos el pago de tu <b>firma electrónica</b>. Adjuntamos tu factura <b>' . self::h($numero) . '</b> por <b>$'
            . number_format((float) $f['importe_total'], 2) . '</b>.</p>'
            . '<p>A este mismo correo te llegará la activación de tu firma, por eso necesitamos que confirmes que es tuyo:</p>'
            . '<p style="text-align:center;margin:24px 0;"><a href="' . self::h($urlConf) . '" '
            . 'style="background:#16a34a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">'
            . 'Confirmo que recibí mi factura</a></p>'
            . '<p style="font-size:12px;color:#64748b;">Si el botón no funciona, copia este enlace en tu navegador:<br>' . self::h($urlConf) . '</p>'
            . '<p>Saludos cordiales,<br>' . self::h($nomEmp) . '</p>';

        $xml = (string) ($f['detalle_xml'] ?? '');
        $extra = $xml !== '' ? [['contenido' => $xml, 'nombre' => 'Factura_' . $numero . '.xml']] : [];
        $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarPdfSimple(
            $idEmpresa, $correo, $nombre, 'Tu factura y confirmación de correo - ' . $nomEmp, $cuerpo, (string) $pdf, 'Factura_' . $numero, $nomEmp, $extra
        );
        if (!$ok) {
            return false;
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'correo_confirmacion_token' => $token, 'correo_confirmacion_enviado_at' => date('Y-m-d H:i:s'),
            ], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'correo_enviado', 'Factura enviada a ' . $correo,
                'Confirma tu correo con el botón del mensaje.', true, $idUsuario);
            $this->logService->registrar($idUsuario ?? 0, $idEmpresa, 'enviar_confirmacion_correo', 'firmas_electronicas', $idFirma, null, ['correo' => $correo]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        return true;
    }

    /** El operador marca el correo como confirmado (el cliente lo confirmó por otro medio). */
    public function marcarCorreoConfirmado(int $idFirma, int $idEmpresa, int $idUsuario): void
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma) {
            throw new Exception('Firma no encontrada.');
        }
        if (!empty($firma['correo_confirmado_at'])) {
            return;
        }
        $this->repository->beginTransaction();
        try {
            $this->repository->actualizarTramite($idFirma, $idEmpresa, ['correo_confirmado_at' => date('Y-m-d H:i:s')], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'correo_confirmado', 'Correo confirmado (registrado por la empresa)', (string) $firma['correo'], true, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'confirmar_correo_manual', 'firmas_electronicas', $idFirma, null, ['correo' => $firma['correo']]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Cron: reintenta autorizar las facturas de firmas pagadas que quedaron en borrador y
     * envía el correo de confirmación de las que el SRI ya autorizó.
     */
    public function procesarCorreosPendientes(): array
    {
        foreach ($this->repository->getPendientesEmitir() as $p) {
            $this->emitirFacturaYConfirmar((int) $p['id'], (int) $p['id_empresa']);
        }
        $enviados = 0;
        $fallidos = 0;
        foreach ($this->repository->getPendientesCorreoConfirmacion() as $p) {
            try {
                $this->enviarCorreoConfirmacion((int) $p['id'], (int) $p['id_empresa']) ? $enviados++ : $fallidos++;
            } catch (\Throwable $e) {
                $fallidos++;
                error_log('[Firmas] Correo de confirmación de la firma #' . $p['id'] . ': ' . $e->getMessage());
            }
        }
        return ['enviados' => $enviados, 'fallidos' => $fallidos];
    }

    // ═════════════════════════════════════════════════════════════════════
    // TRÁMITE EN UANATACA (manual)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Avanza el trámite: registrar_uanataca | emitida | observar | cancelar | nota.
     * Cada acción deja evento, auditoría y, si corresponde, avisa al cliente por correo.
     */
    public function accionTramite(int $idFirma, int $idEmpresa, int $idUsuario, string $accion, array $d): void
    {
        $nota = trim((string) ($d['nota'] ?? ''));
        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            if (!$firma) {
                throw new Exception('Firma no encontrada.');
            }
            $estado = (string) $firma['estado'];
            $aviso  = null;

            switch ($accion) {
                case 'registrar_uanataca':
                    if (!in_array($estado, ['por_registrar', 'en_proceso'], true)) {
                        throw new Exception('Solo se registra en Uanataca una firma pagada (por registrar).');
                    }
                    $ref = trim((string) ($d['referencia'] ?? ''));
                    $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                        'estado' => 'en_proceso', 'uanataca_referencia' => $ref !== '' ? mb_substr($ref, 0, 100) : null,
                        'uanataca_registrada_at' => date('Y-m-d H:i:s'),
                    ], $idUsuario);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca', 'Solicitud ingresada en la entidad certificadora',
                        'Te llegará un correo de Uanataca para validar tu identidad y activar la firma.', true, $idUsuario);
                    if ($ref !== '') {
                        $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca_ref', 'Referencia Uanataca: ' . $ref, null, false, $idUsuario);
                    }
                    $aviso = ['Tu solicitud de firma está en trámite', '<p>Ingresamos tu solicitud de firma electrónica en la entidad certificadora (Uanataca).</p><p>En breve recibirás un correo de Uanataca para validar tu identidad y activar tu firma.</p>'];
                    break;

                case 'emitida':
                    if (!in_array($estado, ['en_proceso', 'aprobada', 'por_registrar'], true)) {
                        throw new Exception('Solo se marca como emitida una firma en trámite.');
                    }
                    $cad = trim((string) ($d['fecha_caducidad'] ?? ''));
                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $cad, $m)) {
                        $cad = "{$m[3]}-{$m[2]}-{$m[1]}";
                    }
                    if ($cad === '') {
                        // La vigencia corre desde la emisión (hoy).
                        $cad = (string) $this->caducidadEstimada((int) $firma['id_producto'], $idEmpresa);
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cad) || strtotime($cad) <= time()) {
                        throw new Exception('Indique la fecha de vencimiento de la firma emitida (futura).');
                    }
                    $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                        'estado' => 'emitida', 'fecha_caducidad' => $cad, 'emitida_at' => date('Y-m-d H:i:s'),
                    ], $idUsuario);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'emitida', 'Firma electrónica emitida',
                        'Vigente hasta el ' . date('d-m-Y', strtotime($cad)) . '.', true, $idUsuario);
                    $aviso = ['Tu firma electrónica fue emitida', '<p>¡Listo! Tu firma electrónica fue emitida y está vigente hasta el <b>' . date('d-m-Y', strtotime($cad)) . '</b>.</p>'];
                    break;

                case 'observar':
                    // Aprobada: la solicitud ya no se edita, el cliente no puede corregirla.
                    if (in_array($estado, ['aprobada', 'emitida', 'cancelada', 'borrador'], true)) {
                        throw new Exception('Esta firma no se puede observar en su estado actual.');
                    }
                    if ($nota === '') {
                        throw new Exception('Indique qué debe corregir el cliente.');
                    }
                    $this->repository->actualizarTramite($idFirma, $idEmpresa, ['estado' => 'observada', 'observacion_cliente' => $nota], $idUsuario);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'observada', 'Debes corregir tu solicitud', $nota, true, $idUsuario);
                    $aviso = ['Tu solicitud de firma necesita una corrección', '<p>Revisamos tu solicitud de firma electrónica y necesitamos que corrijas lo siguiente:</p><p><b>' . nl2br(self::h($nota)) . '</b></p><p>Ingresa a tu enlace, corrige y vuelve a enviarla.</p>'];
                    break;

                case 'cancelar':
                    if ($estado === 'emitida') {
                        throw new Exception('Una firma emitida no se puede cancelar.');
                    }
                    if ($nota === '') {
                        throw new Exception('Indique el motivo de la cancelación.');
                    }
                    $this->repository->actualizarTramite($idFirma, $idEmpresa, ['estado' => 'cancelada'], $idUsuario);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'cancelada', 'Solicitud cancelada', $nota, true, $idUsuario);
                    break;

                case 'nota':
                    if ($nota === '') {
                        throw new Exception('Escriba la nota.');
                    }
                    $visible = !empty($d['visible_cliente']);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'nota', $visible ? 'Mensaje de la empresa' : 'Nota interna', $nota, $visible, $idUsuario);
                    if ($visible) {
                        $aviso = ['Novedades de tu solicitud de firma', '<p>' . nl2br(self::h($nota)) . '</p>'];
                    }
                    break;

                default:
                    throw new Exception('Acción no válida.');
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'tramite_' . $accion, 'firmas_electronicas', $idFirma, ['estado' => $estado], array_merge(['accion' => $accion], $d));
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        if ($aviso) {
            $this->notificarCliente($idFirma, $idEmpresa, $aviso[0], $aviso[1]);
        }
    }

    /** Correo al titular con el mensaje y el botón a su enlace (seguimiento del trámite). */
    private function notificarCliente(int $idFirma, int $idEmpresa, string $asunto, string $html): void
    {
        try {
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            $correo = trim((string) ($firma['correo'] ?? ''));
            if (!$firma || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $empresa = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            $nomEmp  = (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? ''));
            $nombre  = trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? ''));
            $boton   = '';
            if (!empty($firma['id_solicitud']) && ($sol = $this->solRepo->getById((int) $firma['id_solicitud'], $idEmpresa))) {
                $boton = '<p style="text-align:center;margin:24px 0;"><a href="' . self::h(self::urlPortal((string) $sol['token'])) . '" '
                       . 'style="background:#2563eb;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">Ver mi solicitud</a></p>';
            }
            (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple(
                $idEmpresa, $correo, $nombre, $asunto . ' - ' . $nomEmp,
                '<p>Hola ' . self::h($nombre) . ':</p>' . $html . $boton . '<p>Saludos cordiales,<br>' . self::h($nomEmp) . '</p>',
                $nomEmp
            );
        } catch (\Throwable $e) {
            error_log('[Firmas] Aviso al cliente no enviado (firma #' . $idFirma . '): ' . $e->getMessage());
        }
    }

    // ═════════════════════════════════════════════════════════════════════
    // AVISOS DE VENCIMIENTO AL CLIENTE
    // ═════════════════════════════════════════════════════════════════════

    private const PLANTILLA_VENCIMIENTO = 'renovacion_firma_electronica';

    /** "30, 15,5,0,x" → "30,15,5,0" (enteros 0-365, sin repetir, de mayor a menor). */
    public static function normalizarDiasAviso(string $lista, bool $permitirVacio = false): string
    {
        $dias = [];
        foreach (preg_split('/[\s,;]+/', trim($lista)) as $d) {
            if ($d === '') {
                continue;
            }
            if (!ctype_digit($d) || (int) $d > 365) {
                throw new Exception('Los días de aviso deben ser números de 0 a 365 separados por coma (ej. 30,15,5,0).');
            }
            $dias[(int) $d] = (int) $d;
        }
        if (!$dias && !$permitirVacio) {
            return '';
        }
        rsort($dias);
        return implode(',', $dias);
    }

    /**
     * Etapa de aviso que corresponde a los días que faltan: el menor umbral que todavía
     * los cubre. Con 30,15,5,0 y 12 días restantes → 15. Así un día sin cron no manda
     * avisos atrasados: solo el de la etapa actual.
     */
    public static function etapaAviso(int $restantes, string $lista): ?int
    {
        $dias = array_map('intval', array_filter(explode(',', $lista), 'strlen'));
        sort($dias);
        foreach ($dias as $t) {
            if ($restantes <= $t) {
                return $t;
            }
        }
        return null;
    }

    /** Cron (una vez al día): avisos de las firmas por vencer de todas las empresas con avisos activos. */
    public function procesarAvisosVencimiento(): array
    {
        $res = ['correos' => 0, 'whatsapp' => 0, 'fallidos' => 0];
        foreach ($this->repository->getEmpresasConAvisos() as $cfg) {
            $idEmpresa = (int) $cfg['id_empresa'];
            $listaCorreo = (string) $cfg['avisos_dias_correo'];
            $listaWa     = (string) $cfg['avisos_dias_whatsapp'];
            $max = max(array_map('intval', array_filter(explode(',', $listaCorreo . ',' . $listaWa), 'strlen')) ?: [0]);
            foreach ($this->repository->getFirmasPorVencer($idEmpresa, $max) as $firma) {
                if (in_array($firma['renovada'], [true, 't', 1, '1'], true)) {
                    continue;
                }
                $restantes = (int) $firma['dias_restantes'];
                $canales = [];
                if (($e = self::etapaAviso($restantes, $listaCorreo)) !== null) {
                    $canales['correo'] = $e;
                }
                if (($e = self::etapaAviso($restantes, $listaWa)) !== null) {
                    $canales['whatsapp'] = $e;
                }
                if (!$canales) {
                    continue;
                }
                try {
                    foreach ($this->enviarAvisoVencimiento($firma, $canales, false, null) as $canal => $ok) {
                        $ok === true ? $res[$canal === 'correo' ? 'correos' : 'whatsapp']++ : ($ok === false ? $res['fallidos']++ : null);
                    }
                } catch (\Throwable $e) {
                    $res['fallidos']++;
                    error_log('[Firmas] Aviso de vencimiento firma #' . $firma['id'] . ': ' . $e->getMessage());
                }
            }
        }
        return $res;
    }

    /** "Enviar aviso ahora" desde la ficha: correo y WhatsApp, sin importar la etapa. */
    public function avisoVencimientoManual(int $idFirma, int $idEmpresa, int $idUsuario): string
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || $firma['estado'] !== 'emitida' || empty($firma['fecha_caducidad'])) {
            throw new Exception('Solo se avisa el vencimiento de una firma emitida con fecha de vencimiento.');
        }
        $restantes = (int) floor((strtotime((string) $firma['fecha_caducidad']) - strtotime('today')) / 86400);
        $r = $this->enviarAvisoVencimiento($firma, ['correo' => $restantes, 'whatsapp' => $restantes], true, $idUsuario);
        $partes = [];
        $partes[] = $r['correo'] === true ? 'Correo enviado.' : ($r['correo'] === false ? 'El correo no se pudo enviar.' : 'Sin correo válido.');
        $partes[] = $r['whatsapp'] === true ? 'WhatsApp enviado.' : ($r['whatsapp'] === false ? 'El WhatsApp no se pudo enviar.' : 'WhatsApp no disponible (plantilla no aprobada o sin celular).');
        if ($r['correo'] !== true && $r['whatsapp'] !== true) {
            throw new Exception(implode(' ', $partes));
        }
        return implode(' ', $partes);
    }

    /**
     * Envía el aviso por los canales indicados (canal => etapa en días).
     * @return array<string, ?bool> canal => true enviado | false falló | null no aplica / ya enviado
     */
    private function enviarAvisoVencimiento(array $firma, array $canales, bool $manual, ?int $idUsuario): array
    {
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];
        $caducidad = date('Y-m-d', strtotime((string) $firma['fecha_caducidad']));
        $restantes = (int) floor((strtotime($caducidad) - strtotime('today')) / 86400);
        $fechaTxt  = date('d-m-Y', strtotime($caducidad));
        $nombre    = trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? ''));
        $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
        $nomEmp    = (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? ''));
        $out = ['correo' => null, 'whatsapp' => null];
        $url = null;
        $urlRenovacion = function () use (&$url, $firma, $idUsuario): string {
            return $url ??= $this->enlaceRenovacion($firma, $idUsuario);
        };

        // Correo
        $correo = trim((string) ($firma['correo'] ?? ''));
        if (isset($canales['correo']) && filter_var($correo, FILTER_VALIDATE_EMAIL)
            && ($idAviso = $this->repository->reservarAviso($idEmpresa, $idFirma, $caducidad, (int) $canales['correo'], 'correo', $manual, $correo, $idUsuario))) {
            try {
                $cuando = $restantes <= 0 ? '<b>vence hoy</b>' : '<b>vence el ' . $fechaTxt . '</b>, en <b>' . $restantes . ' día' . ($restantes === 1 ? '' : 's') . '</b>';
                $cuerpo = '<p>Hola ' . self::h($nombre) . ':</p>'
                    . '<p>Te recordamos que tu <b>firma electrónica</b>' . (!empty($firma['nombre_producto']) ? ' (' . self::h((string) $firma['nombre_producto']) . ')' : '') . ' ' . $cuando . '.</p>'
                    . '<p>Cuando vence, ya no podrás firmar documentos ni emitir facturas electrónicas. La renovación toma unos días, por eso te recomendamos hacerla con anticipación.</p>'
                    . '<p style="text-align:center;margin:24px 0;"><a href="' . self::h($urlRenovacion()) . '" '
                    . 'style="background:#16a34a;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:600;">Renovar mi firma</a></p>'
                    . '<p>Saludos cordiales,<br>' . self::h($nomEmp) . '</p>';
                $asunto = 'Tu firma electrónica vence ' . ($restantes <= 0 ? 'hoy' : 'el ' . $fechaTxt) . ' - ' . $nomEmp;
                $ok = (new \App\Services\EnvioDocumentosSRIService())->enviarAvisoSimple($idEmpresa, $correo, $nombre, $asunto, $cuerpo, $nomEmp);
                $this->repository->resultadoAviso($idAviso, $ok, $ok ? null : 'No se pudo enviar el correo.');
                $out['correo'] = $ok;
            } catch (\Throwable $e) {
                $this->repository->resultadoAviso($idAviso, false, $e->getMessage());
                $out['correo'] = false;
            }
        }

        // WhatsApp (solo con la plantilla aprobada: Meta no permite mensajes libres automáticos)
        $telefono = self::normalizarTelefono((string) ($firma['telefono'] ?? ''));
        $plantilla = isset($canales['whatsapp']) && $telefono !== '' ? $this->plantillaAprobada($idEmpresa, self::PLANTILLA_VENCIMIENTO) : null;
        if ($plantilla
            && ($idAviso = $this->repository->reservarAviso($idEmpresa, $idFirma, $caducidad, (int) $canales['whatsapp'], 'whatsapp', $manual, $telefono, $idUsuario))) {
            try {
                // La versión de la plantilla se reconoce por cuántas variables tiene: con 3 va el enlace de renovación.
                $vars = [$nombre !== '' ? $nombre : 'cliente', $restantes <= 0 ? 'hoy (' . $fechaTxt . ')' : $fechaTxt];
                if ($plantilla['variables'] >= 3) {
                    $vars[] = $urlRenovacion();
                }
                $params = array_map(static fn($v) => ['type' => 'text', 'text' => (string) $v], $vars);
                $resp = (new \App\services\WhatsappService())->sendTemplateMessage(
                    $idEmpresa, $telefono, $plantilla['nombre'], $plantilla['idioma'], [['type' => 'body', 'parameters' => $params]]
                );
                $ok = !empty($resp['success']);
                $this->repository->resultadoAviso($idAviso, $ok, $ok ? null : (string) ($resp['message'] ?? 'Error de WhatsApp.'));
                if ($ok) {
                    $this->guardarEnChatWhatsapp($idEmpresa, $telefono, $nombre, $plantilla, $vars, $resp, 'Aviso de vencimiento de firma electrónica');
                }
                $out['whatsapp'] = $ok;
            } catch (\Throwable $e) {
                $this->repository->resultadoAviso($idAviso, false, $e->getMessage());
                $out['whatsapp'] = false;
            }
        }

        $enviados = array_keys(array_filter($out, static fn($v) => $v === true));
        if ($enviados) {
            $this->repository->crearEvento($idEmpresa, $idFirma, 'aviso_vencimiento',
                'Aviso de vencimiento enviado' . ($manual ? ' (manual)' : ''),
                'Por ' . implode(' y ', array_map(static fn($c) => $c === 'correo' ? 'correo' : 'WhatsApp', $enviados))
                . ($restantes <= 0 ? '; vence hoy.' : "; faltan {$restantes} días."), false, $idUsuario);
            $this->logService->registrar((int) $idUsuario, $idEmpresa, 'aviso_vencimiento', 'firmas_electronicas', $idFirma, null,
                ['canales' => $enviados, 'dias' => $restantes, 'manual' => $manual]);
        }
        return $out;
    }

    /** Plantilla aprobada por nombre, con el número de variables de su cuerpo. */
    private function plantillaAprobada(int $idEmpresa, string $nombre): ?array
    {
        try {
            foreach ((new \App\models\WhatsappPlantilla())->getPlantillasAprobadas($idEmpresa) as $p) {
                if (($p['nombre'] ?? '') !== $nombre) {
                    continue;
                }
                $vars = 0;
                $botonUrl = null;
                $texto = '';
                foreach (json_decode((string) ($p['componentes'] ?? '[]'), true) ?: [] as $c) {
                    $tipo = strtoupper((string) ($c['type'] ?? ''));
                    if ($tipo === 'BODY') {
                        $texto = (string) ($c['text'] ?? '');
                        if (preg_match_all('/{{(\d+)}}/', $texto, $m)) {
                            $vars = count(array_unique($m[1]));
                        }
                    }
                    // Botón de URL dinámica (…/solicitud-firma/{{1}}): Meta pide el sufijo al enviar.
                    if ($tipo === 'BUTTONS') {
                        foreach (array_values($c['buttons'] ?? []) as $i => $b) {
                            if (strtoupper((string) ($b['type'] ?? '')) === 'URL' && str_contains((string) ($b['url'] ?? ''), '{{1}}')) {
                                $botonUrl = $i;
                                break;
                            }
                        }
                    }
                }
                return ['nombre' => $p['nombre'], 'idioma' => $p['idioma'], 'variables' => $vars, 'boton_url' => $botonUrl, 'texto' => $texto];
            }
        } catch (\Throwable $e) {
            error_log('[Firmas] Plantillas WhatsApp: ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Deja el mensaje enviado en el chat de WhatsApp de la empresa (bandeja + webhook de
     * estados), igual que Cuentas por Cobrar. Un fallo aquí no anula el envío ya hecho.
     */
    private function guardarEnChatWhatsapp(int $idEmpresa, string $telefono, string $nombre, array $plantilla, array $vars, array $resp, string $resumen): void
    {
        try {
            $vars  = array_map('strval', array_slice($vars, 0, $plantilla['variables']));
            $texto = (string) ($plantilla['texto'] ?? '');
            foreach ($vars as $i => $v) {
                $texto = str_replace('{{' . ($i + 1) . '}}', $v, $texto);
            }
            $repo   = new \App\repositories\modulos\WhatsappMensajeRepository();
            $idChat = $repo->getOrCreateChat($idEmpresa, $telefono, $nombre !== '' ? $nombre : 'Cliente', $resumen, false);
            $repo->saveMessage($idEmpresa, $idChat, 'OUT', $telefono, 'template', [
                'template'      => $plantilla['nombre'],
                'variables'     => $vars,
                'template_text' => $texto,
            ], $resp['data']['messages'][0]['id'] ?? null, 'sent');
        } catch (\Throwable $e) {
            error_log('[Firmas] Mensaje no guardado en el chat de WhatsApp: ' . $e->getMessage());
        }
    }

    /**
     * Enlace personal de renovación: una solicitud nueva con los datos del titular ya
     * llenos (el cliente vuelve a subir sus documentos y paga). Se crea una vez y se
     * reutiliza en los avisos siguientes mientras siga en curso.
     */
    public function enlaceRenovacion(array $firma, ?int $idUsuario = null): string
    {
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) {
            $db->beginTransaction();
        }
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa) ?? $firma;
            if (!empty($firma['id_firma_renovacion'])) {
                $ren = $this->repository->findById((int) $firma['id_firma_renovacion'], $idEmpresa);
                if ($ren && $ren['estado'] !== 'cancelada' && !empty($ren['id_solicitud'])
                    && ($sol = $this->solRepo->getById((int) $ren['id_solicitud'], $idEmpresa))) {
                    if ($managed) {
                        $db->commit();
                    }
                    return self::urlPortal((string) $sol['token']);
                }
            }

            $owner = (int) ($firma['created_by'] ?? 0) ?: $this->getConfigModulo($idEmpresa)['id_usuario_responsable'];
            $token = bin2hex(random_bytes(32));
            $idSol = $this->solRepo->crear([
                'id_empresa'       => $idEmpresa,
                'token'            => $token,
                'correo_destino'   => $firma['correo'] ?? '',
                'nombre_destino'   => trim(($firma['nombres'] ?? '') . ' ' . ($firma['apellidos'] ?? '')),
                'telefono_destino' => self::normalizarTelefono((string) ($firma['telefono'] ?? '')),
                'origen'           => 'renovacion',
                'expira_at'        => date('Y-m-d H:i:s', strtotime('+60 days')),
                'created_by'       => $owner ?: null,
            ]);
            // Copia de los datos del titular; documentos, pago, factura y trámite empiezan de cero.
            $copia = array_intersect_key($firma, array_flip([
                'id_producto', 'nombre_producto', 'tipo_persona', 'con_ruc', 'ruc_empresa', 'nombre_empresa', 'cargo',
                'tipo_identificacion', 'numero_identificacion', 'codigo_dactilar', 'nombres', 'apellidos', 'fecha_nacimiento',
                'telefono', 'correo', 'nacionalidad', 'sexo', 'direccion', 'cod_prov', 'cod_ciudad',
                'apellido_paterno', 'apellido_materno', 'telefono2', 'correo2', 'departamento', 'motivo',
                'rep_tipo_identificacion', 'rep_identificacion', 'rep_nombres', 'rep_apellidos',
                'facturacion_mismos_datos', 'facturacion_tipo_id', 'facturacion_num_id', 'facturacion_nombres',
                'facturacion_direccion', 'facturacion_correo', 'facturacion_telefono',
            ]));
            $copia['con_ruc'] = in_array($copia['con_ruc'] ?? false, [true, 't', 1, '1'], true);
            $copia['facturacion_mismos_datos'] = !in_array($copia['facturacion_mismos_datos'] ?? true, [false, 'f', 0, '0'], true);
            $idNueva = $this->repository->create($copia + [
                'id_empresa' => $idEmpresa, 'id_usuario' => $owner ?: null,
                'estado' => 'borrador', 'estado_pago' => 'pendiente', 'tipo_pago' => null, 'observaciones' => null,
                'fecha_caducidad' => $this->caducidadEstimada((int) ($copia['id_producto'] ?? 0), $idEmpresa),
            ]);
            $this->repository->marcarRenovacion($idNueva, $idFirma, $idSol);
            $this->solRepo->marcarCompletado($idSol, $idNueva);
            $this->repository->actualizarTramite($idFirma, $idEmpresa, ['id_firma_renovacion' => $idNueva], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idNueva, 'renovacion', 'Renovación de tu firma electrónica',
                'Revisa tus datos y sube nuevamente tus documentos.', true, $idUsuario);
            $this->logService->registrar((int) $idUsuario, $idEmpresa, 'crear_renovacion', 'firmas_electronicas', $idNueva, null, ['id_firma_anterior' => $idFirma]);
            if ($managed) {
                $db->commit();
            }
            return self::urlPortal($token);
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function getAvisosVencimiento(int $idFirma, int $idEmpresa): array
    {
        return $this->repository->getAvisos($idFirma, $idEmpresa);
    }
    // ═════════════════════════════════════════════════════════════════════
    // API DE UANATACA (Precompra)
    // ═════════════════════════════════════════════════════════════════════

    /** Campo del adjunto → campo de la API. */
    private const ARCHIVOS_UANATACA = [
        'cedula_frontal'          => 'frontIdentification',
        'cedula_posterior'        => 'backIdentification',
        'selfie'                  => 'selfie',
        'ruc_empresa'             => 'rucFile',
        'constitucion_compania'   => 'constitution',
        'nombramiento'            => 'appointment',
        'aceptacion_nombramiento' => 'acceptanceAppointment',
        'cedula_representante'    => 'managerIdentification',
        'autorizacion'            => 'authorization',
        'otro'                    => 'additionalFile',
    ];

    /** Mientras se envía, la firma queda marcada para que otro proceso no la envíe también. */
    private const UANATACA_ENVIANDO = 'SENDING';

    private function clienteUanataca(int $idEmpresa): \App\Services\UanatacaApiService
    {
        $cfg = $this->repository->getConfig($idEmpresa);
        if (!$cfg || !in_array($cfg['uanataca_activo'] ?? false, [true, 't', 1, '1'], true)) {
            throw new Exception('La integración con Uanataca no está activa. Actívela en la configuración del módulo.');
        }
        return new \App\Services\UanatacaApiService(
            (string) $cfg['uanataca_ambiente'],
            (string) $cfg['uanataca_usuario'],
            \App\Helpers\CryptoHelper::desencriptar((string) ($cfg['uanataca_clave_cifrada'] ?? ''))
        );
    }

    /** Prueba las credenciales: devuelve saldo y cantidad de productos. */
    public function probarConexionUanataca(int $idEmpresa): array
    {
        $api = $this->clienteUanataca($idEmpresa);
        return ['saldo' => $api->saldo(), 'productos' => count($api->productos())];
    }

    public function getSaldoUanataca(int $idEmpresa): float
    {
        return $this->clienteUanataca($idEmpresa)->saldo();
    }

    /** Productos de firma del sistema, catálogo de Uanataca y el enlace guardado entre ambos. */
    public function getCatalogoUanataca(int $idEmpresa): array
    {
        $catalogo = array_values(array_filter(
            $this->clienteUanataca($idEmpresa)->productos(),
            static fn($p) => is_array($p) && !in_array($p['active'] ?? true, [false, 'false', 0], true)
        ));
        usort($catalogo, static fn($a, $b) => strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));
        $mapeo = $this->repository->getMapeoProductos($idEmpresa);
        return [
            'catalogo'  => array_map(static fn($p) => [
                'uuid' => (string) ($p['uuid'] ?? ''), 'code' => (string) ($p['code'] ?? ''),
                'name' => (string) ($p['name'] ?? ''), 'price' => (float) ($p['price'] ?? 0),
            ], $catalogo),
            'productos' => array_map(static fn($t) => [
                'id' => (int) $t['id'], 'nombre' => $t['nombre'],
                'uanataca_uuid' => $mapeo[(int) $t['id']]['uanataca_product_uuid'] ?? '',
            ], $this->repository->getTiposFirma($idEmpresa)),
        ];
    }

    /** @param array<int,string> $mapeo id_producto => uuid de Uanataca ('' = sin enlace) */
    public function guardarMapeoUanataca(int $idEmpresa, int $idUsuario, array $mapeo): void
    {
        $catalogo = [];
        foreach ($this->clienteUanataca($idEmpresa)->productos() as $p) {
            $catalogo[(string) ($p['uuid'] ?? '')] = $p;
        }
        $propios = array_map(static fn($t) => (int) $t['id'], $this->repository->getTiposFirma($idEmpresa));
        $antes   = $this->repository->getMapeoProductos($idEmpresa);

        $this->repository->beginTransaction();
        try {
            foreach ($mapeo as $idProducto => $uuid) {
                $idProducto = (int) $idProducto;
                $uuid = trim((string) $uuid);
                if (!in_array($idProducto, $propios, true)) {
                    continue;
                }
                if ($uuid !== '' && !isset($catalogo[$uuid])) {
                    throw new Exception('Uno de los productos elegidos ya no está en el catálogo de Uanataca. Recargue la lista.');
                }
                $p = $catalogo[$uuid] ?? [];
                $this->repository->guardarMapeoProducto($idEmpresa, $idProducto, $uuid, (string) ($p['code'] ?? ''), (string) ($p['name'] ?? ''), $idUsuario);
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'mapeo_uanataca', 'firmas_productos_uanataca', null, $antes, $mapeo);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Lo que falta para enviar la firma a Uanataca (vacío = lista). */
    public function faltantesUanataca(int $idFirma, int $idEmpresa): array
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma) {
            return ['firma no encontrada'];
        }
        $tipos = array_column($this->repository->getAdjuntos($idFirma, $idEmpresa), 'tipo');
        $f = $this->rules->faltantesUanataca($firma, $tipos);
        if (!empty($firma['id_producto']) && empty($this->repository->getMapeoProductos($idEmpresa)[(int) $firma['id_producto']])) {
            $f[] = 'producto de Uanataca enlazado a «' . $firma['nombre_producto'] . '» (configuración → Productos de Uanataca)';
        }
        return $f;
    }

    /**
     * Crea la solicitud en Uanataca con los datos y documentos de la firma.
     * Protegida contra el doble envío: la firma queda marcada "enviando" (con candado)
     * antes de llamar a la API, y si ya existe una solicitud reciente de esa persona y
     * ese producto en Uanataca se enlaza en vez de crear otra.
     * $automatico: lo llama el cron; un error se registra y no se lanza.
     */
    public function enviarAUanataca(int $idFirma, int $idEmpresa, ?int $idUsuario, bool $automatico = false): ?string
    {
        // 1. Reservar el envío.
        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            $firma = $this->repository->findById($idFirma, $idEmpresa);
            if (!$firma) {
                throw new Exception('Firma no encontrada.');
            }
            if (!empty($firma['uanataca_uuid'])) {
                throw new Exception('Esta firma ya tiene una solicitud en Uanataca (' . $firma['uanataca_uuid'] . '). Las correcciones se hacen en el portal de distribuidores.');
            }
            if (($firma['uanataca_estado'] ?? '') === self::UANATACA_ENVIANDO
                && strtotime((string) $firma['uanataca_estado_at']) > time() - 300) {
                throw new Exception('La solicitud se está enviando a Uanataca en este momento.');
            }
            // "En trámite" sin uuid = se registró a mano en el portal: enviarla duplicaría la solicitud.
            if ($firma['estado_pago'] !== 'confirmado' || $firma['estado'] !== 'por_registrar') {
                throw new Exception('Solo se envía a Uanataca una firma pagada y pendiente de registrar.');
            }
            $faltan = $this->faltantesUanataca($idFirma, $idEmpresa);
            if ($faltan) {
                throw new Exception('Falta para enviar a Uanataca: ' . implode(', ', $faltan) . '.');
            }
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'uanataca_estado' => self::UANATACA_ENVIANDO, 'uanataca_estado_at' => date('Y-m-d H:i:s'),
            ], $idUsuario);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            if ($automatico) {
                $this->registrarErrorUanataca($idFirma, $idEmpresa, $e->getMessage(), $idUsuario);
                return null;
            }
            throw $e;
        }

        // 2. Llamar a la API (fuera de la transacción).
        try {
            $api     = $this->clienteUanataca($idEmpresa);
            $payload = $this->payloadUanataca($idFirma, $idEmpresa);
            $uuid    = $this->solicitudExistenteUanataca($api, $payload, $firma) ?? $api->crearSolicitud($payload);
        } catch (\Throwable $e) {
            $this->registrarErrorUanataca($idFirma, $idEmpresa, $e->getMessage(), $idUsuario);
            if ($automatico) {
                return null;
            }
            throw new Exception($e->getMessage());
        }

        // 3. Guardar el resultado.
        $this->repository->beginTransaction();
        try {
            $ahora = date('Y-m-d H:i:s');
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'uanataca_uuid' => $uuid, 'uanataca_estado' => 'NEW', 'uanataca_estado_at' => $ahora,
                'uanataca_enviada_at' => $ahora, 'uanataca_error' => null,
                'estado' => 'en_proceso', 'uanataca_registrada_at' => $ahora, 'uanataca_referencia' => $uuid,
            ], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca', 'Solicitud ingresada en la entidad certificadora',
                'Te llegará un correo de Uanataca para validar tu identidad y activar la firma.', true, $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca_ref', 'Enviada a Uanataca por API' . ($automatico ? ' (automático)' : '') . ': ' . $uuid, null, false, $idUsuario);
            $this->logService->registrar((int) $idUsuario, $idEmpresa, 'enviar_uanataca', 'firmas_electronicas', $idFirma, ['estado' => $firma['estado']], ['estado' => 'en_proceso', 'uanataca_uuid' => $uuid]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            // La solicitud ya existe en Uanataca: el próximo envío la encontrará y la enlazará.
            throw $e;
        }
        $this->notificarCliente($idFirma, $idEmpresa, 'Tu solicitud de firma está en trámite',
            '<p>Ingresamos tu solicitud de firma electrónica en la entidad certificadora (Uanataca).</p><p>En breve recibirás un correo de Uanataca para validar tu identidad y activar tu firma.</p>');
        return $uuid;
    }

    /**
     * Si una caída dejó creada la solicitud sin guardar su uuid, se reutiliza: misma
     * identificación y producto, creada desde el pago y no cancelada ni rechazada.
     */
    private function solicitudExistenteUanataca(\App\Services\UanatacaApiService $api, array $payload, array $firma): ?string
    {
        try {
            $desde = strtotime((string) ($firma['pago_revisado_at'] ?? '')) ?: (time() - 86400);
            foreach ($api->buscarSolicitudes((string) $payload['identification']) as $r) {
                if (($r['productUuid'] ?? '') === $payload['productUuid']
                    && !in_array($r['status'] ?? '', ['CANCELED', 'REJECTED'], true)
                    && strtotime((string) ($r['requestDate'] ?? '')) >= $desde - 3600
                    && !empty($r['uuid'])
                    && !$this->repository->existeUanatacaUuid((string) $r['uuid'])) {
                    return (string) $r['uuid'];
                }
            }
        } catch (\Throwable) {
            // Sin búsqueda se crea normalmente.
        }
        return null;
    }

    private function registrarErrorUanataca(int $idFirma, int $idEmpresa, string $mensaje, ?int $idUsuario): void
    {
        try {
            $this->repository->actualizarTramite($idFirma, $idEmpresa, [
                'uanataca_estado' => null, 'uanataca_estado_at' => date('Y-m-d H:i:s'), 'uanataca_error' => mb_substr($mensaje, 0, 1000),
            ], $idUsuario);
            $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca_error', 'No se pudo enviar a Uanataca', $mensaje, false, $idUsuario);
        } catch (\Throwable $e) {
            error_log('[Firmas/Uanataca] ' . $e->getMessage());
        }
    }

    /** Cuerpo del POST /certificateRequests a partir de la firma y sus documentos. */
    private function payloadUanataca(int $idFirma, int $idEmpresa): array
    {
        $f = $this->repository->getDetalleCompleto($idFirma, $idEmpresa);
        $mapeo = $this->repository->getMapeoProductos($idEmpresa)[(int) $f['id_producto']] ?? null;
        $up = static fn($v) => mb_strtoupper(trim((string) $v), 'UTF-8');
        $tipoId = ['cedula' => 'CÉDULA', 'pasaporte' => 'PASAPORTE', 'ruc' => 'RUC'];
        $tel = static function ($t) {
            $d = preg_replace('/\D/', '', (string) $t);
            return str_starts_with($d, '593') && strlen($d) === 12 ? '0' . substr($d, 3) : $d;
        };

        $p = [
            'identificationType' => $tipoId[$f['tipo_identificacion']] ?? 'CÉDULA',
            'identification'     => trim((string) $f['numero_identificacion']),
            'names'              => $up($f['nombres']),
            'lastName1'          => $up($f['apellido_paterno'] ?: $f['apellidos']),
            'birthDate'          => date('d/m/Y', strtotime((string) $f['fecha_nacimiento'])),
            'nationality'        => $up($f['nacionalidad'] ?: 'ECUATORIANA'),
            'sex'                => $f['sexo'] === 'mujer' ? 'MUJER' : 'HOMBRE',
            'phoneNumber'        => $tel($f['telefono']),
            'email'              => trim((string) $f['correo']),
            'province'           => $up($f['provincia_nombre']),
            'city'               => $up($f['ciudad_nombre']),
            'address'            => $up($f['direccion']),
            'productUuid'        => (string) $mapeo['uanataca_product_uuid'],
        ];
        if ($f['tipo_identificacion'] === 'cedula') {
            $p['fingerprintCode'] = $up($f['codigo_dactilar']);
        }
        foreach (['lastName2' => $up($f['apellido_materno']), 'phoneNumber2' => $tel($f['telefono2']), 'email2' => trim((string) $f['correo2'])] as $k => $v) {
            if ($v !== '') {
                $p[$k] = $v;
            }
        }
        if (in_array($f['tipo_persona'], ['juridica', 'miembro'], true)) {
            $p['ruc']      = trim((string) $f['ruc_empresa']);
            $p['company']  = $up($f['nombre_empresa']);
            $p['position'] = $up($f['cargo']);
            $p['reason']   = $up((string) self::motivoParaTipo((string) $f['tipo_persona'], $f['motivo'] ?? null));
        }
        if ($f['tipo_persona'] === 'miembro') {
            $p['department']                = $up($f['departamento']);
            $p['identificationTypeManager'] = $tipoId[$f['rep_tipo_identificacion'] ?? 'cedula'] ?? 'CÉDULA';
            $p['identificationManager']     = trim((string) $f['rep_identificacion']);
            $p['namesManager']              = $up($f['rep_nombres']);
            $p['lastNameManager']           = $up($f['rep_apellidos']);
        }

        // Documentos: el último de cada tipo, en base64 (fotos reducidas para no exceder el límite del envío).
        $ultimos = [];
        foreach ($this->repository->getAdjuntos($idFirma, $idEmpresa) as $a) {
            $ultimos[$a['tipo']] = $a;
        }
        foreach (self::ARCHIVOS_UANATACA as $tipo => $campo) {
            if (!isset($ultimos[$tipo])) {
                continue;
            }
            $adj = $this->repository->getAdjuntoPorId((int) $ultimos[$tipo]['id'], $idEmpresa);
            $ruta = MVC_ROOT . '/storage/' . $adj['ruta_relativa'];
            if (!is_file($ruta)) {
                throw new Exception('No se encuentra en el servidor el archivo de «' . (FirmaElectronicaRules::ETIQUETAS_ADJUNTO[$tipo] ?? $tipo) . '».');
            }
            [$bytes, $mime, $nombre] = self::archivoParaEnvio($ruta, (string) $adj['mime_type'], (string) $adj['nombre_original']);
            $p[$campo] = ['name' => $nombre, 'type' => $mime, 'base64' => base64_encode($bytes)];
        }
        return $p;
    }

    /** Fotos mayores a 1 MB se reducen a JPEG de 1600 px; los PDF van tal cual. */
    private static function archivoParaEnvio(string $ruta, string $mime, string $nombre): array
    {
        $bytes = (string) file_get_contents($ruta);
        if (!str_starts_with($mime, 'image/') || strlen($bytes) <= 1024 * 1024 || !function_exists('imagecreatefromstring')) {
            return [$bytes, $mime ?: 'application/octet-stream', $nombre];
        }
        $img = @imagecreatefromstring($bytes);
        if (!$img) {
            return [$bytes, $mime, $nombre];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $escala = min(1, 1600 / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) ($w * $escala)), max(1, (int) ($h * $escala)));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);
        $jpg = (string) ob_get_clean();
        imagedestroy($img);
        imagedestroy($dst);
        return [$jpg, 'image/jpeg', preg_replace('/\.\w+$/', '', $nombre) . '.jpg'];
    }

    /**
     * Consulta el estado en Uanataca y lo refleja en el trámite:
     *   UPDATE_REQUESTED / REJECTED → Observada (el cliente recibe el comentario)
     *   ISSUED → Emitida (con la caducidad que informe la API)
     */
    public function sincronizarUanataca(int $idFirma, int $idEmpresa, ?int $idUsuario = null): array
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || empty($firma['uanataca_uuid'])) {
            throw new Exception('Esta firma no tiene una solicitud en Uanataca.');
        }
        $r = $this->clienteUanataca($idEmpresa)->consultarSolicitud((string) $firma['uanataca_uuid']);
        if (!$r) {
            $this->repository->actualizarTramite($idFirma, $idEmpresa, ['uanataca_estado_at' => date('Y-m-d H:i:s')], $idUsuario);
            throw new Exception('Uanataca no devolvió la solicitud ' . $firma['uanataca_uuid'] . '.');
        }
        $nuevo   = strtoupper((string) ($r['status'] ?? ''));
        $antes   = (string) ($firma['uanataca_estado'] ?? '');
        $coment  = trim((string) ($r['comments'] ?? ''));
        $aviso   = null;
        $campos  = ['uanataca_estado' => $nuevo, 'uanataca_estado_at' => date('Y-m-d H:i:s'), 'uanataca_comentarios' => $coment ?: null, 'uanataca_error' => null];
        $etiqueta = \App\Services\UanatacaApiService::ESTADOS[$nuevo] ?? $nuevo;

        $this->repository->beginTransaction();
        try {
            $this->repository->lockFirma($idFirma);
            if ($nuevo !== $antes) {
                $ultimo = self::ultimoComentario($coment);
                if (in_array($nuevo, ['UPDATE_REQUESTED', 'REJECTED'], true) && !in_array($firma['estado'], ['emitida', 'cancelada'], true)) {
                    $nota = $ultimo !== '' ? $ultimo : 'La entidad certificadora pidió revisar la solicitud.';
                    $campos += ['estado' => 'observada', 'observacion_cliente' => $nota];
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'observada',
                        $nuevo === 'REJECTED' ? 'La entidad certificadora rechazó la solicitud' : 'La entidad certificadora pide una corrección', $nota, true, null);
                    $aviso = ['Tu solicitud de firma necesita una corrección',
                        '<p>La entidad certificadora revisó tu solicitud y pide lo siguiente:</p><p><b>' . nl2br(self::h($nota)) . '</b></p><p>Ingresa a tu enlace para corregir.</p>'];
                } elseif ($nuevo === 'ISSUED' && $firma['estado'] !== 'emitida') {
                    $cad = self::fechaApi($r['validTo'] ?? null)
                        ?? $this->caducidadEstimada((int) $firma['id_producto'], $idEmpresa, self::fechaApi($r['approvedDate'] ?? null) ?? 'today');
                    $campos += ['estado' => 'emitida', 'emitida_at' => date('Y-m-d H:i:s')] + ($cad ? ['fecha_caducidad' => $cad] : []);
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'emitida', 'Firma electrónica emitida',
                        $cad ? 'Vigente hasta el ' . date('d-m-Y', strtotime($cad)) . '.' : null, true, null);
                    if (!$cad) {
                        $this->repository->crearEvento($idEmpresa, $idFirma, 'nota', 'Registre la fecha de vencimiento', 'Uanataca no informó la vigencia.', false, null);
                    }
                    $aviso = ['Tu firma electrónica fue emitida', '<p>¡Listo! Tu firma electrónica fue emitida' . ($cad ? ' y está vigente hasta el <b>' . date('d-m-Y', strtotime($cad)) . '</b>' : '') . '.</p>'];
                } elseif ($nuevo === 'APPROVED' && in_array($firma['estado'], ['por_registrar', 'en_proceso', 'observada'], true)) {
                    // Aprobada en Uanataca: se marca igual aquí y la solicitud deja de editarse.
                    $campos += ['estado' => 'aprobada', 'observacion_cliente' => null];
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'aprobada', 'Solicitud aprobada por la entidad certificadora',
                        $ultimo !== '' ? $ultimo : 'Falta solo la emisión de la firma.', true, null);
                } elseif (in_array($nuevo, ['IN_VALIDATION', 'UPDATED'], true) && in_array($firma['estado'], ['observada', 'por_registrar'], true)) {
                    $campos += ['estado' => 'en_proceso', 'observacion_cliente' => null];
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca', 'En validación en la entidad certificadora', null, true, null);
                } else {
                    $this->repository->crearEvento($idEmpresa, $idFirma, 'uanataca_estado', 'Uanataca: ' . $etiqueta,
                        $ultimo !== '' ? $ultimo : null, $nuevo === 'APPROVED', null);
                }
            }
            $this->repository->actualizarTramite($idFirma, $idEmpresa, $campos, $idUsuario);
            if ($nuevo !== $antes) {
                $this->logService->registrar((int) $idUsuario, $idEmpresa, 'sincronizar_uanataca', 'firmas_electronicas', $idFirma, ['uanataca_estado' => $antes], $campos);
            }
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        if ($aviso) {
            $this->notificarCliente($idFirma, $idEmpresa, $aviso[0], $aviso[1]);
        }
        return ['estado' => $nuevo, 'etiqueta' => $etiqueta, 'cambio' => $nuevo !== $antes];
    }

    /** Cron: envíos automáticos pendientes y sincronización de estados. */
    public function procesarUanataca(): array
    {
        $res = ['enviadas' => 0, 'sincronizadas' => 0, 'errores' => 0];
        foreach ($this->repository->getPendientesEnvioUanataca() as $p) {
            $this->enviarAUanataca((int) $p['id'], (int) $p['id_empresa'], null, true) ? $res['enviadas']++ : $res['errores']++;
        }
        foreach ($this->repository->getPendientesSincronizarUanataca() as $p) {
            try {
                $this->sincronizarUanataca((int) $p['id'], (int) $p['id_empresa']);
                $res['sincronizadas']++;
            } catch (\Throwable $e) {
                $res['errores']++;
                error_log('[Firmas/Uanataca] Sincronizar firma #' . $p['id'] . ': ' . $e->getMessage());
            }
        }
        return $res;
    }

    /** Uanataca acumula el historial en "comments" (una línea por evento): se toma la última. */
    private static function ultimoComentario(string $comentarios): string
    {
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\r?\n|(?=\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} - )/', $comentarios))));
        $ultima = (string) end($lineas);
        // "2025-01-18 13:45:53 - usuario@uanataca.ec - TEXTO" → "TEXTO"
        return trim((string) preg_replace('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s*-\s*\S+@\S+\s*-\s*/', '', $ultima));
    }

    private static function fechaApi($v): ?string
    {
        if (!$v) {
            return null;
        }
        $v = (string) $v;
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $v, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        // ISO ("2027-10-23T00:00:00.000+00:00"): la fecha tal cual; convertir la hora UTC
        // a la zona de Ecuador la correría al día anterior.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) {
            return $m[1];
        }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : null;
    }
    // ═════════════════════════════════════════════════════════════════════
    // AYUDANTES
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Base, IVA y total de una firma, con la misma regla que la factura: IVA de la serie
     * configurada (IvaSubtotal), nunca una fórmula aparte (CLAUDE.md §9).
     */
    public function totalesProducto(int $idProducto, int $idEmpresa, ?int $idPunto): array
    {
        $prod = $this->repository->getProductoFacturable($idProducto, $idEmpresa);
        if (!$prod) {
            throw new Exception('Producto/Validez de firma no encontrado.');
        }
        $serie = $this->repository->getSerieFacturacion($idEmpresa, $idPunto);
        $base  = round((float) $prod['precio_base'], 2);
        $pct   = (float) ($prod['porcentaje_iva'] ?? 0);
        $modo  = \App\Helpers\IvaSubtotal::modoPunto($serie ? (int) $serie['id'] : null, $idEmpresa);
        $iva   = \App\Helpers\IvaSubtotal::repartir([0 => ['grupo' => (string) ($prod['id_tarifa_iva'] ?? 'pct'), 'base' => $base, 'pct' => $pct]], $modo)[0];
        return ['producto' => $prod, 'serie' => $serie, 'base' => $base, 'pct' => $pct, 'iva' => $iva, 'total' => round($base + $iva, 2)];
    }

    /** @return array{0:int, 1:array} id de la factura y el payload usado. */
    private function crearFactura(array $firma, int $idUsuario, string $codigoSriPago): array
    {
        $idEmpresa = (int) $firma['id_empresa'];
        if ((int) ($firma['id_producto'] ?? 0) <= 0) {
            throw new Exception('La firma no tiene una Validez de Firma seleccionada.');
        }
        $cfg = $this->getConfigModulo($idEmpresa);
        $tot = $this->totalesProducto((int) $firma['id_producto'], $idEmpresa, $cfg['id_punto_emision'] ?: null);
        $serie = $tot['serie'];
        if (!$serie) {
            throw new Exception('No hay una serie (punto de emisión) activa para facturar. Revise la configuración del módulo.');
        }
        $prod = $tot['producto'];

        $idCliente = $this->buscarOCrearCliente($firma, $idUsuario);
        $secInfo   = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial((int) $serie['id'], 'Facturas de venta');
        $empresaConfig = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];

        $codigoPct = $tot['pct'] > 0
            ? \App\Helpers\SriIvaHelper::codigoPorcentaje($tot['pct'])
            : (string) ($prod['codigo_porcentaje_iva'] ?? '0');

        $correoCliente = $this->datosFacturacion($firma)['correo'];
        $data = [
            'id_empresa'          => $idEmpresa,
            'id_usuario'          => $idUsuario,
            'empresa_config'      => $empresaConfig,
            'id_cliente'          => $idCliente,
            'id_establecimiento'  => (int) $serie['id_establecimiento'],
            'id_punto_emision'    => (int) $serie['id'],
            'id_bodega'           => null,
            'establecimiento'     => $serie['cod_establecimiento'],
            'punto_emision'       => $serie['codigo_punto'],
            'secuencial'          => $secInfo['formateado'],
            'fecha_emision'       => date('Y-m-d'),
            'estado'              => 'borrador',
            'total_sin_impuestos' => $tot['base'],
            'total_descuento'     => 0,
            'importe_total'       => $tot['total'],
            'propina'             => 0,
            'observaciones'       => null,
            'detalles'            => [[
                'id_producto'               => (int) $prod['id'],
                'nombre'                    => $prod['nombre'],
                'descripcion'               => $prod['nombre'],
                'codigo_principal'          => $prod['codigo'] ?: '000',
                'codigo_auxiliar'           => $prod['codigo_auxiliar'] ?? null,
                'id_unidad_medida'          => $prod['id_medida'] ?? null,
                'cantidad'                  => 1,
                'precio_unitario'           => $tot['base'],
                'descuento'                 => 0,
                'precio_total_sin_impuesto' => $tot['base'],
                'porcentaje_iva'            => $tot['pct'],
                'id_tarifa_iva'             => !empty($prod['id_tarifa_iva']) ? (int) $prod['id_tarifa_iva'] : null,
                'impuestos'                 => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => $codigoPct,
                    'tarifa'            => $tot['pct'],
                    'base_imponible'    => $tot['base'],
                    'valor'             => $tot['iva'],
                ]],
            ]],
            'pagos'          => [['forma_pago' => $codigoSriPago, 'total' => $tot['total'], 'plazo' => 0, 'unidad_tiempo' => 'dias']],
            'info_adicional' => filter_var($correoCliente, FILTER_VALIDATE_EMAIL)
                ? [['nombre' => 'correo del cliente', 'valor' => $correoCliente]] : [],
        ];

        $idFactura = $this->facturaService()->crear($data);
        return [$idFactura, $data];
    }

    /** @return array{0:int, 1:array} id del ingreso y el payload usado. */
    private function crearIngreso(int $idFactura, int $idEmpresa, int $idUsuario, array $forma, float $monto, string $referencia, string $tipoPago): array
    {
        $factura = (new \App\repositories\modulos\FacturaVentaRepository())->getPorId($idFactura);
        if (!$factura || (int) $factura['id_empresa'] !== $idEmpresa) {
            throw new Exception('No se encontró la factura para registrar el cobro.');
        }
        $punto = (new \App\repositories\SecuencialRepository())->getPuntoEmisionSerie((int) $factura['id_punto_emision'], $idEmpresa);
        if (!$punto) {
            throw new Exception('La serie de la factura no está disponible para registrar el cobro.');
        }
        $secRes = (new \App\Services\SecuencialService())->obtenerSiguienteSecuencial((int) $punto['id'], 'Ingresos', date('Y-m-d'));

        $importe = round((float) $factura['importe_total'], 2);
        $cobrar  = min($importe, round($monto, 2));
        $numDoc  = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $factura['secuencial'];
        $numeroIngreso = str_pad((string) $punto['establecimiento'], 3, '0', STR_PAD_LEFT) . '-'
                       . str_pad((string) $punto['punto'], 3, '0', STR_PAD_LEFT) . '-'
                       . str_pad((string) $secRes['secuencial'], 9, '0', STR_PAD_LEFT);

        $payload = [
            'id_empresa'          => $idEmpresa,
            'id_establecimiento'  => (int) ($punto['id_establecimiento'] ?? 0),
            'id_punto_emision'    => (int) $punto['id'],
            'id_cliente'          => (int) $factura['id_cliente'],
            'id_usuario'          => $idUsuario,
            'fecha_emision'       => date('Y-m-d'),
            'establecimiento'     => $punto['establecimiento'],
            'punto_emision'       => $punto['punto'],
            'secuencial'          => $secRes['formateado'],
            'numero_ingreso'      => $numeroIngreso,
            'tipo_ingreso'        => 'FACTURA_VENTA',
            'id_ingreso_concepto' => null,
            'monto_total'         => $cobrar,
            'observaciones'       => ($tipoPago === 'payphone' ? 'Cobro con tarjeta (Payphone)' : 'Cobro por transferencia')
                                     . ' - firma electrónica' . ($referencia !== '' ? ' - ref. ' . $referencia : ''),
            'recibo_de'           => $factura['cliente_nombre'] ?? '',
            'id_recibo_cliente'   => (int) $factura['id_cliente'],
            'detalles'            => [[
                'tipo_documento'          => 'FACTURA',
                'id_referencia_documento' => $idFactura,
                'numero_documento'        => $numDoc,
                'descripcion'             => 'Cobro de factura ' . $numDoc,
                'monto_documento'         => $importe,
                'saldo_anterior'          => $importe,
                'monto_cobrado'           => $cobrar,
                'saldo_actual'            => max(0.0, round($importe - $cobrar, 2)),
            ]],
            'pagos' => [[
                'id_forma_cobro'          => (int) $forma['id'],
                'monto'                   => $cobrar,
                'referencia'              => $referencia !== '' ? mb_substr($referencia, 0, 100) : null,
                'tipo_operacion_bancaria' => strtoupper((string) ($forma['tipo'] ?? '')) === 'BANCO' ? 'TRANSFERENCIA' : null,
                'numero_cheque'           => null,
            ]],
        ];
        $idIngreso = $this->ingresoService()->crear($payload);
        return [$idIngreso, $payload];
    }

    private function buscarOCrearCliente(array $firma, int $idUsuario): int
    {
        $idEmpresa = (int) $firma['id_empresa'];
        $fac = $this->datosFacturacion($firma);
        if ($fac['identificacion'] === '' || $fac['nombre'] === '') {
            throw new Exception('Faltan los datos de facturación (identificación y nombre).');
        }
        $repo = new \App\repositories\modulos\ClienteRepository();
        $existente = $repo->findByIdentificacion($idEmpresa, $fac['identificacion']);
        if ($existente) {
            $repo->reactivarYActualizar((int) $existente['id'], [
                'nombre' => $fac['nombre'], 'email' => $fac['correo'], 'telefono' => $fac['telefono'], 'id_usuario' => $idUsuario,
            ]);
            return (int) $existente['id'];
        }
        $tipoId = ['ruc' => '04', 'pasaporte' => '06'][$fac['tipo']] ?? '05';
        return $repo->create([
            'id_empresa'     => $idEmpresa,
            'id_usuario'     => $idUsuario,
            'nombre'         => $fac['nombre'],
            'tipo_id'        => $tipoId,
            'identificacion' => $fac['identificacion'],
            'telefono'       => $fac['telefono'] ?: null,
            'email'          => $fac['correo'] ?: null,
            'direccion'      => $fac['direccion'] ?: null,
            'provincia'      => null,
            'ciudad'         => null,
            'id_vendedor'    => null,
            'plazo'          => 0,
            'status'         => 1,
        ]);
    }

    /** A quién se factura: el titular o los datos de facturación propios. */
    private function datosFacturacion(array $firma): array
    {
        $mismos = !in_array($firma['facturacion_mismos_datos'] ?? true, [false, 'f', 0, '0'], true);
        if ($mismos) {
            return [
                'tipo'           => (string) ($firma['tipo_identificacion'] ?? 'cedula'),
                'identificacion' => trim((string) ($firma['numero_identificacion'] ?? '')),
                'nombre'         => mb_strtoupper(trim(($firma['apellidos'] ?? '') . ' ' . ($firma['nombres'] ?? '')), 'UTF-8'),
                'correo'         => trim((string) ($firma['correo'] ?? '')),
                'telefono'       => trim((string) ($firma['telefono'] ?? '')),
                'direccion'      => trim((string) ($firma['direccion'] ?? '')),
            ];
        }
        return [
            'tipo'           => (string) ($firma['facturacion_tipo_id'] ?? 'cedula'),
            'identificacion' => trim((string) ($firma['facturacion_num_id'] ?? '')),
            'nombre'         => mb_strtoupper(trim((string) ($firma['facturacion_nombres'] ?? '')), 'UTF-8'),
            'correo'         => trim((string) ($firma['facturacion_correo'] ?? '')),
            'telefono'       => trim((string) ($firma['facturacion_telefono'] ?? '')),
            'direccion'      => trim((string) ($firma['facturacion_direccion'] ?? '')),
        ];
    }

    /** id de la factura de la firma si sigue activa (no eliminada ni anulada). */
    /** Factura de la firma para su PDF (también si está anulada); null si no tiene o se eliminó. */
    public function idFacturaParaPdf(int $idFirma, int $idEmpresa): ?int
    {
        $firma = $this->repository->findById($idFirma, $idEmpresa);
        if (!$firma || empty($firma['id_factura'])) {
            return null;
        }
        $f = $this->repository->getEstadoFactura((int) $firma['id_factura'], $idEmpresa);
        if (!$f || $f['eliminado'] === true || $f['eliminado'] === 't') {
            return null;
        }
        return (int) $f['id'];
    }

    private function facturaActiva(array $firma): ?int
    {
        if (empty($firma['id_factura'])) {
            return null;
        }
        $f = $this->repository->getEstadoFactura((int) $firma['id_factura'], (int) $firma['id_empresa']);
        if (!$f || $f['eliminado'] === true || $f['eliminado'] === 't' || $f['estado'] === 'anulado') {
            return null;
        }
        return (int) $f['id'];
    }

    private function numeroFactura(int $idFactura, int $idEmpresa): string
    {
        $f = (new \App\repositories\modulos\FacturaVentaRepository())->getPorId($idFactura);
        return $f ? ($f['establecimiento'] . '-' . $f['punto_emision'] . '-' . $f['secuencial']) : '#' . $idFactura;
    }

    /** Guarda el archivo en disco y su registro; reemplaza el anterior del mismo tipo. */
    private function guardarAdjunto(array $firma, string $tipo, array $file, ?int $idUsuario, bool $reemplazar): array
    {
        [$mime, $ext] = $this->rules->validarArchivo($file, $tipo);
        $idEmpresa = (int) $firma['id_empresa'];
        $idFirma   = (int) $firma['id'];

        $dir = MVC_ROOT . '/storage/' . self::STORAGE_DIR . '/' . $idEmpresa;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $nombreArchivo = $idFirma . '_' . $tipo . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nombreArchivo)) {
            throw new Exception('Error al guardar el archivo en el servidor.');
        }

        $this->repository->beginTransaction();
        try {
            if ($reemplazar && $tipo !== 'otro') {
                foreach ($this->repository->getAdjuntos($idFirma, $idEmpresa) as $a) {
                    if ($a['tipo'] === $tipo) {
                        $this->repository->deleteAdjunto((int) $a['id'], $idEmpresa, (int) $idUsuario);
                    }
                }
            }
            $nombreOriginal = mb_substr(basename((string) $file['name']), 0, 200);
            $idAdj = $this->repository->createAdjunto([
                'id_firma'        => $idFirma,
                'id_empresa'      => $idEmpresa,
                'tipo'            => $tipo,
                'nombre_original' => $nombreOriginal,
                'nombre_archivo'  => $nombreArchivo,
                'ruta_relativa'   => self::STORAGE_DIR . '/' . $idEmpresa . '/' . $nombreArchivo,
                'mime_type'       => $mime,
                'tamano_bytes'    => (int) $file['size'],
                'created_by'      => $idUsuario,
            ]);
            $this->logService->registrar((int) $idUsuario, $idEmpresa, 'subir_adjunto', 'firmas_electronicas_adjuntos', $idAdj, null, ['id_firma' => $idFirma, 'tipo' => $tipo]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            @unlink($dir . '/' . $nombreArchivo);
            throw $e;
        }
        return ['id' => $idAdj, 'tipo' => $tipo, 'nombre_original' => $nombreOriginal];
    }

    private function solicitudActiva(string $token): array
    {
        if (!self::tokenValido($token)) {
            throw new Exception('Enlace inválido.');
        }
        $this->solRepo->expirarVencidos();
        $sol = $this->solRepo->getByToken($token);
        if (!$sol || in_array($sol['estado'], ['expirado', 'cancelado'], true)) {
            throw new Exception('Este enlace ya no está disponible. Solicite uno nuevo a la empresa.');
        }
        return $sol;
    }

    /** @return array{0:array, 1:array} solicitud y firma del portal. */
    private function firmaDelPortal(string $token): array
    {
        $sol = $this->solicitudActiva($token);
        $firma = $this->repository->getPorSolicitud((int) $sol['id'], (int) $sol['id_empresa']);
        if (!$firma) {
            throw new Exception('Primero complete y guarde sus datos.');
        }
        return [$sol, $firma];
    }

    private function exigirEditable(array $firma): void
    {
        if (!in_array((string) $firma['estado'], FirmaElectronicaRules::ESTADOS_EDITABLES_PORTAL, true)) {
            throw new Exception('Su solicitud ya está en trámite y no se puede modificar. Si necesita un cambio, comuníquese con la empresa.');
        }
    }

    private function exigirPagable(array $firma): void
    {
        if (!in_array((string) $firma['estado_pago'], ['pendiente', 'rechazado'], true)) {
            throw new Exception($firma['estado_pago'] === 'en_revision'
                ? 'Su comprobante está en revisión.'
                : 'El pago de esta firma ya fue registrado.');
        }
        if (in_array((string) $firma['estado'], ['borrador', 'cancelada'], true)) {
            throw new Exception($firma['estado'] === 'borrador'
                ? 'Primero suba todos sus documentos.'
                : 'Esta solicitud fue cancelada.');
        }
        if ((int) ($firma['id_producto'] ?? 0) <= 0) {
            throw new Exception('Seleccione la validez de la firma.');
        }
    }

    /** Payphone configurado y activo, y con una forma de cobro PAYPHONE para registrar el cobro. */
    private function payphoneDisponible(int $idEmpresa): bool
    {
        $repo = new \App\repositories\PayphoneRepository();
        try {
            (new \App\Services\PayphoneService($repo))->getConfig($idEmpresa);
        } catch (\Throwable) {
            return false;
        }
        return $repo->getFormaCobroPayphone($idEmpresa) !== null;
    }

    private function usuarioDelTramite(array $firma): int
    {
        return (int) ($firma['created_by'] ?? 0)
            ?: (int) ($firma['id_usuario'] ?? 0)
            ?: $this->getConfigModulo((int) $firma['id_empresa'])['id_usuario_responsable'];
    }

    private function nombreProducto(array $tipos, int $idProducto): string
    {
        foreach ($tipos as $t) {
            if ((int) $t['id'] === $idProducto) {
                return (string) $t['nombre'];
            }
        }
        return '';
    }

    /** Datos de la empresa para el RIDE (mismo armado que Facturas / Condominios). */
    private function empresaPdf(int $idEmpresa, ?int $idPunto): array
    {
        $model   = new \App\models\Empresa();
        $empresa = $model->getPorId($idEmpresa) ?? [];
        $est     = $model->getEstablecimientos($idEmpresa)[0] ?? null;
        if ($est) {
            foreach (['logo_ruta' => 'logo_ruta', 'direccion' => 'direccion_establecimiento', 'leyenda_pdf_titulo' => 'leyenda_pdf_titulo', 'leyenda_pdf_mensaje' => 'leyenda_pdf_mensaje'] as $k => $dest) {
                if (!empty($est[$k])) {
                    $empresa[$dest] = $est[$k];
                }
            }
            try {
                $cfg = (new \App\repositories\modulos\EmpresaRepository())->getEstablecimientoConfig((int) $est['id']);
                if ($cfg) {
                    $cfg['direccion_matriz'] = $empresa['direccion'] ?? '';
                    $cfg['direccion_establecimiento'] = $est['direccion'] ?? '';
                    if (!empty($est['logo_ruta'])) {
                        $cfg['logo_ruta'] = $est['logo_ruta'];
                    }
                    $empresa = array_merge($empresa, $cfg);
                }
            } catch (\Throwable) {
            }
        }
        \App\Helpers\LogoPuntoEmision::aplicar($empresa, $idEmpresa, $idPunto);
        return $empresa;
    }

    private function facturaService(): FacturaVentaService
    {
        return new FacturaVentaService(
            new \App\repositories\modulos\FacturaVentaRepository(),
            new \App\Rules\modulos\FacturaVentaRules(),
            $this->logService
        );
    }

    private function ingresoService(): IngresoService
    {
        return new IngresoService(
            new \App\repositories\modulos\IngresoRepository(),
            new \App\Rules\modulos\IngresoRules(),
            $this->logService
        );
    }

    public static function urlPortal(string $token): string
    {
        return url_absoluta('solicitud-firma/' . $token);
    }

    public static function urlEnlaceFijo(string $codigo): string
    {
        return url_absoluta('solicitud-firma/e/' . $codigo);
    }

    public static function tokenValido(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    /** 09XXXXXXXX / +593 9XXXXXXXX → 5939XXXXXXXX (formato de WhatsApp). '' si no es válido. */
    /**
     * Motivo de uso que Uanataca exige al representante legal ("reason"). El cliente ya no
     * lo escribe: se conserva el que hubiera y, si no hay, va el texto por defecto.
     */
    public static function motivoParaTipo(string $tipoPersona, ?string $actual): ?string
    {
        if (!in_array($tipoPersona, ['juridica', 'miembro'], true)) {
            return $actual !== null && trim($actual) !== '' ? $actual : null;
        }
        return $actual !== null && trim($actual) !== '' ? $actual : FirmaElectronicaRules::MOTIVO_POR_DEFECTO;
    }

    /**
     * Celular como lo escribe el cliente en Ecuador (10 dígitos, 09…). Los enlaces guardan el
     * número en formato internacional para WhatsApp (593…): 593958924835 → 0958924835.
     * Lo que no se reconoce queda solo con sus dígitos.
     */
    public static function celularLocal(?string $tel): string
    {
        $d = preg_replace('/\D/', '', (string) $tel);
        if (preg_match('/^5939\d{8}$/', $d)) {
            return '0' . substr($d, 3);
        }
        if (preg_match('/^9\d{8}$/', $d)) {
            return '0' . $d;
        }
        return $d;
    }

    public static function normalizarTelefono(string $tel): string
    {
        $d = preg_replace('/\D/', '', $tel);
        if (str_starts_with($d, '0') && strlen($d) === 10) {
            $d = '593' . substr($d, 1);
        } elseif (strlen($d) === 9 && str_starts_with($d, '9')) {
            $d = '593' . $d;
        }
        return preg_match('/^\d{11,15}$/', $d) ? $d : '';
    }

    public static function etiquetaEstado(string $estado): string
    {
        return [
            'borrador'      => 'Borrador',
            'pendiente'     => 'Pendiente de pago',
            'por_registrar' => 'Por registrar en Uanataca',
            'en_proceso'    => 'En trámite (Uanataca)',
            'aprobada'      => 'Aprobada por Uanataca',
            'observada'     => 'Observada',
            'emitida'       => 'Emitida',
            'cancelada'     => 'Cancelada',
        ][$estado] ?? ucfirst($estado);
    }

    private static function h(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }
}
