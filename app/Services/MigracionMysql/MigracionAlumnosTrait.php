<?php

declare(strict_types=1);

namespace App\Services\MigracionMysql;

use App\core\Database;
use PDO;
use Throwable;

/**
 * Migración del módulo Alumnos del sistema anterior (MySQL) al nuevo.
 *
 * Tablas viejas (ver sistema56/sistema/ajax: nuevo_alumno, editar_alumno,
 * detalle_factura_alumno, generar_factura_alumno):
 *  - campus_alumnos (id_campus, nombre_campus)            → alumnos_campus
 *  - nivel_alumnos  (id_nivel, nombre_nivel)              → alumnos_niveles
 *  - alumnos: SOLO estado_alumno = '1' (activos). sucursal_alumno = id del campus,
 *    paralelo_alumno = id del nivel, horario_alumno = id de horarios_alumnos
 *    (texto libre tipo «8h30 a 14h00»), id_cliente = representante que factura,
 *    serie_facturar = «001-001», nombres_apellidos en UN campo (nombres primero).
 *  - detalle_por_facturar (id_referencia = id_alumno; los que empiezan con
 *    'CLIENTE' son de facturas programadas, no de alumnos): producto, cantidad,
 *    precio pactado y descuento fijo en $ por línea → alumnos_servicios.
 *
 * Todo idempotente vía migracion_mysql_map (entidades alumnos_campus,
 * alumnos_niveles, alumnos). Depende de Clientes y Productos ya migrados para
 * enlazar el representante y los servicios.
 */
trait MigracionAlumnosTrait
{
    private function migrarAlumnosCatalogo(string $entidad, int $idEmpresa, string $ruc, int $idUsuario): array
    {
        [$tablaVieja, $colId, $colNombre, $tablaNueva] = $entidad === 'alumnos_campus'
            ? ['campus_alumnos', 'id_campus', 'nombre_campus', 'alumnos_campus']
            : ['nivel_alumnos', 'id_nivel', 'nombre_nivel', 'alumnos_niveles'];

        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => $entidad, 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [],
                'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'sin nombre', 'errores' => 0];
        $done   = $this->idsMigrados($pg, $idEmpresa, $entidad);
        $insMap = $this->stmtMap($pg, $entidad);

        // Mismo criterio que el índice único (id_empresa, UPPER(nombre)) WHERE eliminado = false.
        $buscar = $pg->prepare("SELECT id FROM {$tablaNueva} WHERE id_empresa = ? AND UPPER(TRIM(nombre)) = UPPER(?) AND eliminado = false ORDER BY id LIMIT 1");
        $insert = $entidad === 'alumnos_campus'
            ? $pg->prepare("INSERT INTO alumnos_campus (id_empresa, nombre, estado, created_by, updated_by) VALUES (?, ?, 'activo', ?, ?) RETURNING id")
            : $pg->prepare("INSERT INTO alumnos_niveles (id_empresa, nombre, orden, estado, created_by, updated_by) VALUES (?, ?, ?, 'activo', ?, ?) RETURNING id");
        $orden = (int) $pg->query("SELECT COALESCE(MAX(orden), 0) FROM alumnos_niveles WHERE id_empresa = " . (int) $idEmpresa)->fetchColumn();

        $q = "SELECT `$colId` AS id, `$colNombre` AS nombre FROM `$tablaVieja` WHERE ruc_empresa LIKE " . $mysql->quote($base . '%')
            . $this->clausulaEstabOrigen('ruc_empresa', $base, $mysql) . " ORDER BY `$colId`";
        foreach ($mysql->query($q) as $r) {
            $res['total']++;
            $old = (int) $r['id'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }
            $nombre = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $r['nombre'])), 0, 150);
            if ($nombre === '') { $res['omitidos']++; continue; }
            try {
                $buscar->execute([$idEmpresa, $nombre]);
                $existente = $buscar->fetchColumn();
                if ($existente) {
                    // Nombre repetido (en el viejo hay duplicados, o ya se creó a mano): se vincula.
                    $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, (int) $existente, $nombre, $idUsuario);
                    continue;
                }
                if ($entidad === 'alumnos_campus') {
                    $insert->execute([$idEmpresa, $nombre, $idUsuario, $idUsuario]);
                } else {
                    $insert->execute([$idEmpresa, $nombre, ++$orden, $idUsuario, $idUsuario]);
                }
                $nuevo = (int) $insert->fetchColumn();
                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $nuevo, ':cn' => mb_substr($nombre, 0, 120), ':vin' => 'f', ':cb' => $idUsuario]);
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($ex->getMessage(), 0, 160); }
            }
        }
        return $res;
    }

    /**
     * «NOMBRES APELLIDOS» (un solo campo en el viejo, nombres primero) → [nombres, apellidos].
     * Las partículas (DE, DEL, LA…) se pegan a la palabra siguiente para no partir
     * apellidos compuestos («DE LA TORRE»). Con 3+ bloques, los 2 últimos son apellidos.
     */
    public static function separarNombresApellidos(string $completo): array
    {
        $particulas = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'SAN', 'SANTA', 'Y', 'VAN', 'VON', 'DA', 'DI'];
        $bloques = [];
        $pend = [];
        foreach (preg_split('/\s+/u', trim($completo), -1, PREG_SPLIT_NO_EMPTY) as $pal) {
            $pend[] = $pal;
            if (!in_array(mb_strtoupper($pal), $particulas, true)) {
                $bloques[] = implode(' ', $pend);
                $pend = [];
            }
        }
        if ($pend) { $bloques[] = implode(' ', $pend); }
        $n = count($bloques);
        if ($n === 0) { return ['', '']; }
        if ($n === 1) { return [$bloques[0], '']; }
        $nAp = $n >= 3 ? 2 : 1;
        return [implode(' ', array_slice($bloques, 0, $n - $nAp)), implode(' ', array_slice($bloques, $n - $nAp))];
    }

    /**
     * «8h30 a 14h00», «08:00 - 13:00», «7h a 12h30» → ['08:30', '14:00'] o null si no se entiende.
     */
    public static function parsearHorario(string $texto): ?array
    {
        if (!preg_match_all('/(\d{1,2})\s*(?:[h:.]\s*(\d{2})?|hs?\b)/iu', $texto, $m, PREG_SET_ORDER) || count($m) < 2) {
            return null;
        }
        $hora = static function (array $x): ?string {
            $h = (int) $x[1];
            $mi = isset($x[2]) && $x[2] !== '' ? (int) $x[2] : 0;
            return ($h <= 23 && $mi <= 59) ? sprintf('%02d:%02d', $h, $mi) : null;
        };
        $ini = $hora($m[0]);
        $fin = $hora($m[1]);
        if ($ini === null || $fin === null || $fin <= $ini) { return null; }
        return [$ini, $fin];
    }

    private function migrarAlumnos(int $idEmpresa, string $ruc, int $idUsuario): array
    {
        $base  = substr(preg_replace('/\D+/', '', $ruc), 0, 10);
        $mysql = LegacyMysqlConnection::get();
        $pg    = Database::getConnection();
        $res = ['entidad' => 'alumnos', 'total' => 0, 'migrados' => 0, 'vinculados' => 0, 'vinculados_muestra' => [],
                'ya_migrados' => 0, 'omitidos' => 0, 'omitidos_motivo' => 'alumno sin nombre, o sin cliente (representante) y sin Consumidor Final en la empresa', 'errores' => 0,
                'sin_cliente' => 0, 'sin_cliente_muestra' => [], 'servicios' => 0, 'servicios_sin_producto' => 0,
                'horarios' => 0, 'horarios_texto' => 0, 'periodos' => 0];

        $done       = $this->idsMigrados($pg, $idEmpresa, 'alumnos');
        $insMap     = $this->stmtMap($pg, 'alumnos');
        $mapCliente = $this->mapaDe($pg, $idEmpresa, 'clientes');
        $cliIdent   = $this->clientesPorIdentificacion($pg, $idEmpresa);
        $mapProd    = $this->mapaDe($pg, $idEmpresa, 'productos');
        $mapCampus  = $this->mapaDe($pg, $idEmpresa, 'alumnos_campus');
        $mapNivel   = $this->mapaDe($pg, $idEmpresa, 'alumnos_niveles');
        $idCF       = $cliIdent['9999999999999'] ?? null; // Consumidor Final: respaldo si no hay representante

        // alumnos_servicios.descuento llega con 20260924_alumnos_facturacion.sql.
        $conDescuento = (bool) $pg->query("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'alumnos_servicios' AND column_name = 'descuento')")->fetchColumn();

        // Serie «001-001» del viejo → punto de emisión de la empresa.
        $series = [];
        foreach ($pg->query("SELECT pe.id, e.codigo, pe.codigo_punto FROM empresa_punto_emision pe
                               JOIN empresa_establecimiento e ON e.id = pe.id_establecimiento
                              WHERE e.id_empresa = " . (int) $idEmpresa . " AND pe.eliminado = false AND e.eliminado = false") as $s) {
            $series[str_pad((string) $s['codigo'], 3, '0', STR_PAD_LEFT) . '-' . str_pad((string) $s['codigo_punto'], 3, '0', STR_PAD_LEFT)] = (int) $s['id'];
        }

        $buscarAlumno = $pg->prepare("SELECT id FROM alumnos WHERE id_empresa = ? AND numero_identificacion = ? AND eliminado = false ORDER BY id LIMIT 1");
        $insAlumno = $pg->prepare("INSERT INTO alumnos (id_empresa, nombres, apellidos, tipo_identificacion, numero_identificacion, fecha_nacimiento, sexo,
                                         estado_academico, id_cliente, id_punto_emision, observaciones, created_by, updated_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?, ?, ?) RETURNING id");
        $insPeriodo = $pg->prepare("INSERT INTO alumnos_periodos (id_alumno, id_empresa, id_campus, id_nivel, fecha_ingreso, estado, observacion, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, 'activo', 'Migrado del sistema anterior', ?, ?)");
        $insHorario = $pg->prepare("INSERT INTO alumnos_horarios (id_alumno, id_empresa, dia_semana, hora_inicio, hora_fin, jornada, observacion, created_by, updated_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insServ = $conDescuento
            ? $pg->prepare("INSERT INTO alumnos_servicios (id_alumno, id_empresa, id_producto, cantidad_default, precio_override, frecuencia, activo, descuento, created_by, updated_by)
                            VALUES (?, ?, ?, ?, ?, 'mensual', true, ?, ?, ?)")
            : $pg->prepare("INSERT INTO alumnos_servicios (id_alumno, id_empresa, id_producto, cantidad_default, precio_override, frecuencia, activo, created_by, updated_by)
                            VALUES (?, ?, ?, ?, ?, 'mensual', true, ?, ?)");

        // Servicios por alumno: id_referencia = id_alumno (las filas 'CLIENTE…' son de facturas programadas).
        $stServ = $mysql->prepare("SELECT * FROM detalle_por_facturar WHERE ruc_empresa LIKE :b AND id_referencia = :ref ORDER BY id_detalle_pf");

        $q = "SELECT a.*, c.ruc AS cliente_ruc, h.nombre_horario
                FROM alumnos a
                LEFT JOIN clientes c ON c.id = a.id_cliente
                LEFT JOIN horarios_alumnos h ON h.id_horario = a.horario_alumno
               WHERE a.ruc_empresa LIKE " . $mysql->quote($base . '%') . $this->clausulaEstabOrigen('a.ruc_empresa', $base, $mysql) . "
                 AND a.estado_alumno = '1'
               ORDER BY a.id_alumno";

        foreach ($mysql->query($q) as $r) {
            $res['total']++;
            $old = (int) $r['id_alumno'];
            if (isset($done[(string) $old])) { $res['ya_migrados']++; continue; }

            // El viejo pasó de dos columnas (nombres_alumno/apellidos_alumno) a una (nombres_apellidos).
            if (array_key_exists('nombres_apellidos', $r)) {
                [$nombres, $apellidos] = self::separarNombresApellidos(mb_strtoupper(trim((string) $r['nombres_apellidos'])));
            } else {
                $nombres   = mb_strtoupper(trim((string) ($r['nombres_alumno'] ?? '')));
                $apellidos = mb_strtoupper(trim((string) ($r['apellidos_alumno'] ?? '')));
            }
            $nombres   = mb_substr(preg_replace('/\s+/u', ' ', $nombres), 0, 150);
            $apellidos = mb_substr(preg_replace('/\s+/u', ' ', $apellidos), 0, 150);
            if ($nombres === '' && $apellidos === '') { $res['omitidos']++; continue; }
            $nombreCompleto = trim($apellidos . ' ' . $nombres);

            $ident = mb_substr(preg_replace('/\s+/', '', (string) ($r['cedula_alumno'] ?? '')), 0, 20);
            try {
                if ($ident !== '') {
                    $buscarAlumno->execute([$idEmpresa, $ident]);
                    $existente = $buscarAlumno->fetchColumn();
                    if ($existente) {
                        $this->marcarVinculado($res, $done, $pg, $idEmpresa, $old, (int) $existente, $ident . ' ' . $nombreCompleto, $idUsuario);
                        continue;
                    }
                }

                // Representante que factura: mapa de clientes migrados → por identificación → Consumidor Final.
                $idCli = null;
                if ((int) ($r['id_cliente'] ?? 0) > 0) {
                    $idCli = $mapCliente[(string) (int) $r['id_cliente']] ?? null;
                    if (!$idCli && trim((string) ($r['cliente_ruc'] ?? '')) !== '') {
                        $idCli = $cliIdent[trim((string) $r['cliente_ruc'])] ?? null;
                    }
                }
                $obs = [];
                if (!$idCli) {
                    if (!$idCF) { $res['omitidos']++; continue; }
                    $idCli = $idCF;
                    $obs[] = 'Sin representante en el sistema anterior: asigne el cliente que factura.';
                    $res['sin_cliente']++;
                    if (count($res['sin_cliente_muestra']) < 8) { $res['sin_cliente_muestra'][] = $nombreCompleto; }
                }

                $tipo = match (trim((string) ($r['tipo_id'] ?? ''))) { '1' => '05', '2' => '06', default => null };
                if ($tipo === '05' && !preg_match('/^\d{10}$/', $ident)) { $tipo = null; } // cédula mal formada: se conserva el número sin tipo
                $sexo = strtoupper(trim((string) ($r['sexo_alumno'] ?? '')));
                $sexo = in_array($sexo, ['M', 'F'], true) ? $sexo : null;
                $serie = trim((string) ($r['serie_facturar'] ?? ''));
                $idPunto = $series[$serie] ?? null;

                $horarioTxt = trim((string) ($r['nombre_horario'] ?? ''));
                $horas = $horarioTxt !== '' ? self::parsearHorario($horarioTxt) : null;
                if ($horarioTxt !== '' && !$horas) {
                    $obs[] = 'Horario del sistema anterior: ' . $horarioTxt;
                }

                $pg->beginTransaction();
                $insAlumno->execute([
                    $idEmpresa, $nombres !== '' ? $nombres : $apellidos, $apellidos,
                    $tipo, $ident !== '' ? $ident : null, self::fechaCorta($r['fecha_nacimiento_alumno'] ?? null), $sexo,
                    $idCli, $idPunto, $obs ? implode(' ', $obs) : null, $idUsuario, $idUsuario,
                ]);
                $idAlu = (int) $insAlumno->fetchColumn();

                // Matrícula vigente: campus (sucursal_alumno) y nivel (paralelo_alumno) del viejo.
                $idCampus = $mapCampus[(string) (int) ($r['sucursal_alumno'] ?? 0)] ?? null;
                $idNivel  = $mapNivel[(string) (int) ($r['paralelo_alumno'] ?? 0)] ?? null;
                $fIngreso = self::fechaCorta($r['fecha_ingreso_alumno'] ?? null) ?? self::fechaCorta($r['fecha_agregado'] ?? null) ?? date('Y-m-d');
                $insPeriodo->execute([$idAlu, $idEmpresa, $idCampus, $idNivel, $fIngreso, $idUsuario, $idUsuario]);
                $res['periodos']++;

                // Horario: el viejo solo guarda un texto («8h30 a 14h00»); se registra de lunes a viernes.
                if ($horas) {
                    [$ini, $fin] = $horas;
                    $jornada = $ini < '12:00' ? 'matutina' : ($ini < '18:00' ? 'vespertina' : 'nocturna');
                    for ($dia = 1; $dia <= 5; $dia++) {
                        $insHorario->execute([$idAlu, $idEmpresa, $dia, $ini, $fin, $jornada, mb_substr($horarioTxt, 0, 200), $idUsuario, $idUsuario]);
                    }
                    $res['horarios']++;
                } elseif ($horarioTxt !== '') {
                    $res['horarios_texto']++;
                }

                // Servicios a facturar, con su precio pactado y descuento fijo por línea.
                $stServ->execute([':b' => $base . '%', ':ref' => (string) $old]);
                foreach ($stServ->fetchAll(PDO::FETCH_ASSOC) as $sv) {
                    $idProd = $mapProd[(string) (int) ($sv['id_producto'] ?? 0)] ?? null;
                    if (!$idProd) { $res['servicios_sin_producto']++; continue; }
                    $cant   = (float) ($sv['cant_producto'] ?? 1) ?: 1.0;
                    $precio = round((float) ($sv['precio_producto'] ?? 0), 2);
                    $desc   = round(max(0.0, (float) ($sv['descuento'] ?? 0)), 2);
                    $desc   = min($desc, round($cant * $precio, 2)); // nunca más que la línea
                    $params = [$idAlu, $idEmpresa, $idProd, $cant, $precio];
                    if ($conDescuento) { $params[] = $desc; }
                    $params[] = $idUsuario;
                    $params[] = $idUsuario;
                    $insServ->execute($params);
                    $res['servicios']++;
                }

                $insMap->execute([':e' => $idEmpresa, ':o' => $old, ':d' => $idAlu, ':cn' => mb_substr(trim($ident . ' ' . $nombreCompleto), 0, 120), ':vin' => 'f', ':cb' => $idUsuario]);
                $pg->commit();
                $done[(string) $old] = true;
                $res['migrados']++;
            } catch (Throwable $ex) {
                if ($pg->inTransaction()) { $pg->rollBack(); }
                $res['errores']++;
                if (empty($res['error_muestra'])) { $res['error_muestra'] = substr($nombreCompleto . ': ' . $ex->getMessage(), 0, 200); }
            }
        }
        return $res;
    }
}
