<?php
namespace App\Services\modulos\Handlers;

use App\Services\modulos\SriDescargaAutomaticaService;

class DescargasSriHandler extends BaseHandler
{
    /**
     * @param int $idEmpresa ID de la empresa
     * @param int|null $idEstablecimiento No aplica
     * @param int $idUsuario ID del usuario que ejecuta (0 si es cron)
     * @param array $parametros Configuración ingresada en la UI
     * @return array
     */
    public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array
    {
        // Suspensión reversible del modo automático (evita bloqueos del usuario en el SRI).
        // Se reemplaza por la "descarga asistida" (visor remoto + humano). Palanca en config/app.php.
        $appCfg = is_file(MVC_CONFIG . '/app.php') ? require MVC_CONFIG . '/app.php' : [];
        if (!empty($appCfg['sri_descarga_auto_suspendida'])) {
            return [
                'ok'        => true, // queda registrado como ejecutado, sin error y sin descargar
                'mensaje'   => 'Descarga automática SUSPENDIDA. Use la descarga asistida (visor remoto) desde el módulo Descargas SRI.',
                'detalles'  => 'Modo automático desactivado por configuración (sri_descarga_auto_suspendida).',
                'registros' => 0,
            ];
        }

        $hoy = new \DateTime();
        $mesesAEjecutar = [];
        
        // El cliente solicita: no ejecutar muchas veces lo mismo para evitar bloqueos del SRI.
        // En días siempre se selecciona TODOS (dia = 0).
        // Se descarga siempre el mes actual.
        // Si la fecha actual es el día 1 del mes, TAMBIÉN se descarga el mes anterior.
        
        // 1. Siempre el mes actual
        $mesesAEjecutar[] = [
            'ano' => (int)$hoy->format('Y'),
            'mes' => (int)$hoy->format('n'),
            'dia' => 0
        ];

        // 2. Si hoy es el día 1, agregamos el mes anterior
        if ((int)$hoy->format('j') === 1) {
            $mesAnterior = (clone $hoy)->modify('-1 month');
            $mesesAEjecutar[] = [
                'ano' => (int)$mesAnterior->format('Y'),
                'mes' => (int)$mesAnterior->format('n'),
                'dia' => 0
            ];
        }

        $service = new SriDescargaAutomaticaService();
        
        $totalNuevos = 0;
        $totalErrores = 0;
        $detalles = [];

        foreach ($mesesAEjecutar as $periodo) {
            // Se ejecuta de manera silenciosa (background) ya que ejecutarParaEmpresa no hace flush() directo
            $resultado = $service->ejecutarParaEmpresa(
                $idEmpresa,
                $idUsuario,
                $periodo['ano'],
                $periodo['mes'],
                $periodo['dia'],
                null // null fuerza a usar la configuración global guardada de la BD (tipos de doc)
            );

            if (!$resultado['ok']) {
                $totalErrores++;
                $msgError = $resultado['error'] ?? 'Desconocido';
                $detalles[] = "Error en periodo {$periodo['ano']}/{$periodo['mes']}: {$msgError}";
                
                // Si la clave SRI está mal (login bloqueado), no tiene sentido continuar
                if (!empty($resultado['login_bloqueado'])) {
                    break;
                }
            } else {
                $nuevos = $resultado['total_nuevos'] ?? 0;
                $totalNuevos += $nuevos;
                $detalles[] = "Éxito periodo {$periodo['ano']}/{$periodo['mes']}: {$nuevos} nuevos comprobantes descargados.";
            }
        }

        $msgFinal = $totalErrores > 0 
            ? "Finalizado con errores. Nuevos registrados: $totalNuevos"
            : "Descarga exitosa. Nuevos registrados: $totalNuevos";

        return [
            'ok'        => true, // Devolvemos true para que quede registrado en el log
            'mensaje'   => $msgFinal,
            'detalles'  => implode("\n", $detalles),
            'registros' => $totalNuevos
        ];
    }
}
