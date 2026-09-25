<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\AlumnoRepository;
use App\repositories\modulos\SuscripcionesRepository;
use App\Rules\modulos\AlumnoRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Facturación de los servicios de un alumno desde su ficha.
 *
 * Todos los servicios van al cliente que factura (pestaña Facturación). Se
 * agrupa igual por cliente para que el flujo no cambie si algún día vuelve a
 * haber más de uno. El documento lo arma el mismo
 * generador de Suscripciones (SuscripcionFacturacionService::generarUnPeriodo):
 * secuencial con candado, IVA según el modo del establecimiento, factura en
 * borrador y XML — no se duplica esa lógica aquí.
 */
class AlumnoFacturacionService
{
    public function __construct(
        private AlumnoRepository $repository,
        private SuscripcionFacturacionService $generador,
        private LogSistemaService $logService
    ) {}

    /** Filas por defecto de la información adicional si el alumno no tiene propias. */
    public const INFO_ADICIONAL_DEFECTO = [['concepto' => 'Alumno', 'detalle' => '{alumno}']];

    /**
     * Marcadores propios del alumno. Los del mes ({mes}, {MES}, {anio}…) los
     * resuelve después el generador de Suscripciones con el período facturado.
     */
    private function marcadoresAlumno(array $alumno, int $idEmpresa): array
    {
        $vigente = null;
        foreach ($this->repository->getPeriodos((int) $alumno['id'], $idEmpresa) as $p) {
            if (empty($p['fecha_salida'])) { $vigente = $p; break; }
            $vigente = $vigente ?? $p; // si no hay vigente, el más reciente
        }
        return [
            '{alumno}'    => trim(($alumno['apellidos'] ?? '') . ' ' . ($alumno['nombres'] ?? '')),
            // Niño / Niña según el sexo guardado (O o sin dato: Niño/a).
            '{niño}'      => match ($alumno['sexo'] ?? '') { 'M' => 'Niño', 'F' => 'Niña', default => 'Niño/a' },
            '{nino}'      => match ($alumno['sexo'] ?? '') { 'M' => 'Niño', 'F' => 'Niña', default => 'Niño/a' },
            '{nombres}'   => trim((string) ($alumno['nombres'] ?? '')),
            '{apellidos}' => trim((string) ($alumno['apellidos'] ?? '')),
            '{cedula}'    => trim((string) ($alumno['numero_identificacion'] ?? '')),
            '{campus}'    => trim((string) ($vigente['campus_nombre'] ?? '')),
            '{curso}'     => trim((string) ($vigente['nivel_nombre'] ?? '')),
        ];
    }

    /** Filas concepto/detalle guardadas en el alumno (NULL = las de por defecto). */
    private function infoAdicionalAlumno(array $alumno): array
    {
        $raw = $alumno['info_adicional'] ?? null;
        if ($raw === null || !array_key_exists('info_adicional', $alumno)) {
            return self::INFO_ADICIONAL_DEFECTO;
        }
        $filas = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($filas) ? $filas : self::INFO_ADICIONAL_DEFECTO;
    }

    /** Mes 'YYYY-MM' → fecha del primer día 'YYYY-MM-01'. */
    public static function periodoAFecha(string $mes): string
    {
        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mes, $m)) {
            throw new Exception('El período debe ser un mes válido (año y mes).');
        }
        return "{$m[1]}-{$m[2]}-01";
    }

    private function exigirEsquema(): void
    {
        if (!$this->repository->existeEsquemaFacturacion()) {
            throw new Exception('Falta ejecutar en la base el script database/migrations/20260924_alumnos_facturacion.sql para facturar desde el alumno.');
        }
    }

    /**
     * Lo que se facturaría en el mes: líneas activas con su cliente (todas
     * marcadas; el usuario desmarca lo que no corresponda) y avisos de mes ya
     * facturado a ese cliente.
     */
    public function preparar(int $idAlumno, int $idEmpresa, string $mes): array
    {
        $this->exigirEsquema();
        $alumno = $this->repository->findById($idAlumno, $idEmpresa);
        if (!$alumno) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }
        $periodo = self::periodoAFecha($mes);

        $marcas = $this->marcadoresAlumno($alumno, $idEmpresa);
        $lineas = [];
        foreach ($this->repository->getServiciosParaFacturar($idAlumno, $idEmpresa) as $s) {
            $precio = $s['precio_override'] !== null ? (float) $s['precio_override'] : (float) $s['precio_base'];
            $cant   = (float) $s['cantidad_default'];
            $base   = round($cant * $precio, 2);
            $iva    = round($base * ((float) $s['porcentaje_iva'] / 100), 2);
            $lineas[] = [
                'id'              => (int) $s['id'],
                'producto'        => $s['nombre_producto'],
                'cantidad'        => $cant,
                'precio'          => $precio,
                'porcentaje_iva'  => (float) $s['porcentaje_iva'],
                'total'           => round($base + $iva, 2),
                'id_cliente'      => (int) $s['id_cliente'],
                'cliente'         => $s['cliente_nombre'],
                'cliente_identificacion' => $s['cliente_identificacion'],
                'detalle'         => strtr(trim((string) ($s['detalle'] ?? '')), $marcas),
                'marcada'         => $base > 0,
                'aviso'           => null,
            ];
        }

        $avisos = [];
        foreach ($this->repository->getFacturasAlumno($idAlumno, $idEmpresa, $periodo) as $f) {
            $avisos[] = [
                'id_cliente' => (int) $f['id_cliente'],
                'texto'      => sprintf(
                    '%s ya se facturó a %s (factura %s-%s-%s, %s).',
                    date('m-Y', strtotime($f['periodo'])),
                    $f['cliente_nombre'] ?? 'cliente',
                    $f['establecimiento'], $f['punto_emision'], $f['secuencial'],
                    $f['estado_factura']
                ),
            ];
        }

        return [
            'lineas'            => $lineas,
            'avisos'            => $avisos,
            'id_punto_emision'  => $alumno['id_punto_emision'] ? (int) $alumno['id_punto_emision'] : null,
        ];
    }

    /**
     * Genera las facturas del mes para las líneas elegidas (ids de alumnos_servicios).
     * Cada cliente es una factura independiente: si una falla, las demás siguen y
     * se informa cuál falló (mismo criterio que la generación de Suscripciones).
     *
     * @return array{generadas: array, errores: array}
     */
    public function generar(
        int $idAlumno,
        int $idEmpresa,
        int $idUsuario,
        int $idPuntoEmision,
        string $mes,
        array $idsLineas,
        string $textoItem = ''
    ): array {
        $this->exigirEsquema();
        $alumno = $this->repository->findById($idAlumno, $idEmpresa);
        if (!$alumno) {
            throw new Exception('El alumno no existe o ha sido eliminado.');
        }
        $periodo = self::periodoAFecha($mes);
        if ($idPuntoEmision <= 0) {
            throw new Exception('Seleccione la serie (punto de emisión) de la factura.');
        }
        $idsLineas = array_values(array_unique(array_filter(array_map('intval', $idsLineas))));
        if (!$idsLineas) {
            throw new Exception('Seleccione al menos un servicio o producto para facturar.');
        }
        if (mb_strlen($textoItem) > 300) {
            throw new Exception('El texto para los ítems no puede exceder 300 caracteres.');
        }

        $suscRepo    = new SuscripcionesRepository();
        $estabConfig = $suscRepo->getEstablecimientoPorPunto($idEmpresa, $idPuntoEmision);
        if (!$estabConfig) {
            throw new Exception('La serie seleccionada no es válida o está inactiva.');
        }
        $empresaConfig = (new \App\models\Empresa())->getPorId($idEmpresa);
        if (empty($empresaConfig)) {
            throw new Exception('No hay configuración de empresa.');
        }

        $marcas = $this->marcadoresAlumno($alumno, $idEmpresa);

        // Agrupar por cliente solo las líneas pedidas que siguen activas y válidas.
        $grupos = [];
        $encontradas = 0;
        foreach ($this->repository->getServiciosParaFacturar($idAlumno, $idEmpresa) as $s) {
            if (!in_array((int) $s['id'], $idsLineas, true)) {
                continue;
            }
            $encontradas++;
            $precio = $s['precio_override'] !== null ? (float) $s['precio_override'] : (float) $s['precio_base'];
            $idCli  = (int) $s['id_cliente'];
            $grupos[$idCli]['cliente'] = $s;
            $grupos[$idCli]['detalle'][] = [
                'id_producto'       => (int) $s['id_producto'],
                'codigo_producto'   => $s['codigo_producto'],
                'nombre_producto'   => $s['nombre_producto'],
                'descripcion'       => $s['nombre_producto'],
                'cantidad'          => (float) $s['cantidad_default'],
                'precio_unitario'   => $precio,
                'porcentaje_iva'    => (float) $s['porcentaje_iva'],
                'codigo_porcentaje' => $s['codigo_porcentaje'],
                'id_tarifa_iva'     => $s['id_tarifa_iva'],
                // Texto del ítem propio de la línea; vacío = el texto general del lote.
                'info_item'         => strtr(trim((string) ($s['detalle'] ?? '')), $marcas),
            ];
            $grupos[$idCli]['items'][] = ['id_producto' => (int) $s['id_producto']];
        }
        if ($encontradas !== count($idsLineas)) {
            throw new Exception('Los servicios del alumno cambiaron (o alguno se desactivó). Cierre esta ventana y vuelva a abrirla.');
        }

        $infoAdicional = array_map(fn($f) => [
            'concepto' => strtr(trim((string) ($f['concepto'] ?? '')), $marcas),
            'detalle'  => strtr(trim((string) ($f['detalle'] ?? '')), $marcas),
        ], $this->infoAdicionalAlumno($alumno));
        $textoItem = strtr($textoItem, $marcas);
        $generadas = [];
        $errores   = [];

        foreach ($grupos as $idCli => $g) {
            $cli = $g['cliente'];
            try {
                // Se valida aquí el monto para dar un mensaje propio del alumno
                // (el generador hablaría de "suscripción").
                $monto = 0.0;
                foreach ($g['detalle'] as $d) {
                    $monto += round($d['cantidad'] * $d['precio_unitario'], 2);
                }
                if ($monto <= 0) {
                    throw new Exception('los servicios elegidos no tienen monto (precio en cero).');
                }

                // "Pseudo-suscripción" con lo que el generador necesita.
                $doc = [
                    'id'                  => $idAlumno,
                    'id_cliente'          => $idCli,
                    'tipo_comprobante'    => 'factura',
                    'forma_cobro'         => '',
                    'cliente_email'       => $cli['cliente_email'] ?? '',
                    'periodicidad_meses'  => 1,
                    'periodicidad_codigo' => 'MENSUAL',
                    'info_adicional'      => $infoAdicional,
                ];
                $res = $this->generador->generarUnPeriodo(
                    $idEmpresa, $idUsuario, $doc, $g['detalle'], $estabConfig, $empresaConfig,
                    ['texto_item' => $textoItem], $periodo
                );

                $this->repository->registrarFacturaAlumno([
                    'id_empresa' => $idEmpresa,
                    'id_alumno'  => $idAlumno,
                    'id_cliente' => $idCli,
                    'id_factura' => (int) $res['id_factura'],
                    'periodo'    => $periodo,
                    'importe'    => $res['importe'],
                    'items'      => $g['items'],
                    'id_usuario' => $idUsuario,
                ]);

                $this->logService->registrar($idUsuario, $idEmpresa, 'generar_factura', 'alumnos', $idAlumno, null, [
                    'id_factura' => $res['id_factura'],
                    'id_cliente' => $idCli,
                    'periodo'    => $periodo,
                    'importe'    => $res['importe'],
                    'items'      => $g['items'],
                ]);

                $generadas[] = [
                    'id_factura' => (int) $res['id_factura'],
                    'cliente'    => $cli['cliente_nombre'],
                    'importe'    => $res['importe'],
                ];
            } catch (\Throwable $e) {
                \App\Services\ErrorLogService::registrar($e, ['ruta' => static::class, 'accion' => 'generar#alumno_' . $idAlumno . '_cliente_' . $idCli]);
                $errores[] = ($cli['cliente_nombre'] ?? "Cliente {$idCli}") . ': ' . $e->getMessage();
            }
        }

        return ['generadas' => $generadas, 'errores' => $errores];
    }

    public function getFacturas(int $idAlumno, int $idEmpresa): array
    {
        if (!$this->repository->existeEsquemaFacturacion()) {
            return [];
        }
        $facturas = $this->repository->getFacturasAlumno($idAlumno, $idEmpresa);
        $lineas = $this->repository->getLineasFacturas(array_column($facturas, 'id_factura'), $idEmpresa);
        foreach ($facturas as &$f) {
            $f['lineas'] = $lineas[(int) $f['id_factura']] ?? [];
        }
        unset($f);
        return $facturas;
    }
}
