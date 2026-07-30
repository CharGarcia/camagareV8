<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\ChequeRepository;
use App\Services\PlantillasPdfRendererService;
use App\Services\PlantillasPdfSeedService;
use App\Services\LogSistemaService;
use App\models\Empresa;

/**
 * Orquesta la impresión de cheques (pagos de egreso tipo CHEQUE):
 *  - Lista los cheques por imprimir y su estado.
 *  - Genera el PDF usando la plantilla 'cheque' del módulo Plantillas de
 *    Documentos (o la plantilla original del sistema si la empresa/banco aún
 *    no configuró una — ver `PlantillasPdfSeedService`).
 *  - Registra cada impresión en cheques_impresos (control anti-error) con
 *    transacción y auditoría.
 */
class ChequeImpresionService
{
    private ChequeRepository $repo;
    private LogSistemaService $log;

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

        $empresa = $this->cargarEmpresa($idEmpresa);

        // Cada cheque puede ser de un banco distinto: se resuelve (y cachea) la
        // plantilla activa por banco, y se marca en cada fila la clave de mapa
        // que le corresponde para que el renderer arme cada página con su propio
        // diseño (formato, orientación y campos).
        $plantillasPorBanco = [];
        foreach ($cheques as &$chq) {
            $idBanco = (int) ($chq['id_banco'] ?? 0) ?: null;
            $clave   = $idBanco !== null ? (string) $idBanco : '0';
            if (!array_key_exists($clave, $plantillasPorBanco)) {
                $plantillasPorBanco[$clave] = $this->getPlantilla($idEmpresa, $idBanco);
            }
            $chq['_banco_key'] = $clave;
        }
        unset($chq);
        if (!array_key_exists('0', $plantillasPorBanco)) {
            $plantillasPorBanco['0'] = $this->getPlantilla($idEmpresa, null);
        }

        // Generar el PDF en memoria ANTES de registrar (si falla el render, no
        // marcamos como impreso).
        $renderer = new PlantillasPdfRendererService();
        $pdf      = $renderer->generarCheques($plantillasPorBanco, $cheques, $empresa, 'S');
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

    /**
     * Resuelve o crea la plantilla de cheque para un banco específico, y la
     * activa (queda como vigente de ese banco). Usada por el botón "Configurar
     * impresión" de Egresos. Devuelve el id de la plantilla lista para abrir en
     * el diseñador visual (`/modulos/plantillas-pdf?action=disenador&id=`).
     */
    public function resolverConfiguracionImpresion(int $idEmpresa, int $idBanco, int $idUsuario, string $nombreBanco = ''): int
    {
        $nombre = 'Cheque' . ($nombreBanco !== '' ? (' - ' . $nombreBanco) : (' - Banco #' . $idBanco));
        return (new \App\Services\PlantillasPdfService())->obtenerOCrearParaBanco(
            $idEmpresa,
            'cheque',
            $idBanco,
            $nombre,
            PlantillasPdfSeedService::getSeed('cheque'),
            $idUsuario
        );
    }

    // ── Internos ────────────────────────────────────────────────────────────────

    /** Plantilla activa 'cheque' del banco indicado (o genérica de la empresa), o la original del sistema. */
    private function getPlantilla(int $idEmpresa, ?int $idBanco): array
    {
        $renderer  = new PlantillasPdfRendererService();
        $plantilla = $renderer->getPlantillaActiva($idEmpresa, 'cheque', $idBanco);
        if ($plantilla) {
            return $plantilla;
        }
        return [
            'tipo_documento' => 'cheque',
            'configuracion'  => PlantillasPdfSeedService::getSeed('cheque'),
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
