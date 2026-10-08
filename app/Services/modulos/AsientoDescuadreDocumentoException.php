<?php

namespace App\Services\modulos;

/**
 * El asiento de un documento no cuadra aunque TODAS las cuentas estén configuradas: el importe
 * total del documento no es subtotal + IVA + ICE + propina (un rubro que el emisor metió en el
 * total sin declararlo como impuesto, o un comprobante cuyos totales no cuadran consigo mismos).
 *
 * La lanza AsientoBuilderService::aplicarAjusteRedondeo() en la rama "sin reglas sin cuenta".
 * Hereda de \Exception y conserva el mismo mensaje de siempre, así que todo llamador que ya
 * capturaba \Exception/\Throwable —y el SincronizadorAsientosService, que agrupa por el texto
 * «supera el máximo de ajuste»— se comporta exactamente igual.
 *
 * Lo nuevo es que lleva las líneas que el builder sí pudo armar: Compras las usa para que, en un
 * comprobante electrónico del SRI (que no se puede corregir), el usuario complete el asiento a
 * mano desde la pestaña «Asiento contable» en vez de quedarse sin contabilizarlo.
 */
class AsientoDescuadreDocumentoException extends \Exception
{
    /** @var array<int, array<string, mixed>> */
    private array $detalles;
    private float $totalDebe;
    private float $totalHaber;

    public function __construct(string $mensaje, array $detalles, float $totalDebe, float $totalHaber)
    {
        parent::__construct($mensaje);
        $this->detalles   = $detalles;
        $this->totalDebe  = $totalDebe;
        $this->totalHaber = $totalHaber;
    }

    /** Líneas del asiento tal como las armó el builder (descuadradas). */
    public function getDetalles(): array
    {
        return $this->detalles;
    }

    public function getTotalDebe(): float
    {
        return $this->totalDebe;
    }

    public function getTotalHaber(): float
    {
        return $this->totalHaber;
    }

    /** Debe − Haber (positivo: falta Haber; negativo: falta Debe). */
    public function getDiferencia(): float
    {
        return round($this->totalDebe - $this->totalHaber, 2);
    }
}
