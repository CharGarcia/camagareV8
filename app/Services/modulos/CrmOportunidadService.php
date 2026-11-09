<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CrmActividadRepository;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmContactoRepository;
use App\repositories\modulos\CrmEmbudoRepository;
use App\repositories\modulos\CrmOportunidadRepository;
use App\Rules\modulos\CrmOportunidadRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * CRM: oportunidades de venta — crear, editar, mover de etapa (Kanban), ganar,
 * perder, reabrir y eliminar. Diseño: docs/diseno/crm-redes-fase1.md §5.6 y §11.
 *
 * Concurrencia (CLAUDE.md §8): el número correlativo se toma con candado por
 * empresa y todo cambio de etapa bloquea la fila (SELECT … FOR UPDATE), dentro
 * de la transacción, antes de leer el estado actual.
 */
class CrmOportunidadService
{
    public const MODULO_GUARDADO = 'crm_oportunidades';

    public bool $ultimoGuardadoPrevio = false;

    public function __construct(
        private CrmOportunidadRepository $repo,
        private CrmEmbudoRepository $embudos,
        private CrmContactoRepository $contactos,
        private CrmCatalogoRepository $catalogos,
        private CrmActividadRepository $actividades,
        private CrmOportunidadRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(
            new CrmOportunidadRepository(), new CrmEmbudoRepository(), new CrmContactoRepository(),
            new CrmCatalogoRepository(), new CrmActividadRepository(), new CrmOportunidadRules(), new LogSistemaService()
        );
    }

    // ── Lectura ──────────────────────────────────────────────────────────────

    public function getListado(int $idEmpresa, array $filtros, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        return $this->repo->getListado($idEmpresa, $filtros, $page, $perPage, $orden, $idUsuarioFiltro);
    }

    /**
     * Columnas del Kanban: todas las etapas abiertas del embudo (aunque estén
     * vacías) con sus tarjetas, cantidad y valor.
     */
    public function getKanban(int $idEmpresa, int $idEmbudo, array $filtros, ?int $idUsuarioFiltro): array
    {
        $datos = $this->repo->getKanban($idEmpresa, $idEmbudo, $filtros, $idUsuarioFiltro);
        $columnas = [];
        foreach ($this->embudos->getEtapas($idEmbudo, $idEmpresa) as $et) {
            if ($et['tipo'] !== 'abierta') {
                continue;
            }
            $col = $datos[(int) $et['id']] ?? ['tarjetas' => [], 'cantidad' => 0, 'valor' => 0.0];
            $columnas[] = $et + $col + ['truncada' => $col['cantidad'] > count($col['tarjetas'])];
        }
        return $columnas;
    }

    public function getResumen(int $idEmpresa, array $filtros, ?int $idUsuarioFiltro): array
    {
        return $this->repo->getResumen($idEmpresa, $filtros, $idUsuarioFiltro);
    }

    /** Ficha para el modal: datos + actividades + historial de etapas. */
    public function getFicha(int $id, int $idEmpresa): ?array
    {
        $op = $this->repo->getPorId($id, $idEmpresa);
        if (!$op) {
            return null;
        }
        $op['actividades']     = $this->actividades->getPorOportunidad($id, $idEmpresa);
        $op['historial_etapas'] = $this->repo->getHistorialEtapas($id, $idEmpresa);
        return $op;
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getPorId($id, $idEmpresa);
    }

    public function getPorContacto(int $idContacto, int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        return $this->repo->getPorContacto($idContacto, $idEmpresa, $idUsuarioFiltro);
    }

    // ── Escritura ────────────────────────────────────────────────────────────

    /**
     * Crea la oportunidad en la etapa indicada (o la primera abierta del embudo).
     * Guardado único: el reintento del mismo formulario devuelve la ya creada.
     * Devuelve ['id' => …, 'numero' => …].
     */
    public function crearOportunidad(array $d, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro): array
    {
        $this->ultimoGuardadoPrevio = false;
        $guardado = new GuardadoUnicoService();
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            if ($previo = $guardado->previo($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO)) {
                if ($managed) $db->rollBack();
                $this->ultimoGuardadoPrevio = true;
                return ['id' => (int) $previo['id_registro'], 'numero' => $previo['numero']];
            }

            $d = $this->normalizar($d, $idEmpresa, $idUsuarioFiltro);
            $this->rules->validarDatos($d);

            $idEmbudo = (int) ($d['id_embudo'] ?? 0);
            $embudo = $idEmbudo > 0 ? $this->embudos->getEmbudo($idEmbudo, $idEmpresa) : $this->embudos->getEmbudoDefecto($idEmpresa);
            if (!$embudo || $embudo['estado'] !== 'activo') {
                throw new \InvalidArgumentException('Seleccione un embudo activo.');
            }
            $idEmbudo = (int) $embudo['id'];

            $idEtapa = (int) ($d['id_etapa'] ?? 0);
            if ($idEtapa <= 0) {
                $primera = array_values(array_filter($this->embudos->getEtapas($idEmbudo, $idEmpresa), fn($e) => $e['tipo'] === 'abierta'))[0] ?? null;
                $idEtapa = $primera ? (int) $primera['id'] : 0;
            }
            $this->rules->validarEtapaAbierta($this->embudos->getEtapa($idEtapa, $idEmpresa), $idEmbudo);

            // Sin responsable explícito queda a cargo de quien la crea.
            $d['id_responsable'] = $d['id_responsable'] ?: $idUsuario;
            $d['id_embudo'] = $idEmbudo;
            $d['id_etapa']  = $idEtapa;
            $d['numero']    = $this->repo->siguienteNumero($idEmpresa);
            $d['orden_kanban'] = $this->repo->ordenAlFinal($idEmpresa, $idEtapa);

            $id = $this->repo->insertar($d, $idEmpresa, $idUsuario);
            $this->repo->registrarCambioEtapa($idEmpresa, $id, null, $idEtapa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_oportunidades', $id, null, $this->paraLog($d));
            $guardado->registrar($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO, $id, (string) $d['numero'], $idUsuario);
            if ($managed) $db->commit();
            return ['id' => $id, 'numero' => $d['numero']];
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** Datos de la ficha. Etapa y estado cambian por moverEtapa/ganar/perder/reabrir. */
    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro): void
    {
        $this->enTransaccion(function () use ($id, $d, $idEmpresa, $idUsuario, $idUsuarioFiltro) {
            $antes = $this->repo->bloquear($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $this->rules->validarEditable($antes);
            $d = $this->normalizar($d, $idEmpresa, $idUsuarioFiltro);
            $this->rules->validarDatos($d);
            // Con proforma enlazada el valor lo manda la proforma (decisión 8).
            if (!empty($antes['id_proforma'])) {
                $d['valor_estimado'] = $antes['valor_estimado'];
            }
            $d['id_responsable'] = $d['id_responsable'] ?: ($antes['id_responsable'] ?: $idUsuario);
            $this->repo->actualizarDatos($id, $d, $idEmpresa, $idUsuario);

            // Cambio de embudo: la oportunidad pasa a la etapa elegida del embudo nuevo.
            $idEmbudoNuevo = (int) ($d['id_embudo'] ?? 0);
            if ($idEmbudoNuevo > 0 && $idEmbudoNuevo !== (int) $antes['id_embudo']) {
                $this->moverInterno($antes, $idEmbudoNuevo, (int) ($d['id_etapa'] ?? 0), null, $idEmpresa, $idUsuario);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_oportunidades', $id,
                $this->paraLog($antes), $this->paraLog($d));
        });
    }

    /**
     * Arrastre en el Kanban: cambia de etapa (abierta) y/o de posición.
     * $orden = posición calculada por la vista entre las tarjetas vecinas.
     */
    public function moverEtapa(int $id, int $idEtapa, $orden, int $idEmpresa, int $idUsuario): array
    {
        $orden = $this->rules->validarOrden($orden);
        return $this->enTransaccion(function () use ($id, $idEtapa, $orden, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $this->rules->validarEditable($op);
            $this->moverInterno($op, (int) $op['id_embudo'], $idEtapa, $orden, $idEmpresa, $idUsuario);
            return $this->repo->getPorId($id, $idEmpresa);
        });
    }

    /** Ganar: exige cliente, enlaza el contacto al cliente si no lo estaba. */
    public function ganar(int $id, int $idCliente, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idCliente, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $idCliente = $idCliente ?: (int) ($op['id_cliente'] ?? 0);
            if (!$idCliente) {
                $contacto = $this->contactos->getPorId((int) $op['id_contacto'], $idEmpresa);
                $idCliente = (int) ($contacto['id_cliente'] ?? 0);
            }
            $cliente = $idCliente ? $this->catalogos->getCliente($idCliente, $idEmpresa) : null;
            $this->rules->validarGanar($op, $cliente);

            $etapa = $this->etapaCierre((int) $op['id_embudo'], $idEmpresa, 'ganada');
            $this->repo->cambiarEtapa($id, $idEmpresa, (int) $op['id_embudo'], (int) $etapa['id'], 'ganada',
                (float) $op['orden_kanban'], ['id_cliente' => $idCliente], $idUsuario);
            $this->repo->registrarCambioEtapa($idEmpresa, $id, (int) $op['id_etapa'], (int) $etapa['id'], $idUsuario);
            $this->contactos->enlazarCliente((int) $op['id_contacto'], $idCliente, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'GANAR', 'crm_oportunidades', $id,
                ['estado' => $op['estado'], 'id_etapa' => $op['id_etapa']],
                ['estado' => 'ganada', 'id_etapa' => $etapa['id'], 'id_cliente' => $idCliente]);
        });
    }

    public function perder(int $id, int $idMotivo, string $detalle, int $idEmpresa, int $idUsuario): void
    {
        $detalle = trim($detalle);
        $this->enTransaccion(function () use ($id, $idMotivo, $detalle, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $motivo = $idMotivo ? $this->catalogos->obtener('motivos', $idMotivo, $idEmpresa) : null;
            $this->rules->validarPerder($op, $motivo, $detalle);

            $etapa = $this->etapaCierre((int) $op['id_embudo'], $idEmpresa, 'perdida');
            $this->repo->cambiarEtapa($id, $idEmpresa, (int) $op['id_embudo'], (int) $etapa['id'], 'perdida',
                (float) $op['orden_kanban'],
                ['id_motivo_perdida' => $idMotivo, 'detalle_perdida' => $detalle !== '' ? $detalle : null], $idUsuario);
            $this->repo->registrarCambioEtapa($idEmpresa, $id, (int) $op['id_etapa'], (int) $etapa['id'], $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'PERDER', 'crm_oportunidades', $id,
                ['estado' => $op['estado'], 'id_etapa' => $op['id_etapa']],
                ['estado' => 'perdida', 'id_motivo_perdida' => $idMotivo, 'detalle_perdida' => $detalle]);
        });
    }

    /** Reabre en la etapa indicada (o la última abierta del embudo). Conserva el cliente enlazado. */
    public function reabrir(int $id, int $idEtapa, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idEtapa, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $this->rules->validarReabrir($op);
            if ($idEtapa <= 0) {
                $abiertas = array_values(array_filter($this->embudos->getEtapas((int) $op['id_embudo'], $idEmpresa), fn($e) => $e['tipo'] === 'abierta'));
                $idEtapa = $abiertas ? (int) end($abiertas)['id'] : 0;
            }
            $this->rules->validarEtapaAbierta($this->embudos->getEtapa($idEtapa, $idEmpresa), (int) $op['id_embudo']);
            $this->repo->cambiarEtapa($id, $idEmpresa, (int) $op['id_embudo'], $idEtapa, 'abierta',
                $this->repo->ordenAlFinal($idEmpresa, $idEtapa), [], $idUsuario);
            $this->repo->registrarCambioEtapa($idEmpresa, $id, (int) $op['id_etapa'], $idEtapa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'REABRIR', 'crm_oportunidades', $id,
                ['estado' => $op['estado'], 'id_etapa' => $op['id_etapa']], ['estado' => 'abierta', 'id_etapa' => $idEtapa]);
        });
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_oportunidades', $id, $this->paraLog($op), null);
        });
    }

    // ── Enlace con Proformas ─────────────────────────────────────────────────

    /**
     * Antes de abrir Proformas: comprueba que la oportunidad admite una proforma y
     * resuelve su cliente (el elegido, el de la oportunidad o el del contacto). Si
     * se eligió un cliente y el contacto no tenía, lo enlaza. Devuelve lo que
     * Proformas necesita para abrir la proforma nueva.
     */
    public function prepararProforma(int $id, int $idCliente, int $idEmpresa, int $idUsuario): array
    {
        return $this->enTransaccion(function () use ($id, $idCliente, $idEmpresa, $idUsuario) {
            $op = $this->repo->bloquear($id, $idEmpresa);
            if (!$op) {
                throw new \RuntimeException('Oportunidad no encontrada.');
            }
            $this->rules->validarEditable($op);
            if (!empty($op['id_proforma'])) {
                throw new \InvalidArgumentException('La oportunidad ya tiene una proforma enlazada.');
            }
            $contacto = $this->contactos->getPorId((int) $op['id_contacto'], $idEmpresa);
            $idCliente = $idCliente ?: (int) ($op['id_cliente'] ?? 0) ?: (int) ($contacto['id_cliente'] ?? 0);
            $cliente = $idCliente ? $this->catalogos->getCliente($idCliente, $idEmpresa) : null;
            if (!$cliente) {
                throw new \InvalidArgumentException('Seleccione o cree el cliente al que se le hará la proforma.');
            }
            if ($contacto && empty($contacto['id_cliente'])) {
                $this->contactos->enlazarCliente((int) $contacto['id'], (int) $cliente['id'], $idEmpresa, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'ENLAZAR_CLIENTE', 'crm_contactos', (int) $contacto['id'],
                    ['id_cliente' => null], ['id_cliente' => (int) $cliente['id']]);
            }
            return [
                'id'             => (int) $op['id'],
                'id_empresa'     => $idEmpresa,
                'numero'         => (int) $op['numero'],
                'titulo'         => (string) $op['titulo'],
                'id_cliente'     => (int) $cliente['id'],
                'identificacion' => (string) $cliente['identificacion'],
            ];
        });
    }

    /**
     * Enlaza una proforma recién creada a su oportunidad. Lo llama
     * ProformaService::crear() DENTRO de su transacción, así que si el enlace
     * falla la proforma tampoco se crea (el usuario la pidió desde el CRM).
     * Sin el SQL del CRM no hace nada.
     */
    public function vincularProforma(int $idOportunidad, int $idProforma, float $total, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->embudos->disponible()) {
            return;
        }
        $op = $this->repo->bloquear($idOportunidad, $idEmpresa);
        if (!$op) {
            throw new \RuntimeException('La oportunidad del CRM ya no existe; la proforma no se creó.');
        }
        if ($op['estado'] !== 'abierta') {
            throw new \RuntimeException('La oportunidad del CRM está cerrada; reábrala para crearle una proforma.');
        }
        if (!empty($op['id_proforma'])) {
            throw new \RuntimeException('La oportunidad del CRM ya tiene una proforma enlazada.');
        }
        $this->repo->vincularProforma($idOportunidad, $idProforma, $total, $idEmpresa, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'VINCULAR_PROFORMA', 'crm_oportunidades', $idOportunidad,
            ['id_proforma' => null, 'valor_estimado' => $op['valor_estimado']],
            ['id_proforma' => $idProforma, 'valor_estimado' => round($total, 2)]);
    }

    /** La proforma enlazada cambió de total: la oportunidad abierta lo sigue. */
    public function actualizarValorPorProforma(int $idProforma, float $total, int $idEmpresa, int $idUsuario): void
    {
        if ($this->embudos->disponible()) {
            $this->repo->actualizarValorPorProforma($idProforma, $total, $idEmpresa, $idUsuario);
        }
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    /**
     * Mueve una oportunidad ya bloqueada a una etapa abierta de $idEmbudo. Si la
     * etapa no cambia solo actualiza la posición (sin historial ni log).
     */
    private function moverInterno(array $op, int $idEmbudo, int $idEtapa, ?float $orden, int $idEmpresa, int $idUsuario): void
    {
        $embudo = $this->embudos->getEmbudo($idEmbudo, $idEmpresa);
        if (!$embudo || $embudo['estado'] !== 'activo') {
            throw new \InvalidArgumentException('Seleccione un embudo activo.');
        }
        if ($idEtapa <= 0 && $idEmbudo !== (int) $op['id_embudo']) {
            $primera = array_values(array_filter($this->embudos->getEtapas($idEmbudo, $idEmpresa), fn($e) => $e['tipo'] === 'abierta'))[0] ?? null;
            $idEtapa = $primera ? (int) $primera['id'] : 0;
        }
        $this->rules->validarEtapaAbierta($this->embudos->getEtapa($idEtapa, $idEmpresa), $idEmbudo);

        $cambiaEtapa = $idEtapa !== (int) $op['id_etapa'];
        if ($orden === null) {
            $orden = $cambiaEtapa ? $this->repo->ordenAlFinal($idEmpresa, $idEtapa) : (float) $op['orden_kanban'];
        }
        $this->repo->cambiarEtapa((int) $op['id'], $idEmpresa, $idEmbudo, $idEtapa, 'abierta', $orden, [], $idUsuario);
        if ($cambiaEtapa) {
            $this->repo->registrarCambioEtapa($idEmpresa, (int) $op['id'], (int) $op['id_etapa'], $idEtapa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CAMBIAR_ETAPA', 'crm_oportunidades', (int) $op['id'],
                ['id_embudo' => $op['id_embudo'], 'id_etapa' => $op['id_etapa']],
                ['id_embudo' => $idEmbudo, 'id_etapa' => $idEtapa]);
        }
    }

    private function etapaCierre(int $idEmbudo, int $idEmpresa, string $tipo): array
    {
        $etapa = $this->embudos->getEtapaPorTipo($idEmbudo, $idEmpresa, $tipo);
        if (!$etapa) {
            throw new \RuntimeException('El embudo no tiene etapa «' . ($tipo === 'ganada' ? 'Ganado' : 'Perdido') . '». Revise la configuración del CRM.');
        }
        return $etapa;
    }

    /**
     * Normaliza la entrada y comprueba que contacto, responsable, vendedor y
     * origen sean de la empresa. El contacto, además, debe ser visible para el
     * usuario (registros propios): no se puede colgar una oportunidad de un
     * contacto ajeno.
     */
    private function normalizar(array $d, int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        $d['titulo'] = trim((string) ($d['titulo'] ?? ''));
        $d['descripcion'] = trim((string) ($d['descripcion'] ?? '')) ?: null;
        $d['valor_estimado'] = $this->numero($d['valor_estimado'] ?? '0');
        $d['fecha_cierre_estimada'] = trim((string) ($d['fecha_cierre_estimada'] ?? '')) ?: null;

        $idContacto = (int) ($d['id_contacto'] ?? 0);
        if ($idContacto > 0 && !$this->contactos->buscarParaSelect($idEmpresa, '', $idUsuarioFiltro, $idContacto)) {
            throw new \InvalidArgumentException('El contacto seleccionado no existe o no tiene acceso a él.');
        }
        $d['id_contacto'] = $idContacto ?: null;

        $resp = (int) ($d['id_responsable'] ?? 0);
        if ($resp > 0 && !$this->catalogos->usuarioPerteneceEmpresa($resp, $idEmpresa)) {
            throw new \InvalidArgumentException('El responsable seleccionado no pertenece a esta empresa.');
        }
        $d['id_responsable'] = $resp ?: null;

        $vend = (int) ($d['id_vendedor'] ?? 0);
        if ($vend > 0 && !$this->catalogos->vendedorExiste($vend, $idEmpresa)) {
            throw new \InvalidArgumentException('Vendedor no válido.');
        }
        $d['id_vendedor'] = $vend ?: null;

        $origen = (int) ($d['id_origen'] ?? 0);
        if ($origen > 0 && !$this->catalogos->obtener('origenes', $origen, $idEmpresa)) {
            throw new \InvalidArgumentException('Origen no válido.');
        }
        $d['id_origen'] = $origen ?: null;
        $d['id_conversacion'] = isset($d['id_conversacion']) && (int) $d['id_conversacion'] > 0 ? (int) $d['id_conversacion'] : null;
        return $d;
    }

    /**
     * Monto escrito a mano: «1500.5», «1500,50» y «1.500,50» valen 1500,50.
     * Con punto y coma a la vez, el último separador es el decimal. Un texto que
     * no es número se devuelve tal cual para que Rules lo rechace.
     */
    private function numero($v)
    {
        $s = preg_replace('/\s+/', '', (string) $v);
        if ($s === '') {
            return 0.0;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.')
                ? str_replace(['.', ','], ['', '.'], $s)   // 1.500,50
                : str_replace(',', '', $s);                // 1,500.50
        } else {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? round((float) $s, 2) : $v;
    }

    private function paraLog(array $d): array
    {
        return array_intersect_key($d, array_flip([
            'numero', 'titulo', 'id_contacto', 'id_embudo', 'id_etapa', 'id_responsable', 'id_vendedor',
            'valor_estimado', 'fecha_cierre_estimada', 'id_origen', 'estado',
        ]));
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
