<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Detector de "valores de terceros" en el bloque <infoAdicional> de un comprobante.
 *
 * Las planillas de servicios básicos (energía eléctrica, agua) recaudan por cuenta
 * de terceros rubros que NO forman parte del <importeTotal> ni de las bases de IVA:
 * contribución al Cuerpo de Bomberos, tasa de recolección de basura, tasa de
 * seguridad ciudadana, etc. El SRI no tiene un nodo propio para eso, así que cada
 * distribuidora los publica como campos libres de <infoAdicional>, con nombres
 * distintos entre sí. Ejemplo real (Empresa Eléctrica Quito):
 *
 *   CONTRIBUCION BOMBEROS                          = 2.41
 *   TASA RECOLECCION BASURA                        = 0.00
 *   FORMA DE PAGO TERCEROS BASURA Y BOMBEROS       = SIN UTILIZACION DEL SISTEMA FINANCIERO
 *   TOTAL FORMA DE PAGO TERCEROS BASURA Y BOMBEROS = 2.41
 *
 * El importe de la factura es 66.59, pero en la ventanilla se pagan 69.00. Esta clase
 * separa el grano de la paja: devuelve el desglose de rubros y su total, ignorando los
 * campos que son texto y los que son el TOTAL ya declarado por el emisor (sumarlos
 * duplicaría el valor).
 *
 * Deliberadamente NO se toca importe_total: para el SRI, el ATS y la declaración de
 * IVA la factura vale 66.59. El total de terceros viaja aparte.
 */
class RubrosTerceros
{
    /**
     * Fragmentos que identifican un rubro recaudado para un tercero.
     * Se comparan sobre el nombre del campo normalizado (mayúsculas, sin acentos).
     */
    private const PATRONES_RUBRO = [
        'BOMBERO',            // CONTRIBUCION BOMBEROS / CUERPO DE BOMBEROS
        'BASURA',             // TASA RECOLECCION BASURA / TASA DE BASURA
        'RECOLECCION',        // TASA DE RECOLECCION DE DESECHOS
        'SEGURIDAD CIUDADANA',
        'ALUMBRADO PUBLICO GENERAL', // solo si llega por infoAdicional (normalmente es detalle)
        'TERCEROS',           // comodín: cualquier "… TERCEROS …" numérico
    ];

    /**
     * Fragmentos que marcan un campo como TOTAL declarado del bloque de terceros
     * (no es un rubro más: es la suma que el emisor ya calculó).
     */
    private const PATRONES_TOTAL = [
        'TOTAL FORMA DE PAGO TERCEROS',
        'TOTAL TERCEROS',
        'TOTAL VALORES DE TERCEROS',
        'TOTAL RECAUDACION TERCEROS',
    ];

    /**
     * Analiza los campos adicionales de un comprobante.
     *
     * @param array $campos Mapa ['NOMBRE' => 'valor'] o lista de ['nombre' => …, 'valor' => …].
     * @return array{items: array<int, array{nombre: string, valor: float}>, total: float, total_declarado: float|null}
     *         `items` es el desglose de rubros detectados; `total` el monto a sumar al pago
     *         (el declarado por el emisor si existe, si no la suma de los rubros);
     *         `total_declarado` el valor del campo TOTAL cuando el emisor lo envía.
     */
    public static function detectar(array $campos): array
    {
        $items          = [];
        $totalDeclarado = null;

        foreach (self::normalizarEntrada($campos) as [$nombre, $valor]) {
            $monto = self::aMonto($valor);
            if ($monto === null) {
                continue; // campo de texto (nombre del banco, cuenta contrato, leyendas…)
            }

            $clave = self::sinAcentos($nombre);

            if (self::coincide($clave, self::PATRONES_TOTAL)) {
                $totalDeclarado = $monto;
                continue;
            }

            if (self::coincide($clave, self::PATRONES_RUBRO)) {
                $items[] = ['nombre' => trim($nombre), 'valor' => $monto];
            }
        }

        // Sin rubros reconocidos pero con un TOTAL declarado, se toma ese total: el
        // desglose es opcional y el campo TOTAL ya dice explícitamente que es de
        // terceros (los patrones que lo identifican exigen esa palabra), así que no
        // hay riesgo de contar dos veces ni de confundirlo con el total de la factura.
        if (empty($items)) {
            return [
                'items'           => [],
                'total'           => $totalDeclarado !== null ? round($totalDeclarado, 2) : 0.0,
                'total_declarado' => $totalDeclarado,
            ];
        }

        $suma = round(array_sum(array_column($items, 'valor')), 2);

        // El total declarado manda cuando existe (es el que la empresa cobra en
        // ventanilla); la suma de rubros es el respaldo cuando no viene.
        $total = $totalDeclarado !== null ? $totalDeclarado : $suma;

        return [
            'items'           => $items,
            'total'           => round($total, 2),
            'total_declarado' => $totalDeclarado,
        ];
    }

    /** Atajo: solo el monto a sumar al pago. */
    public static function total(array $campos): float
    {
        return self::detectar($campos)['total'];
    }

    /** Acepta tanto el mapa nombre=>valor como la lista de filas de compras_adicional. */
    private static function normalizarEntrada(array $campos): array
    {
        $out = [];
        foreach ($campos as $k => $v) {
            if (is_array($v)) {
                $out[] = [(string) ($v['nombre'] ?? ''), (string) ($v['valor'] ?? '')];
            } else {
                $out[] = [(string) $k, (string) $v];
            }
        }
        return $out;
    }

    private static function coincide(string $clave, array $patrones): bool
    {
        foreach ($patrones as $p) {
            if (str_contains($clave, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convierte el valor a monto solo si el campo ES un número.
     * "2.41" → 2.41 · "0.00" → 0.0 · "1410094983" → 1410094983.0 (numérico, pero su
     * nombre no coincide con ningún patrón, así que nunca llega a sumarse)
     * "SIN UTILIZACION DEL SISTEMA FINANCIERO" → null.
     */
    private static function aMonto(string $valor): ?float
    {
        $v = trim($valor);
        if ($v === '') {
            return null;
        }
        // Formato local con coma decimal (2,41) o separador de miles (1.234,56).
        if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d{1,2}$/', $v)) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        } elseif (preg_match('/^-?\d+,\d{1,2}$/', $v)) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? round((float) $v, 2) : null;
    }

    private static function sinAcentos(string $texto): string
    {
        $texto = strtr(trim($texto), [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        // strtoupper ASCII basta: el strtr de arriba ya convirtió las vocales acentuadas,
        // y así el helper no depende de que mbstring esté cargado.
        return preg_replace('/\s+/', ' ', strtoupper($texto)) ?? '';
    }
}
