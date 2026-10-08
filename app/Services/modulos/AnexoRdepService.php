<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\CatalogoPaisesSri;
use App\Helpers\CatalogoRdep;
use App\repositories\modulos\AnexoRdepRepository;
use App\repositories\modulos\UtilidadesRepository;
use App\Rules\modulos\AnexoRdepRules;
use App\Services\LogSistemaService;
use App\Services\Xml\XmlAnexoRdepService;
use Exception;
use ZipArchive;

/**
 * Anexo RDEP: abrir el anexo del ejercicio, armarlo desde la nómina, dejar que
 * el usuario complete lo que la nómina no sabe, recalcular el resumen
 * impositivo, validar como lo hace el SRI y generar el XML (+ zip) para subirlo
 * a SRI en Línea.
 */
class AnexoRdepService
{
    public const MSG_NO_INSTALADO = 'El Anexo RDEP todavía no está instalado en esta base: ejecute database/migrations/20261008_create_anexo_rdep.sql.';

    private AnexoRdepRepository $repo;
    private AnexoRdepRules $rules;
    private LogSistemaService $log;
    private AnexoRdepCalculoService $calc;
    private XmlAnexoRdepService $xml;
    private ImpuestoRentaEmpleadoService $renta;

    public function __construct(AnexoRdepRepository $repo, AnexoRdepRules $rules, LogSistemaService $log)
    {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
        $this->calc  = new AnexoRdepCalculoService();
        $this->xml   = new XmlAnexoRdepService();
        $this->renta = new ImpuestoRentaEmpleadoService();
    }

    public function instalado(): bool
    {
        return $this->repo->instalado();
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null, array $ordenMulti = []): array
    {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro, $ordenMulti);
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        return $this->repo->getAniosDisponibles($idEmpresa);
    }

    public function getCabecera(int $id, int $idEmpresa): ?array
    {
        return $this->repo->findById($id, $idEmpresa);
    }

    public function getDetalle(int $idAnexo, int $idEmpresa): array
    {
        return $this->repo->getDetalle($idAnexo, $idEmpresa);
    }

    public function getTrabajador(int $idDetalle, int $idEmpresa): ?array
    {
        return $this->repo->findDetalle($idDetalle, $idEmpresa);
    }

    public function getEstablecimientos(int $idEmpresa): array
    {
        return $this->repo->getEstablecimientos($idEmpresa);
    }

    public function buscarEmpleados(int $idEmpresa, string $texto): array
    {
        return $this->repo->buscarEmpleados($idEmpresa, $texto);
    }

    /**
     * Abre (o reutiliza) el anexo del ejercicio: cabecera con los datos del
     * empleador y los parámetros del año, y lo arma desde la nómina.
     */
    public function abrir(int $idEmpresa, int $anio, int $idUsuario): int
    {
        if (!$this->repo->instalado()) throw new Exception(self::MSG_NO_INSTALADO);
        $existente = $this->repo->findPorAnio($idEmpresa, $anio);
        if ($existente) {
            return (int) $existente['id'];
        }

        $empleador = $this->repo->getEmpleador($idEmpresa);
        $param = $this->parametrosDelAnio($anio);
        $cab = [
            'id_empresa'        => $idEmpresa,
            'anio'              => $anio,
            'num_ruc'           => preg_replace('/\D/', '', $empleador['num_ruc']) ?: '',
            'razon_social'      => $empleador['razon_social'],
            'tipo_empleador'    => 'PRIVADO_MIXTO',
            'ente_seg_social'   => 'IESS',
            'fraccion_basica'   => $param['fraccion_basica'],
            'canasta_basica'    => $param['canasta_basica'],
            'porcentaje_rebaja' => $param['porcentaje_rebaja'],
            'ipceg'             => 1.803,
            'factores_canastas' => $param['factores'],
        ];
        $this->rules->validarCabecera($cab + ['num_ruc' => $cab['num_ruc'] ?: '0000000000001']);

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->crearCabecera($cab, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'anexo_rdep', $id, null, ['anio' => $anio]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->importar($id, $idEmpresa, $idUsuario);
        return $id;
    }

    /** Fracción básica desgravada, canasta y % de rebaja del ejercicio, desde la configuración de IR. */
    private function parametrosDelAnio(int $anio): array
    {
        $tramos = $this->renta->getTramosAnio($anio);
        $fb = 0.0;
        foreach ($tramos as $t) {
            if ((float) $t['porcentaje_excedente'] == 0.0 && (float) $t['impuesto_fraccion_basica'] == 0.0 && $t['exceso_hasta'] !== null) {
                $fb = max($fb, (float) $t['exceso_hasta']);
            }
        }
        $p = $this->renta->getParametrosAnio($anio);
        return [
            'fraccion_basica'   => round($fb, 2),
            'canasta_basica'    => (float) $p['canasta_basica'],
            'porcentaje_rebaja' => (float) $p['porcentaje_rebaja'],
            'factores'          => $p['factores'],
            'tramos'            => $tramos,
        ];
    }

    /** Parámetros de cálculo de un anexo ya guardado (cabecera + tramos del año). */
    private function parametrosDeCabecera(array $cab): array
    {
        $factores = json_decode((string) ($cab['factores_canastas'] ?? ''), true);
        return [
            'fraccion_basica'   => (float) $cab['fraccion_basica'],
            'canasta_basica'    => (float) $cab['canasta_basica'],
            'porcentaje_rebaja' => (float) $cab['porcentaje_rebaja'],
            'ipceg'             => (float) $cab['ipceg'],
            'factores'          => is_array($factores) && $factores ? $factores : ImpuestoRentaEmpleadoService::FACTORES_DEFECTO,
            'tramos'            => $this->renta->getTramosAnio((int) $cab['anio']),
        ];
    }

    public function guardarCabecera(int $id, int $idEmpresa, array $data, int $idUsuario): void
    {
        $cab = $this->repo->findById($id, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $d = [
            'anio'              => (int) $cab['anio'],
            'num_ruc'           => preg_replace('/\D/', '', (string) ($data['num_ruc'] ?? '')) ?: '',
            'razon_social'      => trim((string) ($data['razon_social'] ?? '')),
            'tipo_empleador'    => strtoupper(trim((string) ($data['tipo_empleador'] ?? 'PRIVADO_MIXTO'))),
            'ente_seg_social'   => strtoupper(trim((string) ($data['ente_seg_social'] ?? 'IESS'))),
            'fraccion_basica'   => (float) str_replace(',', '.', (string) ($data['fraccion_basica'] ?? 0)),
            'canasta_basica'    => (float) str_replace(',', '.', (string) ($data['canasta_basica'] ?? 0)),
            'porcentaje_rebaja' => (float) str_replace(',', '.', (string) ($data['porcentaje_rebaja'] ?? 18)),
            'ipceg'             => (float) str_replace(',', '.', (string) ($data['ipceg'] ?? 1.803)),
            'observaciones'     => trim((string) ($data['observaciones'] ?? '')),
        ];
        $this->rules->validarCabecera($d);

        $this->repo->beginTransaction();
        try {
            $this->repo->actualizarCabecera($id, $idEmpresa, $d, $idUsuario);
            $this->repo->setBorrador($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'anexo_rdep', $id, $cab, $d);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        // Los parámetros cambian el resumen de todas las filas.
        $this->recalcular($id, $idEmpresa, $idUsuario);
    }

    /**
     * Arma (o actualiza) una fila por cada trabajador con rol mensual, décimo o
     * utilidades en el ejercicio. Respeta los campos que el usuario editó a mano
     * (campos_manuales) y conserva los trabajadores agregados manualmente.
     * @return array{nuevos:int, actualizados:int, total:int}
     */
    public function importar(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $anio = (int) $cab['anio'];

        $roles    = $this->repo->getAcumuladoRoles($idEmpresa, $anio);
        $decimos  = $this->repo->getDecimosAcumulados($idEmpresa, $anio);
        $util     = (new UtilidadesRepository())->getPagadoPorEmpleadoEnAnio($idEmpresa, $anio);
        $gastos   = $this->repo->getGastosPersonales($idEmpresa, $anio);
        $ids      = array_unique(array_merge(array_keys($roles), array_keys($decimos), array_keys($util)));
        $fichas   = $this->repo->getEmpleados($idEmpresa, $ids);
        $empleador = $this->repo->getEmpleador($idEmpresa);
        $param    = $this->parametrosDeCabecera($cab);

        $existentes = [];
        foreach ($this->repo->getDetalle($idAnexo, $idEmpresa) as $d) {
            if (!empty($d['id_empleado'])) $existentes[(int) $d['id_empleado']] = $d;
        }

        $nuevos = 0;
        $actualizados = 0;
        $this->repo->beginTransaction();
        try {
            foreach ($ids as $idEmp) {
                $ficha = $fichas[$idEmp] ?? null;
                if (!$ficha) continue; // empleado eliminado: no se informa
                $prev = $existentes[$idEmp] ?? null;
                $fila = $this->filaDesdeNomina($ficha, $roles[$idEmp] ?? [], $decimos[$idEmp] ?? [], (float) ($util[$idEmp] ?? 0), $gastos[$idEmp] ?? null, $empleador['estab'], $anio);

                if ($prev) {
                    // Lo editado a mano manda; lo demás se refresca desde la nómina.
                    $manuales = json_decode((string) $prev['campos_manuales'], true) ?: [];
                    foreach ($manuales as $k) {
                        if (array_key_exists($k, $prev)) $fila[$k] = $prev[$k];
                    }
                    $fila['campos_manuales'] = $manuales;
                    $fila['observaciones']   = $prev['observaciones'];
                    $fila = array_merge($fila, $this->calc->calcular($fila, $param));
                    $this->repo->updateDetalle((int) $prev['id'], $idEmpresa, $fila, $idUsuario);
                    $actualizados++;
                } else {
                    $fila['campos_manuales'] = [];
                    $fila = array_merge($fila, $this->calc->calcular($fila, $param));
                    $this->repo->insertDetalle($idAnexo, $idEmpresa, $fila, $idUsuario);
                    $nuevos++;
                }
            }
            $this->repo->setBorrador($idAnexo, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'IMPORTAR', 'anexo_rdep', $idAnexo, null, ['anio' => $anio, 'nuevos' => $nuevos, 'actualizados' => $actualizados]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $totales = $this->validar($idAnexo, $idEmpresa, $idUsuario, true);
        return ['nuevos' => $nuevos, 'actualizados' => $actualizados, 'total' => $totales['trabajadores']];
    }

    /** Fila por defecto de un trabajador a partir de la ficha y la nómina del año. */
    private function filaDesdeNomina(array $ficha, array $rol, array $dec, float $util, ?array $gp, string $estab, int $anio): array
    {
        [$nombres, $apellidos] = $this->partirNombreCompleto((string) $ficha['nombres_apellidos']);
        $tipoId = CatalogoRdep::TIPO_ID_DESDE_FICHA[strtolower((string) $ficha['tipo_id'])] ?? 'C';
        $discap = $this->esVerdadero($ficha['discapacidad'] ?? false);
        $cargas = $gp ? (int) $gp['numero_cargas_familiares'] : (int) ($ficha['cargas_familiares'] ?? 0);

        return [
            'id_empleado'       => (int) $ficha['id'],
            'tip_id_ret'        => $tipoId,
            'id_ret'            => CatalogoRdep::limpiarIdentificacion((string) $ficha['identificacion']),
            'apellidos'         => CatalogoRdep::limpiarNombre($apellidos),
            'nombres'           => CatalogoRdep::limpiarNombre($nombres),
            'estab'             => $estab,
            'residencia'        => '01',
            'pais_residencia'   => CatalogoPaisesSri::ECUADOR,
            'aplica_convenio'   => 'NA',
            // La ficha solo tiene un sí/no de discapacidad: el porcentaje lo completa el usuario.
            'tipo_discap'       => $discap ? '02' : '01',
            'porcentaje_discap' => 0,
            'tip_id_discap'     => 'N',
            'id_discap'         => '999',
            'ben_galapagos'     => 'NO',
            'enf_catastro'      => ($gp && $this->esVerdadero($gp['caso_especial'])) ? 'SI' : 'NO',
            'num_cargas'        => min(AnexoRdepRules::MAX_CARGAS, max(0, $cargas)),
            'tercera_edad'      => $this->calc->esTerceraEdad($ficha['fecha_nacimiento'] ?? null, $anio),
            'fecha_nacimiento'  => $ficha['fecha_nacimiento'] ?? null,
            'suel_sal'          => round((float) ($rol['suel_sal'] ?? 0), 2),
            'sob_suel'          => round((float) ($rol['sob_suel'] ?? 0), 2),
            'part_util'         => round($util, 2),
            'int_grab_gen'      => 0.0,
            'imp_rent_empl'     => 0.0,
            'decim_ter'         => round((float) ($rol['decim_ter_rol'] ?? 0) + (float) ($dec['decim_ter'] ?? 0), 2),
            'decim_cuar'        => round((float) ($rol['decim_cuar_rol'] ?? 0) + (float) ($dec['decim_cuar'] ?? 0), 2),
            'fondo_reserva'     => round((float) ($rol['fondo_reserva'] ?? 0), 2),
            'salario_digno'     => 0.0,
            'otros_ing_no_grav' => 0.0,
            'sis_sal_net'       => 1,
            'apo_per_iess'      => round((float) ($rol['apo_per_iess'] ?? 0), 2),
            'apor_per_iess_otros' => 0.0,
            'deduc_vivienda'    => round((float) ($gp['vivienda'] ?? 0), 2),
            'deduc_salud'       => round((float) ($gp['salud'] ?? 0), 2),
            'deduc_educ'        => round((float) ($gp['educacion'] ?? 0), 2),
            'deduc_aliment'     => round((float) ($gp['alimentacion'] ?? 0), 2),
            'deduc_vestim'      => round((float) ($gp['vestimenta'] ?? 0), 2),
            'deduc_turismo'     => round((float) ($gp['turismo'] ?? 0), 2),
            'val_ret_otros'     => 0.0,
            'val_imp_asu_este'  => 0.0,
            'val_ret'           => round((float) ($rol['val_ret'] ?? 0), 2),
            'observaciones'     => null,
            'graves'            => [],
            'leves'             => [],
        ];
    }

    /** Agrega un trabajador a mano (desde la ficha de empleados o en blanco). */
    public function agregarTrabajador(int $idAnexo, int $idEmpresa, ?int $idEmpleado, int $idUsuario): int
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $anio = (int) $cab['anio'];
        $empleador = $this->repo->getEmpleador($idEmpresa);
        $param = $this->parametrosDeCabecera($cab);

        if ($idEmpleado) {
            foreach ($this->repo->getDetalle($idAnexo, $idEmpresa) as $d) {
                if ((int) $d['id_empleado'] === $idEmpleado) throw new Exception('Ese trabajador ya está en el anexo.');
            }
            $ficha = $this->repo->getEmpleados($idEmpresa, [$idEmpleado])[$idEmpleado] ?? null;
            if (!$ficha) throw new Exception('Empleado no encontrado.');
            $roles = $this->repo->getAcumuladoRoles($idEmpresa, $anio);
            $dec   = $this->repo->getDecimosAcumulados($idEmpresa, $anio);
            $util  = (new UtilidadesRepository())->getPagadoPorEmpleadoEnAnio($idEmpresa, $anio);
            $gp    = $this->repo->getGastosPersonales($idEmpresa, $anio)[$idEmpleado] ?? null;
            $fila  = $this->filaDesdeNomina($ficha, $roles[$idEmpleado] ?? [], $dec[$idEmpleado] ?? [], (float) ($util[$idEmpleado] ?? 0), $gp, $empleador['estab'], $anio);
        } else {
            $fila = $this->filaDesdeNomina(
                ['id' => null, 'tipo_id' => 'cedula', 'identificacion' => '', 'nombres_apellidos' => '', 'fecha_nacimiento' => null, 'discapacidad' => false, 'cargas_familiares' => 0],
                [], [], 0.0, null, $empleador['estab'], $anio
            );
            $fila['id_empleado'] = null;
        }
        $fila['campos_manuales'] = [];
        $fila = array_merge($fila, $this->calc->calcular($fila, $param));

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->insertDetalle($idAnexo, $idEmpresa, $fila, $idUsuario);
            $this->repo->setBorrador($idAnexo, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'anexo_rdep_detalle', $id, null, ['id_anexo' => $idAnexo, 'id_empleado' => $idEmpleado]);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->validar($idAnexo, $idEmpresa, $idUsuario);
        return $id;
    }

    /**
     * Guarda los campos editados de un trabajador, los marca como manuales (para
     * que importar no los pise), recalcula el resumen y revalida.
     */
    public function guardarTrabajador(int $idDetalle, int $idEmpresa, array $campos, int $idUsuario): array
    {
        $fila = $this->repo->findDetalle($idDetalle, $idEmpresa);
        if (!$fila) throw new Exception('Trabajador no encontrado en el anexo.');
        $cab = $this->repo->findById((int) $fila['id_anexo'], $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');

        $nuevos = $this->rules->normalizarTrabajador($campos);
        $manuales = json_decode((string) $fila['campos_manuales'], true) ?: [];
        foreach ($nuevos as $k => $v) {
            $actual = $fila[$k];
            $cambio = in_array($k, AnexoRdepRules::camposMonto(), true)
                ? abs((float) $actual - (float) $v) > 0.001
                : ((string) (is_bool($actual) ? ($actual ? 't' : 'f') : $actual) !== (string) (is_bool($v) ? ($v ? 't' : 'f') : $v));
            if ($cambio && $k !== 'observaciones' && !in_array($k, $manuales, true)) {
                $manuales[] = $k;
            }
            $fila[$k] = $v;
        }
        $fila['campos_manuales'] = $manuales;
        $fila = array_merge($fila, $this->calc->calcular($fila, $this->parametrosDeCabecera($cab)));
        $val = $this->rules->validarTrabajador($fila, $cab);
        $fila['graves'] = $val['graves'];
        $fila['leves']  = $val['leves'];

        $this->repo->beginTransaction();
        try {
            $this->repo->updateDetalle($idDetalle, $idEmpresa, $fila, $idUsuario);
            $this->repo->setBorrador((int) $fila['id_anexo'], $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'anexo_rdep_detalle', $idDetalle, null, $nuevos);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->validar((int) $fila['id_anexo'], $idEmpresa, $idUsuario);
        return $this->repo->findDetalle($idDetalle, $idEmpresa) ?? $fila;
    }

    /** Quita de nuevo un campo del listado de manuales: vuelve a tomar el valor de la nómina al importar. */
    public function eliminarTrabajador(int $idDetalle, int $idEmpresa, int $idUsuario): void
    {
        $fila = $this->repo->findDetalle($idDetalle, $idEmpresa);
        if (!$fila) throw new Exception('Trabajador no encontrado en el anexo.');
        $this->repo->beginTransaction();
        try {
            $this->repo->deleteDetalle($idDetalle, $idEmpresa);
            $this->repo->setBorrador((int) $fila['id_anexo'], $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'anexo_rdep_detalle', $idDetalle, $fila, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        $this->validar((int) $fila['id_anexo'], $idEmpresa, $idUsuario);
    }

    /** Recalcula el resumen impositivo de todas las filas con los parámetros vigentes de la cabecera. */
    public function recalcular(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $param = $this->parametrosDeCabecera($cab);
        $this->repo->beginTransaction();
        try {
            foreach ($this->repo->getDetalle($idAnexo, $idEmpresa) as $d) {
                $d = array_merge($d, $this->calc->calcular($d, $param));
                $this->repo->updateDetalle((int) $d['id'], $idEmpresa, $d, $idUsuario);
            }
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
        return $this->validar($idAnexo, $idEmpresa, $idUsuario);
    }

    /**
     * Valida todas las filas como lo haría el SRI, guarda los mensajes en cada
     * una y actualiza los totales de la cabecera.
     * @return array{trabajadores:int, graves:int, leves:int, total_ingresos:float, total_retenido:float, sin_tramos:bool}
     */
    public function validar(int $idAnexo, int $idEmpresa, int $idUsuario, bool $importado = false): array
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $filas = $this->repo->getDetalle($idAnexo, $idEmpresa);
        $dup = array_flip($this->rules->duplicados($filas));

        $graves = 0;
        $leves = 0;
        $ingresos = 0.0;
        $retenido = 0.0;
        foreach ($filas as $f) {
            $v = $this->rules->validarTrabajador($f, $cab);
            if (isset($dup[$f['tip_id_ret'] . '|' . $f['id_ret']])) {
                $v['graves'][] = 'Identificación repetida en el anexo.';
            }
            $this->repo->setValidaciones((int) $f['id'], $v['graves'], $v['leves']);
            $graves += count($v['graves']);
            $leves  += count($v['leves']);
            $ingresos += (float) $f['ing_grav_este_empl'];
            $retenido += (float) $f['val_ret'];
        }
        $this->repo->setTotales($idAnexo, count($filas), round($ingresos, 2), round($retenido, 2), $graves, $leves, $idUsuario, $importado);
        return [
            'trabajadores'   => count($filas),
            'graves'         => $graves,
            'leves'          => $leves,
            'total_ingresos' => round($ingresos, 2),
            'total_retenido' => round($retenido, 2),
            'sin_tramos'     => $this->renta->getTramosAnio((int) $cab['anio']) === [],
        ];
    }

    /** Genera RDEP-aaaa.xml y su zip; valida contra el XSD oficial. */
    public function generar(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $this->rules->validarCabecera($cab);
        $res = $this->recalcular($idAnexo, $idEmpresa, $idUsuario);
        $this->rules->validarGeneracion($cab, $res['graves'], $res['trabajadores']);

        $filas = $this->repo->getDetalle($idAnexo, $idEmpresa);
        $contenido = $this->xml->generar($cab, $filas);
        $erroresXsd = $this->xml->validarContraXsd($contenido);
        if ($erroresXsd !== []) {
            throw new Exception('El archivo no cumple el esquema del SRI: ' . implode(' | ', array_slice($erroresXsd, 0, 3)));
        }

        $anio = (int) $cab['anio'];
        $dir = $this->dirSalida($idEmpresa);
        $nombreXml = 'RDEP-' . $anio . '.xml';
        $nombreZip = 'RDEP-' . $anio . '.zip';
        $rutaXml = $dir . '/' . $nombreXml;
        // El ejemplo del SRI va en ISO-8859-1; los textos ya son ASCII (nombres limpios).
        $bytes = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $contenido) ?: $contenido;
        if (file_put_contents($rutaXml, $bytes) === false) {
            throw new Exception('No se pudo escribir el archivo XML en ' . $dir . '.');
        }
        $this->comprimir($rutaXml, $dir . '/' . $nombreZip, $nombreXml);

        $this->repo->setGenerado($idAnexo, $idEmpresa, $nombreXml, $idUsuario);
        $this->log->registrar($idUsuario, $idEmpresa, 'GENERAR', 'anexo_rdep', $idAnexo, null, ['anio' => $anio, 'trabajadores' => $res['trabajadores'], 'archivo' => $nombreXml]);

        return [
            'trabajadores' => $res['trabajadores'],
            'leves'        => $res['leves'],
            'nombre_xml'   => $nombreXml,
            'nombre_zip'   => is_file($dir . '/' . $nombreZip) ? $nombreZip : null,
        ];
    }

    public function rutaArchivo(int $idEmpresa, string $nombre): ?string
    {
        if (!preg_match('/^RDEP-\d{4}\.(xml|zip)$/', $nombre)) return null;
        $ruta = $this->dirSalida($idEmpresa) . '/' . $nombre;
        return is_file($ruta) ? $ruta : null;
    }

    public function eliminar(int $idAnexo, int $idEmpresa, int $idUsuario): void
    {
        $cab = $this->repo->findById($idAnexo, $idEmpresa);
        if (!$cab) throw new Exception('Anexo no encontrado.');
        $this->repo->beginTransaction();
        try {
            $this->repo->eliminarLogico($idAnexo, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'anexo_rdep', $idAnexo, $cab, null);
            $this->repo->commit();
        } catch (Exception $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    private function dirSalida(int $idEmpresa): string
    {
        $dir = rtrim(dirname(__DIR__, 3), '/\\') . '/storage/anexos/rdep/' . $idEmpresa;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function comprimir(string $rutaXml, string $rutaZip, string $nombreInterno): void
    {
        if (!class_exists(ZipArchive::class)) return;
        $zip = new ZipArchive();
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($rutaXml, $nombreInterno);
            $zip->close();
        }
    }

    /** Misma partición que los décimos: nombres primero, luego apellidos. */
    private function partirNombreCompleto(string $completo): array
    {
        $partes = preg_split('/\s+/', trim($completo)) ?: [];
        $n = count($partes);
        if ($n <= 1) return [$completo, ''];
        $mitad = (int) ceil($n / 2);
        return [implode(' ', array_slice($partes, 0, $mitad)), implode(' ', array_slice($partes, $mitad))];
    }

    private function esVerdadero($v): bool
    {
        if (is_bool($v)) return $v;
        return in_array(strtolower((string) $v), ['1', 't', 'true'], true);
    }
}
