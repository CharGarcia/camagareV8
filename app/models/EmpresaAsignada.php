<?php
/**
 * Modelo EmpresaAsignada - Asignar empresas a usuarios
 *
 * empresa_asignada: id, id_empresa, id_usuario, usu_asignador, fecha_agredado
 * usuario_asignado: id, id_usuario, id_adm (admin que gestiona al usuario)
 *
 * Super admin (nivel 3): asigna a admins y usuarios. Cualquier empresa.
 * Admin (nivel 2): asigna solo a usuarios finales (nivel 1). Solo empresas que tiene asignadas.
 */

declare(strict_types=1);

namespace App\models;

class EmpresaAsignada extends BaseModel
{
    /**
     * Usuarios a los que el actual puede asignar empresas.
     * Super admin: todos los usuarios nivel 1 y 2.
     * Admin: usuarios asignados a las empresas que el admin tiene acceso (comparten al menos una empresa).
     */
    public function getUsuariosAsignables(int $idActual, int $nivel, string $buscar = '', int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        $idActual = (int) $idActual;

        if ($nivel >= 3) {
            $where = "WHERE u.estado = 1 AND u.nivel < 3 AND u.id != {$idActual}";
            $from = "usuarios u";
        } else {
            $where = "WHERE u.estado = 1 AND u.nivel < 3 AND u.id != {$idActual}";
            $from = "empresa_asignada ea_admin
                INNER JOIN empresa_asignada ea_otro ON ea_otro.id_empresa = ea_admin.id_empresa AND ea_otro.id_usuario != {$idActual}
                INNER JOIN usuarios u ON u.id = ea_otro.id_usuario";
            $where .= " AND ea_admin.id_usuario = {$idActual}";
        }

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (u.nombre LIKE '%{$b}%' OR u.cedula LIKE '%{$b}%')";
        }

        $countSql = "SELECT COUNT(DISTINCT u.id) AS total FROM {$from} {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);

        $sql = "SELECT DISTINCT u.id AS id_usuario, u.nombre, u.cedula, u.nivel
            FROM {$from} {$where}
            ORDER BY u.nombre
            LIMIT {$offset}, {$perPage}";
        $rows = $this->query($sql);

        foreach ($rows as &$r) {
            $r['total_empresas'] = $this->contarEmpresasAsignadas((int) $r['id_usuario']);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Usuarios asignados a una empresa
     */
    public function getUsuariosDeEmpresa(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT ea.id AS id_registro, ea.id_usuario, ea.usu_asignador, u.nombre, u.cedula, u.mail, u.nivel
            FROM empresa_asignada ea
            INNER JOIN usuarios u ON u.id = ea.id_usuario
            WHERE ea.id_empresa = {$id}
            ORDER BY u.nombre";
        return $this->query($sql);
    }

    /**
     * Empresas asignadas a un usuario.
     * Si se pasan idActual y nivel, añade puede_quitar (admin puede quitar si asignó o tiene acceso a la empresa).
     */
    public function getEmpresasDeUsuario(int $idUsuario, ?int $idActual = null, ?int $nivel = null): array
    {
        $id = (int) $idUsuario;
        $sql = "SELECT ea.id AS id_registro, ea.id_empresa, ea.usu_asignador, e.nombre_comercial, e.ruc
            FROM empresa_asignada ea
            INNER JOIN empresas e ON e.id = ea.id_empresa
            WHERE ea.id_usuario = {$id} AND e.estado = '1'
            ORDER BY e.nombre_comercial";
        $rows = $this->query($sql);

        if ($idActual !== null && $nivel !== null && $nivel >= 2) {
            $idA = (int) $idActual;
            foreach ($rows as &$r) {
                $puede = (int) ($r['usu_asignador'] ?? 0) === $idA;
                if (!$puede) {
                    $idEmp = (int) ($r['id_empresa'] ?? 0);
                    $tiene = $this->query("SELECT 1 FROM empresa_asignada WHERE id_usuario = {$idA} AND id_empresa = {$idEmp}");
                    $puede = !empty($tiene);
                }
                $r['puede_quitar'] = $puede;
            }
        } elseif ($idActual !== null) {
            $idA = (int) $idActual;
            foreach ($rows as &$r) {
                $r['puede_quitar'] = (int) ($r['usu_asignador'] ?? 0) === $idA;
            }
        }

        return $rows;
    }

    /**
     * Usuarios disponibles para asignar a una empresa (no la tienen asignada).
     * Super admin: todos los usuarios activos. Admin: solo los de usuario_asignado.
     */
    public function getUsuariosDisponiblesParaEmpresa(int $idEmpresa, int $idActual, int $nivel, string $buscar = ''): array
    {
        $idEmp = (int) $idEmpresa;
        $idA = (int) $idActual;
        $notExists = "NOT EXISTS (SELECT 1 FROM empresa_asignada ea2 WHERE ea2.id_usuario = u.id AND ea2.id_empresa = {$idEmp})";

        if ($nivel >= 3) {
            $from = "usuarios u";
            $where = "WHERE u.estado = 1 AND u.id != {$idA} AND {$notExists}";
        } else {
            $from = "usuario_asignado ua INNER JOIN usuarios u ON u.id = ua.id_usuario";
            $where = "WHERE u.estado = 1 AND ua.id_adm = {$idA} AND {$notExists}";
        }

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (u.nombre LIKE '%{$b}%' OR u.cedula LIKE '%{$b}%' OR u.mail LIKE '%{$b}%')";
        }

        $sql = "SELECT DISTINCT u.id AS id_usuario, u.nombre, u.cedula, u.mail FROM {$from} {$where} ORDER BY u.nombre LIMIT 50";
        return $this->query($sql);
    }

    /**
     * Empresas que el actual puede asignar (para el select/modal).
     * Super admin: todas. Admin: solo las que tiene asignadas.
     */
    public function getEmpresasDisponiblesParaAsignar(int $idActual, int $nivel, int $idUsuarioDestino, string $buscar = ''): array
    {
        $idDest = (int) $idUsuarioDestino;
        $where = "e.estado = '1' AND NOT EXISTS (SELECT 1 FROM empresa_asignada ea2 WHERE ea2.id_empresa = e.id AND ea2.id_usuario = {$idDest})";

        if ($nivel >= 3) {
            $from = "empresas e";
        } else {
            $from = "empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa";
            $where .= " AND ea.id_usuario = " . (int) $idActual;
        }

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (e.nombre_comercial LIKE '%{$b}%' OR e.ruc LIKE '%{$b}%')";
        }

        $sql = "SELECT e.id AS id_empresa, e.nombre_comercial, e.ruc FROM {$from} WHERE {$where} ORDER BY e.nombre_comercial LIMIT 50";
        return $this->query($sql);
    }

    /**
     * Usuarios para select (buscable).
     * Super admin: todos. Admin: usuarios asignados a las empresas que el admin tiene asignadas.
     */
    public function getUsuariosParaSelect(int $idActual, int $nivel, string $buscar = '', int $limit = 50): array
    {
        $idA = (int) $idActual;
        if ($nivel >= 3) {
            $where = "WHERE u.estado = 1";
            $from = "usuarios u";
        } else {
            $where = "WHERE u.estado = 1 AND u.nivel < 3 AND u.id != {$idA}";
            $from = "empresa_asignada ea_admin
                INNER JOIN empresa_asignada ea_otro ON ea_otro.id_empresa = ea_admin.id_empresa AND ea_otro.id_usuario != {$idA}
                INNER JOIN usuarios u ON u.id = ea_otro.id_usuario";
            $where .= " AND ea_admin.id_usuario = {$idA}";
        }
        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (u.nombre LIKE '%{$b}%' OR u.cedula LIKE '%{$b}%')";
        }
        $sql = "SELECT DISTINCT u.id, u.nombre, u.cedula FROM {$from} {$where} ORDER BY u.nombre LIMIT " . (int) $limit;
        return $this->query($sql);
    }

    /**
     * Empresas para select. Super admin: solo empresas del usuario seleccionado. Admin: empresas del usuario o propias.
     */
    public function getEmpresasParaSelect(int $idUsuario, int $idActual, int $nivel, string $buscar = '', int $limit = 50): array
    {
        $idU = (int) $idUsuario;
        $idA = (int) $idActual;
        if ($idU > 0) {
            return $this->getEmpresasParaPermisos($idU, $idA, $nivel);
        }
        if ($nivel >= 3) {
            return [];
        }
        return $this->query("SELECT e.id AS id_empresa, e.nombre_comercial, e.ruc FROM empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa WHERE ea.id_usuario = {$idA} AND e.estado = '1' ORDER BY e.nombre_comercial LIMIT " . (int) $limit);
    }

    /**
     * Empresas del usuario para el selector de permisos.
     * Super admin: empresas asignadas al usuario. Admin: solo empresas que el admin también tiene.
     */
    public function getEmpresasParaPermisos(int $idUsuarioTarget, int $idAdmin, int $nivel): array
    {
        $idT = (int) $idUsuarioTarget;
        $idA = (int) $idAdmin;
        if ($nivel >= 3) {
            return $this->query("SELECT e.id AS id_empresa, e.nombre_comercial, e.ruc
                FROM empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa
                WHERE ea.id_usuario = {$idT} AND e.estado = '1'
                ORDER BY e.nombre_comercial");
        }
        return $this->query("SELECT e.id AS id_empresa, e.nombre_comercial, e.ruc
            FROM empresa_asignada ea
            INNER JOIN empresas e ON e.id = ea.id_empresa AND e.estado = '1'
            INNER JOIN empresa_asignada ea2 ON ea2.id_empresa = ea.id_empresa AND ea2.id_usuario = {$idA}
            WHERE ea.id_usuario = {$idT}
            ORDER BY e.nombre_comercial");
    }

    /**
     * Obtener usuario por ID (para permisos, etc.)
     */
    public function getUsuarioPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT id AS id_usuario, nombre, cedula, nivel FROM usuarios WHERE id = {$id} AND estado = 1");
        return $r[0] ?? null;
    }

    public function contarEmpresasAsignadas(int $idUsuario): int
    {
        $id = (int) $idUsuario;
        $r = $this->query("SELECT COUNT(*) AS n FROM empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa WHERE ea.id_usuario = {$id} AND e.estado = '1'");
        return (int) ($r[0]['n'] ?? 0);
    }

    /**
     * Asignar empresa a usuario
     */
    public function asignar(int $idEmpresa, int $idUsuarioDestino, int $idAsignador): bool
    {
        $idEmp = (int) $idEmpresa;
        $idDest = (int) $idUsuarioDestino;
        $idAsig = (int) $idAsignador;

        $existe = $this->query("SELECT 1 FROM empresa_asignada WHERE id_empresa = {$idEmp} AND id_usuario = {$idDest}");
        if (!empty($existe)) return false;

        $sql = "INSERT INTO empresa_asignada (id_empresa, id_usuario, usu_asignador) VALUES ({$idEmp}, {$idDest}, {$idAsig})";
        return $this->execute($sql);
    }

    /**
     * Quitar asignación.
     * Super admin: puede quitar cualquiera.
     * Admin: puede quitar si asignó la empresa (usu_asignador) o si tiene acceso a esa empresa.
     */
    public function quitar(int $idRegistro, int $idActual, int $nivel = 1): bool
    {
        $id = (int) $idRegistro;
        $r = $this->query("SELECT id_usuario, id_empresa, usu_asignador FROM empresa_asignada WHERE id = {$id}");
        if (empty($r)) {
            return false;
        }

        $usuAsignador = (int) $r[0]['usu_asignador'];
        $idUsu = (int) $r[0]['id_usuario'];
        $idEmp = (int) $r[0]['id_empresa'];

        $puedeQuitar = ($usuAsignador === $idActual);
        if (!$puedeQuitar && $nivel >= 2) {
            $tieneAcceso = $this->query("SELECT 1 FROM empresa_asignada WHERE id_usuario = {$idActual} AND id_empresa = {$idEmp}");
            $puedeQuitar = !empty($tieneAcceso);
        }

        if (!$puedeQuitar) {
            return false;
        }

        $this->execute("DELETE FROM modulos_asignados WHERE id_usuario = {$idUsu} AND id_empresa = {$idEmp}");
        return $this->execute("DELETE FROM empresa_asignada WHERE id = {$id}");
    }
}
