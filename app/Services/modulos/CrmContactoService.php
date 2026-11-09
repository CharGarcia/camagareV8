<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\CrmCatalogoRepository;
use App\repositories\modulos\CrmContactoRepository;
use App\Rules\modulos\CrmContactoRules;
use App\Services\GuardadoUnicoService;
use App\Services\LogSistemaService;

/** CRM: contactos (leads). Diseño: docs/diseno/crm-redes-fase1.md §5.2. */
class CrmContactoService
{
    public const MODULO_GUARDADO = 'crm_contactos';

    /** Indica si el último crear() devolvió un contacto ya creado por el mismo formulario. */
    public bool $ultimoGuardadoPrevio = false;

    public function __construct(
        private CrmContactoRepository $repo,
        private CrmCatalogoRepository $catalogos,
        private CrmContactoRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmContactoRepository(), new CrmCatalogoRepository(), new CrmContactoRules(), new LogSistemaService());
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, array $orden, ?int $idUsuarioFiltro): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $orden, $idUsuarioFiltro);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getPorId($id, $idEmpresa);
    }

    /** Ids de Instagram adicionales (quedaron al unir contactos). */
    public function alias(int $id, int $idEmpresa): array
    {
        return $this->repo->aliasDe($id, $idEmpresa);
    }

    /** Se pueden unir contactos (la tabla de alias ya existe). */
    public function unirDisponible(): bool
    {
        return $this->repo->hayAlias();
    }

    public function buscar(int $idEmpresa, string $q, ?int $idUsuarioFiltro, ?int $id = null): array
    {
        return $this->repo->buscarParaSelect($idEmpresa, $q, $idUsuarioFiltro, $id);
    }

    /**
     * Crea el contacto. Guardado único (CLAUDE.md §8): un reintento con el mismo
     * token devuelve el contacto ya creado. Devuelve ['id' => …, 'aviso' => ?string]
     * (aviso = posible duplicado por correo/teléfono; no impide guardar).
     */
    public function crearContacto(array $d, int $idEmpresa, int $idUsuario): array
    {
        $this->ultimoGuardadoPrevio = false;
        $d = $this->normalizar($d, $idEmpresa);
        $guardado = new GuardadoUnicoService();
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            if ($previo = $guardado->previo($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO)) {
                if ($managed) $db->rollBack();
                $this->ultimoGuardadoPrevio = true;
                return ['id' => (int) $previo['id_registro'], 'aviso' => null];
            }
            $this->rules->validar($d);
            $dup = $this->repo->buscarDuplicado($idEmpresa, $d['correo'] ?? null, $d['telefono'] ?? null);

            $id = $this->repo->insertar($d, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_contactos', $id, null, $this->paraLog($d));
            $guardado->registrar($d['token_guardado'] ?? '', $idEmpresa, self::MODULO_GUARDADO, $id, null, $idUsuario);
            if ($managed) $db->commit();

            return ['id' => $id, 'aviso' => $dup ? "Ya existía un contacto con el mismo correo o teléfono: «{$dup['nombre']}»." : null];
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $d, int $idEmpresa, int $idUsuario): ?string
    {
        $d = $this->normalizar($d, $idEmpresa);
        $this->rules->validar($d);
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Contacto no encontrado.');
            }
            // Campos que no se editan desde el formulario: los llena el sistema.
            $d['instagram_id']     = $antes['instagram_id'];
            $d['id_whatsapp_chat'] = $antes['id_whatsapp_chat'];
            $d['id_cliente']       = $d['id_cliente'] ?? $antes['id_cliente'];

            $dup = $this->repo->buscarDuplicado($idEmpresa, $d['correo'] ?? null, $d['telefono'] ?? null, $id);
            $this->repo->actualizar($id, $d, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_contactos', $id, $antes, $this->paraLog($d));
            if ($managed) $db->commit();
            return $dup ? "Hay otro contacto con el mismo correo o teléfono: «{$dup['nombre']}»." : null;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Contacto no encontrado.');
            }
            $this->rules->validarEliminar($this->repo->contarOportunidades($id, $idEmpresa));
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_contactos', $id, $antes, null);
            if ($managed) $db->commit();
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Une dos contactos de la misma persona: el que se une ($idSeUne) pasa al
     * que queda ($idQueda) sus oportunidades, conversaciones, actividades y su id
     * de Instagram (como alias, para que el webhook lo siga reconociendo), y luego
     * se elimina (lógico). En el que queda solo se completan los datos vacíos;
     * las notas de los dos se juntan. Devuelve el resumen de lo movido.
     */
    public function unir(int $idQueda, int $idSeUne, int $idEmpresa, int $idUsuario): array
    {
        if (!$this->repo->hayAlias()) {
            throw new \RuntimeException('Falta actualizar la base de datos del CRM (tabla crm_contactos_alias) para poder unir contactos.');
        }
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $this->repo->lockUnion($idQueda, $idSeUne, $idEmpresa);
            $queda = $this->repo->getPorId($idQueda, $idEmpresa);
            $seUne = $this->repo->getPorId($idSeUne, $idEmpresa);
            $this->rules->validarUnir($queda, $seUne);

            // Mismo candado que usa el webhook al buscar o crear el contacto de un id de Instagram.
            foreach ([$queda['instagram_id'], $seUne['instagram_id']] as $ig) {
                if ($ig) $this->repo->lockInstagram($idEmpresa, (string) $ig);
            }

            $d = [];
            foreach (['empresa_contacto', 'cargo', 'telefono', 'correo', 'instagram_usuario', 'linkedin_url',
                      'id_cliente', 'id_whatsapp_chat', 'id_origen', 'id_responsable'] as $c) {
                $d[$c] = ($queda[$c] ?? null) !== null && $queda[$c] !== '' ? $queda[$c] : ($seUne[$c] ?? null);
            }
            $d['nombre'] = $queda['nombre'];
            $notas = array_filter([trim((string) $queda['notas']), trim((string) $seUne['notas'])], fn($n) => $n !== '');
            $d['notas'] = $notas ? implode("\n\n", array_unique($notas)) : null;

            // Id de Instagram: si el que queda no tiene, toma el del otro; si los dos
            // tienen (DM y comentario con ids distintos), el del otro queda como alias.
            $igQueda = (string) ($queda['instagram_id'] ?? '');
            $igSeUne = (string) ($seUne['instagram_id'] ?? '');
            $d['instagram_id'] = $igQueda !== '' ? $igQueda : ($igSeUne !== '' ? $igSeUne : null);

            $movidas = $this->repo->moverReferencias($idSeUne, $idQueda, $idEmpresa, $idUsuario);
            // Primero se elimina el que se une: libera su id de Instagram (índice único).
            $this->repo->eliminar($idSeUne, $idEmpresa, $idUsuario);
            $this->repo->actualizar($idQueda, $d, $idEmpresa, $idUsuario);
            $this->repo->moverAlias($idSeUne, $idQueda, $d['instagram_id'], $idEmpresa, $idUsuario);
            if ($igQueda !== '' && $igSeUne !== '' && $igSeUne !== $igQueda) {
                $this->repo->agregarAlias($idQueda, $igSeUne, $idEmpresa, $idUsuario);
            }

            $this->log->registrar($idUsuario, $idEmpresa, 'UNIR', 'crm_contactos', $idQueda,
                ['contacto' => $this->paraLog($queda), 'unido' => $this->paraLog($seUne) + ['id' => $idSeUne, 'instagram_id' => $igSeUne ?: null]],
                $this->paraLog($d) + ['instagram_id' => $d['instagram_id'], 'movidas' => $movidas]);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_contactos', $idSeUne,
                $seUne, ['unido_a' => $idQueda]);
            if ($managed) $db->commit();
            return ['id' => $idQueda, 'movidas' => $movidas];
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Limpia la entrada del formulario: recorta, quita la @ inicial de Instagram,
     * y descarta ids de responsable/origen/cliente que no son de esta empresa.
     */
    private function normalizar(array $d, int $idEmpresa): array
    {
        foreach (['nombre', 'empresa_contacto', 'cargo', 'telefono', 'correo', 'instagram_usuario', 'linkedin_url', 'notas'] as $c) {
            $d[$c] = isset($d[$c]) ? trim((string) $d[$c]) : null;
        }
        if (!empty($d['instagram_usuario'])) {
            $d['instagram_usuario'] = ltrim($d['instagram_usuario'], '@');
        }
        if (!empty($d['correo'])) {
            $d['correo'] = mb_strtolower($d['correo']);
        }

        $resp = (int) ($d['id_responsable'] ?? 0);
        if ($resp > 0 && !$this->catalogos->usuarioPerteneceEmpresa($resp, $idEmpresa)) {
            throw new \InvalidArgumentException('El responsable seleccionado no pertenece a esta empresa.');
        }
        $d['id_responsable'] = $resp ?: null;

        $origen = (int) ($d['id_origen'] ?? 0);
        if ($origen > 0 && !$this->catalogos->obtener('origenes', $origen, $idEmpresa)) {
            throw new \InvalidArgumentException('Origen no válido.');
        }
        $d['id_origen'] = $origen ?: null;

        $cliente = (int) ($d['id_cliente'] ?? 0);
        if ($cliente > 0 && !$this->catalogos->getCliente($cliente, $idEmpresa)) {
            throw new \InvalidArgumentException('El cliente seleccionado no existe en esta empresa.');
        }
        $d['id_cliente'] = $cliente ?: null;

        $d['instagram_id']     = $d['instagram_id'] ?? null;
        $d['id_whatsapp_chat'] = isset($d['id_whatsapp_chat']) && (int) $d['id_whatsapp_chat'] > 0 ? (int) $d['id_whatsapp_chat'] : null;
        return $d;
    }

    private function paraLog(array $d): array
    {
        return array_intersect_key($d, array_flip([
            'nombre', 'empresa_contacto', 'telefono', 'correo', 'instagram_usuario', 'linkedin_url',
            'id_cliente', 'id_origen', 'id_responsable',
        ]));
    }
}
