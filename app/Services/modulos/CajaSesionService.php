<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DetalleImpuestos;
use App\models\Empresa;
use App\repositories\modulos\CajaSesionRepository;
use App\Rules\modulos\CajaSesionRules;
use App\Services\LogSistemaService;
use Exception;

/**
 * Turnos de caja del Punto de Venta: abrir (con fondo inicial) y cerrar
 * (con arqueo). Es el prerrequisito común a las tres plantillas de pantalla
 * del POS — ninguna vende sin una sesión de caja abierta para su punto de
 * emisión.
 */
class CajaSesionService
{
    private CajaSesionRepository $repository;
    private CajaSesionRules $rules;
    private LogSistemaService $logService;

    public function __construct(
        CajaSesionRepository $repository,
        CajaSesionRules $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getSesionAbierta(int $idEmpresa, int $idPuntoEmision): ?array
    {
        return $this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision);
    }

    /** Cualquier turno abierto de la empresa (portal público QR — ver CajaSesionRepository::getAbiertaPorEmpresa). */
    public function getSesionAbiertaEmpresa(int $idEmpresa): ?array
    {
        return $this->repository->getAbiertaPorEmpresa($idEmpresa);
    }

    /** ¿Ese turno sigue abierto y es de esta empresa? (validación de un id recibido de afuera). */
    public function esSesionAbiertaDeEmpresa(int $idCajaSesion, int $idEmpresa): bool
    {
        return $this->repository->esAbiertaDeEmpresa($idCajaSesion, $idEmpresa);
    }

    public function abrir(array $data): array
    {
        $this->rules->validarApertura($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idPuntoEmision = (int) $data['id_punto_emision'];

        if ($this->repository->getAbiertaPorPuntoEmision($idEmpresa, $idPuntoEmision)) {
            throw new Exception('Este punto de emisión ya tiene una caja abierta. Ciérrala antes de abrir una nueva.');
        }

        $this->repository->beginTransaction();
        try {
            $insertData = [
                'id_empresa' => $idEmpresa,
                'id_punto_emision' => $idPuntoEmision,
                'id_usuario' => (int) $data['id_usuario'],
                'fondo_inicial' => (float) $data['fondo_inicial'],
                'created_by' => (int) $data['id_usuario'],
            ];

            $id = $this->repository->create($insertData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'crear',
                'caja_sesiones',
                $id,
                null,
                $insertData
            );

            $this->repository->commit();

            return $this->repository->findById($id, $idEmpresa) ?? $insertData;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    public function cerrar(int $id, int $idEmpresa, array $data): array
    {
        $sesion = $this->repository->findById($id, $idEmpresa);
        if (!$sesion) {
            throw new Exception('La sesión de caja no existe.');
        }
        if ($sesion['estado'] !== 'abierta') {
            throw new Exception('Esta sesión de caja ya está cerrada.');
        }

        // El arqueo se hace forma de pago por forma de pago: lo esperado es lo
        // que el sistema registró cobrado, y lo contado es la SUMA de lo que el
        // cajero confirmó en cada una. El fondo inicial no entra: no es un
        // cobro, se cuenta aparte en el cajón.
        $formasPago    = $this->repository->getCobrosPorFormaPagoEnTurno($id);
        $montoEsperado = round(array_sum(array_column($formasPago, 'total')), 2);

        $contadas = $this->normalizarContadas($data['formas_contadas'] ?? null, $formasPago);
        $data['monto_contado'] = $contadas !== null
            ? round(array_sum(array_column($contadas, 'contado')), 2)
            : $data['monto_contado'];

        $this->rules->validarCierre($data);

        $montoContado = (float) $data['monto_contado'];
        $diferencia = round($montoContado - $montoEsperado, 2);

        $this->repository->beginTransaction();
        try {
            $updateData = [
                'monto_esperado' => $montoEsperado,
                'monto_contado' => $montoContado,
                'diferencia' => $diferencia,
                'observaciones_cierre' => trim($data['observaciones_cierre'] ?? '') ?: null,
                'updated_by' => (int) $data['id_usuario'],
            ];

            $this->repository->cerrar($id, $idEmpresa, $updateData);

            $this->logService->registrar(
                (int) $data['id_usuario'],
                $idEmpresa,
                'actualizar',
                'caja_sesiones',
                $id,
                $sesion,
                $updateData
            );

            $this->repository->commit();

            $sesionCerrada = $this->repository->findConCajero($id, $idEmpresa) ?? $updateData;
            $sesionCerrada['formas_pago'] = $this->cruzarContado($formasPago, $contadas);

            // Separadas por origen: el recargo por servicio del local y la propina
            // que dejó el cliente. `propina` se conserva como la suma de las dos.
            $propinas = $this->repository->getPropinasDelTurno($id);
            $sesionCerrada['propina_servicio']   = $propinas['servicio'];
            $sesionCerrada['propina_voluntaria'] = $propinas['voluntaria'];
            $sesionCerrada['propina']            = round($propinas['servicio'] + $propinas['voluntaria'], 2);

            // Detalle de impuestos de los comprobantes del turno: el mismo bloque
            // que imprime la tirilla del Reporte Restaurante.
            $resumenImp = $this->repository->getResumenImpuestosDelTurno($id);
            $sesionCerrada['documentos']        = $resumenImp['documentos'];
            $sesionCerrada['total_vendido']     = $resumenImp['subtotal'];
            $sesionCerrada['detalle_impuestos'] = DetalleImpuestos::armar($resumenImp);

            // El correo va DESPUÉS del commit y no puede tumbar el cierre: la
            // caja ya está cuadrada y cerrada; si el correo falla, se avisa.
            $sesionCerrada['aviso_correo'] = $this->enviarCorreoCierre($idEmpresa, $sesionCerrada, $sesionCerrada['formas_pago']);

            return $sesionCerrada;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Deja lo que confirmó el cajero en una forma segura de usar: solo las
     * formas de pago que el turno realmente tiene, en números y sin negativos.
     *
     * Se filtra contra `$formasPago` a propósito: los importes vienen del
     * navegador, y sin este cruce se podría inventar una forma que no existe o
     * colar texto. Devuelve null si no llegó nada (cliente con la pantalla
     * anterior), y entonces el llamador usa el `monto_contado` de siempre.
     *
     * @return list<array{id_forma_pago:int, nombre:string, contado:float}>|null
     */
    private function normalizarContadas(?array $contadas, array $formasPago): ?array
    {
        if ($contadas === null || $contadas === []) {
            return null;
        }

        $validas = [];
        foreach ($formasPago as $f) {
            $validas[(int) $f['id_forma_pago']] = (string) $f['nombre'];
        }

        // Indexado por forma, no una lista: si llegaran dos filas de la misma
        // forma de pago, una lista haría que el total sumara las dos mientras
        // el desglose mostraría solo una — el total y el detalle dirían cosas
        // distintas. Con la última gana, las dos vistas coinciden siempre.
        $limpias = [];
        foreach ($contadas as $c) {
            if (!is_array($c)) {
                continue;
            }
            $idForma = (int) ($c['id_forma_pago'] ?? -1);
            if (!array_key_exists($idForma, $validas)) {
                continue;
            }
            $limpias[$idForma] = [
                'id_forma_pago' => $idForma,
                'nombre'        => $validas[$idForma],
                'contado'       => max(0, round((float) ($c['contado'] ?? 0), 2)),
            ];
        }

        return $limpias !== [] ? array_values($limpias) : null;
    }

    /**
     * Añade a cada forma de pago lo contado y su diferencia, para la respuesta
     * y el correo. Si el cajero no confirmó nada, se asume lo cobrado.
     */
    private function cruzarContado(array $formasPago, ?array $contadas): array
    {
        $porId = [];
        foreach ($contadas ?? [] as $c) {
            $porId[(int) $c['id_forma_pago']] = (float) $c['contado'];
        }

        return array_map(static function (array $f) use ($porId, $contadas): array {
            $contado = $contadas === null
                ? (float) $f['total']
                : (float) ($porId[(int) $f['id_forma_pago']] ?? 0);
            $f['contado']    = round($contado, 2);
            $f['diferencia'] = round($contado - (float) $f['total'], 2);
            return $f;
        }, $formasPago);
    }

    /** Desglose de cobros del turno por forma de pago, para la pantalla de cierre. */
    public function getResumenTurno(int $id, int $idEmpresa): array
    {
        $sesion = $this->repository->findById($id, $idEmpresa);
        if (!$sesion) {
            throw new Exception('La sesión de caja no existe.');
        }

        $formasPago = $this->repository->getCobrosPorFormaPagoEnTurno($id);
        $efectivo   = $this->efectivoDelTurno($id, $formasPago);
        $propinas   = $this->repository->getPropinasDelTurno($id);

        return [
            'formas_pago'     => $formasPago,
            'total_cobrado'   => round(array_sum(array_column($formasPago, 'total')), 2),
            // Van dentro del total cobrado, no se suman aparte: son un "de esto,
            // tanto se reparte al personal". Separadas por origen, porque el
            // recargo por servicio lo fija el local y la propina la deja el cliente.
            'propina_servicio'   => $propinas['servicio'],
            'propina_voluntaria' => $propinas['voluntaria'],
            'propina'            => round($propinas['servicio'] + $propinas['voluntaria'], 2),
            'efectivo'        => $efectivo,
            'fondo_inicial'   => round((float) $sesion['fondo_inicial'], 2),
            'monto_esperado'  => round((float) $sesion['fondo_inicial'] + $efectivo, 2),
        ];
    }

    /**
     * Efectivo que debería haber en el cajón al cerrar.
     *
     * Se toma de las formas de pago de tipo EFECTIVO del desglose (lo que
     * realmente eligió quien cobró), pero **nunca menos** de lo que daba el
     * cálculo anterior por código SRI '01'. Los dos criterios pueden discrepar:
     * hay turnos cuyos documentos salieron con otro código SRI aunque se
     * cobraran en efectivo, y turnos cuyos cobros no llegaron a generar su
     * Ingreso —y sin Ingreso no hay forma de pago que consultar—. Quedarse con
     * el mayor evita reportarle al cajero menos efectivo del que tiene que
     * entregar, que es el error que sí duele.
     */
    private function efectivoDelTurno(int $id, array $formasPago): float
    {
        $porDesglose = 0.0;
        foreach ($formasPago as $f) {
            if (strtoupper((string) ($f['tipo'] ?? '')) === 'EFECTIVO') {
                $porDesglose += (float) $f['total'];
            }
        }

        return round(max($porDesglose, $this->repository->getEfectivoCobradoEnTurno($id)), 2);
    }

    /**
     * Correo con el detalle del cierre al buzón registrado en la empresa.
     * Devuelve null si salió bien, o el motivo por el que no se pudo enviar.
     */
    private function enviarCorreoCierre(int $idEmpresa, array $sesion, array $formasPago): ?string
    {
        try {
            $empresa = (new Empresa())->getPorId($idEmpresa) ?? [];
            $destino = trim((string) ($empresa['mail'] ?? ''));

            if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                return 'La empresa no tiene un correo válido configurado, así que no se envió el detalle del cierre.';
            }
            if (!function_exists('enviar_correo_reporte')) {
                return 'El envío de correo no está disponible en este servidor.';
            }

            $ok = enviar_correo_reporte(
                [$destino],
                'Cierre de caja ' . date('d-m-Y') . ' — ' . ($empresa['nombre'] ?? ''),
                $this->cuerpoCorreoCierre($empresa, $sesion, $formasPago)
            );

            return $ok ? null : ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'No se pudo enviar el correo del cierre.');
        } catch (\Throwable $e) {
            error_log('[CajaSesion] Correo de cierre no enviado (turno ' . ($sesion['id'] ?? '?') . '): ' . $e->getMessage());
            return 'No se pudo enviar el correo del cierre: ' . $e->getMessage();
        }
    }

    /**
     * Cuerpo HTML del correo de cierre. Sigue el formato de la tirilla del
     * Reporte Restaurante (reporte_restaurante/tirilla.php) —mismas secciones,
     * mismo orden, mismas etiquetas—, para que el cierre y el reporte se lean
     * igual: encabezado, datos, totales, DETALLE DE IMPUESTOS y RESUMEN POR
     * FORMA DE PAGO. Lo que la tirilla no tiene es el ARQUEO (contado y
     * diferencia): va al final porque solo existe al cerrar un turno.
     */
    private function cuerpoCorreoCierre(array $empresa, array $sesion, array $formasPago): string
    {
        $e   = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $m   = static fn($v) => '$' . number_format((float) $v, 2);
        $fec = static function (?string $f): string {
            return $f ? date('d-m-Y H:i:s', strtotime($f)) : '—';
        };

        // Piezas con el estilo de la tirilla: separador punteado, título de
        // sección centrado en negrita y filas "etiqueta … importe".
        $sep     = '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">';
        $linea   = '<tr><td colspan="2"><hr style="border:none;border-top:1px solid #000;margin:2px 0;"></td></tr>';
        $sub     = 'font-size:12px;';
        $negrita = 'font-weight:bold;font-size:14px;';
        $titulo  = static fn(string $t) => '<div style="text-align:center;font-weight:bold;">' . $t . '</div>';
        $fila    = static fn(string $etq, string $valor, string $estilo = '') =>
            '<tr style="' . $estilo . '"><td style="padding:1px 0;">' . $etq . '</td>'
            . '<td style="padding:1px 0;text-align:right;white-space:nowrap;width:30%;">' . $valor . '</td></tr>';
        $concepto = static fn(string $t) => '<tr><td colspan="2" style="padding:1px 0;">' . $t . '</td></tr>';
        $tabla   = static fn(string $filas) =>
            '<table style="width:100%;border-collapse:collapse;font-size:13px;">' . $filas . '</table>';
        $chico   = static fn(string $t) => '<span style="' . $sub . '">' . $t . '</span>';

        // Datos del turno (en la tirilla: los filtros aplicados).
        $datos = '';
        foreach ([
            'Turno'    => '#' . (int) ($sesion['id'] ?? 0),
            'Cajero'   => $sesion['usuario_nombre'] ?? $sesion['cajero_nombre'] ?? '—',
            'Apertura' => $fec($sesion['fecha_apertura'] ?? null),
            'Cierre'   => $fec($sesion['fecha_cierre'] ?? null),
        ] as $etq => $valor) {
            $datos .= '<tr><td style="padding:1px 0;width:38%;">' . $etq . ':</td><td style="padding:1px 0;">' . $e($valor) . '</td></tr>';
        }

        // Totales.
        $totales = $fila('Documentos', (string) (int) ($sesion['documentos'] ?? 0))
                 . $fila('TOTAL VENDIDO (sin&nbsp;imp.)', $m($sesion['total_vendido'] ?? 0), $negrita);

        // Detalle de impuestos: la última fila (total con impuestos) en negrita.
        $imp      = $sesion['detalle_impuestos'] ?? ['lineas' => [], 'total' => 0];
        $filasImp = '';
        foreach ($imp['lineas'] as $l) {
            $filasImp .= $fila($e($l['etiqueta']), $m($l['valor']));
        }
        $filasImp .= $fila('TOTAL CON IMPUESTOS', $m($imp['total']), $negrita);

        // Resumen por forma de pago: concepto, sublínea "TIPO — N cobro(s)" e importe.
        $filasFp = '';
        foreach ($formasPago as $f) {
            $detalle  = trim(($f['tipo'] ?? '') . ' — ' . (int) $f['documentos'] . ' cobro(s)', ' —');
            $filasFp .= $concepto($e($f['nombre'])) . $fila($chico($e($detalle)), $m($f['total']));
        }
        if ($filasFp !== '') {
            $filasFp .= $linea;
        }
        $filasFp .= $fila('<strong>Total cobrado</strong>', '<strong>' . $m(array_sum(array_column($formasPago, 'total'))) . '</strong>')
                  . $fila($chico('Servicio'), $chico($m($sesion['propina_servicio'] ?? $sesion['propina'] ?? 0)))
                  . $fila($chico('Propina voluntaria'), $chico($m($sesion['propina_voluntaria'] ?? 0)));

        // Arqueo: lo que confirmó el cajero contra lo cobrado, forma por forma.
        $diferencia = (float) ($sesion['diferencia'] ?? 0);
        $colorDif   = abs($diferencia) < 0.01 ? '#198754' : '#dc3545';
        $filasArq   = '';
        foreach ($formasPago as $f) {
            $dif       = (float) ($f['diferencia'] ?? 0);
            $filasArq .= $concepto($e($f['nombre']))
                       . $fila(
                           $chico('Cobrado ' . $m($f['total']) . ' · Contado ' . $m($f['contado'] ?? $f['total'])),
                           abs($dif) >= 0.01 ? '<span style="color:#dc3545;font-weight:bold;">' . $m($dif) . '</span>' : '—'
                       );
        }
        $filasArq = $filasArq === ''
            ? '<tr><td colspan="2" style="padding:1px 0;text-align:center;">El turno se cerró sin cobros registrados.</td></tr>'
            : $filasArq . $linea;
        $filasArq .= $fila('Fondo inicial ' . $chico('(no entra en el arqueo)'), $m($sesion['fondo_inicial'] ?? 0))
                   . $fila('Cobrado según el sistema', $m($sesion['monto_esperado'] ?? 0))
                   . $fila('Confirmado por el cajero', $m($sesion['monto_contado'] ?? 0))
                   . $fila('DIFERENCIA', '<span style="color:' . $colorDif . ';">' . $m($diferencia) . '</span>', $negrita);

        return '
            <div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.35;color:#000;max-width:420px;margin:auto;">
                <div style="text-align:center;">
                    <div style="font-size:15px;font-weight:bold;">' . $e($empresa['nombre_comercial'] ?? $empresa['nombre'] ?? '') . '</div>
                    ' . (!empty($empresa['ruc']) ? '<div>RUC: ' . $e($empresa['ruc']) . '</div>' : '') . '
                </div>

                ' . $sep . '
                <div style="text-align:center;font-weight:bold;font-size:14px;">CIERRE DE CAJA</div>
                <div style="text-align:center;">Emitido: ' . date('d-m-Y H:i') . '</div>

                ' . $sep . '
                <table style="width:100%;border-collapse:collapse;font-size:13px;">' . $datos . '</table>

                ' . $sep . $tabla($totales) . '

                ' . $sep . $titulo('DETALLE DE IMPUESTOS') . $tabla($filasImp) . '

                ' . $sep . $titulo('RESUMEN POR FORMA DE PAGO') . '
                <div style="text-align:center;' . $sub . '">Lo cobrado, con impuestos</div>
                ' . $tabla($filasFp) . '

                ' . $sep . $titulo('ARQUEO DE CAJA') . '
                <div style="text-align:center;' . $sub . '">Lo confirmado por el cajero</div>
                ' . $tabla($filasArq) . '

                ' . (!empty($sesion['observaciones_cierre'])
                        ? $sep . '<div><strong>Observaciones:</strong> ' . $e($sesion['observaciones_cierre']) . '</div>'
                        : '') . '

                ' . $sep . '
                <div style="text-align:center;' . $sub . '">Enviado automáticamente al cerrar la caja, el ' . date('d-m-Y H:i:s') . '.</div>
            </div>';
    }
}
