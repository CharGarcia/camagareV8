<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Endpoint `{ruta}/avisoAsientosPdf` de los módulos que muestran el aviso de asientos contables
 * pendientes (Asientos Contables, Estados Financieros, Balance de Comprobación y Mayores).
 *
 * Recibe por POST (JSON) el resultado que el navegador acumuló al generar y devuelve el PDF.
 * Ver App\Services\modulos\AvisoAsientosPdfService y public/js/modulos/asientos_pendientes.js.
 */
trait AvisoAsientosPdfTrait
{
    public function avisoAsientosPdf(): void
    {
        try {
            $this->requireLeer();
            $idEmpresa = (int) $_SESSION['id_empresa'];
            $usuario   = (string) ($_SESSION['nombre'] ?? '');

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $datos = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($datos)) {
                throw new \InvalidArgumentException('No se recibió el aviso a imprimir.');
            }

            (new \App\Services\modulos\AvisoAsientosPdfService())->generar($idEmpresa, $usuario, $datos);
        } catch (\Throwable $th) {
            if (!$th instanceof \InvalidArgumentException) {
                \App\Services\ErrorLogService::registrar($th, ['ruta' => static::class, 'accion' => __FUNCTION__]);
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['error' => 'No se pudo generar el PDF del aviso: ' . $th->getMessage()]);
        }
    }
}
