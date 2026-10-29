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
 * Opciones de la ventana de carga (vacías = no se tocan):
 *  - 'detalle': texto «Adicional» que se pone en cada servicio con precio o X en el
 *    archivo (también si el precio no cambió). Admite los marcadores de la ficha.
 *  - 'info_adicional': filas concepto/detalle de la factura; reemplazan las del
 *    alumno en cada fila del archivo que tenga al menos una celda escrita.
 *
 * Cada carga con cambios queda en alumnos_cargas (con la foto antes/después de
 * cada alumno en alumnos_cargas_detalle) para poder revertirla desde la pestaña
 * Historial: ver AlumnoCargaFacturacionService.
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
    /** Texto «Adicional» para los servicios escritos en el archivo (null = se conserva el de cada línea). */
    private ?string $detalle = null;
    /** Información adicional de la factura para los alumnos del archivo (null = se conserva la de cada alumno). */
    private ?array $infoAdicional = null;

    public function __construct(AlumnoRepository $repo, AlumnoRules $rules, LogSistemaService $log, array $configFacturacion)
    {
        $this->repo   = $repo;
        $this->rules  = $rules;
        $this->log    = $log;
        $this->config = $configFacturacion;
    }

    public function procesar(string $archivoTmp, int $idEmpresa, int $idUsuario, ?int $idUsuarioFiltro, string $nombreArchivo = '', array $opciones = []): array
    {
        $this->leerOpciones($opciones);
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
            $cambiaInfo = $this->cambiaInfo($alumnos[$idAlumno]['info_adicional'] ?? null, $p['celdas']);
            if ($plan['cambios'] || $cambiaInfo) {
                $planes[$idAlumno] = $plan;
            }
        }

        if ($errores) {
            return $this->abortar($errores, count($pedidos));
        }
        $sinCambios = count($pedidos) - count($planes);
        if (!$planes) {
            return ['ok_cambios' => false, 'alumnos' => 0, 'agregados' => 0, 'cambiados' => 0, 'quitados' => 0, 'info' => 0,
                    'sin_cambios' => $sinCambios, 'errores' => [], 'abortada' => false];
        }

        // ── Fase 2: grabar todo en una transacción, alumno por alumno bajo candado ──
        $tot = ['agregados' => 0, 'cambiados' => 0, 'quitados' => 0, 'info' => 0];
        $actualizados = 0;
        $idCarga = null; // se crea con el primer alumno que cambia (sin el SQL del historial, no se registra)
        $conHistorial = $this->repo->existeTablaCargas();
        $this->repo->beginTransaction();
        try {
            foreach (array_keys($planes) as $idAlumno) {
                $this->repo->lockServicios($idAlumno, $idEmpresa);
                // Releer bajo el candado: otra carga o la ficha pudo cambiarlo entre la fase 1 y aquí.
                $antes = $this->repo->getServiciosDeAlumnos([$idAlumno], $idEmpresa)[$idAlumno] ?? [];
                $infoAntes = $this->repo->getAlumnosPorIds([$idAlumno], $idEmpresa)[$idAlumno]['info_adicional'] ?? null;
                $plan = $this->planificar($antes, $pedidos[$idAlumno]['celdas']);
                if ($plan['errores']) {
                    [$servicio, $msg] = $plan['errores'][0];
                    throw new Exception('Fila ' . $pedidos[$idAlumno]['fila'] . " · {$servicio}: {$msg}");
                }
                $cambiaInfo = $this->cambiaInfo($infoAntes, $pedidos[$idAlumno]['celdas']);
                if (!$plan['cambios'] && !$cambiaInfo) {
                    continue;
                }
                if ($plan['cambios']) {
                    $this->repo->syncServicios($idAlumno, $idEmpresa, $plan['lineas'], $idUsuario);
                }
                if ($cambiaInfo) {
                    $this->repo->guardarInfoAdicional($idAlumno, $idEmpresa, $this->infoAdicional);
                    $plan['detalle'][] = 'Información adicional de la factura reemplazada';
                }
                $this->log->registrar($idUsuario, $idEmpresa, 'carga_excel_servicios', 'alumnos', $idAlumno,
                    ['servicios' => $this->resumenLog($antes), 'info_adicional' => $this->decodificarInfo($infoAntes)],
                    ['servicios' => $this->resumenLog($plan['cambios'] ? $plan['lineas'] : $antes),
                     'info_adicional' => $cambiaInfo ? $this->infoAdicional : $this->decodificarInfo($infoAntes),
                     'archivo' => $nombreArchivo, 'cambios' => $plan['detalle']]);
                foreach (['agregados', 'cambiados', 'quitados'] as $k) {
                    $tot[$k] += $plan['cuenta'][$k];
                }
                $tot['info'] += $cambiaInfo ? 1 : 0;
                $actualizados++;

                // Historial (pestaña Historial): cómo estaba y cómo quedó, para poder revertir la carga.
                if ($conHistorial) {
                    $idCarga ??= $this->repo->crearCarga($idEmpresa, $nombreArchivo, $idUsuario);
                    $infoPrevia = $this->decodificarInfo($infoAntes);
                    $this->repo->guardarDetalleCarga($idCarga, $idEmpresa, $idAlumno,
                        ['servicios' => AlumnoCargaFacturacionService::fotoLineas($antes), 'info_adicional' => $infoPrevia, 'info_cambiada' => $cambiaInfo],
                        ['servicios' => AlumnoCargaFacturacionService::fotoLineas($plan['cambios'] ? $plan['lineas'] : $antes),
                         'info_adicional' => $cambiaInfo ? $this->infoAdicional : $infoPrevia, 'info_cambiada' => $cambiaInfo],
                        $idUsuario);
                }
            }
            if ($idCarga !== null) {
                $this->repo->cerrarCarga($idCarga, $idEmpresa, ['alumnos' => $actualizados] + $tot, $idUsuario);
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw new Exception('No se cambió ningún alumno: ' . $e->getMessage());
        }

        return ['ok_cambios' => true, 'alumnos' => $actualizados] + $tot
            + ['sin_cambios' => count($pedidos) - $actualizados, 'errores' => [], 'abortada' => false, 'id_carga' => $idCarga];
    }

    /**
     * Valida y guarda las opciones de la ventana de carga. Mismas reglas que la
     * ficha del alumno (AlumnoRules): detalle de hasta 300 caracteres e info
     * adicional de máximo 10 filas con concepto y detalle.
     */
    private function leerOpciones(array $opciones): void
    {
        $detalle = trim((string) ($opciones['detalle'] ?? ''));
        $filas = array_values(array_filter(
            array_map(fn($f) => [
                'concepto' => trim((string) ($f['concepto'] ?? '')),
                'detalle'  => trim((string) ($f['detalle'] ?? '')),
            ], is_array($opciones['info_adicional'] ?? null) ? $opciones['info_adicional'] : []),
            fn($f) => $f['concepto'] !== '' || $f['detalle'] !== ''
        ));

        if (($detalle !== '' || $filas) && !$this->repo->existeColumnasFacturacion()) {
            throw new Exception('Falta ejecutar en la base el script 20260924_alumnos_facturacion.sql para guardar el adicional y la información adicional.');
        }
        if (mb_strlen($detalle) > 300) {
            throw new Exception('El adicional de cada ítem no puede exceder 300 caracteres.');
        }
        if ($filas) {
            $this->rules->validarInfoAdicional($filas);
        }
        $this->detalle = $detalle !== '' ? $detalle : null;
        $this->infoAdicional = $filas ?: null;
    }

    /** ¿Hay que reemplazar la info adicional del alumno? Solo si se pidió y la fila tiene alguna celda escrita. */
    private function cambiaInfo($infoActual, array $celdas): bool
    {
        if ($this->infoAdicional === null || !$celdas) {
            return false;
        }
        return $this->decodificarInfo($infoActual) !== $this->infoAdicional;
    }

    /** alumnos.info_adicional (jsonb) como lista concepto/detalle; null = la de por defecto. */
    private function decodificarInfo($valor): ?array
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

    /**
     * Aplica las celdas de un alumno a sus líneas actuales, sin escribir nada.
     * Devuelve la lista completa de líneas a guardar (para syncServicios), si hubo
     * cambios, los errores [servicio, mensaje] y el conteo por tipo de cambio.
     * Con $this->detalle, cada servicio con precio o X recibe ese «Adicional».
     */
    private function planificar(array $lineas, array $celdas): array
    {
        $editarPrecio = !empty($this->config['editar_precio_factura']);
        $errores = [];
        $notas   = [];
        $cuenta  = ['agregados' => 0, 'cambiados' => 0, 'quitados' => 0];
        $quitar  = []; // índices de $lineas que se dan de baja
        $nuevas  = [];
        $cambiaDetalle = fn(array $l) => $this->detalle !== null && trim((string) ($l['detalle'] ?? '')) !== $this->detalle;

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
                    $notas[] = 'Quitado: ' . $c['servicio'];
                }
                continue;
            }

            if (count($activas) > 1) {
                // Varias líneas del mismo servicio: cambiar el precio sería ambiguo.
                // El adicional sí se puede poner en todas.
                if ($valor !== AlumnoFacturacionPlantillaService::precioLinea($lineas[$activas[0]])) {
                    $errores[] = [$c['servicio'], 'El alumno tiene ' . count($activas) . ' líneas de este servicio: cambie el precio en su ficha (con 0 sí se quitan todas).'];
                    continue;
                }
                $tocadas = array_filter($activas, fn($k) => $cambiaDetalle($lineas[$k]));
                foreach ($tocadas as $k) {
                    $lineas[$k]['detalle'] = $this->detalle;
                }
                if ($tocadas) {
                    $cuenta['cambiados']++;
                    $notas[] = 'Adicional cambiado: ' . $c['servicio'];
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
                             'frecuencia' => 'mensual', 'activo' => true, 'detalle' => $this->detalle ?? AlumnoFacturacionService::DETALLE_ITEM_DEFECTO,
                             'id_tarifa_iva' => null, 'descuento' => 0];
                $cuenta['agregados']++;
                $notas[] = 'Agregado: ' . $c['servicio'] . ' a ' . number_format($valor, 2, '.', '');
                continue;
            }
            $activa = AlumnoFacturacionPlantillaService::lineaActiva($linea);
            $precioAntes = AlumnoFacturacionPlantillaService::precioLinea($linea);
            $nuevoDetalle = $cambiaDetalle($linea);
            if ($activa && $precioAntes === $valor && !$nuevoDetalle) {
                continue; // sin cambio
            }
            $lineas[$k]['precio_override'] = $override;
            $lineas[$k]['activo'] = true;
            if ($nuevoDetalle) {
                $lineas[$k]['detalle'] = $this->detalle;
            }
            $cuenta['cambiados']++;
            if (!$activa) {
                $notas[] = 'Reactivado: ' . $c['servicio'] . ' a ' . number_format($valor, 2, '.', '');
            } elseif ($precioAntes !== $valor) {
                $notas[] = 'Precio cambiado: ' . $c['servicio'] . ' ' . number_format($precioAntes, 2, '.', '') . ' → ' . number_format($valor, 2, '.', '');
            } else {
                $notas[] = 'Adicional cambiado: ' . $c['servicio'];
            }
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
            'cambios' => !empty($notas),
            'errores' => $errores,
            'detalle' => $notas,
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
            'detalle'          => ($l['detalle'] ?? null) !== null && trim((string) $l['detalle']) !== '' ? trim((string) $l['detalle']) : null,
            'activo'         => AlumnoFacturacionPlantillaService::lineaActiva($l),
        ], array_values($lineas));
    }

    private function abortar(array $errores, int $total): array
    {
        usort($errores, fn($a, $b) => $a['fila'] <=> $b['fila']);
        return ['ok_cambios' => false, 'alumnos' => 0, 'agregados' => 0, 'cambiados' => 0, 'quitados' => 0,
                'sin_cambios' => 0, 'total' => $total, 'errores' => $errores, 'abortada' => true];
    }
}
