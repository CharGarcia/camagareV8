<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class VehiculoRepository extends BaseRepository
{
    protected string $table = 'vehiculos';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Devuelve el listado paginado y con búsqueda para Vehículos.
     */
    public function getListado(
        int $idEmpresa,
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir,
        ?int $idUsuarioFiltro = null
    ): array {
        $whereSql = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        $params   = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Buscador estándar (§9): texto libre sobre las columnas del listado + filtros clave:valor
        // de la ventana de filtros (FiltrosModal). Nunca se concatena la entrada del usuario.
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto([
                'v.marca', 'v.placa', 'v.chasis', 'v.anio::text', 'v.propietario', 'v.correo', 'v.telefono',
                'v.modelo', 'v.color', "TO_CHAR(v.created_at, 'DD-MM-YYYY')",
            ], $parsed['texto_libre'], $params, 'tl');
            if ($condicion !== '') {
                $whereSql .= " AND {$condicion}";
            }
        }
        // Órdenes de Car-Wash del vehículo (vivas y no anuladas): última visita y próxima cita.
        $subOrden = "FROM carwash_ordenes o WHERE o.id_vehiculo = v.id AND o.id_empresa = v.id_empresa AND o.eliminado = false AND o.estado <> 'anulado'";
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($whereSql, $params, $parsed['filtros'], [
            'texto' => [
                'marca'       => 'v.marca',
                'placa'       => 'v.placa',
                'modelo'      => 'v.modelo',
                'color'       => 'v.color',
                'chasis'      => 'v.chasis',
                'propietario' => 'v.propietario',
                'correo'      => 'v.correo',
                'telefono'    => 'v.telefono',
            ],
            'exacto' => [
                'estado'      => 'v.estado',
                'usuario'     => 'v.created_by',
                // con_ordenes:si / con_ordenes:no (tiene órdenes de Car-Wash)
                'con_ordenes' => "CASE WHEN EXISTS (SELECT 1 $subOrden) THEN 'si' ELSE 'no' END",
            ],
            'fecha' => [
                'fecha'         => 'v.created_at',
                'ultima_visita' => "(SELECT MAX(o.fecha_ingreso) $subOrden)",
                'proxima_cita'  => "(SELECT MAX(o.proxima_cita) $subOrden)",
            ],
            'numerico' => [
                'anio' => 'v.anio',
            ],
        ]);

        // Validación de columnas permitidas para order by
        $cols = [
            'id'          => 'v.id',
            'marca'       => 'v.marca',
            'placa'       => 'v.placa',
            'chasis'      => 'v.chasis',
            'anio'        => 'v.anio',
            'propietario' => 'v.propietario',
            'estado'      => 'v.estado',
            'correo'      => 'v.correo',
            'telefono'    => 'v.telefono',
            'created_at'  => 'v.created_at',
        ];
        $col  = $cols[$ordenCol] ?? 'v.id';
        $dir  = ($ordenDir === 'DESC') ? 'DESC' : 'ASC';

        // 1. Contar total
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} v {$whereSql}";
        $stCount  = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        // 2. Obtener filas
        $offset = ($page - 1) * $perPage;
        
        $sqlRows = "SELECT v.id, v.marca, v.placa, v.chasis, v.anio, v.propietario, v.estado, v.correo, v.telefono, v.created_at
                    FROM {$this->table} v
                    {$whereSql}
                    ORDER BY {$col} {$dir}, v.id DESC";
                    
        if ($perPage > 0) {
            $sqlRows .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;
        }

        $stRows = $this->db->prepare($sqlRows);
        $stRows->execute($params);
        $rows = $stRows->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total' => $total,
            'rows'  => $rows
        ];
    }

    /** Usuarios que registraron vehículos (select "Registrado por" de la ventana de filtros). */
    public function getUsuariosConVehiculos(int $idEmpresa): array
    {
        $st = $this->db->prepare("SELECT DISTINCT u.id, u.nombre FROM vehiculos v JOIN usuarios u ON u.id = v.created_by
                                   WHERE v.id_empresa = :e AND v.eliminado = false ORDER BY u.nombre");
        $st->execute([':e' => $idEmpresa]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica si existe otro vehículo con la misma placa para la misma empresa
     */
    public function existePlaca(int $idEmpresa, string $placa, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND REGEXP_REPLACE(UPPER(placa), '[^A-Z0-9]', '', 'g') = :placa
                  AND eliminado = false";
        // Sin guiones ni espacios: ABC-1234 y ABC1234 son la misma placa.
        $params = [
            ':id_empresa' => $idEmpresa,
            ':placa'      => preg_replace('/[^A-Z0-9]/', '', strtoupper($placa))
        ];

        if ($excluirId !== null && $excluirId > 0) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    /**
     * Obtiene el detalle de un vehículo incluyendo nombres de auditoría.
     */
    public function getDetalleCompleto(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT v.*, 
                       u_crea.nombre AS creado_por_nombre,
                       u_act.nombre AS actualizado_por_nombre
                FROM {$this->table} v
                LEFT JOIN usuarios u_crea ON u_crea.id = v.created_by
                LEFT JOIN usuarios u_act ON u_act.id = v.updated_by
                WHERE v.id = :id AND v.id_empresa = :id_empresa AND v.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Crea un nuevo vehículo.
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, created_by, marca, placa, chasis, anio, propietario, estado, correo, telefono, eliminado, created_at
                ) VALUES (
                    :id_empresa, :id_usuario, :created_by, :marca, :placa, :chasis, :anio, :propietario, :estado, :correo, :telefono, :eliminado, CURRENT_TIMESTAMP
                )";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':id_empresa' => $data['id_empresa'],
            ':id_usuario' => $data['id_usuario'],
            ':created_by' => $data['created_by'],
            ':marca'      => $data['marca'],
            ':placa'      => $data['placa'],
            ':chasis'     => $data['chasis'],
            ':anio'       => $data['anio'],
            ':propietario'=> $data['propietario'],
            ':estado'     => $data['estado'] ?? 'activo',
            ':correo'     => $data['correo'] ?? null,
            ':telefono'   => $data['telefono'] ?? null,
            ':eliminado'  => $data['eliminado'] ? 'true' : 'false'
        ]);
        return (int) $this->lastInsertId();
    }

    /**
     * Actualiza un vehículo existente.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $sql = "UPDATE {$this->table} SET 
                marca = :marca,
                placa = :placa,
                chasis = :chasis,
                anio = :anio,
                propietario = :propietario,
                estado = :estado,
                correo = :correo,
                telefono = :telefono,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':marca'      => $data['marca'],
            ':placa'      => $data['placa'],
            ':chasis'     => $data['chasis'],
            ':anio'       => $data['anio'],
            ':propietario'=> $data['propietario'],
            ':estado'     => $data['estado'],
            ':correo'     => $data['correo'] ?? null,
            ':telefono'   => $data['telefono'] ?? null,
            ':updated_by' => $data['updated_by'],
            ':id'         => $id,
            ':id_empresa' => $idEmpresa
        ]);
    }

    /**
     * Eliminación lógica con campos de auditoría.
     */
    public function delete(int $id, int $idEmpresa, int $idUsuario): bool
    {
        $sql = "UPDATE {$this->table} SET 
                eliminado = true, 
                deleted_by = :id_u,
                deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :id_empresa";
        $st = $this->db->prepare($sql);
        return $st->execute([
            ':id'         => $id, 
            ':id_empresa' => $idEmpresa,
            ':id_u'       => $idUsuario
        ]);
    }
}
