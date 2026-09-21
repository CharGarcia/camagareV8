<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;

/**
 * Acta de la transferencia de inventario en PDF (Spipu\Html2Pdf).
 *
 * Vive aquí y no en el controlador porque la generan dos flujos distintos: el
 * módulo (descarga y adjunto del correo, con sesión) y la página pública de
 * recepción, que llega por el token del correo y no tiene sesión. Un solo punto
 * de armado garantiza que el acta que se ve por el enlace sea exactamente la
 * misma que se descarga desde el sistema.
 */
class TransferenciaActaPdfService
{
    /**
     * @param array  $doc     Cabecera + detalles (TransferenciaInventarioService::getPorId).
     * @param string $destino Modo de salida de Html2Pdf: 'S' string, 'D' descarga, 'I' en el navegador.
     */
    public function generar(array $doc, int $idEmpresa, string $destino = 'S'): string
    {
        $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
        $logoPdf = $this->logoPdf($idEmpresa);

        $autoload = MVC_ROOT . '/vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        ob_start();
        include MVC_APP . '/views/modulos/transferencias_inventario/pdf_documento.php';
        $html = ob_get_clean();

        $html2pdf = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'es');
        $html2pdf->writeHTML($html);
        return (string) $html2pdf->output($this->nombreArchivo($doc), $destino);
    }

    public function nombreArchivo(array $doc): string
    {
        return 'Transferencia_' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($doc['numero'] ?? 'acta')) . '.pdf';
    }

    /**
     * Ruta en disco del logo del establecimiento principal ('' si no hay). La tabla guarda la
     * URL pública (empresa_establecimiento.logo_ruta) y se resuelve igual que en los demás PDF
     * del sistema. Solo se devuelve si es una imagen legible: ante una imagen que no puede
     * medir, Html2Pdf aborta el PDF entero, y un logo dañado no debe impedir sacar el acta.
     */
    private function logoPdf(int $idEmpresa): string
    {
        $ruta = (string) ((new Empresa())->getEstablecimientos($idEmpresa)[0]['logo_ruta'] ?? '');
        if ($ruta === '') {
            return '';
        }
        $clean = ltrim($ruta, '/');
        if (strpos($clean, 'sistema/public/') === 0) {
            $clean = substr($clean, strlen('sistema/public/'));
        } elseif (strpos($clean, 'sistema/') === 0) {
            $clean = substr($clean, strlen('sistema/'));
        }
        if (strpos($clean, 'public/') === 0) {
            $clean = substr($clean, strlen('public/'));
        }
        foreach ([MVC_ROOT . '/public/' . $clean, MVC_ROOT . '/' . $clean] as $cand) {
            if (is_file($cand) && @getimagesize($cand)) {
                return $cand;
            }
        }
        return '';
    }
}
