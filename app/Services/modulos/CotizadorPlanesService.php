<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CotizadorPlanesRepository;
use App\repositories\modulos\ProformaRepository;
use App\Rules\modulos\CotizadorPlanesRules;
use App\Rules\modulos\ProformaRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * Cotizador de Planes: orquesta catálogo de precios, cotizaciones y la proforma
 * que se genera desde ellas (CLAUDE.md §3: Controller → Service → Rules → Repository).
 * Diseño: docs/diseno/cotizador-planes-fase1.md
 */
class CotizadorPlanesService
{
    public const MODULO_GUARDADO = 'cotizador_planes';

    public function __construct(
        private CotizadorPlanesRepository $repo,
        private CotizadorPlanesRules $rules,
        private LogSistemaService $log
    ) {}

    public static function crear(): self
    {
        return new self(new CotizadorPlanesRepository(), new CotizadorPlanesRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    // ── Configuración ────────────────────────────────────────────────────────

    /** Configuración de la empresa con los valores por defecto si aún no se guardó. */
    public function getConfig(int $idEmpresa): array
    {
        $row = $this->repo->getConfig($idEmpresa) ?? [];
        $p = CotizadorPlanesCalculo::normalizarParametros($row);
        $p['id_producto_resumen_anual']   = (int) ($row['id_producto_resumen_anual'] ?? 0) ?: null;
        $p['id_producto_resumen_mensual'] = (int) ($row['id_producto_resumen_mensual'] ?? 0) ?: null;
        $p['dias_vigencia']               = (int) ($row['dias_vigencia'] ?? 15) ?: 15;
        foreach (['anual', 'mensual'] as $m) {
            $idP = $p['id_producto_resumen_' . $m];
            $prod = $idP ? $this->repo->getProductoConTarifa($idP, $idEmpresa) : null;
            $p['producto_resumen_' . $m] = $prod ? trim(($prod['codigo'] ?? '') . ' - ' . ($prod['nombre'] ?? ''), ' -') : '';
        }
        return $p;
    }

    public function guardarConfig(int $idEmpresa, array $d, int $idUsuario): void
    {
        $nuevo = CotizadorPlanesCalculo::normalizarParametros($d) + [
            'id_producto_resumen_anual'   => (int) ($d['id_producto_resumen_anual'] ?? 0) ?: null,
            'id_producto_resumen_mensual' => (int) ($d['id_producto_resumen_mensual'] ?? 0) ?: null,
            'dias_vigencia'               => (int) ($d['dias_vigencia'] ?? 15),
        ];
        // Los valores crudos se validan antes de normalizar (para no corregir en silencio un 150 %).
        $this->rules->validarConfig($d + $nuevo);
        foreach (['id_producto_resumen_anual', 'id_producto_resumen_mensual'] as $c) {
            if ($nuevo[$c] && !$this->repo->getProductoConTarifa($nuevo[$c], $idEmpresa)) {
                throw new \InvalidArgumentException('El producto elegido para la línea resumen no existe en esta empresa.');
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $antes = $this->repo->getConfig($idEmpresa);
            $id = $this->repo->guardarConfig($idEmpresa, $nuevo, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, $antes ? 'actualizar' : 'crear', 'cotizador_planes_config', $id, $antes, $nuevo);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ── Catálogo de precios ──────────────────────────────────────────────────

    /** Catálogo con sus dependencias (lista de ids requeridos en cada ítem). */
    public function getCatalogo(int $idEmpresa, bool $soloActivos = false): array
    {
        $deps = $this->repo->getDependencias($idEmpresa);
        $items = $this->repo->getItems($idEmpresa, $soloActivos);
        foreach ($items as &$it) {
            $it['id'] = (int) $it['id'];
            $it['precio_anual'] = (float) $it['precio_anual'];
            $it['aplica_descuento_establecimiento'] = $this->bool($it['aplica_descuento_establecimiento']);
            $it['por_defecto'] = $this->bool($it['por_defecto']);
            $it['requiere'] = $deps[$it['id']] ?? [];
        }
        unset($it);
        return $items;
    }

    public function guardarItem(int $idEmpresa, array $d, int $idUsuario): int
    {
        $id = (int) ($d['id'] ?? 0);
        $item = [
            'tipo'         => ($d['tipo'] ?? 'modulo') === 'servicio' ? 'servicio' : 'modulo',
            'id_submodulo' => (int) ($d['id_submodulo'] ?? 0) ?: null,
            'ruta'         => trim((string) ($d['ruta'] ?? '')),
            'grupo'        => trim((string) ($d['grupo'] ?? '')),
            'nombre'       => trim((string) ($d['nombre'] ?? '')),
            'descripcion'  => trim((string) ($d['descripcion'] ?? '')),
            'precio_anual' => is_numeric($d['precio_anual'] ?? null) ? round((float) $d['precio_anual'], 2) : ($d['precio_anual'] ?? null),
            'cobro'        => (string) ($d['cobro'] ?? 'recurrente'),
            'aplica_descuento_establecimiento' => $this->bool($d['aplica_descuento_establecimiento'] ?? false),
            'por_defecto'  => $this->bool($d['por_defecto'] ?? false),
            'id_producto'  => (int) ($d['id_producto'] ?? 0) ?: null,
            'orden'        => (int) ($d['orden'] ?? 0),
            'estado'       => ($d['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo',
        ];
        if ($item['tipo'] === 'servicio') {
            $item['id_submodulo'] = null;
            $item['ruta'] = '';
        }
        if ($item['cobro'] === 'unico') {
            // Lo único no entra en el descuento por establecimiento (no es recurrente).
            $item['aplica_descuento_establecimiento'] = false;
        }
        $this->rules->validarItem($item);
        if ($item['id_submodulo'] && $this->repo->existeItemSubmodulo($idEmpresa, $item['id_submodulo'], $id ?: null)) {
            throw new \InvalidArgumentException('Ese submódulo ya está en el catálogo de precios.');
        }
        if ($item['id_producto'] && !$this->repo->getProductoConTarifa($item['id_producto'], $idEmpresa)) {
            throw new \InvalidArgumentException('El producto elegido no existe en esta empresa.');
        }
        $requiere = array_values(array_unique(array_filter(array_map('intval', (array) ($d['requiere'] ?? [])))));
        foreach ($requiere as $r) {
            if ($r === $id || !$this->repo->getItem($r, $idEmpresa)) {
                throw new \InvalidArgumentException('Una de las dependencias elegidas no es válida.');
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            if ($id > 0) {
                $antes = $this->repo->getItem($id, $idEmpresa);
                if (!$antes) throw new \InvalidArgumentException('El ítem ya no existe.');
                $this->repo->updateItem($id, $idEmpresa, $item, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'cotizador_planes_items', $id, $antes, $item + ['requiere' => $requiere]);
            } else {
                $id = $this->repo->insertItem($idEmpresa, $item, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'cotizador_planes_items', $id, null, $item + ['requiere' => $requiere]);
            }
            $this->repo->reemplazarDependencias($idEmpresa, $id, $requiere, $idUsuario);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminarItem(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $antes = $this->repo->getItem($id, $idEmpresa);
            if (!$antes) throw new \InvalidArgumentException('El ítem ya no existe.');
            $this->repo->eliminarItem($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'cotizador_planes_items', $id, $antes, null);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ── Cálculo ──────────────────────────────────────────────────────────────

    /**
     * Resuelve lo que eligió el vendedor contra el catálogo vigente: agrega en cadena
     * los ítems que cada uno requiere (por_dependencia = true) y toma los precios del
     * catálogo. Los ítems que ya no están activos se descartan y se avisa.
     *
     * $entrada: [['codigo','nombre','direccion','elegidos'=>[id_item,…]], …]
     * @return array{0: array, 1: string[]} establecimientos resueltos y avisos
     */
    public function resolverEstablecimientos(int $idEmpresa, array $entrada): array
    {
        $catalogo = [];
        foreach ($this->getCatalogo($idEmpresa, true) as $it) {
            $catalogo[$it['id']] = $it;
        }
        $avisos = [];
        $out = [];
        foreach (array_values($entrada) as $i => $e) {
            $elegidos = array_values(array_unique(array_map('intval', (array) ($e['elegidos'] ?? []))));
            $orden = []; // id_item => por_dependencia
            $pendientes = [];
            foreach ($elegidos as $idI) {
                if (!isset($catalogo[$idI])) {
                    $avisos['x' . $idI] = 'Se quitó un ítem que ya no está activo en el catálogo de precios.';
                    continue;
                }
                $orden[$idI] = false;
                $pendientes[] = $idI;
            }
            // Dependencias en cadena (con conjunto de visitados: un ciclo no se repite).
            while ($pendientes) {
                $actual = array_shift($pendientes);
                foreach ($catalogo[$actual]['requiere'] ?? [] as $req) {
                    if (!isset($catalogo[$req]) || array_key_exists($req, $orden)) continue;
                    $orden[$req] = true;
                    $pendientes[] = $req;
                }
            }
            $items = [];
            foreach ($orden as $idI => $porDep) {
                $c = $catalogo[$idI];
                $items[] = [
                    'id_item'      => $idI,
                    'nombre'       => $c['nombre'],
                    'grupo'        => $c['grupo'] ?? '',
                    'tipo'         => $c['tipo'],
                    'cobro'        => $c['cobro'],
                    'precio_anual' => (float) $c['precio_anual'],
                    'aplica_descuento_establecimiento' => (bool) $c['aplica_descuento_establecimiento'],
                    'por_dependencia' => $porDep,
                    'id_producto'  => $c['id_producto'] ?? null,
                    'requerido_por' => [],
                ];
            }
            // Para la pantalla: quién arrastró a cada dependencia.
            $idx = array_flip(array_column($items, 'id_item'));
            foreach ($items as $it) {
                foreach ($catalogo[$it['id_item']]['requiere'] ?? [] as $req) {
                    if (isset($idx[$req])) $items[$idx[$req]]['requerido_por'][] = $it['nombre'];
                }
            }
            usort($items, fn($a, $b) => [$a['cobro'] === 'unico', $a['grupo'], $a['nombre']] <=> [$b['cobro'] === 'unico', $b['grupo'], $b['nombre']]);
            $out[] = [
                'codigo'    => str_pad(preg_replace('/\D/', '', (string) ($e['codigo'] ?? '')) ?: (string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'nombre'    => trim((string) ($e['nombre'] ?? '')),
                'direccion' => trim((string) ($e['direccion'] ?? '')),
                'elegidos'  => array_keys(array_filter($orden, fn($d) => !$d)),
                'items'     => $items,
            ];
        }
        return [$out, array_values($avisos)];
    }

    /** Cálculo en vivo para la pantalla (no guarda nada). */
    public function calcular(int $idEmpresa, array $d): array
    {
        $config = $this->getConfig($idEmpresa);
        [$est, $avisos] = $this->resolverEstablecimientos($idEmpresa, (array) ($d['establecimientos'] ?? []));
        $calc = CotizadorPlanesCalculo::calcular($config, $est, max(1, (int) ($d['usuarios'] ?? 1)), (float) ($d['descuento_vendedor_pct'] ?? 0));
        foreach ($est as $i => $e) {
            $calc['establecimientos'][$i]['items'] = $e['items'];
            $calc['establecimientos'][$i]['direccion'] = $e['direccion'];
        }
        $calc['avisos'] = $avisos;
        return $calc;
    }

    // ── Cotizaciones ─────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idUsuarioFiltro);
    }

    /** Cotización completa para abrir el modal. */
    public function getFicha(int $id, int $idEmpresa): ?array
    {
        $c = $this->repo->getPorId($id, $idEmpresa);
        if (!$c) return null;
        $det = $this->repo->getDetalle($id, $idEmpresa);
        $porEst = [];
        foreach ($det as $d) {
            $d['precio_anual'] = (float) $d['precio_anual'];
            $d['aplica_descuento_establecimiento'] = $this->bool($d['aplica_descuento_establecimiento']);
            $d['por_dependencia'] = $this->bool($d['por_dependencia']);
            $porEst[(int) $d['id_establecimiento']][] = $d;
        }
        $c['establecimientos'] = [];
        foreach ($this->repo->getEstablecimientos($id, $idEmpresa) as $e) {
            $items = $porEst[(int) $e['id']] ?? [];
            $c['establecimientos'][] = [
                'codigo'    => $e['codigo'],
                'nombre'    => $e['nombre'] ?? '',
                'direccion' => $e['direccion'] ?? '',
                'es_principal' => $this->bool($e['es_principal']),
                'elegidos'  => array_values(array_map(fn($x) => (int) $x['id_item'], array_filter($items, fn($x) => !$x['por_dependencia']))),
                'items'     => $items,
            ];
        }
        $c['parametros'] = $c['parametros'] ? json_decode((string) $c['parametros'], true) : null;
        $c['obligado_contabilidad'] = $c['obligado_contabilidad'] === null ? null : $this->bool($c['obligado_contabilidad']);
        return $c;
    }

    /** Datos de la cabecera normalizados desde la petición. */
    private function normalizarCabecera(array $d): array
    {
        $tipo = (string) ($d['tipo_contribuyente'] ?? '');
        $obl  = $d['obligado_contabilidad'] ?? '';
        return [
            'fecha'              => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha'] ?? '')) ? $d['fecha'] : date('Y-m-d'),
            'identificacion'     => preg_replace('/\D/', '', (string) ($d['identificacion'] ?? '')),
            'razon_social'       => mb_substr(trim((string) ($d['razon_social'] ?? '')), 0, 300),
            'nombre_comercial'   => mb_substr(trim((string) ($d['nombre_comercial'] ?? '')), 0, 300),
            'tipo_contribuyente' => in_array($tipo, ['persona_natural', 'sociedad'], true) ? $tipo : '',
            'obligado_contabilidad' => $obl === '' || $obl === null ? null : $this->bool($obl),
            'correo'             => mb_strtolower(trim((string) ($d['correo'] ?? ''))),
            'telefono'           => trim((string) ($d['telefono'] ?? '')),
            'direccion'          => mb_substr(trim((string) ($d['direccion'] ?? '')), 0, 500),
            'cod_prov'           => mb_substr(trim((string) ($d['cod_prov'] ?? '')), 0, 10),
            'cod_ciudad'         => mb_substr(trim((string) ($d['cod_ciudad'] ?? '')), 0, 10),
            'contacto_nombre'    => mb_substr(trim((string) ($d['contacto_nombre'] ?? '')), 0, 200),
            'usuarios'           => (int) ($d['usuarios'] ?? 0),
            'modalidad'          => ($d['modalidad'] ?? 'anual') === 'mensual' ? 'mensual' : 'anual',
            'descuento_vendedor_pct' => round((float) ($d['descuento_vendedor_pct'] ?? 0), 2),
            'observaciones'      => trim((string) ($d['observaciones'] ?? '')),
            'id_vendedor'        => (int) ($d['id_vendedor'] ?? 0) ?: null,
        ];
    }

    /**
     * Crea o actualiza una cotización en borrador. Precios y reglas se copian del
     * catálogo y de la configuración vigentes. Guardado único (§8) en el alta.
     * @return array{id:int, numero:int, previo:bool}
     */
    public function guardar(int $idEmpresa, array $d, int $idUsuario): array
    {
        $id   = (int) ($d['id'] ?? 0);
        $cab  = $this->normalizarCabecera($d);
        $config = $this->getConfig($idEmpresa);
        [$est, ] = $this->resolverEstablecimientos($idEmpresa, (array) ($d['establecimientos'] ?? []));
        $this->rules->validarCotizacion($cab + ['establecimientos' => $est], $config);

        $calc = CotizadorPlanesCalculo::calcular($config, $est, $cab['usuarios'], $cab['descuento_vendedor_pct']);
        $cab['total_anual']   = $calc['total_anual'];
        $cab['total_mensual'] = $calc['total_mensual'];
        $cab['total_unico']   = $calc['total_unico'];
        $cab['parametros']    = $calc['parametros'];

        $guardado = new GuardadoUnicoService();
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            if ($id <= 0 && ($previo = $guardado->previo($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO))) {
                $db->rollBack();
                return ['id' => (int) $previo['id_registro'], 'numero' => (int) $previo['numero'], 'previo' => true];
            }

            if ($id > 0) {
                $antes = $this->repo->bloquear($id, $idEmpresa);
                if (!$antes) throw new \InvalidArgumentException('La cotización ya no existe.');
                if ($antes['estado'] !== 'borrador') {
                    throw new \InvalidArgumentException('Solo se modifica una cotización en borrador. Esta ya generó su proforma o está anulada.');
                }
                $numero = (int) $antes['numero'];
                $this->repo->updateCabecera($id, $idEmpresa, $cab, $idUsuario);
                $this->repo->eliminarHijos($id, $idEmpresa, $idUsuario);
            } else {
                $antes = null;
                $numero = $this->repo->siguienteNumero($idEmpresa);
                $id = $this->repo->insertCabecera($idEmpresa, $numero, $cab, $idUsuario);
            }

            foreach ($est as $i => $e) {
                $e['es_principal'] = $calc['establecimientos'][$i]['es_principal'] ?? false;
                $idEst = $this->repo->insertEstablecimiento($id, $idEmpresa, $e, $i, $idUsuario);
                foreach ($e['items'] as $j => $it) {
                    $this->repo->insertDetalle($id, $idEst, $idEmpresa, $it, $j, $idUsuario);
                }
            }

            $this->log->registrar($idUsuario, $idEmpresa, $antes ? 'actualizar' : 'crear', 'cotizaciones_planes', $id, $antes,
                $cab + ['numero' => $numero, 'establecimientos' => array_map(fn($e) => ['codigo' => $e['codigo'], 'items' => array_column($e['items'], 'nombre')], $est)]);
            if (!$antes) {
                $guardado->registrar($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO, $id, (string) $numero, $idUsuario);
            }
            $db->commit();
            return ['id' => $id, 'numero' => $numero, 'previo' => false];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $antes = $this->repo->bloquear($id, $idEmpresa);
            if (!$antes) throw new \InvalidArgumentException('La cotización ya no existe.');
            if (!empty($antes['id_proforma'])) {
                throw new \InvalidArgumentException('Esta cotización ya generó su proforma: no se elimina. Anule la proforma desde Proformas si ya no aplica.');
            }
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'cotizaciones_planes', $id, $antes, null);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    // ── Apoyo para la pantalla ───────────────────────────────────────────────

    public function getVendedores(int $idEmpresa): array
    {
        return (new \App\repositories\modulos\CrmCatalogoRepository())->vendedores($idEmpresa);
    }

    /** Submódulos del menú (catálogo global) para armar el catálogo de precios. */
    public function getSubmodulosMenu(): array
    {
        $out = [];
        foreach ((new \App\models\PermisoSubmodulo())->getCatalogoSubmodulos() as $s) {
            if ((int) $s['id_submodulo'] <= 0) continue;
            $out[] = [
                'id_submodulo' => (int) $s['id_submodulo'],
                'grupo'        => (string) $s['nombre_modulo'],
                'nombre'       => (string) $s['nombre_submodulo'],
                'ruta'         => (string) $s['ruta'],
            ];
        }
        return $out;
    }

    public function buscarProductos(int $idEmpresa, string $q): array
    {
        return $this->repo->buscarProductos($idEmpresa, $q);
    }

    /** Series activas con secuencial de Proformas configurado. */
    public function getSeriesProforma(int $idEmpresa): array
    {
        $secRepo = new \App\repositories\SecuencialRepository();
        $out = [];
        foreach ($secRepo->getPuntosEmisionSerie($idEmpresa, true) as $p) {
            if (empty($secRepo->getConfigSecuencial((int) $p['id'], 'Proformas')['id'])) continue;
            $out[] = [
                'id'    => (int) $p['id'],
                'texto' => $p['establecimiento'] . '-' . $p['punto'] . ($p['nombre'] !== '' ? ' · ' . $p['nombre'] : ''),
            ];
        }
        return $out;
    }

    /**
     * RUC o cédula → datos del SRI (con los establecimientos del RUC) y, si ya es
     * cliente de la empresa, sus datos de contacto. La consulta al SRI se hace sin
     * empresa para obtener siempre la lista de establecimientos del SRI.
     */
    public function consultarIdentificacion(string $identificacion, int $idEmpresa): array
    {
        $cliente = $this->repo->getClientePorIdentificacion($idEmpresa, $identificacion);
        $sri = (new \App\Services\SriIdentificationService())->consultar($identificacion);
        if (empty($sri['ok']) && !$cliente) {
            return ['ok' => false, 'error' => $sri['error'] ?? 'No se pudo consultar el SRI. Escriba los datos a mano.'];
        }
        $d = $sri['data'] ?? [];
        $tipo = match ((string) ($d['tipo'] ?? '')) {
            '1' => 'persona_natural',
            '3' => 'sociedad',
            default => strlen($identificacion) === 10 ? 'persona_natural' : '',
        };
        return [
            'ok' => true,
            'sri_ok' => !empty($sri['ok']),
            'aviso' => empty($sri['ok']) ? ('El SRI no respondió: se usan los datos del cliente ya registrado. ' . ($sri['error'] ?? '')) : ($sri['advertencia'] ?? null),
            'data' => [
                'razon_social'       => $d['nombre'] ?? ($cliente['nombre'] ?? ''),
                'nombre_comercial'   => $d['nombre_comercial'] ?? '',
                'tipo_contribuyente' => $tipo,
                'direccion'          => $d['direccion'] ?? ($cliente['direccion'] ?? ''),
                'cod_prov'           => $d['cod_prov'] ?? '',
                'cod_ciudad'         => $d['cod_ciudad'] ?? '',
                'correo'             => $cliente['email'] ?? '',
                'telefono'           => $cliente['telefono'] ?? '',
                'establecimientos'   => $d['establecimientos'] ?? [],
            ],
            'cliente' => $cliente ? ['id' => (int) $cliente['id'], 'nombre' => $cliente['nombre']] : null,
        ];
    }

    // ── Proforma + CRM ───────────────────────────────────────────────────────

    /**
     * Genera la proforma de una cotización guardada en borrador y la enlaza a una
     * oportunidad del CRM (la crea con su contacto si no existe).
     *
     * - El cliente se registra ANTES de la transacción: ClienteService maneja la suya
     *   propia (un commit interno cerraría la nuestra) y es idempotente: si ya existe
     *   esa identificación, se reutiliza.
     * - Contacto, oportunidad, proforma y enlace van en una sola transacción.
     * - Si la cotización ya tiene proforma, se devuelve esa (un reintento no crea otra).
     *
     * @return array{id_proforma:int, id_oportunidad:?int, previo:bool, aviso:?string, numero_proforma:string, secuencial_proforma:string}
     */
    public function generarProforma(int $id, int $idEmpresa, int $idUsuario, int $idPunto, string $tipoAmbienteDefecto = '1'): array
    {
        $cot = $this->getFicha($id, $idEmpresa);
        if (!$cot) throw new \InvalidArgumentException('La cotización ya no existe.');
        if (!empty($cot['id_proforma'])) {
            return ['id_proforma' => (int) $cot['id_proforma'], 'id_oportunidad' => (int) $cot['id_oportunidad'] ?: null, 'previo' => true, 'aviso' => null]
                + $this->numeroProforma((int) $cot['id_proforma']);
        }
        $config = $this->getConfig($idEmpresa);
        $this->rules->validarParaProforma($cot, $config);

        // Serie: debe ser de la empresa, activa y con secuencial de Proformas.
        $punto = null;
        $secRepo = new \App\repositories\SecuencialRepository();
        foreach ($secRepo->getPuntosEmisionSerie($idEmpresa, true) as $p) {
            if ((int) $p['id'] === $idPunto && !empty($secRepo->getConfigSecuencial((int) $p['id'], 'Proformas')['id'])) {
                $punto = $p;
                break;
            }
        }
        if (!$punto) {
            throw new \InvalidArgumentException('Elija una serie activa con secuencial de Proformas configurado.');
        }

        // Producto de la línea resumen (con su IVA).
        $campoProd = $cot['modalidad'] === 'mensual' ? 'id_producto_resumen_mensual' : 'id_producto_resumen_anual';
        $prodResumen = $this->repo->getProductoConTarifa((int) $config[$campoProd], $idEmpresa);
        if (!$prodResumen) {
            throw new \InvalidArgumentException('El producto configurado para la línea resumen ya no existe. Revíselo en el engranaje → Parámetros.');
        }

        // Cálculo con lo GUARDADO (precios y parámetros copiados): la proforma dice
        // exactamente lo mismo que la cotización.
        $params = $cot['parametros'] ?: $config;
        $calc = CotizadorPlanesCalculo::calcular($params, $cot['establecimientos'], (int) $cot['usuarios'], (float) $cot['descuento_vendedor_pct']);

        // 1) Cliente (fuera de la transacción, idempotente).
        $idCliente = (int) ($cot['id_cliente'] ?? 0);
        if ($idCliente <= 0) {
            $existente = $this->repo->getClientePorIdentificacion($idEmpresa, $cot['identificacion']);
            if ($existente) {
                $idCliente = (int) $existente['id'];
            } else {
                $res = (new \App\Services\modulos\ClienteService(
                    new \App\repositories\modulos\ClienteRepository(), new \App\Rules\modulos\ClienteRules(), $this->log
                ))->replicarClienteEnEmpresa([
                    'nombre'         => $cot['razon_social'],
                    'tipo_id'        => strlen($cot['identificacion']) === 13 ? '04' : '05',
                    'identificacion' => $cot['identificacion'],
                    'telefono'       => $cot['telefono'] ?: null,
                    'email'          => $cot['correo'],
                    'direccion'      => $cot['direccion'] ?: null,
                    'provincia'      => $cot['cod_prov'] ?: null,
                    'ciudad'         => $cot['cod_ciudad'] ?: null,
                    'plazo'          => 0,
                    'status'         => 1,
                ], $idEmpresa, $idUsuario);
                $idCliente = (int) $res['id'];
            }
        }

        $proformaService = new ProformaService(new ProformaRepository(), new ProformaRules(), $this->log);
        $docService = new ProformaDocumentoService(new ProformaRepository(), $proformaService);
        $configDoc = $docService->empresaConfig($idEmpresa, ['id_punto_emision' => $idPunto, 'id_establecimiento' => $punto['id_establecimiento']]);

        $aviso = null;
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $bloq = $this->repo->bloquear($id, $idEmpresa);
            if (!$bloq) throw new \InvalidArgumentException('La cotización ya no existe.');
            if (!empty($bloq['id_proforma'])) {
                $db->rollBack();
                return ['id_proforma' => (int) $bloq['id_proforma'], 'id_oportunidad' => (int) $bloq['id_oportunidad'] ?: null, 'previo' => true, 'aviso' => null]
                    + $this->numeroProforma((int) $bloq['id_proforma']);
            }
            if ($bloq['estado'] !== 'borrador') {
                throw new \InvalidArgumentException('Esta cotización está anulada.');
            }

            // 2) CRM: contacto y oportunidad (si el CRM está instalado en la empresa).
            [$idContacto, $idOportunidad, $aviso] = $this->asegurarCrm($cot, $idCliente, $calc, $idEmpresa, $idUsuario);

            // 3) Proforma.
            $data = $this->armarProforma($cot, $calc, $punto, $prodResumen, $idCliente, $idEmpresa, $idUsuario, $config, $configDoc, $tipoAmbienteDefecto);
            if ($idOportunidad) $data['id_oportunidad'] = $idOportunidad;
            $idProforma = $proformaService->crear($data, $configDoc);

            // 4) Enlaces y estado.
            $this->repo->setEnlaces($id, $idEmpresa, [
                'id_cliente' => $idCliente, 'id_contacto' => $idContacto, 'id_oportunidad' => $idOportunidad,
                'id_proforma' => $idProforma, 'estado' => 'proforma',
            ], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'generar_proforma', 'cotizaciones_planes', $id,
                ['estado' => 'borrador'], ['estado' => 'proforma', 'id_proforma' => $idProforma, 'id_oportunidad' => $idOportunidad, 'id_cliente' => $idCliente]);
            $db->commit();
            return ['id_proforma' => $idProforma, 'id_oportunidad' => $idOportunidad, 'previo' => false, 'aviso' => $aviso]
                + $this->numeroProforma($idProforma);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Número visible de la proforma (001-001-000000123) y su secuencial, para el aviso y el enlace. */
    public function numeroProforma(int $idProforma): array
    {
        $pf = (new ProformaRepository())->getPorId($idProforma);
        if (!$pf) return ['numero_proforma' => '', 'secuencial_proforma' => ''];
        return [
            'numero_proforma'     => $pf['establecimiento'] . '-' . $pf['punto_emision'] . '-' . $pf['secuencial'],
            'secuencial_proforma' => (string) $pf['secuencial'],
        ];
    }

    /**
     * Contacto y oportunidad del CRM para la cotización. Reutiliza los enlazados si
     * siguen abiertos; si no, los crea. Sin el CRM instalado, devuelve un aviso.
     * @return array{0:?int, 1:?int, 2:?string}
     */
    private function asegurarCrm(array $cot, int $idCliente, array $calc, int $idEmpresa, int $idUsuario): array
    {
        $cfgCrm = CrmConfiguracionService::crear();
        if (!$cfgCrm->disponible()) {
            return [null, null, 'El CRM no está instalado: la proforma se creó sin oportunidad de seguimiento.'];
        }
        $cfgCrm->asegurarInicial($idEmpresa, $idUsuario);

        $idContacto = (int) ($cot['id_contacto'] ?? 0) ?: null;
        if (!$idContacto) {
            $c = $this->repo->getContactoPorCliente($idEmpresa, $idCliente);
            $idContacto = $c ? (int) $c['id'] : null;
        }
        if (!$idContacto) {
            $res = CrmContactoService::crear()->crearContacto([
                'nombre'           => $cot['contacto_nombre'] ?: $cot['razon_social'],
                'empresa_contacto' => $cot['razon_social'],
                'correo'           => $cot['correo'],
                'telefono'         => $cot['telefono'],
                'id_cliente'       => $idCliente,
                // Sin responsable explícito: el CRM valida que esté asignado a la empresa y
                // un superadministrador puede no estarlo. La oportunidad queda a cargo de quien la crea.
                'notas'            => 'Registrado desde el Cotizador de Planes (cotización N.º ' . $cot['numero'] . ').',
            ], $idEmpresa, $idUsuario);
            $idContacto = (int) $res['id'];
        }

        $valor = $cot['modalidad'] === 'mensual' ? $calc['total_mensual'] : $calc['total_anual'];
        $op = $this->repo->getOportunidadAbierta($idEmpresa, (int) ($cot['id_oportunidad'] ?? 0));
        if ($op && ($op['estado'] ?? '') === 'abierta' && empty($op['id_proforma'])) {
            return [$idContacto, (int) $op['id'], null];
        }
        $res = CrmOportunidadService::crear()->crearOportunidad([
            'titulo'         => mb_substr('Plan ' . $cot['modalidad'] . ' — ' . $cot['razon_social'], 0, 200),
            'descripcion'    => 'Cotización N.º ' . $cot['numero'] . ' del Cotizador de Planes: '
                                . count($cot['establecimientos']) . ' establecimiento(s), ' . (int) $cot['usuarios'] . ' usuario(s).',
            'id_contacto'    => $idContacto,
            'valor_estimado' => $valor,
            // Sin id_responsable: crearOportunidad() la deja a cargo de quien la crea (el vendedor).
            'id_vendedor'    => $cot['id_vendedor'] ?: null,
        ], $idEmpresa, $idUsuario, null);
        return [$idContacto, (int) $res['id'], null];
    }

    /** Datos de la proforma en el formato de ProformaService::crear(). */
    private function armarProforma(array $cot, array $calc, array $punto, array $prodResumen, int $idCliente, int $idEmpresa, int $idUsuario, array $config, array $configDoc, string $tipoAmbienteDefecto): array
    {
        $linea = function (?array $prod, string $descripcion, float $cantidad, float $precio, float $descuento, string $adicional, ?array $tarifa) {
            $t = $tarifa ?? $prod;
            return [
                'id_producto'      => $prod['id'] ?? null,
                'id_unidad_medida' => $prod['id_medida'] ?? null,
                'codigo_principal' => $prod['codigo'] ?? '',
                'descripcion'      => $descripcion,
                'adicional'        => $adicional,
                'cantidad'         => $cantidad,
                'precio_unitario'  => $precio,
                'descuento'        => $descuento,
                'id_tarifa_iva'    => (int) ($t['id_tarifa_iva'] ?? 0),
                'impuestos'        => [[
                    'codigo_impuesto'   => '2',
                    'codigo_porcentaje' => (string) ($t['codigo_tarifa'] ?? '0'),
                    'tarifa'            => (float) ($t['porcentaje_iva'] ?? 0),
                    'base_imponible'    => 0,
                    'valor'             => 0,
                ]],
            ];
        };

        $mensual = $cot['modalidad'] === 'mensual';
        $nEst = count($cot['establecimientos']);
        $detalles = [];
        // Línea resumen: lo recurrente (módulos + usuarios adicionales) con el descuento del vendedor aparte.
        $detalles[] = $linea(
            $prodResumen,
            (string) $prodResumen['nombre'],
            1,
            $mensual ? $calc['subtotal_mensual'] : $calc['subtotal_anual'],
            $mensual ? $calc['descuento_vendedor_mensual'] : $calc['descuento_vendedor_anual'],
            'RUC ' . $cot['identificacion'] . ' · ' . $nEst . ' establecimiento' . ($nEst === 1 ? '' : 's')
                . ' · ' . (int) $cot['usuarios'] . ' usuario' . ((int) $cot['usuarios'] === 1 ? '' : 's') . ' · detalle en el anexo de condiciones',
            null
        );

        // Servicios de una sola vez, agrupados por ítem y precio (cantidad = establecimientos que lo llevan).
        $unicos = [];
        foreach ($cot['establecimientos'] as $e) {
            foreach ($e['items'] as $it) {
                if (($it['cobro'] ?? '') !== 'unico') continue;
                $k = $it['id_item'] . '|' . number_format((float) $it['precio_anual'], 2, '.', '');
                $unicos[$k] ??= ['it' => $it, 'cant' => 0];
                $unicos[$k]['cant']++;
            }
        }
        foreach ($unicos as $u) {
            $prod = !empty($u['it']['id_producto']) ? $this->repo->getProductoConTarifa((int) $u['it']['id_producto'], $idEmpresa) : null;
            $detalles[] = $linea($prod, (string) $u['it']['nombre'], (float) $u['cant'], (float) $u['it']['precio_anual'], 0.0,
                'Pago único' . ($u['cant'] > 1 ? ' por establecimiento' : ''), $prod ? null : $prodResumen);
        }

        $obs = 'Cotización N.º ' . $cot['numero'] . ' (Cotizador de Planes). ';
        $obs .= $mensual
            ? 'Plan mensual: $' . $this->m($calc['total_mensual']) . ' al mes. Pagando anual serían $' . $this->m($calc['total_anual']) . ' al año.'
            : 'Plan anual: $' . $this->m($calc['total_anual']) . ' al año (en 12 mensualidades serían $' . $this->m($calc['doce_mensualidades']) . ').';
        if (trim((string) $cot['observaciones']) !== '') {
            $obs .= ' ' . trim((string) $cot['observaciones']);
        }

        return [
            'id_empresa'         => $idEmpresa,
            'id_usuario'         => $idUsuario,
            'id_cliente'         => $idCliente,
            'id_vendedor'        => $cot['id_vendedor'] ?: null,
            'id_establecimiento' => (int) $punto['id_establecimiento'],
            'id_punto_emision'   => (int) $punto['id'],
            'establecimiento'    => (string) $punto['establecimiento'],
            'punto_emision'      => (string) $punto['punto'],
            'secuencial'         => 'AUTO', // ProformaService lo reemplaza por el definitivo bajo candado
            'fecha_emision'      => date('Y-m-d'),
            'tipo_ambiente'      => (string) ($configDoc['tipo_ambiente'] ?? $tipoAmbienteDefecto),
            'dias_vigencia'      => (int) $config['dias_vigencia'],
            'observaciones'      => mb_substr($obs, 0, 1000),
            'condiciones_html'   => $this->armarAnexo($cot, $calc),
            'detalles'           => $detalles,
            'info_adicional'     => array_values(array_filter([
                $cot['correo'] ? ['nombre' => 'Correo', 'valor' => $cot['correo']] : null,
                $cot['telefono'] ? ['nombre' => 'Teléfono', 'valor' => $cot['telefono']] : null,
                ['nombre' => 'Detalle', 'valor' => 'Ver anexo de condiciones'],
            ])),
        ];
    }

    /** Anexo de condiciones: el detalle del plan (solo etiquetas que admite ProformaRules::sanitizarCondiciones). */
    public function armarAnexo(array $cot, array $calc): string
    {
        $h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $p = $calc['parametros'];
        $mensual = $cot['modalidad'] === 'mensual';
        $out = '<h2>Detalle del plan</h2>';
        $out .= '<p><strong>' . $h($cot['razon_social']) . '</strong> — RUC ' . $h($cot['identificacion']) . '<br>'
              . 'Modalidad: <strong>' . ($mensual ? 'mensual' : 'anual') . '</strong> · Usuarios: <strong>' . (int) $calc['usuarios']
              . '</strong> (' . (int) $calc['usuarios_incluidos'] . ' incluidos, compartidos entre los establecimientos'
              . ($calc['usuarios_adicionales'] > 0 ? '; ' . (int) $calc['usuarios_adicionales'] . ' adicionales' : '') . ')</p>';

        foreach ($cot['establecimientos'] as $i => $e) {
            $f = $calc['establecimientos'][$i] ?? [];
            $titulo = 'Establecimiento ' . $e['codigo'] . ($e['nombre'] !== '' ? ' — ' . $e['nombre'] : '');
            if (!empty($f['es_principal']) && count($cot['establecimientos']) > 1) $titulo .= ' (principal)';
            $out .= '<h3>' . $h($titulo) . '</h3>';
            $rec = array_filter($e['items'], fn($it) => ($it['cobro'] ?? '') !== 'unico');
            $uni = array_filter($e['items'], fn($it) => ($it['cobro'] ?? '') === 'unico');
            if ($rec) {
                $porGrupo = [];
                foreach ($rec as $it) $porGrupo[$it['grupo'] ?: 'Otros'][] = $it['nombre'];
                $out .= '<ul>';
                foreach ($porGrupo as $g => $noms) {
                    $out .= '<li><strong>' . $h($g) . ':</strong> ' . $h(implode(', ', $noms)) . '</li>';
                }
                $out .= '</ul>';
            }
            if ($uni) {
                $out .= '<p>Servicios de una sola vez: ' . $h(implode(', ', array_column($uni, 'nombre'))) . '</p>';
            }
            $out .= '<p>Valor del establecimiento: '
                  . ($mensual
                      ? '$' . $this->m($f['mensual'] ?? 0) . ' al mes'
                      : '$' . $this->m($f['anual'] ?? 0) . ' al año'
                        . (!empty($f['descuento_establecimiento']) ? ' (incluye ' . $this->pct($p['descuento_establecimiento_pct']) . ' de descuento por establecimiento adicional)' : ''))
                  . '</p>';
        }

        $out .= '<h3>Resumen</h3><ul>';
        $out .= '<li>Plan anual: <strong>$' . $this->m($calc['total_anual']) . '</strong> al año</li>';
        $out .= '<li>Plan mensual: <strong>$' . $this->m($calc['total_mensual']) . '</strong> al mes ($' . $this->m($calc['doce_mensualidades']) . ' en 12 meses)</li>';
        if ($calc['ahorro_anual'] > 0) {
            $out .= '<li>Ahorro pagando anual: $' . $this->m($calc['ahorro_anual']) . '</li>';
        }
        if ($calc['usuarios_adicionales'] > 0) {
            $out .= '<li>Usuarios adicionales: ' . (int) $calc['usuarios_adicionales'] . ' × $' . $this->m($p['precio_usuario_adicional_anual']) . ' al año</li>';
        }
        if ($calc['descuento_vendedor_pct'] > 0) {
            $out .= '<li>Descuento comercial: ' . $this->pct($calc['descuento_vendedor_pct']) . '</li>';
        }
        if ($calc['total_unico'] > 0) {
            $out .= '<li>Servicios de una sola vez: $' . $this->m($calc['total_unico']) . '</li>';
        }
        $out .= '</ul>';
        $out .= '<p>Valores sin IVA. El plan mensual cuesta ' . $this->pct($p['recargo_mensual_pct']) . ' más que el anual. '
              . 'El descuento por establecimiento adicional (' . $this->pct($p['descuento_establecimiento_pct']) . ') aplica solo en el plan anual; '
              . 'el establecimiento con el plan base de mayor valor paga el precio completo.</p>';
        return $out;
    }

    private function m($v): string
    {
        return number_format((float) $v, 2, ',', '.');
    }

    private function pct($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . ' %';
    }

    private function bool($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 'true', 't', 'on', 'si', 'sí', 'yes'], true);
    }
}
