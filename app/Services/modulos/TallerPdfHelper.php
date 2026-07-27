<?php

declare(strict_types=1);

namespace App\Services\modulos;

use TCPDF;

/**
 * Utilidades compartidas por los PDF del módulo Taller (orden de trabajo e
 * informe técnico): resolución del logo, recorte de texto, etiquetas legibles
 * de los códigos internos y monto en letras.
 *
 * Existe para que ambos documentos hablen el mismo idioma visual y para no
 * repetir helpers entre servicios.
 */
final class TallerPdfHelper
{
    /** Busca el logo de la empresa en las rutas que usa el resto del sistema. */
    public static function resolverLogo(array $empresa): string
    {
        $rutas = array_filter([$empresa['logo_ruta'] ?? '', $empresa['logo'] ?? '']);
        foreach ($rutas as $ruta) {
            $clean = ltrim((string) $ruta, '/');
            if (strpos($clean, 'sistema/public/') === 0) {
                $clean = substr($clean, strlen('sistema/public/'));
            } elseif (strpos($clean, 'sistema/') === 0) {
                $clean = substr($clean, strlen('sistema/'));
            }
            if (strpos($clean, 'public/') === 0) {
                $clean = substr($clean, strlen('public/'));
            }
            foreach ([\MVC_ROOT . '/public/' . $clean, \MVC_ROOT . '/' . $clean] as $cand) {
                if (is_file($cand)) {
                    return $cand;
                }
            }
        }
        return '';
    }

    /** Recorta el texto al ancho disponible agregando puntos suspensivos. */
    public static function ajustar(TCPDF $pdf, string $texto, float $ancho): string
    {
        $texto = trim($texto);
        if ($texto === '' || $pdf->GetStringWidth($texto) <= $ancho) {
            return $texto;
        }
        while ($texto !== '' && $pdf->GetStringWidth($texto . '…') > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }
        return rtrim($texto) . '…';
    }

    /** Barra de título de sección, con el mismo estilo en los dos documentos. */
    public static function tituloSeccion(TCPDF $pdf, float $x, float $y, float $ancho, string $titulo): float
    {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(90, 90, 90);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(90, 90, 90);
        $pdf->SetLineWidth(0.2);
        $pdf->Cell($ancho, 5.5, ' ' . $titulo, 1, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        return $pdf->GetY();
    }

    public static function fecha($valor, string $formato = 'd/m/Y H:i'): string
    {
        if (empty($valor)) return '—';
        $ts = strtotime((string) $valor);
        return $ts ? date($formato, $ts) : (string) $valor;
    }

    /** Diferencia entre dos marcas de tiempo, en horas y minutos legibles. */
    public static function duracion($desde, $hasta): string
    {
        if (empty($desde) || empty($hasta)) return '—';
        $d = strtotime((string) $desde);
        $h = strtotime((string) $hasta);
        if ($d === false || $h === false || $h < $d) return '—';

        $min = (int) round(($h - $d) / 60);
        if ($min < 60) return $min . ' min';

        $horas = intdiv($min, 60);
        $resto = $min % 60;
        if ($horas < 24) {
            return $horas . 'h' . ($resto > 0 ? ' ' . $resto . 'min' : '');
        }
        $dias = intdiv($horas, 24);
        return $dias . ' día' . ($dias > 1 ? 's' : '') . ' ' . ($horas % 24) . 'h';
    }

    public static function etiquetaEstado(string $estado): string
    {
        return [
            'recepcion'       => 'Recepción',
            'diagnostico'     => 'Diagnóstico',
            'presupuesto'     => 'Presupuesto',
            'aprobada'        => 'Aprobada',
            'en_proceso'      => 'En proceso',
            'control_calidad' => 'Control de calidad',
            'terminada'       => 'Terminada',
            'entregada'       => 'Entregada',
            'facturada'       => 'Facturada',
            'anulada'         => 'Anulada',
        ][$estado] ?? ucfirst($estado);
    }

    public static function etiquetaEstadoEtapa(string $estado): string
    {
        return [
            'pendiente'  => 'Pendiente',
            'en_proceso' => 'En proceso',
            'terminada'  => 'Terminada',
            'omitida'    => 'Omitida',
        ][$estado] ?? ucfirst($estado);
    }

    public static function etiquetaEstadoLinea(string $estado): string
    {
        return [
            'sugerida'  => 'Sugerida',
            'aprobada'  => 'Aprobada',
            'rechazada' => 'Rechazada',
            'ejecutada' => 'Ejecutada',
        ][$estado] ?? ucfirst($estado);
    }

    public static function etiquetaTipoLinea(string $tipo): string
    {
        return [
            'repuesto'  => 'Repuesto',
            'mano_obra' => 'Mano obra',
            'insumo'    => 'Insumo',
            'tercero'   => 'Terceros',
        ][$tipo] ?? ucfirst($tipo);
    }

    public static function etiquetaGrupo(string $grupo): string
    {
        return [
            'accesorios' => 'ACCESORIOS',
            'carroceria' => 'CARROCERÍA',
            'documentos' => 'DOCUMENTOS',
            'niveles'    => 'NIVELES',
        ][$grupo] ?? strtoupper($grupo);
    }

    public static function etiquetaValorChecklist(string $valor): string
    {
        return [
            'si'      => 'SÍ',
            'no'      => 'NO',
            'na'      => 'N/A',
            'bueno'   => 'Bueno',
            'regular' => 'Regular',
            'malo'    => 'Malo',
        ][$valor] ?? strtoupper($valor);
    }

    public static function etiquetaMedioAprobacion(string $medio): string
    {
        return [
            'presencial' => 'Presencial',
            'telefono'   => 'Teléfono',
            'whatsapp'   => 'WhatsApp',
            'correo'     => 'Correo electrónico',
            'sistema'    => 'Sistema',
        ][$medio] ?? ucfirst($medio);
    }

    public static function montoEnLetras(float $monto): string
    {
        require_once \MVC_ROOT . '/app/validadores/numero_letras.php';
        $str = number_format($monto, 2, '.', '');
        if (function_exists('num_letras')) {
            $txt = trim((string) num_letras($str));
            return preg_replace('/\s+/', ' ', $txt);
        }
        return $str;
    }
}
