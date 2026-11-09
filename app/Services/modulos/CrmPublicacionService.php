<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CrmMedioRepository;
use App\repositories\modulos\CrmPublicacionRepository;
use App\Rules\modulos\CrmPublicacionRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/**
 * CRM fase 3: publicaciones (docs/diseno/crm-redes-fase3.md §3).
 *
 *  borrador ──(si la empresa exige aprobación)──► en_revision ──► aprobada
 *     │                                                              │
 *     └──────────────────────► programada ◄──────────────────────────┘
 *                                  │  (el cron, CrmPublicadorService)
 *                                  ▼
 *                    publicando → publicada | parcial | con_error
 *
 * Todo cambio de estado va en una transacción con la publicación bloqueada
 * (FOR UPDATE) y queda en log_sistema.
 */
class CrmPublicacionService
{
    public const MODULO_GUARDADO = 'crm_publicaciones';

    /** Permiso que exige cada red para publicar (lo que concedió la cuenta al conectarse). */
    private const PERMISO_PUBLICAR = [
        'instagram'       => 'instagram_business_content_publish',
        'linkedin_perfil' => 'w_member_social',
        'linkedin_pagina' => 'w_organization_social',
    ];

    public bool $ultimoGuardadoPrevio = false;

    public function __construct(
        private CrmPublicacionRepository $repo,
        private CrmMedioRepository $medios,
        private CrmPublicacionRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmPublicacionRepository(), new CrmMedioRepository(), new CrmPublicacionRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    // ── Consultas ────────────────────────────────────────────────────────────

    public function config(int $idEmpresa): array
    {
        return $this->repo->config($idEmpresa);
    }

    public function guardarConfig(int $idEmpresa, bool $aprobacion, int $maxDia, int $idUsuario): void
    {
        if ($maxDia < 1 || $maxDia > 100) {
            throw new \InvalidArgumentException('El tope diario debe estar entre 1 y 100 publicaciones.');
        }
        $this->enTransaccion(function () use ($idEmpresa, $aprobacion, $maxDia, $idUsuario) {
            $antes = $this->repo->config($idEmpresa);
            $this->repo->guardarConfig($idEmpresa, $aprobacion, $maxDia, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_publicaciones_config', null,
                ['requiere_aprobacion' => $antes['requiere_aprobacion'], 'max_publicaciones_dia' => $antes['max_publicaciones_dia']],
                ['requiere_aprobacion' => $aprobacion, 'max_publicaciones_dia' => $maxDia]);
        });
    }

    public function canales(int $idEmpresa): array
    {
        return array_map(function ($c) {
            $permisos = json_decode((string) ($c['permisos'] ?? '[]'), true) ?: [];
            unset($c['permisos']);
            $c['puede_publicar'] = in_array(self::PERMISO_PUBLICAR[$c['tipo']] ?? '-', $permisos, true);
            return $c;
        }, $this->repo->canalesPublicables($idEmpresa));
    }

    /** Publicación con sus medios y destinos. */
    public function obtener(int $id, int $idEmpresa): ?array
    {
        $p = $this->repo->getPorId($id, $idEmpresa);
        if (!$p) {
            return null;
        }
        $p['medios'] = array_map(fn($m) => array_intersect_key($m, array_flip(['id', 'tipo', 'mime', 'ancho', 'alto', 'duracion_seg', 'texto_alternativo', 'es_ia', 'orden'])),
            $this->repo->medios($id));
        $p['destinos'] = $this->repo->destinos($id);
        return $p;
    }

    public function listar(int $idEmpresa, array $f, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        return $this->repo->listar($idEmpresa, $f, $page, $perPage, $orden, $idUsuarioFiltro);
    }

    public function resumen(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        return $this->repo->resumen($idEmpresa, $idUsuarioFiltro);
    }

    public function calendario(int $idEmpresa, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) || $hasta <= $desde
            || (strtotime($hasta) - strtotime($desde)) > 62 * 86400) {
            throw new \InvalidArgumentException('Rango de fechas no válido.');
        }
        return $this->repo->calendario($idEmpresa, $desde, $hasta, $idUsuarioFiltro);
    }

    // ── Crear y editar ───────────────────────────────────────────────────────

    /**
     * $d: titulo, texto, es_contenido_ia, medios (ids en orden),
     *     destinos ([{id_canal, formato, texto?}]), token_guardado.
     */
    public function crearPublicacion(array $d, int $idEmpresa, int $idUsuario): int
    {
        $this->ultimoGuardadoPrevio = false;
        $guardado = new GuardadoUnicoService();
        return $this->enTransaccion(function () use ($d, $idEmpresa, $idUsuario, $guardado) {
            if ($previo = $guardado->previo($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO)) {
                $this->ultimoGuardadoPrevio = true;
                return (int) $previo['id_registro'];
            }
            $d = $this->normalizar($d, $idEmpresa);
            $id = $this->repo->insertar($idEmpresa, $d, $idUsuario);
            $this->repo->reemplazarMedios($id, $idEmpresa, $d['medios'], $idUsuario);
            $this->repo->reemplazarDestinos($id, $idEmpresa, $d['destinos'], $idUsuario);
            $this->medios->marcarUso($d['medios'], $idEmpresa);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_publicaciones', $id, null, $this->paraLog($d));
            $guardado->registrar($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO, $id, null, $idUsuario);
            return $id;
        });
    }

    /** Solo en borrador, revisión o aprobada. Editar algo aprobado le quita la aprobación. */
    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): void
    {
        $this->enTransaccion(function () use ($id, $d, $idEmpresa, $idUsuario) {
            $antes = $this->repo->bloquear($id, $idEmpresa) ?? throw new \RuntimeException('Publicación no encontrada.');
            $this->rules->validarEditable($antes);
            $d = $this->normalizar($d, $idEmpresa);
            $this->repo->actualizarCabecera($id, $idEmpresa, $d, $idUsuario);
            $this->repo->reemplazarMedios($id, $idEmpresa, $d['medios'], $idUsuario);
            $this->repo->reemplazarDestinos($id, $idEmpresa, $d['destinos'], $idUsuario);
            $this->medios->marcarUso($d['medios'], $idEmpresa);
            if (in_array($antes['estado'], ['aprobada', 'en_revision'], true)) {
                $this->repo->cambiarEstado($id, $idEmpresa, 'borrador', $idUsuario, ['aprobada_por' => null, 'aprobada_at' => null]);
            }
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_publicaciones', $id,
                array_intersect_key($antes, array_flip(['titulo', 'texto', 'estado', 'es_contenido_ia'])), $this->paraLog($d));
        });
    }

    /** Limpia la entrada y comprueba que medios y cuentas sean de la empresa. */
    private function normalizar(array $d, int $idEmpresa): array
    {
        $d['titulo'] = trim((string) ($d['titulo'] ?? ''));
        $d['texto'] = trim(str_replace("\r\n", "\n", (string) ($d['texto'] ?? ''))) ?: null;
        $d['es_contenido_ia'] = in_array($d['es_contenido_ia'] ?? false, [true, 1, '1', 'true', 'on'], true);
        $this->rules->validarCabecera($d);

        $pedidos = array_values(array_filter(array_map('intval', (array) ($d['medios'] ?? []))));
        $medios = $this->repo->mediosDeEmpresa($idEmpresa, $pedidos);
        if (count($medios) !== count(array_unique($pedidos))) {
            throw new \InvalidArgumentException('Algún archivo ya no está en la biblioteca. Vuelva a elegirlo.');
        }
        if (count($medios) > 20) {
            throw new \InvalidArgumentException('Una publicación lleva hasta 20 archivos.');
        }
        $d['medios'] = array_map(fn($m) => (int) $m['id'], $medios);

        $canales = array_column($this->repo->canalesPublicables($idEmpresa), null, 'id');
        $destinos = [];
        foreach ((array) ($d['destinos'] ?? []) as $x) {
            $idCanal = (int) ($x['id_canal'] ?? 0);
            $canal = $canales[$idCanal] ?? null;
            if (!$canal) {
                throw new \InvalidArgumentException('Una de las cuentas elegidas no existe en esta empresa.');
            }
            if (isset($destinos[$idCanal])) {
                throw new \InvalidArgumentException('Cada cuenta va una sola vez por publicación.');
            }
            $formato = (string) ($x['formato'] ?? '');
            if (!in_array($formato, CrmPublicacionRules::FORMATOS[$canal['tipo']] ?? [], true)) {
                throw new \InvalidArgumentException('Formato no válido para la cuenta @' . ($canal['usuario'] ?: $canal['nombre']) . '.');
            }
            $texto = trim(str_replace("\r\n", "\n", (string) ($x['texto'] ?? '')));
            $destinos[$idCanal] = ['id_canal' => $idCanal, 'formato' => $formato, 'texto' => $texto !== '' ? $texto : null];
        }
        $d['destinos'] = array_values($destinos);
        return $d;
    }

    // ── Revisión y aprobación ────────────────────────────────────────────────

    public function enviarARevision(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idEmpresa) {
            if (!$this->repo->config($idEmpresa)['requiere_aprobacion']) {
                throw new \InvalidArgumentException('La empresa no exige aprobación: programe la publicación directamente.');
            }
            if ($p['estado'] !== 'borrador') {
                throw new \InvalidArgumentException('Solo se envía a revisión un borrador.');
            }
            $this->validarCompleta((int) $p['id'], $idEmpresa);
            return ['en_revision', []];
        }, 'ENVIAR_REVISION');
    }

    public function aprobar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idEmpresa, $idUsuario) {
            if ($p['estado'] !== 'en_revision') {
                throw new \InvalidArgumentException('Solo se aprueba una publicación en revisión.');
            }
            $this->validarCompleta((int) $p['id'], $idEmpresa);
            return ['aprobada', ['aprobada_por' => $idUsuario, 'aprobada_at' => date('Y-m-d H:i:s')]];
        }, 'APROBAR');
    }

    public function devolver(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) {
            if (!in_array($p['estado'], ['en_revision', 'aprobada'], true)) {
                throw new \InvalidArgumentException('Solo se devuelve a borrador una publicación en revisión o aprobada.');
            }
            return ['borrador', ['aprobada_por' => null, 'aprobada_at' => null]];
        }, 'DEVOLVER_BORRADOR');
    }

    // ── Programar ────────────────────────────────────────────────────────────

    /** $fecha null o vacía = publicar ahora (en el siguiente minuto). */
    public function programar(int $id, ?string $fecha, int $idEmpresa, int $idUsuario): string
    {
        $cuando = $this->rules->validarFecha($fecha) ?? date('Y-m-d H:i:s');
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idEmpresa, $cuando) {
            $exige = $this->repo->config($idEmpresa)['requiere_aprobacion'];
            if ($exige && $p['estado'] !== 'aprobada') {
                throw new \InvalidArgumentException('La empresa exige aprobación: la publicación debe estar aprobada para programarla.');
            }
            if (!$exige && !in_array($p['estado'], ['borrador', 'aprobada'], true)) {
                throw new \InvalidArgumentException('Solo se programa un borrador.');
            }
            $this->validarCompleta((int) $p['id'], $idEmpresa, true);
            $this->repo->destinosAPendiente((int) $p['id'], $idEmpresa);
            $this->medios->marcarUso($this->repo->idsMedios((int) $p['id']), $idEmpresa);
            return ['programada', ['programada_at' => $cuando]];
        }, 'PROGRAMAR');
        return $cuando;
    }

    /** Vuelve a borrador (o aprobada) mientras nada haya empezado a salir. */
    public function desprogramar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idEmpresa) {
            if ($p['estado'] !== 'programada') {
                throw new \InvalidArgumentException('La publicación no está programada.');
            }
            if (array_diff($this->repo->estadosDestinos((int) $p['id']), ['pendiente', 'cancelada'])) {
                throw new \InvalidArgumentException('Ya empezó a publicarse; use «Cancelar lo pendiente».');
            }
            $vuelta = $p['aprobada_por'] !== null && $this->repo->config($idEmpresa)['requiere_aprobacion'] ? 'aprobada' : 'borrador';
            return [$vuelta, ['programada_at' => null]];
        }, 'DESPROGRAMAR');
    }

    /** Cancela los destinos que aún no salieron (pendientes o con error). */
    public function cancelarPendientes(int $id, int $idEmpresa, int $idUsuario): int
    {
        $n = 0;
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idEmpresa, &$n) {
            if (!in_array($p['estado'], ['programada', 'publicando', 'parcial', 'con_error'], true)) {
                throw new \InvalidArgumentException('No hay nada pendiente que cancelar.');
            }
            $n = $this->repo->cancelarDestinosPendientes((int) $p['id'], $idEmpresa);
            return [CrmPublicacionRules::estadoSegunDestinos($this->repo->estadosDestinos((int) $p['id'])), []];
        }, 'CANCELAR_PENDIENTES');
        return $n;
    }

    /** Vuelve a intentar un destino que quedó con error. */
    public function reintentarDestino(int $id, int $idDestino, int $idEmpresa, int $idUsuario): void
    {
        $this->transicion($id, $idEmpresa, $idUsuario, function (array $p) use ($idDestino, $idEmpresa) {
            $d = array_values(array_filter($this->repo->destinos((int) $p['id']), fn($x) => (int) $x['id'] === $idDestino))[0] ?? null;
            if (!$d || $d['estado'] !== 'con_error') {
                throw new \InvalidArgumentException('Solo se reintenta un destino con error.');
            }
            $this->validarCanal($d);
            $this->repo->actualizarDestino($idDestino, ['estado' => 'pendiente', 'intentos' => 0, 'error' => null, 'siguiente_intento_at' => null,
                                                        'contenedor_externo' => null, 'contenedores' => null]);
            return ['publicando', $p['programada_at'] === null ? ['programada_at' => date('Y-m-d H:i:s')] : []];
        }, 'REINTENTAR_DESTINO');
    }

    /**
     * Eliminación lógica. No mientras algo se esté publicando. Lo ya publicado
     * sigue en la red (devuelve cuántos destinos estaban publicados, para avisar).
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): int
    {
        return $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $p = $this->repo->bloquear($id, $idEmpresa) ?? throw new \RuntimeException('Publicación no encontrada.');
            $estados = $this->repo->estadosDestinos($id);
            if (array_intersect($estados, ['preparando', 'publicando'])) {
                throw new \InvalidArgumentException('Se está publicando en este momento. Espere a que termine.');
            }
            $this->repo->cancelarDestinosPendientes($id, $idEmpresa);
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_publicaciones', $id, $p, null);
            return count(array_filter($estados, fn($e) => $e === 'publicada'));
        });
    }

    // ── Validaciones de conjunto ─────────────────────────────────────────────

    /** Lista para salir: al menos un destino y cada uno válido para su red. */
    private function validarCompleta(int $id, int $idEmpresa, bool $conCuentas = false): void
    {
        $pub = $this->repo->getPorId($id, $idEmpresa);
        $destinos = $this->repo->destinos($id);
        if (!$destinos) {
            throw new \InvalidArgumentException('Elija al menos una cuenta donde publicar.');
        }
        $medios = $this->repo->medios($id);
        foreach ($destinos as $d) {
            $texto = (string) ($d['texto'] ?? $pub['texto'] ?? '');
            try {
                $this->rules->validarDestino(['tipo' => $d['canal_tipo']], (string) $d['formato'], $medios, $texto);
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('@' . ($d['canal_usuario'] ?: $d['canal_nombre']) . ': ' . $e->getMessage());
            }
            if ($conCuentas && $d['estado'] !== 'publicada') {
                $this->validarCanal($d);
            }
        }
    }

    /** La cuenta está conectada y concedió el permiso de publicar. */
    private function validarCanal(array $d): void
    {
        $nombre = '@' . ($d['canal_usuario'] ?: $d['canal_nombre']);
        if ($d['canal_estado'] !== 'activo') {
            throw new \InvalidArgumentException("La cuenta {$nombre} está desconectada o con el acceso vencido. Reconéctela en Configuración del CRM.");
        }
        $permisos = json_decode((string) ($d['canal_permisos'] ?? '[]'), true) ?: [];
        $necesario = self::PERMISO_PUBLICAR[$d['canal_tipo']] ?? null;
        if ($necesario && !in_array($necesario, $permisos, true)) {
            throw new \InvalidArgumentException("La cuenta {$nombre} no dio permiso para publicar. Reconéctela en Configuración del CRM y acepte todos los permisos.");
        }
    }

    // ── Utilidades ───────────────────────────────────────────────────────────

    /**
     * Cambio de estado bajo candado. $fn(publicación) devuelve [estado nuevo, extra]
     * o lanza si no corresponde.
     */
    private function transicion(int $id, int $idEmpresa, int $idUsuario, callable $fn, string $accion): void
    {
        $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario, $fn, $accion) {
            $p = $this->repo->bloquear($id, $idEmpresa) ?? throw new \RuntimeException('Publicación no encontrada.');
            [$estado, $extra] = $fn($p);
            $this->repo->cambiarEstado($id, $idEmpresa, $estado, $idUsuario, $extra);
            $this->log->registrar($idUsuario, $idEmpresa, $accion, 'crm_publicaciones', $id,
                ['estado' => $p['estado']], ['estado' => $estado] + $extra);
        });
    }

    private function paraLog(array $d): array
    {
        return ['titulo' => $d['titulo'], 'es_contenido_ia' => $d['es_contenido_ia'], 'medios' => $d['medios'],
                'destinos' => array_map(fn($x) => $x['id_canal'] . ':' . $x['formato'], $d['destinos'])];
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
