<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ChequeRepository;
use App\Services\PlantillasPdfRendererService;
use App\Services\LogSistemaService;
use App\models\Empresa;

/**
 * Orquesta la impresión de cheques (pagos de egreso tipo CHEQUE):
 *  - Lista los cheques por imprimir y su estado.
 *  - Genera el PDF usando la plantilla 'cheque' del módulo Plantillas de
 *    Documentos (o una plantilla por defecto si la empresa aún no configuró una).
 *  - Registra cada impresión en cheques_impresos (control anti-error) con
 *    transacción y auditoría.
 */
class ChequeImpresionService
{
    private ChequeRepository $repo;
    private LogSistemaService $log;

    /**
     * Plantilla por defecto de cheque (fallback), según el ESTÁNDAR de cheque en
     * Ecuador: 15.5 cm × 7.5 cm, todo el texto en MAYÚSCULAS. Coordenadas en mm
     * medidas desde la esquina superior izquierda del cheque:
     *  - Beneficiario ("Páguese a la orden de"): arriba 24, izq. 20 → 100 (w=80).
     *  - Valor en número: izq. 120, misma fila (y=24).
     *  - Valor en letras: arriba 30, izq. 20 (puede envolver a la fila siguiente).
     *  - Ciudad, fecha (aaaa-mm-dd): arriba 43, izq. 10.
     * El usuario puede afinarla en Plantillas de Documentos.
     */
    private const PLANTILLA_DEFAULT = '{
        "pagina": {"formato":"CUSTOM","ancho":155,"alto":75,"orientacion":"L","margenTop":0,"margenLeft":0,"margenRight":0,"margenBottom":0},
        "elementos": [
            {"tipo":"campo","campo":"{beneficiario}","x":20,"y":24,"w":80,"h":6,"alineacion":"L","fuente":"helvetica","tamano":10},
            {"tipo":"campo","campo":"{monto_numero_protegido}","x":120,"y":24,"w":33,"h":6,"alineacion":"L","fuente":"helvetica","tamano":11,"estilo":"B"},
            {"tipo":"campo","campo":"{monto_letras}","x":20,"y":30,"w":133,"h":10,"alineacion":"L","fuente":"helvetica","tamano":9},
            {"tipo":"campo","campo":"{ciudad_fecha}","x":10,"y":43,"w":120,"h":6,"alineacion":"L","fuente":"helvetica","tamano":10}
        ]
    }';

    public function __construct()
    {
        $this->repo = new ChequeRepository();
        $this->log  = new LogSistemaService();
    }

    // ── Consultas para la UI ───────────────────────────────────────────────────

    /** Cheques por imprimir para el modal masivo. */
    public function listarPorImprimir(int $idEmpresa, array $filtros): array
    {
        return $this->repo->getChequesPorImprimir($idEmpresa, $filtros);
    }

    /** Estado de impresión de uno o varios pagos-cheque. */
    public function estado(int $idEmpresa, array $idsPago): array
    {
        return $this->repo->getEstadoImpreso($idEmpresa, $idsPago);
    }

    // ── Impresión ──────────────────────────────────────────────────────────────

    /**
     * Genera el PDF de los cheques indicados y registra cada impresión.
     * Devuelve el binario del PDF (para que el controlador fije cabeceras).
     *
     * @param int[] $idsPago  ids de egresos_pagos (tipo CHEQUE)
     * @throws \RuntimeException si no hay cheques válidos
     */
    public function imprimirLote(int $idEmpresa, array $idsPago, int $idUsuario): string
    {
        $cheques = $this->repo->getChequesPorPagos($idEmpresa, $idsPago);
        if (empty($cheques)) {
            throw new \RuntimeException('No se encontraron cheques válidos para imprimir.');
        }

        $empresa   = $this->cargarEmpresa($idEmpresa);
        $plantilla = $this->getPlantilla($idEmpresa);

        // Generar el PDF en memoria ANTES de registrar (si falla el render, no
        // marcamos como impreso).
        $renderer = new PlantillasPdfRendererService();
        $pdf      = $renderer->generarCheques($plantilla, $cheques, $empresa, 'S');
        if (!is_string($pdf) || $pdf === '') {
            throw new \RuntimeException('No se pudo generar el PDF de los cheques.');
        }

        // Registrar cada impresión (transacción + auditoría).
        $this->repo->beginTransaction();
        try {
            foreach ($cheques as $chq) {
                $idPago      = (int) $chq['id_pago'];
                $yaImpreso   = $this->repo->contarImpresiones($idEmpresa, $idPago) > 0;
                $idImpresion = $this->repo->registrarImpreso([
                    'id_empresa'         => $idEmpresa,
                    'id_egreso_pago'     => $idPago,
                    'id_egreso'          => (int) ($chq['id_egreso'] ?? 0),
                    'id_forma_pago'      => (int) ($chq['id_forma_pago'] ?? 0),
                    'numero_cheque'      => $chq['numero_cheque'] ?? null,
                    'beneficiario'       => $chq['beneficiario'] ?? null,
                    'beneficiario_ident' => $chq['beneficiario_ident'] ?? null,
                    'monto'              => (float) ($chq['monto'] ?? 0),
                    'fecha_cheque'       => $chq['fecha_cheque'] ?? null,
                    'banco_nombre'       => $chq['banco_nombre'] ?? null,
                    'cuenta_numero'      => $chq['cuenta_numero'] ?? null,
                    'es_reimpresion'     => $yaImpreso,
                    'impreso_por'        => $idUsuario,
                ]);

                $this->log->registrar(
                    $idUsuario,
                    $idEmpresa,
                    $yaImpreso ? 'REIMPRIMIR_CHEQUE' : 'IMPRIMIR_CHEQUE',
                    'cheques_impresos',
                    $idImpresion,
                    null,
                    [
                        'id_egreso_pago' => $idPago,
                        'numero_cheque'  => $chq['numero_cheque'] ?? '',
                        'beneficiario'   => $chq['beneficiario'] ?? '',
                        'monto'          => (float) ($chq['monto'] ?? 0),
                        'reimpresion'    => $yaImpreso,
                    ]
                );
            }
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $pdf;
    }

    /** Anula las impresiones vigentes de un cheque (p. ej. cheque dañado). */
    public function anularImpresiones(int $idEmpresa, int $idPago, int $idUsuario): int
    {
        $n = $this->repo->anularImpresiones($idEmpresa, $idPago, $idUsuario);
        if ($n > 0) {
            $this->log->registrar($idUsuario, $idEmpresa, 'ANULAR_IMPRESION_CHEQUE',
                'cheques_impresos', $idPago, null, ['id_egreso_pago' => $idPago, 'anuladas' => $n]);
        }
        return $n;
    }

    // ── Internos ────────────────────────────────────────────────────────────────

    /** Plantilla activa 'cheque' de la empresa o la de por defecto. */
    private function getPlantilla(int $idEmpresa): array
    {
        $renderer  = new PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cheque');
        if ($plantilla) {
            return $plantilla;
        }
        return [
            'tipo_documento' => 'cheque',
            'configuracion'  => self::PLANTILLA_DEFAULT,
        ];
    }

    /** Datos de la empresa para el PDF (con logo del establecimiento y ciudad). */
    private function cargarEmpresa(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresa      = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos[0]['logo_ruta'])) {
            $empresa['logo_ruta'] = $establecimientos[0]['logo_ruta'];
        }
        // Nombre de la ciudad para el cheque (empresa.cod_ciudad → ciudad.nombre).
        $empresa['ciudad'] = $this->resolverCiudad($empresa);
        return $empresa;
    }

    /** Resuelve el nombre de la ciudad desde el catálogo por el código de la empresa. */
    private function resolverCiudad(array $empresa): string
    {
        $cod = trim((string)($empresa['cod_ciudad'] ?? ''));
        if ($cod === '') return '';
        try {
            $db = \App\core\Database::getConnection();
            $st = $db->prepare("SELECT nombre FROM ciudad WHERE codigo = :c LIMIT 1");
            $st->execute([':c' => $cod]);
            return (string) ($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
