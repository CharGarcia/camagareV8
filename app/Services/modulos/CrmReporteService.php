<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\CrmEmbudoRepository;
use App\repositories\modulos\CrmReporteRepository;

/**
 * CRM: reportes del embudo (solo lectura). Diseño: docs/diseno/crm-redes-fase1.md §4 punto 8.
 */
class CrmReporteService
{
    /** Tope del período para no recorrer años de historial en cada consulta. */
    public const MAX_DIAS = 731;

    public function __construct(private CrmReporteRepository $repo, private CrmEmbudoRepository $embudos)
    {
    }

    public static function crear(): self
    {
        return new self(new CrmReporteRepository(), new CrmEmbudoRepository());
    }

    /**
     * Todos los bloques del reporte para el período y los filtros de pantalla.
     * Sin fechas: desde el primer día del mes hace 5 meses hasta hoy (6 meses).
     */
    public function generar(int $idEmpresa, array $f, string $desde, string $hasta, ?int $idUsuarioFiltro): array
    {
        [$desde, $hasta] = $this->periodo($desde, $hasta);
        $idEmbudo = (int) ($f['id_embudo'] ?? 0);
        if ($idEmbudo <= 0 || !$this->embudos->getEmbudo($idEmbudo, $idEmpresa)) {
            $idEmbudo = (int) ($this->embudos->getEmbudoDefecto($idEmpresa)['id'] ?? 0);
        }
        // Los bloques generales respetan el embudo elegido en la tarjeta de control.
        $f['id_embudo'] = $idEmbudo ?: null;
        return [
            'desde'          => $desde,
            'hasta'          => $hasta,
            'id_embudo'      => $idEmbudo,
            'resumen'        => $this->repo->resumen($idEmpresa, $f, $desde, $hasta, $idUsuarioFiltro),
            'por_etapa'      => $idEmbudo ? $this->repo->porEtapa($idEmpresa, $idEmbudo, $f, $idUsuarioFiltro) : [],
            'tiempo_etapa'   => $idEmbudo ? $this->repo->tiempoPorEtapa($idEmpresa, $idEmbudo, $f, $desde, $hasta, $idUsuarioFiltro) : [],
            'por_mes'        => $this->repo->porMes($idEmpresa, $f, $desde, $hasta, $idUsuarioFiltro),
            'por_origen'     => $this->repo->agrupado('origen', $idEmpresa, $f, $desde, $hasta, $idUsuarioFiltro),
            'por_responsable' => $this->repo->agrupado('responsable', $idEmpresa, $f, $desde, $hasta, $idUsuarioFiltro),
            'motivos'        => $this->repo->motivosPerdida($idEmpresa, $f, $desde, $hasta, $idUsuarioFiltro),
        ];
    }

    /** @return array{0:string,1:string} fechas Y-m-d válidas, ordenadas y dentro del tope */
    private function periodo(string $desde, string $hasta): array
    {
        $ok = fn(string $d) => ($t = \DateTime::createFromFormat('!Y-m-d', $d)) && $t->format('Y-m-d') === $d;
        $hasta = $ok($hasta) ? $hasta : date('Y-m-d');
        $desde = $ok($desde) ? $desde : date('Y-m-01', strtotime('-5 months', strtotime(date('Y-m-01', strtotime($hasta)))));
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > self::MAX_DIAS) {
            throw new \InvalidArgumentException('El período del reporte no puede pasar de 2 años.');
        }
        return [$desde, $hasta];
    }
}
