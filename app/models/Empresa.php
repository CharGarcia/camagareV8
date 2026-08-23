<?php
/**
 * Modelo Empresa
 *
 * empresa_asignada: relaciona usuarios con empresas (id_usuario, id_empresa)
 * Define qué empresas puede ver/seleccionar cada usuario.
 */

declare(strict_types=1);

namespace App\models;

class Empresa extends BaseModel
{
    public const COLUMNAS_ORDEN = ['nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion', 'nombre_provincia', 'nombre_ciudad', 'estado'];

    /**
     * Verifica que la empresa siga habilitada (estado activo y no eliminada).
     * Usado para revalidar la empresa activa en sesión ante cada request.
     */
    public function estaActiva(int $idEmpresa): bool
    {
        $id = $this->escape((string) $idEmpresa);
        $sql = "SELECT 1 FROM empresas WHERE id = '{$id}' AND estado = '1' AND eliminado = false LIMIT 1";
        return !empty($this->query($sql));
    }

    public function getEmpresasAsignadas(int $idUsuario): array
    {
        $id = $this->escape((string) $idUsuario);
        $sql = "SELECT emp.id AS id_empresa, emp.ruc, emp.nombre, emp.nombre_comercial, emp.establecimiento
                FROM empresa_asignada emp_asi
                INNER JOIN empresas emp ON emp.id = emp_asi.id_empresa
                WHERE emp_asi.id_usuario = '{$id}' AND emp.estado = '1' AND emp.eliminado = false";
        return $this->query($sql);
    }

    /**
     * Todas las empresas activas del sistema, mismo shape que getEmpresasAsignadas()
     * (id_empresa, ruc, nombre, nombre_comercial, establecimiento). Solo para nivel 3
     * (superadmin): no depende de empresa_asignada porque ese nivel tiene acceso total
     * sin necesitar registro ahí.
     */
    public function getTodasActivas(): array
    {
        $sql = "SELECT id AS id_empresa, ruc, nombre, nombre_comercial, establecimiento
                FROM empresas
                WHERE estado = '1' AND eliminado = false
                ORDER BY nombre_comercial, nombre";
        return $this->query($sql);
    }

    /**
     * Lista empresas para el módulo empresas del sistema.
     * SuperAdmin: todas. Admin: solo las que tiene asignadas.
     */
    public function getTodosParaListado(int $idActual, int $nivel, string $buscar = '', int $page = 1, int $perPage = 20, string $ordenCol = 'nombre_comercial', string $ordenDir = 'ASC'): array
    {
        $offset = ($page - 1) * $perPage;
        $idActual = (int) $idActual;

        if (!in_array($ordenCol, self::COLUMNAS_ORDEN, true)) {
            $ordenCol = 'nombre_comercial';
        }
        $dir = strtoupper($ordenDir) === 'DESC' ? 'DESC' : 'ASC';
        $colMap = [
            'nombre_provincia' => 'p.nombre',
            'nombre_ciudad' => 'c.nombre',
        ];
        $col = $colMap[$ordenCol] ?? 'e.' . $ordenCol;

        if ($nivel >= 3) {
            $from = 'empresas e';
            $where = 'WHERE e.eliminado = false';
        } else {
            $from = 'empresa_asignada ea INNER JOIN empresas e ON e.id = ea.id_empresa';
            $where = "WHERE ea.id_usuario = {$idActual} AND e.eliminado = false";
        }
        $joinProv = 'LEFT JOIN provincia p ON p.codigo = e.cod_prov';
        $joinCiud = 'LEFT JOIN ciudad c ON c.codigo = e.cod_ciudad';

        if ($buscar !== '') {
            $b = $this->escape($buscar);
            $where .= " AND (e.nombre ILIKE '%{$b}%' OR e.nombre_comercial ILIKE '%{$b}%' OR e.ruc ILIKE '%{$b}%' OR e.establecimiento ILIKE '%{$b}%')";
        }

        $countSql = "SELECT COUNT(DISTINCT e.id) AS total FROM {$from} {$where}";
        $total = (int) ($this->query($countSql)[0]['total'] ?? 0);

        $sql = "SELECT DISTINCT e.id, e.nombre, e.nombre_comercial, e.ruc, e.establecimiento, e.direccion, e.telefono, e.mail,
                e.cod_prov, e.cod_ciudad, e.estado, e.valor_cobro, e.periodo_vigencia_desde, e.periodo_vigencia_hasta, e.estado_pago,
                e.obligado_contabilidad, COALESCE(e.max_usuarios, 3) AS max_usuarios,
                e.id_empresa_suscripciones, COALESCE(e.es_administradora_suscripciones, false) AS es_administradora_suscripciones,
                e.id_cliente_facturado,
                e.id_suscripcion,
                COALESCE(e.factura_operadora_transporte, 'false') AS factura_operadora_transporte,
                COALESCE(NULLIF(ctrl.nombre_comercial,''), ctrl.nombre) AS ctrl_nombre, ctrl.ruc AS ctrl_ruc, ctrl.establecimiento AS ctrl_estab,
                cli.nombre AS cli_nombre, cli.identificacion AS cli_identificacion,
                p.nombre AS nombre_provincia, c.nombre AS nombre_ciudad
            FROM {$from} {$joinProv} {$joinCiud}
                LEFT JOIN empresas ctrl ON ctrl.id = e.id_empresa_suscripciones
                LEFT JOIN clientes cli  ON cli.id = e.id_cliente_facturado
            {$where}
            ORDER BY {$col} {$dir}
            LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->query($sql);

        $empAsignada = new EmpresaAsignada();
        foreach ($rows as &$r) {
            $r['usuarios'] = $empAsignada->getUsuariosDeEmpresa((int) $r['id'], true);
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function getPorId(int $id): ?array
    {
        $id = (int) $id;
        if ($id <= 0) return null;
        $r = $this->query("SELECT * FROM empresas WHERE id = {$id}");
        return $r[0] ?? null;
    }

    /**
     * Lista simple de empresas activas para poblar selectores
     * (p. ej. "empresa que controla las suscripciones").
     */
    public function getListaParaSelect(): array
    {
        return $this->query(
            "SELECT id, nombre, nombre_comercial, ruc,
                    COALESCE(es_administradora_suscripciones, false) AS es_administradora_suscripciones
             FROM empresas
             WHERE eliminado = false
             ORDER BY nombre_comercial, nombre"
        );
    }

    /**
     * Id de la empresa administradora de suscripciones por defecto (o null).
     */
    public function getIdAdministradoraSuscripciones(): ?int
    {
        $r = $this->query(
            "SELECT id FROM empresas
             WHERE es_administradora_suscripciones = true AND eliminado = false
             ORDER BY id LIMIT 1"
        );
        return isset($r[0]['id']) ? (int) $r[0]['id'] : null;
    }

    /**
     * Extrae establecimiento de RUC: últimos 3 dígitos (000-999).
     */
    private function extraerEstablecimientoDeRuc(string $ruc): string
    {
        $digitos = preg_replace('/\D/', '', trim($ruc));
        $ultimos3 = substr($digitos, -3);
        return str_pad($ultimos3 ?: '0', 3, '0', STR_PAD_LEFT);
    }

    /**
     * Obtiene un establecimiento disponible para el RUC: empieza con los 3 últimos dígitos,
     * si (ruc, 001) existe prueba 002, 003, etc. hasta encontrar uno libre.
     */
    private function obtenerEstablecimientoDisponible(string $ruc, ?int $excluirId = null): string
    {
        $base = (int) $this->extraerEstablecimientoDeRuc($ruc);
        for ($i = 0; $i < 1000; $i++) {
            $est = sprintf('%03d', ($base + $i) % 1000);
            if (!$this->existePorRucYEstablecimiento($ruc, $est, $excluirId)) {
                return $est;
            }
        }
        throw new \InvalidArgumentException('No hay establecimientos disponibles para este RUC (000-999 ocupados).');
    }

    /**
     * Normaliza establecimiento a 3 dígitos (000-999). Retorna null si no es válido.
     */
    private function normalizarEstablecimiento(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') return null;
        if (preg_match('/^\d{1,3}$/', $valor)) {
            $n = (int) $valor;
            if ($n >= 0 && $n <= 999) {
                return sprintf('%03d', $n);
            }
        }
        return null;
    }

    /**
     * Verifica si ya existe la combinación (RUC, establecimiento).
     */
    public function existePorRucYEstablecimiento(string $ruc, string $establecimiento, ?int $excluirId = null): bool
    {
        $rucEsc = $this->escape(trim($ruc));
        $est = $this->escape(trim($establecimiento));
        if ($rucEsc === '' || $est === '') return false;
        $sql = "SELECT 1 FROM empresas WHERE ruc = '{$rucEsc}' AND establecimiento = '{$est}' AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != ' . (int) $excluirId;
        }
        return !empty($this->query($sql));
    }

    /** ¿Ya existe alguna otra empresa activa con este RUC? Usado para decidir si la nueva "nace" matriz del grupo. */
    public function existeOtraEmpresaConRuc(string $ruc): bool
    {
        $rucEsc = $this->escape(trim($ruc));
        if ($rucEsc === '') return false;
        $sql = "SELECT 1 FROM empresas WHERE ruc = '{$rucEsc}' AND eliminado = false LIMIT 1";
        return !empty($this->query($sql));
    }

    /**
     * Crear empresa. Retorna id o lanza excepción.
     */
    public function crear(array $data): int
    {
        $nombre = $this->escape(trim($data['nombre'] ?? ''));
        $nombreComercial = $this->escape(trim($data['nombre_comercial'] ?? ''));
        $ruc = $this->escape(trim($data['ruc'] ?? ''));
        $direccion = $this->escape(trim($data['direccion'] ?? ''));
        $telefono = $this->escape(trim($data['telefono'] ?? ''));
        $tipo = $this->escape(trim($data['tipo'] ?? '1'));
        $nomRepLegal = $this->escape(trim($data['nom_rep_legal'] ?? ''));
        $cedRepLegal = $this->escape(trim($data['ced_rep_legal'] ?? ''));
        $mail = $this->escape(trim($data['mail'] ?? ''));
        $codProv = $this->escape(trim($data['cod_prov'] ?? ''));
        $codCiudad = $this->escape(trim($data['cod_ciudad'] ?? ''));
        $nombreContador = $this->escape(trim($data['nombre_contador'] ?? ''));
        $rucContador = $this->escape(trim($data['ruc_contador'] ?? ''));
        $idUsuario = $this->escape(trim($data['id_usuario'] ?? '0'));

        if ($nombre === '' || $ruc === '') {
            throw new \InvalidArgumentException('Razón social y RUC son obligatorios.');
        }
        $establecimiento = trim($data['establecimiento'] ?? '');
        if ($establecimiento !== '') {
            $estNorm = $this->normalizarEstablecimiento($establecimiento);
            if ($estNorm === null) {
                throw new \InvalidArgumentException('Establecimiento debe ser de 3 dígitos (000 a 999).');
            }
            if ($this->existePorRucYEstablecimiento($ruc, $estNorm)) {
                throw new \InvalidArgumentException('Ya existe una empresa con el mismo RUC y establecimiento.');
            }
            $establecimiento = $estNorm;
        } else {
            $establecimiento = $this->obtenerEstablecimientoDisponible($ruc);
        }

        $valorCobro = isset($data['valor_cobro']) && $data['valor_cobro'] !== '' ? (float) $data['valor_cobro'] : null;
        $vigenciaDesde = !empty($data['periodo_vigencia_desde']) ? "'" . $this->escape($data['periodo_vigencia_desde']) . "'" : 'NULL';
        $vigenciaHasta = !empty($data['periodo_vigencia_hasta']) ? "'" . $this->escape($data['periodo_vigencia_hasta']) . "'" : 'NULL';
        $estadoPago = !empty($data['estado_pago']) ? "'" . $this->escape($data['estado_pago']) . "'" : "'pendiente'";
        $valCobroSql = $valorCobro !== null ? (string) $valorCobro : 'NULL';
        $estado = (trim($data['estado'] ?? '1') === '0') ? '0' : '1';
        $maxUsuarios = isset($data['max_usuarios']) && (int) $data['max_usuarios'] > 0 ? (int) $data['max_usuarios'] : 3;

        $estEsc = $this->escape($establecimiento);
        $obligadoCont = strtoupper(trim($data['obligado_contabilidad'] ?? 'NO')) === 'SI' ? 'SI' : 'NO';

        // "Nace" matriz del grupo RUC solo si es la primera empresa activa con este RUC en el
        // sistema; si ya hay otra (matriz o no), la nueva no reclama el flag automáticamente
        // — cambiar la matriz de un grupo existente es una acción explícita del usuario
        // (EmpresaService::marcarEstablecimientoMatriz()), no un efecto secundario de alta.
        $esMatrizSql = $this->existeOtraEmpresaConRuc($ruc) ? 'false' : 'true';

        // Empresa que controla la suscripción (FK lógica a empresas.id) y flag de administradora.
        $idEmpSusc = isset($data['id_empresa_suscripciones']) && $data['id_empresa_suscripciones'] !== '' && (int) $data['id_empresa_suscripciones'] > 0
            ? (int) $data['id_empresa_suscripciones'] : null;
        $idEmpSuscSql = $idEmpSusc !== null ? (string) $idEmpSusc : 'NULL';
        $esAdminSusc = $this->esValorVerdadero($data['es_administradora_suscripciones'] ?? null);

        // Solo una empresa puede ser administradora por defecto.
        if ($esAdminSusc) {
            $this->execute("UPDATE empresas SET es_administradora_suscripciones = false WHERE es_administradora_suscripciones = true");
        }
        $esAdminSql = $esAdminSusc ? 'true' : 'false';

        // Empresa a la que facturamos la suscripción (reventa): relaciona por esa empresa, no por RUC propio.
        $idEmpFact = isset($data['id_cliente_facturado']) && $data['id_cliente_facturado'] !== '' && (int) $data['id_cliente_facturado'] > 0
            ? (int) $data['id_cliente_facturado'] : null;
        $idEmpFactSql = $idEmpFact !== null ? (string) $idEmpFact : 'NULL';

        // Suscripción específica que cubre a esta empresa (reventa con varias suscripciones).
        $idSusc = isset($data['id_suscripcion']) && $data['id_suscripcion'] !== '' && (int) $data['id_suscripcion'] > 0
            ? (int) $data['id_suscripcion'] : null;
        $idSuscSql = $idSusc !== null ? (string) $idSusc : 'NULL';

        // Operadora de transporte comercial, excepto taxis (Ficha SRI v2.34, Anexo 25):
        // su factura exige la placa del vehículo en XML y PDF.
        $operadoraTransporte = $this->esValorVerdadero($data['factura_operadora_transporte'] ?? null) ? 'true' : 'false';

        // Régimen SRI por defecto = General (si no se especifica uno). No se deja NULL
        // ni se depende del default de la columna (varía entre esquemas).
        $idRegimen = isset($data['id_tipo_regimen']) && (int) $data['id_tipo_regimen'] > 0
            ? (int) $data['id_tipo_regimen']
            : $this->idRegimenGeneral();
        $idRegimenSql = $idRegimen > 0 ? (string) $idRegimen : 'NULL';

        // Agente de retención: número de resolución del SRI (solo dígitos, máx. 8) o
        // '' si no aplica. Vacío por defecto (NO se hereda ningún "No" de la columna).
        $agenteRet    = substr(preg_replace('/\D+/', '', (string) ($data['agente_retencion'] ?? '')), 0, 8);
        $agenteRetEsc = $this->escape($agenteRet);

        $sql = "INSERT INTO empresas (nombre, nombre_comercial, ruc, establecimiento, direccion, telefono, tipo, nom_rep_legal, ced_rep_legal, mail, cod_prov, cod_ciudad, estado, fecha_agregado, id_usuario, nombre_contador, ruc_contador, valor_cobro, periodo_vigencia_desde, periodo_vigencia_hasta, estado_pago, obligado_contabilidad, max_usuarios, id_empresa_suscripciones, es_administradora_suscripciones, id_cliente_facturado, id_suscripcion, factura_operadora_transporte, es_matriz, id_tipo_regimen, agente_retencion)
            VALUES ('{$nombre}', '{$nombreComercial}', '{$ruc}', '{$estEsc}', '{$direccion}', '{$telefono}', '{$tipo}', '{$nomRepLegal}', '{$cedRepLegal}', '{$mail}', '{$codProv}', '{$codCiudad}', '{$estado}', NOW(), '{$idUsuario}', '{$nombreContador}', '{$rucContador}', {$valCobroSql}, {$vigenciaDesde}, {$vigenciaHasta}, {$estadoPago}, '{$obligadoCont}', {$maxUsuarios}, {$idEmpSuscSql}, {$esAdminSql}, {$idEmpFactSql}, {$idSuscSql}, '{$operadoraTransporte}', {$esMatrizSql}, {$idRegimenSql}, '{$agenteRetEsc}')";
        $this->execute($sql);
        $id = $this->lastInsertId('empresas_id_seq');

        // Insertar establecimiento por defecto en la nueva tabla
        $estCod = $this->escape($establecimiento);
        $estNom = ($nombreComercial !== '') ? $nombreComercial : $nombre;
        $estTipo = ($establecimiento === '001') ? 'Matriz' : 'Sucursal';
        $sqlEst = "INSERT INTO empresa_establecimiento (id_empresa, codigo, nombre, direccion, tipo, estado, created_by, created_at, eliminado)
                   VALUES ({$id}, '{$estCod}', '{$estNom}', '{$direccion}', '{$estTipo}', 'activo', {$idUsuario}, NOW(), false)";
        $this->execute($sqlEst);

        // Crear bodega Central por defecto
        $sqlBod = "INSERT INTO bodegas (id_empresa, id_usuario, nombre, status, created_by, updated_by, created_at, updated_at, eliminado)
                   VALUES ({$id}, {$idUsuario}, 'Central', true, {$idUsuario}, {$idUsuario}, NOW(), NOW(), false)";
        $this->execute($sqlBod);

        return $id;
    }

    /**
     * Id del régimen "General" del catálogo tipo_regimen. La PK del catálogo es
     * id_tipo_regimen o id según la versión del esquema; se prueba primero por
     * código '1' y luego por nombre. Fallback 1 (General suele ser el primero).
     */
    private function idRegimenGeneral(): int
    {
        foreach (['id_tipo_regimen', 'id'] as $pk) {
            try {
                $r = $this->query("SELECT {$pk} AS id FROM tipo_regimen WHERE codigo = '1' OR LOWER(nombre) = 'general' ORDER BY {$pk} LIMIT 1");
                if (!empty($r[0]['id'])) {
                    return (int) $r[0]['id'];
                }
            } catch (\Throwable $e) {
                // Esquema con otra PK: probar la siguiente.
            }
        }
        return 1;
    }

    /**
     * Actualizar empresa.
     */
    public function actualizar(int $id, array $data): bool
    {
        $id = (int) $id;
        if ($id <= 0) return false;

        $establecimiento = trim($data['establecimiento'] ?? '');
        $ruc = trim($data['ruc'] ?? '');

        // Obtener datos actuales para verificar establecimiento
        $actual = $this->getPorId($id);
        $estActual = $actual['establecimiento'] ?? '';

        if ($establecimiento !== '' && $ruc !== '') {
            $estNorm = $this->normalizarEstablecimiento($establecimiento);
            if ($estNorm === null) {
                throw new \InvalidArgumentException('Establecimiento debe ser de 3 dígitos (000 a 999).');
            }
            
            // El establecimiento (incluido el 001/matriz) sí es editable; solo se valida
            // el formato y que no se repita la combinación RUC + establecimiento.
            if ($this->existePorRucYEstablecimiento($ruc, $estNorm, $id)) {
                throw new \InvalidArgumentException('Ya existe una empresa con el mismo RUC y establecimiento.');
            }
            $data['establecimiento'] = $estNorm;
        } elseif ($ruc !== '') {
            $data['establecimiento'] = ($estActual !== '') ? $estActual : $this->obtenerEstablecimientoDisponible($ruc, $id);
        }

        // Si se marca como administradora por defecto, desmarcar a las demás.
        if (array_key_exists('es_administradora_suscripciones', $data) && $this->esValorVerdadero($data['es_administradora_suscripciones'])) {
            $this->execute("UPDATE empresas SET es_administradora_suscripciones = false WHERE es_administradora_suscripciones = true AND id != {$id}");
        }

        $sets = [];
        $campos = ['nombre', 'nombre_comercial', 'ruc', 'establecimiento', 'direccion', 'telefono', 'mail', 'nom_rep_legal', 'ced_rep_legal', 'cod_prov', 'cod_ciudad', 'nombre_contador', 'ruc_contador', 'estado', 'valor_cobro', 'periodo_vigencia_desde', 'periodo_vigencia_hasta', 'estado_pago', 'obligado_contabilidad', 'max_usuarios', 'id_empresa_suscripciones', 'es_administradora_suscripciones', 'id_cliente_facturado', 'id_suscripcion', 'factura_operadora_transporte'];
        foreach ($campos as $c) {
            if (array_key_exists($c, $data)) {
                if (in_array($c, ['valor_cobro'], true)) {
                    $v = $data[$c];
                    $sets[] = "{$c} = " . ($v === '' || $v === null ? 'NULL' : (float) $v);
                } elseif ($c === 'max_usuarios') {
                    $v = (int) ($data[$c] ?? 3);
                    $sets[] = "{$c} = " . ($v > 0 ? $v : 3);
                } elseif (in_array($c, ['id_empresa_suscripciones', 'id_cliente_facturado', 'id_suscripcion'], true)) {
                    $v = $data[$c];
                    $sets[] = "{$c} = " . ($v === '' || $v === null || (int) $v <= 0 ? 'NULL' : (int) $v);
                } elseif ($c === 'es_administradora_suscripciones') {
                    $sets[] = "{$c} = " . ($this->esValorVerdadero($data[$c]) ? 'true' : 'false');
                } elseif (in_array($c, ['periodo_vigencia_desde', 'periodo_vigencia_hasta'], true)) {
                    $v = trim($data[$c] ?? '');
                    $sets[] = "{$c} = " . ($v === '' ? 'NULL' : "'" . $this->escape($v) . "'");
                } elseif ($c === 'obligado_contabilidad') {
                    $v = strtoupper(trim((string) ($data[$c] ?? 'NO'))) === 'SI' ? 'SI' : 'NO';
                    $sets[] = "{$c} = '" . $v . "'";
                } else {
                    $sets[] = "{$c} = '" . $this->escape(trim((string) $data[$c])) . "'";
                }
            }
        }
        if (empty($sets)) return true;
        $sql = 'UPDATE empresas SET ' . implode(', ', $sets) . ' WHERE id = ' . $id;
        return $this->execute($sql);
    }

    /**
     * Normaliza distintas representaciones de "verdadero" (checkbox, texto, número).
     */
    private function esValorVerdadero($v): bool
    {
        if (is_bool($v)) return $v;
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'si', 'sí', 'on', 't', 'yes'], true);
    }

    /**
     * Decenas de controladores (Factura de Venta, Ingresos, Egresos, Pedidos,
     * etc.) usan $establecimientos[0] como "el establecimiento con el que
     * opera esta empresa" — p. ej. para armar el combo de Serie/punto de
     * emisión. Prioriza el que está `estado = 'activo'` (solo puede haber uno
     * por empresa, ver EmpresaRepository::updateEstablecimiento()): un
     * establecimiento inactivo que ordene antes por código (p. ej. un "001"
     * viejo/huérfano) no debe ganarle al que la empresa realmente usa — eso
     * dejaba el combo de Serie vacío (el establecimiento activo real, con sus
     * puntos y secuenciales, quedaba ignorado). Si ninguno está activo, cae a
     * codigo ASC como antes.
     */
    public function getEstablecimientos(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query(
            "SELECT * FROM empresa_establecimiento
              WHERE id_empresa = {$id} AND eliminado = false
              ORDER BY CASE WHEN LOWER(estado) = 'activo' THEN 0 ELSE 1 END, codigo ASC"
        );
    }

    /** Un establecimiento por su id (incluye id_empresa, para validar acceso antes de actuar sobre él). */
    public function getEstablecimientoById(int $id): ?array
    {
        $id = (int) $id;
        $res = $this->query("SELECT * FROM empresa_establecimiento WHERE id = {$id} AND eliminado = false");
        return $res[0] ?? null;
    }

    public function getBodegas(int $idEmpresa): array
    {
        $id = (int) $idEmpresa;
        return $this->query("SELECT * FROM bodegas WHERE id_empresa = {$id} AND eliminado = false ORDER BY nombre ASC");
    }

    /**
     * Puntos de emisión de un establecimiento.
     * Por defecto SOLO los activos: es lo que se usa al emitir documentos (elegir el
     * punto para numerar). Un punto inactivo no debe ofrecerse para nuevos documentos.
     * Pasar $soloActivos=false para obtener también los inactivos (gestión).
     */
    public function getPuntosEmision(int $idEstablecimiento, bool $soloActivos = true): array
    {
        $id = (int) $idEstablecimiento;
        $filtroEstado = $soloActivos ? " AND LOWER(p.estado) = 'activo'" : '';
        $sql = "SELECT p.*, e.codigo as cod_establecimiento, e.direccion as direccion_establecimiento
                FROM empresa_punto_emision p
                JOIN empresa_establecimiento e ON e.id = p.id_establecimiento
                WHERE p.id_establecimiento = {$id}
                  AND p.eliminado = false
                  AND e.eliminado = false
                  {$filtroEstado}
                ORDER BY p.codigo_punto ASC";
        return $this->query($sql);
    }

    /**
     * ¿Ya existe otro establecimiento con ese código en la misma empresa?
     * $excluirId permite ignorar el propio registro al actualizar.
     */
    public function existeCodigoEstablecimiento(int $idEmpresa, string $codigo, ?int $excluirId = null): bool
    {
        $idEmp = (int) $idEmpresa;
        $cod   = $this->escape(trim($codigo));
        if ($idEmp <= 0 || $cod === '') return false;
        $sql = "SELECT 1 FROM empresa_establecimiento
                WHERE id_empresa = {$idEmp} AND TRIM(codigo) = '{$cod}' AND eliminado = false";
        if ($excluirId !== null && $excluirId > 0) {
            $sql .= ' AND id != ' . (int) $excluirId;
        }
        return !empty($this->query($sql));
    }

    public function actualizarEstablecimiento(int $id, array $data): bool
    {
        $id = (int) $id;

        $cur = $this->query("SELECT id_empresa FROM empresa_establecimiento WHERE id = {$id}");
        $idEmp = (int) ($cur[0]['id_empresa'] ?? 0);

        // El establecimiento matriz también es editable (código, tipo y estado).
        // Se valida formato del código y que no se repita dentro de la misma empresa.
        if (isset($data['codigo'])) {
            $cod = $this->normalizarEstablecimiento((string) $data['codigo']);
            if ($cod === null) {
                throw new \InvalidArgumentException('El código del establecimiento debe ser de 3 dígitos (000 a 999).');
            }
            $data['codigo'] = $cod;

            if ($idEmp > 0 && $this->existeCodigoEstablecimiento($idEmp, $cod, $id)) {
                throw new \InvalidArgumentException("Ya existe otro establecimiento con el código {$cod} en esta empresa.");
            }
        }

        $sets = [];
        $campos = ['nombre', 'codigo', 'direccion', 'tipo', 'estado'];
        foreach ($campos as $c) {
            if (isset($data[$c])) {
                $sets[] = "{$c} = '" . $this->escape(trim((string) $data[$c])) . "'";
            }
        }
        if (empty($sets)) return true;
        $sql = 'UPDATE empresa_establecimiento SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ' . $id;
        $ok = $this->execute($sql);

        // Un solo establecimiento activo por empresa: en la práctica cada empresa
        // opera con uno solo a la vez (nunca dos sucursales activas al mismo
        // tiempo), así que activar este desactiva automáticamente los demás. Es
        // justamente el que se muestra en el módulo Empresa (autoservicio),
        // pestaña Establecimientos — ver EmpresaRepository::getEstablecimientos().
        if ($ok && $idEmp > 0 && isset($data['estado']) && strtolower(trim((string) $data['estado'])) === 'activo') {
            $this->execute(
                "UPDATE empresa_establecimiento SET estado = 'inactivo', updated_at = NOW()
                 WHERE id_empresa = {$idEmp} AND id != {$id} AND eliminado = false"
            );
        }

        return $ok;
    }

    /**
     * Elimina (lógicamente) un establecimiento de la empresa. Bloquea si:
     * - es el matriz (código 001 o tipo Matriz) — nunca se elimina;
     * - es el único establecimiento activo de la empresa — siempre debe quedar
     *   al menos uno disponible;
     * - alguno de sus puntos de emisión ya tiene documentos emitidos.
     * Si pasa las validaciones, también da de baja sus puntos de emisión y los
     * secuenciales configurados en ellos, para no dejar huérfanos (mismo
     * criterio que EmpresaRepository::deletePuntoEmision(), ver docs/manual).
     */
    public function deleteEstablecimiento(int $id, int $idEmpresa): bool
    {
        $id = (int) $id;
        $idEmp = (int) $idEmpresa;
        $user = (int) ($_SESSION['id_usuario'] ?? 0);

        $actual = $this->query("SELECT codigo, tipo FROM empresa_establecimiento WHERE id = {$id} AND id_empresa = {$idEmp} AND eliminado = false");
        if (empty($actual)) {
            throw new \InvalidArgumentException('El establecimiento no existe o no pertenece a esta empresa.');
        }
        $est = $actual[0];
        if (trim((string) $est['codigo']) === '001' || strtolower((string) $est['tipo']) === 'matriz') {
            throw new \InvalidArgumentException('El establecimiento matriz no puede ser eliminado.');
        }

        $totalActivos = $this->query("SELECT COUNT(*) AS n FROM empresa_establecimiento WHERE id_empresa = {$idEmp} AND eliminado = false");
        if ((int) ($totalActivos[0]['n'] ?? 0) <= 1) {
            throw new \InvalidArgumentException('No se puede eliminar: la empresa debe tener siempre al menos un establecimiento disponible.');
        }

        $puntos = $this->query("SELECT id, codigo_punto FROM empresa_punto_emision WHERE id_establecimiento = {$id} AND eliminado = false");
        $secRepo = new \App\repositories\modulos\EmpresaRepository();
        $usos = [];
        foreach ($puntos as $p) {
            foreach ($secRepo->puntoEmisionEnUso((int) $p['id'], $idEmp) as $u) {
                $usos[] = 'punto ' . $p['codigo_punto'] . ': ' . $u;
            }
        }
        if (!empty($usos)) {
            throw new \InvalidArgumentException(
                'No se puede eliminar este establecimiento: ya tiene documentos emitidos (' . implode(', ', $usos) . ').'
            );
        }

        $this->db->beginTransaction();
        try {
            foreach ($puntos as $p) {
                $idPunto = (int) $p['id'];
                $this->execute(
                    "UPDATE empresa_secuencial SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                     WHERE id_punto_emision = {$idPunto} AND eliminado = false"
                );
                $this->execute(
                    "UPDATE empresa_punto_emision SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                     WHERE id = {$idPunto}"
                );
            }
            $ok = $this->execute(
                "UPDATE empresa_establecimiento SET eliminado = true, deleted_at = NOW(), deleted_by = {$user}
                 WHERE id = {$id} AND id_empresa = {$idEmp}"
            );
            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idUsuario): bool
    {
        $id = (int) $id;
        if ($this->esUsada($id)) {
            throw new \Exception('No se puede eliminar la empresa porque ya tiene registros asociados (clientes, facturas, etc).');
        }
        
        // 1. Eliminar puntos de emisión relacionados
        $sqlPuntos = "UPDATE empresa_punto_emision SET eliminado = true, deleted_at = NOW(), deleted_by = {$idUsuario} WHERE id_empresa = {$id}";
        $this->execute($sqlPuntos);

        // 2. Eliminar establecimientos relacionados
        $sqlEst = "UPDATE empresa_establecimiento SET eliminado = true, deleted_at = NOW(), deleted_by = {$idUsuario} WHERE id_empresa = {$id}";
        $this->execute($sqlEst);

        // 3. Eliminar empresa asignada (accesos)
        $sqlAsig = "DELETE FROM empresa_asignada WHERE id_empresa = {$id}"; // Esta tabla sí se puede limpiar físicamente ya que es solo de permisos
        $this->execute($sqlAsig);

        // 4. Eliminar empresa
        $sql = "UPDATE empresas SET eliminado = true, deleted_at = NOW(), deleted_by = {$idUsuario} WHERE id = {$id}";
        return $this->execute($sql);
    }

    public function esUsada(int $id): bool
    {
        $id = (int) $id;
        
        // Tablas críticas a revisar
        $tablas = ['clientes', 'proveedores', 'productos', 'compras', 'ventas', 'facturas_emisores'];
        
        foreach ($tablas as $t) {
            // Verificar si la tabla existe antes de consultar
            $checkTable = $this->query("SELECT 1 FROM information_schema.tables WHERE table_name = '{$t}'");
            if (empty($checkTable)) continue;

            $res = $this->query("SELECT 1 FROM {$t} WHERE id_empresa = {$id} AND eliminado = false LIMIT 1");
            if (!empty($res)) return true;
        }
        
        return false;
    }
}
