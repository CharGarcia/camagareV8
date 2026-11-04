<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\Rules\modulos\ProduccionOrdenRules;

/**
 * PDF de la orden de producción / registro de lote: datos de la orden, insumos teóricos y
 * reales con los lotes consumidos, salidas con su costo, instrucciones de la fórmula y
 * espacios de firma (lo que pide una auditoría de buenas prácticas de manufactura).
 * Html2Pdf: el ancho va en CADA celda, no basta el <th>.
 */
class ProduccionOrdenPdfService
{
    private static function e($v): string
    {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    }

    private static function c($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
    }

    private static function m($v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    private static function f(?string $v): string
    {
        return $v ? date('d-m-Y', strtotime($v)) : '';
    }

    /** 'D' descarga, 'S' devuelve el contenido. */
    public function generar(array $o, int $idEmpresa, string $destino = 'D'): string
    {
        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
        $insumos = array_values(array_filter($o['lineas'], fn($l) => $l['rol'] === 'insumo'));
        $salidas = array_values(array_filter($o['lineas'], fn($l) => $l['rol'] !== 'insumo'));
        $consumos = array_values(array_filter($o['movimientos'], fn($m) => $m['tipo'] === 'consumo'));
        $roles = ['principal' => 'Principal', 'coproducto' => 'Coproducto', 'subproducto' => 'Subproducto', 'desperdicio' => 'Desperdicio'];
        $cerrada = $o['estado'] === 'cerrada';

        ob_start();
        ?>
        <style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; font-size: 8pt; }
            th { background: #eef2f6; border: 1px solid #b9c2cc; padding: 3px; text-align: left; }
            td { border: 1px solid #b9c2cc; padding: 3px; }
            .r { text-align: right; } .c { text-align: center; }
            .t { font-size: 13pt; font-weight: bold; text-align: center; }
            .s { font-size: 9pt; font-weight: bold; margin: 8px 0 3px; color: #2c3e50; }
            .k { background: #f7f9fb; font-weight: bold; }
            .firma { border: none; border-top: 1px solid #333; text-align: center; padding-top: 3px; }
        </style>
        <page backtop="10mm" backbottom="12mm" backleft="10mm" backright="10mm">
            <page_footer><div style="text-align:right; font-size:7pt; color:#777;">Página [[page_cu]] de [[page_nb]]</div></page_footer>
            <div style="text-align:center; font-size:10pt; font-weight:bold;"><?= self::e($empresa['nombre'] ?? '') ?></div>
            <div style="text-align:center; font-size:8pt; color:#555;">RUC <?= self::e($empresa['ruc'] ?? '') ?></div>
            <div class="t">ORDEN DE PRODUCCIÓN / REGISTRO DE LOTE</div>
            <div style="text-align:center; font-size:11pt; margin-bottom:6px;"><?= self::e($o['numero']) ?> · <?= self::e(ProduccionOrdenRules::ESTADOS[$o['estado']] ?? $o['estado']) ?></div>

            <table style="width:100%">
                <tr>
                    <td class="k" style="width:18%">Producto</td>
                    <td style="width:47%"><?= self::e(trim($o['producto_codigo'] . ' - ' . $o['producto_nombre'], ' -')) ?></td>
                    <td class="k" style="width:15%">Fórmula</td>
                    <td style="width:20%"><?= self::e($o['formula_codigo'] ?? '') ?> v<?= (int) $o['version_formula'] ?></td>
                </tr>
                <tr>
                    <td class="k" style="width:18%">Lote</td>
                    <td style="width:47%"><?= self::e($o['lote']) ?></td>
                    <td class="k" style="width:15%">Caducidad</td>
                    <td style="width:20%"><?= self::f($o['fecha_caducidad']) ?></td>
                </tr>
                <tr>
                    <td class="k" style="width:18%">Planificado</td>
                    <td style="width:47%"><?= self::c($o['cantidad_planificada']) ?> <?= self::e($o['medida_nombre'] ?? '') ?></td>
                    <td class="k" style="width:15%">Producido</td>
                    <td style="width:20%"><?= $cerrada ? self::c($o['cantidad_producida']) . ' ' . self::e($o['medida_nombre'] ?? '') : '' ?></td>
                </tr>
                <tr>
                    <td class="k" style="width:18%">Fecha</td>
                    <td style="width:47%"><?= self::f($o['fecha_inicio']) ?></td>
                    <td class="k" style="width:15%">Cierre</td>
                    <td style="width:20%"><?= self::f($o['fecha_fin']) ?></td>
                </tr>
                <tr>
                    <td class="k" style="width:18%">Bodegas</td>
                    <td style="width:47%"><?= self::e($o['bodega_insumos_nombre'] ?? '') ?> → <?= self::e($o['bodega_destino_nombre'] ?? '') ?></td>
                    <td class="k" style="width:15%">Responsable</td>
                    <td style="width:20%"><?= self::e($o['responsable'] ?? '') ?></td>
                </tr>
            </table>

            <div class="s">INSUMOS</div>
            <table style="width:100%">
                <thead><tr>
                    <th style="width:44%">Insumo</th><th style="width:10%">Unidad</th>
                    <th style="width:12%" class="r">Teórico</th><th style="width:12%" class="r">Consumido</th>
                    <th style="width:10%" class="r">Diferencia</th><th style="width:12%" class="r">Costo</th>
                </tr></thead>
                <tbody>
                <?php foreach ($insumos as $l): ?>
                    <tr>
                        <td style="width:44%"><?= self::e(trim($l['producto_codigo'] . ' - ' . $l['producto_nombre'], ' -')) ?><?= !empty($l['fuera_formula']) ? ' (fuera de fórmula)' : '' ?></td>
                        <td style="width:10%"><?= self::e($l['medida_nombre'] ?? '') ?></td>
                        <td style="width:12%" class="r"><?= self::c($l['cantidad_teorica']) ?></td>
                        <td style="width:12%" class="r"><?= self::c($l['cantidad_real']) ?></td>
                        <td style="width:10%" class="r"><?= self::c((float) $l['cantidad_real'] - (float) $l['cantidad_teorica']) ?></td>
                        <td style="width:12%" class="r"><?= self::m($l['costo_real']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($consumos): ?>
                <div class="s">LOTES DE INSUMO CONSUMIDOS</div>
                <table style="width:100%">
                    <thead><tr>
                        <th style="width:10%">Fecha</th><th style="width:40%">Insumo</th><th style="width:16%">Lote</th>
                        <th style="width:11%">Caducidad</th><th style="width:11%" class="r">Cantidad</th><th style="width:12%">Registró</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($consumos as $m): ?>
                        <tr>
                            <td style="width:10%"><?= self::f($m['fecha']) ?></td>
                            <td style="width:40%"><?= self::e($m['producto_nombre']) ?></td>
                            <td style="width:16%"><?= self::e($m['numero_lote'] ?: 'Sin lote') ?></td>
                            <td style="width:11%"><?= self::f($m['fecha_caducidad']) ?></td>
                            <td style="width:11%" class="r"><?= self::c($m['cantidad']) ?><?= $m['peso_inicial'] !== null ? '<br><span style="font-size:6.5pt;color:#666;">' . self::c($m['peso_inicial']) . ' − ' . self::c($m['peso_final']) . '</span>' : '' ?></td>
                            <td style="width:12%"><?= self::e($m['usuario_nombre'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="s">PRODUCTO OBTENIDO</div>
            <table style="width:100%">
                <thead><tr>
                    <th style="width:14%">Tipo</th><th style="width:40%">Producto</th><th style="width:12%" class="r">Teórico</th>
                    <th style="width:12%" class="r">Real</th><th style="width:11%" class="r">Costo unit.</th><th style="width:11%" class="r">Costo</th>
                </tr></thead>
                <tbody>
                <?php foreach ($salidas as $l): ?>
                    <tr>
                        <td style="width:14%"><?= self::e($roles[$l['rol']] ?? $l['rol']) ?></td>
                        <td style="width:40%"><?= self::e($l['id_producto'] ? trim($l['producto_codigo'] . ' - ' . $l['producto_nombre'], ' -') : ($l['descripcion'] ?? '')) ?></td>
                        <td style="width:12%" class="r"><?= self::c($l['cantidad_teorica']) ?></td>
                        <td style="width:12%" class="r"><?= $cerrada ? self::c($l['cantidad_real']) : '' ?></td>
                        <td style="width:11%" class="r"><?= $cerrada ? number_format((float) $l['costo_unitario'], 4) : '' ?></td>
                        <td style="width:11%" class="r"><?= $cerrada ? self::m($l['costo_real']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($cerrada): ?>
                <div class="s">COSTO DE LA ORDEN</div>
                <table style="width:60%">
                    <tr><td style="width:60%">Materiales consumidos</td><td style="width:40%" class="r"><?= self::m($o['costo_materiales']) ?></td></tr>
                    <tr><td style="width:60%">Mano de obra aplicada</td><td style="width:40%" class="r"><?= self::m($o['costo_mano_obra']) ?></td></tr>
                    <tr><td style="width:60%">Costos indirectos aplicados</td><td style="width:40%" class="r"><?= self::m($o['costo_cif']) ?></td></tr>
                    <?php if ((float) $o['costo_otros'] > 0): ?><tr><td style="width:60%">Otros costos</td><td style="width:40%" class="r"><?= self::m($o['costo_otros']) ?></td></tr><?php endif; ?>
                    <?php if ((float) $o['costo_merma'] > 0): ?><tr><td style="width:60%">Merma anormal (a gasto) — <?= self::c($o['cantidad_merma']) ?></td><td style="width:40%" class="r">-<?= self::m($o['costo_merma']) ?></td></tr><?php endif; ?>
                    <tr><td class="k" style="width:60%">Costo que entra al inventario</td><td class="k r" style="width:40%"><?= self::m($o['costo_total']) ?></td></tr>
                </table>
            <?php endif; ?>

            <?php if (trim((string) ($o['formula_instrucciones'] ?? '')) !== ''): ?>
                <div class="s">INSTRUCCIONES DE FABRICACIÓN</div>
                <table style="width:100%"><tr><td style="width:100%"><?= nl2br(self::e($o['formula_instrucciones'])) ?></td></tr></table>
            <?php endif; ?>
            <?php if (trim((string) ($o['observaciones'] ?? '')) !== ''): ?>
                <div class="s">OBSERVACIONES</div>
                <table style="width:100%"><tr><td style="width:100%"><?= nl2br(self::e($o['observaciones'])) ?></td></tr></table>
            <?php endif; ?>

            <table style="width:100%; margin-top:40px;">
                <tr>
                    <td style="width:30%; border:none;" class="firma">Elaboró / Pesó</td>
                    <td style="width:5%; border:none;"></td>
                    <td style="width:30%; border:none;" class="firma">Verificó</td>
                    <td style="width:5%; border:none;"></td>
                    <td style="width:30%; border:none;" class="firma">Aprobó (Calidad)</td>
                </tr>
            </table>
        </page>
        <?php
        $html = ob_get_clean();

        $pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
        $pdf->writeHTML($html);
        return (string) $pdf->output('Orden_Produccion_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $o['numero']) . '.pdf', $destino);
    }
}
