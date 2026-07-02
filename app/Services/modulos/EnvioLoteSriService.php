<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\EnvioLoteSriRepository;
use App\Services\Sri\SriEnvioService;
use App\Services\LogSistemaService;

/**
 * Orquesta el envío EN LOTE de comprobantes al SRI.
 *
 * - crearLote(): persiste el lote y sus ítems en una transacción.
 * - procesarLote(): worker que recorre los ítems pendientes y delega cada
 *   envío al SriEnvioService reutilizable (facturas, NC, retenciones, liq.).
 *
 * El procesamiento es secuencial (cada envío hace sleep() esperando la
 * autorización del SRI). Pensado para ejecutarse desde el worker CLI
 * scripts/procesar_lote_sri.php, en segundo plano.
 */
class EnvioLoteSriService
{
    private const TIPOS_VALIDOS = ['factura_venta', 'nota_credito', 'retencion_compra', 'liquidacion_compra'];

    private EnvioLoteSriRepository $repo;
    private LogSistemaService $log;

    public function __construct(?EnvioLoteSriRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new EnvioLoteSriRepository();
        $this->log  = $log  ?? new LogSistemaService();
    }

    // ── Creación del lote ────────────────────────────────────────────────────

    /**
     * Crea un lote con los comprobantes seleccionados.
     *
     * @param array<int,array{tipo:string,id:int,numero?:string,fecha_emision?:string}> $items
     * @param array<string,mixed> $filtros  Filtros usados (para trazabilidad).
     * @return int id del lote creado.
     * @throws \RuntimeException si no hay ítems válidos.
     */
    public function crearLote(int $idEmpresa, int $idUsuario, string $tipoAmbiente, array $items, array $filtros): int
    {
        // Normalizar y validar la selección
        $limpios = [];
        foreach ($items as $it) {
            $tipo = (string) ($it['tipo'] ?? '');
            $id   = (int) ($it['id'] ?? 0);
            if ($id <= 0 || !in_array($tipo, self::TIPOS_VALIDOS, true)) {
                continue;
            }
            $limpios[] = [
                'tipo'          => $tipo,
                'id'            => $id,
                'numero'        => isset($it['numero']) ? substr((string) $it['numero'], 0, 40) : null,
                'fecha_emision' => $it['fecha_emision'] ?? null,
            ];
        }

        if (empty($limpios)) {
            throw new \RuntimeException('No hay comprobantes válidos seleccionados para enviar.');
        }

        $db = Database::getConnection();
        $enTx = false;
        try {
            if (!$db->inTransaction()) { $db->beginTransaction(); $enTx = true; }

            $idLote = $this->repo->crearLote(
                $idEmpresa,
                $idUsuario,
                $tipoAmbiente,
                count($limpios),
                json_encode($filtros, JSON_UNESCAPED_UNICODE)
            );

            foreach ($limpios as $it) {
                $this->repo->insertarItem($idLote, $idEmpresa, $it['tipo'], $it['id'], $it['numero'], $it['fecha_emision']);
            }

            if ($enTx) { $db->commit(); }

            try {
                $this->log->registrar(
                    $idUsuario, $idEmpresa, 'crear_lote_sri', 'sri_lotes', $idLote,
                    null, ['total' => count($limpios), 'tipo_ambiente' => $tipoAmbiente]
                );
            } catch (\Throwable) {}

            return $idLote;
        } catch (\Throwable $e) {
            if ($enTx && $db->inTransaction()) { $db->rollBack(); }
            throw $e;
        }
    }

    // ── Procesamiento (worker) ───────────────────────────────────────────────

    /**
     * Procesa todos los ítems pendientes de un lote, secuencialmente.
     * Idempotente: si el lote no existe o ya finalizó, no hace nada.
     */
    public function procesarLote(int $idLote): void
    {
        $lote = $this->repo->getLoteCli($idLote);
        if (!$lote) {
            return;
        }
        if (in_array($lote['estado'], ['completado', 'completado_con_errores', 'cancelado'], true)) {
            return;
        }

        $idEmpresa = (int) $lote['id_empresa'];
        $idUsuario = (int) ($lote['created_by'] ?? 0);

        $this->repo->marcarLoteEstado($idLote, 'procesando', tocarInicio: true);

        $envioService = new SriEnvioService();

        while (true) {
            // Respetar cancelación entre ítems
            $estadoActual = $this->repo->getEstadoLote($idLote);
            if ($estadoActual && $estadoActual['estado'] === 'cancelado') {
                return;
            }

            $item = $this->repo->reclamarSiguienteItem($idLote);
            if ($item === null) {
                break; // No quedan pendientes
            }

            [$estadoItem, $exito, $mensaje, $numAut] = $this->despachar($envioService, $item, $idEmpresa, $idUsuario);

            $this->repo->marcarItem((int) $item['id'], $estadoItem, $mensaje, $numAut);
            $this->repo->incrementarContadores($idLote, $exito);
        }

        // Estado final del lote
        $final = $this->repo->getEstadoLote($idLote);
        if ($final && $final['estado'] === 'cancelado') {
            return;
        }
        $estadoFinal = ($final && (int) $final['fallidos'] > 0) ? 'completado_con_errores' : 'completado';
        $this->repo->marcarLoteEstado($idLote, $estadoFinal, tocarFin: true);

        try {
            $this->log->registrar(
                $idUsuario, $idEmpresa, 'procesar_lote_sri', 'sri_lotes', $idLote,
                null, $final
            );
        } catch (\Throwable) {}
    }

    /**
     * Envía un ítem al SRI usando el método correspondiente de SriEnvioService
     * y traduce el resultado a un estado de ítem.
     *
     * @return array{0:string,1:bool,2:?string,3:?string} [estadoItem, exito, mensaje, numeroAutorizacion]
     */
    private function despachar(SriEnvioService $svc, array $item, int $idEmpresa, int $idUsuario): array
    {
        $tipo = (string) $item['tipo_comprobante'];
        $id   = (int) $item['id_comprobante'];

        try {
            $res = match ($tipo) {
                'factura_venta'      => $svc->enviarFacturaVenta($id, $idEmpresa, $idUsuario),
                'nota_credito'       => $svc->enviarNotaCredito($id, $idEmpresa, $idUsuario),
                'retencion_compra'   => $svc->enviarRetencionCompra($id, $idEmpresa, $idUsuario),
                'liquidacion_compra' => $svc->enviarLiquidacionCompra($id, $idEmpresa, $idUsuario),
                default              => throw new \RuntimeException("Tipo de comprobante no soportado: {$tipo}"),
            };
        } catch (\Throwable $e) {
            return ['error', false, $e->getMessage(), null];
        }

        $estadoSri = (string) ($res['estado'] ?? '');
        $mensaje   = (string) ($res['mensaje'] ?? '');
        $numAut    = $res['numero_autorizacion'] ?? null;

        $estadoItem = match ($estadoSri) {
            'autorizado', 'autorizada'     => 'autorizado',
            'devuelta'                     => 'devuelto',
            'no_autorizado', 'no_autorizada' => 'no_autorizado',
            'en_procesamiento'             => 'error',
            default                        => 'error',
        };

        // Concatenar errores del SRI al mensaje cuando existan
        if (!empty($res['errores']) && is_array($res['errores'])) {
            $partes = [];
            foreach ($res['errores'] as $err) {
                if (is_array($err)) {
                    $partes[] = trim(($err['mensaje'] ?? '') . ' ' . ($err['informacionAdicional'] ?? ''));
                } else {
                    $partes[] = (string) $err;
                }
            }
            $errTxt = trim(implode(' | ', array_filter($partes)));
            if ($errTxt !== '') {
                $mensaje = $mensaje !== '' ? ($mensaje . ' — ' . $errTxt) : $errTxt;
            }
        }

        $exito = ($estadoItem === 'autorizado');
        return [$estadoItem, $exito, ($mensaje !== '' ? $mensaje : null), ($numAut ?: null)];
    }

    // ── Consulta de estado (para el polling del frontend) ────────────────────

    public function getEstado(int $idLote, int $idEmpresa): ?array
    {
        $lote = $this->repo->getLote($idLote, $idEmpresa);
        if (!$lote) {
            return null;
        }
        $lote['items'] = $this->repo->getItems($idLote, $idEmpresa);
        return $lote;
    }
}
