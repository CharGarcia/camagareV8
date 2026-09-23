<?php
/**
 * Interruptor por empresa: ¿este módulo genera asientos contables?
 *
 * Cada empresa puede apagar la contabilización automática de un módulo declarado en
 * config/contabilidad_modulos.php. Nació para Consignaciones — empresas que no quieren
 * reclasificar la mercadería entregada a «Mercadería en Consignación» (el "enfoque A" de
 * SAP/Odoo: la mercadería sigue en Inventario y solo la factura mueve cuentas) —, pero sirve
 * para cualquiera de los módulos del mapa.
 *
 * Reglas:
 *   1. Por defecto TODO contabiliza. Sin fila en la tabla, o sin la tabla, no cambia nada.
 *   2. Apagado = no se crean asientos NUEVOS. Un documento que ya tiene asiento lo conserva y lo
 *      sigue actualizando al editarse; si no, al apagar quedarían asientos desfasados de su
 *      documento. Por eso los services no preguntan "¿contabiliza?" sino omitirGeneracion().
 *   3. Al volver a encenderlo, los documentos que quedaron sin asiento se contabilizan por los
 *      caminos de siempre (al abrir el módulo o con la sincronización de Estados Financieros).
 *   4. Los módulos con 'sigue_a' no tienen interruptor: Retornos y Facturación CV generan su
 *      asiento inverso solo si la consignación madre tiene asiento (ConsignacionVentaRepository::sqlContabilizada).
 *      Esa herencia se aplica siempre, con el interruptor encendido o apagado: el inverso de un
 *      asiento que no existe acreditaría una cuenta que nunca se debitó.
 *
 * Tabla: contabilidad_modulos_empresa (database/migrations/20260922_contabilidad_modulos_empresa.sql).
 */

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\ContabilidadModulos;
use App\repositories\modulos\AsientoProgramadoRepository;
use App\repositories\modulos\ContabilidadInterruptorRepository;
use App\Rules\modulos\ContabilidadInterruptorRules;
use App\Services\LogSistemaService;

class ContabilidadInterruptorService
{
    /** @var array<int,string[]> Claves apagadas por empresa, leídas una vez por request. */
    private static array $cacheApagados = [];

    public function __construct(
        private ContabilidadInterruptorRepository $repo,
        private ContabilidadInterruptorRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(
            new ContabilidadInterruptorRepository(),
            new ContabilidadInterruptorRules(),
            new LogSistemaService()
        );
    }

    /**
     * ¿El módulo contabiliza sus documentos en esta empresa? Los módulos con 'sigue_a' responden
     * siempre true: lo que decide por ellos es su documento de origen (herencia).
     */
    public function contabiliza(int $idEmpresa, string $clave): bool
    {
        $def = ContabilidadModulos::definicion($clave);
        if ($def === null || !empty($def['sigue_a'])) {
            return true;
        }
        return !in_array($clave, $this->clavesApagadas($idEmpresa), true);
    }

    /**
     * Pregunta que hacen los services antes de armar el asiento de un documento: ¿lo salto?
     * Solo se salta si el módulo está apagado Y el documento todavía no tiene asiento (regla 2).
     */
    public function omitirGeneracion(int $idEmpresa, string $clave, string $moduloOrigen, int $idDocumento): bool
    {
        if ($this->contabiliza($idEmpresa, $clave)) {
            return false;
        }
        return !$this->repo->tieneAsientoVivo($moduloOrigen, $idDocumento, $idEmpresa);
    }

    /**
     * Módulos agrupados para la ventana «Módulos que contabilizan».
     *
     * @return array<string, array<int, array{clave:string, nombre:string, ayuda:string, contabiliza:bool, sigue_a:?string}>>
     */
    public function listar(int $idEmpresa): array
    {
        $apagadas = $this->clavesApagadas($idEmpresa);
        $grupos = [];
        foreach (ContabilidadModulos::todos() as $clave => $def) {
            $sigueA = !empty($def['sigue_a']) ? (ContabilidadModulos::definicion((string) $def['sigue_a'])['nombre'] ?? null) : null;
            $grupos[(string) ($def['grupo'] ?? 'Otros')][] = [
                'clave'       => (string) $clave,
                'nombre'      => (string) ($def['nombre'] ?? $clave),
                'ayuda'       => (string) ($def['ayuda'] ?? ''),
                'contabiliza' => $sigueA !== null || !in_array($clave, $apagadas, true),
                'sigue_a'     => $sigueA,
            ];
        }
        return $grupos;
    }

    /**
     * Enciende o apaga un módulo. Devuelve un aviso para el usuario cuando el cambio tiene
     * consecuencias que conviene revisar (saldo pendiente en consignación, documentos por
     * contabilizar al encender).
     *
     * @return array{cambio:bool, aviso:?string}
     */
    public function cambiar(int $idEmpresa, int $idUsuario, string $clave, bool $contabiliza): array
    {
        $def = $this->rules->validarClave($clave);
        if (!$this->repo->existeTabla()) {
            throw new \Exception('Falta aplicar en la base de datos el script 20260922_contabilidad_modulos_empresa.sql.');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->lock($idEmpresa, $clave);
            $fila  = $this->repo->getFila($idEmpresa, $clave);
            $antes = $fila === null ? true : (bool) $fila['contabiliza'];

            if ($antes === $contabiliza) {
                $this->repo->commit();
                return ['cambio' => false, 'aviso' => null];
            }

            $id = $fila === null
                ? $this->repo->insertar($idEmpresa, $clave, $contabiliza, $idUsuario)
                : (int) $fila['id'];
            if ($fila !== null) {
                $this->repo->actualizar($id, $idEmpresa, $contabiliza, $idUsuario);
            }

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'actualizar',
                'contabilidad_modulos_empresa',
                $id,
                ['modulo_clave' => $clave, 'modulo' => $def['nombre'], 'contabiliza' => $antes],
                ['modulo_clave' => $clave, 'modulo' => $def['nombre'], 'contabiliza' => $contabiliza]
            );

            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        unset(self::$cacheApagados[$idEmpresa]);

        return ['cambio' => true, 'aviso' => $this->avisoPorCambio($idEmpresa, $clave, (string) $def['nombre'], $contabiliza)];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @return string[] */
    private function clavesApagadas(int $idEmpresa): array
    {
        if (!array_key_exists($idEmpresa, self::$cacheApagados)) {
            self::$cacheApagados[$idEmpresa] = $this->repo->getClavesApagadas($idEmpresa);
        }
        return self::$cacheApagados[$idEmpresa];
    }

    private function avisoPorCambio(int $idEmpresa, string $clave, string $nombre, bool $contabiliza): ?string
    {
        if ($contabiliza) {
            return 'Los documentos de «' . $nombre . '» que quedaron sin asiento mientras estuvo apagado '
                . 'se contabilizarán al abrir el módulo o al sincronizar Estados Financieros '
                . '(salvo los de períodos cerrados).';
        }

        if ($clave !== 'consignaciones') {
            return null;
        }

        // Al apagar Consignaciones: lo ya contabilizado sigue su curso (retornos y facturaciones de
        // esas consignaciones siguen generando el inverso), pero conviene que el contador vea
        // cuánto hay hoy en la cuenta puente.
        $idCuenta = $this->cuentaMercaderiaConsignacion($idEmpresa);
        if ($idCuenta === null) {
            return null;
        }
        $saldo = $this->repo->getSaldoCuenta($idEmpresa, $idCuenta['id']);
        if (abs($saldo) < 0.005) {
            return null;
        }
        return 'La cuenta «' . trim($idCuenta['codigo'] . ' ' . $idCuenta['nombre']) . '» tiene hoy un saldo de '
            . number_format($saldo, 2, '.', ',') . '. Corresponde a consignaciones ya contabilizadas: sus retornos '
            . 'y facturaciones lo irán descargando. Si prefieres devolverlo a Inventario de una vez, registra un '
            . 'asiento manual.';
    }

    /** @return array{id:int, codigo:string, nombre:string}|null */
    private function cuentaMercaderiaConsignacion(int $idEmpresa): ?array
    {
        foreach ((new AsientoProgramadoRepository())->getReglasGeneralesPorConcepto($idEmpresa, 'consignacion_venta') as $r) {
            if (empty($r['id_cuenta'])) {
                continue;
            }
            $codigo = strtoupper($r['asiento_tipo_codigo'] ?? $r['codigo'] ?? '');
            if (str_contains($codigo, 'INVENTARIO')) {
                continue;
            }
            if (str_contains($codigo, 'CONSIGNACION') || str_contains($codigo, 'MERCADERIA')) {
                return [
                    'id'     => (int) $r['id_cuenta'],
                    'codigo' => (string) ($r['cuenta_codigo'] ?? ''),
                    'nombre' => (string) ($r['cuenta_nombre'] ?? ''),
                ];
            }
        }
        return null;
    }
}
