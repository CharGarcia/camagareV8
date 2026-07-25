<?php
/**
 * MarkdownSimple — conversor Markdown → HTML para el Manual del Sistema.
 *
 * Es un conversor ACOTADO a propósito: cubre exactamente lo que se usa al
 * documentar módulos, sin dependencias externas ni descargas (el despliegue del
 * sistema es "git pull", así que una librería más significaría un paso más).
 *
 * ─── QUÉ SOPORTA ────────────────────────────────────────────────────────────
 *   ## Título          → <h2>   (también # se convierte en h2: el h1 de la
 *   ### Subtítulo      → <h3>    página ya es el título del artículo)
 *   #### Apartado      → <h4>
 *   - item / * item    → <ul>  (con un nivel de sublista por sangría)
 *   1. item            → <ol>
 *   > cita             → <blockquote>
 *   ```                → <pre><code>
 *   | a | b |          → <table> (tabla estilo GitHub, con fila separadora)
 *   ---                → <hr>
 *   **negrita**  *cursiva*  `código`  [texto](url)
 *
 * ─── QUÉ NO SOPORTA (a propósito) ───────────────────────────────────────────
 *   - HTML crudo dentro del Markdown: se escapa y se ve como texto. Si hace
 *     falta HTML puntual, se escribe el artículo desde la pantalla de gestión.
 *   - Imágenes por URL externa, notas al pie, listas de tareas, texto tachado.
 *
 * La salida vuelve a pasar por HTMLPurifier en DocumentacionService antes de
 * guardarse, así que este conversor no es la única línea de defensa.
 */

declare(strict_types=1);

namespace App\lib;

final class MarkdownSimple
{
    /** Marcador interno para proteger el código en línea del resto del formateo. */
    private const MARCA = "\x00CODE%d\x00";

    /**
     * Separa el front-matter YAML simple de la cabecera del archivo.
     *
     * Formato esperado (solo pares "clave: valor", sin listas ni anidación):
     *
     *   ---
     *   titulo: Clientes
     *   categoria: Ventas
     *   ---
     *   ## Contenido…
     *
     * @return array{0:array<string,string>,1:string} [metadatos, cuerpo Markdown]
     */
    public static function separarFrontMatter(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", ltrim($texto, "\xEF\xBB\xBF \n"));

        if (!str_starts_with($texto, '---')) {
            return [[], $texto];
        }

        $lineas = explode("\n", $texto);
        array_shift($lineas); // el '---' de apertura

        $meta = [];
        $cierre = false;
        while (($linea = array_shift($lineas)) !== null) {
            if (rtrim($linea) === '---') {
                $cierre = true;
                break;
            }
            $pos = strpos($linea, ':');
            if ($pos === false) {
                continue;
            }
            $clave = trim(substr($linea, 0, $pos));
            $valor = trim(substr($linea, $pos + 1));
            // Quitar comillas envolventes si las hay.
            if (strlen($valor) >= 2
                && (($valor[0] === '"' && str_ends_with($valor, '"'))
                    || ($valor[0] === "'" && str_ends_with($valor, "'")))) {
                $valor = substr($valor, 1, -1);
            }
            if ($clave !== '') {
                $meta[strtolower($clave)] = $valor;
            }
        }

        // Sin cierre no era front-matter: se devuelve el texto original intacto.
        return $cierre ? [$meta, implode("\n", $lineas)] : [[], $texto];
    }

    /** Convierte el cuerpo Markdown a HTML. */
    public static function aHtml(string $markdown): string
    {
        $lineas = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $n = count($lineas);
        $i = 0;
        $html = [];

        while ($i < $n) {
            $linea = $lineas[$i];
            $trim  = trim($linea);

            if ($trim === '') {
                $i++;
                continue;
            }

            // Bloque de código ```
            if (str_starts_with($trim, '```')) {
                $html[] = self::bloqueCodigo($lineas, $i, $n);
                continue;
            }

            // Regla horizontal
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim) === 1) {
                $html[] = '<hr>';
                $i++;
                continue;
            }

            // Encabezado
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trim, $m) === 1) {
                $nivel = strlen($m[1]);
                $tag = match (true) {
                    $nivel <= 2 => 'h2',   // '#' y '##' → h2
                    $nivel === 3 => 'h3',
                    default => 'h4',
                };
                $html[] = '<' . $tag . '>' . self::enLinea($m[2]) . '</' . $tag . '>';
                $i++;
                continue;
            }

            // Cita
            if (str_starts_with($trim, '>')) {
                $html[] = self::bloqueCita($lineas, $i, $n);
                continue;
            }

            // Tabla: requiere la fila separadora |---|---|
            if (str_starts_with($trim, '|')
                && isset($lineas[$i + 1])
                && preg_match('/^\s*\|[\s:|-]+\|\s*$/', $lineas[$i + 1]) === 1) {
                $html[] = self::bloqueTabla($lineas, $i, $n);
                continue;
            }

            // Lista
            if (self::itemLista($linea) !== null) {
                $html[] = self::bloqueLista($lineas, $i, $n);
                continue;
            }

            // Párrafo: junta líneas hasta una vacía o el inicio de otro bloque.
            $parrafo = [];
            while ($i < $n) {
                $l = $lineas[$i];
                $t = trim($l);
                if ($t === '' || self::iniciaBloque($t) || self::itemLista($l) !== null) {
                    break;
                }
                $parrafo[] = $t;
                $i++;
            }
            if ($parrafo !== []) {
                $html[] = '<p>' . self::enLinea(implode(' ', $parrafo)) . '</p>';
            }
        }

        return implode("\n", $html);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Bloques
    // ────────────────────────────────────────────────────────────────────

    private static function bloqueCodigo(array $lineas, int &$i, int $n): string
    {
        $i++; // saltar la línea de apertura ```
        $cuerpo = [];
        while ($i < $n && !str_starts_with(trim($lineas[$i]), '```')) {
            $cuerpo[] = $lineas[$i];
            $i++;
        }
        $i++; // saltar el cierre ```

        return '<pre><code>'
             . htmlspecialchars(implode("\n", $cuerpo), ENT_QUOTES, 'UTF-8')
             . '</code></pre>';
    }

    private static function bloqueCita(array $lineas, int &$i, int $n): string
    {
        $cuerpo = [];
        while ($i < $n && str_starts_with(trim($lineas[$i]), '>')) {
            $cuerpo[] = trim(ltrim(trim($lineas[$i]), '>'));
            $i++;
        }
        return '<blockquote><p>' . self::enLinea(implode(' ', $cuerpo)) . '</p></blockquote>';
    }

    private static function bloqueTabla(array $lineas, int &$i, int $n): string
    {
        $celdas = static function (string $fila): array {
            $fila = trim($fila);
            $fila = preg_replace('/^\||\|$/', '', $fila) ?? $fila;
            return array_map('trim', explode('|', $fila));
        };

        $cabecera = $celdas($lineas[$i]);
        $i += 2; // saltar la cabecera y la fila separadora

        $html = "<table>\n<thead>\n<tr>";
        foreach ($cabecera as $c) {
            $html .= '<th>' . self::enLinea($c) . '</th>';
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";

        while ($i < $n && str_starts_with(trim($lineas[$i]), '|')) {
            $html .= '<tr>';
            foreach ($celdas($lineas[$i]) as $c) {
                $html .= '<td>' . self::enLinea($c) . '</td>';
            }
            $html .= "</tr>\n";
            $i++;
        }

        return $html . "</tbody>\n</table>";
    }

    /** Consume las líneas de una lista y la construye con sus sublistas. */
    private static function bloqueLista(array $lineas, int &$i, int $n): string
    {
        $items = [];
        while ($i < $n) {
            $item = self::itemLista($lineas[$i]);
            if ($item === null) {
                // Una línea en blanco entre dos items no corta la lista.
                if (trim($lineas[$i]) === ''
                    && isset($lineas[$i + 1])
                    && self::itemLista($lineas[$i + 1]) !== null) {
                    $i++;
                    continue;
                }
                break;
            }
            $items[] = $item;
            $i++;
        }

        $pos = 0;
        return self::renderLista($items, $pos, $items[0]['sangria']);
    }

    /**
     * Construye <ul>/<ol> desde la lista plana de items, anidando por sangría.
     *
     * @param array<int,array{sangria:int,tipo:string,texto:string}> $items
     */
    private static function renderLista(array $items, int &$pos, int $sangria): string
    {
        $tipo = $items[$pos]['tipo'];
        $total = count($items);
        $partes = [];

        while ($pos < $total && $items[$pos]['sangria'] >= $sangria) {
            if ($items[$pos]['sangria'] > $sangria) {
                // Sublista: cuelga del último item abierto.
                $sub = self::renderLista($items, $pos, $items[$pos]['sangria']);
                if ($partes !== []) {
                    $partes[count($partes) - 1] .= "\n" . $sub;
                } else {
                    $partes[] = $sub;
                }
                continue;
            }
            $partes[] = self::enLinea($items[$pos]['texto']);
            $pos++;
        }

        $li = '';
        foreach ($partes as $p) {
            $li .= '<li>' . $p . "</li>\n";
        }

        return $tipo === 'ol' ? "<ol>\n{$li}</ol>" : "<ul>\n{$li}</ul>";
    }

    /**
     * ¿La línea es un item de lista? Devuelve su sangría, tipo y texto.
     *
     * @return array{sangria:int,tipo:string,texto:string}|null
     */
    private static function itemLista(string $linea): ?array
    {
        if (preg_match('/^(\s*)([-*+]|\d+[.)])\s+(.*)$/', $linea, $m) !== 1) {
            return null;
        }
        // Una regla horizontal (---) no es un item de lista.
        if (preg_match('/^(-{3,}|\*{3,})$/', trim($linea)) === 1) {
            return null;
        }

        return [
            'sangria' => strlen(str_replace("\t", '    ', $m[1])),
            'tipo'    => ctype_digit($m[2][0]) ? 'ol' : 'ul',
            'texto'   => trim($m[3]),
        ];
    }

    /** ¿La línea abre un bloque distinto a un párrafo? (corta el párrafo actual) */
    private static function iniciaBloque(string $trim): bool
    {
        return str_starts_with($trim, '```')
            || str_starts_with($trim, '>')
            || str_starts_with($trim, '|')
            || preg_match('/^#{1,6}\s/', $trim) === 1
            || preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim) === 1;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Formato en línea
    // ────────────────────────────────────────────────────────────────────

    /**
     * Aplica el formato dentro de una línea. El código va primero y se aparta
     * con marcadores: lo que hay dentro de `comillas` no debe interpretarse
     * como negrita ni como enlace.
     */
    private static function enLinea(string $texto): string
    {
        $codigos = [];
        $texto = preg_replace_callback('/`([^`]+)`/', static function (array $m) use (&$codigos): string {
            $codigos[] = $m[1];
            return sprintf(self::MARCA, count($codigos) - 1);
        }, $texto) ?? $texto;

        // Todo lo demás se escapa: no se acepta HTML crudo en el Markdown.
        $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

        // Enlaces [texto](destino) — solo destinos seguros. El destino admite un
        // nivel de paréntesis balanceados, tanto para URLs que los llevan
        // (…/Ecuador_(país)) como para no dejar restos al descartar un destino
        // no permitido.
        $texto = preg_replace_callback(
            '/\[([^\]]+)\]\(((?:[^()\s]|\([^()]*\))+)\)/',
            static function (array $m): string {
                $destino = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
                if (preg_match('#^(https?://|/|\#)#i', $destino) !== 1) {
                    return $m[1]; // destino no permitido: queda solo el texto
                }
                $externo = preg_match('#^https?://#i', $destino) === 1;
                return '<a href="' . htmlspecialchars($destino, ENT_QUOTES, 'UTF-8') . '"'
                     . ($externo ? ' target="_blank" rel="noopener"' : '') . '>'
                     . $m[1] . '</a>';
            },
            $texto
        ) ?? $texto;

        // Negrita antes que cursiva: ** contiene *.
        $texto = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $texto) ?? $texto;
        $texto = preg_replace('/(?<![\w*])\*(?=\S)([^*]+?)(?<=\S)\*(?![\w*])/s', '<em>$1</em>', $texto) ?? $texto;
        $texto = preg_replace('/(?<![\w_])_(?=\S)([^_]+?)(?<=\S)_(?![\w_])/s', '<em>$1</em>', $texto) ?? $texto;

        // Devolver el código a su sitio, ya escapado.
        foreach ($codigos as $idx => $codigo) {
            $texto = str_replace(
                sprintf(self::MARCA, $idx),
                '<code>' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '</code>',
                $texto
            );
        }

        return $texto;
    }
}
