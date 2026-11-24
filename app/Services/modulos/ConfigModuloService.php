<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ConfigModulo;
use App\repositories\modulos\ConfigModuloRepository;
use App\Rules\modulos\ConfigModuloRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Personalización de módulos por empresa y por cliente (catálogo
 * App\Helpers\ConfigModulo, tabla `config_modulos`).
 *
 * Resolución de una opción, de lo específico a lo general:
 *   valor propio del cliente (si la opción es 'por_cliente') → empresa → catálogo.
 *
 * Uso desde un módulo:
 *   ConfigModuloService::crear()->valor($idEmpresa, 'modulos/consignaciones-ventas', 'columnas_pdf', $idCliente)
 */
class ConfigModuloService
{
    private const TABLA = 'config_modulos';

    public function __construct(
        private ConfigModuloRepository $repository,
        private ConfigModuloRules $rules,
        private LogSistemaService $logService
    ) {
    }

    /** Instancia lista para usar desde un controlador. */
    public static function crear(): self
    {
        return new self(new ConfigModuloRepository(), new ConfigModuloRules(), new LogSistemaService());
    }

    /**
     * Valor efectivo de una opción para el cliente indicado (o para la empresa, sin
     * cliente). Si la tabla aún no existe, no hay nada guardado o algo falla,
     * devuelve el valor por defecto del catálogo: una personalización nunca debe
     * impedir imprimir un documento.
     */
    public function valor(int $idEmpresa, string $modulo, string $clave, ?int $idCliente = null)
    {
        $def = ConfigModulo::opcion($modulo, $clave);
        if ($def === null) {
            return null;
        }
        $porDefecto = ConfigModulo::porDefecto($modulo, $clave);

        try {
            if (!empty($def['por_cliente']) && $idCliente) {
                $filaCliente = $this->repository->getFilaCliente($idEmpresa, $modulo, $idCliente);
                if ($filaCliente && array_key_exists($clave, $filaCliente['opciones'])) {
                    return $this->rules->normalizarValor($modulo, $clave, $filaCliente['opciones'][$clave]);
                }
            }
            $filaEmpresa = $this->repository->getFilaEmpresa($idEmpresa, $modulo);
            if ($filaEmpresa && array_key_exists($clave, $filaEmpresa['opciones'])) {
                return $this->rules->normalizarValor($modulo, $clave, $filaEmpresa['opciones'][$clave]);
            }
        } catch (\Throwable $e) {
            \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => __FUNCTION__]);
        }
        return $porDefecto;
    }

    /**
     * Datos para dibujar las pestañas de Configuración de Empresa: por cada módulo
     * del catálogo, sus opciones con el valor de la empresa y, en las 'por_cliente',
     * los clientes que tienen un valor propio.
     */
    public function datosPantalla(int $idEmpresa): array
    {
        $disponible = $this->repository->disponible();
        $out = [];
        foreach (ConfigModulo::CATALOGO as $modulo => $mod) {
            $filaEmpresa   = $disponible ? $this->repository->getFilaEmpresa($idEmpresa, $modulo) : null;
            $filasClientes = $disponible ? $this->repository->getFilasClientes($idEmpresa, $modulo) : [];

            $opciones = [];
            foreach ($mod['opciones'] as $clave => $def) {
                $actual = array_key_exists($clave, $filaEmpresa['opciones'] ?? [])
                    ? $this->rules->normalizarValor($modulo, $clave, $filaEmpresa['opciones'][$clave])
                    : ConfigModulo::porDefecto($modulo, $clave);

                $clientes = [];
                if (!empty($def['por_cliente'])) {
                    foreach ($filasClientes as $fc) {
                        if (array_key_exists($clave, $fc['opciones'])) {
                            $clientes[] = [
                                'id'             => (int) $fc['id_cliente'],
                                'nombre'         => (string) $fc['cliente_nombre'],
                                'identificacion' => (string) $fc['cliente_identificacion'],
                                'valor'          => $this->rules->normalizarValor($modulo, $clave, $fc['opciones'][$clave]),
                            ];
                        }
                    }
                }

                $opciones[$clave] = $def + ['valor' => $actual, 'clientes' => $clientes];
            }

            $out[$modulo] = [
                'modulo'      => $modulo,
                'slug'        => ConfigModulo::slug($modulo),
                'titulo'      => $mod['titulo'],
                'icono'       => $mod['icono'],
                'descripcion' => $mod['descripcion'] ?? '',
                'opciones'    => $opciones,
                'disponible'  => $disponible,
            ];
        }
        return $out;
    }

    /**
     * Guarda la configuración de un módulo para la empresa.
     *
     * @param array $valores  clave => valor de la empresa. Una clave ausente conserva
     *                        su valor actual.
     * @param array $clientes clave => [id_cliente => valor], solo opciones 'por_cliente'.
     *                        Lo recibido para una clave REEMPLAZA a lo guardado: un
     *                        cliente que ya no viene vuelve a usar el valor de la
     *                        empresa. Una clave ausente deja sus clientes como están.
     */
    public function guardar(int $idEmpresa, int $idActor, string $modulo, array $valores, array $clientes): void
    {
        $this->rules->validarModulo($modulo);
        if (!$this->repository->disponible()) {
            throw new Exception('Falta ejecutar database/2026-11-24_config_modulos.sql en la base de datos.');
        }

        // Normalizar y validar todo ANTES de abrir la transacción.
        $nuevosEmpresa = [];
        foreach ($valores as $clave => $valor) {
            $nuevosEmpresa[(string) $clave] = $this->rules->normalizarValor($modulo, (string) $clave, $valor);
        }

        $porClave = [];
        foreach ($clientes as $clave => $mapa) {
            $clave = (string) $clave;
            $this->rules->validarPorCliente($modulo, $clave);
            $porClave[$clave] = [];
            foreach ((array) $mapa as $idCli => $valor) {
                $idCli = (int) $idCli;
                if ($idCli > 0) {
                    $porClave[$clave][$idCli] = $this->rules->normalizarValor($modulo, $clave, $valor);
                }
            }
            $ids = array_keys($porClave[$clave]);
            $this->rules->validarClientes($ids, $this->repository->clientesValidos($idEmpresa, $ids));
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->lock($idEmpresa, $modulo);

            // 1) Fila de la empresa.
            if ($nuevosEmpresa) {
                $fila    = $this->repository->getFilaEmpresa($idEmpresa, $modulo);
                $antes   = $fila['opciones'] ?? [];
                $despues = array_merge($antes, $nuevosEmpresa);
                if (!$fila) {
                    $id = $this->repository->crear($idEmpresa, $modulo, null, $despues, $idActor);
                    $this->logService->registrar($idActor, $idEmpresa, 'crear', self::TABLA, $id, null,
                        ['modulo' => $modulo, 'opciones' => $despues]);
                } elseif ($despues != $antes) {
                    $this->repository->actualizar((int) $fila['id'], $idEmpresa, $despues, $idActor);
                    $this->logService->registrar($idActor, $idEmpresa, 'actualizar', self::TABLA, (int) $fila['id'],
                        ['modulo' => $modulo, 'opciones' => $antes], ['modulo' => $modulo, 'opciones' => $despues]);
                }
            }

            // 2) Filas de clientes (solo las opciones cuya lista llegó).
            if ($porClave) {
                $existentes = [];
                foreach ($this->repository->getFilasClientes($idEmpresa, $modulo) as $fc) {
                    $existentes[(int) $fc['id_cliente']] = $fc;
                }

                // Clientes a revisar: los que ya tenían fila y los que llegan.
                $idsRevisar = array_keys($existentes);
                foreach ($porClave as $mapa) {
                    $idsRevisar = array_merge($idsRevisar, array_keys($mapa));
                }

                foreach (array_unique($idsRevisar) as $idCli) {
                    $fila    = $existentes[$idCli] ?? null;
                    $antes   = $fila['opciones'] ?? [];
                    $despues = $antes;
                    foreach ($porClave as $clave => $mapa) {
                        if (array_key_exists($idCli, $mapa)) {
                            $despues[$clave] = $mapa[$idCli];
                        } else {
                            unset($despues[$clave]);
                        }
                    }
                    if ($despues == $antes) {
                        continue;
                    }

                    $datosAntes   = ['modulo' => $modulo, 'id_cliente' => $idCli, 'opciones' => $antes];
                    $datosDespues = ['modulo' => $modulo, 'id_cliente' => $idCli, 'opciones' => $despues];
                    if (!$fila) {
                        $id = $this->repository->crear($idEmpresa, $modulo, $idCli, $despues, $idActor);
                        $this->logService->registrar($idActor, $idEmpresa, 'crear', self::TABLA, $id, null, $datosDespues);
                    } elseif (!$despues) {
                        $this->repository->eliminar((int) $fila['id'], $idEmpresa, $idActor);
                        $this->logService->registrar($idActor, $idEmpresa, 'eliminar', self::TABLA, (int) $fila['id'], $datosAntes, null);
                    } else {
                        $this->repository->actualizar((int) $fila['id'], $idEmpresa, $despues, $idActor);
                        $this->logService->registrar($idActor, $idEmpresa, 'actualizar', self::TABLA, (int) $fila['id'], $datosAntes, $datosDespues);
                    }
                }
            }

            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /** Clientes para el buscador de la pestaña (id, nombre, identificación). */
    public function buscarClientes(int $idEmpresa, string $termino): array
    {
        $rows = (new \App\repositories\modulos\ClienteRepository())->buscarAutocomplete($idEmpresa, $termino, 15, false);
        return array_map(fn($r) => [
            'id'             => (int) $r['id'],
            'nombre'         => (string) $r['nombre'],
            'identificacion' => (string) $r['identificacion'],
        ], $rows);
    }
}
