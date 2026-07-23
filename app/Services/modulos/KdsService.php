<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\Cache;
use App\repositories\modulos\ComandaRepository;
use App\repositories\modulos\MenuRepository;

/**
 * Lectura para la pantalla de cocina/barra (KDS). Capa fina y de solo lectura
 * sobre ComandaRepository::getLineasParaKds(), con caché APCu de TTL corto
 * (mismo patrón que ContadoresNavbarService) — varias pantallas/pollings
 * pidiendo la misma estación casi al mismo tiempo no repiten la consulta.
 * La caché se invalida al enviar líneas a cocina o cambiar su estado (ver
 * ComandaService::invalidarKds), no por tiempo únicamente.
 *
 * Las estaciones son un catálogo configurable por empresa (no un enum fijo
 * cocina/barra): un restaurante puede tener 5 barras y 3 cocinas, cada una
 * con su propia pantalla — ver MenuService::getEstaciones().
 */
class KdsService
{
    private const TTL_SEGUNDOS = 5;

    private ComandaRepository $repository;
    private MenuRepository $menuRepository;

    public function __construct(ComandaRepository $repository, MenuRepository $menuRepository)
    {
        $this->repository = $repository;
        $this->menuRepository = $menuRepository;
    }

    /** Estaciones configuradas para la empresa (para las pestañas del KDS). */
    public function getEstaciones(int $idEmpresa): array
    {
        return $this->menuRepository->getEstaciones($idEmpresa);
    }

    /**
     * Comandas activas para una estación, con sus líneas agrupadas por
     * numero_comanda/mesa — lo que pinta cada tarjeta del KDS.
     */
    public function getComandas(int $idEmpresa, int $idEstacion): array
    {
        $clave = "kds:{$idEmpresa}:{$idEstacion}";

        $cache = Cache::get($clave);
        if (is_array($cache)) {
            return $cache;
        }

        $lineas = $this->repository->getLineasParaKds($idEmpresa, $idEstacion);
        $agrupado = $this->agruparPorComanda($lineas);

        Cache::set($clave, $agrupado, self::TTL_SEGUNDOS);
        return $agrupado;
    }

    /** Agrupa líneas sueltas en tarjetas por comanda (numero_comanda + mesa), ordenadas por la más antigua primero. */
    private function agruparPorComanda(array $lineas): array
    {
        $porComanda = [];
        foreach ($lineas as $l) {
            $idComanda = (int) $l['id_comanda'];
            if (!isset($porComanda[$idComanda])) {
                $porComanda[$idComanda] = [
                    'id_comanda'     => $idComanda,
                    'numero_comanda' => $l['numero_comanda'],
                    'mesa_nombre'    => $l['mesa_nombre'],
                    'enviado_at'     => $l['enviado_at'],
                    'lineas'         => [],
                ];
            }
            // La tarjeta muestra el envío más antiguo entre sus líneas (la ronda que más tiempo lleva esperando).
            if ($l['enviado_at'] !== null && $l['enviado_at'] < $porComanda[$idComanda]['enviado_at']) {
                $porComanda[$idComanda]['enviado_at'] = $l['enviado_at'];
            }
            $porComanda[$idComanda]['lineas'][] = $l;
        }
        $resultado = array_values($porComanda);
        usort($resultado, fn($a, $b) => strcmp((string) $a['enviado_at'], (string) $b['enviado_at']));
        return $resultado;
    }
}
