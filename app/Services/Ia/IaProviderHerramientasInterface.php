<?php
declare(strict_types=1);

namespace App\Services\Ia;

/**
 * Proveedor de IA que puede usar herramientas (lo usa el asistente virtual del
 * CRM, docs/diseno/crm-redes-fase2.md). El proveedor maneja su propio formato
 * de mensajes y el ciclo pedir herramienta → ejecutar → seguir; el sistema solo
 * le da las definiciones y una función que ejecuta cada herramienta.
 */
interface IaProviderHerramientasInterface
{
    /**
     * @param array<int, array{rol:string, contenido:string}> $historial user/assistant, del más antiguo al más nuevo.
     * @param array<int, array{nombre:string, descripcion:string, esquema:array}> $herramientas Esquema JSON de la entrada.
     * @param callable(string $nombre, array $entrada): string $ejecutar Devuelve el resultado como texto (o JSON).
     * @param array{modelo:string, api_key:string, esfuerzo?:?string, max_tokens?:int, max_vueltas?:int, sistema_variable?:string} $opciones
     *        sistema_variable: instrucciones que cambian en cada llamada; van después de $promptSistema
     *        (que el proveedor puede guardar en caché).
     * @return array{
     *   texto:string, rechazado:bool, motivo_fin:string, herramientas:list<string>,
     *   tokens_entrada:int, tokens_salida:int, tokens_cache:int, costo_estimado:float, modelo:string
     * }
     * @throws \RuntimeException con un mensaje en español sin datos sensibles si la llamada falla.
     */
    public function conversar(string $promptSistema, array $historial, array $herramientas, callable $ejecutar, array $opciones): array;
}
