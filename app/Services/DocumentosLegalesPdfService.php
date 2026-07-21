<?php

declare(strict_types=1);

namespace App\Services;

use TCPDF;

/**
 * Genera en PDF los documentos legales (Acuerdo de uso de datos y Contrato de
 * uso del sistema) personalizados con los datos de la empresa.
 *
 * El contenido viene de documentos_legales (editable en /config/documentos-legales)
 * y admite placeholders que aquí se reemplazan.
 */
class DocumentosLegalesPdfService
{
    private float $marginL = 15;
    private float $marginR = 15;

    /**
     * Reemplaza los placeholders del texto con los datos reales de la empresa.
     */
    public function resolverPlaceholders(string $contenido, array $empresa, string $sistemaNombre = ''): string
    {
        $mapa = [
            '{{empresa_nombre}}'        => (string) ($empresa['nombre'] ?? ''),
            '{{empresa_comercial}}'     => (string) ($empresa['nombre_comercial'] ?? ''),
            '{{empresa_ruc}}'           => (string) ($empresa['ruc'] ?? ''),
            '{{empresa_direccion}}'     => (string) ($empresa['direccion'] ?? ''),
            '{{empresa_telefono}}'      => (string) ($empresa['telefono'] ?? ''),
            '{{empresa_correo}}'        => (string) ($empresa['mail'] ?? ''),
            '{{empresa_representante}}' => (string) ($empresa['nom_rep_legal'] ?? ''),
            '{{fecha}}'                 => date('d-m-Y'),
            '{{sistema_nombre}}'        => $sistemaNombre !== '' ? $sistemaNombre : 'CaMaGaRe',
        ];

        return str_replace(array_keys($mapa), array_values($mapa), $contenido);
    }

    /**
     * Genera el PDF de un documento legal.
     *
     * @param array  $documento Fila de documentos_legales (titulo, contenido, version)
     * @param array  $empresa   Datos de la empresa
     * @param string $dest      'S' devuelve el binario, 'F' escribe en $rutaSalida
     * @return string Binario del PDF cuando $dest = 'S'
     */
    public function generar(array $documento, array $empresa, string $dest = 'S', string $rutaSalida = '', string $sistemaNombre = ''): string
    {
        $titulo    = (string) ($documento['titulo'] ?? 'Documento');
        $version   = (int) ($documento['version'] ?? 1);
        $contenido = $this->resolverPlaceholders((string) ($documento['contenido'] ?? ''), $empresa, $sistemaNombre);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CaMaGaRe');
        $pdf->SetAuthor($sistemaNombre !== '' ? $sistemaNombre : 'CaMaGaRe');
        $pdf->SetTitle($titulo);
        $pdf->SetMargins($this->marginL, 18, $this->marginR);
        $pdf->SetAutoPageBreak(true, 20);

        // Pie con versión y paginación (evidencia de qué versión se envió)
        $pdf->setFooterData([0, 0, 0], [255, 255, 255]);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);

        $pdf->AddPage();

        // Encabezado del documento
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 4, 'Versión ' . $version . '  ·  Generado el ' . date('d-m-Y H:i:s'), 0, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        // Cuerpo
        $pdf->SetFont('helvetica', '', 10);
        $html = '<style>
            h3 { font-size: 13px; text-align:center; }
            p  { text-align: justify; line-height: 1.5; }
        </style>' . $contenido;
        $pdf->writeHTML($html, true, false, true, false, '');

        // Bloque de firmas / constancia
        $pdf->Ln(6);
        $pdf->SetFont('helvetica', '', 9);
        $datos = '<table border="0" cellpadding="3">
            <tr><td width="30%"><b>Empresa:</b></td><td width="70%">' . htmlspecialchars((string) ($empresa['nombre'] ?? '')) . '</td></tr>
            <tr><td><b>RUC:</b></td><td>' . htmlspecialchars((string) ($empresa['ruc'] ?? '')) . '</td></tr>
            <tr><td><b>Representante:</b></td><td>' . htmlspecialchars((string) ($empresa['nom_rep_legal'] ?? '—')) . '</td></tr>
            <tr><td><b>Correo:</b></td><td>' . htmlspecialchars((string) ($empresa['mail'] ?? '—')) . '</td></tr>
        </table>';
        $pdf->writeHTML($datos, true, false, true, false, '');

        $nombreArchivo = $this->nombreArchivo($documento, $empresa);

        if ($dest === 'F' && $rutaSalida !== '') {
            $pdf->Output($rutaSalida, 'F');

            return $rutaSalida;
        }

        return (string) $pdf->Output($nombreArchivo, 'S');
    }

    public function nombreArchivo(array $documento, array $empresa): string
    {
        $tipo = (string) ($documento['tipo'] ?? 'documento');
        $base = $tipo === 'acuerdo_datos' ? 'Acuerdo-Uso-Datos' : 'Contrato-Uso-Sistema';
        $ruc  = preg_replace('/[^0-9A-Za-z]/', '', (string) ($empresa['ruc'] ?? ''));

        return $base . ($ruc !== '' ? '-' . $ruc : '') . '.pdf';
    }
}
