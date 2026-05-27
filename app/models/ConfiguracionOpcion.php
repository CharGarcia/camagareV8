<?php
/**
 * Modelo ConfiguracionOpcion - Opciones de configuración por nivel de usuario
 * nivel_minimo: 1=Usuario, 2=Admin, 3=SuperAdmin
 */

declare(strict_types=1);

namespace App\models;

class ConfiguracionOpcion extends BaseModel
{
    private const RUTAS_BASE = ['/perfil' => 'Mi perfil', '/auth/cambiar-clave' => 'Cambiar contraseña', '/config/appearance' => 'Apariencia'];

    /**
     * Asegura que las opciones base (Cambiar contraseña, Apariencia) existan en BD.
     * No inserta si el usuario ya eliminó una tarjeta que enlazaba a esa ruta.
     */
    public function asegurarOpcionesBase(): void
    {
        // Limpiar tarjeta "Plantillas de Documentos" si fue insertada automáticamente antes.
        // Pertenece al módulo operativo por empresa (/modulos/plantillas-pdf) y no a config global.
        $this->limpiarPlantillasDocumentosBase();

        $base = [
            ['nombre' => 'Mi perfil', 'descripcion' => 'Editar tu nombre, cédula y correo', 'icono' => 'person-circle', 'clase_color' => 'info', 'nivel_minimo' => 1, 'enlace' => ['etiqueta' => 'Mi perfil', 'ruta' => '/perfil', 'clase_btn' => 'info']],
            ['nombre' => 'Cambiar contraseña', 'descripcion' => 'Actualizar tu contraseña de acceso', 'icono' => 'key', 'clase_color' => 'warning', 'nivel_minimo' => 1, 'enlace' => ['etiqueta' => 'Cambiar contraseña', 'ruta' => '/auth/cambiar-clave', 'clase_btn' => 'warning']],
            ['nombre' => 'Apariencia', 'descripcion' => 'Colores y tema visual del sistema', 'icono' => 'palette', 'clase_color' => 'primary', 'nivel_minimo' => 1, 'enlace' => ['etiqueta' => 'Colores y tema', 'ruta' => '/config/appearance', 'clase_btn' => 'primary']],
            ['nombre' => 'Unidades de medida', 'descripcion' => 'Tipos y unidades de medida (kg, litro, etc.)', 'icono' => 'rulers', 'clase_color' => 'secondary', 'nivel_minimo' => 2, 'enlace' => ['etiqueta' => 'Unidades de medida', 'ruta' => '/config/unidades-medida', 'clase_btn' => 'secondary']],
            ['nombre' => 'Plan de cuentas modelo', 'descripcion' => 'Plantilla del plan de cuentas contable', 'icono' => 'journal-bookmark', 'clase_color' => 'info', 'nivel_minimo' => 2, 'enlace' => ['etiqueta' => 'Plan de cuentas modelo', 'ruta' => '/config/plan-cuentas-modelo', 'clase_btn' => 'info']],
            ['nombre' => 'Asientos tipo', 'descripcion' => 'Modelos de asientos contables predefinidos del sistema', 'icono' => 'sliders', 'clase_color' => 'dark', 'nivel_minimo' => 2, 'enlace' => ['etiqueta' => 'Asientos tipo', 'ruta' => '/config/asientos-tipo', 'clase_btn' => 'dark']],
        ];
        foreach ($base as $op) {
            $ruta = $op['enlace']['ruta'] ?? '';
            if ($ruta === '') continue;
            $rutaEsc = $this->escape($ruta);
            if ($this->estaRutaOmitida($rutaEsc)) continue;
            $existePorNombre = $this->query("SELECT 1 FROM configuracion_opciones WHERE nombre = '" . $this->escape($op['nombre']) . "' LIMIT 1");
            $existePorRuta = $this->query("SELECT 1 FROM configuracion_opcion_enlaces e INNER JOIN configuracion_opciones o ON o.id = e.id_opcion WHERE e.ruta = '{$rutaEsc}' AND o.activo IS TRUE LIMIT 1");
            if (empty($existePorNombre) && empty($existePorRuta)) {
                $id = $this->crearOpcion($op);
                $this->crearEnlace($id, $op['enlace']);
            }
        }
    }

    private function estaRutaOmitida(string $rutaEsc): bool
    {
        try {
            $this->execute("CREATE TABLE IF NOT EXISTS config_opciones_base_omitidas (ruta VARCHAR(255) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $r = $this->query("SELECT 1 FROM config_opciones_base_omitidas WHERE ruta = '{$rutaEsc}' LIMIT 1");
            return !empty($r);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function asegurarTablaOmitidas(): void
    {
        try {
            $this->execute("CREATE TABLE IF NOT EXISTS config_opciones_base_omitidas (ruta VARCHAR(255) PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            // Tabla puede no existir o sin permisos CREATE
        }
    }

    private function limpiarPlantillasDocumentosBase(): void
    {
        try {
            $rows = $this->query(
                "SELECT o.id FROM configuracion_opciones o
                 INNER JOIN configuracion_opcion_enlaces e ON e.id_opcion = o.id
                 WHERE o.nombre = 'Plantillas de Documentos'
                   AND e.ruta = '/modulos/plantillas-pdf'
                 LIMIT 1"
            );
            if (!empty($rows)) {
                $id = (int) $rows[0]['id'];
                $this->execute("DELETE FROM configuracion_opcion_enlaces WHERE id_opcion = {$id}");
                $this->execute("DELETE FROM configuracion_opciones WHERE id = {$id}");
            }
        } catch (\Throwable $e) {
            // Fallo silencioso: si la tarjeta no existe o hay error de BD no bloqueamos la carga
        }
    }

    private function marcarOpcionBaseOmitida(string $ruta): void
    {
        try {
            $this->asegurarTablaOmitidas();
            $rutaEsc = $this->escape($ruta);
            if (isset(self::RUTAS_BASE[$ruta])) {
                $this->execute("INSERT IGNORE INTO config_opciones_base_omitidas (ruta) VALUES ('{$rutaEsc}')");
            }
        } catch (\Throwable $e) {
            // Fallo silencioso
        }
    }

    public function getOpcionesPorNivel(int $nivelUsuario): array
    {
        $nivel = (int) $nivelUsuario;
        $activoFilter = ($nivel >= 3) ? '' : ' AND activo IS TRUE';
        $sql = "SELECT id, nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo
                FROM configuracion_opciones
                WHERE nivel_minimo <= {$nivel}{$activoFilter}
                ORDER BY orden ASC, nombre ASC";
        return $this->query($sql);
    }

    public function getEnlacesPorOpcion(int $idOpcion): array
    {
        $id = (int) $idOpcion;
        $sql = "SELECT id, etiqueta, ruta, clase_btn, orden
                FROM configuracion_opcion_enlaces
                WHERE id_opcion = {$id}
                ORDER BY orden ASC, etiqueta ASC";
        return $this->query($sql);
    }

    public function getOpcionesConEnlaces(int $nivelUsuario): array
    {
        $opciones = $this->getOpcionesPorNivel($nivelUsuario);
        foreach ($opciones as &$op) {
            $op['enlaces'] = $this->getEnlacesPorOpcion((int) $op['id']);
        }
        return $opciones;
    }

    public function crearOpcion(array $data): int
    {
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $descripcion = $this->escape(trim($data['descripcion'] ?? ''));
        $icono = $this->escape(trim($data['icono'] ?? 'gear'));
        $claseColor = $this->escape(trim($data['clase_color'] ?? 'primary'));
        $nivelMinimo = (int) ($data['nivel_minimo'] ?? 1);
        $orden = (int) ($data['orden'] ?? 0);
        $activo = isset($data['activo']) ? (bool) $data['activo'] : true;
        $activoSql = $activo ? 'TRUE' : 'FALSE';

        $sql = "INSERT INTO configuracion_opciones (nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo)
                VALUES ('{$nombre}', '{$descripcion}', '{$icono}', '{$claseColor}', {$nivelMinimo}, {$orden}, {$activoSql})";
        $this->execute($sql);
        return $this->lastInsertId('configuracion_opciones_id_seq');
    }

    public function crearEnlace(int $idOpcion, array $data): int
    {
        $id = (int) $idOpcion;
        $etiqueta = $this->escape(trim($data['etiqueta'] ?? ''));
        $ruta = $this->escape(trim($data['ruta'] ?? ''));
        $claseBtn = $this->escape(trim($data['clase_btn'] ?? 'outline-primary'));
        $orden = (int) ($data['orden'] ?? 0);

        $sql = "INSERT INTO configuracion_opcion_enlaces (id_opcion, etiqueta, ruta, clase_btn, orden)
                VALUES ({$id}, '{$etiqueta}', '{$ruta}', '{$claseBtn}', {$orden})";
        $this->execute($sql);
        return $this->lastInsertId('configuracion_opcion_enlaces_id_seq');
    }

    public function getOpcionPorId(int $id): ?array
    {
        $id = (int) $id;
        $sql = "SELECT id, nombre, descripcion, icono, clase_color, nivel_minimo, orden, activo
                FROM configuracion_opciones WHERE id = {$id}";
        $rows = $this->query($sql);
        if (empty($rows)) {
            return null;
        }
        $op = $rows[0];
        $op['enlaces'] = $this->getEnlacesPorOpcion($id);
        return $op;
    }

    public function actualizarOpcion(int $id, array $data): bool
    {
        $id = (int) $id;
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $descripcion = $this->escape(trim($data['descripcion'] ?? ''));
        $icono = $this->escape(trim($data['icono'] ?? 'gear'));
        $claseColor = $this->escape(trim($data['clase_color'] ?? 'primary'));
        $nivelMinimo = (int) ($data['nivel_minimo'] ?? 1);
        $orden = (int) ($data['orden'] ?? 0);
        $activo = isset($data['activo']) ? (bool) $data['activo'] : true;
        $activoSql = $activo ? 'TRUE' : 'FALSE';

        $sql = "UPDATE configuracion_opciones SET
                nombre = '{$nombre}', descripcion = '{$descripcion}', icono = '{$icono}',
                clase_color = '{$claseColor}', nivel_minimo = {$nivelMinimo}, orden = {$orden}, activo = {$activoSql}
                WHERE id = {$id}";
        return $this->execute($sql);
    }

    public function eliminarEnlacesPorOpcion(int $idOpcion): bool
    {
        $id = (int) $idOpcion;
        $sql = "DELETE FROM configuracion_opcion_enlaces WHERE id_opcion = {$id}";
        return $this->execute($sql);
    }

    public function eliminarOpcion(int $id): bool
    {
        $id = (int) $id;
        $this->eliminarEnlacesPorOpcion($id);
        return $this->execute("DELETE FROM configuracion_opciones WHERE id = {$id}");
    }

    /**
     * Actualiza el orden de las opciones según el array de IDs (posición = orden).
     */
    public function reordenarOpciones(array $ordenIds): bool
    {
        foreach ($ordenIds as $orden => $id) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $orden = (int) $orden;
            $this->execute("UPDATE configuracion_opciones SET orden = {$orden} WHERE id = {$id}");
        }
        return true;
    }
}
