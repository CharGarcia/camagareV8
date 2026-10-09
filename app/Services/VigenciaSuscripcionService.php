<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Cache;
use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\SuscripcionesRepository;

/**
 * Suscripción del SISTEMA de una empresa (lo que paga el dueño del RUC por usar CaMaGaRe):
 * cuál le corresponde y cuándo vence.
 *
 * Única fuente para la tarjeta "Suscripción y Vigencia del Sistema" de la ficha de empresa,
 * el aviso del navbar y el modal que sale al ingresar. Antes el navbar tenía su propia
 * consulta, que solo miraba el vínculo directo y el RUC exacto: no veía la empresa hermana,
 * la administradora por defecto ni la reventa, y había empresas con suscripción en la ficha
 * que nunca recibían el aviso.
 *
 * Vencimiento: el próximo cobro avanza solo en cuanto se genera el documento del período,
 * esté pagado o no. Contar solo esa fecha nunca llega a "vencida" con la facturación
 * automática. Por eso, si algún documento de la suscripción tiene saldo, el vencimiento es
 * la fecha del más antiguo de ellos; si todo está pagado, es el próximo cobro. En ambos casos
 * se suman DIAS_GRACIA días antes de marcarla vencida.
 */
class VigenciaSuscripcionService
{
    /** Desde cuántos días antes del vencimiento sale el modal al ingresar (incluye vencidas). */
    public const DIAS_MODAL = 2;

    /**
     * Días de gracia después de la fecha del período (documento con saldo o próximo cobro)
     * antes de marcar la suscripción como vencida. El modal sale igual desde DIAS_MODAL
     * días antes de esa fecha límite.
     */
    public const DIAS_GRACIA = 3;

    /** Clave de sesión: el modal se revisa una vez por ingreso y por cambio de empresa. */
    public const CLAVE_SESION = 'aviso_suscripcion_pendiente';

    /** TTL de la caché del aviso del navbar (segundos). El modal siempre calcula en vivo. */
    private const TTL = 300;

    /**
     * Resuelve la(s) suscripción(es) que cubren a la empresa. Regla, una vez resuelta la
     * controladora (vínculo directo, empresa hermana con el mismo RUC o administradora):
     *   a) suscripción vinculada (`id_suscripcion`): solo esa;
     *   b) cliente de reventa (`id_cliente_facturado`): sus suscripciones, sin montos; si
     *      tiene varias y ninguna está vinculada, no se sabe cuál es (`varias` = N);
     *   c) si no, por RUC propio contra la controladora, con montos;
     *   d) si nada coincide, `info` vacío (la ficha cae a los datos manuales).
     *
     * @param array $empresa Fila de empresas (EmpresaRepository::getEmisorConfig).
     * @return array{info: array, controladora: int, sin_valores: bool, varias: int}
     */
    public function resolver(array $empresa): array
    {
        $out = ['info' => [], 'controladora' => 0, 'sin_valores' => false, 'varias' => 0];

        $ruc = trim((string) ($empresa['ruc'] ?? ''));
        if ($ruc === '') {
            return $out;
        }

        try {
            $idDirecto = (int) ($empresa['id_empresa_suscripciones'] ?? 0);
            $idCtrl = (int) ((new EmpresaRepository())->resolverEmpresaControladoraSuscripciones(
                $ruc,
                $idDirecto > 0 ? $idDirecto : null
            ) ?? 0);
            $out['controladora'] = $idCtrl;
            if ($idCtrl <= 0) {
                return $out;
            }

            $repo          = new SuscripcionesRepository();
            $idClienteFact = (int) ($empresa['id_cliente_facturado'] ?? 0);
            $idSuscripcion = (int) ($empresa['id_suscripcion'] ?? 0);

            if ($idSuscripcion > 0) {
                $out['info'] = $repo->getResumenPorSuscripcion($idCtrl, $idSuscripcion);
                // Sin montos solo si es reventa (se factura a un tercero).
                $out['sin_valores'] = !empty($out['info']) && $idClienteFact > 0;
            } elseif ($idClienteFact > 0) {
                $lista = $repo->getResumenPorControladoraYCliente($idCtrl, $idClienteFact);
                if (count($lista) > 1) {
                    $out['varias'] = count($lista);
                } else {
                    $out['info']        = $lista;
                    $out['sin_valores'] = !empty($lista);
                }
            } else {
                $out['info'] = $repo->getResumenPorControladoraYRuc($idCtrl, $ruc);
            }
        } catch (\Throwable $e) {
            // Módulo de suscripciones o migración no disponible: la ficha usa los datos manuales.
            $out['info']        = [];
            $out['sin_valores'] = false;
            $out['varias']      = 0;
        }

        return $out;
    }

    /**
     * Vigencia de la empresa, con caché corta (para el sondeo del navbar).
     * Con $usarCache = false calcula en vivo y refresca la caché.
     */
    public function estado(int $idEmpresa, bool $usarCache = true): ?array
    {
        if ($idEmpresa <= 0) {
            return null;
        }
        $clave = 'cmg_vigencia_susc_' . $idEmpresa;
        if ($usarCache) {
            $c = Cache::get($clave);
            if (is_array($c) && array_key_exists('v', $c)) {
                return $c['v'];
            }
        }

        try {
            $empresa = (new EmpresaRepository())->getEmisorConfig($idEmpresa);
            $v = $empresa ? $this->evaluar($this->resolver($empresa), $empresa) : null;
            if ($v !== null) {
                $v['empresa'] = (string) ($empresa['nombre'] ?? '');
                $v['ruc']     = (string) ($empresa['ruc'] ?? '');
            }
        } catch (\Throwable $e) {
            error_log('VigenciaSuscripcionService::estado ' . $e->getMessage());
            $v = null;
        }

        // Se guarda envuelto para cachear también el "no aplica" (null).
        Cache::set($clave, ['v' => $v], self::TTL);
        return $v;
    }

    /**
     * Calcula el vencimiento a partir de lo que devolvió resolver(). Si la empresa tiene
     * varias suscripciones, manda la más urgente.
     *
     * @return array{estado:string, dias:int, fecha:string, motivo:string, saldo:?float,
     *               documento:?string, documentos_pendientes:int, periodicidad:?string,
     *               meses:?int, sin_valores:bool}|null  null = no hay nada que avisar.
     */
    public function evaluar(array $resuelto, array $empresa): ?array
    {
        // Reventa con varias suscripciones sin asignar: no se sabe cuál es la de esta empresa.
        if ((int) ($resuelto['varias'] ?? 0) > 0) {
            return null;
        }

        $sinValores = !empty($resuelto['sin_valores']);
        $info       = $resuelto['info'] ?? [];

        if (empty($info)) {
            // Fallback manual: fecha de vigencia tecleada en la empresa.
            $hasta = $empresa['periodo_vigencia_hasta'] ?? null;
            if (empty($hasta)) {
                return null;
            }
            return $this->armar('manual', (string) $hasta, null, null, 0, null, null, $sinValores);
        }

        $repo  = new SuscripcionesRepository();
        $idCtrl = (int) ($resuelto['controladora'] ?? 0);
        $mejor   = null;
        $porSusc = [];

        foreach ($info as $s) {
            $estadoSusc = strtolower((string) ($s['estado'] ?? ''));
            if ($estadoSusc === 'cancelado') {
                continue;
            }
            $periodicidad = $s['periodicidad'] ?? null;
            $meses = isset($s['periodicidad_meses']) && $s['periodicidad_meses'] !== null
                ? (int) $s['periodicidad_meses'] : null;

            // 1) Documentos de la suscripción con saldo: vence en la fecha del más antiguo.
            //    Misma consulta (y regla de saldo) que la pestaña Facturas del módulo.
            // En la empresa DUEÑA de la suscripción (puede ser otro establecimiento de la
            // controladora, con el mismo RUC): ahí están sus documentos y cobros.
            $cand = null;
            $idDuena = (int) ($s['id_empresa'] ?? $idCtrl);
            if ($idDuena > 0) {
                $pend = $repo->getFacturasCliente(
                    $idDuena, [], (int) $s['id'], true,
                    ['FACTURA' => null, 'RECIBO' => null],
                    'saldo:>0', 1, 1, 'fecha', 'ASC'
                );
                if (($pend['total'] ?? 0) > 0 && !empty($pend['rows'][0])) {
                    $doc  = $pend['rows'][0];
                    $cand = $this->armar(
                        'pendiente',
                        (string) $doc['fecha'],
                        (float) ($pend['resumen']['saldo'] ?? 0),
                        trim($doc['tipo_documento'] . ' ' . $doc['numero']),
                        (int) ($pend['resumen']['con_saldo'] ?? 1),
                        $periodicidad,
                        $meses,
                        $sinValores
                    );
                }
            }

            // 2) Todo pagado: vence en el próximo cobro (solo suscripciones activas).
            if ($cand === null && $estadoSusc === 'activo' && !empty($s['proximo_cobro'])) {
                $cand = $this->armar('proximo_cobro', (string) $s['proximo_cobro'], null, null, 0, $periodicidad, $meses, $sinValores);
            }

            if ($cand !== null) {
                $porSusc[(int) $s['id']] = $cand;
                if ($mejor === null || $cand['dias'] < $mejor['dias']) {
                    $mejor = $cand;
                }
            }
        }

        // Vencimiento de CADA suscripción: la tarjeta de la ficha pinta una barra por
        // suscripción y debe usar esta misma regla (no solo el próximo cobro).
        if ($mejor !== null) {
            $mejor['por_suscripcion'] = $porSusc;
        }
        return $mejor;
    }

    /** ¿Corresponde mostrar el modal al ingresar? (vencida o a ≤ DIAS_MODAL días). */
    public static function requiereModal(?array $vig): bool
    {
        return $vig !== null && ($vig['estado'] === 'vencida' || $vig['dias'] <= self::DIAS_MODAL);
    }

    private function armar(
        string $motivo,
        string $fecha,
        ?float $saldo,
        ?string $documento,
        int $pendientes,
        ?string $periodicidad,
        ?int $meses,
        bool $sinValores
    ): array {
        $hoy    = new \DateTimeImmutable('today');
        $f      = new \DateTimeImmutable(substr($fecha, 0, 10));
        // Fecha límite de pago = fecha del período + días de gracia. Recién al pasar
        // esa fecha se marca vencida; mientras tanto cuenta como "por vencer".
        $limite = $f->modify('+' . self::DIAS_GRACIA . ' days');
        $dias   = (int) $hoy->diff($limite)->format('%r%a');

        if ($dias < 0) {
            $estado = 'vencida';
        } elseif ($dias === 0) {
            $estado = 'vence_hoy';
        } else {
            $estado = 'por_vencer';
        }

        return [
            'estado'                => $estado,
            'dias'                  => $dias,
            'fecha'                 => $limite->format('Y-m-d'), // fecha límite de pago (con gracia)
            'fecha_periodo'         => $f->format('Y-m-d'),      // documento / próximo cobro
            'motivo'                => $motivo,
            // En reventa no se expone el precio ni el documento del revendedor.
            'saldo'                 => $sinValores ? null : $saldo,
            'documento'             => $sinValores ? null : $documento,
            'documentos_pendientes' => $pendientes,
            'periodicidad'          => $periodicidad,
            'meses'                 => $meses,
            'sin_valores'           => $sinValores,
        ];
    }
}
