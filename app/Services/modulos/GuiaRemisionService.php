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

        // RUC Proveedor (Res. NAC-DGERCGC26-00000027) se agrega aquí, al crear —
        // así solo los documentos nuevos lo llevan; los ya emitidos no se alteran.
        $adicionales = \App\Helpers\SriProveedorHelper::conRucProveedor($adicionales);

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
                $id,
                null,
                $this->repo->getPorId($id)
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

        // La clave se regenera en prepararData(); se le pasa la actual para
        // conservar su código numérico.
        $data['clave_acceso'] = $actual['clave_acceso'] ?? null;

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
                $id,
                $actual,
                $this->repo->getPorId($id)
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
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'guias_remision_cabecera', $id, $actual, null);
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

        $estadoActual = $actual['estado'] ?? '';
        if ($estadoActual === 'anulado') {
            throw new \RuntimeException('La guía ya está anulada.');
        }
        // Anular es para comprobantes que existen ante el SRI. Una guía que no
        // llegó a autorizarse se elimina, no se anula.
        if ($estadoActual !== 'autorizado') {
            throw new \RuntimeException('Solo se pueden anular guías autorizadas por el SRI. Para descartar una guía en borrador, elimínela.');
        }

        // Misma regla que en factura de venta: la anulación real se hace en el
        // portal del SRI. Aquí solo se refleja cuando el SRI ya dejó de
        // reportarla como AUTORIZADO.
        $claveAcceso = trim((string)($actual['clave_acceso'] ?? ''));
        if ($claveAcceso !== '') {
            $tipoAmbiente = (string)($actual['tipo_ambiente'] ?? '1');
            $consulta  = (new \App\Services\Sri\SriEnvioService())->verificarAutorizacion($claveAcceso, $tipoAmbiente);
            $estadoSri = strtoupper($consulta['estado'] ?? '');
            if ($estadoSri === 'AUTORIZADO') {
                throw new \RuntimeException(
                    'No se puede anular: la guía sigue AUTORIZADA en el SRI. ' .
                    'Primero debe anularla en el portal del SRI; cuando deje de estar autorizada podrá anularla aquí.'
                );
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->actualizarEstado($id, 'anulado', $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR', 'guias_remision_cabecera', $id, $actual, ['estado' => 'anulado']);
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

        $empresa = (new \App\models\Empresa())->getPorId((int)$data['id_empresa']) ?? [];

        // Ambiente y tipo de emisión los define la empresa, NUNCA el formulario:
        // la clave de acceso lleva el ambiente en su posición 24 y el SRI rechaza
        // el comprobante ("error en la estructura de la clave de acceso") si no
        // coincide con el ambiente al que se envía. Igual que en factura de venta.
        $data['tipo_ambiente'] = (string) ($empresa['tipo_ambiente'] ?? '1');
        $data['tipo_emision']  = (string) ($empresa['tipo_emision']  ?? '1');

        // Fecha que va DENTRO de la clave de acceso: para la guía de remisión es
        // la de inicio de transporte, no la de emisión. El XML de la guía no
        // tiene <fechaEmision>, así que el SRI toma <fechaIniTransporte> como
        // fecha del comprobante y la contrasta con la clave; si no coinciden
        // responde "ERROR EN LA ESTRUCTURA DE LA CLAVE DE ACCESO".
        $fechaClave = !empty($data['fecha_inicio_transporte'])
            ? (string) $data['fecha_inicio_transporte']
            : (string) $data['fecha_emision'];

        // La clave de acceso se regenera SIEMPRE: depende de esa fecha, del
        // establecimiento, el punto de emisión, el secuencial y el ambiente, y
        // cualquiera de ellos pudo cambiar al editar el borrador. Se conserva el
        // código numérico de la clave anterior para no alterarla sin motivo.
        $codigoNumerico = ClaveAccesoService::extraerCodigoNumerico((string) ($data['clave_acceso'] ?? ''));
        $data['clave_acceso'] = ClaveAccesoService::generar(
            $fechaClave,
            ClaveAccesoService::GUIA_REMISION,
            (string) ($empresa['ruc'] ?? ''),
            $data['tipo_ambiente'],
            (string) $data['establecimiento'],
            (string) $data['punto_emision'],
            (string) $data['secuencial'],
            $data['tipo_emision'],
            $codigoNumerico
        );

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
