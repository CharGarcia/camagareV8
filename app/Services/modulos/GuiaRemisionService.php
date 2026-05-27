<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\GuiaRemisionRepository;
use App\Rules\modulos\GuiaRemisionRules;
use App\Services\LogSistemaService;
use App\Services\ClaveAccesoService;
use App\Services\Xml\XmlGuiaRemisionService;
use App\core\Database;

class GuiaRemisionService
{
    private GuiaRemisionRepository $repo;
    private GuiaRemisionRules      $rules;
    private LogSistemaService      $log;

    public function __construct(
        GuiaRemisionRepository $repo,
        GuiaRemisionRules      $rules,
        LogSistemaService      $log
    ) {
        $this->repo  = $repo;
        $this->rules = $rules;
        $this->log   = $log;
    }

    public function crear(array $data): int
    {
        $this->rules->validarGuardar($data);

        $adicionales = $data['adicionales'] ?? [];
        if (!empty($adicionales)) {
            $this->rules->validarAdicionales($adicionales);
        }

        if ($this->repo->existeSecuencial(
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (string) $data['secuencial']
        )) {
            throw new \Exception('El número de secuencial ya existe para este punto de emisión. Recargue e intente nuevamente.');
        }

        $data = $this->prepararData($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->insertarCabecera($data);

            foreach ($data['detalles'] as $detalle) {
                $this->repo->insertarDetalle($id, $detalle);
            }

            foreach ($adicionales as $a) {
                $this->repo->insertarAdicional($id, trim($a['nombre']), trim($a['valor']));
            }

            $this->log->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'CREAR',
                'guias_remision_cabecera',
                null,
                ['id' => $id, 'secuencial' => $data['secuencial'], 'estado' => $data['estado']]
            );

            $db->commit();
            $this->generarYGuardarXml($id, $data);
            return $id;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): void
    {
        $this->rules->validarGuardar($data);

        $adicionales = $data['adicionales'] ?? [];
        if (!empty($adicionales)) {
            $this->rules->validarAdicionales($adicionales);
        }

        $actual = $this->repo->getPorId($id);
        if (!$actual || (int)($actual['id_empresa'] ?? 0) !== (int)$data['id_empresa']) {
            throw new \RuntimeException('Guía de remisión no encontrada.');
        }
        if (($actual['estado'] ?? '') !== 'borrador') {
            throw new \RuntimeException('Solo se pueden editar guías en estado borrador.');
        }

        if ($this->repo->existeSecuencial(
            (int) $data['id_empresa'],
            (int) $data['id_establecimiento'],
            (int) $data['id_punto_emision'],
            (string) $data['secuencial'],
            $id
        )) {
            throw new \Exception('El número de secuencial ya está en uso por otro documento.');
        }

        $data = $this->prepararData($data);

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->actualizarCabecera($id, $data);
            $this->repo->eliminarDetalles($id);
            foreach ($data['detalles'] as $detalle) {
                $this->repo->insertarDetalle($id, $detalle);
            }
            $this->repo->eliminarAdicionales($id);
            foreach ($adicionales as $a) {
                $this->repo->insertarAdicional($id, trim($a['nombre']), trim($a['valor']));
            }

            $this->log->registrar(
                (int) $data['id_usuario'],
                (int) $data['id_empresa'],
                'ACTUALIZAR',
                'guias_remision_cabecera',
                $actual,
                ['id' => $id, 'secuencial' => $data['secuencial']]
            );

            $db->commit();
            $this->generarYGuardarXml($id, $data);
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getPorId($id);
        if (!$actual || (int)($actual['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \RuntimeException('Guía de remisión no encontrada.');
        }
        if (!in_array($actual['estado'] ?? '', ['borrador', 'anulado'], true)) {
            throw new \RuntimeException('Solo se pueden eliminar guías en estado borrador o anulado.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'guias_remision_cabecera', $actual, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getPorId($id);
        if (!$actual || (int)($actual['id_empresa'] ?? 0) !== $idEmpresa) {
            throw new \RuntimeException('Guía de remisión no encontrada.');
        }
        if (($actual['estado'] ?? '') === 'anulado') {
            throw new \RuntimeException('La guía ya está anulada.');
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->actualizarEstado($id, 'anulado', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR', 'guias_remision_cabecera', $actual, ['estado' => 'anulado']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function prepararData(array $data): array
    {
        $data['placa']           = mb_strtoupper(trim($data['placa'] ?? ''));
        $data['motivo_traslado'] = trim($data['motivo_traslado'] ?? '');
        $data['direccion_partida'] = trim($data['direccion_partida'] ?? '');
        $data['direccion_destino'] = trim($data['direccion_destino'] ?? '');
        $data['ruta']            = trim($data['ruta'] ?? '') ?: null;
        $data['observaciones']   = trim($data['observaciones'] ?? '') ?: null;
        $data['cod_doc_sustento']             = trim($data['cod_doc_sustento'] ?? '') ?: null;
        $data['num_doc_sustento']             = trim($data['num_doc_sustento'] ?? '') ?: null;
        $data['num_autorizacion_doc_sustento']= trim($data['num_autorizacion_doc_sustento'] ?? '') ?: null;
        $data['fecha_emision_doc_sustento']   = trim($data['fecha_emision_doc_sustento'] ?? '') ?: null;
        $data['doc_aduanero_unico']           = trim($data['doc_aduanero_unico'] ?? '') ?: null;
        $data['cod_establecimiento_destino']  = trim($data['cod_establecimiento_destino'] ?? '') ?: null;

        $data['secuencial'] = str_pad((string)((int) ltrim($data['secuencial'], '0') ?: 0), 9, '0', STR_PAD_LEFT);

        // Generar clave de acceso si no existe
        if (empty($data['clave_acceso'])) {
            $empresa = (new \App\models\Empresa())->getPorId((int)$data['id_empresa']);
            if ($empresa) {
                $data['clave_acceso'] = ClaveAccesoService::generar(
                    $data['fecha_emision'],
                    ClaveAccesoService::GUIA_REMISION,
                    $empresa['ruc'] ?? '',
                    $data['tipo_ambiente'] ?? '1',
                    $data['establecimiento'],
                    $data['punto_emision'],
                    $data['secuencial']
                );
            }
        }

        // Normalizar detalles
        $detalles = [];
        foreach (($data['detalles'] ?? []) as $d) {
            $detalles[] = [
                'id_producto'     => !empty($d['id_producto']) ? (int)$d['id_producto'] : null,
                'codigo_principal'=> trim($d['codigo_principal'] ?? ''),
                'codigo_auxiliar' => trim($d['codigo_auxiliar']  ?? ''),
                'descripcion'     => mb_strtoupper(trim($d['descripcion'] ?? '')),
                'cantidad'        => (float) ($d['cantidad'] ?? 1),
            ];
        }
        $data['detalles'] = $detalles;

        return $data;
    }

    // ── XML en base de datos ──────────────────────────────────────────────────

    private function generarYGuardarXml(int $idGuia, array $data): void
    {
        try {
            $cabecera = $this->repo->getPorId($idGuia);
            if (!$cabecera) return;

            $detalles      = $this->repo->getDetalles($idGuia);
            $infoAdicional = $this->repo->getInfoAdicional($idGuia);

            $idEmpresa    = (int) $cabecera['id_empresa'];
            $empresaModel = new \App\models\Empresa();
            $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];

            $dirEstablecimiento = null;
            if (!empty($cabecera['id_establecimiento'])) {
                try {
                    $estRepo = new \App\repositories\modulos\EmpresaRepository();
                    foreach ($estRepo->getEstablecimientos($idEmpresa) as $est) {
                        if ((int)$est['id'] === (int)$cabecera['id_establecimiento']) {
                            $dirEstablecimiento = $est['direccion'] ?? null;
                            break;
                        }
                    }
                } catch (\Throwable) {}
            }

            $xml = (new XmlGuiaRemisionService())->generar($cabecera, $detalles, $infoAdicional, $empresa, $dirEstablecimiento);
            $this->repo->updateDetalleXml($idGuia, $xml);
        } catch (\Throwable $e) {
            error_log('[Guia] Error generando XML para guía #' . $idGuia . ': ' . $e->getMessage());
        }
    }
}
