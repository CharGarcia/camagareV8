<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoRepository;
use App\Rules\modulos\AlumnoRules;
use App\Services\LogSistemaService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Exception;

/**
 * Importa la facturación de los alumnos desde la plantilla de
 * AlumnoFacturacionPlantillaService (una fila por alumno, una columna por servicio).
 *
 * Qué hace cada celda de servicio con el alumno de la fila (la plantilla las trae vacías):
 *  - vacía: no cambia nada.
 *  - precio > 0: si no tiene el servicio, se agrega (cantidad 1, IVA del producto);
 *    si lo tiene con otro precio, se cambia el precio; si lo tenía desactivado, se
 *    reactiva con ese precio. "X" = precio base del producto.
 *  - 0: si tiene el servicio activo, se le quita (baja lógica). Las líneas
 *    desactivadas no se tocan.
 * Cantidad, descuento, detalle e IVA de las líneas existentes se conservan. Los
 * servicios sin columna (productos que no son servicio, servicios inactivos) no se
 * tocan; una columna de un producto inactivo (plantilla vieja) se rechaza. Precio
 * igual al base → precio_override NULL: la línea sigue al producto.
 *
 * Es TODO O NADA, como Novedades: primero se revisa el archivo completo y, solo si
 * no hay ni un error, se graba en una sola transacción. Al grabar, cada alumno se
 * bloquea (AlumnoRepository::lockServicios) y sus líneas se vuelven a leer bajo el
 * candado antes de recalcular (CLAUDE.md §8). La escritura reutiliza
 * syncServicios() de la ficha, así que la línea queda igual que si se guardara a mano.
 */
class AlumnoFacturacionImportService
{
    private AlumnoRepository $repo;
    private AlumnoRules $rules;
    private LogSistemaService $log;
    private array $config;

    public function __construct(AlumnoRepository $repo, AlumnoRules $rules, LogSistemaService $log, array $configFacturacion)
    {
        $this->repo   = $repo;
        $this->rules  = $rules;
        $this->log    = $log;
        $this->config = $configFacturacion;
    }

    public function procesar(string $archivoTmp, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro, string $nombreArchivo = ''): array
    {
        $ss = IOFactory::load($archivoTmp);
        $hoja = $ss->getSheetByName(AlumnoFacturacionPlantillaService::HOJA_DATOS) ?? $ss->getActiveSheet();
        // Valores crudos: un precio con formato de moneda sigue siendo el número.
        $filas = $hoja->toArray(null, true, false, false);
        if (count($filas) <= 1) {
            throw new Exception('El archivo está vacío o solo contiene los encabezados.');
        }

        // ── Encabezados: ID_ALUMNO y una columna por servicio ([CODIGO]) ──────
        $colId = null;
        $colsServicio = []; // índice de columna => código
        foreach ($filas[0] as $idx => $enc) {
            $enc = trim((string) $enc);
            if ($enc === '') {
                continue;
            }
            if (strtoupper(str_replace([' ', '_'], '', $enc)) === 'IDALUMNO') {
                $colId = $idx;
                continue;
            }
            $codigo = AlumnoFacturacionPlantillaService::codigoDeEncabezado($enc);
            if ($codigo !== null) {
                $colsServicio[$idx] = $codigo;
            }
        }
        if ($colId === null) {
            throw new Exception('Falta la columna ID_ALUMNO: descargue la plantilla desde Alumnos → Cargar facturación.');
        }
        if (empty($colsServicio)) {
            throw new Exception('El archivo no tiene columnas de servicio (encabezados que terminan en [CODIGO]).');
        }

        $errores = [];
        $repetidos = array_diff_assoc($colsServicio, array_unique($colsServicio));
        foreach (array_unique($repetidos) as $codigo) {
            $errores[] = ['fila' => 1, 'servicio' => $codigo, 'error' => 'La columna del servicio está repetida.'];
        }
        $productos = $this->repo->getProductosPorCodigo(array_values($colsServicio), $idEmpresa);
        foreach (array_unique($colsServicio) as $codigo) {
            if (!isset($productos[$codigo])) {
                $errores[] = ['fila' => 1, 'servicio' => $codigo, 'error' => 'No existe un producto con ese código en la empresa.'];
            } elseif ((int) $productos[$codigo]['status'] !== 1) {
                $errores[] = ['fila' => 1, 'servicio' => $codigo, 'error' => 'El producto está inactivo: borre la columna o descargue la plantilla de nuevo.'];
            }
        }
        if ($errores) {
            return $this->abortar($errores, 0);
        }

        // ── Fase 1: leer y validar TODO el archivo, sin escribir nada ─────────
        $pedidos = []; // id_alumno => ['fila' => n, 'celdas' => [id_producto => ['valor' => ?float, 'servicio' => etiqueta]]]
        for ($i = 1; $i < count($filas); $i++) {
            $fila = $filas[$i];
            if (empty(array_filter($fila, fn($v) => trim((string) $v) !== ''))) {
                continue;
            }
            $nf = $i + 1;
            $idTxt = trim((string) ($fila[$colId] ?? ''));
            if ($idTxt === '' || !ctype_digit(preg_replace('/\.0+$/', '', $idTxt))) {
                $errores[] = ['fila' => $nf, 'servicio' => null, 'error' => 'ID_ALUMNO vacío o no válido.'];
                continue;
            }
            $idAlumno = (int) $idTxt;
            if (isset($pedidos[$idAlumno])) {
                $errores[] = ['fila' => $nf, 'servicio' => null, 'error' => 'El alumno ya está en la fila ' . $pedidos[$idAlumno]['fila'] . '.'];
                continue;
            }
            $celdas = [];
            foreach ($colsServicio as $idx => $codigo) {
                $p = $productos[$codigo];
                $etq = $codigo . ' · ' . $p['nombre'];
                $crudo = $fila[$idx] ?? null;
                $txt = trim((string) $crudo);
                if (preg_match('/^\d+,\d{1,2}$/', $txt)) {
                    $txt = str_replace(',', '.', $txt); // "12,50" escrito como texto
                }
                if ($txt === '') {
                    continue; // vacía: no se toca ese servicio
                }
                if (is_numeric($txt) && (float) $txt == 0.0) {
                    $valor = null; // 0: se le quita
                } elseif (strtoupper($txt) === 'X') {
                    $valor = round((float) $p['precio_base'], 2);
                } elseif (is_numeric($txt)) {
                    $valor = round((float) $txt, 2);
                } else {
                    $errores[] = ['fila' => $nf, 'servicio' => $etq, 'error' => "«{$txt}» no es un precio: escriba un número, X (precio base), 0 (quitar) o déjela vacía."];
                    continue;
                }
                $celdas[(int) $p['id']] = ['valor' => $valor, 'servicio' => $etq, 'precio_base' => round((float) $p['precio_base'], 2)];
            }
            $pedidos[$idAlumno] = ['fila' => $nf, 'celdas' => $celdas];
        }

        $alumnos = $this->repo->getAlumnosPorIds(array_keys($pedidos), $idEmpresa, $idUsuarioFiltro);
        foreach ($pedidos as $idAlumno => $p) {
            if (!isset($alumnos[$idAlumno])) {
                $errores[] = ['fila' => $p['fila'], 'servicio' => null, 'error' => "No existe el alumno con ID {$idAlumno} (o no tiene acceso a él)."];
                unset($pedidos[$idAlumno]);
            }
        }

        $lineas = $this->repo->getServiciosDeAlumnos(array_keys($pedidos), $idEmpresa);
        $planes = [];
        foreach ($pedidos as $idAlumno => $p) {
            $plan = $this->planificar($lineas[$idAlumno] ?? [], $p['celdas']);
            foreach ($plan['errores'] as [$servicio, $msg]) {
                $errores[] = ['fila' => $p['fila'], 'servicio' => $servicio, 'error' => $msg];
            }
            if ($plan['cambios']) {
                $planes[$idAlumno] = $plan;
            }
        }

        if ($errores) {
            return $this->abortar($errores, count($pedidos));
        }
        $sinCambios = count($pedidos) - count($planes);
        if (!$planes) {
            return ['ok_cambios' => false, 'alumnos' => 0, 'agregados' => 0, 'cambiados' => 0, 'quitados' => 0,
                    'sin_cambios' => $sinCambios, 'errores' => [], 'abortada' => false];
        }

        // ── Fase 2: grabar todo en una transacción, alumno por alumno bajo candado ──
        $tot = ['agregados' => 0, 'cambiados' => 0, 'quitados' => 0];
        $actualizados = 0;
        $this->repo->beginTransaction();
        try {
            foreach (array_keys($planes) as $idAlumno) {
                $this->repo->lockServicios($idAlumno, $idEmpresa);
                // Releer bajo el candado: otra carga o la ficha pudo cambiarlo entre la fase 1 y aquí.
                $antes = $this->repo->getServiciosDeAlumnos([$idAlumno], $idEmpresa)[$idAlumno] ?? [];
                $plan = $this->planificar($antes, $pedidos[$idAlumno]['celdas']);
                if ($plan['errores']) {
                    [$servicio, $msg] = $plan['errores'][0];
                    throw new Exception('Fila ' . $pedidos[$idAlumno]['fila'] . " · {$servicio}: {$msg}");
                }
                if (!$plan['cambios']) {
                    continue;
                }
                $this->repo->syncServicios($idAlumno, $idEmpresa, $plan['lineas'], $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'carga_excel_servicios', 'alumnos', $idAlumno,
                    ['servicios' => $this->resumenLog($antes)],
                    ['servicios' => $this->resumenLog($plan['lineas']), 'archivo' => $nombreArchivo, 'cambios' => $plan['detalle']]);
                foreach ($tot as $k => $v) {
                    $tot[$k] = $v + $plan['cuenta'][$k];
                }
                $actualizados++;
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw new Exception('No se cambió ningún alumno: ' . $e->getMessage());
        }

        return ['ok_cambios' => true, 'alumnos' => $actualizados] + $tot
            + ['sin_cambios' => count($pedidos) - $actualizados, 'errores' => [], 'abortada' => false];
    }

    /**
     * Aplica las celdas de un alumno a sus líneas actuales, sin escribir nada.
     * Devuelve la lista completa de líneas a guardar (para syncServicios), si hubo
     * cambios, los errores [servicio, mensaje] y el conteo por tipo de cambio.
     */
    private function planificar(array $lineas, array $celdas): array
    {
        $editarPrecio = !empty($this->config['editar_precio_factura']);
        $errores = [];
        $detalle = [];
        $cuenta  = ['agregados' => 0, 'cambiados' => 0, 'quitados' => 0];
        $quitar  = []; // índices de $lineas que se dan de baja
        $nuevas  = [];

        foreach ($celdas as $idProd => $c) {
            $valor = $c['valor'];
            $activas = $inactivas = [];
            foreach ($lineas as $k => $l) {
                if ((int) $l['id_producto'] === $idProd) {
                    AlumnoFacturacionPlantillaService::lineaActiva($l) ? $activas[] = $k : $inactivas[] = $k;
                }
            }

            if ($valor === null) {
                foreach ($activas as $k) {
                    $quitar[$k] = true;
                }
                if ($activas) {
                    $cuenta['quitados']++;
                    $detalle[] = 'Quitado: ' . $c['servicio'];
                }
                continue;
            }

            if (count($activas) > 1) {
                // Varias líneas del mismo servicio: la celda muestra la primera. Sin
                // cambio de precio no se toca nada; cambiarlo sería ambiguo.
                if ($valor !== AlumnoFacturacionPlantillaService::precioLinea($lineas[$activas[0]])) {
                    $errores[] = [$c['servicio'], 'El alumno tiene ' . count($activas) . ' líneas de este servicio: cámbielo en su ficha y deje el precio del Excel como estaba.'];
                }
                continue;
            }

            $k = $activas[0] ?? ($inactivas[0] ?? null);
            $linea = $k !== null ? $lineas[$k] : null;
            $cantidad  = $linea !== null ? (float) $linea['cantidad_default'] : 1.0;
            $descuento = $linea !== null ? (float) ($linea['descuento'] ?? 0) : 0.0;
            $msg = $this->rules->validarPrecioCarga($valor, $c['precio_base'], $cantidad, $descuento, $editarPrecio);
            if ($msg !== null) {
                $errores[] = [$c['servicio'], $msg];
                continue;
            }
            $override = ($valor === $c['precio_base']) ? '' : $valor;

            if ($linea === null) {
                $nuevas[] = ['id_producto' => $idProd, 'cantidad_default' => 1, 'precio_override' => $override,
                             'frecuencia' => 'mensual', 'activo' => true, 'detalle' => null, 'id_tarifa_iva' => null, 'descuento' => 0];
                $cuenta['agregados']++;
                $detalle[] = 'Agregado: ' . $c['servicio'] . ' a ' . number_format($valor, 2, '.', '');
                continue;
            }
            $activa = AlumnoFacturacionPlantillaService::lineaActiva($linea);
            $precioAntes = AlumnoFacturacionPlantillaService::precioLinea($linea);
            if ($activa && $precioAntes === $valor) {
                continue; // sin cambio
            }
            $lineas[$k]['precio_override'] = $override;
            $lineas[$k]['activo'] = true;
            $cuenta['cambiados']++;
            $detalle[] = ($activa ? 'Precio cambiado: ' : 'Reactivado: ') . $c['servicio'] . ' '
                . number_format($precioAntes, 2, '.', '') . ' → ' . number_format($valor, 2, '.', '');
        }

        $final = [];
        foreach ($lineas as $k => $l) {
            if (!isset($quitar[$k])) {
                $final[] = $l;
            }
        }
        $final = array_merge($final, $nuevas);

        return [
            'lineas'  => $final,
            'cambios' => !empty($detalle),
            'errores' => $errores,
            'detalle' => $detalle,
            'cuenta'  => $cuenta,
        ];
    }

    /** Líneas en forma corta para log_sistema (antes/después). */
    private function resumenLog(array $lineas): array
    {
        return array_map(fn($l) => [
            'id_producto'      => (int) $l['id_producto'],
            'cantidad_default' => (float) ($l['cantidad_default'] ?? 1),
            'precio_override'  => ($l['precio_override'] ?? '') !== '' && $l['precio_override'] !== null ? (float) $l['precio_override'] : null,
            'descuento'        => (float) ($l['descuento'] ?? 0),
            'activo'           => AlumnoFacturacionPlantillaService::lineaActiva($l),
        ], array_values($lineas));
    }

    private function abortar(array $errores, int $total): array
    {
        usort($errores, fn($a, $b) => $a['fila'] <=> $b['fila']);
        return ['ok_cambios' => false, 'alumnos' => 0, 'agregados' => 0, 'cambiados' => 0, 'quitados' => 0,
                'sin_cambios' => 0, 'total' => $total, 'errores' => $errores, 'abortada' => true];
    }
}
