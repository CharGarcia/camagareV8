<?php
/**
 * Generación automática y SILENCIOSA de asientos contables al abrir un módulo.
 *
 * Cuando un usuario entra a Facturas de Venta (o Compras, o Ingresos…), el
 * sistema revisa por su cuenta si a esa empresa le faltan asientos de ESE
 * módulo y los genera en segundo plano. El usuario no ve nada: ni diálogos, ni
 * barras de progreso, ni avisos. Lo que no se pueda generar queda registrado y
 * se sigue reportando donde sí corresponde avisar — la pantalla de Asientos
 * Contables, Mayores y Estados Financieros, que conservan su diálogo con el
 * detalle de qué cuenta falta (ver public/js/modulos/asientos_pendientes.js).
 *
 * Cuatro reglas gobiernan la pasada, y las cuatro existen para que un
 * automatismo que corre en cada carga de página no se convierta en un problema:
 *
 *   1. COMPUERTA. Si la empresa no tiene ninguna cuenta configurada para ese
 *      módulo, no se hace nada. Es la primera consulta, y la más barata.
 *   2. CANDADO. Dos usuarios abriendo la misma pantalla a la vez no procesan
 *      los mismos documentos. Quien no obtiene el candado se retira sin esperar.
 *   3. TOPE. Como máximo TOPE_POR_PASADA documentos por vez, del más antiguo al
 *      más nuevo. Si quedan más, la siguiente entrada al módulo continúa —
 *      y en ese caso no se aplica el throttle, para no obligar al usuario a
 *      recargar N veces.
 *   4. FALLOS QUE NO SE REINTENTAN. Un documento sin cuenta configurada, o con
 *      el período contable cerrado, va a fallar igual las próximas mil veces.
 *      Se registra con la firma de la configuración vigente y se salta hasta que
 *      esa configuración cambie.
 *
 * La detección de "a este documento le falta el asiento" y la generación en sí
 * NO se reimplementan aquí: son las de SincronizadorAsientosService, el mismo
 * que usa la sincronización manual. Una sola fuente de verdad.
 */

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ContabilidadModulos;
use App\repositories\modulos\ContabilidadAutoRepository;
use App\Services\ErrorLogService;
use App\Services\LogSistemaService;

class ContabilidadAutoService
{
    /** Máximo de documentos por pasada. Un servidor chico no debe atragantarse al abrir un módulo. */
    public const TOPE_POR_PASADA = 50;

    /** Si no cambió la configuración y no quedó trabajo pendiente, no se repite la pasada tan seguido. */
    private const THROTTLE_SEGUNDOS = 60;

    public function __construct(
        private ContabilidadAutoRepository $repo,
        private SincronizadorAsientosService $sincronizador,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(
            new ContabilidadAutoRepository(),
            new SincronizadorAsientosService(),
            new LogSistemaService()
        );
    }

    /**
     * Procesa todos los trabajos que dispara una ruta MVC.
     *
     * @return array Resumen por módulo. Es solo para el log y para las pruebas:
     *               la vista lo descarta, porque este flujo no habla con el usuario.
     */
    public function generarPorRuta(int $idEmpresa, int $idUsuario, string $rutaMvc): array
    {
        $resultado = [];
        foreach (ContabilidadModulos::clavesPorRuta($rutaMvc) as $clave) {
            $resultado[$clave] = $this->generarModulo($idEmpresa, $idUsuario, $clave);
        }
        return $resultado;
    }

    /**
     * Una pasada sobre un módulo. Nunca lanza excepción hacia afuera: si algo
     * falla, se registra y se devuelve el motivo. Un problema contabilizando no
     * puede impedirle al usuario abrir su módulo.
     */
    public function generarModulo(int $idEmpresa, int $idUsuario, string $clave): array
    {
        $definicion = ContabilidadModulos::definicion($clave);
        if ($definicion === null) {
            return ['omitido' => 'módulo no declarado en config/contabilidad_modulos.php'];
        }

        try {
            // 1. Compuerta: ¿hay configuración contable para este módulo?
            $hashConfig = $this->repo->firmaConfiguracion($idEmpresa, $definicion);
            if ($hashConfig === null) {
                return ['omitido' => 'sin configuración contable'];
            }

            // 2. Throttle: misma configuración, sin trabajo pendiente y recién corrido.
            if ($this->debeEsperar($idEmpresa, $clave, $hashConfig)) {
                return ['omitido' => 'ejecutado hace poco'];
            }

            // 3. Candado por empresa+módulo.
            if (!$this->repo->intentarCandado($idEmpresa, $clave)) {
                return ['omitido' => 'otra sesión lo está generando'];
            }

            try {
                return $this->procesar($idEmpresa, $idUsuario, $clave, $definicion, $hashConfig);
            } finally {
                $this->repo->liberarCandado($idEmpresa, $clave);
            }
        } catch (\Throwable $e) {
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'generarModulo', 'modulo' => $clave]);
            return ['error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function debeEsperar(int $idEmpresa, string $clave, string $hashConfig): bool
    {
        $estado = $this->repo->getEstado($idEmpresa, $clave);
        if ($estado === null || empty($estado['ultima_corrida'])) {
            return false;
        }

        // La configuración cambió: hay que reintentar ya (incluidos los fallidos).
        if (($estado['ultimo_hash_config'] ?? null) !== $hashConfig) {
            return false;
        }

        // Quedó trabajo a medias por el tope: seguir de inmediato.
        if (!empty($estado['quedan_pendientes']) && $estado['quedan_pendientes'] !== 'f') {
            return false;
        }

        $ultima = strtotime((string) $estado['ultima_corrida']);
        return $ultima !== false && (time() - $ultima) < self::THROTTLE_SEGUNDOS;
    }

    private function procesar(int $idEmpresa, int $idUsuario, string $clave, array $definicion, string $hashConfig): array
    {
        $trabajo = $this->sincronizador->getTrabajoPorClave($idEmpresa, $clave);
        if ($trabajo === null) {
            return ['omitido' => 'sin trabajo en SincronizadorAsientosService'];
        }

        try {
            $ids = $this->repo->idsPendientes(
                $trabajo['sql'],
                $trabajo['params'],
                $idEmpresa,
                $clave,
                $hashConfig,
                self::TOPE_POR_PASADA
            );
        } catch (\Throwable $e) {
            // Tabla o columna inexistente (migración pendiente en esta base): se omite el módulo
            // sin romper nada, igual que la sincronización manual. Se guarda estado igualmente
            // para que el throttle evite repetir una consulta que va a fallar en cada carga de
            // página hasta que se aplique la migración.
            $this->repo->guardarEstado($idEmpresa, $clave, $hashConfig, 0, 0, false, $idUsuario);
            error_log("[ContabilidadAuto] {$clave} (empresa {$idEmpresa}): detección no disponible — " . $e->getMessage());
            return ['omitido' => 'detección no disponible: ' . $e->getMessage()];
        }

        if ($ids === []) {
            $this->repo->guardarEstado($idEmpresa, $clave, $hashConfig, 0, 0, false, $idUsuario);
            return ['generados' => 0, 'fallidos' => 0, 'pendientes' => 0];
        }

        $service = ($trabajo['factory'])();
        if (!method_exists($service, 'procesarAsientoContablePorSincronizacion')) {
            return ['omitido' => 'el service del módulo no expone la generación por sincronización'];
        }

        $intentados = [];
        $fallidos   = 0;

        foreach ($ids as $id) {
            try {
                $service->procesarAsientoContablePorSincronizacion($id);
                $intentados[] = $id;
            } catch (\Throwable $e) {
                $motivo = trim($e->getMessage()) !== '' ? trim($e->getMessage()) : get_class($e);
                $this->repo->registrarFallo($idEmpresa, $clave, $id, $motivo, $hashConfig, $idUsuario);
                $fallidos++;
            }
        }

        // Varios services retornan en silencio cuando el asiento queda vacío por falta de
        // reglas: sin esta comprobación esos documentos se contarían como generados y nadie
        // volvería a mirarlos. Mismo criterio que SincronizadorAsientosService.
        $sinAsiento = $this->repo->idsSinAsiento(
            (string) ($trabajo['tablaVerif'] ?? ''),
            (string) ($trabajo['colAsiento'] ?? 'id_asiento_contable'),
            $intentados
        );
        foreach ($sinAsiento as $id) {
            $this->repo->registrarFallo(
                $idEmpresa,
                $clave,
                $id,
                'El asiento quedó vacío: faltan cuentas configuradas para este documento.',
                $hashConfig,
                $idUsuario
            );
            $fallidos++;
        }

        $generadosIds = array_values(array_diff($intentados, $sinAsiento));
        $this->repo->limpiarFallos($idEmpresa, $clave, $generadosIds, $idUsuario);

        $quedanPendientes = count($ids) >= self::TOPE_POR_PASADA;
        $this->repo->guardarEstado($idEmpresa, $clave, $hashConfig, count($generadosIds), $fallidos, $quedanPendientes, $idUsuario);

        if ($generadosIds !== []) {
            $this->auditar($idEmpresa, $idUsuario, $clave, $definicion, $generadosIds, $fallidos);
        }
        if ($fallidos > 0) {
            error_log("[ContabilidadAuto] {$clave} (empresa {$idEmpresa}): {$fallidos} documento(s) sin asiento — ver contabilidad_auto_fallos");
        }

        return [
            'generados'  => count($generadosIds),
            'fallidos'   => $fallidos,
            'pendientes' => $quedanPendientes ? 1 : 0,
        ];
    }

    /**
     * Una línea en log_sistema por pasada con resultado, no una por documento:
     * el detalle de cada asiento ya lo registra el service del módulo al crearlo.
     */
    private function auditar(int $idEmpresa, int $idUsuario, string $clave, array $definicion, array $generadosIds, int $fallidos): void
    {
        try {
            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'GENERAR_ASIENTOS_AUTO',
                'contabilidad_auto_estado',
                null,
                null,
                [
                    'modulo'      => $definicion['nombre'] ?? $clave,
                    'clave'       => $clave,
                    'generados'   => count($generadosIds),
                    'fallidos'    => $fallidos,
                    'documentos'  => $generadosIds,
                ]
            );
        } catch (\Throwable $e) {
            // La auditoría nunca puede tumbar la generación.
            ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'auditar', 'modulo' => $clave]);
        }
    }
}
