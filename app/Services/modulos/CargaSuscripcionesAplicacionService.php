<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Services\LogSistemaService;

/**
 * Aplica una carga de suscripciones ya validada.
 *
 * Escribe a través de SuscripcionesService::crear() para conservar sus reglas de
 * negocio, su transacción y su auditoría. Se procesa suscripción a suscripción,
 * cada una con su propia transacción (la abre SuscripcionesService): un fallo
 * aislado no tumba la carga completa (aplicación parcial).
 */
class CargaSuscripcionesAplicacionService
{
    private SuscripcionesService $suscripcionesService;
    private LogSistemaService $logService;

    public function __construct(
        SuscripcionesService $suscripcionesService,
        LogSistemaService $logService
    ) {
        $this->suscripcionesService = $suscripcionesService;
        $this->logService           = $logService;
    }

    /**
     * @param array $informe Salida de CargaSuscripcionesValidacionService::validar().
     */
    public function aplicar(array $informe, int $idEmpresa, int $idUsuario): array
    {
        $resultado = [
            'creadas'  => 0,
            'omitidas' => 0,
            'fallidas' => 0,
            'detalle'  => [],
        ];

        foreach ($informe['suscripciones'] ?? [] as $s) {
            // Las bloqueadas (con errores) se omiten e informan.
            if (!empty($s['errores'])) {
                $resultado['omitidas']++;
                $resultado['detalle'][] = [
                    'codigo'  => $s['clave'],
                    'estado'  => 'omitida',
                    'mensaje' => 'Tiene errores de validación.',
                ];
                continue;
            }

            try {
                $this->suscripcionesService->crear($this->construirDatos($s, $idEmpresa, $idUsuario));
                $resultado['creadas']++;
                $resultado['detalle'][] = [
                    'codigo'  => $s['clave'],
                    'estado'  => 'creada',
                    'mensaje' => '',
                ];
            } catch (\Throwable $e) {
                $resultado['fallidas']++;
                $resultado['detalle'][] = [
                    'codigo'  => $s['clave'],
                    'estado'  => 'error',
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        $this->logService->registrar(
            $idUsuario,
            $idEmpresa,
            'carga_masiva_excel',
            'suscripciones',
            null,
            null,
            [
                'creadas'  => $resultado['creadas'],
                'omitidas' => $resultado['omitidas'],
                'fallidas' => $resultado['fallidas'],
            ]
        );

        return $resultado;
    }

    /** Arma el array que espera SuscripcionesService::crear(). */
    private function construirDatos(array $s, int $idEmpresa, int $idUsuario): array
    {
        $infoAdicional = [];
        if ($s['info_concepto'] !== '' && $s['info_detalle'] !== '') {
            $infoAdicional[] = ['concepto' => $s['info_concepto'], 'detalle' => $s['info_detalle']];
        }

        $detalle = array_map(static fn($d) => [
            'id_producto'     => $d['id_producto'],
            'descripcion'     => $d['descripcion'],
            'cantidad'        => $d['cantidad'],
            'precio_unitario' => $d['precio_unitario'],
            'id_tarifa_iva'   => $d['id_tarifa_iva'],
            'porcentaje_iva'  => $d['porcentaje_iva'],
        ], $s['detalle']);

        return [
            'id_empresa'       => $idEmpresa,
            'id_usuario'       => $idUsuario,
            'id_cliente'       => $s['id_cliente'],
            'id_periodicidad'  => $s['id_periodicidad'],
            'fecha_inicio'     => $s['fecha_inicio'],
            'fecha_fin'        => $s['fecha_fin'] !== '' ? $s['fecha_fin'] : null,
            // crear() usa proximo_cobro tal cual; si viene vacío tomamos la fecha de inicio.
            'proximo_cobro'    => $s['proximo_cobro'] !== '' ? $s['proximo_cobro'] : $s['fecha_inicio'],
            'forma_cobro'      => $s['forma_cobro'],
            'estado'           => $s['estado'],
            'tipo_comprobante' => $s['tipo_comprobante'],
            'observaciones'    => $s['observaciones'],
            'info_adicional'   => $infoAdicional,
            'detalle'          => $detalle,
        ];
    }
}
