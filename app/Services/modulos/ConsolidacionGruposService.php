<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ConsolidacionGruposRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Rules\modulos\ConsolidacionGruposRules;
use App\Services\LogSistemaService;
use Throwable;

/**
 * "Configuración de Balances Consolidados": arma grupos de cuentas equivalentes entre
 * establecimientos del mismo RUC (consolidacion_grupos/_cuentas), 100% manual — nunca se
 * sugiere una equivalencia por coincidencia de código (el código de plan_cuentas no es
 * confiable entre empresas: es libre, editable, y ni siquiera consistente dentro de una
 * misma empresa — ver docs/manual/modulos/balances-consolidados.md).
 *
 * Lo leen, en modo solo-lectura, los reportes que consolidan por RUC (Estados Financieros,
 * Balance de Comprobación) vía getMapaCuentaGrupo().
 */
class ConsolidacionGruposService
{
    public function __construct(
        private ConsolidacionGruposRepository $repo,
        private ConsolidacionGruposRules $rules,
        private LogSistemaService $log,
        private ?EmpresaRepository $empresaRepo = null
    ) {
        $this->empresaRepo = $empresaRepo ?? new EmpresaRepository();
    }

    private function ruc(int $idEmpresa): string
    {
        return (string) ($this->empresaRepo->getRucPorId($idEmpresa) ?? '');
    }

    /** Establecimientos del RUC accesibles al usuario, con su propio plan de cuentas (para el picker del modal). */
    public function getEstablecimientosConCuentas(int $idEmpresa, int $idUsuario, ?int $idGrupoExcluir = null): array
    {
        $ruc = $this->ruc($idEmpresa);
        $idsGrupo = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        $etiquetas = $this->empresaRepo->getEtiquetasEstablecimiento($idsGrupo);
        $usadas = $this->repo->getCuentasUsadasDelRuc($ruc, $idGrupoExcluir);

        $out = [];
        foreach ($idsGrupo as $idEmp) {
            $cuentas = [];
            foreach ($this->repo->getCuentasDeEmpresa($idEmp) as $c) {
                $cuentas[] = [
                    'id'      => (int) $c['id'],
                    'codigo'  => $c['codigo'],
                    'nombre'  => $c['nombre'],
                    'nivel'   => $c['nivel'],
                    'usada'   => isset($usadas[(int) $c['id']]),
                ];
            }
            $out[] = [
                'id_empresa' => $idEmp,
                'etiqueta'   => $etiquetas[$idEmp] ?? ('Empresa ' . $idEmp),
                'cuentas'    => $cuentas,
            ];
        }
        return $out;
    }

    /** Grupos ya armados del RUC, con sus cuentas anidadas por empresa. */
    public function listarGrupos(int $idEmpresa, int $idUsuario): array
    {
        $ruc = $this->ruc($idEmpresa);
        $filas = $this->repo->listarGruposConCuentas($ruc);

        $grupos = [];
        foreach ($filas as $f) {
            $id = (int) $f['id_grupo'];
            if (!isset($grupos[$id])) {
                $grupos[$id] = [
                    'id' => $id, 'nombre' => $f['nombre'], 'tipo' => $f['tipo'], 'orden' => (int) $f['orden'],
                    'cuentas' => [],
                ];
            }
            if ($f['id_detalle'] !== null) {
                $grupos[$id]['cuentas'][] = [
                    'id_empresa'      => (int) $f['id_empresa'],
                    'id_cuenta'       => (int) $f['id_cuenta'],
                    'cuenta_codigo'   => $f['cuenta_codigo'],
                    'cuenta_nombre'   => $f['cuenta_nombre'],
                    'empresa_nombre'  => $f['empresa_nombre'],
                    'establecimiento' => $f['establecimiento'],
                ];
            }
        }
        return array_values($grupos);
    }

    public function guardarGrupo(int $idEmpresa, int $idUsuario, array $data): array
    {
        $this->rules->validarGuardado($data);
        $ruc = $this->ruc($idEmpresa);

        // Cada cuenta debe pertenecer a una empresa del grupo RUC accesible al usuario —
        // refuerzo de servidor, no solo confiar en lo que mandó el formulario.
        $idsAccesibles = $this->empresaRepo->getIdsGrupoRucAccesible($idEmpresa, $idUsuario);
        foreach ($data['cuentas'] as $c) {
            if (!in_array((int) $c['id_empresa'], $idsAccesibles, true)) {
                throw new \Exception('Una de las empresas seleccionadas no pertenece a este RUC o no tiene acceso a ella.');
            }
        }

        $idExistente = !empty($data['id']) ? (int) $data['id'] : 0;

        // Ninguna cuenta puede repetirse en otro grupo del RUC (ambigüedad de a qué concepto pertenece).
        $usadas = $this->repo->getCuentasUsadasDelRuc($ruc, $idExistente ?: null);
        foreach ($data['cuentas'] as $c) {
            $idCuenta = (int) $c['id_cuenta'];
            if (isset($usadas[$idCuenta])) {
                throw new \Exception('Una de las cuentas seleccionadas ya pertenece a otro grupo consolidado.');
            }
        }

        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $payload = [
                'ruc' => $ruc, 'id_empresa_matriz' => $idEmpresa,
                'nombre' => trim((string) $data['nombre']), 'tipo' => $data['tipo'],
                'orden' => (int) ($data['orden'] ?? 0), 'usuario_id' => $idUsuario,
            ];
            if ($idExistente) {
                $antes = $this->repo->getGrupo($idExistente, $ruc);
                if (!$antes) throw new \Exception('Grupo no encontrado.');
                $this->repo->actualizarGrupo($idExistente, $ruc, $payload);
                $id = $idExistente;
                $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'consolidacion_grupos', $id, $antes, $payload);
            } else {
                $id = $this->repo->crearGrupo($payload);
                $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'consolidacion_grupos', $id, null, $payload);
            }
            $this->repo->guardarCuentasDelGrupo($id, $data['cuentas'], $idUsuario);
            if ($managed) $db->commit();
        } catch (Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }

        return ['id' => $id];
    }

    public function eliminarGrupo(int $idEmpresa, int $idUsuario, int $idGrupo): void
    {
        $ruc = $this->ruc($idEmpresa);
        $antes = $this->repo->getGrupo($idGrupo, $ruc);
        if (!$antes) {
            throw new \Exception('Grupo no encontrado.');
        }
        if (!$this->repo->eliminarGrupo($idGrupo, $ruc, $idUsuario)) {
            throw new \Exception('No se pudo eliminar el grupo.');
        }
        $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'consolidacion_grupos', $idGrupo, $antes, null);
    }

    /** Usado por los reportes que consolidan (Estados Financieros, Balance de Comprobación). */
    public function getMapaCuentaGrupo(int $idEmpresa): array
    {
        return $this->repo->getMapaCuentaGrupo($this->ruc($idEmpresa));
    }
}
