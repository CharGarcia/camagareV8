<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\CajaMovimientoRepository;

/**
 * Validaciones de los traslados entre formas de pago y de los saldos de apertura.
 */
class CajaMovimientoRules
{
    private CajaMovimientoRepository $repo;

    public function __construct(CajaMovimientoRepository $repo)
    {
        $this->repo = $repo;
    }

    private static function fecha(string $fecha, string $campo): string
    {
        $d = \DateTime::createFromFormat('!Y-m-d', trim($fecha));
        if (!$d || $d->format('Y-m-d') !== trim($fecha)) {
            throw new \InvalidArgumentException("La {$campo} no es válida.");
        }
        return trim($fecha);
    }

    /** Devuelve el traslado normalizado o lanza InvalidArgumentException. */
    public function validarTraslado(array $d, int $idEmpresa): array
    {
        $t = [
            'fecha'            => self::fecha((string) ($d['fecha'] ?? ''), 'fecha del traslado'),
            'id_forma_origen'  => (int) ($d['id_forma_origen'] ?? 0),
            'id_forma_destino' => (int) ($d['id_forma_destino'] ?? 0),
            'valor'            => round((float) ($d['valor'] ?? 0), 2),
            'observaciones'    => mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 300),
        ];
        if ($t['id_forma_origen'] <= 0 || $t['id_forma_destino'] <= 0) {
            throw new \InvalidArgumentException('Elija la forma de pago de origen y la de destino.');
        }
        if ($t['id_forma_origen'] === $t['id_forma_destino']) {
            throw new \InvalidArgumentException('La forma de pago de origen y la de destino deben ser distintas.');
        }
        if ($t['valor'] <= 0) {
            throw new \InvalidArgumentException('El valor del traslado debe ser mayor que cero.');
        }
        foreach ([$t['id_forma_origen'], $t['id_forma_destino']] as $idForma) {
            if (!$this->repo->formaEsDeEmpresa($idForma, $idEmpresa)) {
                throw new \InvalidArgumentException('Una de las formas de pago no existe en esta empresa.');
            }
        }
        return $t;
    }

    /**
     * Aperturas enviadas desde el formulario: id_forma => [fecha, valor]. Una fila sin
     * fecha se toma como "quitar la apertura" de esa forma.
     *
     * @return array<int, array{fecha: string, valor: float}|null>
     */
    public function validarAperturas(array $filas, int $idEmpresa): array
    {
        $out = [];
        foreach ($filas as $f) {
            $idForma = (int) ($f['id_forma_pago'] ?? 0);
            if ($idForma <= 0) {
                continue;
            }
            if (!$this->repo->formaEsDeEmpresa($idForma, $idEmpresa)) {
                throw new \InvalidArgumentException('Una de las formas de pago no existe en esta empresa.');
            }
            $fecha = trim((string) ($f['fecha'] ?? ''));
            if ($fecha === '') {
                $out[$idForma] = null;
                continue;
            }
            $out[$idForma] = ['fecha' => self::fecha($fecha, 'fecha de apertura'), 'valor' => round((float) ($f['valor'] ?? 0), 2)];
        }
        return $out;
    }
}
