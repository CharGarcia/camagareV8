<?php
/**
 * Mantenimiento del catálogo global de tramos de Impuesto a la Renta
 * (retención en la fuente, relación de dependencia) y del tope de gasto
 * personal deducible por año. Ver ImpuestoRentaEmpleadoService.
 */

declare(strict_types=1);

namespace App\controllers;

use App\core\Controller;
use PDO;

class ImpuestoRentaTramosController extends Controller
{
    private const BASE_PATH = '/config/impuesto-renta-tramos';

    private function requireNivel(int $min): void
    {
        $this->requireAuth();
        $nivel = (int) ($_SESSION['nivel'] ?? 0);
        if ($nivel < $min) {
            $_SESSION['config_msg'] = ['danger', 'No tiene permisos para acceder a esta sección.'];
            header('Location: ' . BASE_URL . '/config');
            exit;
        }
    }

    public function index(): void
    {
        $this->requireNivel(3);

        $anio = (int) ($_GET['anio'] ?? date('Y'));

        $st = $this->db->prepare(
            "SELECT * FROM impuesto_renta_tramos WHERE anio = :a AND eliminado = false ORDER BY orden ASC"
        );
        $st->execute([':a' => $anio]);
        $tramos = $st->fetchAll(PDO::FETCH_ASSOC);

        // Parámetros de gastos personales: la rebaja se calcula sobre la canasta
        // familiar básica del año y el número de canastas según cargas familiares.
        $ir = new \App\Services\modulos\ImpuestoRentaEmpleadoService();
        $parametros = $ir->getParametrosAnio($anio);

        $st3 = $this->db->query("SELECT DISTINCT anio FROM impuesto_renta_tramos ORDER BY anio DESC");
        $aniosConfigurados = $st3->fetchAll(PDO::FETCH_COLUMN);

        $this->viewWithLayout('layouts.main', 'config.impuestoRentaTramos.index', [
            'titulo' => 'Tramos de Impuesto a la Renta (retención en relación de dependencia)',
            'fullWidth' => true,
            'anio' => $anio,
            'tramos' => $tramos,
            'parametros' => $parametros,
            'aniosConfigurados' => $aniosConfigurados,
        ]);
    }

    public function store(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $anio = (int) ($_POST['anio'] ?? 0);
        $orden = (int) ($_POST['orden'] ?? 0);
        $fraccionBasica = (float) ($_POST['fraccion_basica'] ?? 0);
        $excesoHastaRaw = trim((string) ($_POST['exceso_hasta'] ?? ''));
        $excesoHasta = $excesoHastaRaw === '' ? null : (float) $excesoHastaRaw;
        $impuestoFraccionBasica = (float) ($_POST['impuesto_fraccion_basica'] ?? 0);
        $porcentajeExcedente = (float) ($_POST['porcentaje_excedente'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($anio <= 0 || $orden <= 0) {
            $_SESSION['config_msg'] = ['danger', 'Año y orden son obligatorios.'];
            $this->redirect(BASE_URL . self::BASE_PATH . '?anio=' . $anio);
        }

        try {
            $st = $this->db->prepare(
                "INSERT INTO impuesto_renta_tramos
                    (anio, orden, fraccion_basica, exceso_hasta, impuesto_fraccion_basica, porcentaje_excedente, created_by, updated_by)
                 VALUES (:anio, :orden, :fb, :eh, :ifb, :pe, :u, :u)
                 ON CONFLICT (anio, orden) DO UPDATE SET
                    fraccion_basica = EXCLUDED.fraccion_basica,
                    exceso_hasta = EXCLUDED.exceso_hasta,
                    impuesto_fraccion_basica = EXCLUDED.impuesto_fraccion_basica,
                    porcentaje_excedente = EXCLUDED.porcentaje_excedente,
                    updated_by = EXCLUDED.updated_by,
                    updated_at = CURRENT_TIMESTAMP"
            );
            $st->execute([
                ':anio' => $anio, ':orden' => $orden, ':fb' => $fraccionBasica, ':eh' => $excesoHasta,
                ':ifb' => $impuestoFraccionBasica, ':pe' => $porcentajeExcedente, ':u' => $idUsuario,
            ]);
            $_SESSION['config_msg'] = ['success', 'Tramo guardado correctamente.'];
        } catch (\Throwable $e) {
            $_SESSION['config_msg'] = ['danger', 'Error al guardar: ' . $e->getMessage()];
        }

        $this->redirect(BASE_URL . self::BASE_PATH . '?anio=' . $anio);
    }

    public function delete(): void
    {
        $this->requireNivel(3);
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $anio = (int) ($_GET['anio'] ?? $_POST['anio'] ?? date('Y'));

        if ($id > 0) {
            $this->db->prepare("UPDATE impuesto_renta_tramos SET eliminado = true WHERE id = :id")->execute([':id' => $id]);
            $_SESSION['config_msg'] = ['success', 'Tramo eliminado.'];
        }
        $this->redirect(BASE_URL . self::BASE_PATH . '?anio=' . $anio);
    }

    /**
     * Guarda los parámetros de gastos personales del año: canasta familiar básica,
     * porcentaje de rebaja y número de canastas según cargas familiares.
     */
    public function guardarParametros(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $anio = (int) ($_POST['anio'] ?? 0);
        if ($anio <= 0) {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $canasta   = max(0.0, (float) ($_POST['canasta_basica'] ?? 0));
        $pctRebaja = max(0.0, (float) ($_POST['porcentaje_rebaja'] ?? 18));
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        // Factores (canastas) por número de cargas + caso especial.
        $factores = [];
        foreach (['0', '1', '2', '3', '4', '5', 'especial'] as $k) {
            $v = (float) ($_POST['factor_' . $k] ?? 0);
            if ($v > 0) {
                $factores[$k] = $v;
            }
        }
        if (!$factores) {
            $factores = \App\Services\modulos\ImpuestoRentaEmpleadoService::FACTORES_DEFECTO;
        }

        // gasto_personal_maximo queda como referencia: el tope sin cargas (7 canastas).
        $topeSinCargas = round($canasta * (float) ($factores['0'] ?? 7), 2);

        $st = $this->db->prepare(
            "INSERT INTO impuesto_renta_parametros
                (anio, gasto_personal_maximo, canasta_basica, porcentaje_rebaja, factores_canastas, updated_by)
             VALUES (:a, :g, :c, :p, :f, :u)
             ON CONFLICT (anio) DO UPDATE SET
                gasto_personal_maximo = EXCLUDED.gasto_personal_maximo,
                canasta_basica        = EXCLUDED.canasta_basica,
                porcentaje_rebaja     = EXCLUDED.porcentaje_rebaja,
                factores_canastas     = EXCLUDED.factores_canastas,
                updated_by            = EXCLUDED.updated_by,
                updated_at            = CURRENT_TIMESTAMP"
        );
        $st->execute([
            ':a' => $anio,
            ':g' => $topeSinCargas,
            ':c' => $canasta,
            ':p' => $pctRebaja,
            ':f' => json_encode($factores),
            ':u' => $idUsuario,
        ]);

        $_SESSION['config_msg'] = ['success', 'Parámetros de gastos personales guardados.'];
        $this->redirect(BASE_URL . self::BASE_PATH . '?anio=' . $anio);
    }
}
