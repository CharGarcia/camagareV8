<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\repositories\modulos\SuscripcionesRepository;
use App\repositories\modulos\FacturaVentaRepository;
use App\Rules\modulos\FacturaVentaRules;
use App\Services\LogSistemaService;
use App\Services\SecuencialService;
use App\Services\modulos\FacturaVentaService;
use App\Services\modulos\SuscripcionFacturacionService;
use App\Services\modulos\SuscripcionesService;
use App\Rules\modulos\SuscripcionesRules;

class SuscripcionesHandler extends BaseHandler
{
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        return match ($this->accion) {
            'generar_facturacion'      => $this->generarFacturacion($idEmpresa, $idUsuario, $parametros),
            'enviar_aviso_vencimiento' => $this->noImplementado('Enviar aviso de vencimiento'),
            default                    => throw new \RuntimeException("Acción '{$this->accion}' no implementada en SuscripcionesHandler."),
        };
    }

    // ── Generar facturación ───────────────────────────────────────────────────
    // Solo crea las facturas (estado borrador). El SRI lo envía la automatización
    // separada de "Facturas de venta → Enviar al SRI".
    private function generarFacturacion(int $idEmpresa, int $idUsuario, array $p): array
    {
        $idPuntoEmision = (int)($p['id_punto_emision'] ?? 0);
        if ($idPuntoEmision <= 0) {
            return ['registros' => 0, 'mensaje' => 'No se configuró la serie (punto de emisión) en la automatización.'];
        }

        $suscRepo = new SuscripcionesRepository();

        // Serie elegida
        $estabConfig = $suscRepo->getEstablecimientoPorPunto($idEmpresa, $idPuntoEmision);
        if (!$estabConfig) {
            return ['registros' => 0, 'mensaje' => 'La serie configurada no es válida o está inactiva.'];
        }

        // Config de empresa
        $empresaConfig = (new \App\models\Empresa())->getPorId($idEmpresa);
        if (empty($empresaConfig)) {
            return ['registros' => 0, 'mensaje' => 'No hay configuración de empresa.'];
        }

        // Suscripciones con períodos vencidos
        $vencidas = $suscRepo->getVencidasPorEmpresa($idEmpresa);
        if (empty($vencidas)) {
            return ['registros' => 0, 'mensaje' => 'No hay suscripciones con períodos pendientes de facturar.'];
        }

        $facturacion = new SuscripcionFacturacionService(
            new FacturaVentaService(new FacturaVentaRepository(), new FacturaVentaRules(), new LogSistemaService()),
            new SecuencialService()
        );
        $suscService = new SuscripcionesService($suscRepo, new SuscripcionesRules(), new LogSistemaService());

        $extras = [
            'texto_item'    => trim($p['texto_item']    ?? ''),
            'info_concepto' => trim($p['info_concepto'] ?? ''),
            'info_detalle'  => trim($p['info_detalle']  ?? ''),
        ];

        $hoy       = date('Y-m-d');
        $generadas = 0;
        $errores   = 0;

        foreach ($vencidas as $susc) {
            $idSusc  = (int)$susc['id'];
            $detalle = $suscRepo->getDetalle($idSusc);
            if (empty($detalle)) {
                continue;
            }

            $proximo  = (string)$susc['proximo_cobro'];
            $fechaFin = $susc['fecha_fin'] ?? null;
            $meses    = (int)($susc['periodicidad_meses'] ?? 1);
            $codigo   = (string)($susc['periodicidad_codigo'] ?? '');

            // Bucle de "ponerse al día": una factura por cada período vencido,
            // sin pasar la fecha_fin de la suscripción.
            while ($proximo <= $hoy && ($fechaFin === null || $proximo <= $fechaFin)) {
                // Calcular el siguiente período ANTES de crear la factura.
                // Protección anti-bucle: la fecha SIEMPRE debe avanzar.
                $nuevoProximo = $suscService->calcularProximoCobro($proximo, $meses, $codigo);
                if ($nuevoProximo <= $proximo) {
                    $errores++;
                    break;
                }

                try {
                    // 1. Crear la factura (FacturaVentaService maneja su propia transacción)
                    //    $proximo = fecha del período facturado → alimenta los placeholders {mes}, {anio}, etc.
                    $res = $facturacion->generarUnPeriodo(
                        $idEmpresa, $idUsuario, $susc, $detalle, $estabConfig, $empresaConfig, $extras, $proximo
                    );

                    // 2. Avanzar el próximo cobro
                    $suscRepo->updateProximoCobro($idSusc, $nuevoProximo);

                    // 3. Registrar el pago/factura generada
                    $suscRepo->insertPago([
                        'id_suscripcion' => $idSusc,
                        'id_empresa'     => $idEmpresa,
                        'id_factura'     => $res['id_factura'],
                        'fecha_cobro'    => $hoy,
                        'monto'          => $res['importe'],
                        'estado'         => 'exitoso',
                        'id_usuario'     => $idUsuario,
                    ]);

                    $generadas++;
                    $proximo = $nuevoProximo;
                } catch (\Throwable $e) {
                    // La factura falló: NO se avanzó la fecha → se reintenta en la próxima corrida.
                    $errores++;
                    break; // pasar a la siguiente suscripción
                }
            }
        }

        $msg = "Se generaron {$generadas} factura(s) de suscripciones.";
        if ($errores > 0) $msg .= " ({$errores} suscripción(es) con error)";
        return ['registros' => $generadas, 'mensaje' => $msg];
    }

    private function noImplementado(string $accion): array
    {
        return [
            'registros' => 0,
            'mensaje'   => "La acción \"{$accion}\" aún no está implementada. Pendiente de desarrollo.",
        ];
    }
}
