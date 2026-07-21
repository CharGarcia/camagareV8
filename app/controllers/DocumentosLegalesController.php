<?php
/**
 * Configuración de los documentos legales del sistema (global, solo super admin).
 * Ruta: /config/documentos-legales
 *
 * Permite editar y versionar el "Acuerdo de uso de datos" y el
 * "Contrato de uso del sistema" que se envían por correo a las empresas.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use App\Services\DocumentosLegalesService;

class DocumentosLegalesController extends Controller
{
    private DocumentosLegalesService $service;
    private const BASE_PATH = '/config/documentos-legales';

    public function __construct()
    {
        parent::__construct();
        $this->service = new DocumentosLegalesService();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requireSuperAdmin();

        $vigentes = $this->service->getVigentes();

        $msg = $_SESSION['doc_legales_msg'] ?? null;
        unset($_SESSION['doc_legales_msg']);

        $this->viewWithLayout('layouts.main', 'documentosLegales.index', [
            'titulo'    => 'Documentos legales',
            'acuerdo'   => $vigentes['acuerdo_datos'] ?? null,
            'contrato'  => $vigentes['contrato_uso'] ?? null,
            'histAcuerdo'  => $this->service->getHistorial('acuerdo_datos'),
            'histContrato' => $this->service->getHistorial('contrato_uso'),
            'msg'       => $msg,
        ]);
    }

    /** Publica una nueva versión de un texto legal. */
    public function guardar(): void
    {
        $this->requireAuth();
        $this->requireSuperAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $tipo      = trim($_POST['tipo'] ?? '');
        $titulo    = trim($_POST['titulo'] ?? '');
        $contenido = (string) ($_POST['contenido'] ?? '');
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            $this->service->publicarVersion($tipo, $titulo, $contenido, $idUsuario);
            $_SESSION['doc_legales_msg'] = ['success', 'Se publicó una nueva versión del documento.'];
        } catch (\InvalidArgumentException $e) {
            $_SESSION['doc_legales_msg'] = ['danger', $e->getMessage()];
        } catch (\Throwable $e) {
            $_SESSION['doc_legales_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH);
    }

    /** Vista previa en PDF del documento vigente (usa una empresa de ejemplo). */
    public function previsualizar(): void
    {
        $this->requireAuth();
        $this->requireSuperAdmin();

        $tipo = trim($_GET['tipo'] ?? 'acuerdo_datos');
        $vigentes = $this->service->getVigentes();
        $doc = $vigentes[$tipo] ?? null;
        if (!$doc) {
            echo 'No hay documento vigente para ese tipo.';
            return;
        }

        $empresaEjemplo = [
            'nombre'        => 'EMPRESA DE EJEMPLO S.A.',
            'ruc'           => '9999999999001',
            'direccion'     => 'Dirección de ejemplo',
            'nom_rep_legal' => 'Representante de Ejemplo',
            'mail'          => 'ejemplo@correo.com',
        ];

        $pdf = new \App\Services\DocumentosLegalesPdfService();
        $bin = $pdf->generar($doc, $empresaEjemplo, 'S');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="previsualizacion.pdf"');
        echo $bin;
    }

    private function requireSuperAdmin(): void
    {
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < 3) {
            $_SESSION['config_msg'] = ['danger', 'Solo el super administrador puede configurar los documentos legales.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }
}
