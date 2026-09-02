<?php
declare(strict_types=1);

namespace App\repositories\modulos;

use App\repositories\BaseRepository;
use PDO;

/**
 * Configuración del restaurante: catálogo de estaciones de preparación
 * (cocina, barra, parrilla…) con su impresora, y cuál de ellas es la
 * predeterminada.
 *
 * El CRUD de estaciones vivía en MenuRepository, servido desde una pestaña del
 * modal de Menú; se movió aquí para que la configuración del salón esté en un
 * solo sitio. MenuRepository conserva únicamente la LECTURA `getEstaciones()`,
 * que siguen usando el selector "Preparar en" de la carta, el KDS y el tablero
 * de mesas.
 */
class ConfiguracionRestauranteRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct('estaciones_impresion');
    }

    /** ¿Está aplicada la migración de la estación predeterminada? */
    public function soportaPredeterminada(): bool
    {
        return $this->columnaExiste('estaciones_impresion', 'es_predeterminada');
    }

    /** Columnas por las que se puede ordenar el listado (lista blanca: van al SQL). */
    private const ORDEN_PERMITIDO = ['nombre', 'tipo', 'orden', 'activo', 'imprime_ordenes', 'ancho_papel', 'copias', 'usos'];

    /**
     * Listado paginado del módulo. Incluye cuántos ítems/categorías usan cada
     * estación, que es lo que decide si se puede eliminar.
     *
     * @param int $perPage 0 = sin paginar (exportaciones).
     */
    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir): array
    {
        $colPred = $this->soportaPredeterminada() ? 'e.es_predeterminada' : 'false AS es_predeterminada';

        $where  = "WHERE e.id_empresa = :id_empresa AND e.eliminado = false";
        $params = [':id_empresa' => $idEmpresa];

        // Buscador estándar: texto libre + filtros clave:valor, siempre con
        // consultas preparadas (nunca concatenando lo que escribe el usuario).
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $where .= ' AND ' . \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['e.nombre', 'e.tipo'],
                $parsed['texto_libre'],
                $params,
                'tl'
            );
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto'    => ['nombre' => 'e.nombre'],
            'exacto'   => ['tipo' => 'e.tipo', 'estado' => 'e.activo', 'imprime' => 'e.imprime_ordenes'],
            'numerico' => ['papel' => 'e.ancho_papel', 'copias' => 'e.copias'],
        ]);

        $usos = "(SELECT COUNT(*) FROM menu_items m
                   WHERE m.id_estacion_impresion = e.id AND m.id_empresa = e.id_empresa AND m.eliminado = false)
               + (SELECT COUNT(*) FROM categorias c
                   WHERE c.id_estacion_impresion = e.id AND c.id_empresa = e.id_empresa AND c.eliminado = false)";

        $stTotal = $this->db->prepare("SELECT COUNT(*) FROM estaciones_impresion e {$where}");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $col = in_array($ordenCol, self::ORDEN_PERMITIDO, true) ? $ordenCol : 'nombre';
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $orderBy = $col === 'usos' ? "({$usos}) {$dir}" : "e.{$col} {$dir}";

        $limit = '';
        if ($perPage > 0) {
            $offset = max(0, ($page - 1) * $perPage);
            $limit = " LIMIT {$perPage} OFFSET {$offset}";
        }

        $sql = "SELECT e.id, e.nombre, e.tipo, e.orden, e.activo,
                       e.imprime_ordenes, e.imprimir_auto, e.ancho_papel, e.copias,
                       {$colPred},
                       ({$usos}) AS usos,
                       e.created_at, e.updated_at
                FROM estaciones_impresion e
                {$where}
                ORDER BY {$orderBy}, e.nombre ASC{$limit}";
        $st = $this->db->prepare($sql);
        $st->execute($params);

        return ['rows' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }

    /** Todas las estaciones de la empresa, sin paginar (selectores y validaciones). */
    public function getEstaciones(int $idEmpresa): array
    {
        return $this->getListado($idEmpresa, '', 1, 0, 'orden', 'ASC')['rows'];
    }

    public function find(int $id, int $idEmpresa): ?array
    {
        $sql = "SELECT * FROM estaciones_impresion WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':id' => $id, ':e' => $idEmpresa]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function existeNombre(int $idEmpresa, string $nombre, ?int $excluirId = null): bool
    {
        $sql = "SELECT 1 FROM estaciones_impresion
                WHERE id_empresa = :e AND UPPER(nombre) = UPPER(:n) AND eliminado = false";
        $params = [':e' => $idEmpresa, ':n' => $nombre];
        if ($excluirId !== null) {
            $sql .= " AND id != :id";
            $params[':id'] = $excluirId;
        }
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return (bool) $st->fetchColumn();
    }

    public function crear(array $d): int
    {
        $sql = "INSERT INTO estaciones_impresion
                    (id_empresa, nombre, tipo, orden, activo,
                     imprime_ordenes, imprimir_auto, ancho_papel, copias, created_by, updated_by)
                VALUES (:e, :nombre, :tipo, :orden, :activo, :imp, :auto, :ancho, :copias, :u, :u)
                RETURNING id";
        $st = $this->db->prepare($sql);
        $st->execute([
            ':e'      => $d['id_empresa'],
            ':nombre' => $d['nombre'],
            ':tipo'   => $d['tipo'],
            ':orden'  => $d['orden'],
            // Booleanos como literal: PHP false se bindea como cadena vacía y
            // Postgres rechaza el tipo boolean.
            ':activo' => !empty($d['activo']) ? 'true' : 'false',
            ':imp'    => !empty($d['imprime_ordenes']) ? 'true' : 'false',
            ':auto'   => !empty($d['imprimir_auto']) ? 'true' : 'false',
            ':ancho'  => $d['ancho_papel'],
            ':copias' => $d['copias'],
            ':u'      => $d['id_usuario'],
        ]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, int $idEmpresa, array $d): void
    {
        $sql = "UPDATE estaciones_impresion
                SET nombre = :nombre, tipo = :tipo, orden = :orden, activo = :activo,
                    imprime_ordenes = :imp, imprimir_auto = :auto, ancho_papel = :ancho, copias = :copias,
                    updated_by = :u, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([
            ':nombre' => $d['nombre'],
            ':tipo'   => $d['tipo'],
            ':orden'  => $d['orden'],
            ':activo' => !empty($d['activo']) ? 'true' : 'false',
            ':imp'    => !empty($d['imprime_ordenes']) ? 'true' : 'false',
            ':auto'   => !empty($d['imprimir_auto']) ? 'true' : 'false',
            ':ancho'  => $d['ancho_papel'],
            ':copias' => $d['copias'],
            ':u'      => $d['id_usuario'],
            ':id'     => $id,
            ':e'      => $idEmpresa,
        ]);
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $sql = "UPDATE estaciones_impresion
                SET eliminado = true, deleted_at = CURRENT_TIMESTAMP, deleted_by = :u
                WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($sql)->execute([':u' => $idUsuario, ':id' => $id, ':e' => $idEmpresa]);
    }

    /** Cuántos ítems del menú o categorías enrutan a esta estación. */
    public function contarUsos(int $idEstacion, int $idEmpresa): int
    {
        $sql = "SELECT (SELECT COUNT(*) FROM menu_items WHERE id_estacion_impresion = :est AND id_empresa = :e AND eliminado = false)
                     + (SELECT COUNT(*) FROM categorias  WHERE id_estacion_impresion = :est2 AND id_empresa = :e2 AND eliminado = false)";
        $st = $this->db->prepare($sql);
        // Placeholders distintos: PDO con emulación desactivada no permite
        // repetir el mismo nombre en la consulta.
        $st->execute([':est' => $idEstacion, ':e' => $idEmpresa, ':est2' => $idEstacion, ':e2' => $idEmpresa]);
        return (int) $st->fetchColumn();
    }

    // ─── Estación predeterminada ──────────────────────────────────────────────

    /**
     * Marca una estación como predeterminada y desmarca el resto. Se llama
     * dentro de la transacción del Service: si el UPDATE de marcado fallara
     * después del de limpieza, la empresa se quedaría sin ninguna.
     *
     * @param int $idEstacion 0 = ninguna (la empresa se queda sin predeterminada).
     */
    public function fijarPredeterminada(int $idEstacion, int $idEmpresa, int $idUsuario): void
    {
        if (!$this->soportaPredeterminada()) {
            return;
        }

        $limpiar = "UPDATE estaciones_impresion
                    SET es_predeterminada = false, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                    WHERE id_empresa = :e AND es_predeterminada = true AND eliminado = false";
        $this->db->prepare($limpiar)->execute([':u' => $idUsuario, ':e' => $idEmpresa]);

        if ($idEstacion <= 0) {
            return;
        }

        $marcar = "UPDATE estaciones_impresion
                   SET es_predeterminada = true, updated_by = :u, updated_at = CURRENT_TIMESTAMP
                   WHERE id = :id AND id_empresa = :e AND eliminado = false";
        $this->db->prepare($marcar)->execute([':u' => $idUsuario, ':id' => $idEstacion, ':e' => $idEmpresa]);
    }

    /**
     * ¿El local trabaja con preparación?
     *
     * No basta con que existan estaciones: lo que decide es que haya **algo que
     * mandar a preparar**, o sea al menos un ítem de la carta o una categoría de
     * productos enrutados a una estación activa. Si no hay ninguno, todo se
     * entrega directo y la comanda esconde el flujo de cocina.
     *
     * La estación predeterminada NO cuenta aquí a propósito: dice a qué
     * impresora sale la orden, no que el pedido tenga que pasar por cocina.
     */
    public function tienePreparacionConfigurada(int $idEmpresa): bool
    {
        $sql = "SELECT 1
                FROM estaciones_impresion e
                WHERE e.id_empresa = :e1 AND e.eliminado = false AND e.activo = true
                  AND (
                        EXISTS (SELECT 1 FROM menu_items m
                                 WHERE m.id_estacion_impresion = e.id AND m.id_empresa = :e2 AND m.eliminado = false)
                     OR EXISTS (SELECT 1 FROM categorias c
                                 WHERE c.id_estacion_impresion = e.id AND c.id_empresa = :e3 AND c.eliminado = false)
                  )
                LIMIT 1";
        $st = $this->db->prepare($sql);
        // Placeholders distintos: PDO sin emulación no permite repetir el nombre.
        $st->execute([':e1' => $idEmpresa, ':e2' => $idEmpresa, ':e3' => $idEmpresa]);
        return (bool) $st->fetchColumn();
    }

    // ─── Configuración general del salón (restaurante_config) ────────────────

    /** Ancho de papel por defecto de la tirilla, en mm. */
    public const ANCHO_TIRILLA_DEFAULT = 80;

    /**
     * Ancho del papel de la tirilla de esta empresa. Devuelve el valor por
     * defecto mientras la migración no esté aplicada o la empresa no lo haya
     * configurado, para que la tirilla siga saliendo como hasta ahora.
     */
    public function getAnchoTirilla(int $idEmpresa): int
    {
        if (!$this->tablaExiste('restaurante_config')) {
            return self::ANCHO_TIRILLA_DEFAULT;
        }

        $sql = "SELECT ancho_papel_tirilla FROM restaurante_config
                WHERE id_empresa = :e AND eliminado = false LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $valor = (int) $st->fetchColumn();

        return in_array($valor, [58, 80], true) ? $valor : self::ANCHO_TIRILLA_DEFAULT;
    }

    /**
     * Guarda el ancho de la tirilla. Una fila por empresa: se inserta la primera
     * vez y se actualiza después (UPSERT sobre el índice único).
     */
    public function guardarAnchoTirilla(int $idEmpresa, int $ancho, int $idUsuario): void
    {
        if (!$this->tablaExiste('restaurante_config')) {
            throw new \RuntimeException('La configuración del restaurante todavía no está habilitada en este servidor.');
        }

        $sql = "INSERT INTO restaurante_config (id_empresa, ancho_papel_tirilla, created_by, updated_by)
                VALUES (:e, :a, :u, :u2)
                ON CONFLICT (id_empresa) WHERE eliminado = false
                DO UPDATE SET ancho_papel_tirilla = EXCLUDED.ancho_papel_tirilla,
                              updated_by = EXCLUDED.updated_by,
                              updated_at = CURRENT_TIMESTAMP";
        $this->db->prepare($sql)->execute([
            ':e'  => $idEmpresa,
            ':a'  => $ancho,
            ':u'  => $idUsuario,
            ':u2' => $idUsuario,
        ]);
    }

    /** Cabecera de la comanda para el encabezado del ticket. */
    public function getCabeceraComanda(int $idComanda, int $idEmpresa): ?array
    {
        $sql = "SELECT c.numero_comanda, c.observaciones, m.nombre AS mesa_nombre, u.nombre AS mesero_nombre
                FROM comandas c
                JOIN mesas m ON m.id = c.id_mesa
                LEFT JOIN usuarios u ON u.id = c.id_usuario_mesero
                WHERE c.id = :ic AND c.id_empresa = :e AND c.eliminado = false";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Líneas de la comanda tal como salen en el ticket: sin precios, que es una
     * instrucción de preparación y no una cuenta.
     */
    public function getLineasParaImprimir(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT descripcion, cantidad, observacion_item
                FROM comanda_detalle
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea != 'anulado'
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return $st->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Todas las líneas vivas de una comanda, sin importar su estación ni su
     * estado. Es lo que se imprime en un local sin preparación: no hay envío a
     * cocina que filtrar, así que la orden es la comanda entera.
     *
     * @return int[] ids de comanda_detalle
     */
    public function getLineasVivasDeComanda(int $idComanda, int $idEmpresa): array
    {
        $sql = "SELECT id FROM comanda_detalle
                WHERE id_comanda = :ic AND id_empresa = :e AND eliminado = false
                  AND estado_linea != 'anulado'
                ORDER BY id ASC";
        $st = $this->db->prepare($sql);
        $st->execute([':ic' => $idComanda, ':e' => $idEmpresa]);
        return array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Estación que recoge lo que no resolvió por Menú ni por categoría.
     * null si la empresa no marcó ninguna (o si el SQL no está aplicado).
     */
    public function getPredeterminada(int $idEmpresa): ?int
    {
        if (!$this->soportaPredeterminada()) {
            return null;
        }

        $sql = "SELECT id FROM estaciones_impresion
                WHERE id_empresa = :e AND es_predeterminada = true AND eliminado = false AND activo = true
                LIMIT 1";
        $st = $this->db->prepare($sql);
        $st->execute([':e' => $idEmpresa]);
        $val = $st->fetchColumn();
        return $val !== false ? (int) $val : null;
    }
}
