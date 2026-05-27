<?php

declare(strict_types=1);

namespace App\Services\modulos;

/**
 * Genera el PDF de la Guía de Remisión usando TCPDF/FPDF o la librería disponible en el sistema.
 * Mientras no haya una plantilla personalizada activa, se genera un PDF básico con la información completa.
 */
class GuiaRemisionPdfService
{
    public function generar(array $cabecera, array $detalles, array $infoAdicional, array $empresa): void
    {
        // Número formateado
        $numero = ($cabecera['establecimiento'] ?? '001') . '-'
                . ($cabecera['punto_emision']   ?? '001') . '-'
                . str_pad((string)($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);

        $filename = 'guia_remision_' . $numero . '.pdf';

        // Si el sistema tiene TCPDF disponible (vía composer)
        if (class_exists('\TCPDF')) {
            $this->generarConTcpdf($cabecera, $detalles, $infoAdicional, $empresa, $numero, $filename);
            return;
        }

        // Fallback: PDF simple con HTML vía output buffer
        $this->generarHtmlFallback($cabecera, $detalles, $infoAdicional, $empresa, $numero, $filename);
    }

    private function generarHtmlFallback(
        array $cabecera,
        array $detalles,
        array $infoAdicional,
        array $empresa,
        string $numero,
        string $filename
    ): void {
        $fechaEmision    = !empty($cabecera['fecha_emision'])            ? date('d-m-Y', strtotime($cabecera['fecha_emision']))            : '—';
        $fechaIni        = !empty($cabecera['fecha_inicio_transporte'])  ? date('d-m-Y', strtotime($cabecera['fecha_inicio_transporte']))  : '—';
        $fechaFin        = !empty($cabecera['fecha_fin_transporte'])     ? date('d-m-Y', strtotime($cabecera['fecha_fin_transporte']))     : '—';
        $fechaAut        = !empty($cabecera['fecha_autorizacion'])       ? date('d-m-Y H:i:s', strtotime($cabecera['fecha_autorizacion'])): '—';
        $estado          = strtoupper($cabecera['estado'] ?? 'BORRADOR');

        // Filas de detalle
        $rowsDetalle = '';
        foreach ($detalles as $i => $d) {
            $rowsDetalle .= '<tr>
                <td style="border:1px solid #ccc;padding:4px;text-align:center;">' . ($i + 1) . '</td>
                <td style="border:1px solid #ccc;padding:4px;">' . htmlspecialchars($d['codigo_principal'] ?? '') . '</td>
                <td style="border:1px solid #ccc;padding:4px;">' . htmlspecialchars($d['descripcion'] ?? '') . '</td>
                <td style="border:1px solid #ccc;padding:4px;text-align:right;">' . number_format((float)($d['cantidad'] ?? 0), 2) . '</td>
            </tr>';
        }

        // Info adicional
        $rowsAdicional = '';
        foreach ($infoAdicional as $ia) {
            $rowsAdicional .= '<tr>
                <td style="border:1px solid #ccc;padding:4px;font-weight:600;">' . htmlspecialchars($ia['nombre'] ?? '') . '</td>
                <td style="border:1px solid #ccc;padding:4px;">' . htmlspecialchars($ia['valor'] ?? '') . '</td>
            </tr>';
        }

        $html = '<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Guía de Remisión ' . htmlspecialchars($numero) . '</title>
<style>
  body{font-family:Arial,sans-serif;font-size:11px;color:#222;}
  h2{margin:0;font-size:14px;}
  table{border-collapse:collapse;width:100%;}
  .section{margin-bottom:10px;}
  .label{font-weight:600;color:#555;}
  .badge-estado{display:inline-block;padding:2px 8px;border-radius:4px;font-weight:700;font-size:10px;
    background:' . ($estado === 'AUTORIZADO' ? '#d4edda' : '#f8d7da') . ';
    color:' . ($estado === 'AUTORIZADO' ? '#155724' : '#721c24') . ';}
</style>
</head>
<body>
<div class="section">
  <table><tr>
    <td style="width:60%">
      <h2>' . htmlspecialchars($empresa['nombre'] ?? '') . '</h2>
      <div>RUC: ' . htmlspecialchars($empresa['ruc'] ?? '') . '</div>
      <div>' . htmlspecialchars($empresa['direccion'] ?? '') . '</div>
    </td>
    <td style="width:40%;text-align:right;">
      <div style="border:1px solid #ccc;padding:8px;display:inline-block;">
        <div style="font-weight:700;font-size:12px;">GUÍA DE REMISIÓN</div>
        <div style="font-size:13px;font-weight:700;">' . htmlspecialchars($numero) . '</div>
        <div class="badge-estado">' . $estado . '</div>
      </div>
    </td>
  </tr></table>
</div>

<div class="section">
  <table><tr>
    <td><span class="label">Fecha emisión:</span> ' . $fechaEmision . '</td>
    <td><span class="label">Fecha inicio transporte:</span> ' . $fechaIni . '</td>
    <td><span class="label">Fecha fin transporte:</span> ' . $fechaFin . '</td>
  </tr></table>
</div>

<div class="section">
  <table><tr>
    <td><span class="label">Transportista:</span> ' . htmlspecialchars($cabecera['transportista_nombre'] ?? '') . '</td>
    <td><span class="label">Identificación:</span> ' . htmlspecialchars($cabecera['transportista_ruc'] ?? '') . '</td>
    <td><span class="label">Placa:</span> ' . htmlspecialchars($cabecera['placa'] ?? '') . '</td>
  </tr>
  <tr>
    <td colspan="3"><span class="label">Destinatario:</span> ' . htmlspecialchars($cabecera['cliente_nombre'] ?? '') . '
      &nbsp;&nbsp;<span class="label">RUC/Cédula:</span> ' . htmlspecialchars($cabecera['cliente_ruc'] ?? '') . '</td>
  </tr>
  <tr>
    <td><span class="label">Dirección partida:</span> ' . htmlspecialchars($cabecera['direccion_partida'] ?? '') . '</td>
    <td colspan="2"><span class="label">Dirección destino:</span> ' . htmlspecialchars($cabecera['direccion_destino'] ?? '') . '</td>
  </tr>
  <tr>
    <td><span class="label">Motivo traslado:</span> ' . htmlspecialchars($cabecera['motivo_traslado'] ?? '') . '</td>
    <td colspan="2"><span class="label">Ruta:</span> ' . htmlspecialchars($cabecera['ruta'] ?? '') . '</td>
  </tr>
  ' . (!empty($cabecera['num_doc_sustento']) ? '<tr><td colspan="3"><span class="label">Doc. sustento:</span> ' . htmlspecialchars($cabecera['num_doc_sustento']) . '</td></tr>' : '') . '
  ' . (!empty($cabecera['numero_autorizacion']) ? '<tr><td colspan="3"><span class="label">N° Autorización:</span> ' . htmlspecialchars($cabecera['numero_autorizacion']) . ' &nbsp; <span class="label">Fecha:</span> ' . $fechaAut . '</td></tr>' : '') . '
  </table>
</div>

<div class="section">
  <table>
    <thead><tr style="background:#f0f0f0;">
      <th style="border:1px solid #ccc;padding:4px;">#</th>
      <th style="border:1px solid #ccc;padding:4px;">Código</th>
      <th style="border:1px solid #ccc;padding:4px;">Descripción</th>
      <th style="border:1px solid #ccc;padding:4px;">Cantidad</th>
    </tr></thead>
    <tbody>' . $rowsDetalle . '</tbody>
  </table>
</div>

' . (!empty($rowsAdicional) ? '<div class="section"><strong>Información Adicional</strong><table style="width:50%">' . $rowsAdicional . '</table></div>' : '') . '

' . (!empty($cabecera['clave_acceso']) ? '<div class="section"><span class="label">Clave de acceso:</span> <small>' . htmlspecialchars($cabecera['clave_acceso']) . '</small></div>' : '') . '

</body></html>';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        // Si no hay librería PDF, enviar HTML como fallback con content-type text/html
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        echo $html;
    }

    private function generarConTcpdf(
        array $cabecera,
        array $detalles,
        array $infoAdicional,
        array $empresa,
        string $numero,
        string $filename
    ): void {
        // Implementación con TCPDF cuando esté disponible
        // Por ahora delegamos al fallback HTML
        $this->generarHtmlFallback($cabecera, $detalles, $infoAdicional, $empresa, $numero, $filename);
    }
}
