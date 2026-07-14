<?php
declare(strict_types=1);

namespace App\Services\Ia;

/**
 * Abstracción mínima de un proveedor de IA conversacional, para no acoplar
 * el módulo IA Soporte a un proveedor concreto (BYOK: cada empresa elige el suyo).
 */
interface IaProviderInterface
{
    /**
     * @param array<int, array{rol:string, contenido:string}> $mensajes Historial user/assistant (sin el system prompt).
     * @return array{contenido:string, tokens_entrada:int, tokens_salida:int}
     * @throws \RuntimeException si la llamada al proveedor falla.
     */
    public function chat(array $mensajes, string $promptSistema, string $apiKey, string $modelo): array;
}
