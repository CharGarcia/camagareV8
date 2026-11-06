<?php

declare(strict_types=1);

namespace App\Services;

use App\core\Database;
use App\repositories\DocumentoSriRestauracionRepository;
use App\repositories\SecuencialRepository;

/**
 * RESTAURAR (des-eliminar) un comprobante electrónico eliminado en borrador, con su mismo
 * número. Común a los 7 comprobantes que se envían a autorizar al SRI.
 *
 * Para qué: una factura/NC/retención… que el SRI dejó NO AUTORIZADA (o que nunca se envió)
 * y se eliminó. Su número queda libre y se puede volver a usar (el SRI acepta el número con
 * otra clave de acceso: ver SecuencialRepository::ACCIONES_SRI_CONCLUYENTES), pero el usuario
 * puede preferir recuperar el documento tal como estaba, corregirlo y reenviarlo.
 *
 * Solo el superadministrador (lo controla cada controlador). Vuelve con su mismo estado
 * (borrador / no autorizada). Un borrador no tiene asiento ni kardex que rehacer; lo que el
 * borrado deshizo aparte (cobros, pagos) NO se recupera.
 *
 * No se restaura si:
 *  - no estaba en un estado restaurable (el borrado forzado de un autorizado revirtió de más);
 *  - su período contable está cerrado;
 *  - al eliminarlo se revirtió inventario (restaurar no lo rehace);
 *  - tiene un vínculo que el borrado deshizo (propio de cada documento: $motivoExtra);
 *  - otro documento vigente del mismo punto y ambiente ya usa su número.
 */
class DocumentoSriRestauracionService
{
    use \App\Traits\PeriodoContableTrait;

    /**
     * Whitelist de documentos: tipo (= tipo_comprobante de sri_envio_log) → tabla, tipo de
     * secuencial (SecuencialRepository::DOCUMENT_MAP), nombre para mensajes, referencia_tipo
     * del kardex y estados desde los que se puede restaurar.
     */
    public const TIPOS = [
        'factura_venta'      => ['tabla' => 'ventas_cabecera',            'secuencial' => 'Facturas de venta',                  'nombre' => 'la factura',            'kardex' => ['factura_venta'], 'estados' => ['borrador']],
        'nota_credito'       => ['tabla' => 'notas_credito_cabecera',     'secuencial' => 'Nota de crédito',                    'nombre' => 'la nota de crédito',    'kardex' => ['nota_credito'],  'estados' => ['borrador']],
        'nota_debito'        => ['tabla' => 'nota_debito_cabecera',       'secuencial' => 'Nota de débito',                     'nombre' => 'la nota de débito',     'kardex' => [],                'estados' => ['borrador']],
        'retencion_compra'   => ['tabla' => 'retencion_compra_cabecera',  'secuencial' => 'Retenciones de compras',             'nombre' => 'la retención',          'kardex' => [],                'estados' => ['borrador', 'no_autorizada']],
        'guia_remision'      => ['tabla' => 'guias_remision_cabecera',    'secuencial' => 'Guía de remisión',                   'nombre' => 'la guía de remisión',   'kardex' => [],                'estados' => ['borrador']],
        'liquidacion_compra' => ['tabla' => 'liquidaciones_cabecera',     'secuencial' => 'Liquidación de compras o servicios', 'nombre' => 'la liquidación',        'kardex' => [],                'estados' => ['borrador', '']],
        'factura_reembolso'  => ['tabla' => 'factura_reembolso_cabecera', 'secuencial' => 'Facturas de reembolso',              'nombre' => 'la factura de reembolso','kardex' => [],               'estados' => ['borrador']],
    ];

    private DocumentoSriRestauracionRepository $repo;
    private LogSistemaService $log;

    public function __construct(?DocumentoSriRestauracionRepository $repo = null, ?LogSistemaService $log = null)
    {
        $this->repo = $repo ?? new DocumentoSriRestauracionRepository();
        $this->log  = $log ?? new LogSistemaService();
    }

    /**
     * @param callable|null $motivoExtra fn(int $id, int $idEmpresa, array $cabecera): ?string — vínculo
     *        propio del documento que impide restaurarlo (null = ninguno). Corre dentro de la transacción.
     * @return array{id:int, numero:string}
     */
    public function restaurar(string $tipo, int $id, int $idEmpresa, int $idUsuario, ?callable $motivoExtra = null): array
    {
        $cfg = self::TIPOS[$tipo] ?? null;
        if ($cfg === null) {
            throw new \InvalidArgumentException("Tipo de documento no restaurable: {$tipo}");
        }
        $tabla  = $cfg['tabla'];
        $nombre = $cfg['nombre'];

        $db = Database::getConnection();
        $propia = !$db->inTransaction();
        if ($propia) $db->beginTransaction();

        try {
            $cab = $this->repo->getEliminado($tabla, $id, $idEmpresa);
            if (!$cab) {
                throw new \Exception(ucfirst($nombre) . ' eliminada no se encontró.');
            }
            $numero = self::numero($cab);

            $estado = strtolower(trim((string) ($cab['estado'] ?? '')));
            if (!in_array($estado, $cfg['estados'], true)) {
                throw new \Exception('No se puede restaurar ' . $nombre . " {$numero}: se eliminó en estado «{$estado}» y solo se restauran las eliminadas en borrador.");
            }
            $this->validarPeriodoContable(
                $cab['fecha_emision'] ?? null,
                $idEmpresa,
                'No se puede restaurar ' . $nombre . ' porque su período contable está cerrado.'
            );
            if ($this->repo->inventarioRevertidoAlEliminar($cfg['kardex'], $id, $idEmpresa, $cab['deleted_at'] ?? null)) {
                throw new \Exception('No se puede restaurar ' . $nombre . " {$numero}: movió inventario y al eliminarla ese movimiento se revirtió. Emítala de nuevo.");
            }
            if ($motivoExtra !== null) {
                $motivo = $motivoExtra($id, $idEmpresa, $cab);
                if ($motivo !== null && $motivo !== '') {
                    throw new \Exception('No se puede restaurar ' . $nombre . " {$numero}: {$motivo}");
                }
            }

            // Mismo candado que la emisión (CLAUDE.md §8): nadie toma este número mientras tanto.
            $idPunto = (int) ($cab['id_punto_emision'] ?? 0);
            if ($idPunto > 0) {
                (new SecuencialRepository())->lockSecuencial($idPunto, $cfg['secuencial']);
                if ($this->repo->secuencialOcupado($tabla, $idPunto, (string) $cab['secuencial'], (string) $cab['tipo_ambiente'], $id)) {
                    throw new \Exception("No se puede restaurar: otro documento vigente ya usa el número {$numero}.");
                }
            }

            if (!$this->repo->restaurar($tabla, $id, $idEmpresa, $idUsuario)) {
                throw new \Exception(ucfirst($nombre) . ' ya no está eliminada.');
            }

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'RESTAURAR',
                $tabla,
                $id,
                ['eliminado' => true, 'deleted_at' => $cab['deleted_at'], 'deleted_by' => $cab['deleted_by']],
                ['eliminado' => false, 'estado' => $cab['estado'], 'numero' => $numero]
            );

            if ($propia) $db->commit();
            return ['id' => $id, 'numero' => $numero];
        } catch (\Throwable $e) {
            if ($propia && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /** 001-001-000001130 a partir de la cabecera. */
    public static function numero(array $cab): string
    {
        return sprintf('%s-%s-%s',
            str_pad((string) ($cab['establecimiento'] ?? ''), 3, '0', STR_PAD_LEFT),
            str_pad((string) ($cab['punto_emision'] ?? ''), 3, '0', STR_PAD_LEFT),
            str_pad(trim((string) ($cab['secuencial'] ?? '')), 9, '0', STR_PAD_LEFT));
    }
}
