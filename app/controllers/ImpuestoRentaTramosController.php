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

        $st2 = $this->db->prepare("SELECT gasto_personal_maximo FROM impuesto_renta_parametros WHERE anio = :a");
        $st2->execute([':a' => $anio]);
        $gastoPersonalMaximo = (float) ($st2->fetchColumn() ?: 0);

        $st3 = $this->db->query("SELECT DISTINCT anio FROM impuesto_renta_tramos ORDER BY anio DESC");
        $aniosConfigurados = $st3->fetchAll(PDO::FETCH_COLUMN);

        $this->viewWithLayout('layouts.main', 'config.impuestoRentaTramos.index', [
            'titulo' => 'Tramos de Impuesto a la Renta (retención en relación de dependencia)',
            'fullWidth' => true,
            'anio' => $anio,
            'tramos' => $tramos,
            'gastoPersonalMaximo' => $gastoPersonalMaximo,
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

    /** Guarda el tope de gasto personal deducible del año. */
    public function guardarParametros(): void
    {
        $this->requireNivel(3);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $anio = (int) ($_POST['anio'] ?? 0);
        $gastoPersonalMaximo = (float) ($_POST['gasto_personal_maximo'] ?? 0);
        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if ($anio <= 0) {
            $this->redirect(BASE_URL . self::BASE_PATH);
        }

        $st = $this->db->prepare(
            "INSERT INTO impuesto_renta_parametros (anio, gasto_personal_maximo, updated_by)
             VALUES (:a, :g, :u)
             ON CONFLICT (anio) DO UPDATE SET gasto_personal_maximo = EXCLUDED.gasto_personal_maximo,
                updated_by = EXCLUDED.updated_by, updated_at = CURRENT_TIMESTAMP"
        );
        $st->execute([':a' => $anio, ':g' => $gastoPersonalMaximo, ':u' => $idUsuario]);
        $_SESSION['config_msg'] = ['success', 'Parámetros del año guardados.'];
        $this->redirect(BASE_URL . self::BASE_PATH . '?anio=' . $anio);
    }
}
