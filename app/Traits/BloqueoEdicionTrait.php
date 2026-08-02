<?php
declare(strict_types=1);

namespace App\Traits;

use App\Services\BloqueoEdicionService;

/**
 * Endpoints AJAX genéricos de bloqueo de edición, listos para reusar en cualquier
 * controlador de módulo. Un controlador que quiera proteger una tabla solo necesita
 * exponer 3 acciones delgadas que llamen a estos métodos con el nombre de tabla fijo:
 *
 *   public function bloquearAjax(): void   { $this->tomarBloqueoAjax('pedidos_cabecera'); }
 *   public function renovarBloqueoAjax(): void { $this->renovarBloqueoAjaxTrait('pedidos_cabecera'); }
 *   public function liberarBloqueoAjax(): void { $this->liberarBloqueoAjaxTrait('pedidos_cabecera'); }
 *
 * El nombre de tabla se fija en el controlador (no llega del cliente) para que un
 * usuario no pueda bloquear/liberar registros de una tabla arbitraria.
 */
trait BloqueoEdicionTrait
{
    private ?BloqueoEdicionService $bloqueoEdicionService = null;

    private function bloqueoService(): BloqueoEdicionService
    {
        if ($this->bloqueoEdicionService === null) {
            $this->bloqueoEdicionService = new BloqueoEdicionService();
        }
        return $this->bloqueoEdicionService;
    }

    protected function tomarBloqueoAjax(string $tabla): void
    {
        header('Content-Type: application/json');
        [$idRegistro, $idEmpresa, $idUsuario, $nombreUsuario, $moduloContexto] = $this->datosBloqueoDesdeRequest();

        if ($idRegistro <= 0 || $idEmpresa <= 0 || $idUsuario <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos.']);
            exit;
        }

        $res = $this->bloqueoService()->intentarTomar($tabla, $idRegistro, $idEmpresa, $idUsuario, $nombreUsuario, $moduloContexto);
        echo json_encode(array_merge(['ok' => true], $res));
        exit;
    }

    protected function renovarBloqueoAjaxTrait(string $tabla): void
    {
        header('Content-Type: application/json');
        [$idRegistro, $idEmpresa, $idUsuario] = $this->datosBloqueoDesdeRequest();

        if ($idRegistro <= 0 || $idEmpresa <= 0 || $idUsuario <= 0) {
            echo json_encode(['ok' => false, 'vigente' => false]);
            exit;
        }

        $vigente = $this->bloqueoService()->renovarPing($tabla, $idRegistro, $idEmpresa, $idUsuario);
        echo json_encode(['ok' => true, 'vigente' => $vigente]);
        exit;
    }

    protected function liberarBloqueoAjaxTrait(string $tabla): void
    {
        header('Content-Type: application/json');
        [$idRegistro, $idEmpresa, $idUsuario] = $this->datosBloqueoDesdeRequest();

        if ($idRegistro > 0 && $idEmpresa > 0 && $idUsuario > 0) {
            $this->bloqueoService()->liberar($tabla, $idRegistro, $idEmpresa, $idUsuario);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    /** @return array{0:int,1:int,2:int,3:string,4:?string} */
    private function datosBloqueoDesdeRequest(): array
    {
        $idRegistro = (int) ($_POST['id_registro'] ?? $_GET['id_registro'] ?? 0);
        $moduloContexto = trim((string) ($_POST['modulo_contexto'] ?? $_GET['modulo_contexto'] ?? '')) ?: null;
        $idEmpresa = (int) ($_SESSION['id_empresa'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
        $nombreUsuario = (string) ($_SESSION['nombre'] ?? 'Usuario');

        return [$idRegistro, $idEmpresa, $idUsuario, $nombreUsuario, $moduloContexto];
    }
}
