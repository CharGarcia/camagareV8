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
                       cancelar_renovacion, obligado_contabilidad, id_empresa_suscripciones, id_cliente_facturado, id_suscripcion,
                       COALESCE(max_usuarios, 3) AS max_usuarios,
                       COALESCE(usa_liquidacion_diferida_iva, false) AS usa_liquidacion_diferida_iva,
                       COALESCE(es_demo, false) AS es_demo,
                       COALESCE(es_matriz, false) AS es_matriz
                FROM empresas
                WHERE id = {$id} AND eliminado = false";
        $res = $this->query($sql);
        return $res[0] ?? null;
    }

    /** true si la empresa está marcada como demo (ver EmpresaService::saveEmisor). */
    public function esDemo(int $idEmpresa): bool
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT COALESCE(es_demo, false) FROM empresas WHERE id = {$id} AND eliminado = false";
        $res = $this->query($sql);
        return !empty($res[0]) && filter_var(reset($res[0]), FILTER_VALIDATE_BOOLEAN);
    }

    public function getEmpresasByRuc(string $ruc): array
    {
        $ruc = $this->escape($ruc);
        $sql = "SELECT id, ruc, nombre FROM empresas WHERE ruc = '{$ruc}' AND eliminado = false";
        return $this->query($sql);
    }

    /** ¿Es esta empresa la matriz de su grupo RUC? */
    public function getEsMatriz(int $idEmpresa): bool
    {
        $id = (int) $idEmpresa;
        $res = $this->query("SELECT COALESCE(es_matriz, false) FROM empresas WHERE id = {$id} AND eliminado = false");
        return !empty($res[0]) && filter_var(reset($res[0]), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Empresa marcada matriz dentro del grupo RUC de $idEmpresa, excluyéndola a ella misma.
     * Null si nadie más del grupo es matriz (o el grupo no tiene matriz asignada aún).
     */
    public function getOtraMatrizDelGrupo(int $idEmpresa): ?array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT id, ruc, establecimiento, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nombre
                FROM empresas
                WHERE eliminado = false AND es_matriz = true AND id != {$id}
                  AND ruc = (SELECT ruc FROM empresas WHERE id = {$id} AND eliminado = false)
                LIMIT 1";
        $res = $this->query($sql);
        return $res[0] ?? null;
    }

    /**
     * Marca $idEmpresa como matriz de su grupo RUC y desmarca a cualquier otra del mismo
     * grupo (solo puede haber una). Transaccional: sin esto, dos requests concurrentes
     * podrían dejar dos empresas marcadas matriz a la vez.
     */
    public function marcarMatriz(int $idEmpresa): void
    {
        $id = (int) $idEmpresa;
        $pdo = \App\core\Database::getConnection();
        $managedTransaction = !$pdo->inTransaction();
        if ($managedTransaction) $pdo->beginTransaction();
        try {
            $pdo->exec("UPDATE empresas SET es_matriz = false
                        WHERE eliminado = false AND es_matriz = true
                          AND ruc = (SELECT ruc FROM empresas WHERE id = {$id} AND eliminado = false)");
            $pdo->exec("UPDATE empresas SET es_matriz = true WHERE id = {$id} AND eliminado = false");
            if ($managedTransaction) $pdo->commit();
        } catch (\Throwable $e) {
            if ($managedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * IDs de todas las empresas (activas) que comparten el mismo RUC que $idEmpresa,
     * incluida ella misma. Un mismo RUC puede tener varias filas en `empresas` (una
     * por establecimiento); esto permite deduplicar documentos del SRI a nivel de
     * contribuyente, ya que el SRI no distingue establecimiento comprador.
     */
    public function getIdsEmpresaMismoRuc(int $idEmpresa): array
    {
        $id  = (int) $idEmpresa;
        $sql = "SELECT id FROM empresas
                WHERE eliminado = false
                  AND ruc = (SELECT ruc FROM empresas WHERE id = {$id} AND eliminado = false)";
        $ids = array_map(static fn($r) => (int) $r['id'], $this->query($sql));
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * Mapa id_empresa => "establecimiento - nombre" para etiquetar, en reportes consolidados
     * por RUC (ATS, etc.), de cuál establecimiento propio viene cada fila.
     */
    public function getEtiquetasEstablecimiento(array $idsEmpresa): array
    {
        if (!$idsEmpresa) {
            return [];
        }
        $ids = implode(',', array_map('intval', $idsEmpresa));
        $sql = "SELECT id, establecimiento, COALESCE(NULLIF(nombre_comercial,''), nombre) AS nombre
                FROM empresas WHERE id IN ({$ids}) AND eliminado = false";
        $out = [];
        foreach ($this->query($sql) as $r) {
            $out[(int) $r['id']] = trim((string) $r['establecimiento']) . ' - ' . $r['nombre'];
        }
        return $out;
    }

    /**
     * IDs de empresas del mismo RUC que $idEmpresa a las que $idUsuario tiene acceso: todas si
     * es nivel 3 (sesión), si no, solo las asignadas (empresa_asignada) + la propia activa. Punto
     * único de esta regla — la usan Control Bancario y Dashboard para consolidar información
     * entre establecimientos del mismo RUC sin exponer datos de uno al que el usuario no fue
     * asignado.
     */
    public function getIdsGrupoRucAccesible(int $idEmpresa, int $idUsuario): array
    {
        $grupo = $this->getIdsEmpresaMismoRuc($idEmpresa);
        if ((int) ($_SESSION['nivel'] ?? 0) >= 3) {
            return $grupo;
        }
        $asignadas = array_map(
            static fn ($e) => (int) $e['id_empresa'],
            (new \App\models\EmpresaAsignada())->getEmpresasDeUsuario($idUsuario)
        );
        $asignadas[] = $idEmpresa;
        return array_values(array_intersect($grupo, $asignadas));
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

        // Columnas INTEGER: una cadena vacía debe guardarse como NULL, nunca como ''
        $integerCols = ['id_tipo_regimen', 'tipo_ambiente'];

        foreach ($data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                if (in_array($k, $integerCols, true) && ($v === '' || $v === null)) {
                    $sets[] = "{$k} = NULL";
                    continue;
                }
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
            $modoCuerpo = ($data['modo_cuerpo_correo'] ?? 'diseno') === 'propio' ? 'propio' : 'diseno';
            $user = (int) ($_SESSION['id_usuario'] ?? 0);

            $check = $this->query("SELECT id FROM empresa_correo WHERE id_empresa = {$id} AND eliminado = false");
            if (!empty($check)) {
                $sql = "UPDATE empresa_correo SET 
                        ssl_habilitado = {$ssl}, envio_automatico = {$envio}, asunto_correo = '{$asunto}', host = '{$host}', 
                        puerto = {$puerto}, correo_emisor = '{$correo}', password_correo_emisor = '{$pass}',
                        tipo_correo = '{$tipoCorreo}', cuerpo_correo = '{$cuerpoCorreo}',
                        modo_cuerpo_correo = '{$modoCuerpo}',
                        updated_at = NOW(), updated_by = {$user}
                        WHERE id_empresa = {$id}";
            } else {
                $sql = "INSERT INTO empresa_correo (id_empresa, ssl_habilitado, envio_automatico, asunto_correo, host, puerto, correo_emisor, password_correo_emisor, tipo_correo, cuerpo_correo, modo_cuerpo_correo, created_by, updated_by)
                        VALUES ({$id}, {$ssl}, {$envio}, '{$asunto}', '{$host}', {$puerto}, '{$correo}', '{$pass}', '{$tipoCorreo}', '{$cuerpoCorreo}', '{$modoCuerpo}', {$user}, {$user})";
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

    /**
     * El primero de la lista (índice 0) es el que usa EmpresaService::getData()
     * como "establecimiento principal" para toda la pestaña Establecimientos y
     * los valores por defecto de otras pestañas (Secuenciales, IVA, etc.).
     * Prioriza el que está `estado = 'activo'`: solo puede haber uno activo por
     * empresa a la vez (updateEstablecimiento() de este mismo archivo y
     * Empresa::actualizarEstablecimiento() desactivan automáticamente los demás
     * al activar uno), así que ese es, por definición, el que la empresa
     * realmente usa hoy. Si por algún motivo ninguno está activo, cae a id ASC
     * — el establecimiento con el que se creó la empresa siempre tiene el id
     * más bajo (ver getPrimerEstablecimientoId() y EmpresaInicializadorService::
     * obtenerOCrearEstablecimientoPrincipal()). Ordenar por código en cambio es
     * puramente alfabético: un establecimiento adicional con código menor (p.
     * ej. un "001" agregado por error) no debería ganarle al real solo por eso.
     */
    public function getEstablecimientos(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        $sql = "SELECT * FROM empresa_establecimiento
                 WHERE id_empresa = {$id} AND eliminado = false
                 ORDER BY CASE WHEN LOWER(estado) = 'activo' THEN 0 ELSE 1 END, id ASC";
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

    /**
     * ¿Ya usa este código otra empresa del mismo grupo RUC (varias filas de `empresas` pueden
     * compartir RUC)? El código SRI del establecimiento debe ser único por RUC completo, no solo
     * dentro de una empresa — sin esto, dos empresas hermanas podían terminar ambas con "002".
     */
    private function existeCodigoEstablecimientoEnGrupo(int $idEmpresa, string $codigo, ?int $excluirId = null): ?string
    {
        $idsGrupo = $this->getIdsEmpresaMismoRuc($idEmpresa);
        $idsOtras = array_values(array_diff($idsGrupo, [(int) $idEmpresa]));
        if (!$idsOtras) { return null; }

        $in  = implode(',', $idsOtras);
        $cod = $this->escape($codigo);
        $sql = "SELECT COALESCE(NULLIF(e.nombre_comercial,''), e.nombre) AS nombre
                FROM empresa_establecimiento ee
                JOIN empresas e ON e.id = ee.id_empresa AND e.eliminado = false
                WHERE ee.id_empresa IN ({$in}) AND TRIM(ee.codigo) = '{$cod}' AND ee.eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND ee.id != ' . (int) $excluirId;
        }
        $sql .= ' LIMIT 1';
        $res = $this->query($sql);
        return $res[0]['nombre'] ?? null;
    }

    public function saveEstablecimiento(int $idEmpresa, array $data): int
    {
        $id = (int) $idEmpresa;

        $codNorm = $this->normalizarCodigoEstablecimiento((string) ($data['codigo'] ?? '001'));
        if ($this->existeCodigoEstablecimiento($id, $codNorm)) {
            throw new \Exception("Ya existe un establecimiento con el código {$codNorm} en esta empresa.");
        }
        $otraEmpresa = $this->existeCodigoEstablecimientoEnGrupo($id, $codNorm);
        if ($otraEmpresa !== null) {
            throw new \Exception("El código {$codNorm} ya lo usa «{$otraEmpresa}», otra empresa del mismo RUC. El código debe ser único por RUC completo.");
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
        $otraEmpresa = $this->existeCodigoEstablecimientoEnGrupo((int) $idEmpresa, $codNorm, $id);
        if ($otraEmpresa !== null) {
            throw new \Exception("El código {$codNorm} ya lo usa «{$otraEmpresa}», otra empresa del mismo RUC. El código debe ser único por RUC completo.");
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
        $ok = $this->execute($sql);

        // Un solo establecimiento activo por empresa (ver Empresa::actualizarEstablecimiento()
        // en app/models/Empresa.php, misma regla replicada aquí para el módulo autoservicio).
        if ($ok && strtolower((string) ($data['estado'] ?? 'activo')) === 'activo') {
            $this->execute(
                "UPDATE empresa_establecimiento SET estado = 'inactivo', updated_at = NOW()
                 WHERE id_empresa = {$idEmpresa} AND id != {$id} AND eliminado = false"
            );
        }

        // Mantener sincronizado empresas.establecimiento con el código del
        // establecimiento Activo (ver Empresa::actualizarEstablecimiento(), misma
        // regla replicada aquí para el módulo autoservicio).
        if ($ok) {
            $activo = $this->query(
                "SELECT codigo FROM empresa_establecimiento
                  WHERE id_empresa = {$idEmpresa} AND eliminado = false AND LOWER(estado) = 'activo'
                  ORDER BY id ASC LIMIT 1"
            );
            if (!empty($activo)) {
                $this->execute(
                    "UPDATE empresas SET establecimiento = '" . $this->escape($activo[0]['codigo']) . "', updated_at = NOW()
                      WHERE id = {$idEmpresa}"
                );
            }
        }

        return $ok;
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
                       inv_requiere_aprobacion, inv_notificar_correo, inv_usuarios_aprobadores,
                       transf_requiere_aprobacion, transf_notificar_correo, transf_usuarios_aprobadores
                FROM empresa_establecimiento
                WHERE id = {$id} AND eliminado = false";
        $res = $this->query($sql);
        return $res[0] ?? null;
    }

    /**
     * Recargo por servicio del POS Restaurante (se emite como propina).
     *
     * Va aparte de getEstablecimientoConfig() a propósito: esa consulta la usan
     * la emisión de facturas y recibos, y si el código llegara al servidor antes
     * que la migración 20260819_servicio_restaurante_propina.sql, incluir aquí
     * columnas inexistentes tumbaría toda la facturación. Así, mientras falte la
     * migración, el servicio simplemente queda apagado.
     */
    public function getConfigServicioRestaurante(int $idEst): array
    {
        $default = [
            'servicio_restaurante'            => 'no',
            'servicio_restaurante_porcentaje' => 0.0,
            'mostrar_propina_factura'         => 'false',
        ];
        if (!$this->tieneColumnasServicioRestaurante()) {
            return $default;
        }
        $id = (int) $idEst;
        // mostrar_propina_factura viaja aquí porque es el interruptor maestro:
        // sin campo de propina en el comprobante no hay dónde emitir el recargo
        // (quien aplica esa regla es ComandaService::getConfigServicio).
        $res = $this->query("SELECT servicio_restaurante, servicio_restaurante_porcentaje, mostrar_propina_factura
                             FROM empresa_establecimiento
                             WHERE id = {$id} AND eliminado = false");
        return $res[0] ?? $default;
    }

    /** Cacheado por request: el esquema no cambia a media petición. */
    public function tieneColumnasServicioRestaurante(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $res = $this->query("SELECT 1 FROM information_schema.columns
                                     WHERE table_name = 'empresa_establecimiento'
                                       AND column_name = 'servicio_restaurante'");
                $existe = !empty($res);
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
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
            'servicio_restaurante', 'servicio_restaurante_porcentaje',
            'factura_agrupar_items', 'factura_item_mostrar_unidad',
            'factura_item_mostrar_lote', 'factura_item_mostrar_caducidad',
            'factura_item_mostrar_nup',
            'inv_requiere_aprobacion', 'inv_notificar_correo', 'inv_usuarios_aprobadores',
            'transf_requiere_aprobacion', 'transf_notificar_correo', 'transf_usuarios_aprobadores',
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

    /**
     * ¿Existe otro punto de emisión (no eliminado) con el mismo código en esta
     * empresa, sin contar $excluirId? Usado para permitir eliminar un punto que
     * ya tiene documentos SOLO cuando ese número de punto sigue existiendo en
     * algún otro establecimiento de la empresa — ver EmpresaService::deletePunto().
     */
    public function existeOtroPuntoConCodigo(int $idEmpresa, string $codigoPunto, int $excluirId): bool
    {
        $ide = (int) $idEmpresa;
        $id = (int) $excluirId;
        $cod = $this->escape(trim($codigoPunto));
        if ($cod === '') return false;
        $sql = "SELECT 1 FROM empresa_punto_emision
                 WHERE id_empresa = {$ide} AND TRIM(codigo_punto) = '{$cod}' AND eliminado = false AND id != {$id}";
        return !empty($this->query($sql));
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
        $idEmp = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $this->db->beginTransaction();
        try {
            $ok = $this->execute(
                "UPDATE empresa_punto_emision SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                 WHERE id = {$id} AND id_empresa = {$idEmp}"
            );

            // Da de baja también los tipos de secuencial configurados en este punto:
            // si no, quedan huérfanos (eliminado = false apuntando a un punto ya
            // eliminado) y, por ejemplo, un tipo de "único punto por empresa" (ver
            // SecuencialRepository::TIPOS_PUNTO_UNICO) seguiría bloqueado en
            // cualquier otro punto aunque el que lo tenía ya no exista.
            $this->execute(
                "UPDATE empresa_secuencial SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                 WHERE id_punto_emision = {$id} AND id_empresa = {$idEmp} AND eliminado = false"
            );

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
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

    /** Un secuencial configurado por su id, validando que pertenezca a la empresa. */
    public function getSecuencialById(int $id, int $idEmpresa): ?array
    {
        $id = (int) $id;
        $idEmp = (int) $idEmpresa;
        $res = $this->query(
            "SELECT id, id_punto_emision, tipo_documento, secuencial_inicial
               FROM empresa_secuencial
              WHERE id = {$id} AND id_empresa = {$idEmp} AND eliminado = false"
        );
        return $res[0] ?? null;
    }

    /** Eliminación lógica de un tipo de secuencial configurado en un punto de emisión. */
    public function deleteSecuencial(int $id, int $idEmpresa): bool
    {
        $id = (int) $id;
        $idEmp = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);
        $sql = "UPDATE empresa_secuencial
                   SET eliminado = true, deleted_at = NOW(), deleted_by = {$user},
                       updated_at = NOW(), updated_by = {$user}
                 WHERE id = {$id} AND id_empresa = {$idEmp} AND eliminado = false";
        return $this->execute($sql);
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

    public function updateUsaLiquidacionDiferidaIva(int $idEmpresa, bool $valor): bool
    {
        $id = (int) $idEmpresa;
        $val = $valor ? 'true' : 'false';
        return $this->execute("UPDATE empresas SET usa_liquidacion_diferida_iva = {$val} WHERE id = {$id}");
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
