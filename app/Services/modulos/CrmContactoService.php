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
