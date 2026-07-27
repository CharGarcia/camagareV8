<?php

declare(strict_types=1);

namespace App\repositories;

use App\Helpers\FiltrosBusqueda;

/**
 * Consulta de la bitácora `errores_sistema` (solo lectura, exclusiva nivel 3).
 *
 * Como es tarjeta de superadmin, ve TODOS los errores del sistema — no filtra por
 * empresa (muchos errores no tienen empresa/sesión). La tabla no tiene `eliminado`,
 * así que no se usa getBaseWhere(); el WHERE se arma a mano con FiltrosBusqueda.
 */
class ErroresSistemaRepository extends BaseRepository
{
    /** Columnas permitidas para ordenar (whitelist → nombre real de columna). */
    private const MAPA_ORDEN = [
        'created_at' => 'e.created_at',
        'tipo'       => 'e.tipo',
        'sql_state'  => 'e.sql_state',
        'ruta'       => 'e.ruta',
        'id'         => 'e.id',
    ];

    public function __construct()
    {
        parent::__construct('errores_sistema');
    }

    public function getListado(
        string $buscar,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        $where  = ' WHERE 1=1 ';
        $params = [];

        $parsed = FiltrosBusqueda::parsear($buscar);

        // Texto libre → busca en mensaje, clase, ruta y SQLSTATE.
        if (($parsed['texto_libre'] ?? '') !== '') {
            $where .= " AND (e.mensaje ILIKE :txt OR e.clase ILIKE :txt OR e.ruta ILIKE :txt OR e.sql_state ILIKE :txt) ";
            $params[':txt'] = '%' . $parsed['texto_libre'] . '%';
        }

        // Filtros clave:valor (tipo:fatal, sqlstate:22P02, ruta:factura, etc.).
        // El mapa se agrupa por TIPO de campo (texto/exacto/numerico/fecha).
        FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'] ?? [], [
            'texto' => [
                'sqlstate'  => 'e.sql_state',
                'sql_state' => 'e.sql_state',
                'clase'     => 'e.clase',
                'ruta'      => 'e.ruta',
                'accion'    => 'e.accion',
                'mensaje'   => 'e.mensaje',
            ],
            'exacto' => [
                'tipo' => 'e.tipo',
            ],
            'numerico' => [
                'usuario' => 'e.id_usuario',
                'empresa' => 'e.id_empresa',
            ],
            'fecha' => [
                'fecha' => 'e.created_at',
            ],
        ]);

        // Total
        $stTot = $this->db->prepare("SELECT COUNT(*) FROM errores_sistema e {$where}");
        $stTot->execute($params);
        $total = (int) $stTot->fetchColumn();

        // Página
        $colSql  = self::MAPA_ORDEN[$ordenCol] ?? 'e.created_at';
        $dirSql  = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        $sql = "SELECT e.id, e.id_empresa, e.id_usuario, e.tipo, e.clase, e.mensaje, e.sql_state,
                       e.archivo, e.linea, e.ruta, e.accion, e.url, e.metodo_http, e.ip_usuario,
                       e.user_agent, e.traza, e.created_at,
                       u.nombre AS usuario_nombre,
                       em.nombre AS empresa_nombre
                FROM errores_sistema e
                LEFT JOIN usuarios u  ON u.id  = e.id_usuario
                LEFT JOIN empresas em ON em.id = e.id_empresa
                {$where}
                ORDER BY {$colSql} {$dirSql}, e.id {$dirSql}
                LIMIT {$perPage} OFFSET {$offset}";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(\PDO::FETCH_ASSOC), 'total' => $total];
    }

    public function getPorId(int $id): ?array
    {
        $sql = "SELECT e.*, u.nombre AS usuario_nombre, em.nombre AS empresa_nombre
                FROM errores_sistema e
                LEFT JOIN usuarios u  ON u.id  = e.id_usuario
                LEFT JOIN empresas em ON em.id = e.id_empresa
                WHERE e.id = :id";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id]);
        return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
