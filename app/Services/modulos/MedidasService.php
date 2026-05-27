<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\TipoMedidaRepository;
use App\repositories\modulos\UnidadMedidaRepository;

class MedidasService
{
    private TipoMedidaRepository $tipoRepo;
    private UnidadMedidaRepository $unidadRepo;

    public function __construct()
    {
        $this->tipoRepo = new TipoMedidaRepository();
        $this->unidadRepo = new UnidadMedidaRepository();
    }

    /**
     * Obtiene todos los tipos de medida activos para la empresa
     */
    public function listarTipos(int $idEmpresa): array
    {
        return $this->tipoRepo->getActivos($idEmpresa);
    }

    /**
     * Obtiene las unidades de medida asociadas a un tipo
     */
    public function listarUnidadesPorTipo(int $idEmpresa, int $idTipo): array
    {
        return $this->unidadRepo->getPorTipo($idEmpresa, $idTipo);
    }

    /**
     * Asegura la existencia de medidas base por defecto (Unidad, Peso)
     */
    public function asegurarMedidasBase(int $idEmpresa, int $idUsuario): void
    {
        // Asegurar tipo "Unidad" (código "0") — es el default para tipo_produccion='01'
        $existeUnidad = $this->tipoRepo->existsByNameIncludingDeleted($idEmpresa, 'Unidad');
        if (!$existeUnidad) {
            $idTipoU = $this->tipoRepo->create([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'codigo'     => '0',
                'nombre'     => 'Unidad',
                'status'     => true
            ]);
            $this->unidadRepo->create([
                'id_empresa'  => $idEmpresa,
                'id_tipo'     => $idTipoU,
                'id_usuario'  => $idUsuario,
                'codigo'      => '0',
                'nombre'      => 'Unidad',
                'abreviatura' => 'U',
                'factor_base' => 1,
                'status'      => true
            ]);
        }

        // Asegurar tipo "Peso"
        $existePeso = $this->tipoRepo->existsByNameIncludingDeleted($idEmpresa, 'Peso');
        if (!$existePeso) {
            $idTipo = $this->tipoRepo->create([
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
                'codigo'     => 'PESO',
                'nombre'     => 'Peso',
                'status'     => true
            ]);
            $this->unidadRepo->create([
                'id_empresa'  => $idEmpresa,
                'id_tipo'     => $idTipo,
                'id_usuario'  => $idUsuario,
                'codigo'      => 'KG',
                'nombre'      => 'Kilogramo',
                'abreviatura' => 'Kg',
                'factor_base' => 1,
                'status'      => true
            ]);
        }
    }

    /**
     * Retorna los IDs del tipo medida "Unidad" y su unidad "Unidad" para la empresa.
     * Se usa como default al crear productos tipo '01'.
     */
    public function getMedidaDefaultUnidad(int $idEmpresa): ?array
    {
        $tipo = $this->tipoRepo->findByName($idEmpresa, 'Unidad');
        if (!$tipo) return null;

        $unidades = $this->unidadRepo->getPorTipo($idEmpresa, (int)$tipo['id']);
        if (empty($unidades)) return null;

        $unidad = null;
        foreach ($unidades as $u) {
            if (strtolower($u['nombre']) === 'unidad') {
                $unidad = $u;
                break;
            }
        }
        if (!$unidad) $unidad = $unidades[0];

        return [
            'id_tipo_medida' => (int)$tipo['id'],
            'id_medida'      => (int)$unidad['id'],
        ];
    }
}
