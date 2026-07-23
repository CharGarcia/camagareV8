<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\Cache;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MesaRepository;
use App\Rules\modulos\ComandaRules;
use App\Services\LogSistemaService;
use Exception;
use PDO;
use Throwable;

/**
 * Lógica de negocio del módulo Comandas (POS Restaurantes).
 *
 * Fase 1: abrir comanda, agregar/anular líneas, anular comanda completa. El
 * cobro (que genera Factura/Recibo vía PosVentaService, con posible división
 * de cuenta) llega en una fase posterior; por ahora las líneas solo quedan en
 * estado 'pendiente' (el envío a cocina/barra es la Fase 2, KDS).
 */
class ComandaService
{
    private ComandaRepository $repository;
    private ComandaRules $rules;
    private MesaRepository $mesaRepo;
    private LogSistemaService $logService;
    private PDO $db;

    public function __construct(
        ComandaRepository $repository,
        ComandaRules $rules,
        MesaRepository $mesaRepo,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->mesaRepo   = $mesaRepo;
        $this->logService = $logService;
        $this->db         = Database::getConnection();
    }

    // ─── Tablero ──────────────────────────────────────────────────────────────

    public function getTablero(int $idEmpresa): array
    {
        return $this->repository->getTablero($idEmpresa);
    }

    // ─── Abrir comanda ────────────────────────────────────────────────────────

    public function abrir(array $data): int
    {
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];
        $idMesa    = (int) $data['id_mesa'];

        $mesa = $this->mesaRepo->findById($idMesa, $idEmpresa);
        $this->rules->validarAbrir($mesa);

        if ($this->repository->existeComandaAbierta($idMesa, $idEmpresa)) {
            throw new Exception('Ya hay una comanda abierta para esta mesa.');
        }

        $this->db->beginTransaction();
        try {
            // Bloqueo advisory por empresa: evita que dos meseros abriendo mesas
            // distintas al mismo tiempo obtengan el mismo numero_comanda.
            $this->repository->bloquearNumeracion($idEmpresa);
            $numero = $this->repository->getSiguienteNumero($idEmpresa);

            $cabecera = [
                'id_empresa'        => $idEmpresa,
                'id_mesa'           => $idMesa,
                'id_usuario_mesero' => $idUsuario,
                'id_caja_sesion'    => !empty($data['id_caja_sesion']) ? (int) $data['id_caja_sesion'] : null,
                'numero_comanda'    => $numero,
                'estado'            => 'abierta',
                'id_cliente'        => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
                'comensales'        => !empty($data['comensales']) ? (int) $data['comensales'] : null,
                'observaciones'     => trim((string) ($data['observaciones'] ?? '')) ?: null,
                'created_by'        => $idUsuario,
            ];
            $idComanda = $this->repository->crear($cabecera);

            $this->mesaRepo->actualizarEstado($idMesa, $idEmpresa, 'ocupada');

            $this->logService->registrar($idUsuario, $idEmpresa, 'ABRIR_COMANDA', 'comandas', $idComanda, null, $cabecera);

            $this->db->commit();
            return $idComanda;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ─── Lecturas ─────────────────────────────────────────────────────────────

    public function getDetalle(int $idComanda, int $idEmpresa): ?array
    {
        $c = $this->repository->find($idComanda, $idEmpresa);
        if (!$c) return null;
        $c['detalles'] = $this->repository->getLineas($idComanda, $idEmpresa);
        $c['total']    = $this->repository->getTotal($idComanda);
        return $c;
    }

    // ─── Líneas ───────────────────────────────────────────────────────────────

    public function agregarLinea(int $idComanda, int $idEmpresa, int $idUsuario, array $item): int
    {
        $this->rules->validarLinea($item);

        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $idProducto = (int) $item['id_producto'];
        $cantidad   = (float) $item['cantidad'];
        $precio     = (float) ($item['precio_unitario'] ?? 0);
        $descuento  = (float) ($item['descuento'] ?? 0);
        $subtotal   = round($precio * $cantidad - $descuento, 2);
        if ($subtotal < 0) $subtotal = 0.0;

        $idEstacion = $this->repository->getEstacionImpresionProducto($idProducto, $idEmpresa);

        $this->db->beginTransaction();
        try {
            $idLinea = $this->repository->insertLinea([
                'id_empresa'            => $idEmpresa,
                'id_comanda'            => $idComanda,
                'id_producto'           => $idProducto,
                'descripcion'           => trim((string) ($item['descripcion'] ?? '')),
                'cantidad'              => $cantidad,
                'precio_unitario'       => $precio,
                'descuento'             => $descuento,
                'subtotal'              => $subtotal,
                'observacion_item'      => trim((string) ($item['observacion_item'] ?? '')) ?: null,
                'id_estacion_impresion' => $idEstacion,
                'created_by'            => $idUsuario,
            ]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'AGREGAR_LINEA_COMANDA',
                'comanda_detalle',
                $idLinea,
                null,
                ['id_comanda' => $idComanda, 'id_producto' => $idProducto, 'cantidad' => $cantidad, 'subtotal' => $subtotal]
            );

            $this->db->commit();
            return $idLinea;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function anularLinea(int $idLinea, int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $this->db->beginTransaction();
        try {
            $this->repository->anularLinea($idLinea, $idComanda, $idEmpresa);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ANULAR_LINEA_COMANDA', 'comanda_detalle', $idLinea, null, ['anulado' => true]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ─── Cocina / barra (KDS) ─────────────────────────────────────────────────

    /**
     * Envía a cocina/barra las líneas 'pendiente' de la comanda (todas, o solo
     * las indicadas). No mueve inventario ni genera nada: solo cambia el
     * estado que lee el KDS.
     */
    public function enviarACocina(int $idComanda, int $idEmpresa, int $idUsuario, array $idsLineas = []): int
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $this->db->beginTransaction();
        try {
            $n = $this->repository->enviarLineasACocina($idComanda, $idEmpresa, $idsLineas);
            if ($n > 0) {
                $this->logService->registrar(
                    $idUsuario,
                    $idEmpresa,
                    'ENVIAR_COCINA_COMANDA',
                    'comandas',
                    $idComanda,
                    null,
                    ['lineas_enviadas' => $n]
                );
                $this->invalidarKds($idEmpresa);
            }
            $this->db->commit();
            return $n;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Avanza el estado de una línea (KDS: preparando/listo; mesero: entregado). Solo permite avanzar. */
    public function cambiarEstadoLinea(int $idLinea, int $idEmpresa, int $idUsuario, string $nuevoEstado): void
    {
        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if (!$linea) {
            throw new Exception('Ítem no encontrado.');
        }
        $this->rules->validarTransicionLinea((string) $linea['estado_linea'], $nuevoEstado);

        $this->db->beginTransaction();
        try {
            $this->repository->actualizarEstadoLinea($idLinea, $idEmpresa, $nuevoEstado);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'CAMBIAR_ESTADO_LINEA_COMANDA',
                'comanda_detalle',
                $idLinea,
                ['estado_linea' => $linea['estado_linea']],
                ['estado_linea' => $nuevoEstado]
            );
            $this->invalidarKds($idEmpresa);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Limpia la caché del KDS de ambos destinos (barato: la próxima consulta la reconstruye). */
    /** Limpia la caché del KDS de todas las estaciones de la empresa (barato: la próxima consulta la reconstruye). */
    private function invalidarKds(int $idEmpresa): void
    {
        $st = $this->db->prepare("SELECT id FROM estaciones_impresion WHERE id_empresa = :e AND eliminado = false");
        $st->execute([':e' => $idEmpresa]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idEstacion) {
            Cache::delete("kds:{$idEmpresa}:{$idEstacion}");
        }
    }

    // ─── Anular comanda completa ──────────────────────────────────────────────

    public function anular(int $idComanda, int $idEmpresa, int $idUsuario, string $motivo = ''): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        if (($comanda['estado'] ?? '') !== 'abierta') {
            throw new Exception('Solo se puede anular una comanda abierta.');
        }

        $this->db->beginTransaction();
        try {
            $this->repository->actualizarEstadoComanda($idComanda, $idEmpresa, 'anulada', $idUsuario, true);
            $this->repository->anularLineasDeComanda($idComanda, $idEmpresa);
            $this->mesaRepo->actualizarEstado((int) $comanda['id_mesa'], $idEmpresa, 'disponible');
            $this->invalidarKds($idEmpresa);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'ANULAR_COMANDA',
                'comandas',
                $idComanda,
                ['estado' => 'abierta'],
                ['estado' => 'anulada', 'motivo' => $motivo]
            );

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ─── Cabecera (cliente / comensales / observaciones) ──────────────────────

    public function actualizarCabecera(int $idComanda, int $idEmpresa, int $idUsuario, array $data): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $update = [
            'id_cliente'    => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            'comensales'    => !empty($data['comensales']) ? (int) $data['comensales'] : null,
            'observaciones' => trim((string) ($data['observaciones'] ?? '')) ?: null,
            'updated_by'    => $idUsuario,
        ];

        $this->db->beginTransaction();
        try {
            $this->repository->actualizarCabecera($idComanda, $idEmpresa, $update);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR_COMANDA', 'comandas', $idComanda, $comanda, $update);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
