<?php

declare(strict_types=1);

namespace App\Traits;

use PDO;

/**
 * Pestaña "Transacciones" de las fichas de proveedor y cliente: consulta paginada de
 * las líneas (productos o servicios) de sus documentos, con el buscador estándar
 * (FiltrosBusqueda) y dos vistas:
 *  - detalle:  una fila por línea de documento.
 *  - producto: una fila por producto/servicio (mismo código y descripción) con
 *              cantidad y total netos, veces en documentos y último precio.
 *
 * Cada repositorio arma las ramas SQL de SUS documentos (compras y liquidaciones del
 * proveedor; facturas, recibos y notas de crédito del cliente) con sus propios filtros
 * de empresa, estado, ambiente y registros propios; este trait hace el resto. Cada rama
 * debe exponer estas columnas, con estos alias:
 *
 *   origen, id_documento, id_linea, fecha, numero_documento, tipo_documento,
 *   codigo, descripcion, cantidad, precio_unitario, descuento, subtotal,
 *   iva, tarifa_iva, signo
 *
 * iva es el IVA de la línea (suma de sus impuestos con codigo_impuesto = '2') y
 * tarifa_iva su porcentaje, para mostrarlo junto al subtotal (que va sin impuestos).
 *
 * signo = -1 en las notas de crédito: se listan (son devoluciones) y restan en las
 * cantidades y los totales. Todas las ramas llevan alias porque la primera del UNION es
 * la que da nombre a las columnas, y cuál es depende de los permisos del usuario.
 *
 * Requiere `protected PDO $db` (BaseRepository lo provee).
 */
trait LineasDocumentoTrait
{
    /**
     * Ambiente activo de la empresa ('1' pruebas | '2' producción), o null si no se
     * conoce; en ese caso las ramas no filtran por ambiente (mismo criterio que el
     * resumen comercial de la ficha de proveedor).
     */
    protected function ambienteLineasDocumento(int $idEmpresa): ?string
    {
        $st = $this->db->prepare("SELECT CAST(tipo_ambiente AS VARCHAR) FROM empresas WHERE id = :id_empresa LIMIT 1");
        $st->execute([':id_empresa' => $idEmpresa]);
        $amb = $st->fetchColumn();
        $amb = ($amb !== false && $amb !== null) ? trim((string) $amb) : '';
        return $amb !== '' ? $amb : null;
    }

    /**
     * @param string[] $ramas  Un SELECT por tipo de documento (ver columnas en el docblock del trait).
     * @param array    $params Parámetros de las ramas; cada rama con nombres propios.
     * @return array{rows: array, total: int, total_neto: float}
     */
    protected function consultarLineasDocumento(
        array $ramas,
        array $params,
        string $buscar,
        string $vista,
        int $page,
        int $perPage,
        string $ordenCol,
        string $ordenDir
    ): array {
        if (empty($ramas)) {
            return ['rows' => [], 'total' => 0, 'total_neto' => 0.0, 'total_iva' => 0.0];
        }
        $vista = $vista === 'producto' ? 'producto' : 'detalle';

        // Buscador: texto libre por palabras (sin tildes) + filtros clave:valor
        $where  = '';
        $parsed = \App\Helpers\FiltrosBusqueda::parsear($buscar);
        if ($parsed['texto_libre'] !== '') {
            $cond = \App\Helpers\FiltrosBusqueda::condicionTexto(
                ['t.codigo', 't.descripcion', 't.numero_documento', 't.tipo_documento'],
                $parsed['texto_libre'],
                $params,
                'trx_b'
            );
            if ($cond !== '') {
                $where .= ' AND ' . $cond;
            }
        }
        \App\Helpers\FiltrosBusqueda::aplicarFiltros($where, $params, $parsed['filtros'], [
            'texto' => [
                'producto'    => 't.descripcion',
                'servicio'    => 't.descripcion',
                'descripcion' => 't.descripcion',
                'codigo'      => 't.codigo',
                'documento'   => 't.numero_documento',
                'tipo'        => 't.tipo_documento',
            ],
            'fecha'    => ['fecha' => 't.fecha'],
            'numerico' => [
                'cantidad' => 't.cantidad',
                'precio'   => 't.precio_unitario',
                'subtotal' => 't.subtotal',
                'total'    => 't.subtotal',
            ],
        ]);

        $base   = "FROM ( " . implode("\n UNION ALL \n", $ramas) . " ) t WHERE true {$where}";
        $orden  = $this->ordenLineasDocumento($vista);
        $dir    = strtoupper($ordenDir) === 'ASC' ? 'ASC' : 'DESC';
        $limite = " LIMIT " . (int) $perPage . " OFFSET " . (int) max(0, ($page - 1) * $perPage);

        if ($vista === 'detalle') {
            $stT = $this->db->prepare("SELECT COUNT(*) AS total,
                                              COALESCE(SUM(t.signo * t.subtotal), 0) AS neto,
                                              COALESCE(SUM(t.signo * t.iva), 0) AS iva
                                       {$base}");
            $stT->execute($params);
            $tot = $stT->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'neto' => 0];

            $col = $orden[$ordenCol] ?? $orden['fecha'];
            $sql = "SELECT t.* {$base}
                    ORDER BY {$col} {$dir}, t.fecha DESC, t.id_documento DESC, t.id_linea ASC
                    {$limite}";
        } else {
            // Un producto/servicio = mismo código y descripción (sin distinguir mayúsculas).
            // Veces, última fecha y último precio miran solo documentos que suman (no NC).
            $grupo = "SELECT MIN(t.codigo) AS codigo,
                             MIN(t.descripcion) AS descripcion,
                             COUNT(DISTINCT t.origen || ':' || t.id_documento) FILTER (WHERE t.signo = 1) AS documentos,
                             SUM(t.signo * t.cantidad) AS cantidad,
                             SUM(t.signo * t.subtotal) AS total,
                             SUM(t.signo * t.iva) AS iva,
                             MAX(t.fecha) FILTER (WHERE t.signo = 1) AS ultima_fecha,
                             (ARRAY_AGG(t.precio_unitario ORDER BY t.fecha DESC, t.id_documento DESC, t.id_linea DESC)
                                 FILTER (WHERE t.signo = 1))[1] AS ultimo_precio
                      {$base}
                      GROUP BY UPPER(t.codigo), UPPER(t.descripcion)";

            $stT = $this->db->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(g.total), 0) AS neto,
                                              COALESCE(SUM(g.iva), 0) AS iva
                                       FROM ( {$grupo} ) g");
            $stT->execute($params);
            $tot = $stT->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'neto' => 0];

            $col = $orden[$ordenCol] ?? $orden['ultima_fecha'];
            $sql = "SELECT g.* FROM ( {$grupo} ) g
                    ORDER BY {$col} {$dir} NULLS LAST, g.descripcion ASC
                    {$limite}";
        }

        $st = $this->db->prepare($sql);
        $st->execute($params);

        return [
            'rows'       => $st->fetchAll(PDO::FETCH_ASSOC),
            'total'      => (int) $tot['total'],
            'total_neto' => (float) $tot['neto'],
            'total_iva'  => (float) $tot['iva'],
        ];
    }

    /**
     * Columnas ordenables de cada vista (clave recibida del navegador => expresión SQL
     * fija). Deben coincidir con las columnas "o: true" de public/js/components/ficha_consultas.js.
     */
    private function ordenLineasDocumento(string $vista): array
    {
        if ($vista === 'producto') {
            return [
                'codigo'        => 'g.codigo',
                'descripcion'   => 'g.descripcion',
                'documentos'    => 'g.documentos',
                'cantidad'      => 'g.cantidad',
                'ultimo_precio' => 'g.ultimo_precio',
                'ultima_fecha'  => 'g.ultima_fecha',
                'total'         => 'g.total',
            ];
        }
        return [
            'fecha'       => 't.fecha',
            'documento'   => 't.numero_documento',
            'codigo'      => 't.codigo',
            'descripcion' => 't.descripcion',
            'cantidad'    => 't.cantidad',
            'precio'      => 't.precio_unitario',
            'subtotal'    => 't.subtotal',
            'iva'         => 't.iva',
        ];
    }
}
