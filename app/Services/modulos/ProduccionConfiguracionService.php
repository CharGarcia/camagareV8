<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ProduccionConfiguracionRepository;
use App\Services\LogSistemaService;

/**
 * Configuración del módulo Producción por empresa: bodegas por defecto, interruptores
 * (§3.3 del diseño) y cuentas contables propias del módulo. Guardarla por primera vez
 * activa el módulo para la empresa.
 *
 * Las cuentas viven aquí y no en Configuración Contable a propósito: así el módulo no
 * agrega conceptos ni avisos de "cuentas faltantes" a las empresas que no lo usan
 * (docs/diseno/produccion-fase1.md §10.4).
 */
class ProduccionConfiguracionService
{
    public const METODOS_REPARTO = [
        'valor_venta' => 'Valor de venta relativo',
        'unidades'    => 'Unidades físicas (kg, litros, unidades)',
        'porcentaje'  => '% fijo definido en la fórmula',
    ];

    public const ETIQUETAS_CUENTA = [
        'id_cuenta_materia_prima' => 'Inventario de materia prima',
        'id_cuenta_en_proceso'    => 'Productos en proceso',
        'id_cuenta_terminado'     => 'Inventario de producto terminado',
        'id_cuenta_mano_obra'     => 'Mano de obra aplicada',
        'id_cuenta_cif'           => 'Costos indirectos aplicados',
        'id_cuenta_merma'         => 'Merma anormal (gasto)',
    ];

    private ProduccionConfiguracionRepository $repo;
    private LogSistemaService $log;

    public function __construct(?ProduccionConfiguracionRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new ProduccionConfiguracionRepository();
        $this->log  = $log  ?? new LogSistemaService();
    }

    public static function crear(): self
    {
        return new self();
    }

    public function repo(): ProduccionConfiguracionRepository
    {
        return $this->repo;
    }

    public function instalado(): bool
    {
        return $this->repo->instalado();
    }

    /** Configuración de la empresa, o null si todavía no la guardó (módulo inactivo). */
    public function get(int $idEmpresa): ?array
    {
        if (!$this->instalado()) {
            return null;
        }
        return $this->repo->getPorEmpresa($idEmpresa);
    }

    /** Lo mismo que get(), pero lanza un mensaje claro si el módulo no está activo. */
    public function requerir(int $idEmpresa): array
    {
        if (!$this->instalado()) {
            throw new \DomainException('El módulo Producción aún no está instalado en la base de datos (falta database/2026-11-03_produccion.sql).');
        }
        $cfg = $this->repo->getPorEmpresa($idEmpresa);
        if (!$cfg) {
            throw new \DomainException('Producción aún no está configurado en esta empresa. Guarde primero la Configuración de Producción.');
        }
        return $cfg;
    }

    public function guardar(array $in, int $idEmpresa, int $idUsuario): array
    {
        if (!$this->instalado()) {
            throw new \DomainException('El módulo Producción aún no está instalado en la base de datos.');
        }

        $bool = static fn($v): bool => in_array((string) $v, ['1', 'true', 'on', 'si'], true);
        $idOpc = static fn($v): ?int => (int) $v > 0 ? (int) $v : null;

        $d = [
            'id_bodega_insumos'    => $idOpc($in['id_bodega_insumos'] ?? null),
            'id_bodega_terminado'  => $idOpc($in['id_bodega_terminado'] ?? null),
            'id_bodega_cuarentena' => $idOpc($in['id_bodega_cuarentena'] ?? null),
            'permitir_sin_stock'   => $bool($in['permitir_sin_stock'] ?? false),
            'exigir_lote_insumos'  => $bool($in['exigir_lote_insumos'] ?? false),
            'usar_cuarentena'      => $bool($in['usar_cuarentena'] ?? false),
            'metodo_reparto'       => (string) ($in['metodo_reparto'] ?? 'valor_venta'),
            'contabilizar'         => $bool($in['contabilizar'] ?? false),
        ];
        foreach (ProduccionConfiguracionRepository::CAMPOS_CUENTA as $campo) {
            $d[$campo] = $idOpc($in[$campo] ?? null);
        }

        if (!isset(self::METODOS_REPARTO[$d['metodo_reparto']])) {
            throw new \DomainException('Método de reparto del costo no válido.');
        }
        foreach (['id_bodega_insumos', 'id_bodega_terminado', 'id_bodega_cuarentena'] as $campo) {
            if ($d[$campo] !== null && !$this->repo->bodegaValida($d[$campo], $idEmpresa)) {
                throw new \DomainException('Una de las bodegas elegidas no existe en esta empresa.');
            }
        }
        if ($d['usar_cuarentena'] && $d['id_bodega_cuarentena'] === null) {
            throw new \DomainException('Para usar cuarentena elija la bodega de cuarentena.');
        }
        foreach (ProduccionConfiguracionRepository::CAMPOS_CUENTA as $campo) {
            if ($d[$campo] !== null && !$this->repo->cuentaValida($d[$campo], $idEmpresa)) {
                throw new \DomainException('La cuenta «' . self::ETIQUETAS_CUENTA[$campo] . '» debe ser una cuenta de movimiento de esta empresa.');
            }
        }
        if ($d['contabilizar']) {
            $faltan = $this->cuentasFaltantes($d);
            if ($faltan !== []) {
                throw new \DomainException('Para contabilizar la producción configure: ' . implode(', ', $faltan) . '.');
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $previo = $this->repo->getPorEmpresa($idEmpresa);
            if ($previo) {
                $this->repo->actualizar((int) $previo['id'], $d, $idEmpresa, $idUsuario);
                $id = (int) $previo['id'];
            } else {
                $id = $this->repo->insertar($d, $idEmpresa, $idUsuario);
            }
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                $previo ? 'ACTUALIZAR_CONFIG_PRODUCCION' : 'CREAR_CONFIG_PRODUCCION',
                'produccion_configuracion',
                $id,
                $previo,
                $d
            );
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $this->repo->getPorEmpresa($idEmpresa) ?? [];
    }

    /**
     * Cuentas que faltan para poder contabilizar (la de merma solo es obligatoria
     * cuando se registra merma anormal; la de mano de obra/CIF, cuando hay importes).
     *
     * @return string[] etiquetas
     */
    public function cuentasFaltantes(array $cfg): array
    {
        $obligatorias = ['id_cuenta_materia_prima', 'id_cuenta_en_proceso', 'id_cuenta_terminado'];
        $faltan = [];
        foreach ($obligatorias as $campo) {
            if (empty($cfg[$campo])) {
                $faltan[] = self::ETIQUETAS_CUENTA[$campo];
            }
        }
        return $faltan;
    }

    /** Cuentas configuradas con su código y nombre, para pintar los selectores. */
    public function cuentasParaVista(?array $cfg, int $idEmpresa): array
    {
        if (!$cfg) {
            return [];
        }
        $ids = [];
        foreach (ProduccionConfiguracionRepository::CAMPOS_CUENTA as $campo) {
            if (!empty($cfg[$campo])) {
                $ids[] = (int) $cfg[$campo];
            }
        }
        $datos = $this->repo->getCuentas($ids, $idEmpresa);
        $out = [];
        foreach (ProduccionConfiguracionRepository::CAMPOS_CUENTA as $campo) {
            $id = (int) ($cfg[$campo] ?? 0);
            if ($id > 0 && isset($datos[$id])) {
                $out[$campo] = $datos[$id];
            }
        }
        return $out;
    }
}
