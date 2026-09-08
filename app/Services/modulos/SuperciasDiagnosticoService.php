<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\SuperciasEcp;
use App\Helpers\SuperciasEfe;
use App\Helpers\SuperciasSugerencias;
use App\repositories\modulos\EstadosFinancierosRepository;

/**
 * Diagnóstico Supercías: revisa, para una empresa y un rango, todo lo que impide que los TXT
 * (ESF, ERI, ECP, EFE) salgan completos y cuadrados, y propone cómo corregirlo. Pensado para que
 * cualquier usuario con permiso sobre Plan de Cuentas pueda dejar la empresa lista sin ayuda del
 * superadministrador. No modifica datos.
 */
class SuperciasDiagnosticoService
{
    /**
     * Número de casilleros que exige el portal en cada archivo (verificado contra archivos
     * aceptados del ejercicio 2025). Si el archivo no los trae todos, el portal responde
     * "El número total de cuentas no es el correcto" y lista cada casillero faltante.
     */
    public const CASILLEROS_OFICIALES = ['ESF' => 376, 'ERI' => 246, 'ECP' => 320, 'EFE' => 83];

    public function __construct(
        private EstadosFinancierosRepository $repository,
        private EstadosFinancierosService $estadosService
    ) {}

    /**
     * @return array{
     *   hallazgos: array<int, array{clave:string, titulo:string, severidad:string, afecta:array, descripcion:string, items:array, accion:string}>,
     *   resumen: array{bloqueantes:int, advertencias:int, ok:int},
     *   cuadres: array
     * }
     */
    public function diagnosticar(int $idEmpresa, string $fechaInicio, string $fechaFin, ?int $idCentroCosto = null, ?int $idProyecto = null): array
    {
        $hallazgos = [];
        $esfEstructura = $this->repository->getCasillerosEstructura('ESF');
        $eriEstructura = $this->repository->getCasillerosEstructura('ERI');

        // ── 0. La estructura debe tener el catálogo COMPLETO: el portal rechaza el archivo con
        //       "El número total de cuentas no es el correcto" si falta un solo casillero. ────────
        $itemsCatalogo = [];
        foreach (self::CASILLEROS_OFICIALES as $tipo => $esperado) {
            $tiene = $this->repository->contarCasilleros($tipo);
            if ($tiene !== $esperado) {
                $itemsCatalogo[] = [
                    'codigo'   => $tipo,
                    'nombre'   => 'Catálogo de casilleros incompleto',
                    'problema' => "La estructura tiene $tiene casilleros y el portal exige $esperado",
                ];
            }
        }
        $hallazgos[] = $this->hallazgo('catalogo_incompleto', 'Catálogo de casilleros de la estructura', empty($itemsCatalogo) ? 'ok' : 'bloqueante', ['ESF', 'ERI', 'ECP', 'EFE'],
            'El portal exige que el archivo contenga TODOS los casilleros oficiales, incluidos los que valen 0.00. Si falta alguno, rechaza el archivo completo. Esto es configuración global.',
            $itemsCatalogo, 'Solicite al administrador del sistema que complete la estructura en /config/supercias.');
        $cuentas = $this->repository->getCuentasConMovimientoParaDiagnostico($idEmpresa, $fechaInicio, $fechaFin);

        // ── 1. Cuentas con movimiento sin casillero / con casillero inválido / con casillero de fórmula ──
        $sinMapeo = []; $invalidas = []; $conFormula = []; $ecpSinColumna = []; $ecpInvalida = [];
        $hayEfectivo = false;
        foreach ($cuentas as $c) {
            if ((int) $c['nivel'] !== 5) continue;
            $clase = substr((string) $c['codigo'], 0, 1);
            if ($clase === '7') continue; // pivote de cierre migrado: no se mapea
            $esf = trim((string) ($c['supercias_esf'] ?? ''));
            $eri = trim((string) ($c['supercias_eri'] ?? ''));
            $sug = SuperciasSugerencias::sugerir((string) $c['codigo'], (string) $c['nombre'], (string) ($c['nombre_padre'] ?? ''));
            $item = [
                'id_cuenta' => (int) $c['id_cuenta'],
                'codigo'    => $c['codigo'],
                'nombre'    => $c['nombre'],
                'esf'       => $esf,
                'eri'       => $eri,
                'ecp'       => trim((string) ($c['supercias_ecp_subcodigo'] ?? '')),
                'neto'      => round((float) $c['debe'] - (float) $c['haber'], 2),
                'sugerencia' => $sug,
            ];
            if (SuperciasEfe::esEfectivo($esf)) $hayEfectivo = true;

            $esBalance = in_array($clase, ['1', '2', '3'], true);
            if ($esBalance && $esf === '') {
                $item['problema'] = 'Sin casillero ESF';
                $sinMapeo[] = $item;
            } elseif (!$esBalance && $eri === '') {
                $item['problema'] = 'Sin casillero ERI';
                $sinMapeo[] = $item;
            } elseif ($esBalance && !isset($esfEstructura[$esf])) {
                $item['problema'] = "El ESF $esf no existe en la estructura";
                $invalidas[] = $item;
            } elseif (!$esBalance && !isset($eriEstructura[$eri])) {
                $item['problema'] = "El ERI $eri no existe en la estructura";
                $invalidas[] = $item;
            } elseif ($esBalance && $esfEstructura[$esf] !== '') {
                $item['problema'] = "El ESF $esf tiene fórmula ({$esfEstructura[$esf]}): el valor de la cuenta se ignora";
                $conFormula[] = $item;
            } elseif (!$esBalance && $eriEstructura[$eri] !== '') {
                $item['problema'] = "El ERI $eri tiene fórmula ({$eriEstructura[$eri]}): el valor de la cuenta se ignora";
                $conFormula[] = $item;
            }

            if ($clase === '3' && $esf !== '') {
                $col = $item['ecp'];
                if ($col === '') {
                    $item['problema'] = 'Sin columna ECP';
                    $item['sugerencia']['ecp_columna'] = SuperciasEcp::columnaDesdeEsf($esf);
                    $ecpSinColumna[] = $item;
                } elseif (!in_array($col, SuperciasEcp::COLUMNAS, true)) {
                    $item['problema'] = "La columna ECP $col no es válida";
                    $item['sugerencia']['ecp_columna'] = SuperciasEcp::columnaDesdeEsf($esf);
                    $ecpInvalida[] = $item;
                }
            }
        }

        $hallazgos[] = $this->hallazgo('sin_mapeo', 'Cuentas con movimiento sin casillero Supercías', 'bloqueante', ['ESF', 'ERI', 'ECP', 'EFE'],
            'Estas cuentas tuvieron movimiento en el período y no tienen casillero ESF (activo, pasivo, patrimonio) o ERI (ingresos, costos, gastos). Sus valores no salen en ningún archivo.',
            $sinMapeo, 'Asigne el casillero: acepte la sugerencia o abra la ficha de la cuenta.');
        $hallazgos[] = $this->hallazgo('invalidas', 'Casilleros que no existen en la estructura', 'bloqueante', ['ESF', 'ERI', 'ECP', 'EFE'],
            'El casillero asignado no está en la estructura oficial cargada. El valor se pierde.',
            $invalidas, 'Corrija el casillero con uno válido.');
        $hallazgos[] = $this->hallazgo('con_formula', 'Cuentas mapeadas a un casillero de totales', 'bloqueante', ['ESF', 'ERI'],
            'El casillero tiene fórmula, así que suma otros casilleros e ignora lo que aportan las cuentas. Hay que mapear la cuenta al casillero de detalle.',
            $conFormula, 'Cambie el casillero por el de detalle sugerido.');
        $hallazgos[] = $this->hallazgo('ecp_sin_columna', 'Cuentas de patrimonio sin columna ECP', 'bloqueante', ['ECP'],
            'Sin columna, la cuenta no entra al Estado de Cambios en el Patrimonio. La columna se deduce del ESF.',
            $ecpSinColumna, 'Acepte la sugerencia o use "Completar automáticamente".');
        $hallazgos[] = $this->hallazgo('ecp_invalida', 'Columnas ECP no válidas', 'bloqueante', ['ECP'],
            'La columna debe ser uno de los componentes del patrimonio (301, 302, 303, 30401, 30402, 30501 a 30504, 30601 a 30607, 30701, 30702).',
            $ecpInvalida, 'Acepte la sugerencia.');

        // ── 2. Efectivo ───────────────────────────────────────────────────────────────────────
        $itemsEfectivo = [];
        if (!$hayEfectivo && !empty($cuentas)) {
            $itemsEfectivo = array_values(array_filter($sinMapeo, fn($i) => str_starts_with((string) $i['codigo'], '1') && in_array($i['sugerencia']['esf'], ['1010101', '1010102', '1010103'], true)));
            if (empty($itemsEfectivo)) {
                $itemsEfectivo[] = ['codigo' => '—', 'nombre' => 'Ninguna cuenta con movimiento tiene casillero ESF 1010101, 1010102 o 1010103', 'problema' => 'Sin cuentas de efectivo el EFE sale en cero'];
            }
        }
        $hallazgos[] = $this->hallazgo('sin_efectivo', 'Cuentas de efectivo (Caja y Bancos)', empty($itemsEfectivo) ? 'ok' : 'bloqueante', ['EFE'],
            'El Estado de Flujos de Efectivo necesita saber qué cuentas son Caja y Bancos: son las que tienen casillero ESF 1010101, 1010102 o 1010103.',
            $itemsEfectivo,
            'Asigne el ESF a las cuentas de caja y bancos.');

        // ── 3. Cuenta de cierre del ejercicio ─────────────────────────────────────────────────
        $ctasCierre = $this->repository->getCuentasCierreEjercicio($idEmpresa);
        $faltaCierre = empty($ctasCierre['utilidad']) || empty($ctasCierre['perdida']);
        $hallazgos[] = $this->hallazgo('cuenta_cierre', 'Cuentas de utilidad y pérdida del ejercicio', $faltaCierre ? 'advertencia' : 'ok', ['ESF', 'ECP'],
            'El balance muestra el resultado del ejercicio en la cuenta configurada para el cierre. Sin ella, el resultado no llega al casillero 30701 / 30702 del ESF ni al ECP.',
            $faltaCierre ? [['codigo' => '', 'nombre' => 'Configure la cuenta de Utilidad y la de Pérdida del ejercicio', 'problema' => (empty($ctasCierre['utilidad']) ? 'Falta utilidad. ' : '') . (empty($ctasCierre['perdida']) ? 'Falta pérdida.' : '')]] : [],
            'Configuración Contable › Cierre del Ejercicio.', '/modulos/configuracion-contable');

        // ── 4. Saldos iniciales sin tipo apertura ─────────────────────────────────────────────
        $sospechosos = $this->repository->getAsientosAperturaSospechosos($idEmpresa, $fechaInicio);
        $hallazgos[] = $this->hallazgo('apertura', 'Saldos iniciales que no están como asiento de apertura', empty($sospechosos) ? 'ok' : 'advertencia', ['ECP', 'EFE'],
            'Los asientos de la fecha de inicio con solo cuentas de balance parecen saldos iniciales. Si su tipo no es "apertura", el ECP los toma como cambios del año y el EFE como movimiento de efectivo.',
            array_map(fn($a) => ['codigo' => '#' . $a['id'], 'nombre' => (string) $a['concepto'], 'problema' => 'Tipo ' . ($a['tipo_comprobante'] ?: '(vacío)') . ', ' . $a['lineas'] . ' líneas, ' . number_format((float) $a['total_debe'], 2) . ' de debe'], $sospechosos),
            'Cambie el tipo de comprobante a "apertura" en Asientos Contables.', '/modulos/asientos-contables');

        // ── 5. Cuadres de los cuatro estados ──────────────────────────────────────────────────
        $ev = $this->estadosService->evaluarSupercias($idEmpresa, $fechaInicio, $fechaFin, $idCentroCosto, $idProyecto);
        $cas = $ev['casilleros'];
        $v = fn(string $tipo, string $cod) => round((float) ($cas[$tipo][$cod]['valor'] ?? 0), 2);

        $activo = $v('ESF', '1'); $pasivo = $v('ESF', '2'); $patrimonio = $v('ESF', '3');
        $difEsf = round($activo - $pasivo - $patrimonio, 2);
        $totalesEsfSinFormula = array_values(array_filter(['1', '101', '102', '2', '201', '202', '3'], fn($c) => isset($esfEstructura[$c]) && $esfEstructura[$c] === ''));
        $totalesEriSinFormula = array_values(array_filter(['401', '402', '501', '502', '600', '602', '604', '607', '707'], fn($c) => isset($eriEstructura[$c]) && $eriEstructura[$c] === ''));
        $itemsFormulas = [];
        foreach ($totalesEsfSinFormula as $c) $itemsFormulas[] = ['codigo' => 'ESF ' . $c, 'nombre' => 'Casillero de total sin fórmula', 'problema' => 'Configuración global pendiente'];
        foreach ($totalesEriSinFormula as $c) $itemsFormulas[] = ['codigo' => 'ERI ' . $c, 'nombre' => 'Casillero de total sin fórmula', 'problema' => 'Configuración global pendiente'];
        $hallazgos[] = $this->hallazgo('formulas_globales', 'Casilleros de totales sin fórmula en la estructura', empty($itemsFormulas) ? 'ok' : 'advertencia', ['ESF', 'ERI', 'EFE'],
            'Los totales del ESF y del ERI (activo, pasivo, patrimonio, ganancia antes de impuestos…) se calculan con fórmulas en la estructura global. Sin ellas salen en cero. Esto lo configura el superadministrador en /config/supercias.',
            $itemsFormulas,
            'Solicite al administrador del sistema que complete las fórmulas.');

        // Impuesto / participación sin gasto en resultados (típico de cierres migrados)
        $irPagarDelta = 0.0; $ptDelta = 0.0;
        foreach ($cuentas as $c) {
            $esf = trim((string) ($c['supercias_esf'] ?? ''));
            $mov = (float) $c['haber'] - (float) $c['debe'];
            if ($esf === '2010702') $irPagarDelta += $mov;
            if ($esf === '2010705') $ptDelta += $mov;
        }
        $irSinGasto = round($irPagarDelta, 2) > 0 && $v('ERI', '603') == 0;
        $ptSinGasto = round($ptDelta, 2) > 0 && $v('ERI', '601') == 0;
        $itemsImp = [];
        if ($irSinGasto) $itemsImp[] = ['codigo' => 'ERI 603', 'nombre' => 'Impuesto a la renta', 'problema' => 'El pasivo de impuesto creció ' . number_format($irPagarDelta, 2) . ' pero no hay gasto de impuesto a la renta en resultados (ERI 603).'];
        if ($ptSinGasto) $itemsImp[] = ['codigo' => 'ERI 601', 'nombre' => 'Participación trabajadores', 'problema' => 'El pasivo de participación creció ' . number_format($ptDelta, 2) . ' pero no hay gasto de participación en resultados (ERI 601).'];
        $hallazgos[] = $this->hallazgo('impuestos_sin_gasto', 'Impuesto o participación registrados solo como pasivo', empty($itemsImp) ? 'ok' : 'advertencia', ['ERI', 'EFE'],
            'Es lo típico de un cierre migrado: el impuesto a la renta y la participación de trabajadores se crearon como pasivo al distribuir el resultado, sin pasar por el gasto. El ERI queda antes de impuestos y la conciliación del EFE no cuadra.',
            $itemsImp,
            'Registre al último día del período un asiento de diario: Debe en la cuenta de gasto (ERI 603 o 601) y Haber en la cuenta pivote de cierre (clase 7) o en la que recibió el resultado.');

        // Resultado del ejercicio: el ESF (30701 ganancia / 30702 pérdida) y el ERI (707) deben
        // coincidir. El ESF lo toma del balance (ingresos − costos − gastos de las cuentas 4/5/6,
        // más la cuenta pivote de cierre) y el ERI de sus propios casilleros, así que difieren
        // cuando hay cuentas de resultados sin mapear, la cuenta de cierre tiene saldo propio
        // (doble conteo) o hay saldo en la cuenta pivote de los cierres migrados.
        $resEsf = $v('ESF', '30701') + $v('ESF', '30702');
        $resEri = $v('ERI', '707');
        $difRes = round($resEsf - $resEri, 2);
        $itemsRes = [];
        if (abs($difRes) >= 0.01) {
            $itemsRes[] = ['codigo' => 'ESF 30701/30702', 'nombre' => 'Ganancia (pérdida) neta del período en el balance', 'problema' => number_format($resEsf, 2)];
            $itemsRes[] = ['codigo' => 'ERI 707', 'nombre' => 'Ganancia (pérdida) neta del período en resultados', 'problema' => number_format($resEri, 2)];
            $itemsRes[] = ['codigo' => 'Diferencia', 'nombre' => 'ESF − ERI', 'problema' => number_format($difRes, 2)];

            // Pistas concretas sobre el origen de la diferencia
            $ctaCierreIds = array_filter([$ctasCierre['utilidad']['id'] ?? null, $ctasCierre['perdida']['id'] ?? null]);
            $pivote = 0.0;
            foreach ($cuentas as $c) {
                $neto = (float) $c['haber'] - (float) $c['debe'];
                if (str_starts_with((string) $c['codigo'], '7')) $pivote += $neto;
                if (in_array((int) $c['id_cuenta'], $ctaCierreIds, true) && round($neto, 2) != 0) {
                    $itemsRes[] = ['codigo' => $c['codigo'], 'nombre' => (string) $c['nombre'],
                        'problema' => 'Cuenta de cierre con movimiento propio en el período (' . number_format($neto, 2) . '): su saldo se suma al resultado calculado y el ESF lo cuenta dos veces'];
                }
            }
            if (round($pivote, 2) != 0) {
                $itemsRes[] = ['codigo' => 'Clase 7', 'nombre' => 'Cuenta pivote de cierres migrados',
                    'problema' => 'Saldo ' . number_format($pivote, 2) . ': el balance lo suma al resultado y el ERI no lo ve'];
            }
            foreach ($cuentas as $c) {
                if ((int) $c['nivel'] !== 5) continue;
                $clase = substr((string) $c['codigo'], 0, 1);
                if (!in_array($clase, ['4', '5', '6'], true)) continue;
                $eri = trim((string) ($c['supercias_eri'] ?? ''));
                $neto = round((float) $c['debe'] - (float) $c['haber'], 2);
                if ($neto == 0) continue;
                if ($eri === '' || !isset($eriEstructura[$eri]) || $eriEstructura[$eri] !== '') {
                    $itemsRes[] = ['codigo' => $c['codigo'], 'nombre' => (string) $c['nombre'],
                        'problema' => 'Cuenta de resultados que entra al balance pero no al ERI (' . number_format($neto, 2) . ')'];
                }
            }
        }
        $hallazgos[] = $this->hallazgo('resultado_esf_eri', 'El resultado del ejercicio coincide entre el ESF y el ERI', empty($itemsRes) ? 'ok' : 'bloqueante', ['ESF', 'ERI', 'ECP'],
            'La ganancia (pérdida) neta del período del balance (ESF 30701 / 30702) debe ser igual a la del estado de resultados (ERI 707). Supercías rechaza los estados si no cuadran entre sí.',
            $itemsRes, 'Revise las cuentas listadas: mapee las que falten, y si la cuenta de cierre tiene saldo propio, el resultado se está contando dos veces.');

        $ecpDif = [];
        foreach ($cas['ECP'] ?? [] as $key => $x) {
            $partes = explode('.', (string) $key, 2);
            if (count($partes) < 2 || $partes[0] !== '99') continue;
            $col = $partes[1];
            $d = round((float) $x['valor'] - ((float) ($cas['ECP']['9901.' . $col]['valor'] ?? 0) + (float) ($cas['ECP']['9902.' . $col]['valor'] ?? 0)), 2);
            if (abs($d) >= 0.01) $ecpDif[] = ['codigo' => 'Columna ' . $col, 'nombre' => 'Diferencia 99 − (9901 + 9902)', 'problema' => number_format($d, 2)];
        }
        $efeC = $ev['efe']['controles'];
        $efeItems = [];
        if (abs($efeC['dif_9507_vs_esf']) >= 0.01) $efeItems[] = ['codigo' => '9507', 'nombre' => 'Efectivo al final vs ESF 10101', 'problema' => number_format($efeC['dif_9507_vs_esf'], 2)];
        if (abs($efeC['dif_9505_vs_movimiento']) >= 0.01) $efeItems[] = ['codigo' => '9505', 'nombre' => 'Flujo neto vs movimiento contable del efectivo', 'problema' => number_format($efeC['dif_9505_vs_movimiento'], 2)];
        if (abs($efeC['dif_9820_vs_9501']) >= 0.01) $efeItems[] = ['codigo' => '9820', 'nombre' => 'Conciliación vs flujo de operación', 'problema' => number_format($efeC['dif_9820_vs_9501'], 2)];
        $otrosEfe = 0.0;
        foreach ($ev['efe']['asientos'] as $a) foreach ($a['asignaciones'] as $x) if ($x['otros']) $otrosEfe += abs($x['valor']);
        if ($otrosEfe >= 0.01) $efeItems[] = ['codigo' => 'Otros', 'nombre' => 'Movimientos en "otros cobros / pagos" por falta de regla', 'problema' => number_format($otrosEfe, 2) . ' (ver detalle en Ver EFE)'];

        $cuadres = [
            'esf' => ['activo' => $activo, 'pasivo' => $pasivo, 'patrimonio' => $patrimonio, 'diferencia' => $difEsf],
            'ecp' => $ecpDif,
            'efe' => $efeC,
        ];
        $hallazgos[] = $this->hallazgo('cuadre_esf', 'Cuadre del balance (activo = pasivo + patrimonio)', abs($difEsf) < 0.01 ? 'ok' : 'advertencia', ['ESF'],
            'El ESF de Supercías debe cuadrar. Si no, suele faltar el mapeo de alguna cuenta o la cuenta de cierre del ejercicio.',
            abs($difEsf) < 0.01 ? [] : [['codigo' => 'ESF', 'nombre' => 'Activo ' . number_format($activo, 2) . ' − Pasivo ' . number_format($pasivo, 2) . ' − Patrimonio ' . number_format($patrimonio, 2), 'problema' => 'Diferencia ' . number_format($difEsf, 2)]],
            'Revise los hallazgos anteriores.');
        $hallazgos[] = $this->hallazgo('cuadre_ecp', 'Cuadre del ECP', empty($ecpDif) ? 'ok' : 'advertencia', ['ECP'],
            'En cada columna, el saldo final (99) debe ser igual al saldo inicial más los cambios del año (9901 + 9902).',
            $ecpDif, 'Use "Ver ECP" para ver qué cuentas alimentan la columna.');
        $hallazgos[] = $this->hallazgo('cuadre_efe', 'Cuadre del EFE', empty($efeItems) ? 'ok' : 'advertencia', ['EFE'],
            'Tres controles deben estar en cero: efectivo final contra el ESF, flujo neto contra el movimiento contable del efectivo, y conciliación contra el flujo de operación.',
            $efeItems, 'Use "Ver EFE" para ver el detalle por asiento.');

        $resumen = ['bloqueantes' => 0, 'advertencias' => 0, 'ok' => 0];
        foreach ($hallazgos as $h) {
            if ($h['severidad'] === 'bloqueante') $resumen['bloqueantes']++;
            elseif ($h['severidad'] === 'advertencia') $resumen['advertencias']++;
            else $resumen['ok']++;
        }

        return ['hallazgos' => $hallazgos, 'resumen' => $resumen, 'cuadres' => $cuadres];
    }

    private function hallazgo(string $clave, string $titulo, string $severidad, array $afecta, string $descripcion, array $items, string $accion, string $enlace = ''): array
    {
        if (empty($items) && $severidad !== 'ok') $severidad = 'ok';
        return [
            'clave'       => $clave,
            'titulo'      => $titulo,
            'severidad'   => $severidad,
            'afecta'      => $afecta,
            'descripcion' => $descripcion,
            'items'       => array_values($items),
            'accion'      => $accion,
            'enlace'      => $enlace,
        ];
    }
}
