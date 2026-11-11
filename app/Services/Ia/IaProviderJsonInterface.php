<?php
declare(strict_types=1);

namespace App\Services\Ia;

/**
 * Proveedor de IA que devuelve una respuesta con estructura fija (esquema JSON)
 * y puede mirar imágenes. Lo usa la redacción de publicaciones del CRM
 * (docs/diseno/crm-redes-fase3.md, paso 5).
 */
interface IaProviderJsonInterface
{
    /**
     * @param list<array{ruta:string, mime:string, etiqueta:string}> $imagenes Archivos locales (JPEG/PNG/GIF/WEBP).
     * @param array $esquema Esquema JSON del resultado (objeto, additionalProperties false).
     * @param array{modelo:string, api_key:string, esfuerzo?:?string, max_tokens?:int, timeout?:float, documentos?:list<array{ruta:string, etiqueta?:string}>} $opciones
     *        `documentos`: archivos PDF locales que el modelo debe leer (opcional).
     * @return array{datos:array, rechazado:bool, tokens_entrada:int, tokens_salida:int, tokens_cache:int, costo_estimado:float, modelo:string}
     * @throws \RuntimeException con un mensaje en español sin datos sensibles si la llamada falla.
     */
    public function generarJson(string $promptSistema, string $instruccion, array $imagenes, array $esquema, array $opciones): array;
}
