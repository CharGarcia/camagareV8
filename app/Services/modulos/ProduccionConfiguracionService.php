<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\repositories\modulos\ProduccionConfiguracionRepository;
use App\Services\LogSistemaService;

/**
 * Configuración del módulo Producción por empresa: bodegas por defecto e interruptores
 * (§3.3 del diseño). Guardarla por primera vez activa el módulo para la empresa.
 *
 * Las cuentas contables NO se configuran aquí: van en Configuración Contable → Producción
 * (tipo de asiento 'produccion'), que solo ven las empresas que usan el módulo.
 * ETIQUETAS_CUENTA se conserva para los mensajes del asiento.
 */
class ProduccionConfiguracionService
{
    /** Submódulos del módulo Producción (los que dan acceso a él a la empresa y al usuario). */
    public const RUTAS = [
        'modulos/produccion-configuracion', 'modulos/produccion-formulas',
        'modulos/produccion-ordenes', 'modulos/produccion-reportes',
    ];

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
        ];

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
}
