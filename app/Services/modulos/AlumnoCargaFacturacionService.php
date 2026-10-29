<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoRepository;
use App\Services\LogSistemaService;
use Exception;

/**
 * Historial de las cargas de facturación por Excel de Alumnos (pestaña
 * Historial de la ventana Cargar facturación) y su reversión.
 *
 * Cada carga guarda, por alumno que cambió, una FOTO de sus servicios (y de la
 * información adicional, si la carga la reemplazó) ANTES y DESPUÉS
 * (AlumnoFacturacionImportService). Revertir = volver a dejar cada alumno como
 * estaba antes, en una sola transacción. Se puede solo si, para TODOS los
 * alumnos de la carga:
 *  - no se generó ninguna factura vigente desde el alumno después de la carga; y
 *  - el alumno sigue exactamente como lo dejó la carga (nadie lo cambió después
 *    en su ficha ni en otra carga). Si hay una carga más nueva sobre los mismos
 *    alumnos, se revierte primero esa.
 */
class AlumnoCargaFacturacionService
{
    /** Cargas que muestra la pestaña Historial. */
    public const HISTORIAL = 10;

    public function __construct(
        private AlumnoRepository $repo,
        private LogSistemaService $log
    ) {}

    /**
     * Foto comparable de las líneas de servicio de un alumno, en el orden de la
     * ficha: los campos que syncServicios() escribe, con los números como texto
     * a 2 decimales para que la foto guardada en jsonb y la leída de la tabla
     * comparen igual. Sirve también para volver a grabarlas con syncServicios().
     */
    public static function fotoLineas(array $lineas): array
    {
        $n2 = fn($v) => number_format(round((float) $v, 2), 2, '.', '');
        return array_values(array_map(function ($l) use ($n2) {
            $over = $l['precio_override'] ?? null;
            $det  = trim((string) ($l['detalle'] ?? ''));
            return [
                'id_producto'      => (int) $l['id_producto'],
                'cantidad_default' => $n2($l['cantidad_default'] ?? 1),
                'precio_override'  => ($over === null || $over === '') ? null : $n2($over),
                'frecuencia'       => (string) ($l['frecuencia'] ?? 'mensual'),
                'activo'           => AlumnoFacturacionPlantillaService::lineaActiva($l),
                'detalle'          => $det !== '' ? $det : null,
                'id_tarifa_iva'    => !empty($l['id_tarifa_iva']) ? (int) $l['id_tarifa_iva'] : null,
                'descuento'        => $n2($l['descuento'] ?? 0),
            ];
        }, array_filter($lineas, fn($l) => !empty($l['id_producto']))));
    }

    /** Información adicional comparable: lista concepto/detalle o null (= la de por defecto). */
    public static function fotoInfo($valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        $filas = is_array($valor) ? $valor : json_decode((string) $valor, true);
        if (!is_array($filas)) {
            return null;
        }
        return array_values(array_map(fn($f) => [
            'concepto' => trim((string) ($f['concepto'] ?? '')),
            'detalle'  => trim((string) ($f['detalle'] ?? '')),
        ], $filas));
    }

    /** Cargas vigentes para la pestaña Historial, cada una con si se puede revertir y por qué no. */
    public function listar(int $idEmpresa, ?int $idUsuarioFiltro): array
    {
        if (!$this->repo->existeTablaCargas()) {
            throw new Exception('Falta ejecutar en la base el script database/migrations/20261029_alumnos_cargas_facturacion.sql para ver el historial de cargas.');
        }
        $cargas = $this->repo->getCargas($idEmpresa, self::HISTORIAL, $idUsuarioFiltro);
        $detalles = $this->repo->getDetallesCargas(array_column($cargas, 'id'), $idEmpresa);
        $estado = $this->estadoActual(array_merge([], ...array_map(fn($d) => array_column($d, 'id_alumno'), array_values($detalles))), $idEmpresa);

        return array_map(function ($c) use ($detalles, $estado) {
            $motivo = $this->motivoBloqueo($c, $detalles[(int) $c['id']] ?? [], $estado);
            return [
                'id'             => (int) $c['id'],
                'fecha'          => $c['created_at'] ? date('d-m-Y H:i:s', strtotime((string) $c['created_at'])) : '',
                'archivo'        => (string) ($c['archivo'] ?? ''),
                'alumnos'        => (int) $c['alumnos'],
                'agregados'      => (int) $c['agregados'],
                'cambiados'      => (int) $c['cambiados'],
                'quitados'       => (int) $c['quitados'],
                'info'           => (int) $c['info'],
                'usuario'        => (string) ($c['usuario_nombre'] ?? ''),
                'reversible'     => $motivo === null,
                'motivo_bloqueo' => (string) $motivo,
            ];
        }, $cargas);
    }

    /**
     * Revierte una carga completa: cada alumno vuelve a sus servicios (y su
     * información adicional, si la carga la cambió) de antes. Todo o nada.
     *
     * @return array{alumnos:int}
     */
    public function revertir(int $idCarga, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro): array
    {
        if (!$this->repo->existeTablaCargas()) {
            throw new Exception('Falta ejecutar en la base el script 20261029_alumnos_cargas_facturacion.sql.');
        }
        $this->repo->beginTransaction();
        try {
            $this->repo->lockCarga($idCarga, $idEmpresa);
            // Releída bajo el candado: otra pestaña pudo revertirla un instante antes.
            $carga = $this->repo->getCarga($idCarga, $idEmpresa, $idUsuarioFiltro);
            if (!$carga) {
                throw new Exception('La carga no existe, ya fue revertida o no tiene acceso a ella.');
            }
            $detalle = $this->repo->getDetallesCargas([$idCarga], $idEmpresa)[$idCarga] ?? [];
            $idsAlumno = array_map('intval', array_column($detalle, 'id_alumno'));
            sort($idsAlumno); // mismo orden de candados en toda reversión: sin bloqueos cruzados
            foreach ($idsAlumno as $idAlumno) {
                $this->repo->lockServicios($idAlumno, $idEmpresa);
            }
            $estado = $this->estadoActual($idsAlumno, $idEmpresa);
            $motivo = $this->motivoBloqueo($carga, $detalle, $estado);
            if ($motivo !== null) {
                throw new Exception($motivo);
            }

            foreach ($detalle as $d) {
                $idAlumno = (int) $d['id_alumno'];
                $antes    = json_decode((string) $d['antes'], true) ?: [];
                $despues  = json_decode((string) $d['despues'], true) ?: [];
                $servAntes = self::fotoLineas($antes['servicios'] ?? []);
                if ($servAntes !== self::fotoLineas($despues['servicios'] ?? [])) {
                    $this->repo->syncServicios($idAlumno, $idEmpresa, $servAntes, $idUsuario);
                }
                if (!empty($despues['info_cambiada'])) {
                    $this->repo->guardarInfoAdicional($idAlumno, $idEmpresa, self::fotoInfo($antes['info_adicional'] ?? null));
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'revertir_carga_excel_servicios', 'alumnos', $idAlumno,
                    $despues + ['id_carga' => $idCarga], $antes + ['id_carga' => $idCarga]);
            }
            $this->repo->eliminarCarga($idCarga, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos_cargas', $idCarga, $carga, null);

            $this->repo->commit();
            return ['alumnos' => count($detalle)];
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    /** Servicios, información adicional y última factura de cada alumno, tal como están ahora. */
    private function estadoActual(array $idsAlumno, int $idEmpresa): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsAlumno)));
        return [
            'lineas'   => $this->repo->getServiciosDeAlumnos($ids, $idEmpresa),
            'alumnos'  => $this->repo->getAlumnosPorIds($ids, $idEmpresa),
            'facturas' => $this->repo->getUltimaFacturaAlumnos($ids, $idEmpresa),
        ];
    }

    /** Primer motivo por el que la carga no se puede revertir, o null si se puede. */
    private function motivoBloqueo(array $carga, array $detalle, array $estado): ?string
    {
        if (!$detalle) {
            return 'La carga no tiene alumnos registrados.';
        }
        $fechaCarga = (string) $carga['created_at'];
        foreach ($detalle as $d) {
            $idAlumno = (int) $d['id_alumno'];
            $nombre   = (string) $d['alumno'];
            if (!isset($estado['alumnos'][$idAlumno])) {
                return "El alumno {$nombre} fue eliminado.";
            }
            $ultima = $estado['facturas'][$idAlumno] ?? null;
            if ($ultima !== null && strtotime($ultima) >= strtotime($fechaCarga)) {
                return "Ya se facturó a {$nombre} después de esta carga. Anule esa factura primero si necesita revertirla.";
            }
            $despues = json_decode((string) $d['despues'], true) ?: [];
            $modificado = self::fotoLineas($estado['lineas'][$idAlumno] ?? []) !== self::fotoLineas($despues['servicios'] ?? [])
                || (!empty($despues['info_cambiada'])
                    && self::fotoInfo($estado['alumnos'][$idAlumno]['info_adicional'] ?? null) !== self::fotoInfo($despues['info_adicional'] ?? null));
            if ($modificado) {
                return "{$nombre} se modificó después de esta carga (en su ficha o en una carga más nueva). Revierta primero la más nueva o corrija la ficha a mano.";
            }
        }
        return null;
    }
}
