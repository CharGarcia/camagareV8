<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

class VendedorRepository extends BaseRepository
{
    public const COLUMNAS_ORDEN = ['nombre', 'identificacion', 'correo', 'telefono', 'direccion', 'status'];

    public function __construct()
    {
        parent::__construct('vendedores');
    }

    /**
     * Obtiene solo los vendedores activos permitidos.
     */
    public function getVendedoresActivos(int $idEmpresa, ?int $idUsuarioFiltro = null): array
    {
        $where = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        $where .= " AND v.status = 1"; // Fixed integer = boolean error
        
        $sql = "SELECT v.id, v.nombre 
                FROM {$this->table} v 
                $where 
                ORDER BY v.nombre ASC";
                
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de vendedores con filtros y paginación.
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre';
        }
        $ordenDir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        
        $where = $this->getBaseWhere($idEmpresa, 'v', $idUsuarioFiltro);
        $params = [':id_empresa' => $idEmpresa];
        if ($idUsuarioFiltro !== null) {
            $params[':id_usuario_filtro'] = $idUsuarioFiltro;
        }

        // Usuario que registró (texto libre). Solo el listado del módulo busca texto:
        // el resto de llamadores (combos de vendedor en facturas, proformas…) pasan ''.
        $joins = "LEFT JOIN usuarios ureg ON ureg.id = v.created_by";

        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            // Texto libre estándar (todas las palabras, en cualquier orden, sin tildes)
            // sobre las columnas del listado + lo que identifica al vendedor. Antes era
            // un ILIKE de la frase completa sobre nombre, identificación y correo.
            // Decisión del usuario: Estado NO entra en el texto libre (se filtra desde
            // el modal de filtros).
            $condicion = \App\Helpers\FiltrosBusqueda::condicionTexto(
                [
                    'v.nombre',          // Nombre Vendedor
                    'v.identificacion',  // Identificación
                    'v.correo',          // Correo
                    'v.telefono',        // Teléfono
                    'v.direccion',       // Dirección (ficha)
                    'ureg.nombre',       // Usuario que registró (ficha)
                ],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
            if ($condicion !== '') {
                $where .= " AND {$condicion}";
            }
        }

        // v.status es entero (1 = activo); el modal envía 'activo'/'inactivo'.
        foreach (['estado', 'status'] as $claveEstado) {
            if (!isset($parsed['filtros'][$claveEstado])) {
                continue;
            }
            $mapEstado = ['activo' => '1', 'inactivo' => '0'];
            $val = $parsed['filtros'][$claveEstado]['valor'];
            $parsed['filtros'][$claveEstado]['valor'] = is_array($val)
                ? array_map(fn($x) => $mapEstado[strtolower(trim((string) $x))] ?? $x, $val)
                : ($mapEstado[strtolower(trim((string) $val))] ?? $val);
        }

        $exacto = [
            // Antes apuntaba a v.estado, columna que no existe: cualquier estado:… lanzaba
            // error de SQL. Igual que la columna Estado del listado, solo status = 1
            // (o NULL) es "Activo".
            'estado'    => "CASE WHEN COALESCE(v.status, 1) = 1 THEN '1' ELSE '0' END",
            'status'    => 'v.status',
            'usuario'   => 'v.created_by',
            // Sí/No calculados.
            'con_clientes' => "CASE WHEN EXISTS (SELECT 1 FROM clientes cl WHERE cl.id_empresa = v.id_empresa AND cl.id_vendedor = v.id AND cl.eliminado = false) THEN 'si' ELSE 'no' END",
            'con_email'    => "CASE WHEN NULLIF(TRIM(COALESCE(v.correo, '')), '') IS NULL THEN 'no' ELSE 'si' END",
        ];
        if ($this->tieneVinculoExplicito()) {
            // Usuario del sistema vinculado (columna opcional según la migración aplicada).
            $exacto['usuario_vinculado'] = 'v.id_usuario_vinculado';
            $exacto['con_usuario']       = "CASE WHEN v.id_usuario_vinculado IS NOT NULL THEN 'si' ELSE 'no' END";
        }

        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'nombre'         => 'v.nombre',
                'vendedor'       => 'v.nombre',
                'identificacion' => 'v.identificacion',
                'ci'             => 'v.identificacion',
                'email'          => 'v.correo',
                'correo'         => 'v.correo',
                'telefono'       => 'v.telefono',
                'direccion'      => 'v.direccion',
            ],
            'exacto' => $exacto,
            'fecha'  => [
                'registro'   => 'v.created_at',
                'created_at' => 'v.created_at',
            ],
        ]);

        // Obtener total (mismos JOIN que las filas: el texto libre usa ureg).
        $sqlCount = "SELECT COUNT(*) FROM {$this->table} v $joins $where";
        $stCount = $this->db->prepare($sqlCount);
        $stCount->execute($params);
        $total = (int) $stCount->fetchColumn();

        $rows = [];
        if ($total > 0) {
            $limitOffset = "";
            if ($perPage > 0) {
                $offset = ($page - 1) * $perPage;
                $limitOffset = " LIMIT $perPage OFFSET $offset";
            }
            $sql = "SELECT v.*
                    FROM {$this->table} v
                    $joins
                    $where
                    ORDER BY v.{$ordenCol} $ordenDir, v.id DESC
                    $limitOffset";
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Opciones de los selects del modal de filtros del listado: solo los usuarios que
     * la empresa realmente usa en sus vendedores (no eliminados). `vinculados` queda
     * vacío si la columna id_usuario_vinculado aún no existe.
     *
     * @return array{usuarios: array, vinculados: array, con_vinculo: bool}
     */
    public function getOpcionesFiltroListado(int $idEmpresa): array
    {
        $leer = function (string $col) use ($idEmpresa): array {
            $st = $this->db->prepare("SELECT DISTINCT u.id, u.nombre
                                      FROM vendedores v JOIN usuarios u ON u.id = v.{$col}
                                      WHERE v.id_empresa = :id_empresa AND v.eliminado = false
                                      ORDER BY u.nombre");
            $st->execute([':id_empresa' => $idEmpresa]);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        };

        return [
            'usuarios'   => $leer('created_by'),
            'vinculados' => $this->tieneVinculoExplicito() ? $leer('id_usuario_vinculado') : [],
            'con_vinculo' => $this->tieneVinculoExplicito(),
        ];
    }

    /**
     * Verifica si una identificación ya existe en la empresa.
     */
    public function existeIdentificacion(int $idEmpresa, string $identificacion, ?int $idExcluir = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} 
                WHERE id_empresa = :id_empresa 
                  AND identificacion = :identificacion 
                  AND eliminado = false";
        $params = [
            ':id_empresa'    => $idEmpresa,
            ':identificacion' => $identificacion
        ];

        if ($idExcluir !== null) {
            $sql .= " AND id <> :id_exc";
            $params[':id_exc'] = $idExcluir;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return ((int) $st->fetchColumn()) > 0;
    }

    /**
     * Inserta un nuevo vendedor con campos de auditoría.
     */
    public function create(array $data): int
    {
        // OJO: id_usuario guarda el usuario que CREA el registro (igual que
        // created_by). El asesor dueño de la ficha va en id_usuario_vinculado.
        $conVinculo = $this->tieneVinculoExplicito();
        $colVinculo = $conVinculo ? ', id_usuario_vinculado' : '';
        $valVinculo = $conVinculo ? ', :id_usuario_vinculado' : '';

        $sql = "INSERT INTO {$this->table} (
                    id_empresa, id_usuario, nombre, identificacion, telefono, correo,
                    direccion, status, created_by, created_at, eliminado{$colVinculo}
                ) VALUES (
                    :id_empresa, :id_usuario, :nombre, :identificacion, :telefono, :correo,
                    :direccion, :status, :id_u, CURRENT_TIMESTAMP, false{$valVinculo}
                )";
        $params = [
            ':id_empresa'       => $data['id_empresa'],
            ':id_usuario'       => $data['id_usuario'],
            ':nombre'           => $data['nombre'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'] ?? null,
            ':correo'           => $data['correo'] ?? null,
            ':direccion'        => $data['direccion'] ?? null,
            ':status'           => $data['status'] ?? 1,
            ':id_u'             => $data['id_usuario']
        ];
        if ($conVinculo) {
            $params[':id_usuario_vinculado'] = $data['id_usuario_vinculado'] ?? null;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $this->lastInsertId();
    }

    /**
     * Actualiza un vendedor con campos de auditoría.
     */
    public function update(int $id, int $idEmpresa, array $data): bool
    {
        $conVinculo = $this->tieneVinculoExplicito();
        $setVinculo = $conVinculo ? ",
                id_usuario_vinculado = :id_usuario_vinculado" : '';

        $sql = "UPDATE {$this->table} SET
                nombre = :nombre,
                identificacion = :identificacion,
                telefono = :telefono,
                correo = :correo,
                direccion = :direccion,
                status = :status,
                updated_by = :id_u,
                updated_at = CURRENT_TIMESTAMP{$setVinculo}
                WHERE id = :id AND id_empresa = :id_empresa AND eliminado = false";
        $params = [
            ':nombre'           => $data['nombre'],
            ':identificacion'   => $data['identificacion'],
            ':telefono'         => $data['telefono'] ?? null,
            ':correo'           => $data['correo'] ?? null,
            ':direccion'        => $data['direccion'] ?? null,
            ':status'           => $data['status'] ?? 1,
            ':id_u'             => $data['id_usuario'],
            ':id'               => $id,
            ':id_empresa'       => $idEmpresa
        ];
        if ($conVinculo) {
            $params[':id_usuario_vinculado'] = $data['id_usuario_vinculado'] ?? null;
        }

        $st = $this->db->prepare($sql);
        return $st->execute($params);
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

    /**
     * Obtiene el detalle de un vendedor incluyendo nombres de auditoría.
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
     * Vendedor (asesor) que corresponde a una cuenta de usuario. Se usa para
     * restringir reportes a "mis ventas" cuando quien consulta es un usuario de
     * nivel 1.
     *
     * Se resuelve por dos vías, en este orden:
     *   1. El VÍNCULO EXPLÍCITO de la ficha del vendedor
     *      (vendedores.id_usuario_vinculado, campo "Usuario del sistema"). Manda
     *      sobre todo lo demás: es lo que el administrador declaró a mano.
     *   2. La CÉDULA: la identificación del vendedor contra la cédula del
     *      usuario (que es además su usuario de inicio de sesión), comparando
     *      solo los dígitos para que no estorben guiones ni espacios. También
     *      calza si uno está registrado con el RUC de persona natural y el otro
     *      con la cédula (1712345678 vs 1712345678001): se comparan entonces los
     *      primeros 10 dígitos, que son la cédula. Un RUC de sociedad nunca
     *      choca con una cédula válida (su tercer dígito es 6 o 9, el de una
     *      cédula < 6).
     *
     * OJO con dos criterios que NO se usan a propósito:
     *   - vendedores.id_usuario: esa columna guarda el usuario que CREÓ el
     *     registro (así la escriben tanto el alta manual como el migrador de
     *     MySQL), de modo que vincularía a todos los vendedores con el
     *     administrador que los dio de alta. Por eso el vínculo explícito vive
     *     en una columna aparte, id_usuario_vinculado.
     *   - el correo: es habitual que varios usuarios compartan el correo
     *     corporativo (info@empresa.com), y ahí el cruce le entregaría a un
     *     asesor las ventas de otro. Ante la duda es preferible no resolver el
     *     vendedor (reporte vacío + aviso) que resolverlo mal.
     */
    public function getPorUsuario(int $idEmpresa, int $idUsuario): ?array
    {
        // Solo dígitos: la identificación puede venir con guiones o espacios.
        $identVend = "regexp_replace(COALESCE(v.identificacion, ''), '[^0-9]', '', 'g')";
        $cedulaUsr = "regexp_replace(COALESCE(u.cedula, ''), '[^0-9]', '', 'g')";
        // Igual, o igual en sus primeros 10 dígitos (RUC de persona natural
        // frente a la cédula de esa misma persona).
        $porCedula = "({$identVend} <> ''
                       AND ({$identVend} = {$cedulaUsr}
                            OR (LENGTH({$identVend}) >= 10 AND LENGTH({$cedulaUsr}) >= 10
                                AND LEFT({$identVend}, 10) = LEFT({$cedulaUsr}, 10))))";

        // Mientras no se haya ejecutado database/vendedores_usuario_vinculado.sql
        // la columna no existe: se trabaja solo con la cédula en vez de romper.
        $explicito = $this->tieneVinculoExplicito() ? 'v.id_usuario_vinculado = u.id' : 'false';

        $sql = "SELECT v.id, v.nombre
                FROM {$this->table} v
                JOIN usuarios u ON u.id = :id_usuario AND u.eliminado = false
                WHERE v.id_empresa = :id_empresa
                  AND v.eliminado = false
                  AND ({$explicito} OR {$porCedula})
                ORDER BY CASE WHEN {$explicito} THEN 1 ELSE 2 END,
                         COALESCE(v.status, 0) DESC, v.id ASC
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':id_empresa' => $idEmpresa, ':id_usuario' => $idUsuario]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * ¿Ya existe la columna del vínculo explícito? Se consulta una sola vez por
     * petición para que el módulo siga funcionando entre el despliegue del
     * código y la ejecución de database/vendedores_usuario_vinculado.sql.
     */
    public function tieneVinculoExplicito(): bool
    {
        static $existe = null;
        if ($existe === null) {
            $st = $this->db->query(
                "SELECT 1 FROM information_schema.columns
                  WHERE table_name = 'vendedores' AND column_name = 'id_usuario_vinculado' LIMIT 1"
            );
            $existe = (bool) $st->fetchColumn();
        }
        return $existe;
    }

    /**
     * Usuarios que pueden vincularse como "Usuario del sistema" de un vendedor:
     * los asignados a la empresa (empresa_asignada) y activos. Cada uno indica
     * si ya está tomado por OTRO vendedor de la misma empresa, para que la ficha
     * lo muestre deshabilitado en vez de fallar al guardar.
     *
     * Se incluye además el usuario que YA está vinculado al vendedor que se está
     * editando, aunque hoy no cumpla esas condiciones (le quitaron la empresa o
     * lo desactivaron): si no saliera en la lista, editar cualquier otro dato de
     * la ficha borraría el vínculo sin avisar.
     *
     * @param int $idVendedorActual Vendedor que se está editando (0 = nuevo).
     */
    public function getUsuariosVinculables(int $idEmpresa, int $idVendedorActual = 0): array
    {
        $conVinculo = $this->tieneVinculoExplicito();

        $ocupado = $conVinculo
            ? "(SELECT vo.nombre FROM {$this->table} vo
                 WHERE vo.id_empresa = :id_empresa_ocup AND vo.eliminado = false
                   AND vo.id_usuario_vinculado = u.id AND vo.id <> :id_vendedor_ocup
                 LIMIT 1)"
            : 'NULL::text';

        $yaVinculado = $conVinculo
            ? "OR u.id = (SELECT vx.id_usuario_vinculado FROM {$this->table} vx
                           WHERE vx.id = :id_vendedor_actual AND vx.id_empresa = :id_empresa_act)"
            : '';

        $sql = "SELECT u.id, u.nombre, u.cedula, u.nivel,
                       {$ocupado} AS tomado_por
                FROM usuarios u
                WHERE u.eliminado = false
                  AND ( (COALESCE(u.estado, 1) = 1
                         AND EXISTS (SELECT 1 FROM empresa_asignada ea
                                      WHERE ea.id_empresa = :id_empresa AND ea.id_usuario = u.id))
                        {$yaVinculado} )
                ORDER BY u.nombre ASC";

        $params = [':id_empresa' => $idEmpresa];
        if ($conVinculo) {
            $params[':id_empresa_ocup']    = $idEmpresa;
            $params[':id_empresa_act']     = $idEmpresa;
            $params[':id_vendedor_ocup']   = $idVendedorActual;
            $params[':id_vendedor_actual'] = $idVendedorActual;
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta cuántos clientes activos tiene asignados el vendedor.
     */
    public function contarClientesAsignados(int $id, int $idEmpresa): int
    {
        $sql = "SELECT COUNT(*) FROM clientes 
                WHERE id_vendedor = :id 
                  AND id_empresa = :id_empresa 
                  AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':id_empresa' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }
}
