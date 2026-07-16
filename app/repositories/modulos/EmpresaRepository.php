<?php

namespace App\repositories\modulos;

use App\models\BaseModel;

class EmpresaRepository extends BaseModel
{
    public function getEmisorConfig(int $idEmpresa): ?array
    {
        $id = (int) $idEmpresa;
                $sql = "SELECT id, ruc, nombre, nombre_comercial, establecimiento, direccion, telefono, mail,
                       resolucion_contribuyente, id_tipo_regimen, tipo_ambiente, agente_retencion, tipo_emision,
                       nom_rep_legal, ced_rep_legal, nombre_contador, ruc_contador, cod_prov, cod_ciudad,
                       tipo, valor_cobro, periodo_vigencia_desde, periodo_vigencia_hasta, estado_pago, estado,
                       cancelar_renovacion, obligado_contabilidad, id_empresa_suscripciones, id_cliente_facturado,
                       COALESCE(max_usuarios, 3) AS max_usuarios
                FROM empresas
                WHERE id = {$id} AND eliminado = false";
        $res = $this->query($sql);
        return $res[0] ?? null;
    }

    public function getEmpresasByRuc(string $ruc): array
    {
        $ruc = $this->escape($ruc);
        $sql = "SELECT id, ruc, nombre FROM empresas WHERE ruc = '{$ruc}' AND eliminado = false";
        return $this->query($sql);
    }

    /** RUC de una empresa por id (para resolver la empresa facturada). */
    public function getRucPorId(int $id): ?string
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT ruc FROM empresas WHERE id = {$id} AND eliminado = false");
        return $r[0]['ruc'] ?? null;
    }

    /**
     * Resuelve la empresa que controla las suscripciones de la empresa actual,
     * relacionando SIEMPRE por RUC (no por establecimiento):
     *   1) el vínculo directo de la fila actual (id_empresa_suscripciones), si existe;
     *   2) el vínculo de cualquier empresa hermana con el mismo RUC (otro establecimiento);
     *   3) la empresa administradora por defecto (es_administradora_suscripciones = true).
     * Devuelve el id de la controladora o null si no hay ninguna.
     */
    public function resolverEmpresaControladoraSuscripciones(string $ruc, ?int $idDirecto): ?int
    {
        if ($idDirecto !== null && $idDirecto > 0) {
            return $idDirecto;
        }

        $rucNorm = preg_replace('/\D/', '', $ruc);
        if ($rucNorm !== '') {
            $rucEsc = $this->escape($rucNorm);
            $r = $this->query(
                "SELECT id_empresa_suscripciones
                 FROM empresas
                 WHERE regexp_replace(ruc, '[^0-9]', '', 'g') = '{$rucEsc}'
                   AND id_empresa_suscripciones IS NOT NULL
                   AND eliminado = false
                 ORDER BY id LIMIT 1"
            );
            if (!empty($r[0]['id_empresa_suscripciones'])) {
                return (int) $r[0]['id_empresa_suscripciones'];
            }
        }

        $g = $this->query(
            "SELECT id FROM empresas
             WHERE es_administradora_suscripciones = true AND eliminado = false
             ORDER BY id LIMIT 1"
        );
        return isset($g[0]['id']) ? (int) $g[0]['id'] : null;
    }

    public function updateEmpresa(int $idEmpresa, array $data): bool
    {
        $id = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        $sets = [];
        
        // Lista de columnas permitidas en la tabla 'empresas'
        $allowed = [
            'nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion',
            'telefono', 'mail', 'nom_rep_legal', 'ced_rep_legal', 'cod_prov',
            'cod_ciudad', 'nombre_contador', 'ruc_contador', 'estado', 'tipo',
            'resolucion_contribuyente', 'id_tipo_regimen', 'tipo_ambiente',
            'agente_retencion', 'tipo_emision', 'cancelar_renovacion', 'obligado_contabilidad',
        ];

        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $val = $this->escape((string) $v);
                $sets[] = "{$k} = '{$val}'";
            }
        }
        
        if (empty($sets)) return true;

        // Incluimos auditoría si las columnas existen (ya las aseguramos arriba)
        $sql = "UPDATE empresas SET " . implode(', ', $sets) . ", updated_at = NOW(), updated_by = {$user} WHERE id = {$id}";
        return $this->execute($sql);
    }

    public function saveCorreoConfig(int $idEmpresa, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $id = (int) $idEmpresa;
            $ssl = ($data['ssl_habilitado'] ?? false) ? 'true' : 'false';
            $envio = ($data['envio_automatico'] ?? false) ? 'true' : 'false';
            $asunto = $this->escape($data['asunto_correo'] ?? '');
            $host = $this->escape($data['host'] ?? '');
            $puerto = (int) ($data['puerto'] ?? 0);
            $correo = $this->escape($data['correo_emisor'] ?? '');
            $pass = $this->escape($data['password_correo_emisor'] ?? '');
            $tipoCorreo = $this->escape($data['tipo_correo'] ?? 'camagare');
            $cuerpoCorreo = $this->escape($data['cuerpo_correo'] ?? '');
            $user = (int) ($_SESSION['id_usuario'] ?? 0);

            $check = $this->query("SELECT id FROM empresa_correo WHERE id_empresa = {$id} AND eliminado = false");
            if (!empty($check)) {
                $sql = "UPDATE empresa_correo SET 
                        ssl_habilitado = {$ssl}, envio_automatico = {$envio}, asunto_correo = '{$asunto}', host = '{$host}', 
                        puerto = {$puerto}, correo_emisor = '{$correo}', password_correo_emisor = '{$pass}',
                        tipo_correo = '{$tipoCorreo}', cuerpo_correo = '{$cuerpoCorreo}',
                        updated_at = NOW(), updated_by = {$user}
                        WHERE id_empresa = {$id}";
            } else {
                $sql = "INSERT INTO empresa_correo (id_empresa, ssl_habilitado, envio_automatico, asunto_correo, host, puerto, correo_emisor, password_correo_emisor, tipo_correo, cuerpo_correo, created_by, updated_by)
                        VALUES ({$id}, {$ssl}, {$envio}, '{$asunto}', '{$host}', {$puerto}, '{$correo}', '{$pass}', '{$tipoCorreo}', '{$cuerpoCorreo}', {$user}, {$user})";
            }
            $this->execute($sql);
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function getCorreoConfig(int $idEmpresa): ?array
    {
        $id = (int) $idEmpresa;
        $res = $this->query("SELECT * FROM empresa_correo WHERE id_empresa = {$id} AND eliminado = false");
        return $res[0] ?? null;
    }

    public function saveFirma(int $idEmpresa, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $id = (int) $idEmpresa;
            $nom = $this->escape($data['archivo_nombre'] ?? '');
            $ruta = $this->escape($data['archivo_ruta'] ?? '');
            $pass = $this->escape($data['password_firma'] ?? '');
            $fechaEmi = !empty($data['fecha_emision']) ? "'" . $this->escape($data['fecha_emision']) . "'" : "NULL";
            $fechaExp = !empty($data['fecha_expiracion']) ? "'" . $this->escape($data['fecha_expiracion']) . "'" : "NULL";
            $user = (int) ($_SESSION['id_usuario'] ?? 0);

            $sql = "INSERT INTO empresa_firma (id_empresa, archivo_nombre, archivo_ruta, password_firma, es_activo, fecha_emision, fecha_expiracion, created_by, updated_by)
                    VALUES ({$id}, '{$nom}', '{$ruta}', '{$pass}', false, {$fechaEmi}, {$fechaExp}, {$user}, {$user})";
            $this->execute($sql);

            // Determinar cuál es la firma más actualizada (mayor fecha de expiración) y ponerla como activa
            $this->execute("UPDATE empresa_firma SET es_activo = false WHERE id_empresa = {$id}");
            $this->execute("
                UPDATE empresa_firma 
                SET es_activo = true 
                WHERE id = (
                    SELECT id 
                    FROM empresa_firma 
                    WHERE id_empresa = {$id} AND eliminado = false 
                    ORDER BY fecha_expiracion DESC NULLS LAST, created_at DESC 
                    LIMIT 1
                )
            ");

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getFirmas(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query("SELECT * FROM empresa_firma WHERE id_empresa = {$id} AND eliminado = false ORDER BY created_at DESC");
    }

    public function getFirmaById(int $id): ?array
    {
        $id = (int) $id;
        $res = $this->query("SELECT * FROM empresa_firma WHERE id = {$id} AND eliminado = false");
        return $res[0] ?? null;
    }

    public function getEstablecimientos(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT * FROM empresa_establecimiento WHERE id_empresa = {$id} AND eliminado = false ORDER BY codigo ASC";
        return $this->query($sql);
    }

    /** Normaliza el código a 3 dígitos (000-999). Lanza excepción si el formato es inválido. */
    private function normalizarCodigoEstablecimiento(string $codigo): string
    {
        $codigo = trim($codigo);
        if (!preg_match('/^\d{1,3}$/', $codigo)) {
            throw new \Exception('El código del establecimiento debe ser de 3 dígitos (000 a 999).');
        }
        return str_pad($codigo, 3, '0', STR_PAD_LEFT);
    }

    /** ¿Ya existe otro establecimiento con ese código en la misma empresa? */
    private function existeCodigoEstablecimiento(int $idEmpresa, string $codigo, ?int $excluirId = null): bool
    {
        $idEmp = (int) $idEmpresa;
        $cod   = $this->escape($codigo);
        $sql = "SELECT 1 FROM empresa_establecimiento
                WHERE id_empresa = {$idEmp} AND TRIM(codigo) = '{$cod}' AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != ' . (int) $excluirId;
        }
        return !empty($this->query($sql));
    }

    public function saveEstablecimiento(int $idEmpresa, array $data): int
    {
        $id = (int) $idEmpresa;

        $codNorm = $this->normalizarCodigoEstablecimiento((string) ($data['codigo'] ?? '001'));
        if ($this->existeCodigoEstablecimiento($id, $codNorm)) {
            throw new \Exception("Ya existe un establecimiento con el código {$codNorm} en esta empresa.");
        }
        $data['codigo'] = $codNorm;

        $nom = $this->escape($data['nombre'] ?? '');
        $cod = $this->escape($data['codigo'] ?? '001');
        $dir = $this->escape($data['direccion'] ?? '');
        $tipo = $this->escape($data['tipo'] ?? 'Matriz');
        $logo = $this->escape($data['logo_ruta'] ?? '');
        $leyendaTitulo = $this->escape($data['leyenda_pdf_titulo'] ?? '');
        $leyendaMensaje = $this->escape($data['leyenda_pdf_mensaje'] ?? '');
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $sql = "INSERT INTO empresa_establecimiento (id_empresa, nombre, codigo, direccion, tipo, logo_ruta, leyenda_pdf_titulo, leyenda_pdf_mensaje, created_by, updated_by)
                VALUES ({$id}, '{$nom}', '{$cod}', '{$dir}', '{$tipo}', '{$logo}', '{$leyendaTitulo}', '{$leyendaMensaje}', {$user}, {$user})";
        $this->execute($sql);
        return $this->lastInsertId('empresa_establecimiento_id_seq');
    }

    public function updateEstablecimiento(int $idEst, int $idEmpresa, array $data): bool
    {
        $id = (int) $idEst;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        // El establecimiento matriz también es editable (código, tipo y estado).
        // Se valida el formato del código y que no se repita dentro de la misma empresa.
        $codNorm = $this->normalizarCodigoEstablecimiento((string) ($data['codigo'] ?? '001'));
        if ($this->existeCodigoEstablecimiento((int) $idEmpresa, $codNorm, $id)) {
            throw new \Exception("Ya existe otro establecimiento con el código {$codNorm} en esta empresa.");
        }
        $data['codigo'] = $codNorm;

        $nom = $this->escape($data['nombre'] ?? '');
        $cod = $this->escape($data['codigo'] ?? '001');
        $dir = $this->escape($data['direccion'] ?? '');
        $tipo = $this->escape($data['tipo'] ?? 'Matriz');
        $est = $this->escape($data['estado'] ?? 'activo');
        $logo = isset($data['logo_ruta']) ? $this->escape($data['logo_ruta']) : null;
        
        $leyendaTitulo = $this->escape($data['leyenda_pdf_titulo'] ?? '');
        $leyendaMensaje = $this->escape($data['leyenda_pdf_mensaje'] ?? '');

        $logoSql = ($logo !== null) ? ", logo_ruta = '{$logo}'" : "";

        $sql = "UPDATE empresa_establecimiento SET 
                nombre = '{$nom}', codigo = '{$cod}', direccion = '{$dir}', tipo = '{$tipo}',
                estado = '{$est}', leyenda_pdf_titulo = '{$leyendaTitulo}', leyenda_pdf_mensaje = '{$leyendaMensaje}' {$logoSql}, updated_at = NOW(), updated_by = {$user}
                WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        return $this->execute($sql);
    }

    public function deleteEstablecimiento(int $idEst, int $idEmpresa): bool
    {
        $id = (int) $idEst;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        // Validar si es matriz 001
        $current = $this->query("SELECT codigo, tipo FROM empresa_establecimiento WHERE id = {$id} AND id_empresa = {$idEmpresa}");
        if (!empty($current)) {
            $estActual = $current[0];
            if ($estActual['codigo'] === '001' || strtolower($estActual['tipo']) === 'matriz') {
                throw new \Exception('El establecimiento matriz no puede ser eliminado.');
            }
        }

        $sql = "UPDATE empresa_establecimiento SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        return $this->execute($sql);
    }

    public function getPrimerEstablecimientoId(int $idEmpresa): int
    {
        $id  = (int) $idEmpresa;
        $res = $this->query("SELECT id FROM empresa_establecimiento WHERE id_empresa = {$id} AND eliminado = false ORDER BY id ASC LIMIT 1");
        return (int) ($res[0]['id'] ?? 0);
    }

    public function getEstablecimientoConfig(int $idEst): ?array
    {
        $id = (int) $idEst;
        $sql = "SELECT id, decimales_cantidad, decimales_precio, calculo_iva_facturacion,
                       facturacion_inventario, metodo_costeo, facturacion_libre,
                       factura_solo_stock_positivo,
                       obligatorio_lotes, obligatorio_caducidad, obligatorio_nup,
                       mostrar_cajero_factura, mostrar_vendedor_factura,
                       mostrar_unidad_medida, valor_limite_consumidor_final,
                       id_forma_pago_sri_def,
                       editar_precio_factura, editar_iva_factura, editar_descuento_factura,
                       mostrar_propina_factura, logo_ruta,
                       factura_agrupar_items, factura_item_mostrar_unidad,
                       factura_item_mostrar_lote, factura_item_mostrar_caducidad,
                       factura_item_mostrar_nup,
                       inv_requiere_aprobacion, inv_notificar_correo, inv_usuarios_aprobadores
                FROM empresa_establecimiento
                WHERE id = {$id} AND eliminado = false";
        $res = $this->query($sql);
        return $res[0] ?? null;
    }

    public function updateEstablecimientoConfig(int $idEst, array $data): bool
    {
        $id   = (int) $idEst;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $allowed = [
            'decimales_cantidad', 'decimales_precio', 'calculo_iva_facturacion',
            'facturacion_inventario', 'metodo_costeo', 'facturacion_libre',
            'factura_solo_stock_positivo',
            'obligatorio_lotes', 'obligatorio_caducidad', 'obligatorio_nup',
            'mostrar_cajero_factura', 'mostrar_vendedor_factura',
            'mostrar_unidad_medida', 'valor_limite_consumidor_final',
            'id_forma_pago_sri_def',
            'editar_precio_factura', 'editar_iva_factura', 'editar_descuento_factura',
            'mostrar_propina_factura',
            'factura_agrupar_items', 'factura_item_mostrar_unidad',
            'factura_item_mostrar_lote', 'factura_item_mostrar_caducidad',
            'factura_item_mostrar_nup',
            'inv_requiere_aprobacion', 'inv_notificar_correo', 'inv_usuarios_aprobadores',
        ];

        // Campos numéricos que admiten NULL
        $numericNullable = ['valor_limite_consumidor_final', 'id_forma_pago_sri_def'];

        $sets = [];
        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                if (in_array($k, $numericNullable, true) && ($v === 'NULL' || $v === null || $v === '')) {
                    $sets[] = "{$k} = NULL";
                } elseif (in_array($k, $numericNullable, true)) {
                    $sets[] = "{$k} = " . (float) $v;
                } else {
                    $val    = $this->escape((string) $v);
                    $sets[] = "{$k} = '{$val}'";
                }
            }
        }

        if (empty($sets)) return true;

        $sql = "UPDATE empresa_establecimiento SET " . implode(', ', $sets)
             . ", updated_at = NOW(), updated_by = {$user} WHERE id = {$id}";
        return $this->execute($sql);
    }

    public function getPuntosEmision(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT p.*, e.codigo AS cod_establecimiento 
                FROM empresa_punto_emision p
                LEFT JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                WHERE p.id_empresa = {$id} AND p.eliminado = false 
                ORDER BY e.codigo, p.codigo_punto ASC";
        return $this->query($sql);
    }

    public function savePuntoEmision(int $idEmpresa, array $data): int
    {
        $id = (int) $idEmpresa;
        $est_id = (int) ($data['id_establecimiento'] ?? 0);
        $nom = $this->escape($data['nombre'] ?? '');
        $cod = $this->escape($data['codigo_punto'] ?? '001');
        $logo = $this->escape($data['logo_ruta'] ?? '');
        $est = $this->escape($data['estado'] ?? 'activo');
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $sql = "INSERT INTO empresa_punto_emision (id_empresa, id_establecimiento, nombre, codigo_punto, logo_ruta, estado, created_by, updated_by)
                VALUES ({$id}, {$est_id}, '{$nom}', '{$cod}', '{$logo}', '{$est}', {$user}, {$user})";
        $this->execute($sql);
        return $this->lastInsertId('empresa_punto_emision_id_seq');
    }

    /** Datos actuales de un punto de emisión (para validar qué cambió). */
    public function getPuntoEmision(int $idPunto, int $idEmpresa): ?array
    {
        $id = (int) $idPunto; $ide = (int) $idEmpresa;
        $r = $this->query("SELECT id, id_establecimiento, nombre, codigo_punto, estado
                           FROM empresa_punto_emision WHERE id = {$id} AND id_empresa = {$ide} AND eliminado = false");
        return $r[0] ?? null;
    }

    public function updatePuntoEmision(int $idPunto, int $idEmpresa, array $data): bool
    {
        $id = (int) $idPunto;
        $est_id = (int) ($data['id_establecimiento'] ?? 0);
        $nom = $this->escape($data['nombre'] ?? '');
        $cod = $this->escape($data['codigo_punto'] ?? '001');
        $est = $this->escape($data['estado'] ?? 'activo');
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $sql = "UPDATE empresa_punto_emision SET 
                id_establecimiento = {$est_id}, nombre = '{$nom}', codigo_punto = '{$cod}', 
                estado = '{$est}', updated_at = NOW(), updated_by = {$user}
                WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        return $this->execute($sql);
    }

    public function deletePuntoEmision(int $idPunto, int $idEmpresa): bool
    {
        $id = (int) $idPunto;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        $sql = "UPDATE empresa_punto_emision SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        return $this->execute($sql);
    }

    /**
     * Devuelve los módulos donde el punto de emisión ya está siendo utilizado en documentos.
     * Si el resultado no está vacío, el punto no debe poder editarse ni eliminarse.
     *
     * IMPORTANTE: la verificación NO filtra por tipo_ambiente, por lo que detecta el uso
     * en CUALQUIER ambiente (pruebas '1' y producción '2'). Un punto usado en pruebas
     * tampoco podrá editarse/eliminarse aunque la empresa esté en producción, y viceversa.
     * Cada módulo se reporta con el/los ambiente(s) donde tiene uso.
     *
     * @return string[] Nombres descriptivos de los módulos con uso (incluyendo ambiente)
     */
    public function puntoEmisionEnUso(int $idPunto, int $idEmpresa): array
    {
        $checks = [
            'ventas_cabecera'            => 'Facturas de venta',
            'ingresos_cabecera'          => 'Ingresos',
            'egresos_cabecera'           => 'Egresos',
            'notas_credito_cabecera'     => 'Notas de crédito',
            'guias_remision_cabecera'    => 'Guías de remisión',
            'liquidaciones_cabecera'     => 'Liquidaciones de compra',
            'retencion_compra_cabecera'  => 'Retenciones en compras',
            'ordenes_compra'             => 'Órdenes de compra',
            'pedidos_cabecera'           => 'Pedidos',
        ];

        $idp = (int) $idPunto;
        $ide = (int) $idEmpresa;

        $usos = [];
        foreach ($checks as $tabla => $nombre) {
            try {
                // Agrupa por tipo_ambiente para reportar en qué ambiente(s) hay uso.
                // No se filtra por ambiente: se considera cualquier ambiente.
                $res = $this->query(
                    "SELECT DISTINCT tipo_ambiente FROM {$tabla}
                     WHERE id_punto_emision = {$idp} AND id_empresa = {$ide} AND eliminado = false"
                );
                if (!empty($res)) {
                    $ambientes = [];
                    foreach ($res as $row) {
                        $amb = (string) ($row['tipo_ambiente'] ?? '');
                        $ambientes[] = $amb === '2' ? 'producción' : ($amb === '1' ? 'pruebas' : 'sin ambiente');
                    }
                    $ambientes = array_values(array_unique($ambientes));
                    $usos[] = $nombre . ' (' . implode(' y ', $ambientes) . ')';
                }
            } catch (\Throwable) {
                // Si la tabla no existe en alguna instalación, se ignora
            }
        }

        return $usos;
    }

    public function updateSecuencial(int $idPunto, string $tipo, int $numero, int $idEmpresa): bool
    {
        $id = (int) $idPunto;
        $t = $this->escape($tipo);
        $n = (int) $numero;
        $idEmp = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $check = $this->query("SELECT id FROM empresa_secuencial WHERE id_punto_emision = {$id} AND tipo_documento = '{$t}' AND id_empresa = {$idEmp} AND eliminado = false");
        if (!empty($check)) {
            $sql = "UPDATE empresa_secuencial SET secuencial_inicial = {$n}, updated_at = NOW(), updated_by = {$user}
                    WHERE id_punto_emision = {$id} AND tipo_documento = '{$t}' AND id_empresa = {$idEmp}";
        } else {
            $sql = "INSERT INTO empresa_secuencial (id_punto_emision, id_empresa, tipo_documento, secuencial_inicial, created_by, updated_by)
                    VALUES ({$id}, {$idEmp}, '{$t}', {$n}, {$user}, {$user})";
        }
        return $this->execute($sql);
    }

    public function getSecuencial(int $idPunto, string $tipo, int $idEmpresa = 0): int
    {
        $id = (int) $idPunto;
        $t = $this->escape($tipo);
        $where = "id_punto_emision = {$id} AND tipo_documento = '{$t}' AND eliminado = false";
        if ($idEmpresa > 0) $where .= " AND id_empresa = {$idEmpresa}";
        $res = $this->query("SELECT secuencial_inicial FROM empresa_secuencial WHERE {$where}");
        return (int) ($res[0]['secuencial_inicial'] ?? 1);
    }

    public function getSecuencialesByPunto(int $idPunto, int $idEmpresa): array
    {
        $id = (int) $idPunto;
        $idEmp = (int) $idEmpresa;
        $res = $this->query("SELECT id, tipo_documento, COALESCE(secuencial_inicial, 1) AS secuencial_inicial FROM empresa_secuencial WHERE id_punto_emision = {$id} AND id_empresa = {$idEmp} AND eliminado = false ORDER BY tipo_documento ASC");
        return $res ?: [];
    }

    public function updateSecuencialById(int $id, string $tipo, int $numero, int $idEmpresa): bool
    {
        $idEmp = (int) $idEmpresa;
        $t = $this->escape($tipo);
        $n = (int) $numero;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        $sql = "UPDATE empresa_secuencial SET tipo_documento = '{$t}', secuencial_inicial = {$n}, updated_at = NOW(), updated_by = {$user} WHERE id = {$id} AND id_empresa = {$idEmp} AND eliminado = false";
        return $this->execute($sql);
    }

    public function hasSecuenciales(int $idPunto, int $idEmpresa): bool
    {
        $res = $this->query(
            "SELECT 1 FROM empresa_secuencial WHERE id_punto_emision = {$idPunto} AND id_empresa = {$idEmpresa} AND eliminado = false LIMIT 1"
        );
        return !empty($res);
    }

    public function crearSecuencialesIniciales(int $idPunto, int $idEmpresa): bool
    {
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        $tiposIniciales = [
            'Facturas de venta',
            'Nota de crédito',
            'Nota de débito',
            'Retenciones de compras',
            'Guía de remisión',
            'Liquidación de compras o servicios',
            'Ingresos',
            'Egresos',
            'Pedidos',
            'Órdenes de compra',
        ];

        $this->db->beginTransaction();
        try {
            foreach ($tiposIniciales as $tipo) {
                $t = $this->escape($tipo);
                $existe = $this->query(
                    "SELECT id FROM empresa_secuencial WHERE id_punto_emision = {$idPunto} AND id_empresa = {$idEmpresa} AND tipo_documento = '{$t}' AND eliminado = false"
                );
                if (!empty($existe)) continue;

                $this->execute(
                    "INSERT INTO empresa_secuencial (id_punto_emision, id_empresa, tipo_documento, secuencial_inicial, created_by, updated_by)
                     VALUES ({$idPunto}, {$idEmpresa}, '{$t}', 1, {$user}, {$user})"
                );
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function getUsuariosAsignados(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        // Se excluyen los super administradores (nivel 3): no se muestran en la lista
        // ni cuentan para el cupo de usuarios de la empresa.
        $sql = "SELECT u.id, u.nombre, u.estado, u.nivel, u.mail AS correo
                FROM empresa_asignada ea
                INNER JOIN usuarios u ON u.id = ea.id_usuario
                WHERE ea.id_empresa = {$id}
                  AND COALESCE(u.nivel, 1) < 3
                ORDER BY u.nombre ASC";
        return $this->query($sql);
    }

    public function getIvaCasilleros(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto
                FROM empresa_casilleros_iva_sri
                WHERE id_empresa = {$id} AND eliminado = false";
        $res = $this->query($sql);

        $mapping = [];
        foreach ($res as $row) {
            $td = $row['tipo_documento'];
            $cod = $row['codigo'];
            if (!isset($mapping[$td])) {
                $mapping[$td] = [];
            }
            $mapping[$td][$cod] = [
                'bruto'    => $row['casillero_bruto'],
                'neto'     => $row['casillero_neto'],
                'impuesto' => $row['casillero_impuesto'],
            ];
        }
        return $mapping;
    }

    public function clearIvaCasilleros(int $idEmpresa): bool
    {
        $id = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        return $this->execute("UPDATE empresa_casilleros_iva_sri SET eliminado = true, deleted_at = NOW(), deleted_by = {$user} WHERE id_empresa = {$id} AND eliminado = false");
    }

    public function updateIvaCasillero(int $idEmpresa, int $codigo, string $tipoDocumento, array $data): bool
    {
        $idEmp  = (int) $idEmpresa;
        $cod    = (int) $codigo;
        $td     = $this->escape($tipoDocumento);
        
        $bruto  = $this->escape($data['bruto'] ?? '');
        $neto   = $this->escape($data['neto'] ?? '');
        $imp    = $this->escape($data['impuesto'] ?? '');
        $user   = (int) ($_SESSION['id_usuario'] ?? 0);

        $check = $this->query("SELECT id FROM empresa_casilleros_iva_sri WHERE id_empresa = {$idEmp} AND codigo = {$cod} AND tipo_documento = '{$td}' AND eliminado = false");
        if (!empty($check)) {
            $sql = "UPDATE empresa_casilleros_iva_sri SET
                    casillero_bruto    = '{$bruto}',
                    casillero_neto     = '{$neto}',
                    casillero_impuesto = '{$imp}',
                    updated_at = NOW(), updated_by = {$user}
                    WHERE id_empresa = {$idEmp} AND codigo = {$cod} AND tipo_documento = '{$td}'";

        } else {
            $sql = "INSERT INTO empresa_casilleros_iva_sri
                        (id_empresa, codigo, tipo_documento, casillero_bruto, casillero_neto, casillero_impuesto, created_by, updated_by)
                    VALUES ({$idEmp}, {$cod}, '{$td}', '{$bruto}', '{$neto}', '{$imp}', {$user}, {$user})";
        }
        return $this->execute($sql);
    }

    public function getRetencionesSriIva(): array
    {
        $sql = "SELECT id, concepto_ret, porcentaje_ret AS porcentaje
                FROM retenciones_sri
                WHERE impuesto_ret = 'IVA'
                ORDER BY porcentaje_ret ASC";
        return $this->query($sql);
    }

    public function getRetencionesCasilleros(int $idEmpresa): array
    {
        $id  = (int) $idEmpresa;
        $sql = "SELECT codigo, casillero_bruto, casillero_neto
                FROM empresa_casilleros_iva_sri
                WHERE id_empresa = {$id} AND tipo_documento = 'retencion_iva' AND eliminado = false";
        $res = $this->query($sql);

        $map = [];
        foreach ($res as $row) {
            $map[(int)$row['codigo']] = [
                'casillero_compras' => $row['casillero_bruto'], // Mapeamos bruto a compras por compatibilidad anterior
                'casillero_ventas'  => $row['casillero_neto'],  // Mapeamos neto a ventas por compatibilidad anterior
            ];
        }
        return $map;
    }

    public function updateRetencionCasillero(int $idEmpresa, int $idRetencion, array $data): void
    {
        $idEmp = (int) $idEmpresa;
        $idRet = (int) $idRetencion;
        $comp  = $this->escape($data['cas_compras'] ?? '');
        $ven   = $this->escape($data['cas_ventas']  ?? '');
        $user  = (int) ($_SESSION['id_usuario'] ?? 0);

        $check = $this->query("SELECT id FROM empresa_casilleros_iva_sri WHERE id_empresa = {$idEmp} AND codigo = {$idRet} AND tipo_documento = 'retencion_iva' AND eliminado = false");
        if (!empty($check)) {
            $sql = "UPDATE empresa_casilleros_iva_sri SET
                    casillero_bruto = '{$comp}',
                    casillero_neto  = '{$ven}',
                    updated_at = NOW(), updated_by = {$user}
                    WHERE id_empresa = {$idEmp} AND codigo = {$idRet} AND tipo_documento = 'retencion_iva'";
        } else {
            $sql = "INSERT INTO empresa_casilleros_iva_sri
                        (id_empresa, codigo, tipo_documento,
                         casillero_bruto, casillero_neto, casillero_impuesto,
                         created_by, updated_by)
                    VALUES ({$idEmp}, {$idRet}, 'retencion_iva',
                            '{$comp}', '{$ven}', '',
                            {$user}, {$user})";
        }
        $this->execute($sql);
    }

    public function getIces(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT i.*
                FROM empresa_ice i
                WHERE i.id_empresa = {$id} AND i.eliminado = false
                ORDER BY i.nombre_ice ASC";
        return $this->query($sql);
    }

    public function saveIce(array $data): bool
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $idEmpresa = (int)$data['id_empresa'];
        $casillero = $this->escape($data['casillero_ice'] ?? '');
        $casilleroBase = $this->escape($data['casillero_base_ice'] ?? '');
        $codigo = $this->escape($data['codigo_ats'] ?? '');
        $nombre = $this->escape($data['nombre_ice'] ?? '');
        $valor = (float)($data['valor_ice'] ?? 0);
        $user = (int)($_SESSION['id_usuario'] ?? 0);

        if ($id > 0) {
            $sql = "UPDATE empresa_ice SET 
                    casillero_ice = '{$casillero}',
                    casillero_base_ice = '{$casilleroBase}',
                    codigo_ats = '{$codigo}',
                    nombre_ice = '{$nombre}',
                    valor_ice = {$valor},
                    updated_at = NOW(),
                    updated_by = {$user}
                    WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        } else {
            $sql = "INSERT INTO empresa_ice (id_empresa, casillero_ice, casillero_base_ice, codigo_ats, nombre_ice, valor_ice, created_by, updated_by)
                    VALUES ({$idEmpresa}, '{$casillero}', '{$casilleroBase}', '{$codigo}', '{$nombre}', {$valor}, {$user}, {$user})";
        }
        return $this->execute($sql);
    }

    public function deleteIce(int $id, int $idEmpresa): bool
    {
        $id = (int)$id;
        $idEmpresa = (int)$idEmpresa;
        $user = (int)($_SESSION['id_usuario'] ?? 0);
        $sql = "UPDATE empresa_ice SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                WHERE id = {$id} AND id_empresa = {$idEmpresa}";
        return $this->execute($sql);
    }
}
