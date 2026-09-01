<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\Cache;
use App\models\Empresa;
use App\repositories\modulos\ClienteRepository;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\MenuRepository;
use App\repositories\modulos\MesaRepository;
use App\repositories\modulos\ProductoRepository;
use App\repositories\PayphoneRepository;
use App\Rules\modulos\ComandaRules;
use App\Services\LogSistemaService;
use Exception;
use PDO;
use Throwable;

/**
 * Lógica de negocio del módulo Comandas (POS Restaurantes).
 *
 * Abrir comanda, agregar/anular líneas, anular comanda completa, enviar a
 * cocina/barra (KDS) y cobrar (con posible división de cuenta por ítems).
 * El cobro NO reimplementa inventario/asiento/Ingreso: arma los ítems del
 * grupo y reutiliza PosVentaService::cobrar() tal cual usa el mostrador.
 */
class ComandaService
{
    /** Tabla con la que la comanda se registra en `registros_en_uso` (bloqueo de edición concurrente). */
    public const TABLA_BLOQUEO = 'comandas';

    private ComandaRepository $repository;
    private ComandaRules $rules;
    private MesaRepository $mesaRepo;
    private LogSistemaService $logService;
    private PosVentaService $ventaService;
    private MenuRepository $menuRepo;
    private ProductoRepository $productoRepo;
    private ClienteRepository $clienteRepo;
    private PDO $db;

    public function __construct(
        ComandaRepository $repository,
        ComandaRules $rules,
        MesaRepository $mesaRepo,
        LogSistemaService $logService,
        PosVentaService $ventaService,
        ?MenuRepository $menuRepo = null,
        ?ProductoRepository $productoRepo = null,
        ?ClienteRepository $clienteRepo = null
    ) {
        $this->repository   = $repository;
        $this->rules        = $rules;
        $this->mesaRepo     = $mesaRepo;
        $this->logService   = $logService;
        $this->ventaService = $ventaService;
        $this->menuRepo     = $menuRepo ?? new MenuRepository();
        $this->productoRepo = $productoRepo ?? new ProductoRepository();
        $this->clienteRepo  = $clienteRepo ?? new ClienteRepository();
        $this->db            = Database::getConnection();
    }

    // ─── Tablero ──────────────────────────────────────────────────────────────

    public function getTablero(int $idEmpresa): array
    {
        // El importe de cada mesa incluye el recargo por servicio, así que el
        // repositorio necesita saber cómo está configurado (ver getTablero). El
        // producto de propina viaja para poder dejar esa línea fuera de la base
        // del recargo, igual que hacen el pie de la comanda y PosVentaService.
        $servicio = $this->getConfigServicio($idEmpresa);
        return $this->repository->getTablero(
            $idEmpresa,
            $servicio['modo'],
            $servicio['porcentaje'],
            (int) ($servicio['id_producto_propina'] ?? 0)
        );
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

            // El recargo por servicio se congela al abrir la mesa: si mañana
            // cambian el porcentaje en la configuración, las cuentas que están
            // en el salón siguen con el que se les prometió al cliente.
            $servicio = $this->getConfigServicio($idEmpresa);

            // Turno de caja de la comanda: de él sale el punto de emisión con el
            // que se factura al cobrar. Lo manda quien abre la mesa —el salón
            // envía el turno del punto de emisión que eligió el mesero, ver
            // ComandasController::abrirAjax—; se valida aquí que siga abierto y
            // que sea de esta empresa, porque de ese id depende por dónde se
            // emite el documento.
            $idCajaSesion = (int) ($data['id_caja_sesion'] ?? 0);
            if ($idCajaSesion > 0 && !$this->ventaService->esSesionAbiertaDeEmpresa($idCajaSesion, $idEmpresa)) {
                throw new Exception('El turno de caja indicado ya no está abierto. Vuelve a Cajas y elige tu punto de emisión.');
            }
            // Sin turno indicado: solo llega así el portal público QR, donde el
            // cliente que escanea no elige punto de emisión. Ahí sí se toma el
            // turno abierto de la empresa (el más reciente, ver
            // CajaSesionRepository::getAbiertaPorEmpresa).
            if ($idCajaSesion <= 0) {
                $idCajaSesion = (int) ($this->ventaService->getSesionAbiertaEmpresa($idEmpresa)['id'] ?? 0);
            }
            // Y si NO hay ningún turno abierto, no se abre la mesa. La pantalla
            // del salón se sirve sin caché justamente para no llegar aquí, pero
            // el guard real vive en el servidor: una pestaña vieja, el botón
            // "atrás" del navegador o un POST directo llegaban igual y creaban la
            // comanda con id_caja_sesion = null. El problema aparecía recién al
            // cobrar, con la mesa ya servida.
            if ($idCajaSesion <= 0) {
                throw new Exception('No hay un turno de caja abierto. Abre la caja en Punto de Venta antes de abrir mesas.');
            }

            $cabecera = [
                'id_empresa'          => $idEmpresa,
                'id_mesa'             => $idMesa,
                'id_usuario_mesero'   => $idUsuario,
                'id_caja_sesion'      => $idCajaSesion ?: null,
                'numero_comanda'      => $numero,
                'estado'              => 'abierta',
                'id_cliente'          => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
                'comensales'          => !empty($data['comensales']) ? (int) $data['comensales'] : null,
                'observaciones'       => trim((string) ($data['observaciones'] ?? '')) ?: null,
                'created_by'          => $idUsuario,
                'aplica_servicio'     => $servicio['modo'] !== 'no',
                'porcentaje_servicio' => $servicio['modo'] !== 'no' ? $servicio['porcentaje'] : 0.0,
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

    /**
     * Resuelve la comanda de una mesa para el portal público QR: si ya hay
     * una abierta, la reutiliza (varios comensales piden desde su propio
     * celular a la misma mesa); si no, la abre en self-service, atribuida al
     * cajero del turno abierto de la empresa — hasta que un mesero entre a
     * la comanda desde el tablero, que es quien queda como "mesero" real.
     */
    public function resolverComandaQr(int $idMesa, int $idEmpresa, array $sesionCaja): int
    {
        $abierta = $this->repository->getAbiertaPorMesa($idMesa, $idEmpresa);
        if ($abierta) {
            return (int) $abierta['id'];
        }
        return $this->abrir([
            'id_empresa'     => $idEmpresa,
            'id_usuario'     => (int) $sesionCaja['id_usuario'],
            'id_mesa'        => $idMesa,
            'id_caja_sesion' => (int) $sesionCaja['id'],
        ]);
    }

    // ─── Lecturas ─────────────────────────────────────────────────────────────

    public function getDetalle(int $idComanda, int $idEmpresa): ?array
    {
        $c = $this->repository->find($idComanda, $idEmpresa);
        if (!$c) return null;
        $c['detalles'] = $this->repository->getLineas($idComanda, $idEmpresa);
        $c['total']    = $this->repository->getTotal($idComanda);
        $c['grupos']   = $this->getGrupos($idComanda, $idEmpresa);
        // Porcentaje de recargo que se le va a cobrar de verdad (0 = ninguno),
        // ya resueltas las reglas del establecimiento. La pantalla usa este
        // número y no el crudo de la comanda, para no prometer algo distinto
        // de lo que hará el cobro.
        $c['servicio_efectivo'] = $this->porcentajeServicioComanda($c);
        return $c;
    }

    // ─── Líneas ───────────────────────────────────────────────────────────────

    /**
     * $item trae id_producto (catálogo normal) o id_menu_item (carta del
     * restaurante, con o sin producto vinculado — ver ComandaRules::validarLinea).
     * Cuando es un ítem del menú, descripción/precio/estación se resuelven
     * siempre desde su registro (no se confía en lo que mande el cliente),
     * igual que id_producto si el ítem tiene uno vinculado (queda facturable).
     *
     * $empresaConfig es la config de Facturación del establecimiento (mismo
     * shape que arma ComandasController::getEmpresaConfig()) — se usa para
     * exigir lote/caducidad/NUP aquí mismo si la empresa los requiere, en vez
     * de descubrirlo recién al cobrar (donde ya no hay forma de corregirlo).
     */
    public function agregarLinea(int $idComanda, int $idEmpresa, int $idUsuario, array $item, array $empresaConfig = []): int
    {
        $this->rules->validarLinea($item);

        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $idMenuItem  = (int) ($item['id_menu_item'] ?? 0);
        $idProducto  = (int) ($item['id_producto'] ?? 0);
        $descripcion = trim((string) ($item['descripcion'] ?? ''));
        $precio      = (float) ($item['precio_unitario'] ?? 0);
        $idEstacion  = null;

        if ($idMenuItem > 0) {
            $menuItem = $this->menuRepo->getDisponibleById($idMenuItem, $idEmpresa);
            if (!$menuItem) {
                throw new Exception('El ítem del menú no existe o ya no está disponible.');
            }
            $idProducto  = (int) ($menuItem['id_producto'] ?? 0);
            $descripcion = (string) $menuItem['nombre'];
            $precio      = (float) $menuItem['precio'];
            $idEstacion  = $menuItem['id_estacion_impresion'] !== null ? (int) $menuItem['id_estacion_impresion'] : null;
        } elseif ($idProducto > 0) {
            $idEstacion = $this->repository->getEstacionImpresionProducto($idProducto, $idEmpresa);
        }

        // Lote/caducidad/NUP: solo aplica a productos inventariables reales
        // (no compuestos/kits) — mismo criterio que ya usa el POS mostrador.
        $esInventariableControlado = $idProducto > 0 && $this->productoRepo->isInventariable($idProducto, $idEmpresa);
        $this->rules->validarLoteCaducidadNup($esInventariableControlado, $empresaConfig, $item);

        $cantidad  = (float) $item['cantidad'];
        $descuento = (float) ($item['descuento'] ?? 0);
        $subtotal  = round($precio * $cantidad - $descuento, 2);
        if ($subtotal < 0) $subtotal = 0.0;

        // Toda línea nace 'pendiente', tenga estación o no: hasta confirmarla, el
        // cliente del QR debe poder quitarla y el botón "Confirmar pedido" tiene
        // que contarla. Lo que cambia es a dónde va al confirmar: las que tienen
        // estación pasan a 'enviado' (cocina/barra) y las que no, directo a
        // 'entregado' — ver ComandaRepository::enviarLineasACocina.
        $this->db->beginTransaction();
        try {
            $idLinea = $this->repository->insertLinea([
                'id_empresa'            => $idEmpresa,
                'id_comanda'            => $idComanda,
                'id_producto'           => $idProducto ?: null,
                'id_menu_item'          => $idMenuItem ?: null,
                'descripcion'           => $descripcion,
                'cantidad'              => $cantidad,
                'precio_unitario'       => $precio,
                'descuento'             => $descuento,
                'subtotal'              => $subtotal,
                'observacion_item'      => trim((string) ($item['observacion_item'] ?? '')) ?: null,
                'id_estacion_impresion' => $idEstacion,
                'lote'                  => trim((string) ($item['lote'] ?? '')) ?: null,
                'caducidad'             => trim((string) ($item['caducidad'] ?? '')) ?: null,
                'nup'                   => trim((string) ($item['nup'] ?? '')) ?: null,
                'created_by'            => $idUsuario,
            ]);

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                'AGREGAR_LINEA_COMANDA',
                'comanda_detalle',
                $idLinea,
                null,
                ['id_comanda' => $idComanda, 'id_producto' => $idProducto ?: null, 'id_menu_item' => $idMenuItem ?: null, 'cantidad' => $cantidad, 'subtotal' => $subtotal]
            );

            $this->db->commit();
            return $idLinea;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Se puede anular en cualquier estado (pendiente/enviado/preparando/
     * listo/entregado) — un plato se puede caer, el cliente puede arrepentirse,
     * etc. en cualquier momento del servicio. Lo único que lo bloquea es que
     * ya esté asignada a un grupo de cobro (ahí ya se congeló para facturar).
     */
    public function anularLinea(int $idLinea, int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea && (int) $linea['id_comanda'] !== $idComanda) {
            $linea = null;
        }
        $this->rules->validarPuedeEditarLinea($linea);

        $this->db->beginTransaction();
        try {
            $this->repository->anularLinea($idLinea, $idComanda, $idEmpresa);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ANULAR_LINEA_COMANDA', 'comanda_detalle', $idLinea, ['estado_linea' => $linea['estado_linea']], ['anulado' => true]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Deshace un "Eliminar ítem": vuelve a 'pendiente' (se puede volver a enviar a preparación si hace falta). */
    public function restaurarLinea(int $idLinea, int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea && (int) $linea['id_comanda'] !== $idComanda) {
            $linea = null;
        }
        $this->rules->validarPuedeRestaurarLinea($linea);

        $this->db->beginTransaction();
        try {
            $this->repository->restaurarLinea($idLinea, $idComanda, $idEmpresa);
            $this->logService->registrar($idUsuario, $idEmpresa, 'RESTAURAR_LINEA_COMANDA', 'comanda_detalle', $idLinea, ['estado_linea' => 'anulado'], ['estado_linea' => 'pendiente']);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Propina voluntaria: la que el cliente deja por su cuenta, además del
     * recargo por servicio.
     *
     * Va como una LÍNEA MÁS de la comanda, no como un campo aparte: el campo
     * <propina> del comprobante es uno solo, ya lo ocupa el recargo por servicio
     * y está topado al 10% del subtotal, así que no hay dónde poner una segunda.
     * Siendo una línea, el resto del recorrido (totales, división de la cuenta,
     * cobro, ticket) la trata como a cualquier ítem sin tocar nada más.
     *
     * Es única por comanda: con un monto nuevo se actualiza la que ya existe, y
     * con 0 se elimina. El producto con el que se emite lo configura el
     * establecimiento (Empresa → Facturación); sin él, la función no existe.
     *
     * Devuelve ['monto' => float, 'id_linea' => ?int]: el id lo necesita el
     * portal QR para meter la propina en la misma cuenta que el cliente va a
     * pagar.
     */
    public function guardarPropina(int $idComanda, int $idEmpresa, int $idUsuario, float $monto, array $empresaConfig): array
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $idProducto = (int) ($empresaConfig['id_producto_propina'] ?? 0);
        if ($idProducto <= 0) {
            throw new Exception('No hay un producto configurado para la propina. Configúralo en Empresa → Facturación → Propina voluntaria.');
        }
        if ($monto < 0) {
            throw new Exception('La propina no puede ser negativa.');
        }
        $monto = round($monto, 2);

        // Solo la propina libre: getLineaPropina() ya deja fuera la que está en
        // una cuenta. Si la comanda se dividió y la anterior ya viajó en un
        // grupo, aquí se crea una nueva para la cuenta que se esté armando.
        $linea = $this->repository->getLineaPropina($idComanda, $idEmpresa, $idProducto);

        $idLineaPropina = $linea ? (int) $linea['id'] : null;

        $this->db->beginTransaction();
        try {
            if ($monto <= 0) {
                if ($linea) {
                    $this->repository->eliminarLineaPropina((int) $linea['id'], $idEmpresa);
                    $this->logService->registrar(
                        $idUsuario, $idEmpresa, 'QUITAR_PROPINA_COMANDA', 'comanda_detalle', (int) $linea['id'],
                        ['subtotal' => $linea['subtotal']], null
                    );
                    $idLineaPropina = null;
                }
            } elseif ($linea) {
                $this->repository->actualizarMontoPropina((int) $linea['id'], $idEmpresa, $monto);
                $this->logService->registrar(
                    $idUsuario, $idEmpresa, 'PROPINA_COMANDA', 'comanda_detalle', (int) $linea['id'],
                    ['subtotal' => $linea['subtotal']], ['subtotal' => $monto]
                );
            } else {
                $idLinea = $this->repository->insertLinea([
                    'id_empresa'            => $idEmpresa,
                    'id_comanda'            => $idComanda,
                    'id_producto'           => $idProducto,
                    'id_menu_item'          => null,
                    'descripcion'           => $this->productoRepo->getNombre($idProducto) ?: 'PROPINA',
                    'cantidad'              => 1,
                    'precio_unitario'       => $monto,
                    'descuento'             => 0,
                    'subtotal'              => $monto,
                    'observacion_item'      => null,
                    // Nunca va a cocina/barra, aunque la categoría del producto
                    // tenga estación: no hay nada que preparar.
                    'id_estacion_impresion' => null,
                    'lote'                  => null,
                    'caducidad'             => null,
                    'nup'                   => null,
                    'estado_linea'          => 'entregado',
                    'created_by'            => $idUsuario,
                ]);
                $this->logService->registrar(
                    $idUsuario, $idEmpresa, 'PROPINA_COMANDA', 'comanda_detalle', $idLinea,
                    null, ['id_comanda' => $idComanda, 'subtotal' => $monto]
                );
                $idLineaPropina = $idLinea;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        return ['monto' => $monto, 'id_linea' => $idLineaPropina];
    }

    /**
     * Descuento por línea (Porcentaje o Valor, ya resuelto a $ por el cliente
     * — mismo patrón que el descuento de línea del POS mostrador). Solo
     * mientras la línea no esté anulada ni ya asignada a un grupo de cobro.
     */
    public function actualizarDescuentoLinea(int $idLinea, int $idComanda, int $idEmpresa, int $idUsuario, float $descuento): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $linea = $this->repository->getLinea($idLinea, $idEmpresa);
        if ($linea && (int) $linea['id_comanda'] !== $idComanda) {
            $linea = null;
        }
        $this->rules->validarPuedeEditarLinea($linea);

        if ($descuento < 0) {
            throw new Exception('El descuento no puede ser negativo.');
        }
        $base = (float) $linea['precio_unitario'] * (float) $linea['cantidad'];
        if ($descuento > $base) {
            $descuento = $base;
        }
        $descuento = round($descuento, 2);
        $subtotal = round($base - $descuento, 2);
        if ($subtotal < 0) $subtotal = 0.0;

        $this->db->beginTransaction();
        try {
            $this->repository->actualizarDescuentoLinea($idLinea, $idEmpresa, $descuento, $subtotal);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'DESCUENTO_LINEA_COMANDA', 'comanda_detalle', $idLinea,
                ['descuento' => $linea['descuento']], ['descuento' => $descuento, 'subtotal' => $subtotal]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** El mesero abrió la comanda: ya vio lo que pidió el cliente desde el QR, se apaga el aviso del tablero. */
    public function marcarPedidoQrVisto(int $idComanda, int $idEmpresa): void
    {
        $this->repository->marcarPedidoQr($idComanda, $idEmpresa, false);
    }

    // ─── Cocina / barra (KDS) ─────────────────────────────────────────────────

    /**
     * Envía a cocina/barra las líneas 'pendiente' de la comanda (todas, o solo
     * las indicadas). No mueve inventario ni genera nada: solo cambia el
     * estado que lee el KDS.
     */
    public function enviarACocina(int $idComanda, int $idEmpresa, int $idUsuario, array $idsLineas = [], bool $desdeQr = false): int
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $this->db->beginTransaction();
        try {
            $n = $this->repository->enviarLineasACocina($idComanda, $idEmpresa, $idsLineas);
            // Confirmado desde el celular del cliente: se enciende el aviso del
            // tablero para que alguien del salón se entere. Los ítems se van
            // solos a cocina (o quedan entregados si no pasan por estación), así
            // que sin esto la mesa pediría sin que nadie lo note.
            if ($desdeQr && $n > 0) {
                $this->repository->marcarPedidoQr($idComanda, $idEmpresa, true);
            }
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
        $this->rules->validarMotivoAnulacion($this->repository->contarLineas($idComanda, $idEmpresa), $motivo);

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

    // ─── Recargo por servicio (el "10%") ───────────────────────────────────────

    /**
     * Configuración del recargo por servicio del establecimiento
     * ('no' | 'obligatorio' | 'opcional' + porcentaje). La resuelve
     * PosVentaService, que es el motor de cobro compartido por el salón y el
     * mostrador — así ambos puntos de venta obedecen la misma configuración.
     */
    public function getConfigServicio(int $idEmpresa): array
    {
        return $this->ventaService->getConfigServicio($idEmpresa);
    }

    /**
     * Enciende o apaga el recargo por servicio de una comanda. Solo tiene
     * sentido cuando el establecimiento lo tiene en 'opcional': es el caso del
     * cliente que no quiere pagar el 10%.
     */
    public function cambiarServicio(int $idComanda, int $idEmpresa, int $idUsuario, bool $aplica): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $servicio = $this->getConfigServicio($idEmpresa);
        $this->rules->validarServicio($servicio['modo'], (float) ($comanda['porcentaje_servicio'] ?? 0));

        // Si la comanda se abrió antes de configurar el servicio, no tiene
        // porcentaje guardado: se le aplica el vigente al encenderlo.
        $porcentaje = (float) ($comanda['porcentaje_servicio'] ?? 0);
        if ($aplica && $porcentaje <= 0) {
            $porcentaje = $servicio['porcentaje'];
        }

        $update = [
            // El bool de PHP llega a Postgres como cadena vacía por PDO; se manda el literal.
            'aplica_servicio'     => $aplica ? 'true' : 'false',
            'porcentaje_servicio' => $porcentaje,
            'updated_by'          => $idUsuario,
        ];

        $this->db->beginTransaction();
        try {
            $this->repository->actualizarCabecera($idComanda, $idEmpresa, $update);
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ACTUALIZAR_COMANDA', 'comandas', $idComanda,
                ['aplica_servicio' => $comanda['aplica_servicio'] ?? null],
                ['aplica_servicio' => $aplica, 'porcentaje_servicio' => $porcentaje]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Porcentaje de recargo que debe cobrarse a esta comanda. El monto lo
     * calcula PosVentaService sobre el subtotal real del documento, así que al
     * dividir la cuenta cada parte carga el suyo sin hacer cuentas aparte.
     */
    public function porcentajeServicioComanda(array $comanda): float
    {
        $cfg = $this->getConfigServicio((int) $comanda['id_empresa']);

        // Apagado (o sin campo de propina en la factura): no se cobra ni en las
        // cuentas ya abiertas. Es lo mismo que ve el mesero en pantalla, y
        // cobrar algo invisible sería peor que perder el recargo de una mesa.
        if ($cfg['modo'] === 'no') {
            return 0.0;
        }

        // Obligatorio manda sobre el estado de la comanda: lo llevan también las
        // que se abrieron antes de configurarlo, y las que alguien dejó sin
        // recargo cuando el establecimiento todavía estaba en "opcional".
        // El 'f' de Postgres es truthy en PHP: hay que compararlo a mano.
        $aplica = !(empty($comanda['aplica_servicio']) || $comanda['aplica_servicio'] === 'f');
        if ($cfg['modo'] === 'obligatorio') {
            $aplica = true;
        }
        if (!$aplica) {
            return 0.0;
        }

        // El porcentaje de la comanda es un snapshot del momento en que se
        // abrió; si no tiene (se abrió antes de que existiera el recargo), se
        // usa el vigente.
        $pct = (float) ($comanda['porcentaje_servicio'] ?? 0);
        return $pct > 0 ? $pct : $cfg['porcentaje'];
    }

    // ─── Cobro / división de cuenta ────────────────────────────────────────────

    /** Grupos de cobro de la comanda, cada uno con sus líneas asignadas. */
    public function getGrupos(int $idComanda, int $idEmpresa): array
    {
        $grupos = $this->repository->getGruposDeComanda($idComanda, $idEmpresa);
        foreach ($grupos as &$g) {
            if (($g['tipo_split'] ?? 'items') === 'partes_iguales') {
                $idGrupoRaiz = (int) ($g['id_grupo_padre'] ?? $g['id']);
                $numPartes = max(1, (int) ($g['total_partes'] ?? 1));
                $pool = $this->repository->getLineasDelPoolPartes($idGrupoRaiz, $idEmpresa);
                // Solo para mostrar el monto de ESTA parte (1/N del pool compartido) — el
                // cobro real recalcula lo mismo en cobrarGrupo(), esto no lo reemplaza.
                foreach ($pool as &$l) {
                    $l['subtotal'] = round(((float) $l['subtotal']) / $numPartes, 2);
                }
                unset($l);
                $g['lineas'] = $pool;
            } else {
                $g['lineas'] = $this->repository->getLineasDelGrupo((int) $g['id'], $idEmpresa);
            }
        }
        unset($g);
        return $grupos;
    }

    public function getLineasSinGrupo(int $idComanda, int $idEmpresa): array
    {
        return $this->repository->getLineasSinGrupo($idComanda, $idEmpresa);
    }

    /**
     * Crea un grupo de cobro con las líneas indicadas (split "por ítems"). Si
     * $idsLineas viene vacío, toma TODAS las líneas activas sin grupo que YA
     * se puedan cobrar — así "cobrar todo junto" no se traba si algo sigue en
     * preparación, simplemente lo deja fuera (queda disponible para cuando
     * esté listo). Si se piden ids explícitos, un ítem en 'preparando' sí
     * se rechaza con un mensaje claro (fue un intento deliberado de incluirlo).
     */
    public function crearGrupoCobro(int $idComanda, int $idEmpresa, int $idUsuario, array $idsLineas = [], string $etiqueta = ''): int
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $disponibles = $this->repository->getLineasSinGrupo($idComanda, $idEmpresa);
        if (empty($idsLineas)) {
            $lineas = array_values(array_filter($disponibles, fn($l) => ($l['estado_linea'] ?? '') !== 'preparando'));
        } else {
            $idsPedidos = array_map('intval', $idsLineas);
            $idsDisponibles = array_map(fn($l) => (int) $l['id'], $disponibles);
            foreach ($idsPedidos as $id) {
                if (!in_array($id, $idsDisponibles, true)) {
                    throw new Exception('Uno de los ítems ya está en otro grupo de cobro o no existe.');
                }
            }
            $lineas = array_values(array_filter($disponibles, fn($l) => in_array((int) $l['id'], $idsPedidos, true)));
            foreach ($lineas as $l) {
                $this->rules->validarLineaCobrable($l);
            }
        }
        $this->rules->validarLineasParaGrupo($lineas);

        $this->db->beginTransaction();
        try {
            $numero = $this->repository->getSiguienteNumeroGrupo($idComanda);
            $idGrupo = $this->repository->crearGrupoCobro([
                'id_empresa'   => $idEmpresa,
                'id_comanda'   => $idComanda,
                'numero_grupo' => $numero,
                'etiqueta'     => trim($etiqueta) !== '' ? trim($etiqueta) : ('Cuenta ' . $numero),
                'tipo_split'   => 'items',
                'created_by'   => $idUsuario,
            ]);
            $ids = array_map(fn($l) => (int) $l['id'], $lineas);
            $this->repository->asignarLineasAGrupo($ids, $idGrupo, $idComanda, $idEmpresa);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR_GRUPO_COBRO', 'comanda_grupos_cobro', $idGrupo,
                null, ['id_comanda' => $idComanda, 'lineas' => $ids]
            );

            $this->db->commit();
            return $idGrupo;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Divide la cuenta en N partes iguales: cada línea del "pool" se reparte
     * proporcionalmente entre las N partes (no una línea completa por parte,
     * como en crearGrupoCobro) — al cobrar cada parte se genera su propio
     * documento con el detalle real de productos a 1/N de cantidad, ver
     * cobrarGrupo(). Retorna los ids de los N grupos creados.
     */
    public function crearGruposPartesIguales(int $idComanda, int $idEmpresa, int $idUsuario, array $idsLineas, int $numPartes): array
    {
        if ($numPartes < 2) {
            throw new Exception('Debes dividir entre al menos 2 partes.');
        }
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $disponibles = $this->repository->getLineasSinGrupo($idComanda, $idEmpresa);
        $idsPedidos = array_map('intval', $idsLineas);
        $idsDisponibles = array_map(fn($l) => (int) $l['id'], $disponibles);
        foreach ($idsPedidos as $id) {
            if (!in_array($id, $idsDisponibles, true)) {
                throw new Exception('Uno de los ítems ya está en otro grupo de cobro o no existe.');
            }
        }
        $lineas = array_values(array_filter($disponibles, fn($l) => in_array((int) $l['id'], $idsPedidos, true)));
        foreach ($lineas as $l) {
            $this->rules->validarLineaCobrable($l);
        }
        $this->rules->validarLineasParaGrupo($lineas);

        $totalPool = array_sum(array_map(fn($l) => (float) $l['subtotal'], $lineas));
        $montoParte = round($totalPool / $numPartes, 2);

        $this->db->beginTransaction();
        try {
            $ids = array_map(fn($l) => (int) $l['id'], $lineas);
            $numeroBase = $this->repository->getSiguienteNumeroGrupo($idComanda);
            $idsGrupos = [];
            $idGrupoRaiz = null;

            for ($parte = 1; $parte <= $numPartes; $parte++) {
                $idGrupo = $this->repository->crearGrupoCobro([
                    'id_empresa'     => $idEmpresa,
                    'id_comanda'     => $idComanda,
                    'numero_grupo'   => $numeroBase + $parte - 1,
                    'etiqueta'       => "Parte {$parte} de {$numPartes}",
                    'tipo_split'     => 'partes_iguales',
                    'created_by'     => $idUsuario,
                    'id_grupo_padre' => $idGrupoRaiz,
                    'numero_parte'   => $parte,
                    'total_partes'   => $numPartes,
                ]);
                if ($idGrupoRaiz === null) {
                    $idGrupoRaiz = $idGrupo;
                }
                $idsGrupos[] = $idGrupo;
            }

            $this->repository->agregarLineasAPoolPartes($idGrupoRaiz, $ids);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR_GRUPOS_PARTES_IGUALES', 'comanda_grupos_cobro', $idGrupoRaiz,
                null, ['id_comanda' => $idComanda, 'lineas' => $ids, 'num_partes' => $numPartes, 'monto_parte' => $montoParte, 'grupos' => $idsGrupos]
            );

            $this->db->commit();
            return $idsGrupos;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Valida los datos (nombre/identificación/correo, cédula-RUC ecuatoriana)
     * y busca al cliente por identificación (incluye eliminados: se reactiva
     * y reutiliza en vez de duplicar) o lo crea — mismo criterio que ya usa
     * Factura Express QR.
     */
    public function resolverClienteQr(array $datos, int $idEmpresa, int $idUsuario): int
    {
        $this->rules->validarDatosClienteQr($datos);

        $identificacion = trim((string) $datos['identificacion']);
        $existente = $this->clienteRepo->findByIdentificacion($idEmpresa, $identificacion);
        if ($existente) {
            $this->clienteRepo->reactivarYActualizar((int) $existente['id'], [
                'nombre'     => trim((string) $datos['nombre']),
                'email'      => trim((string) ($datos['correo'] ?? '')),
                'telefono'   => trim((string) ($datos['telefono'] ?? '')),
                'id_usuario' => $idUsuario,
            ]);
            return (int) $existente['id'];
        }

        $tipoIdMap = ['cedula' => '05', 'ruc' => '04', 'pasaporte' => '06', 'sin_ruc' => '07'];
        $tipoId = $tipoIdMap[$datos['tipo_identificacion'] ?? 'cedula'] ?? '05';

        return $this->clienteRepo->create([
            'id_empresa'     => $idEmpresa,
            'id_usuario'     => $idUsuario,
            'nombre'         => trim((string) $datos['nombre']),
            'tipo_id'        => $tipoId,
            'identificacion' => $identificacion,
            'telefono'       => trim((string) ($datos['telefono'] ?? '')) ?: null,
            'email'          => trim((string) ($datos['correo'] ?? '')) ?: null,
            'direccion'      => trim((string) ($datos['direccion'] ?? '')) ?: null,
            'provincia'      => null,
            'ciudad'         => null,
            'id_vendedor'    => null,
            'plazo'          => 0,
            'status'         => 1,
        ]);
    }

    /**
     * "Consumidor Final" desde el portal QR: sin datos que pedir ni validar
     * (a diferencia de resolverClienteQr) — reutiliza el cliente canónico
     * (tipo_id='07', identificación '9999999999999') que ya existe en toda
     * empresa desde su alta (EmpresaInicializadorService), mismo criterio que
     * PosVentaService::getClienteConsumidorFinal() en el mostrador. Si por
     * algún motivo no existiera, lo crea (para no dejar al cliente sin poder
     * pedir la cuenta).
     */
    public function resolverClienteConsumidorFinal(int $idEmpresa, int $idUsuario): int
    {
        $existente = $this->clienteRepo->findByIdentificacion($idEmpresa, '9999999999999');
        if ($existente && (string) ($existente['tipo_id'] ?? '') === '07') {
            $this->clienteRepo->reactivarYActualizar((int) $existente['id'], ['nombre' => 'CONSUMIDOR FINAL', 'id_usuario' => $idUsuario]);
            return (int) $existente['id'];
        }

        return $this->clienteRepo->create([
            'id_empresa'     => $idEmpresa,
            'id_usuario'     => $idUsuario,
            'nombre'         => 'CONSUMIDOR FINAL',
            'tipo_id'        => '07',
            'identificacion' => '9999999999999',
            'telefono'       => null,
            'email'          => null,
            'direccion'      => null,
            'provincia'      => null,
            'ciudad'         => null,
            'id_vendedor'    => null,
            'plazo'          => 0,
            'status'         => 1,
        ]);
    }

    /**
     * "Pedir la cuenta" desde el portal QR: solo ítems YA ENTREGADOS (más
     * estricto que el split del mesero), con el cliente y el tipo de
     * documento ya resueltos — el mesero solo confirma la forma de pago
     * física para cerrarlo (ver comandas/ver.php).
     */
    public function crearGrupoCobroQr(int $idComanda, int $idEmpresa, int $idUsuario, array $idsLineas, int $idCliente, string $tipoDocumentoSolicitado, int $idFormaPagoSugerida = 0): int
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        $this->rules->validarPuedeModificar($comanda);

        $disponibles = $this->repository->getLineasSinGrupo($idComanda, $idEmpresa);
        $idsPedidos = array_map('intval', $idsLineas);
        $idsDisponibles = array_map(fn($l) => (int) $l['id'], $disponibles);
        foreach ($idsPedidos as $id) {
            if (!in_array($id, $idsDisponibles, true)) {
                throw new Exception('Uno de los ítems ya está en otro grupo de cobro o no existe.');
            }
        }
        $lineas = array_values(array_filter($disponibles, fn($l) => in_array((int) $l['id'], $idsPedidos, true)));
        foreach ($lineas as $l) {
            $this->rules->validarLineaCobrableQr($l);
        }
        $this->rules->validarLineasParaGrupo($lineas);

        $tipoDocumentoSolicitado = strtoupper($tipoDocumentoSolicitado) === 'FACTURA' ? 'FACTURA' : 'RECIBO';

        $this->db->beginTransaction();
        try {
            $numero = $this->repository->getSiguienteNumeroGrupo($idComanda);
            $idGrupo = $this->repository->crearGrupoCobro([
                'id_empresa'                => $idEmpresa,
                'id_comanda'                => $idComanda,
                'numero_grupo'              => $numero,
                'etiqueta'                  => 'Cuenta ' . $numero,
                'tipo_split'                => 'items',
                'created_by'                => $idUsuario,
                'id_cliente'                => $idCliente,
                'tipo_documento_solicitado' => $tipoDocumentoSolicitado,
                'origen'                    => 'qr',
                // Cómo dijo el cliente que piensa pagar. Es informativa: el
                // mesero la ve precargada al cobrar y puede cambiarla.
                'id_forma_pago_sugerida'    => $idFormaPagoSugerida ?: null,
            ]);
            $ids = array_map(fn($l) => (int) $l['id'], $lineas);
            $this->repository->asignarLineasAGrupo($ids, $idGrupo, $idComanda, $idEmpresa);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'CREAR_GRUPO_COBRO_QR', 'comanda_grupos_cobro', $idGrupo,
                null, ['id_comanda' => $idComanda, 'lineas' => $ids, 'id_cliente' => $idCliente, 'tipo_documento_solicitado' => $tipoDocumentoSolicitado]
            );

            $this->db->commit();
            return $idGrupo;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ─── "Llamar al mesero" (portal QR) ────────────────────────────────────────

    public function solicitarAsistencia(int $idComanda, int $idEmpresa): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        $this->repository->solicitarAsistencia($idComanda, $idEmpresa);
        $this->logService->registrar(
            (int) $comanda['id_usuario_mesero'], $idEmpresa, 'SOLICITAR_ASISTENCIA', 'comandas', $idComanda, null, ['solicita_asistencia' => true]
        );
    }

    public function atenderAsistencia(int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        $this->repository->atenderAsistencia($idComanda, $idEmpresa);
        $this->logService->registrar($idUsuario, $idEmpresa, 'ATENDER_ASISTENCIA', 'comandas', $idComanda, null, ['solicita_asistencia' => false]);
    }

    /** El propio cliente cancela su aviso desde el portal QR (p. ej. presionó la campana por error) — distinto de que el mesero lo atienda. */
    public function cancelarAsistencia(int $idComanda, int $idEmpresa): void
    {
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }
        $this->repository->atenderAsistencia($idComanda, $idEmpresa);
        $this->logService->registrar(
            (int) $comanda['id_usuario_mesero'], $idEmpresa, 'CANCELAR_ASISTENCIA_QR', 'comandas', $idComanda, null, ['solicita_asistencia' => false]
        );
    }

    /** Deshace un grupo pendiente (aún no cobrado): sus líneas vuelven a quedar sin grupo. */
    public function eliminarGrupoCobro(int $idGrupo, int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $grupo = $this->repository->getGrupo($idGrupo, $idEmpresa);
        if (!$grupo || (int) $grupo['id_comanda'] !== $idComanda) {
            throw new Exception('Grupo de cobro no encontrado.');
        }
        if (($grupo['estado'] ?? '') !== 'pendiente') {
            throw new Exception('Solo se puede deshacer un grupo pendiente de cobro.');
        }

        if (($grupo['tipo_split'] ?? 'items') === 'partes_iguales') {
            $this->eliminarSplitPartesIguales($grupo, $idEmpresa, $idUsuario);
            return;
        }

        $this->db->beginTransaction();
        try {
            $this->repository->liberarLineasDelGrupo($idGrupo, $idEmpresa);
            $this->repository->eliminarGrupo($idGrupo, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR_GRUPO_COBRO', 'comanda_grupos_cobro', $idGrupo, $grupo, ['eliminado' => true]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Deshace una división en partes iguales COMPLETA (todas sus N partes a
     * la vez) — no tiene sentido borrar una sola parte y dejar el pool
     * compartido a medias. Si alguna parte ya se cobró, no se puede deshacer
     * ninguna (el dinero de esa parte ya generó un documento fiscal real).
     */
    private function eliminarSplitPartesIguales(array $grupo, int $idEmpresa, int $idUsuario): void
    {
        $idGrupoRaiz = (int) ($grupo['id_grupo_padre'] ?? $grupo['id']);
        $hermanos = $this->repository->getGruposHermanos($idGrupoRaiz, $idEmpresa);
        foreach ($hermanos as $h) {
            if (($h['estado'] ?? '') !== 'pendiente') {
                throw new Exception('No se puede deshacer: ya se cobró al menos una de las partes de esta división.');
            }
        }

        $this->db->beginTransaction();
        try {
            $this->repository->liberarPoolPartes($idGrupoRaiz);
            foreach ($hermanos as $h) {
                $this->repository->eliminarGrupo((int) $h['id'], $idEmpresa, $idUsuario);
            }
            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'ELIMINAR_SPLIT_PARTES_IGUALES', 'comanda_grupos_cobro', $idGrupoRaiz,
                null, ['grupos' => array_map(fn($h) => (int) $h['id'], $hermanos)]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Cobra un grupo: arma sus líneas como ítems y llama a
     * PosVentaService::cobrar() (genera Factura o Recibo, mueve inventario y
     * el asiento/Ingreso, igual que el mostrador — nada de eso se reimplementa
     * aquí). Cuando ya no queda nada pendiente de cobro en la comanda, la
     * cierra y libera la mesa.
     */
    /**
     * ¿Otro usuario tiene abierta esta comanda ahora mismo?
     *
     * En el salón varios meseros pueden estar en la misma mesa: agregar ítems se
     * deja pasar (agregar de más se corrige quitando la línea), pero COBRAR no
     * — de ahí sale un documento electrónico que no se puede "quitar". Por eso
     * esta verificación se llama solo desde el cobro interactivo del salón, y
     * NO desde el cobro que dispara Payphone: ahí el cliente ya pagó con
     * tarjeta y negarse dejaría el pago aprobado sin comprobante.
     *
     * @throws Exception si la tiene otro usuario (con su nombre).
     */
    public function verificarComandaLibre(int $idComanda, int $idEmpresa, int $idUsuario): void
    {
        $enUso = (new \App\Services\BloqueoEdicionService())
            ->verificarLibreOPropio(self::TABLA_BLOQUEO, $idComanda, $idEmpresa, $idUsuario);
        if ($enUso !== null) {
            throw new Exception("Esta mesa la está atendiendo ahora mismo {$enUso['usuario']}. Pídele que cierre la comanda antes de cobrarla.");
        }
    }

    public function cobrarGrupo(int $idGrupo, int $idEmpresa, int $idUsuario, array $datosPago, array $empresaConfig): array
    {
        // Candado ANTES de leer el estado del grupo. Sin él, "verificar que está
        // pendiente" y "marcarlo cobrado" quedan separados por toda la emisión
        // del documento: dos cobros simultáneos de la misma cuenta —dos meseros,
        // o un doble clic— pasaban ambos la verificación y emitían DOS
        // comprobantes del mismo consumo. Ver ComandaRepository::
        // intentarLockCobroGrupo() para por qué no es un lock de transacción.
        if (!$this->repository->intentarLockCobroGrupo($idGrupo, $idEmpresa)) {
            throw new Exception('Esta cuenta se está cobrando en otro dispositivo. Espera unos segundos y vuelve a intentarlo.');
        }

        try {
            return $this->_cobrarGrupoBloqueado($idGrupo, $idEmpresa, $idUsuario, $datosPago, $empresaConfig);
        } finally {
            $this->repository->liberarLockCobroGrupo($idGrupo, $idEmpresa);
        }
    }

    /** Cuerpo del cobro; solo se entra aquí con el candado del grupo ya tomado. */
    private function _cobrarGrupoBloqueado(int $idGrupo, int $idEmpresa, int $idUsuario, array $datosPago, array $empresaConfig): array
    {
        // Estado releído YA con el candado: es esta lectura la que decide, no la
        // que pudo hacer otro proceso un instante antes de tomarlo.
        $grupo = $this->repository->getGrupo($idGrupo, $idEmpresa);
        if (!$grupo) {
            throw new Exception('Grupo de cobro no encontrado.');
        }
        if (($grupo['estado'] ?? '') !== 'pendiente') {
            throw new Exception('Este grupo ya fue cobrado o anulado.');
        }

        $idComanda = (int) $grupo['id_comanda'];
        $comanda = $this->repository->find($idComanda, $idEmpresa);
        if (!$comanda) {
            throw new Exception('Comanda no encontrada.');
        }

        $esPartesIguales = ($grupo['tipo_split'] ?? 'items') === 'partes_iguales';
        if ($esPartesIguales) {
            $idGrupoRaiz = (int) ($grupo['id_grupo_padre'] ?? $grupo['id']);
            $numPartes = max(1, (int) ($grupo['total_partes'] ?? 1));
            $lineas = $this->repository->getLineasDelPoolPartes($idGrupoRaiz, $idEmpresa);
        } else {
            $lineas = $this->repository->getLineasDelGrupo($idGrupo, $idEmpresa);
        }
        if (empty($lineas)) {
            throw new Exception('El grupo no tiene ítems.');
        }
        foreach ($lineas as $l) {
            if (empty($l['id_producto'])) {
                throw new Exception('El ítem "' . $l['descripcion'] . '" no tiene un producto vinculado; todavía no se puede cobrar (solo ítems ligados a un producto del inventario).');
            }
        }

        $idPuntoEmision = (int) ($datosPago['id_punto_emision'] ?? 0);
        if ($idPuntoEmision <= 0) {
            $idPuntoEmision = (int) ($this->repository->getIdPuntoEmisionDeComanda($idComanda, $idEmpresa) ?? 0);
        }
        // Comanda abierta antes de que se atara el turno al abrir la mesa: en vez
        // de dejarla sin poder cobrar, se factura con el turno abierto de la
        // empresa. Es el mismo que habría tomado si se hubiera abierto hoy.
        if ($idPuntoEmision <= 0) {
            $idPuntoEmision = (int) ($this->ventaService->getSesionAbiertaEmpresa($idEmpresa)['id_punto_emision'] ?? 0);
        }
        if ($idPuntoEmision <= 0) {
            throw new Exception('No se pudo cobrar: no hay ningún turno de caja abierto. Abre la caja y vuelve a intentarlo.');
        }

        // En "partes iguales" cada línea del pool se reparte 1/N entre cada
        // parte (misma unidad, misma tarifa de IVA) — el precio_unitario no
        // cambia, así que el subtotal de cada parte queda proporcional.
        // Propina voluntaria: se factura como una línea más, pero PosVentaService
        // la deja fuera de la base sobre la que calcula el recargo por servicio
        // (si no, la propina inflaría ese recargo).
        $idProductoPropina = (int) ($empresaConfig['id_producto_propina'] ?? 0);

        $items = array_map(fn($l) => [
            'id_producto'     => (int) $l['id_producto'],
            'cantidad'        => $esPartesIguales ? round((float) $l['cantidad'] / $numPartes, 4) : (float) $l['cantidad'],
            'precio_unitario' => (float) $l['precio_unitario'],
            'descuento'       => $esPartesIguales ? round((float) $l['descuento'] / $numPartes, 2) : (float) $l['descuento'],
            'descripcion'     => (string) $l['descripcion'],
            'es_propina'      => $idProductoPropina > 0 && (int) $l['id_producto'] === $idProductoPropina,
            // Tarifa ya resuelta por ComandaRepository (la del ítem del Menú si la
            // línea vino de la carta, si no la del producto): el comprobante se
            // emite con ella, así coincide con lo que mostró la comanda.
            'id_tarifa_iva'   => (int) ($l['id_tarifa_iva'] ?? 0),
            'lote'            => (string) ($l['lote'] ?? ''),
            'caducidad'       => (string) ($l['caducidad'] ?? ''),
            'nup'             => (string) ($l['nup'] ?? ''),
        ], $lineas);

        $idCliente = (int) ($datosPago['id_cliente'] ?? 0);
        if ($idCliente <= 0) {
            $idCliente = (int) ($comanda['id_cliente'] ?? 0);
        }

        $res = $this->ventaService->cobrar([
            'id_empresa'              => $idEmpresa,
            'id_usuario'              => $idUsuario,
            'id_punto_emision'        => $idPuntoEmision,
            'items'                   => $items,
            'id_cliente'              => $idCliente,
            'tipo_documento'          => $datosPago['tipo_documento'] ?? 'RECIBO',
            'forma_pago'              => $datosPago['forma_pago'] ?? '01',
            'id_forma_pago_empresa'   => $datosPago['id_forma_pago_empresa'] ?? 0,
            'tipo_operacion_bancaria' => $datosPago['tipo_operacion_bancaria'] ?? '',
            'numero_operacion'        => $datosPago['numero_operacion'] ?? '',
            'fecha_cobro'             => $datosPago['fecha_cobro'] ?? '',
            'id_bodega'               => $datosPago['id_bodega'] ?? 0,
            // Recargo por servicio: se manda el porcentaje y PosVentaService lo
            // aplica sobre el subtotal de ESTE documento, así cada parte de una
            // cuenta dividida carga lo suyo. Viaja al comprobante como <propina>.
            'porcentaje_propina'      => $this->porcentajeServicioComanda($comanda),
        ], $empresaConfig);

        try {
            $this->db->beginTransaction();
            $this->repository->marcarGrupoCobrado($idGrupo, $idEmpresa, [
                'tipo_documento'   => $res['tipo_documento'],
                'id_documento'     => $res['id_documento'],
                'numero_documento' => $res['numero_documento'],
                'forma_pago'       => $datosPago['forma_pago'] ?? '01',
                'updated_by'       => $idUsuario,
            ]);

            $this->logService->registrar(
                $idUsuario, $idEmpresa, 'COBRAR_GRUPO_COMANDA', 'comanda_grupos_cobro', $idGrupo,
                null, ['id_comanda' => $idComanda, 'documento' => $res]
            );

            $sinGrupo = $this->repository->getLineasSinGrupo($idComanda, $idEmpresa);
            $pendientes = $this->repository->contarGruposPendientes($idComanda, $idEmpresa);
            if (empty($sinGrupo) && $pendientes === 0) {
                $this->repository->actualizarEstadoComanda($idComanda, $idEmpresa, 'cerrada', $idUsuario, true);
                $this->mesaRepo->actualizarEstado((int) $comanda['id_mesa'], $idEmpresa, 'disponible');
                $this->logService->registrar($idUsuario, $idEmpresa, 'CERRAR_COMANDA', 'comandas', $idComanda, null, ['estado' => 'cerrada']);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // El documento (Factura/Recibo) ya se generó dentro de PosVentaService::cobrar(),
            // en su propia transacción — un documento fiscal ya emitido no se revierte. Solo
            // se avisa: el grupo debe marcarse como cobrado a mano si esta escritura falló.
            error_log('[ComandaService::cobrarGrupo] Documento ' . ($res['numero_documento'] ?? '?') . ' generado, pero no se pudo cerrar el grupo/comanda: ' . $e->getMessage());
            throw new Exception('El documento ' . ($res['numero_documento'] ?? '') . ' se generó correctamente, pero hubo un error al cerrar el grupo de cobro. Contacta soporte.');
        }

        return $res;
    }

    /**
     * Cobra un grupo tras la aprobación de un pago Payphone iniciado desde el
     * portal QR (ver PedidoPublicoController::pagarAjax + PayphoneController::
     * procesarAprobacion). $trans es la fila de payphone_transacciones ya
     * aprobada. La idempotencia la da el propio estado del grupo: si ya no
     * está 'pendiente' (porque este webhook/retorno se disparó dos veces, o el
     * cliente refrescó la página), no hace nada — mismo criterio que
     * FacturaVentaService::generarIngresoDesdePayphone() con su id_ingreso.
     */
    public function cobrarGrupoDesdePayphone(array $trans): ?array
    {
        if (($trans['modulo'] ?? '') !== 'comanda_grupo_cobro' || empty($trans['id_referencia'])) {
            return null;
        }
        $idEmpresa = (int) $trans['id_empresa'];
        $idGrupo   = (int) $trans['id_referencia'];

        $grupo = $this->repository->getGrupo($idGrupo, $idEmpresa);
        if (!$grupo || $grupo['estado'] !== 'pendiente') {
            return null;
        }

        $comanda = $this->repository->find((int) $grupo['id_comanda'], $idEmpresa);
        if (!$comanda) {
            return null;
        }

        $ppRepo = new PayphoneRepository();
        $formaCobro = !empty($trans['id_forma_cobro'])
            ? ['id' => (int) $trans['id_forma_cobro']]
            : $ppRepo->getFormaCobroPayphone($idEmpresa);
        if (!$formaCobro) {
            throw new Exception('Pago Payphone aprobado (grupo ' . $idGrupo . ') pero no hay una forma de cobro PAYPHONE configurada; el grupo queda pendiente para cobrarlo manualmente.');
        }

        return $this->cobrarGrupo($idGrupo, $idEmpresa, (int) $comanda['id_usuario_mesero'], [
            'id_cliente'            => (int) ($grupo['id_cliente'] ?? 0),
            'tipo_documento'        => $grupo['tipo_documento_solicitado'] ?: 'RECIBO',
            'forma_pago'            => '19', // TARJETA DE CRÉDITO — mismo código SRI que ComandasController::mapearCodigoSriFormaPago() usa para tipo PAYPHONE
            'id_forma_pago_empresa' => (int) $formaCobro['id'],
        ], $this->getEmpresaConfigParaCobro($idEmpresa));
    }

    /** Mismo criterio que ComandasController::getEmpresaConfig() — necesario aquí porque el pago aprobado llega desde un controlador público sin ese helper. */
    private function getEmpresaConfigParaCobro(int $idEmpresa): array
    {
        $empresaModel = new Empresa();
        $empresaData = $empresaModel->getPorId($idEmpresa) ?? [];
        $establecimientos = $empresaModel->getEstablecimientos($idEmpresa);
        if (!empty($establecimientos)) {
            try {
                $estConfig = (new EmpresaRepository())->getEstablecimientoConfig((int) $establecimientos[0]['id']);
                if ($estConfig) {
                    $empresaData = array_merge($empresaData, $estConfig);
                }
            } catch (Throwable $e) {
                // Migración de configuración pendiente — se usan valores por defecto.
            }
        }
        return $empresaData;
    }
}
