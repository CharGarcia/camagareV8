<?php
declare(strict_types=1);

namespace App\Services\Ia;

/**
 * Catálogo de proveedores de IA (BYOK, tabla ia_config). Un solo lugar para
 * saber cuáles hay, cuál es su modelo por defecto y cuáles admiten
 * herramientas (requisito del asistente virtual del CRM).
 */
final class IaProveedores
{
    public const PROVEEDORES = [
        'openai'    => ['nombre' => 'OpenAI', 'modelo' => 'gpt-4o-mini', 'herramientas' => false],
        'anthropic' => ['nombre' => 'Anthropic (Claude)', 'modelo' => AnthropicProvider::MODELO_DEFECTO, 'herramientas' => true],
    ];

    public static function crear(string $proveedor): IaProviderInterface
    {
        return match ($proveedor) {
            'openai'    => new OpenAiProvider(),
            'anthropic' => new AnthropicProvider(),
            default     => throw new \RuntimeException('Proveedor de IA no soportado: ' . $proveedor),
        };
    }

    /** Proveedor con herramientas, o null si el configurado no las admite. */
    public static function conHerramientas(string $proveedor): ?IaProviderHerramientasInterface
    {
        $p = isset(self::PROVEEDORES[$proveedor]) ? self::crear($proveedor) : null;
        return $p instanceof IaProviderHerramientasInterface ? $p : null;
    }

    /** Proveedor que devuelve respuestas con esquema e imágenes, o null. */
    public static function conJson(string $proveedor): ?IaProviderJsonInterface
    {
        $p = isset(self::PROVEEDORES[$proveedor]) ? self::crear($proveedor) : null;
        return $p instanceof IaProviderJsonInterface ? $p : null;
    }

    public static function modeloDefecto(string $proveedor): string
    {
        return self::PROVEEDORES[$proveedor]['modelo'] ?? '';
    }
}
