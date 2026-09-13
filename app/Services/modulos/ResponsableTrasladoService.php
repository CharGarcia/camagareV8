<?php
/**
 * Lógica de negocio de Responsables de Traslado.
 *
 * Lo usan tanto el módulo propio (modulos/responsables-traslado) como el alta
 * rápida desde el modal de Pedidos y de Consignaciones de Venta: ahí antes se
 * escribía el INSERT a mano dentro del controller (SQL crudo duplicado en dos
 * sitios). Todo pasa ahora por aquí, así la validación, la normalización y la
 * auditoría son las mismas venga de donde venga el alta.
 */

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ResponsableTrasladoRepository;
use App\Rules\modulos\ResponsableTrasladoRules;
use App\Services\LogSistemaService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ResponsableTrasladoService
{
    private const TABLA = 'responsables_traslado';

    private ResponsableTrasladoRepository $repo;
    private ResponsableTrasladoRules $rules;
    private LogSistemaService $log;

    public function __construct(
        ?ResponsableTrasladoRepository $repo = null,
        ?ResponsableTrasladoRules $rules = null,
        ?LogSistemaService $log = null
    ) {
        $this->repo  = $repo  ?? new ResponsableTrasladoRepository();
        $this->rules = $rules ?? new ResponsableTrasladoRules();
        $this->log   = $log   ?? new LogSistemaService();
    }

    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getPorId($id, $idEmpresa);
    }

    /** Responsables activos para los selects de Pedidos / Consignaciones. */
    public function listarActivos(int $idEmpresa): array
    {
        return $this->repo->listarPorEmpresa($idEmpresa);
    }

    public function crear(array $data): int
    {
        $data = $this->normalizar($data);
        $this->rules->validarCrear($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        if ($data['identificacion'] !== null
            && $this->repo->existeIdentificacion($idEmpresa, $data['identificacion'])) {
            throw new InvalidArgumentException('Ya existe un responsable de traslado con esa identificación en esta empresa.');
        }
        if ($this->repo->existeNombre($idEmpresa, $data['nombre'])) {
            throw new InvalidArgumentException('Ya existe un responsable de traslado con ese nombre en esta empresa.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertar($data);

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'CREAR',
                self::TABLA,
                $id,
                null,
                [
                    'nombre'         => $data['nombre'],
                    'identificacion' => $data['identificacion'],
                    'telefono'       => $data['telefono'],
                    'email'          => $data['email'],
                    'estado'         => $data['estado'],
                ]
            );

            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): void
    {
        $data       = $this->normalizar($data);
        $data['id'] = $id;
        $this->rules->validarActualizar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $actual = $this->repo->getPorId($id, $idEmpresa);
        if (!$actual) {
            throw new RuntimeException('Responsable de traslado no encontrado.');
        }

        if ($data['identificacion'] !== null
            && $this->repo->existeIdentificacion($idEmpresa, $data['identificacion'], $id)) {
            throw new InvalidArgumentException('Ya existe otro responsable de traslado con esa identificación en esta empresa.');
        }
        if ($this->repo->existeNombre($idEmpresa, $data['nombre'], $id)) {
            throw new InvalidArgumentException('Ya existe otro responsable de traslado con ese nombre en esta empresa.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->actualizar($id, $data);

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'ACTUALIZAR',
                self::TABLA,
                $id,
                $actual,
                [
                    'nombre'         => $data['nombre'],
                    'identificacion' => $data['identificacion'],
                    'telefono'       => $data['telefono'],
                    'email'          => $data['email'],
                    'estado'         => $data['estado'],
                ]
            );

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Eliminación lógica. Se bloquea si el responsable ya figura en documentos:
     * borrarlo dejaría pedidos y consignaciones apuntando a un registro
     * invisible, y sus listados mostrarían la columna vacía. Para sacarlo de
     * circulación sin perder el histórico está el estado "inactivo", que lo
     * quita de los selects pero conserva el nombre en los documentos viejos.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getPorId($id, $idEmpresa);
        if (!$actual) {
            throw new RuntimeException('Responsable de traslado no encontrado.');
        }

        $usos = $this->repo->getUsos($id, $idEmpresa);
        if ($usos) {
            throw new RuntimeException(
                'No se puede eliminar: el responsable está siendo usado en ' . implode(', ', $usos) . '. '
                . 'Cámbielo a estado "Inactivo" para que deje de aparecer en los formularios.'
            );
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', self::TABLA, $id, $actual, null);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Nombre en MAYÚSCULAS (igual que Clientes / Transportistas), correo en
     * minúsculas y campos opcionales vacíos guardados como NULL en vez de ''
     * (así el índice único parcial de identificación no los cuenta).
     */
    private function normalizar(array $data): array
    {
        $opcional = static fn($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v);

        $data['nombre']         = mb_strtoupper(trim((string) ($data['nombre'] ?? '')));
        $data['identificacion'] = $opcional($data['identificacion'] ?? null);
        $data['telefono']       = $opcional($data['telefono'] ?? null);
        $email                  = $opcional($data['email'] ?? null);
        $data['email']          = $email !== null ? mb_strtolower($email) : null;
        $data['estado']         = trim((string) ($data['estado'] ?? 'activo')) ?: 'activo';

        return $data;
    }
}
