<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\models\Empresa;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\ProformaRepository;
use App\Services\EnvioDocumentosSRIService;
use App\Services\PlantillasPdfRendererService;

/**
 * PDF y envío por correo de una proforma. Compartido por el módulo web
 * (ProformasController) y la API móvil (api/v1/ProformasController), para que
 * los dos canales generen el mismo documento y el mismo correo.
 */
class ProformaDocumentoService
{
    private ProformaRepository $repository;
    private ProformaService $service;

    public function __construct(ProformaRepository $repository, ProformaService $service)
    {
        $this->repository = $repository;
        $this->service    = $service;
    }

    /**
     * Datos de la empresa fusionados con la configuración de su primer establecimiento
     * (decimales de cantidad/precio, modo de cálculo del IVA, logo). La pantalla, el
     * guardado y el PDF deben usar la misma configuración: de ahí salen los decimales y
     * el redondeo con los que se calculan y muestran las cifras.
     */
    public function empresaConfig(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        try {
            $estabs = $empresaModel->getEstablecimientos($idEmpresa);
            if (!empty($estabs)) {
                $estConfig = (new EmpresaRepository())->getEstablecimientoConfig((int) $estabs[0]['id']);
                if ($estConfig) {
                    $empresa = array_merge($empresa, $estConfig);
                }
                if (!empty($estabs[0]['logo_ruta'])) {
                    $empresa['logo_ruta'] = $estabs[0]['logo_ruta'];
                }
            }
        } catch (\Throwable $e) {
            // Sin config de establecimiento se usan los valores por defecto (2 decimales).
        }
        return $empresa;
    }

    /** Detalles de la proforma con sus impuestos (en lote: una consulta para todas las líneas). */
    public function getDetallesConImpuestos(int $id): array
    {
        $detalles = $this->repository->getDetalles($id);
        $impuestosPorDetalle = $this->repository->getImpuestosPorDetalles(array_column($detalles, 'id'));
        foreach ($detalles as &$d) {
            $d['impuestos'] = $impuestosPorDetalle[(int) $d['id']] ?? [];
        }
        unset($d);
        return $detalles;
    }

    /**
     * Cabecera y líneas con el IVA recalculado según la configuración de facturación
     * VIGENTE (al subtotal o ítem por ítem), para que el PDF —propio o de plantilla— y
     * el total que se cita en el correo / WhatsApp coincidan con lo que se facturaría.
     *
     * @return array{0: array, 1: array} [cabecera, detalles]
     */
    public function conIvaVigente(int $id, array $cabecera, ?array $empresa = null): array
    {
        $empresa = $empresa ?? $this->empresaConfig((int) ($cabecera['id_empresa'] ?? 0));
        return \App\Helpers\ProformaTotales::recalcularIva($cabecera, $this->getDetallesConImpuestos($id), $empresa);
    }

    /**
     * Genera el PDF de la proforma como STRING (para adjuntar o devolver a la app).
     * Usa la plantilla configurable 'proforma' si existe; si no, el diseño propio.
     */
    public function generarPdfString(int $id, int $idEmpresa, ?array $cabecera = null): ?string
    {
        $cabecera = $cabecera ?? $this->repository->getPorId($id);
        if (!$cabecera) return null;

        $empresa   = $this->empresaConfig($idEmpresa);
        [$cabecera, $detalles] = $this->conIvaVigente($id, $cabecera, $empresa);
        $adicional = $this->repository->getInfoAdicional($id);

        try {
            $renderer  = new PlantillasPdfRendererService();
            $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'proforma');
            if ($plantilla) {
                $pdf = $renderer->generar($plantilla, $cabecera, $detalles, [], $adicional, $empresa, 'S');
                if (is_string($pdf) && $pdf !== '') return $pdf;
            }
        } catch (\Throwable $e) {
            // cae al diseño propio
        }

        try {
            return (new ProformaPdfService())->generar($cabecera, $detalles, $adicional, $empresa, 'S');
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function numero(array $cabecera): string
    {
        return ($cabecera['establecimiento'] ?? '') . '-' . ($cabecera['punto_emision'] ?? '') . '-'
            . str_pad((string) ($cabecera['secuencial'] ?? ''), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Envía la proforma por correo (PDF adjunto, más ficha de productos y condiciones si
     * aplican) a los destinatarios indicados. Si sigue en borrador, el correo lleva el
     * botón para que el cliente la apruebe.
     *
     * @return array{ok:bool, mensaje:string}
     */
    public function enviarCorreo(int $id, int $idEmpresa, string $correos, bool $adjuntarFicha): array
    {
        $correos = trim($correos);
        if ($correos === '') {
            return ['ok' => false, 'mensaje' => 'Debe indicar al menos un correo.'];
        }

        $cabecera = $this->repository->getPorId($id);
        if (!$cabecera || (int) $cabecera['id_empresa'] !== $idEmpresa) {
            return ['ok' => false, 'mensaje' => 'Proforma no encontrada.'];
        }

        $pdf = $this->generarPdfString($id, $idEmpresa, $cabecera);
        if ($pdf === null || $pdf === '') {
            return ['ok' => false, 'mensaje' => 'No se pudo generar el PDF de la proforma.'];
        }

        $numero        = self::numero($cabecera);
        $empresa       = (new Empresa())->getPorId($idEmpresa) ?? [];
        $empresaNombre = (string) ($empresa['nombre_comercial'] ?? ($empresa['nombre'] ?? 'CaMaGaRe'));

        // Token + enlace absoluto para aprobar desde el correo (solo si está pendiente).
        $urlAprobar = '';
        if (($cabecera['estado'] ?? '') === 'borrador') {
            try {
                $token      = $this->service->obtenerTokenAprobacion($id, $idEmpresa);
                $urlAprobar = url_absoluta('aprobar-proforma/' . $token);
            } catch (\Throwable $e) {
                $urlAprobar = '';
            }
        }

        // El total del cuerpo del correo, con el mismo IVA vigente que el PDF adjunto.
        [$cabTotales] = $this->conIvaVigente($id, $cabecera);

        $asunto = "Proforma {$numero} · " . $empresaNombre;
        $cuerpo = $this->construirCorreo(
            (string) ($cabecera['cliente_nombre'] ?? 'cliente'),
            $numero,
            (float) ($cabTotales['importe_total'] ?? 0),
            $empresaNombre,
            $urlAprobar
        );

        $adjuntosExtra = [];
        if ($adjuntarFicha) {
            $fichaPdf = (new ProformaFichaProductosPdfService())
                ->generar($cabecera, $this->repository->getDetalles($id), $empresa, 'S');
            if ($fichaPdf !== '') {
                $adjuntosExtra[] = ['contenido' => $fichaPdf, 'nombre' => "Ficha_Productos_{$numero}.pdf"];
            }
        }
        // Anexo de condiciones: va SIEMPRE con la proforma cuando existe texto guardado
        // (si la proforma no tiene condiciones, generar() devuelve '' y no se adjunta nada).
        $condPdf = (new ProformaCondicionesPdfService())->generar($cabecera, $empresa, 'S');
        if ($condPdf !== '') {
            $adjuntosExtra[] = ['contenido' => $condPdf, 'nombre' => "Condiciones_{$numero}.pdf"];
        }

        $enviado = (new EnvioDocumentosSRIService())->enviarPdfSimple(
            $idEmpresa,
            $correos,
            (string) ($cabecera['cliente_nombre'] ?? ''),
            $asunto,
            $cuerpo,
            $pdf,
            "Proforma_{$numero}",
            $empresaNombre,
            $adjuntosExtra
        );

        if (!$enviado) {
            return ['ok' => false, 'mensaje' => 'No se pudo enviar el correo. Verifique la configuración de correo de la empresa.'];
        }
        try { $this->repository->marcarCorreoEnviado($id); } catch (\Throwable $e) { /* columna aún no desplegada */ }
        return ['ok' => true, 'mensaje' => 'Correo enviado correctamente.'];
    }

    /**
     * Cuerpo HTML del correo de la proforma: saludo, número y total, el botón para
     * aprobarla (si se pasa una URL) y la invitación a responder el correo.
     */
    private function construirCorreo(string $cliente, string $numero, float $total, string $empresaNombre, string $urlAprobar): string
    {
        $e   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $tot = '$' . number_format($total, 2, '.', ',');

        $botonHtml = '';
        if ($urlAprobar !== '') {
            $botonHtml =
                '<tr><td style="padding:6px 0 2px;">'
              . '<a href="' . $e($urlAprobar) . '" target="_blank" '
              . 'style="display:inline-block;background:#16a34a;color:#ffffff;text-decoration:none;'
              . 'font-weight:700;font-size:15px;padding:13px 26px;border-radius:8px;">✓ Aprobar esta proforma</a>'
              . '</td></tr>'
              . '<tr><td style="padding:8px 0 0;color:#64748b;font-size:12px;">'
              . 'También puede aprobarla escribiendo un comentario en el enlace anterior.'
              . '</td></tr>';
        }

        return
            '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1e293b;max-width:560px;">'
          . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" '
          . 'style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">'
          . '<tr><td style="background:#1f4e79;color:#ffffff;padding:18px 22px;font-size:18px;font-weight:700;">'
          . $e($empresaNombre) . '</td></tr>'
          . '<tr><td style="padding:22px;">'
          . '<p style="margin:0 0 12px;font-size:15px;">Estimado(a) <strong>' . $e($cliente) . '</strong>,</p>'
          . '<p style="margin:0 0 12px;font-size:14px;line-height:1.5;">Adjuntamos en PDF la proforma '
          . '<strong>N.º ' . $e($numero) . '</strong> por un total de <strong>' . $e($tot) . '</strong> para su revisión.</p>'
          . '<p style="margin:0 0 16px;font-size:14px;line-height:1.5;">Si está de acuerdo, puede '
          . '<strong>responder a este correo</strong> para confirmarnos'
          . ($urlAprobar !== '' ? ', o aprobarla directamente con el botón:' : '.') . '</p>'
          . '<table role="presentation" cellpadding="0" cellspacing="0">' . $botonHtml . '</table>'
          . '<p style="margin:18px 0 0;font-size:13px;color:#475569;">Quedamos atentos a cualquier consulta.<br>'
          . 'Saludos cordiales,<br><strong>' . $e($empresaNombre) . '</strong></p>'
          . '</td></tr>'
          . '<tr><td style="background:#f8fafc;padding:12px 22px;color:#94a3b8;font-size:11px;">'
          . 'Documento no tributario · Proforma sin validez de factura.</td></tr>'
          . '</table></div>';
    }
}
