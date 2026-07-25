<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DocumentoOrigenAsiento;
use App\repositories\modulos\DocumentoOrigenRepository;

/**
 * Arma el detalle del documento que originó un asiento (factura, compra, egreso…) para el modal
 * de solo lectura que se abre desde la columna "Documento Ref." de Mayores y del mayor auxiliar
 * de Estados Financieros.
 *
 * Es solo consulta: no modifica nada y no reemplaza al módulo emisor, que sigue siendo el único
 * lugar donde el documento se edita.
 */
class DocumentoOrigenService
{
    private DocumentoOrigenRepository $repository;

    public function __construct(?DocumentoOrigenRepository $repository = null)
    {
        $this->repository = $repository ?: new DocumentoOrigenRepository();
    }

    /**
     * @return array{
     *     etiqueta: string, numero: string, fecha: string, estado: ?string, observaciones: ?string,
     *     tercero: ?string, tercero_identificacion: ?string,
     *     totales: array<int, array{label: string, valor: string}>,
     *     columnas: array<int, array{label: string, numerica: bool}>,
     *     lineas: array<int, array<int, string>>
     * }
     * @throws \InvalidArgumentException si el módulo no tiene documento o el documento no existe
     */
    public function getDetalle(string $modulo, int $idDocumento, int $idEmpresa): array
    {
        $doc = DocumentoOrigenAsiento::paraModulo($modulo);
        if ($doc === null) {
            throw new \InvalidArgumentException('Este asiento no tiene un documento que se pueda mostrar.');
        }

        $cab = $this->repository->getCabecera($modulo, $idDocumento, $idEmpresa);
        if ($cab === null) {
            throw new \InvalidArgumentException('El documento ya no existe o pertenece a otra empresa.');
        }

        // Totales: el repository los devuelve como total_0, total_1… en el orden del mapa.
        $totales = [];
        $i = 0;
        foreach (array_keys($doc['totales']) as $label) {
            $totales[] = ['label' => $label, 'valor' => $this->dinero($cab['total_' . $i] ?? null)];
            $i++;
        }

        $numericas = $doc['detalle']['numericas'] ?? [];
        $columnas = [];
        foreach (array_keys($doc['detalle']['columnas']) as $label) {
            $columnas[] = ['label' => $label, 'numerica' => in_array($label, $numericas, true)];
        }

        $lineas = [];
        foreach ($this->repository->getLineas($modulo, $idDocumento) as $fila) {
            $valores = [];
            foreach (array_values($fila) as $pos => $valor) {
                $valores[] = ($columnas[$pos]['numerica'] ?? false) ? $this->dinero($valor) : (string) ($valor ?? '');
            }
            $lineas[] = $valores;
        }

        return [
            'etiqueta' => $doc['etiqueta'],
            'numero' => (string) ($cab['numero'] ?? ''),
            'fecha' => $this->fecha($cab['fecha'] ?? null),
            'estado' => $cab['estado'] !== null && $cab['estado'] !== '' ? (string) $cab['estado'] : null,
            'observaciones' => $cab['observaciones'] !== null && trim((string) $cab['observaciones']) !== ''
                ? (string) $cab['observaciones'] : null,
            'tercero' => $cab['tercero'] !== null && $cab['tercero'] !== '' ? (string) $cab['tercero'] : null,
            'tercero_identificacion' => $cab['tercero_identificacion'] ?? null,
            'totales' => $totales,
            'columnas' => $columnas,
            'lineas' => $lineas,
        ];
    }

    /** Formato de fecha del sistema (d-m-Y). */
    private function fecha(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $ts = strtotime($valor);
        return $ts ? date('d-m-Y', $ts) : (string) $valor;
    }

    private function dinero(?string $valor): string
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return (string) ($valor ?? '');
        }
        return number_format((float) $valor, 2, '.', ',');
    }
}
