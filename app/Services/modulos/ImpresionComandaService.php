<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ImpresionComandaRepository;
use App\Services\LogSistemaService;
use Exception;
use PDO;
use Throwable;

/**
 * Impresión de órdenes de cocina/barra.
 *
 * El servidor no puede hablarle a la impresora del restaurante (producción
 * corre fuera de esa red), así que este servicio no imprime: **encola**. Quien
 * imprime es el navegador que ya tiene abierto el KDS de la estación, que en su
 * poll recoge lo pendiente, lo manda a la impresora conectada a ese equipo y
 * marca la fila como impresa.
 *
 * Cada estación decide su comportamiento (ver `estaciones_impresion`):
 *  - `imprime_ordenes = false` → no se encola nada; la estación es solo pantalla.
 *  - `imprimir_auto = true`    → el ticket se encola solo al enviar a cocina.
 *  - `imprimir_auto = false`   → solo se encola cuando alguien lo pide a mano.
 */
class ImpresionComandaService
{
    private ImpresionComandaRepository $repository;
    private LogSistemaService $logService;
    private PDO $db;

    public function __construct(
        ?ImpresionComandaRepository $repository = null,
        ?LogSistemaService $logService = null
    ) {
        $this->repository = $repository ?? new ImpresionComandaRepository();
        $this->logService = $logService ?? new LogSistemaService();
        $this->db         = Database::getConnection();
    }

    /**
     * Configuración del restaurante (si el local prepara, y su estación
     * predeterminada). Se resuelve al vuelo y no en el constructor porque casi
     * ningún flujo de este servicio la necesita.
     */
    private function configRestaurante(): ConfiguracionRestauranteService
    {
        static $servicio = null;
        return $servicio ??= new ConfiguracionRestauranteService();
    }

    private function configRepository(): \App\repositories\modulos\ConfiguracionRestauranteRepository
    {
        static $repo = null;
        return $repo ??= new \App\repositories\modulos\ConfiguracionRestauranteRepository();
    }

    /**
     * Encola los tickets que correspondan a un envío a cocina.
     *
     * IMPORTANTE: se llama DENTRO de la transacción de
     * ComandaService::enviarACocina() y por eso **no abre ni cierra ninguna**:
     * si el envío se revierte, los tickets encolados se van con él. Postgres no
     * anida transacciones, así que abrir otra aquí rompería la de fuera.
     *
     * @param array $lineasEnviadas Filas [{id, id_estacion_impresion}] que acaba de mover el envío.
     * @return int Cuántos tickets se encolaron.
     */
    public function encolarPorEnvio(int $idComanda, int $idEmpresa, int $idUsuario, array $lineasEnviadas): int
    {
        // Sin migración aplicada esto no existe todavía: se calla y sigue. Aquí
        // NO se lanza excepción — corre dentro de la transacción del envío a
        // cocina, y el salón no puede quedarse sin poder enviar pedidos porque
        // falte una función accesoria.
        if (empty($lineasEnviadas) || !$this->repository->disponible()) {
            return 0;
        }

        $estaciones = $this->repository->getEstacionesConImpresora($idEmpresa);
        if (empty($estaciones)) {
            return 0;
        }

        // Un ticket por estación: la cocina no debe recibir lo de la barra.
        $porEstacion = [];
        foreach ($lineasEnviadas as $linea) {
            $idEstacion = (int) ($linea['id_estacion_impresion'] ?? 0);
            // Sin estación la línea nace entregada (no pasa por preparación), y
            // una estación sin impresora o en modo manual no encola sola.
            if ($idEstacion <= 0 || !isset($estaciones[$idEstacion])) {
                continue;
            }
            if (empty($estaciones[$idEstacion]['imprimir_auto'])) {
                continue;
            }
            $porEstacion[$idEstacion][] = (int) $linea['id'];
        }

        $encolados = 0;
        foreach ($porEstacion as $idEstacion => $idsLineas) {
            $this->repository->encolar($idEmpresa, $idComanda, (int) $idEstacion, $idsLineas, $idUsuario);
            $encolados++;
        }
        return $encolados;
    }

    /**
     * Encola a pedido: el botón "Imprimir orden" de la comanda y la reimpresión
     * desde el KDS. Toma las líneas vivas de cada estación —no solo las que se
     * acaban de enviar— porque lo que se quiere es el ticket completo de esa
     * estación tal como está la mesa ahora.
     *
     * @param int $idEstacion 0 = todas las estaciones con impresora que tengan algo.
     * @return int Cuántos tickets se encolaron.
     */
    public function encolarAPedido(int $idComanda, int $idEmpresa, int $idUsuario, int $idEstacion = 0, bool $esReimpresion = true): int
    {
        if (!$this->repository->disponible()) {
            throw new Exception('La impresión de órdenes todavía no está habilitada en este servidor.');
        }

        $estaciones = $this->repository->getEstacionesConImpresora($idEmpresa);
        if (empty($estaciones)) {
            throw new Exception('Ninguna estación tiene impresora configurada. Actívala en Configuración Restaurante.');
        }
        if ($idEstacion > 0 && !isset($estaciones[$idEstacion])) {
            throw new Exception('Esa estación no tiene impresora configurada.');
        }

        // Un local que no manda nada a preparar imprime SIN restricciones: no
        // hay envío a cocina que exigir ni estación por línea que filtrar, así
        // que la orden es la comanda entera y sale por la estación
        // predeterminada — la impresora que el local dejó configurada para esto.
        if (!$this->configRestaurante()->usaPreparacion($idEmpresa)) {
            return $this->encolarComandaCompleta($idComanda, $idEmpresa, $idUsuario, $idEstacion, $esReimpresion, $estaciones);
        }

        $objetivo = $idEstacion > 0 ? [$idEstacion => $estaciones[$idEstacion]] : $estaciones;

        $this->db->beginTransaction();
        try {
            $encolados = 0;
            foreach ($objetivo as $id => $cfg) {
                $idsLineas = $this->repository->getLineasVivasDeEstacion($idComanda, $idEmpresa, (int) $id);
                if (empty($idsLineas)) {
                    continue;
                }
                $this->repository->encolar($idEmpresa, $idComanda, (int) $id, $idsLineas, $idUsuario, $esReimpresion);
                $encolados++;
            }

            if ($encolados === 0) {
                // Aquí el local SÍ trabaja con preparación, así que el caso
                // habitual no es "no hay ítems" sino "todavía no se enviaron":
                // la orden de cocina es lo que se mandó a preparar. Decirlo así
                // evita que el mesero busque el problema en la impresora cuando
                // lo que falta es pulsar Enviar.
                throw new Exception(
                    'No hay nada que imprimir todavía: primero pulse "Enviar a preparación". '
                    . 'La orden se imprime sola al enviarla si la estación está configurada para eso.'
                );
            }

            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                $esReimpresion ? 'REIMPRIMIR_ORDEN_COMANDA' : 'IMPRIMIR_ORDEN_COMANDA',
                'comandas',
                $idComanda,
                null,
                ['tickets' => $encolados, 'id_estacion' => $idEstacion ?: 'todas']
            );

            $this->db->commit();
            return $encolados;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Encola la comanda entera en una sola impresora. Es lo que se imprime en un
     * local que no manda nada a preparar: sus líneas nacen entregadas y no
     * pertenecen a ninguna estación, así que no hay nada que agrupar ni ningún
     * envío previo que exigir.
     *
     * @param array $estaciones Estaciones con impresora, indexadas por id.
     */
    private function encolarComandaCompleta(int $idComanda, int $idEmpresa, int $idUsuario, int $idEstacion, bool $esReimpresion, array $estaciones): int
    {
        // El destino es la estación predeterminada. Si el local no marcó
        // ninguna y solo tiene una impresora, se usa esa: pedir que marque la
        // única que existe sería un trámite sin sentido.
        $destino = $idEstacion > 0 ? $idEstacion : (int) ($this->configRestaurante()->getEstacionPredeterminada($idEmpresa) ?? 0);
        if ($destino <= 0 && count($estaciones) === 1) {
            $destino = (int) array_key_first($estaciones);
        }
        if ($destino <= 0 || !isset($estaciones[$destino])) {
            throw new Exception(
                'Marque la estación predeterminada en Configuración Restaurante: '
                . 'es la impresora por la que sale la orden cuando el local no trabaja con preparación.'
            );
        }

        $idsLineas = $this->configRepository()->getLineasVivasDeComanda($idComanda, $idEmpresa);
        if (empty($idsLineas)) {
            throw new Exception('Esta comanda no tiene ítems para imprimir.');
        }

        $this->db->beginTransaction();
        try {
            $this->repository->encolar($idEmpresa, $idComanda, $destino, $idsLineas, $idUsuario, $esReimpresion);
            $this->logService->registrar(
                $idUsuario,
                $idEmpresa,
                $esReimpresion ? 'REIMPRIMIR_ORDEN_COMANDA' : 'IMPRIMIR_ORDEN_COMANDA',
                'comandas',
                $idComanda,
                null,
                ['tickets' => 1, 'id_estacion' => $destino, 'sin_preparacion' => true]
            );
            $this->db->commit();
            return 1;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Configuración de impresora de una estación, o null si no imprime. La
     * pantalla la usa para saber si debe ofrecer el botón de reimprimir.
     */
    public function configEstacion(int $idEmpresa, int $idEstacion): ?array
    {
        if ($idEstacion <= 0 || !$this->repository->disponible()) {
            return null;
        }
        return $this->repository->getEstacionesConImpresora($idEmpresa)[$idEstacion] ?? null;
    }

    /**
     * Datos del ticket de una comanda para imprimirlo AQUÍ MISMO, desde el
     * navegador que tiene abierta la comanda.
     *
     * Es lo que se usa en un local sin preparación. Ahí no sirve encolar: quien
     * saca el papel de la cola es la pantalla del KDS de la estación, y en un
     * local que no prepara nada esa pantalla no tiene por qué estar abierta —de
     * hecho nunca mostraría ninguna tarjeta—, así que el ticket se quedaría
     * esperando para siempre.
     *
     * La estación predeterminada aporta el FORMATO (ancho de papel y copias);
     * la impresora física es la que tenga configurada ese equipo.
     */
    public function getOrdenParaImprimir(int $idComanda, int $idEmpresa): array
    {
        $cabecera = $this->configRepository()->getCabeceraComanda($idComanda, $idEmpresa);
        if (!$cabecera) {
            throw new Exception('Comanda no encontrada.');
        }

        $lineas = $this->configRepository()->getLineasParaImprimir($idComanda, $idEmpresa);
        if (empty($lineas)) {
            throw new Exception('Esta comanda no tiene ítems para imprimir.');
        }

        // Formato: el de la estación predeterminada; si no hay ninguna marcada y
        // solo existe una impresora, la de esa. Sin nada configurado se imprime
        // igual, con el formato estándar de 80 mm.
        $estaciones = $this->repository->disponible()
            ? $this->repository->getEstacionesConImpresora($idEmpresa)
            : [];
        $idPred = (int) ($this->configRestaurante()->getEstacionPredeterminada($idEmpresa) ?? 0);
        $cfg = $estaciones[$idPred] ?? (count($estaciones) === 1 ? reset($estaciones) : null);

        return [
            'estacion_nombre' => (string) ($cfg['nombre'] ?? ''),
            'ancho_papel'     => (int) ($cfg['ancho_papel'] ?? 80),
            'copias'          => max(1, min(5, (int) ($cfg['copias'] ?? 1))),
            'numero_comanda'  => (string) ($cabecera['numero_comanda'] ?? ''),
            'mesa_nombre'     => (string) ($cabecera['mesa_nombre'] ?? ''),
            'mesero_nombre'   => (string) ($cabecera['mesero_nombre'] ?? ''),
            'observaciones'   => (string) ($cabecera['observaciones'] ?? ''),
            'lineas'          => $lineas,
        ];
    }

    /**
     * ¿Hay alguna estación con impresora? Decide si la comanda pinta el botón
     * de imprimir: sin ninguna, ese botón solo podría dar un error.
     */
    public function hayImpresoras(int $idEmpresa): bool
    {
        if (!$this->repository->disponible()) {
            return false;
        }
        return $this->repository->getEstacionesConImpresora($idEmpresa) !== [];
    }

    /** Tickets que la pantalla de esta estación tiene que imprimir ahora. */
    public function pendientes(int $idEmpresa, int $idEstacion): array
    {
        if ($idEstacion <= 0 || !$this->repository->disponible()) {
            return [];
        }
        return $this->repository->getPendientes($idEmpresa, $idEstacion);
    }

    /**
     * El KDS confirma que el ticket salió. Devuelve false si ya estaba marcado
     * —dos pantallas abiertas en la misma estación—, y en ese caso el llamador
     * no debe tratarlo como error: la orden ya se imprimió.
     */
    public function marcarImpreso(int $id, int $idEmpresa, int $idUsuario): bool
    {
        if (!$this->repository->disponible()) {
            return false;
        }
        return $this->repository->marcarImpreso($id, $idEmpresa, $idUsuario);
    }

    /** Historial de impresiones de la comanda. */
    public function getPorComanda(int $idComanda, int $idEmpresa): array
    {
        if (!$this->repository->disponible()) {
            return [];
        }
        return $this->repository->getPorComanda($idComanda, $idEmpresa);
    }

    /**
     * Descarta lo pendiente de una comanda anulada. Igual que encolarPorEnvio,
     * corre dentro de la transacción del llamador y no abre la suya.
     */
    public function anularPendientes(int $idComanda, int $idEmpresa): int
    {
        if (!$this->repository->disponible()) {
            return 0;
        }
        return $this->repository->anularPendientesDeComanda($idComanda, $idEmpresa);
    }
}
