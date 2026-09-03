<?php

declare(strict_types=1);

namespace App\Rules\modulos;

class ProformaRules
{
    public function validar(array $data): array
    {
        $errores = [];

        if (empty($data['fecha_emision'])) {
            $errores[] = 'La fecha de emisión es obligatoria.';
        }

        if (empty($data['id_cliente']) || (int) $data['id_cliente'] <= 0) {
            $errores[] = 'Debe seleccionar un cliente.';
        }

        if (empty($data['id_establecimiento']) || (int) $data['id_establecimiento'] <= 0) {
            $errores[] = 'Debe seleccionar un establecimiento.';
        }

        if (empty($data['id_punto_emision']) || (int) $data['id_punto_emision'] <= 0) {
            $errores[] = 'Debe seleccionar un punto de emisión.';
        }

        if (empty($data['secuencial'])) {
            $errores[] = 'El secuencial es obligatorio.';
        }

        if (empty($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
            $errores[] = 'Debe agregar al menos un ítem a la proforma.';
        } else {
            foreach ($data['detalles'] as $i => $det) {
                $fila = $i + 1;
                if (empty($det['descripcion'])) {
                    $errores[] = "Fila {$fila}: la descripción es obligatoria.";
                }
                if (!isset($det['cantidad']) || (float) $det['cantidad'] <= 0) {
                    $errores[] = "Fila {$fila}: la cantidad debe ser mayor a cero.";
                }
                if (!isset($det['precio_unitario']) || (float) $det['precio_unitario'] < 0) {
                    $errores[] = "Fila {$fila}: el precio unitario no puede ser negativo.";
                }
            }
        }

        $diasVigencia = (int) ($data['dias_vigencia'] ?? 15);
        if ($diasVigencia < 1 || $diasVigencia > 3650) {
            $errores[] = 'Los días de vigencia deben estar entre 1 y 3650.';
        }

        return $errores;
    }

    /**
     * Limpia el HTML de las "Condiciones" (editor enriquecido). Ese mismo HTML se
     * vuelve a inyectar en el editor al abrir la proforma y se imprime con TCPDF,
     * así que a la BD solo pueden llegar etiquetas de formato: se quitan scripts,
     * atributos de evento, estilos ajenos al formato y enlaces que no sean
     * http/https/mailto. Devuelve NULL si no queda texto real.
     */
    public static function sanitizarCondiciones(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '' || $html === '<p><br></p>') {
            return null;
        }

        // Bloques ejecutables/no visibles: fuera con todo su contenido (strip_tags solo quita la etiqueta).
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

        $permitidas = '<p><br><strong><b><em><i><u><s><strike><h1><h2><h3><h4>'
                    . '<ol><ul><li><a><span><blockquote><sub><sup><div>';
        $html = strip_tags($html, $permitidas);

        $html = preg_replace_callback('/<([a-z][a-z0-9]*)\b([^>]*)>/i', static function (array $m): string {
            $tag   = strtolower($m[1]);
            $attrs = $m[2];
            $keep  = '';

            if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs, $c)) {
                $clases = array_filter(
                    explode(' ', $c[1]),
                    static fn($x) => (bool) preg_match('/^ql-(align|indent)-[a-z0-9]+$/', $x)
                );
                if ($clases) {
                    $keep .= ' class="' . implode(' ', $clases) . '"';
                }
            }

            if (preg_match('/\bstyle\s*=\s*"([^"]*)"/i', $attrs, $s)) {
                $decl = [];
                foreach (explode(';', $s[1]) as $d) {
                    if (preg_match('/^\s*(color|background-color|text-align)\s*:\s*([#a-z0-9(),.\s%-]+)\s*$/i', $d, $dm)) {
                        $decl[] = strtolower($dm[1]) . ':' . trim($dm[2]);
                    }
                }
                if ($decl) {
                    $keep .= ' style="' . implode(';', $decl) . '"';
                }
            }

            if ($tag === 'a' && preg_match('/\bhref\s*=\s*"([^"]*)"/i', $attrs, $h)) {
                $href = trim(html_entity_decode($h[1]));
                if (preg_match('#^(https?://|mailto:)#i', $href)) {
                    $keep .= ' href="' . htmlspecialchars($href, ENT_QUOTES) . '" target="_blank" rel="noopener"';
                }
            }

            return '<' . $tag . $keep . '>';
        }, $html) ?? '';

        $texto = trim(html_entity_decode(strip_tags($html)));
        return ($texto === '' || $texto === "\u{A0}") ? null : $html;
    }
}
