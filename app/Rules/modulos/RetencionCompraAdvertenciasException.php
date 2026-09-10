<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use RuntimeException;

/**
 * La retención pasó las validaciones que impiden guardar, pero quedaron
 * advertencias de criterio tributario (plazo de emisión, porcentaje distinto al
 * del catálogo, base bajo el mínimo…) que el usuario tiene que confirmar.
 *
 * El guardado se detiene igual: no es un aviso que se deja pasar solo. El
 * controlador la traduce a `requiere_confirmacion` y el formulario reenvía la
 * misma petición con `confirmar_advertencias` cuando el usuario acepta.
 */
class RetencionCompraAdvertenciasException extends RuntimeException
{
    /** @var array<int, array{texto:string, base_legal:string}> */
    private array $advertencias;

    /** @param array<int, array{texto:string, base_legal:string}|string> $advertencias */
    public function __construct(array $advertencias)
    {
        // Cada advertencia lleva su texto y el sustento normativo que el usuario
        // puede ir a comprobar. Se admite también una cadena suelta por si algún
        // llamador antiguo las arma así.
        $this->advertencias = array_values(array_map(
            static fn($a) => is_array($a)
                ? ['texto' => (string) ($a['texto'] ?? ''), 'base_legal' => (string) ($a['base_legal'] ?? '')]
                : ['texto' => (string) $a, 'base_legal' => ''],
            $advertencias
        ));

        parent::__construct(implode(' ', array_column($this->advertencias, 'texto')));
    }

    /** @return array<int, array{texto:string, base_legal:string}> */
    public function getAdvertencias(): array
    {
        return $this->advertencias;
    }
}
