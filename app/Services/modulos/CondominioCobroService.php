<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CondominioCobroRepository;
use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\CondominioCobroRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Cobros desde la pestaña Condóminos de Configuración de condominios. Dos caminos:
 *
 *  1. EMITIR AHORA (multas, cuotas extraordinarias, cualquier servicio): crea una emisión con un
 *     ítem por documento y la procesa un worker en segundo plano
 *     (scripts/procesar_emision_condominios.php). Cada documento sale por
 *     SuscripcionFacturacionService::generarUnPeriodo() —el mismo punto de generación de
 *     Suscripciones, con su IVA, secuencial con candado y XML—, pero SIN crear suscripciones.
 *     Las facturas se envían al SRI; con «enviar por correo», cada documento va al condómino.
 *
 *  2. AGREGAR A SUSCRIPCIONES (cobro recurrente, p. ej. la alícuota): no duplica. Si el condómino
 *     (o su inmueble) ya tiene el concepto con el mismo valor, se omite; si el valor cambió, se
 *     actualiza; si tiene suscripción sin ese concepto, se le agrega la línea; si no tiene, se crea.
 *
 * Lo que muestra la vista previa se recalcula SIEMPRE en el servidor al grabar; el usuario solo
 * decide qué filas (clave cliente:inmueble) se incluyen.
 */
class CondominioCobroService
{
    private const MODULO_GUARDADO = 'condominios_emision';

    public function __construct(
        private CondominioCobroRepository $repo,
        private CondominioCobroRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CondominioCobroRepository(), new CondominioCobroRules(), new LogSistemaService());
    }

    public function repo(): CondominioCobroRepository
    {
        return $this->repo;
    }

    /** Catálogos del modal: series, periodicidades y multas del reglamento (atajo de concepto + valor). */
    public function opciones(int $idEmpresa): array
    {
        $susc = new SuscripcionesRepository();
        $multas = [];
        try {
            foreach ((new \App\repositories\modulos\CondominioRepository())->getMultas($idEmpresa, true) as $m) {
                $multas[] = ['id' => (int) $m['id'], 'nombre' => $m['nombre'], 'valor' => (float) $m['valor'],
                             'id_producto' => (int) $m['id_producto'], 'producto_nombre' => $m['producto_nombre'] ?? ''];
            }
        } catch (\Throwable $e) {
            $multas = [];
        }
        return [
            'instalado'      => $this->repo->instalado(),
            'series'         => $susc->getSeriesActivas($idEmpresa),
            'periodicidades' => $susc->getPeriodicidades(),
            'multas'         => $multas,
            'emisiones'      => $this->repo->instalado() ? $this->repo->getEmisiones($idEmpresa, 30) : [],
        ];
    }

    // ── Vista previa (común a los dos modos) ────────────────────────────────

    public function previsualizar(array $data, int $idEmpresa): array
    {
        $d = $this->validar($data, $idEmpresa);
        $filas = $this->construirFilas($d, $idEmpresa);
        return ['filas' => $filas, 'totales' => $this->totales($filas, $d['modo'])];
    }

    /** Valida datos, módulo activo, concepto y (si emite) serie; devuelve los datos limpios. */
    private function validar(array $data, int $idEmpresa): array
    {
        $d = $this->rules->normalizar($data);
        if (!CondominioService::activo($idEmpresa)) {
            throw new \DomainException('Guarde primero la configuración del condominio.');
        }
        $prod = $this->repo->getProductoCobro($d['id_producto'], $idEmpresa);
        if (!$prod) {
            throw new \InvalidArgumentException('El concepto elegido no existe en Productos.|#cob_producto_txt');
        }
        if ((string) ($prod['tipo_produccion'] ?? '') !== '02') {
            throw new \InvalidArgumentException("«{$prod['nombre']}» es un bien: el concepto debe ser un servicio.|#cob_producto_txt");
        }
        $d['producto'] = $prod;
        if ($d['modo'] === 'emitir') {
            if (!$this->repo->instalado()) {
                throw new \DomainException('Falta aplicar database/migrations/20261009_condominios_emisiones.sql.');
            }
            $serie = $this->repo->getSerie($d['id_punto_emision'], $idEmpresa);
            if (!$serie) {
                throw new \InvalidArgumentException('La serie elegida no existe o está inactiva.|#cob_id_punto_emision');
            }
            $d['serie'] = $serie;
        } else {
            $ids = array_map(fn($p) => (int) $p['id'], (new SuscripcionesRepository())->getPeriodicidades());
            if (!in_array($d['id_periodicidad'], $ids, true)) {
                throw new \InvalidArgumentException('La periodicidad elegida no es válida.|#cob_id_periodicidad');
            }
        }
        return $d;
    }

    /** Ids de los clientes destino: los marcados, o todos los del filtro actual de la pestaña. */
    private function idsDestino(array $d, int $idEmpresa): array
    {
        if ($d['destino'] === 'filtro') {
            $rows = CondominioService::crear()->getCondominos($idEmpresa, $d['buscar'], 1, 0, [['col' => 'nombre', 'dir' => 'ASC']])['rows'];
            $ids = array_map(fn($r) => (int) $r['id'], $rows);
        } else {
            $ids = $d['ids'];
        }
        if (count($ids) > CondominioCobroRules::MAX_DESTINATARIOS) {
            throw new \InvalidArgumentException('Son demasiados condóminos a la vez (máximo ' . CondominioCobroRules::MAX_DESTINATARIOS . '). Acote el filtro.|#cob_destino');
        }
        return $ids;
    }

    /**
     * Una fila por documento (emitir) o por suscripción afectada (suscripción):
     * {clave, id_cliente, cliente, identificacion, email, id_unidad, inmueble, valor, accion, nota,
     *  incluir, id_suscripcion, id_detalle, actual}.
     */
    private function construirFilas(array $d, int $idEmpresa): array
    {
        $clientes = $this->repo->getClientes($idEmpresa, $this->idsDestino($d, $idEmpresa));
        $idsCli   = array_map(fn($c) => (int) $c['id'], $clientes);

        $inmPorCliente = [];
        foreach ($this->repo->getInmueblesPagados($idEmpresa, $idsCli) as $u) {
            $inmPorCliente[(int) $u['id_pagador']][] = $u;
        }
        $suscPorCliente = [];
        foreach ($this->repo->getSuscripcionesClientes($idEmpresa, $idsCli, $d['id_producto']) as $s) {
            $suscPorCliente[(int) $s['id_cliente']][] = $s;
        }
        $cuotas = $d['forma_valor'] === 'inmueble' ? CondominioService::crear()->cuotasVigentes($idEmpresa) : [];
        $etiqueta = fn(array $u) => $u['codigo'] . ($u['nombre'] !== '' && $u['nombre'] !== $u['codigo'] ? ' · ' . $u['nombre'] : '');

        $filas = [];
        foreach ($clientes as $c) {
            $idC   = (int) $c['id'];
            $email = trim((string) ($c['email'] ?? ''));
            $base  = ['id_cliente' => $idC, 'cliente' => $c['nombre'], 'identificacion' => (string) ($c['identificacion'] ?? ''), 'email' => $email];
            $inms  = $inmPorCliente[$idC] ?? [];
            $suscs = $suscPorCliente[$idC] ?? [];

            if ($d['agrupar'] === 'inmueble') {
                if (!$inms) {
                    $filas[] = $base + ['clave' => "{$idC}:0", 'id_unidad' => null, 'inmueble' => '', 'valor' => null, 'accion' => 'omitir',
                                        'nota' => 'No paga ningún inmueble activo.', 'incluir' => false, 'id_suscripcion' => null, 'id_detalle' => null, 'actual' => null];
                    continue;
                }
                foreach ($inms as $u) {
                    $valor = $d['forma_valor'] === 'fijo' ? $d['valor'] : ($cuotas[(int) $u['id']] ?? null);
                    $propias = array_values(array_filter($suscs, fn($s) => (int) ($s['id_unidad'] ?? 0) === (int) $u['id']));
                    $filas[] = $this->decidir($d, $base + ['clave' => "{$idC}:{$u['id']}", 'id_unidad' => (int) $u['id'], 'inmueble' => $etiqueta($u)], $valor, $propias);
                }
            } else {
                // Uno por condómino: sus suscripciones sin inmueble primero (las del cliente en general).
                usort($suscs, fn($a, $b) => (empty($a['id_unidad']) ? 0 : 1) <=> (empty($b['id_unidad']) ? 0 : 1));
                $filas[] = $this->decidir($d, $base + ['clave' => "{$idC}:0", 'id_unidad' => null, 'inmueble' => implode(', ', array_map($etiqueta, $inms))], $d['valor'], $suscs);
            }
        }
        return $filas;
    }

    /** Acción de una fila según el modo y las suscripciones que ya tiene ese destino. */
    private function decidir(array $d, array $f, ?float $valor, array $suscs): array
    {
        $f += ['valor' => $valor !== null ? round($valor, 2) : null, 'accion' => 'omitir', 'nota' => '', 'incluir' => false,
               'id_suscripcion' => null, 'id_detalle' => null, 'actual' => null];
        if ($valor === null || $valor <= 0) {
            $f['nota'] = $d['forma_valor'] === 'inmueble' ? 'El inmueble no tiene cuota con el valor que rige.' : 'Sin valor.';
            return $f;
        }
        $conLinea = null;
        foreach ($suscs as $s) {
            if (!empty($s['id_detalle'])) {
                $conLinea = $s;
                break;
            }
        }

        if ($d['modo'] === 'emitir') {
            $f['accion'] = 'emitir';
            $f['incluir'] = true;
            $notas = [];
            if ($conLinea) {
                // Evita cobrarle dos veces lo mismo: ya lo factura su suscripción.
                $notas[] = "Ya cobra este concepto en la suscripción #{$conLinea['id']} ($" . number_format((float) $conLinea['precio_actual'], 2) . ').';
                $f['incluir'] = false;
                $f['id_suscripcion'] = (int) $conLinea['id'];
            }
            if ($d['enviar_correo'] && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
                $notas[] = 'Sin correo válido: el documento se emite pero no se envía.';
            }
            $f['nota'] = implode(' ', $notas);
            return $f;
        }

        // Agregar a suscripciones: nunca duplicar.
        if ($conLinea) {
            $f['id_suscripcion'] = (int) $conLinea['id'];
            $f['id_detalle']     = (int) $conLinea['id_detalle'];
            $f['actual']         = round((float) $conLinea['precio_actual'], 2);
            if (abs($f['actual'] - $f['valor']) < 0.005) {
                $f['accion'] = 'sin_cambio';
                $f['nota']   = "Ya está en la suscripción #{$conLinea['id']} con el mismo valor.";
            } else {
                $f['accion']  = 'actualizar';
                $f['incluir'] = true;
                $f['nota']    = "Cambia el valor en la suscripción #{$conLinea['id']}.";
            }
        } elseif ($suscs) {
            $f['accion']         = 'agregar';
            $f['incluir']        = true;
            $f['id_suscripcion'] = (int) $suscs[0]['id'];
            $f['nota']           = "Se agrega la línea a la suscripción #{$suscs[0]['id']}.";
        } else {
            $f['accion']  = 'crear';
            $f['incluir'] = true;
            $f['nota']    = 'Se crea una suscripción nueva.';
        }
        return $f;
    }

    private function totales(array $filas, string $modo): array
    {
        $t = ['filas' => count($filas), 'incluidas' => 0, 'valor' => 0.0, 'acciones' => []];
        foreach ($filas as $f) {
            $t['acciones'][$f['accion']] = ($t['acciones'][$f['accion']] ?? 0) + 1;
            if ($f['incluir']) {
                $t['incluidas']++;
                $t['valor'] += (float) $f['valor'];
            }
        }
        $t['valor'] = round($t['valor'], 2);
        $t['modo'] = $modo;
        return $t;
    }

    /** Filas que el usuario dejó marcadas (por clave) y que el servidor confirma como aplicables. */
    private function filasElegidas(array $filas, array $seleccion, array $acciones): array
    {
        $sel = array_flip($seleccion);
        return array_values(array_filter($filas, fn($f) => isset($sel[$f['clave']]) && in_array($f['accion'], $acciones, true)));
    }

    // ── 1. Emitir ahora (lote en segundo plano) ─────────────────────────────

    /**
     * Crea la emisión con sus ítems. Guardado único: un doble clic o un reintento con el mismo
     * token devuelve la emisión ya creada en vez de crear otra (CLAUDE.md §8).
     *
     * @return array{id:int, items:int, previo:bool}
     */
    public function crearEmision(array $data, int $idEmpresa, int $idUsuario, string $token): array
    {
        $d = $this->validar($data, $idEmpresa);
        if ($d['modo'] !== 'emitir') {
            throw new \InvalidArgumentException('Modo no válido para emitir.');
        }
        $guardado = new GuardadoUnicoService();
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $previo = $guardado->previo($token, $idEmpresa, self::MODULO_GUARDADO);
            if ($previo) {
                $db->rollBack();
                return ['id' => (int) $previo['id_registro'], 'items' => 0, 'previo' => true];
            }
            $elegidas = $this->filasElegidas($this->construirFilas($d, $idEmpresa), $d['seleccion'], ['emitir']);
            if (!$elegidas) {
                throw new \InvalidArgumentException('No quedó ningún documento marcado para emitir.');
            }
            $total = round(array_sum(array_map(fn($f) => (float) $f['valor'], $elegidas)), 2);
            $id = $this->repo->insertEmision($idEmpresa, $d + ['total_items' => count($elegidas), 'total_valor' => $total], $idUsuario);
            foreach ($elegidas as $f) {
                $this->repo->insertItem($id, $idEmpresa, $f, $d['enviar_correo'], $idUsuario);
            }
            $guardado->registrar($token, $idEmpresa, self::MODULO_GUARDADO, $id, null, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'Emisión en bloque (condominio)', 'condominios_emisiones', $id, null, [
                'descripcion' => $d['descripcion'], 'tipo_comprobante' => $d['tipo_comprobante'], 'id_producto' => $d['id_producto'],
                'documentos' => count($elegidas), 'total' => $total, 'enviar_correo' => $d['enviar_correo'],
            ]);
            $db->commit();
            return ['id' => $id, 'items' => count($elegidas), 'previo' => false];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function cancelarEmision(int $id, int $idEmpresa, int $idUsuario): void
    {
        $em = $this->repo->getEmision($id, $idEmpresa);
        if (!$em) {
            throw new \DomainException('La emisión no existe.');
        }
        if (!in_array($em['estado'], ['pendiente', 'procesando'], true)) {
            throw new \DomainException('La emisión ya terminó; no se puede cancelar.');
        }
        $this->repo->marcarEmision($id, 'cancelado', fin: true);
        $this->log->registrar($idUsuario, $idEmpresa, 'Cancelar emisión en bloque (condominio)', 'condominios_emisiones', $id, ['estado' => $em['estado']], ['estado' => 'cancelado']);
    }

    /** Estado para el seguimiento en pantalla (sondeo). */
    public function estadoEmision(int $id, int $idEmpresa, bool $conItems = false): array
    {
        $em = $this->repo->getEmision($id, $idEmpresa);
        if (!$em) {
            throw new \DomainException('La emisión no existe.');
        }
        $em['pendientes'] = max(0, (int) $em['total_items'] - (int) $em['generados'] - (int) $em['fallidos']);
        return ['emision' => $em, 'items' => $conItems ? $this->repo->getItems($id, $idEmpresa) : []];
    }

    /**
     * Worker: genera los documentos pendientes de la emisión, uno por uno. Un solo worker por
     * emisión (candado de sesión); idempotente: si ya terminó o la cancelaron, no hace nada.
     * Un ítem que quedó a medias por un corte NO se repite (podría duplicar el documento): queda
     * «para revisar».
     */
    public function procesarEmision(int $idEmision): void
    {
        $em = $this->repo->getEmision($idEmision);
        if (!$em || !in_array($em['estado'], ['pendiente', 'procesando'], true)) {
            return;
        }
        if (!$this->repo->tomarCandado($idEmision)) {
            return; // otro worker la está procesando
        }
        $em['enviar_correo'] = in_array($em['enviar_correo'], [true, 't', 'true', 1, '1'], true);
        try {
            $this->repo->marcarInterrumpidos($idEmision);
            $this->repo->marcarEmision($idEmision, 'procesando', inicio: true);

            $idEmpresa = (int) $em['id_empresa'];
            $idUsuario = (int) ($em['created_by'] ?? 0);
            $serie     = $this->repo->getSerie((int) $em['id_punto_emision'], $idEmpresa);
            $producto  = $this->repo->getProductoCobro((int) $em['id_producto'], $idEmpresa);
            $empresa   = (new \App\models\Empresa())->getPorId($idEmpresa) ?? [];
            if (!$serie || !$producto || !$empresa) {
                throw new \RuntimeException('La serie, el concepto o la empresa de la emisión ya no existen.');
            }
            $facturacion = $this->facturacion();

            while (true) {
                $actual = $this->repo->getEmision($idEmision);
                if (!$actual || $actual['estado'] === 'cancelado') {
                    return;
                }
                $idItem = $this->repo->reclamarSiguienteItem($idEmision);
                if ($idItem === null) {
                    break;
                }
                $this->procesarItem($idItem, $em, $serie, $producto, $empresa, $facturacion, $idEmpresa, $idUsuario);
                $this->repo->recontar($idEmision);
            }

            $c = $this->repo->recontar($idEmision);
            $this->repo->marcarEmision($idEmision, (int) $c['fallidos'] > 0 ? 'completado_con_errores' : 'completado', fin: true);
            try {
                $this->log->registrar($idUsuario, $idEmpresa, 'Procesar emisión en bloque (condominio)', 'condominios_emisiones', $idEmision, null, $c);
            } catch (\Throwable) {
            }
        } finally {
            $this->repo->soltarCandado($idEmision);
        }
    }

    private function procesarItem(int $idItem, array $em, array $serie, array $producto, array $empresa, SuscripcionFacturacionService $facturacion, int $idEmpresa, int $idUsuario): void
    {
        $it = $this->repo->getItemParaGenerar($idItem);
        if (!$it) {
            return;
        }
        $email = trim((string) ($it['cliente_email_actual'] ?: $it['email']));

        // 1) Documento (mismo punto de generación que Suscripciones).
        try {
            $info = [];
            if ($em['agrupar'] === 'cliente' && trim((string) $it['inmueble_texto']) !== '') {
                $info[] = ['concepto' => 'Inmueble', 'detalle' => $it['inmueble_texto']];
            }
            $susc = [
                'id'                 => 'emisión ' . $em['id'],
                'id_cliente'         => (int) $it['id_cliente'],
                'tipo_comprobante'   => $em['tipo_comprobante'],
                'forma_cobro'        => 'credito',
                'cliente_email'      => $email,
                'id_unidad'          => $em['agrupar'] === 'inmueble' ? (int) $it['id_unidad'] : null,
                'info_adicional'     => $info ? json_encode($info, JSON_UNESCAPED_UNICODE) : null,
                'periodicidad_meses' => 1,
                'periodicidad_codigo' => '',
            ];
            $detalle = [[
                'id_producto'       => (int) $producto['id'],
                'codigo_producto'   => (string) ($producto['codigo'] ?: '000'),
                'descripcion'       => $producto['nombre'],
                'nombre_producto'   => $producto['nombre'],
                'cantidad'          => 1,
                'precio_unitario'   => (float) $it['valor'],
                'porcentaje_iva'    => (float) $producto['porcentaje_iva'],
                'codigo_porcentaje' => (string) $producto['codigo_porcentaje'],
                'id_tarifa_iva'     => $producto['id_tarifa_iva'] ? (int) $producto['id_tarifa_iva'] : null,
            ]];
            $extras = ['texto_item' => (string) ($em['texto_item'] ?? ''), 'info_concepto' => 'Concepto', 'info_detalle' => (string) $em['descripcion']];
            $res = $facturacion->generarUnPeriodo($idEmpresa, $idUsuario, $susc, $detalle, $serie, $empresa, $extras, date('Y-m-d'));
        } catch (\Throwable $e) {
            $this->repo->actualizarItem($idItem, ['estado' => 'error', 'mensaje' => mb_substr($e->getMessage(), 0, 1000),
                                                  'estado_correo' => $em['enviar_correo'] ? 'error' : 'no_aplica']);
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'procesarItem#' . $idItem]);
            return;
        }

        $esFactura = $res['tipo'] === 'factura';
        $idDoc = (int) ($esFactura ? $res['id_factura'] : $res['id_recibo']);
        $doc   = $this->documento($esFactura, $idDoc, $idEmpresa);
        $this->repo->actualizarItem($idItem, [
            'estado' => 'generado', $esFactura ? 'id_factura' : 'id_recibo' => $idDoc, 'numero' => $this->numero($doc),
        ]);

        // 2) Facturas: al SRI (el correo de una factura necesita la autorización).
        $autorizada = false;
        if ($esFactura) {
            try {
                $r = (new \App\Services\Sri\SriEnvioService())->enviarFacturaVenta($idDoc, $idEmpresa, $idUsuario);
                $estadoSri = (string) ($r['estado'] ?? '');
                $autorizada = in_array($estadoSri, ['autorizado', 'autorizada'], true);
                $estadoItem = $autorizada ? 'autorizado' : ($estadoSri === 'en_procesamiento' ? 'en_procesamiento' : 'generado');
                $msg = $autorizada ? null : 'SRI: ' . trim((string) ($r['mensaje'] ?? $estadoSri)) . ' La factura quedó creada; reenvíela desde Facturas de Venta.';
                $this->repo->actualizarItem($idItem, ['estado' => $estadoItem, 'mensaje' => $msg]);
            } catch (\Throwable $e) {
                $this->repo->actualizarItem($idItem, ['mensaje' => 'SRI: ' . mb_substr($e->getMessage(), 0, 800) . ' La factura quedó creada; reenvíela desde Facturas de Venta.']);
            }
        }

        // 3) Correo.
        if (!$em['enviar_correo']) {
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->repo->actualizarItem($idItem, ['estado_correo' => 'sin_correo']);
            return;
        }
        try {
            if ($esFactura) {
                if (!$autorizada) {
                    $this->repo->actualizarItem($idItem, ['estado_correo' => 'pendiente']);
                    return;
                }
                if ($this->repo->estadoCorreoFactura($idDoc) === 'enviado') { // ya lo mandó el envío automático
                    $this->repo->actualizarItem($idItem, ['estado_correo' => 'enviado']);
                    return;
                }
                $ok = $this->enviarFactura($idDoc, $idEmpresa, $email);
                if ($ok) {
                    $this->repo->marcarCorreoFacturaEnviado($idDoc);
                }
            } else {
                $ok = $this->enviarRecibo($idDoc, $idEmpresa, $email, (string) $it['cliente_nombre'], (string) $em['descripcion']);
            }
            $this->repo->actualizarItem($idItem, ['estado_correo' => $ok ? 'enviado' : 'error']);
        } catch (\Throwable $e) {
            $this->repo->actualizarItem($idItem, ['estado_correo' => 'error']);
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'correo#' . $idItem]);
        }
    }

    /** Retoma las emisiones que quedaron abiertas (cron): relanza su worker. */
    public function retomarAbiertas(): array
    {
        if (!$this->repo->instalado()) {
            return [];
        }
        $lanzadas = [];
        foreach ($this->repo->getEmisionesAbiertas() as $id) {
            if (\App\Helpers\ProcesoSegundoPlano::lanzar('procesar_emision_condominios.php', ['emision' => (int) $id])) {
                $lanzadas[] = (int) $id;
            }
        }
        return $lanzadas;
    }

    // ── 2. Agregar a suscripciones (cobro recurrente, sin duplicar) ─────────

    /** @return array{creadas:int, actualizadas:int, agregadas:int} */
    public function aplicarSuscripciones(array $data, int $idEmpresa, int $idUsuario): array
    {
        $d = $this->validar($data, $idEmpresa);
        if ($d['modo'] !== 'suscripcion') {
            throw new \InvalidArgumentException('Modo no válido.');
        }
        $prod = $d['producto'];
        $suscRepo = new SuscripcionesRepository();
        $condominio = CondominioService::crear();
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            // Dos usuarios aplicando a la vez podrían crear la misma suscripción dos veces: se
            // serializa por empresa ANTES de leer qué existe (CLAUDE.md §8).
            $st = $db->prepare("SELECT pg_advisory_xact_lock(hashtext('cond_suscripciones:' || :e::text))");
            $st->execute([':e' => $idEmpresa]);

            $elegidas = $this->filasElegidas($this->construirFilas($d, $idEmpresa), $d['seleccion'], ['crear', 'actualizar', 'agregar']);
            if (!$elegidas) {
                throw new \InvalidArgumentException('No hay cambios marcados para aplicar.');
            }
            $n = ['creadas' => 0, 'actualizadas' => 0, 'agregadas' => 0];
            $linea = fn(int $idSusc, float $valor) => [
                'id_suscripcion' => $idSusc, 'id_empresa' => $idEmpresa, 'id_producto' => (int) $prod['id'], 'descripcion' => $prod['nombre'],
                'cantidad' => 1, 'precio_unitario' => $valor, 'porcentaje_iva' => (float) $prod['porcentaje_iva'],
                'id_tarifa_iva' => $prod['id_tarifa_iva'], 'orden' => 99, 'id_usuario' => $idUsuario,
            ];
            foreach ($elegidas as $f) {
                $valor = (float) $f['valor'];
                if ($f['accion'] === 'actualizar') {
                    $suscRepo->updatePrecioDetalle((int) $f['id_detalle'], $idEmpresa, $valor, $idUsuario);
                    $this->log->registrar($idUsuario, $idEmpresa, 'Cobro recurrente (condominio): actualizar valor', 'suscripciones', (int) $f['id_suscripcion'],
                        ['precio_unitario' => $f['actual']], ['precio_unitario' => $valor, 'id_producto' => (int) $prod['id']]);
                    $n['actualizadas']++;
                } elseif ($f['accion'] === 'agregar') {
                    $suscRepo->insertDetalle($linea((int) $f['id_suscripcion'], $valor));
                    $this->log->registrar($idUsuario, $idEmpresa, 'Cobro recurrente (condominio): agregar línea', 'suscripciones', (int) $f['id_suscripcion'],
                        null, ['id_producto' => (int) $prod['id'], 'precio_unitario' => $valor]);
                    $n['agregadas']++;
                } else {
                    $datos = [
                        'id_empresa' => $idEmpresa, 'id_cliente' => (int) $f['id_cliente'], 'id_periodicidad' => $d['id_periodicidad'],
                        'fecha_inicio' => $d['fecha_inicio'], 'fecha_fin' => null, 'proximo_cobro' => $d['fecha_inicio'],
                        'forma_cobro' => 'credito', 'estado' => 'activo', 'tipo_comprobante' => $d['tipo_comprobante'],
                        'observaciones' => 'Creada desde Configuración de condominios (Condóminos).', 'info_adicional' => null,
                        'id_unidad' => $f['id_unidad'], 'id_usuario' => $idUsuario,
                    ];
                    $idS = $suscRepo->create($datos);
                    $suscRepo->insertDetalle($linea($idS, $valor));
                    if ($f['id_unidad']) {
                        $condominio->espejarSuscripcion($idS, (int) $f['id_unidad'], $idEmpresa);
                    }
                    $this->log->registrar($idUsuario, $idEmpresa, 'Cobro recurrente (condominio): crear suscripción', 'suscripciones', $idS, null,
                        $datos + ['id_producto' => (int) $prod['id'], 'precio_unitario' => $valor]);
                    $n['creadas']++;
                }
            }
            $db->commit();
            return $n;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // ── Apoyo: documentos, PDF y correo ─────────────────────────────────────

    private function facturaService(): FacturaVentaService
    {
        return new FacturaVentaService(new \App\repositories\modulos\FacturaVentaRepository(), new \App\Rules\modulos\FacturaVentaRules(), new LogSistemaService());
    }

    private function reciboService(): ReciboVentaService
    {
        return new ReciboVentaService(new \App\repositories\modulos\ReciboVentaRepository(), new \App\Rules\modulos\ReciboVentaRules(), new LogSistemaService());
    }

    private function facturacion(): SuscripcionFacturacionService
    {
        return new SuscripcionFacturacionService($this->facturaService(), new \App\Services\SecuencialService(), $this->reciboService());
    }

    private function documento(bool $esFactura, int $id, int $idEmpresa): ?array
    {
        return $esFactura ? $this->facturaService()->getPorId($id, $idEmpresa) : $this->reciboService()->getPorId($id, $idEmpresa);
    }

    private function numero(?array $doc): ?string
    {
        if (!$doc) {
            return null;
        }
        $partes = [$doc['establecimiento'] ?? '', $doc['punto_emision'] ?? '', $doc['secuencial'] ?? ''];
        return implode('', $partes) === '' ? null : mb_substr(implode('-', $partes), 0, 30);
    }

    /** Datos de la empresa para el PDF (mismo armado que la pantalla de Facturas / Recibos). */
    private function empresaPdf(int $idEmpresa, ?int $idPunto): array
    {
        $model = new \App\models\Empresa();
        $empresa = $model->getPorId($idEmpresa) ?? [];
        $est = $model->getEstablecimientos($idEmpresa)[0] ?? null;
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

    private function enviarFactura(int $idFactura, int $idEmpresa, string $email): bool
    {
        $f = $this->facturaService()->getPorId($idFactura, $idEmpresa);
        if (!$f) {
            return false;
        }
        $empresa = $this->empresaPdf($idEmpresa, isset($f['id_punto_emision']) ? (int) $f['id_punto_emision'] : null);
        $renderer = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'factura_venta');
        $pdf = $plantilla
            ? $renderer->generar($plantilla, $f, $f['detalles'], $f['pagos'], $f['info_adicional'], $empresa, 'S')
            : (new FacturaVentaPdfService())->generar($f, $f['detalles'], $f['pagos'], $f['info_adicional'], $empresa, 'S');
        return (new \App\Services\EnvioDocumentosSRIService())->enviarSiAplica(
            $idEmpresa, 'factura_venta', $f, (string) ($f['detalle_xml'] ?? ''), (string) $pdf, (string) ($f['numero_autorizacion'] ?? ''), true, $email
        );
    }

    private function enviarRecibo(int $idRecibo, int $idEmpresa, string $email, string $nombre, string $concepto): bool
    {
        $r = $this->reciboService()->getPorId($idRecibo, $idEmpresa);
        if (!$r) {
            return false;
        }
        $empresa = $this->empresaPdf($idEmpresa, isset($r['id_punto_emision']) ? (int) $r['id_punto_emision'] : null);
        $renderer = new \App\Services\PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'recibo_venta');
        $pdf = $plantilla
            ? $renderer->generar($plantilla, $r, $r['detalles'], $r['pagos'], $r['info_adicional'], $empresa, 'S')
            : (new ReciboVentaPdfService())->generarBytes($r, $r['detalles'], $r['pagos'], $r['info_adicional'], $empresa);
        $numero = $this->numero($r) ?? (string) $idRecibo;
        $nomEmpresa = (string) (($empresa['nombre_comercial'] ?? '') ?: ($empresa['nombre'] ?? ''));
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $cuerpo = '<p>Estimado/a ' . $h($nombre) . ':</p>'
            . '<p>Adjuntamos el recibo <b>' . $h($numero) . '</b> por <b>' . $h($concepto) . '</b>, por un valor de <b>$'
            . number_format((float) ($r['importe_total'] ?? 0), 2) . '</b>.</p>'
            . '<p>Saludos cordiales,<br>' . $h($nomEmpresa) . '</p>';
        return (new \App\Services\EnvioDocumentosSRIService())->enviarPdfSimple(
            $idEmpresa, $email, $nombre, 'Recibo ' . $numero . ' - ' . $nomEmpresa, $cuerpo, (string) $pdf, 'Recibo_' . $numero, $nomEmpresa
        );
    }
}
