<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * PDF del aviso de asientos contables pendientes.
 *
 * El aviso que queda al terminar «Generar ahora» (qué falta configurar, en qué documentos y
 * qué cuentas) lo acumula el navegador paso a paso — ver public/js/modulos/asientos_pendientes.js —
 * y no se guarda en el servidor. Este servicio recibe ese mismo resultado y lo imprime, para que
 * el usuario lo tenga a mano mientras configura sin volver a generar. No consulta ni escribe nada
 * de contabilidad: solo da formato a los textos recibidos (acotados y escapados).
 */
class AvisoAsientosPdfService
{
    private const MAX_ACCIONES = 200;
    private const MAX_DETALLES = 500;
    private const MAX_INFO     = 100;
    private const MAX_TEXTO    = 2000;

    /**
     * Limpia y acota lo que envía el navegador.
     *
     * @return array{acciones: list<array{texto: string, detalles: list<string>}>, info: list<string>,
     *               generados: int, pendientes: int, interrumpido: bool}
     */
    public function normalizar(array $datos): array
    {
        $acciones = [];
        foreach (array_slice($this->lista($datos['acciones'] ?? null), 0, self::MAX_ACCIONES) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $texto = $this->texto($a['texto'] ?? '');
            if ($texto === '') {
                continue;
            }
            $detalles = [];
            foreach (array_slice($this->lista($a['detalles'] ?? null), 0, self::MAX_DETALLES) as $d) {
                $d = $this->texto($d);
                if ($d !== '') {
                    $detalles[] = $d;
                }
            }
            $acciones[] = ['texto' => $texto, 'detalles' => $detalles];
        }

        $info = [];
        foreach (array_slice($this->lista($datos['info'] ?? null), 0, self::MAX_INFO) as $i) {
            $i = $this->texto($i);
            if ($i !== '') {
                $info[] = $i;
            }
        }

        return [
            'acciones'     => $acciones,
            'info'         => $info,
            'generados'    => max(0, (int) ($datos['generados'] ?? 0)),
            'pendientes'   => max(0, (int) ($datos['pendientes'] ?? 0)),
            'interrumpido' => !empty($datos['interrumpido']),
        ];
    }

    /** Genera el PDF y lo envía al navegador. */
    public function generar(int $idEmpresa, string $usuario, array $datos): void
    {
        $aviso = $this->normalizar($datos);

        $empresa = (new \App\models\Empresa())->getPorId($idEmpresa);
        $nombreEmpresa = (string) ($empresa['nombre'] ?? '');

        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es', true, 'UTF-8');
        $html2pdf->writeHTML($this->html($aviso, $nombreEmpresa, $usuario));
        $html2pdf->output('Asientos_pendientes_' . date('Ymd_His') . '.pdf', 'D');
    }

    private function html(array $aviso, string $nombreEmpresa, string $usuario): string
    {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $h  = '<style>
            h1 { margin:0; font-size:14pt; color:#333; text-align:center; }
            h2 { margin:3px 0 0 0; font-size:10pt; color:#666; text-align:center; }
            .meta { font-size:8pt; color:#555; text-align:center; margin-top:4px; }
            .bloque { font-size:9pt; margin-top:10px; }
            .titulo { font-size:10pt; font-weight:bold; margin-top:14px; border-bottom:1px solid #999; padding-bottom:2px; }
            table { width:100%; border-collapse:collapse; margin-top:6px; }
            td { font-size:9pt; padding:3px 4px; vertical-align:top; }
            td.n { width:6%; font-weight:bold; }
            td.t { width:94%; }
            td.accion { font-weight:bold; border-top:1px solid #ccc; }
            td.det { font-size:8pt; color:#333; }
            .pend { font-size:10pt; font-weight:bold; margin-top:14px; border-top:1px solid #999; padding-top:5px; }
        </style>';
        $h .= '<page backtop="10mm" backbottom="12mm" backleft="10mm" backright="10mm">';
        $h .= '<page_footer><div style="font-size:7pt;color:#777;text-align:right;">Página [[page_cu]] de [[page_nb]]</div></page_footer>';
        $h .= '<h1>' . $e($nombreEmpresa !== '' ? $nombreEmpresa : 'Asientos contables') . '</h1>';
        $h .= '<h2>ASIENTOS CONTABLES PENDIENTES DE GENERAR</h2>';
        $h .= '<div class="meta">Generado el ' . date('d-m-Y H:i:s') . ($usuario !== '' ? ' por ' . $e($usuario) : '') . '</div>';

        if ($aviso['interrumpido']) {
            $h .= '<div class="bloque">La generación fue interrumpida por el usuario: este aviso solo cubre los módulos que se alcanzaron a revisar.</div>';
        }
        if ($aviso['generados'] > 0) {
            $h .= '<div class="bloque">Se generaron <b>' . $aviso['generados'] . '</b> asiento(s) contable(s).</div>';
        }

        if ($aviso['acciones']) {
            $h .= '<div class="titulo">Para generar los asientos que faltan</div><table>';
            foreach ($aviso['acciones'] as $i => $a) {
                $h .= '<tr><td class="n accion">' . ($i + 1) . '.</td><td class="t accion">' . $e($a['texto']) . '.</td></tr>';
                foreach ($a['detalles'] as $d) {
                    $h .= '<tr><td class="n"></td><td class="t det">- ' . $e($d) . '</td></tr>';
                }
            }
            $h .= '</table>';
        }

        if ($aviso['info']) {
            $h .= '<div class="titulo">Información</div><table>';
            foreach ($aviso['info'] as $i) {
                $h .= '<tr><td class="n"></td><td class="t">- ' . $e($i) . '</td></tr>';
            }
            $h .= '</table>';
        }

        if ($aviso['pendientes'] > 0) {
            $h .= '<div class="pend">Quedan pendientes ' . $aviso['pendientes'] . ' asiento(s) por generar'
                . ($aviso['interrumpido'] ? ' en los módulos revisados' : '') . '.</div>';
        } elseif (!$aviso['acciones']) {
            $h .= '<div class="pend">No quedaron asientos por generar.</div>';
        }

        return $h . '</page>';
    }

    private function lista($valor): array
    {
        return is_array($valor) ? array_values($valor) : [];
    }

    private function texto($valor): string
    {
        if (!is_scalar($valor)) {
            return '';
        }
        $t = trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');
        return mb_substr($t, 0, self::MAX_TEXTO, 'UTF-8');
    }
}
