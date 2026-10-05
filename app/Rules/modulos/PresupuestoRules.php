<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio de Presupuestos. Los mensajes llevan `|#id` del campo cuando aplica,
 * igual que el resto de módulos (el JS enfoca el control).
 */
class PresupuestoRules
{
    public const TIPOS_PERIODO = ['anual', 'mensual', 'personalizado'];
    public const ALCANCES      = ['empresa', 'centro_costo', 'proyecto'];

    /** Cabecera. Devuelve los datos normalizados (periodo_desde/hasta como primer día del mes). */
    public function validarCabecera(array $d): array
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del presupuesto es obligatorio.|#pre_nombre');
        }
        if (mb_strlen($nombre) > 150) {
            throw new \InvalidArgumentException('El nombre no puede pasar de 150 caracteres.|#pre_nombre');
        }

        $tipo = (string) ($d['tipo_periodo'] ?? 'anual');
        if (!in_array($tipo, self::TIPOS_PERIODO, true)) {
            throw new \InvalidArgumentException('El tipo de período no es válido.|#pre_tipo_periodo');
        }

        // Período: anual = año; mensual = un mes; personalizado = desde/hasta (YYYY-MM).
        if ($tipo === 'anual') {
            $anio = (int) ($d['anio'] ?? 0);
            if ($anio < 2000 || $anio > 2100) {
                throw new \InvalidArgumentException('Indique el año del presupuesto.|#pre_anio');
            }
            $desde = sprintf('%04d-01-01', $anio);
            $hasta = sprintf('%04d-12-01', $anio);
        } elseif ($tipo === 'mensual') {
            $mes = (string) ($d['mes'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
                throw new \InvalidArgumentException('Indique el mes del presupuesto.|#pre_mes');
            }
            $desde = $hasta = $mes . '-01';
        } else {
            $d1 = (string) ($d['desde'] ?? '');
            $d2 = (string) ($d['hasta'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}$/', $d1) || !preg_match('/^\d{4}-\d{2}$/', $d2)) {
                throw new \InvalidArgumentException('Indique el mes inicial y el mes final del período.|#pre_desde');
            }
            if ($d2 < $d1) {
                throw new \InvalidArgumentException('El mes final debe ser igual o posterior al inicial.|#pre_hasta');
            }
            $desde = $d1 . '-01';
            $hasta = $d2 . '-01';
            if (self::mesesEntre($desde, $hasta) > 60) {
                throw new \InvalidArgumentException('El período no puede pasar de 60 meses.|#pre_hasta');
            }
        }

        $alcance = (string) ($d['alcance'] ?? 'empresa');
        if (!in_array($alcance, self::ALCANCES, true)) {
            throw new \InvalidArgumentException('El alcance no es válido.|#pre_alcance');
        }
        $idCc = (int) ($d['id_centro_costo'] ?? 0);
        $idPr = (int) ($d['id_proyecto'] ?? 0);
        if ($alcance === 'centro_costo' && $idCc <= 0) {
            throw new \InvalidArgumentException('Elija el centro de costo.|#pre_id_centro_costo');
        }
        if ($alcance === 'proyecto' && $idPr <= 0) {
            throw new \InvalidArgumentException('Elija el proyecto.|#pre_id_proyecto');
        }

        $am = (float) ($d['umbral_amarillo'] ?? 90);
        $ro = (float) ($d['umbral_rojo'] ?? 100);
        if ($am <= 0 || $am > 200 || $ro < $am || $ro > 300) {
            throw new \InvalidArgumentException('Los umbrales del semáforo deben ser porcentajes válidos (amarillo ≤ rojo).|#pre_umbral_amarillo');
        }

        return [
            'nombre'          => $nombre,
            'tipo_periodo'    => $tipo,
            'periodo_desde'   => $desde,
            'periodo_hasta'   => $hasta,
            'alcance'         => $alcance,
            'id_centro_costo' => $alcance === 'centro_costo' ? $idCc : null,
            'id_proyecto'     => $alcance === 'proyecto' ? $idPr : null,
            'base_alicuotas'  => !empty($d['base_alicuotas']) && $d['base_alicuotas'] !== '0' && $d['base_alicuotas'] !== 'false',
            'umbral_amarillo' => round($am, 2),
            'umbral_rojo'     => round($ro, 2),
            'observaciones'   => trim((string) ($d['observaciones'] ?? '')) ?: null,
        ];
    }

    /**
     * Líneas que envía la grilla: cada una con id (0 si es nueva), id_cuenta, id_rubro y valores
     * {'YYYY-MM': monto}. Comprueba cuentas válidas (clase 4/5/6 de la empresa), sin repetir, y
     * montos no negativos dentro del período.
     *
     * @param array $cuentas Cuentas de la empresa indexadas por id (PresupuestoRepository::getCuentasPorIds)
     * @param string[] $meses Meses del período ('YYYY-MM')
     */
    public function validarLineas(array $lineas, array $cuentas, array $meses): array
    {
        $vistas = [];
        $out = [];
        foreach ($lineas as $i => $l) {
            $fila = $i + 1;
            $idCuenta = (int) ($l['id_cuenta'] ?? 0);
            if ($idCuenta <= 0 || !isset($cuentas[$idCuenta])) {
                throw new \InvalidArgumentException("Fila {$fila}: la cuenta no existe en el plan de cuentas de la empresa.");
            }
            $codigo = (string) $cuentas[$idCuenta]['codigo'];
            if (!preg_match('/^[456]/', $codigo)) {
                throw new \InvalidArgumentException("Fila {$fila}: solo se presupuestan cuentas de ingresos (4), costos y gastos (5, 6). La cuenta {$codigo} no lo es.");
            }
            if (isset($vistas[$idCuenta])) {
                throw new \InvalidArgumentException("Fila {$fila}: la cuenta {$codigo} está repetida.");
            }
            $vistas[$idCuenta] = true;

            $valores = [];
            foreach ((array) ($l['valores'] ?? []) as $ym => $m) {
                if (!in_array((string) $ym, $meses, true)) {
                    continue; // meses fuera del período se ignoran
                }
                $monto = (float) str_replace(',', '', (string) $m);
                if ($monto < 0) {
                    throw new \InvalidArgumentException("Fila {$fila} ({$codigo}): el monto de {$ym} no puede ser negativo.");
                }
                $valores[(string) $ym] = round($monto, 2);
            }
            $out[] = [
                'id'        => (int) ($l['id'] ?? 0),
                'id_cuenta' => $idCuenta,
                'id_rubro'  => (int) ($l['id_rubro'] ?? 0) ?: null,
                'orden'     => $i,
                'valores'   => $valores,
            ];
        }
        return $out;
    }

    public function validarAprobacion(array $d): array
    {
        $acta = trim((string) ($d['acta'] ?? ''));
        if ($acta === '') {
            throw new \InvalidArgumentException('Indique el acta o documento con que se aprobó.|#pre_acta');
        }
        return ['acta' => mb_substr($acta, 0, 120), 'observacion' => trim((string) ($d['observacion'] ?? '')) ?: null];
    }

    /** Meses 'YYYY-MM' entre dos primeros de mes, inclusive. */
    public static function listarMeses(string $desde, string $hasta): array
    {
        $out = [];
        $d = new \DateTimeImmutable($desde);
        $h = new \DateTimeImmutable($hasta);
        while ($d <= $h) {
            $out[] = $d->format('Y-m');
            $d = $d->modify('+1 month');
        }
        return $out;
    }

    public static function mesesEntre(string $desde, string $hasta): int
    {
        return count(self::listarMeses($desde, $hasta));
    }
}
