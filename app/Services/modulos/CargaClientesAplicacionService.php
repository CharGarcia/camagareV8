<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\LogSistemaService;

/**
 * Aplica una carga de clientes ya validada.
 *
 * Escribe a través de ClienteService::crear()/actualizar() para conservar sus
 * reglas, su transacción y su auditoría (cada cliente queda en log_sistema con
 * sus datos antes/después). Se procesa CLIENTE A CLIENTE, cada uno con su
 * transacción: BaseRepository::commit() confirmaría en el primero cualquier
 * transacción externa, y así un fallo aislado no tumba la carga completa.
 */
class CargaClientesAplicacionService
{
    private ClienteService $clienteService;
    private LogSistemaService $logService;

    public function __construct(ClienteService $clienteService, LogSistemaService $logService)
    {
        $this->clienteService = $clienteService;
        $this->logService     = $logService;
    }

    /**
     * @param array $informe Salida de CargaClientesValidacionService::validar().
     */
    public function aplicar(array $informe, int $idEmpresa, int $idUsuario): array
    {
        $resultado = [
            'creados'      => 0,
            'actualizados' => 0,
            'sin_cambios'  => 0,
            'omitidos'     => 0,
            'fallidos'     => 0,
            'detalle'      => [],
        ];

        foreach ($informe['clientes'] ?? [] as $c) {
            if (!empty($c['errores'])) {
                $resultado['omitidos']++;
                continue;
            }
            if ($c['accion'] === 'sin_cambios') {
                $resultado['sin_cambios']++;
                continue;
            }

            $datos = $c['datos'] + [
                'id_empresa' => $idEmpresa,
                'id_usuario' => $idUsuario,
            ];

            try {
                if ($c['accion'] === 'crear') {
                    $this->clienteService->crear($datos + [
                        'id_forma_cobro_predeterminada'          => null,
                        'tipo_operacion_bancaria_predeterminada' => null,
                        'monto_minimo_auto_cobro'                => null,
                        'monto_maximo_auto_cobro'                => null,
                        'id_ingreso_concepto_predeterminado'     => null,
                        'latitud'                                => null,
                        'longitud'                               => null,
                    ]);
                    $resultado['creados']++;
                } else {
                    $this->clienteService->actualizar((int) $c['id_cliente'], $idEmpresa, $c['conservar'] + $datos);
                    $resultado['actualizados']++;
                }
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'SQLSTATE')) {
                    // El usuario ve un mensaje simple; el detalle técnico queda en el registro de errores.
                    \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'aplicar', 'fila' => $c['fila']]);
                }
                $resultado['fallidos']++;
                $resultado['detalle'][] = [
                    'fila'           => $c['fila'],
                    'identificacion' => $c['identificacion'],
                    'nombre'         => $c['nombre'],
                    'mensaje'        => $this->mensajeLegible($e),
                ];
            }
        }

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'carga_masiva_excel',
            'clientes',
            null,
            null,
            [
                'modo'         => $informe['modo'] ?? 'completa',
                'columnas'     => $informe['parcial'] ?? null,
                'creados'      => $resultado['creados'],
                'actualizados' => $resultado['actualizados'],
                'sin_cambios'  => $resultado['sin_cambios'],
                'omitidos'     => $resultado['omitidos'],
                'fallidos'     => $resultado['fallidos'],
            ]
        );

        return $resultado;
    }

    /** Traduce los errores de Postgres más probables a algo que el usuario entienda. */
    private function mensajeLegible(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '23505')) {
            return 'Ya existe un cliente con esta identificación (quizá se registró mientras se aplicaba la carga).';
        }
        if (str_contains($msg, '22001')) {
            return 'Un texto excede el largo permitido por la base de datos.';
        }
        if (str_contains($msg, 'SQLSTATE')) {
            return 'No se pudo guardar por un error de base de datos.';
        }
        return $msg;
    }
}
