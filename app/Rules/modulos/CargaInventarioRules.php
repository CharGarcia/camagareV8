<?php
declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * Validaciones de negocio para Cargas de Inventario.
 */
class CargaInventarioRules
{
    public const TIPOS = ['entrada', 'salida', 'ajuste'];

    /**
     * Valida la cabecera de la carga. Lanza InvalidArgumentException si algo falla.
     */
    public function validarCabecera(array $data): void
    {
        $tipo = $data['tipo_movimiento'] ?? '';
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de movimiento inválido. Debe ser entrada, salida o ajuste.');
        }
        if (empty($data['filas']) || !is_array($data['filas'])) {
            throw new \InvalidArgumentException('La carga no contiene líneas para procesar.');
        }
    }

    /**
     * Valida la estructura básica de una línea (sin tocar BD).
     * Devuelve el mensaje de error o null si es válida a nivel estructural.
     */
    public function validarEstructuraLinea(array $fila): ?string
    {
        $cant = isset($fila['cantidad']) ? (float) $fila['cantidad'] : 0.0;
        if (empty($fila['id_producto'])) {
            return 'Falta el producto.';
        }
        if (empty($fila['id_bodega'])) {
            return 'Falta la bodega.';
        }
        if ($cant <= 0) {
            return 'La cantidad debe ser mayor a cero.';
        }
        return null;
    }

    /**
     * Solo se anula una carga aprobada: las pendientes y rechazadas no movieron el stock y
     * se eliminan. Igual que al aprobar, quien registró la carga no la anula (salvo el
     * superadministrador). Lo que depende de la base (aprobadores, movimientos, período,
     * bodegas y uso posterior) lo comprueba CargaInventarioService.
     */
    public function validarAnulable(?array $carga, int $idUsuario, int $nivel): void
    {
        if (!$carga) {
            throw new \InvalidArgumentException('Carga no encontrada.');
        }
        $estado = (string) ($carga['estado'] ?? '');
        if ($estado === 'anulada') {
            throw new \InvalidArgumentException('La carga ya está anulada.');
        }
        if ($estado !== 'aprobada') {
            throw new \InvalidArgumentException('Solo se anulan cargas aprobadas. Una carga pendiente o rechazada no movió el stock: se elimina.');
        }
        if ($nivel < 3 && (int) ($carga['created_by'] ?? 0) === $idUsuario) {
            throw new \InvalidArgumentException('No puede anular una carga que usted mismo registró. Debe anularla otro aprobador.');
        }
    }

    public function validarMotivoAnulacion(string $motivo): void
    {
        if (trim($motivo) === '') {
            throw new \InvalidArgumentException('Indique el motivo de la anulación.');
        }
    }

    // ─── Series (NUP) ─────────────────────────────────────────────────────────

    /**
     * Series de la celda NUP: una por renglón (Alt+Enter en Excel), sin vacíos. Mismo corte
     * que hace InventarioService::ajusteManual() cuando registra una serie por movimiento.
     *
     * @return list<string>
     */
    public static function seriesDeNup(?string $nup): array
    {
        $series = array_map('trim', preg_split('/\r\n|\r|\n/', (string) $nup) ?: []);
        return array_values(array_filter($series, static fn(string $s): bool => $s !== ''));
    }

    /**
     * NUP de una línea de entrada o salida. Con UNA serie la línea mueve su cantidad completa,
     * igual que ventas, compras e importaciones. Con VARIAS, cada serie es una unidad (un
     * movimiento por serie), así que la cantidad debe ser igual al número de series.
     */
    public function validarCantidadSeries(float $cantidad, array $series): ?string
    {
        $n = count($series);
        if ($n > 1 && abs($cantidad - $n) > 0.000001) {
            return "La celda NUP trae {$n} series y la cantidad es " . self::numero($cantidad)
                . ': con varias series, la cantidad debe ser igual al número de series.';
        }
        return null;
    }

    // ─── Ajuste por conteo físico ─────────────────────────────────────────────

    /** "Sin lote" escrito a mano en la columna de lote equivale a no indicar lote. */
    public static function normalizarLote($lote): ?string
    {
        $lote = trim((string) $lote);
        return ($lote === '' || preg_match('/^sin[\s_]*lote$/i', $lote) === 1) ? null : $lote;
    }

    /**
     * Línea de una carga de ajuste: la cantidad es lo contado, así que puede ser 0 pero no
     * negativa ni faltar (una celda vacía no se toma como cero), y no lleva NUP: las series
     * se registran con cargas de entrada o de salida.
     *
     * @param mixed $cantidadCelda Valor tal como vino en el archivo.
     */
    public function validarLineaAjuste($cantidadCelda, ?string $nup): ?string
    {
        $texto = trim((string) $cantidadCelda);
        if ($texto === '' || !is_numeric($texto)) {
            return 'Falta la cantidad contada o no es un número (escriba 0 si no hay unidades).';
        }
        if ((float) $texto < 0) {
            return 'La cantidad contada no puede ser negativa.';
        }
        if (self::seriesDeNup($nup) !== []) {
            return 'Una carga de ajuste no admite NUP: registre las series con una carga de entrada o de salida.';
        }
        return null;
    }

    /**
     * En un ajuste cada producto se cuenta UNA vez por bodega y lote, y en total (sin lote) o
     * por lotes, no de las dos formas: dos conteos del mismo saldo no pueden aplicarse ambos.
     * Las líneas sin producto o sin bodega (ya con su propio error) se omiten; las que tienen
     * otro error sí cuentan, porque el producto se repite igual.
     *
     * @param array<int, array{id_producto:int, id_bodega:int, numero_lote:?string}> $lineas
     *        En el orden del archivo (índice 0 = fila 2 del Excel).
     * @return array<int, string> Índice de la línea => error.
     */
    public function erroresDuplicadosAjuste(array $lineas): array
    {
        $vistos  = [];   // "producto:bodega:lote" => fila
        $formas  = [];   // "producto:bodega" => ['total' => fila|null, 'lotes' => fila|null]
        $errores = [];

        foreach (array_values($lineas) as $i => $ln) {
            if (empty($ln['id_producto']) || empty($ln['id_bodega'])) {
                continue;
            }
            $fila   = $i + 2;
            $pb     = (int) $ln['id_producto'] . ':' . (int) $ln['id_bodega'];
            $lote   = $ln['numero_lote'] ?? null;
            $clave  = $pb . ':' . ($lote ?? '');
            $formas[$pb] ??= ['total' => null, 'lotes' => null];

            if (isset($vistos[$clave])) {
                $errores[$i] = 'Este producto ya se contó en la fila ' . $vistos[$clave] . ' para la misma bodega'
                    . ($lote !== null ? " y el mismo lote ({$lote})" : '') . '.';
                continue;
            }
            $otra = $lote === null ? $formas[$pb]['lotes'] : $formas[$pb]['total'];
            if ($otra !== null) {
                $errores[$i] = 'Este producto ya se contó ' . ($lote === null ? 'por lotes' : 'en total (sin lote)')
                    . " en esta bodega (fila {$otra}): cuéntelo por lotes o en total, no de las dos formas.";
                continue;
            }

            $vistos[$clave] = $fila;
            $formas[$pb][$lote === null ? 'total' : 'lotes'] ??= $fila;
        }
        return $errores;
    }

    /** Cantidad sin ceros de relleno: 10, 2.5, 0.125. */
    public static function numero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 6, '.', ''), '0'), '.');
    }
}
