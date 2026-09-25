<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\models\IdentificadorCompradorVendedor;
use App\repositories\modulos\AlumnoRepository;
use App\Rules\modulos\AlumnoRules;
use App\Services\LogSistemaService;
use Exception;

class AlumnoService
{
    private AlumnoRepository $repository;
    private AlumnoRules $rules;
    private LogSistemaService $logService;

    public function __construct(AlumnoRepository $repository, AlumnoRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    public function crear(array $data): int
    {
        $data['config_facturacion'] = $this->getConfigFacturacion((int) $data['id_empresa']);
        $this->rules->validar($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->create($data);
            $this->guardarInfoAdicional($id, $idEmpresa, $data);
            $data['servicios'] = $this->prepararServicios($data['servicios'] ?? [], $idEmpresa, $idUsuario, $data['config_facturacion']);

            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['horarios'])) {
                $this->repository->syncHorarios($id, $idEmpresa, $data['horarios'], $idUsuario);
            }
            if (isset($data['servicios'])) {
                $this->repository->syncServicios($id, $idEmpresa, $data['servicios'], $idUsuario);
            }
            // Sin la tabla (SQL 20260924 aún no aplicado) se omite: ver existeTablaRepresentantes().
            if (isset($data['representantes']) && $this->repository->existeTablaRepresentantes()) {
                $this->repository->syncRepresentantes($id, $idEmpresa, $data['representantes'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'alumnos', $id, null, $data);

            $this->repository->commit();
            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $data['config_facturacion'] = $this->getConfigFacturacion((int) $data['id_empresa']);
        $this->rules->validar($data);

        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }

        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            $this->guardarInfoAdicional($id, $idEmpresa, $data);
            $data['servicios'] = $this->prepararServicios($data['servicios'] ?? [], $idEmpresa, $idUsuario, $data['config_facturacion']);

            if (isset($data['periodos'])) {
                $this->repository->syncPeriodos($id, $idEmpresa, $data['periodos'], $idUsuario);
            }
            if (isset($data['horarios'])) {
                $this->repository->syncHorarios($id, $idEmpresa, $data['horarios'], $idUsuario);
            }
            if (isset($data['servicios'])) {
                $this->repository->syncServicios($id, $idEmpresa, $data['servicios'], $idUsuario);
            }
            // Sin la tabla (SQL 20260924 aún no aplicado) se omite: ver existeTablaRepresentantes().
            if (isset($data['representantes']) && $this->repository->existeTablaRepresentantes()) {
                $this->repository->syncRepresentantes($id, $idEmpresa, $data['representantes'], $idUsuario);
            }

            $this->logService->registrar($idUsuario, $idEmpresa, 'actualizar', 'alumnos', $id, $antes, $data);

            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->repository->findById($id, $idEmpresa);
        if (!$antes) {
            throw new Exception('El alumno no existe o ya ha sido eliminado.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos', $id, $antes, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Reglas de facturación del establecimiento principal, las mismas que lee la
     * Factura de Venta (FacturaVentaController::index): ingreso libre, editar IVA
     * y la tarifa por defecto de los ítems libres.
     */
    public function getConfigFacturacion(int $idEmpresa): array
    {
        $toBool = fn($v) => ($v === true || $v === 't' || $v === 'true' || $v === 1 || $v === '1');
        $cfg = [];
        $est = (new \App\models\Empresa())->getEstablecimientos($idEmpresa);
        if (!empty($est)) {
            try {
                $cfg = (new \App\repositories\modulos\EmpresaRepository())->getEstablecimientoConfig((int) $est[0]['id']) ?? [];
            } catch (\Throwable $e) {
                $cfg = []; // configuración pendiente de migrar: valores por defecto
            }
        }
        return [
            'facturacion_libre'           => $toBool($cfg['facturacion_libre'] ?? true),
            'editar_iva_factura'          => $toBool($cfg['editar_iva_factura'] ?? true),
            'editar_precio_factura'       => $toBool($cfg['editar_precio_factura'] ?? true),
            'id_tarifa_iva_defecto_libre' => !empty($cfg['id_tarifa_iva_defecto_libre']) ? (int) $cfg['id_tarifa_iva_defecto_libre'] : null,
        ];
    }

    public function getTarifasIva(): array
    {
        return $this->repository->getTarifasIva();
    }

    /**
     * Deja los servicios listos para guardar:
     *  - ítem libre → se crea en el catálogo como servicio (mismo
     *    FacturaVentaRepository::crearServicioLibre() de la factura) con su IVA;
     *  - IVA de la línea igual al del producto → NULL (sigue al producto);
     *  - sin permiso de editar IVA → NULL (el del producto, siempre).
     * Corre dentro de la transacción del guardado del alumno.
     */
    private function prepararServicios(array $servicios, int $idEmpresa, int $idUsuario, array $config): array
    {
        $tarifas = [];
        foreach ($this->repository->getTarifasIva() as $t) {
            $tarifas[(int) $t['id']] = $t;
        }
        $factRepo = null;
        foreach ($servicios as &$s) {
            $idTarifa = !empty($s['id_tarifa_iva']) ? (int) $s['id_tarifa_iva'] : null;
            if ($idTarifa !== null && !isset($tarifas[$idTarifa])) {
                throw new Exception('La tarifa de IVA elegida en un servicio no existe o está inactiva.');
            }
            if (!empty($s['es_libre']) && empty($s['id_producto'])) {
                $idTarifa = $idTarifa ?? $config['id_tarifa_iva_defecto_libre'];
                $t = $idTarifa !== null ? ($tarifas[$idTarifa] ?? null) : null;
                $factRepo = $factRepo ?? new \App\repositories\modulos\FacturaVentaRepository();
                $nuevo = $factRepo->crearServicioLibre(
                    $idEmpresa,
                    $idUsuario,
                    AlumnoRules::normalizarConcepto((string) ($s['nombre_libre'] ?? '')),
                    ($s['precio_override'] ?? '') !== '' ? (float) $s['precio_override'] : 0.0,
                    $t !== null ? (float) $t['porcentaje_iva'] : null,
                    $t !== null ? (string) $t['codigo'] : null
                );
                $s['id_producto']   = (int) $nuevo['id'];
                $s['id_tarifa_iva'] = null; // ya es el IVA del servicio recién creado
                continue;
            }
            $s['id_tarifa_iva'] = $config['editar_iva_factura'] ? $idTarifa : null;
            // Sin permiso de editar precio: el del producto (precio base vigente).
            if (empty($config['editar_precio_factura'])) {
                $s['precio_override'] = '';
            }
        }
        unset($s);

        // Si el IVA elegido es el mismo del producto, no se fija: la línea sigue al producto.
        $tarProd = $this->repository->getTarifasProductos(array_column($servicios, 'id_producto'), $idEmpresa);
        foreach ($servicios as &$s) {
            if (!empty($s['id_tarifa_iva']) && ($tarProd[(int) ($s['id_producto'] ?? 0)] ?? null) === (int) $s['id_tarifa_iva']) {
                $s['id_tarifa_iva'] = null;
            }
        }
        unset($s);
        return $servicios;
    }

    /** Filas concepto/detalle de las facturas del alumno (si el SQL ya se aplicó). */
    private function guardarInfoAdicional(int $id, int $idEmpresa, array $data): void
    {
        if (!array_key_exists('info_adicional', $data) || !$this->repository->existeColumnasFacturacion()) {
            return;
        }
        $filas = $data['info_adicional'];
        $this->repository->guardarInfoAdicional($id, $idEmpresa, is_array($filas) ? array_map(fn($f) => [
            'concepto' => trim((string) ($f['concepto'] ?? '')),
            'detalle'  => trim((string) ($f['detalle'] ?? '')),
        ], $filas) : null);
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $alumno = $this->repository->findDetalle($id, $idEmpresa);
        if (!$alumno) {
            return null;
        }
        $alumno['periodos']   = $this->repository->getPeriodos($id, $idEmpresa);
        $alumno['horarios']   = $this->repository->getHorarios($id, $idEmpresa);
        $alumno['servicios']  = $this->repository->getServicios($id, $idEmpresa);
        $alumno['representantes'] = $this->repository->getRepresentantes($id, $idEmpresa);
        $alumno['documentos'] = $this->repository->getDocumentos($id, $idEmpresa);
        return $alumno;
    }

    public function agregarDocumento(int $idAlumno, int $idEmpresa, array $data, int $idUsuario): array
    {
        $alumno = $this->repository->findById($idAlumno, $idEmpresa);
        if (!$alumno) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }
        if (empty($data['tipo_documento'])) {
            throw new Exception('El tipo de documento es obligatorio.');
        }
        if (empty($data['ruta_archivo'])) {
            throw new Exception('No se recibió el archivo a adjuntar.');
        }

        $this->repository->beginTransaction();
        try {
            $id = $this->repository->agregarDocumento($idAlumno, $idEmpresa, $data, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'crear', 'alumnos_documentos', $id, null, $data);
            $this->repository->commit();
            return $this->repository->getDocumentos($idAlumno, $idEmpresa);
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function eliminarDocumento(int $idDocumento, int $idAlumno, int $idEmpresa, int $idUsuario): array
    {
        $this->repository->beginTransaction();
        try {
            $antes = $this->repository->eliminarDocumento($idDocumento, $idAlumno, $idEmpresa, $idUsuario);
            if (!$antes) {
                throw new Exception('El documento no existe o ya fue eliminado.');
            }
            $this->logService->registrar($idUsuario, $idEmpresa, 'eliminar', 'alumnos_documentos', $idDocumento, $antes, null);
            $this->repository->commit();
            return $this->repository->getDocumentos($idAlumno, $idEmpresa);
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function getPuntosEmisionParaSelect(int $idEmpresa): array
    {
        return $this->repository->getPuntosEmisionParaSelect($idEmpresa);
    }

    /**
     * Tipos de identificación que se ofrecen en el modal del alumno: del
     * catálogo global de identificadores de comprador, solo los que aplican a
     * una persona natural (ver AlumnoRules::TIPOS_IDENTIFICACION). El catálogo
     * es global, así que no se filtra por empresa.
     */
    public function getTiposIdentificacionParaSelect(): array
    {
        $todos = (new IdentificadorCompradorVendedor())->getAll('codigo', 'ASC');

        return array_values(array_filter($todos, fn($r) =>
            (int)($r['tipo'] ?? 0) === IdentificadorCompradorVendedor::TIPO_COMPRADOR
            && (int)($r['status'] ?? 1) === 1
            && in_array((string)($r['codigo'] ?? ''), AlumnoRules::TIPOS_IDENTIFICACION, true)
        ));
    }
}
