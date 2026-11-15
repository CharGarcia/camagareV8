<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\DocumentoIgnoradoRepository;
use App\repositories\modulos\EmpresaRepository;
use App\Services\LogSistemaService;

/**
 * Al eliminar un comprobante electrónico recibido (Compras, Retenciones de Ventas),
 * lo deja en «Documentos para no descargar» de Descargas SRI para que la próxima
 * descarga no lo vuelva a registrar. Se llama DENTRO de la transacción del borrado:
 * si la eliminación falla, tampoco queda en la lista.
 *
 * Motivos (DocumentoIgnoradoRepository::MOTIVO_*):
 *  - ANULADO_SRI          → bloqueo para todos los establecimientos del RUC.
 *  - OTRO_ESTABLECIMIENTO → bloqueo solo en este establecimiento (otro del mismo RUC
 *                           sí podrá descargarlo o cargar su XML).
 * Manual: docs/manual/modulos/descargas-sri.md («Documentos para no descargar»).
 */
class DocumentoNoDescargarService
{
    public const MOTIVOS = [
        DocumentoIgnoradoRepository::MOTIVO_ANULADO_SRI,
        DocumentoIgnoradoRepository::MOTIVO_OTRO_ESTABLECIMIENTO,
    ];

    private DocumentoIgnoradoRepository $repo;
    private LogSistemaService $log;

    public function __construct(?LogSistemaService $log = null)
    {
        $this->repo = new DocumentoIgnoradoRepository();
        $this->log  = $log ?? new LogSistemaService();
    }

    /** ¿Es una clave de acceso electrónica (49 dígitos)? */
    public static function esClaveElectronica(?string $clave): bool
    {
        return (bool) preg_match('/^\d{49}$/', trim((string) $clave));
    }

    /**
     * Normaliza y valida el motivo recibido del formulario: null/'' = solo eliminar.
     * @throws \Exception si el motivo no es uno de los permitidos.
     */
    public static function normalizarMotivo(?string $motivo): ?string
    {
        $motivo = trim((string) $motivo);
        if ($motivo === '') {
            return null;
        }
        if (!in_array($motivo, self::MOTIVOS, true)) {
            throw new \Exception('Motivo de «no descargar» no válido.');
        }
        return $motivo;
    }

    /**
     * Agrega la clave a la lista con el alcance del motivo. No duplica: si ya está
     * bloqueada con igual o mayor alcance no hace nada; si estaba solo para este
     * establecimiento y ahora es ANULADO_SRI, la amplía a todo el RUC.
     *
     * @param array $doc ['modulo' => 'Compras', 'documento' => 'Factura 001-001-15',
     *                    'emisor' => razón social, 'fecha' => Y-m-d, 'tabla' => tabla origen, 'id' => id origen]
     * @return string aviso para el usuario
     */
    public function registrar(string $clave, string $motivo, string $observacion, array $doc, int $idEmpresa, int $idUsuario): string
    {
        $clave   = trim($clave);
        $todoRuc = $motivo === DocumentoIgnoradoRepository::MOTIVO_ANULADO_SRI;
        $avisoOk = $todoRuc
            ? 'Se agregó a «Documentos para no descargar»: no se volverá a descargar en ningún establecimiento.'
            : 'Se agregó a «Documentos para no descargar» solo en este establecimiento: ya puede descargarlo o cargar su XML en el otro.';

        $documento = trim((string) ($doc['documento'] ?? ''));
        $texto = 'Eliminado desde ' . ($doc['modulo'] ?? 'el sistema')
            . ($documento !== '' ? " ({$documento})" : '')
            . ': ' . ($todoRuc ? 'anulado en el SRI o emitido por error' : 'se registrará en otro establecimiento')
            . ($observacion !== '' ? '. ' . $observacion : '');
        $origen = ['tabla_origen' => $doc['tabla'] ?? null, 'id_origen' => isset($doc['id']) ? (int) $doc['id'] : null];

        $propia = $this->repo->getVigente($clave, $idEmpresa);
        if ($propia) {
            $soloEst = in_array($propia['solo_establecimiento'], [true, 't', 'true', 1, '1'], true);
            if ($todoRuc && $soloEst && $this->repo->tieneAlcance()) {
                $this->repo->ampliarATodoElRuc((int) $propia['id'], $motivo, $texto, $idUsuario);
                $this->log->registrar(
                    $idUsuario, $idEmpresa, 'ACTUALIZAR', 'documentos_ignorados_sri', (int) $propia['id'],
                    ['solo_establecimiento' => true], ['solo_establecimiento' => false, 'motivo' => $motivo] + $origen
                );
                return $avisoOk;
            }
            return 'El documento ya estaba en «Documentos para no descargar».';
        }

        $idsGrupo = (new EmpresaRepository())->getIdsEmpresaMismoRuc($idEmpresa);
        // Otro establecimiento del RUC ya la bloqueó para todos: no hace falta otra fila.
        if ($this->repo->existeClaveEnGrupo($clave, $idsGrupo, $idEmpresa)) {
            return 'El documento ya estaba en «Documentos para no descargar» para todos los establecimientos.';
        }

        $idFila = $this->repo->insertar([
            'id_empresa'           => $idEmpresa,
            'clave_acceso'         => $clave,
            'nombre_proveedor'     => $doc['emisor'] ?? '',
            'fecha_documento'      => $doc['fecha'] ?? '',
            'observaciones'        => $texto,
            'solo_establecimiento' => !$todoRuc,
            'motivo'               => $motivo,
            'id_usuario'           => $idUsuario,
        ]);
        $this->log->registrar(
            $idUsuario, $idEmpresa, 'CREAR', 'documentos_ignorados_sri', $idFila, null,
            [
                'clave_acceso'         => $clave,
                'motivo'               => $motivo,
                'solo_establecimiento' => !$todoRuc,
                'observaciones'        => $texto,
            ] + $origen
        );
        if (!$this->repo->tieneAlcance() && !$todoRuc) {
            // SQL de alcance aún no aplicado: la fila bloquea a todo el RUC.
            return 'Se agregó a «Documentos para no descargar» (para todos los establecimientos: falta actualizar la base de datos para limitarlo a este).';
        }
        return $avisoOk;
    }
}
