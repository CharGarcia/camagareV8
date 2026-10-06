<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\IngresoRepository;

/**
 * Sugiere el cliente y el documento (factura/saldo inicial/recibo) de CxC que
 * corresponde a una línea del extracto bancario, comparando el texto de la
 * descripción contra los clientes activos de la empresa. Una vez identificado el
 * cliente, el documento se elige SIEMPRE por ANTIGÜEDAD (el pendiente más viejo
 * primero, como se cobra la cartera). Ni el monto del depósito ni un número de
 * factura escrito en la descripción del banco cambian esa elección: antes se
 * buscaba la factura cuyo saldo coincidía con el valor (o la mencionada en el
 * texto), y el usuario pidió (06-10-2026) que se propongan siempre las más
 * antiguas.
 *
 * Es solo una sugerencia: el usuario siempre confirma o corrige antes de
 * generar el Ingreso (ver ConciliacionCobrosService::generarIngresos()).
 */
class ConciliacionMatchService
{
    /** Score mínimo (0-100) de similitud de texto para considerar un cliente como candidato. */
    private const UMBRAL_SCORE_CLIENTE = 35.0;

    /**
     * Documentos pendientes ya consultados en esta petición, indexados por "empresa:cliente".
     * getFacturasPendientes() es una consulta con 7 CTE de agregación sobre toda la cartera y
     * aquí se necesita hasta 6 veces por línea del extracto: sin esta caché, procesar un
     * archivo de 200 líneas disparaba más de mil ejecuciones y colgaba el request.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $cachePendientes = [];

    public function __construct(private IngresoRepository $ingresoRepository)
    {
    }

    /**
     * Documentos pendientes del cliente, servidos desde la caché de la petición.
     * La caché vive solo mientras dura el request (el objeto se crea por petición), así que
     * no puede devolver saldos de una petición anterior.
     */
    private function pendientesDe(int $idCliente, int $idEmpresa): array
    {
        $clave = $idEmpresa . ':' . $idCliente;
        if (!isset($this->cachePendientes[$clave])) {
            $this->cachePendientes[$clave] = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
        }
        return $this->cachePendientes[$clave];
    }

    /**
     * @param array $fila ['descripcion' => string, 'monto' => float, ...]
     * @param array $clientes Lista de clientes activos de la empresa: [['id'=>, 'nombre'=>, 'identificacion'=>], ...]
     */
    public function sugerir(array $fila, array $clientes, int $idEmpresa): array
    {
        $descripcionNorm = $this->normalizar($fila['descripcion']);
        $digitosDescripcion = preg_replace('/\D/', '', $fila['descripcion']) ?? '';
        // $digitosDescripcion solo sirve para reconocer al CLIENTE por su identificación
        // (cédula/RUC en el texto del banco); no se usa para elegir el documento.

        $candidatos = [];
        foreach ($clientes as $cliente) {
            $razonNorm = $this->normalizar((string) $cliente['nombre']);
            $scoreTexto = $this->scoreClienteTexto($descripcionNorm, $razonNorm);

            $digitosCliente = preg_replace('/\D/', '', (string) ($cliente['identificacion'] ?? '')) ?? '';
            $matchIdentificacion = $digitosCliente !== '' && strlen($digitosCliente) >= 7 && str_contains($digitosDescripcion, $digitosCliente);

            $score = $matchIdentificacion ? 100.0 : $scoreTexto;
            if ($score >= self::UMBRAL_SCORE_CLIENTE) {
                $candidatos[] = ['cliente' => $cliente, 'score' => $score];
            }
        }

        if (empty($candidatos)) {
            return $this->sinMatch();
        }

        usort($candidatos, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Se sugiere el documento pendiente MÁS ANTIGUO del mejor cliente candidato (la cartera
        // se cobra en orden); el usuario confirma o cambia. Ni el monto del depósito ni un número
        // de factura en la descripción cambian la elección; si el monto supera el saldo de ese
        // documento, el reparto en partes (repartirPorAntiguedad) sigue con el siguiente más antiguo.
        $idMejorCliente = (int) $candidatos[0]['cliente']['id'];
        $masAntiguo = $this->masAntiguo($this->pendientesDe($idMejorCliente, $idEmpresa));
        if ($masAntiguo !== null) {
            return $this->armarResultado($idMejorCliente, $masAntiguo, $candidatos[0]['score']);
        }

        // El cliente identificado no tiene ningún documento pendiente de cobro: no hay nada que sugerir.
        return [
            'estado' => 'SUGERIDO',
            'id_cliente' => $idMejorCliente,
            'tipo_documento' => null,
            'id_documento' => null,
            'score' => round($candidatos[0]['score'], 2),
        ];
    }

    /**
     * Variante de sugerir() para cuando el cliente YA es conocido — típicamente la diferencia
     * de un pago parcial: ya se aplicó parte de la línea a un documento de este cliente y se
     * busca otro documento pendiente del mismo cliente para el resto. Misma regla que
     * sugerir() (el pendiente más antiguo), sin tener que volver a identificar al cliente
     * por texto. $descripcion se conserva en la firma por compatibilidad; no interviene.
     */
    public function sugerirParaClienteConocido(int $idCliente, string $descripcion, float $monto, int $idEmpresa): array
    {
        // A propósito NO usa pendientesDe(): este método corre justo DESPUÉS de haber generado
        // el Ingreso que cobró parte de la línea, así que los saldos del cliente acaban de
        // cambiar y hay que releerlos. Es una sola consulta por diferencia de pago parcial,
        // no el bucle masivo que justifica la caché en sugerir().
        $pendientes = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
        // Se deja en la caché para que repartirPorAntiguedad() reparta sobre estos mismos saldos.
        $this->cachePendientes[$idEmpresa . ':' . $idCliente] = $pendientes;
        if (empty($pendientes)) {
            return ['estado' => 'SIN_MATCH', 'id_cliente' => $idCliente, 'tipo_documento' => null, 'id_documento' => null, 'score' => null];
        }

        $masAntiguo = $this->masAntiguo($pendientes);
        if ($masAntiguo === null) {
            return ['estado' => 'SIN_MATCH', 'id_cliente' => $idCliente, 'tipo_documento' => null, 'id_documento' => null, 'score' => null];
        }
        return $this->armarResultado($idCliente, $masAntiguo, 40.0);
    }

    /**
     * Reparte el monto de una línea del banco entre los documentos pendientes del cliente, del
     * más antiguo al más reciente (como se cobra la cartera), hasta agotar el monto.
     *
     * Los saldos que se asignan se DESCUENTAN de la caché de pendientes de esta petición: así,
     * si el mismo extracto trae dos depósitos del mismo cliente, el segundo se sugiere sobre los
     * documentos que el primero no cubrió, en vez de proponer la misma factura dos veces (la
     * segunda confirmación fallaría por saldo apartado). Es solo la sugerencia: el usuario
     * confirma o cambia cada parte.
     *
     * @return array{asignaciones: array<int, array{tipo_documento: string, id_documento: int, numero_documento: string, saldo: float, monto: float}>, sobrante: float}
     */
    public function repartirPorAntiguedad(int $idCliente, float $monto, int $idEmpresa): array
    {
        $clave = $idEmpresa . ':' . $idCliente;
        $pendientes = $this->ordenarPorAntiguedad($this->pendientesDe($idCliente, $idEmpresa));

        $restante = round($monto, 2);
        $asignaciones = [];
        foreach ($pendientes as $doc) {
            if ($restante <= 0.009) {
                break;
            }
            $saldo = round((float) $doc['saldo_pendiente'], 2);
            if ($saldo <= 0.009) {
                continue;
            }
            $aplicar = min($saldo, $restante);
            $asignaciones[] = [
                'tipo_documento' => (string) $doc['tipo_documento'],
                'id_documento' => (int) $doc['id'],
                'numero_documento' => (string) $doc['numero_documento'],
                'saldo' => $saldo,
                'monto' => round($aplicar, 2),
            ];
            $restante = round($restante - $aplicar, 2);

            // Consumir el saldo en la caché de la petición (ver nota del método).
            foreach ($this->cachePendientes[$clave] ?? [] as $i => $cacheado) {
                if ($cacheado['tipo_documento'] === $doc['tipo_documento'] && (int) $cacheado['id'] === (int) $doc['id']) {
                    $this->cachePendientes[$clave][$i]['saldo_pendiente'] = round($saldo - $aplicar, 2);
                    break;
                }
            }
        }

        return ['asignaciones' => $asignaciones, 'sobrante' => max(0.0, $restante)];
    }


    /** El documento pendiente más antiguo (fecha de emisión y, a igual fecha, menor id); null si no hay. */
    private function masAntiguo(array $pendientes): ?array
    {
        $ordenados = $this->ordenarPorAntiguedad($pendientes);
        return $ordenados[0] ?? null;
    }

    /**
     * getFacturasPendientes() ya devuelve ORDER BY fecha_emision ASC, id ASC; se reordena
     * aquí de todos modos para que la regla "el más antiguo primero" no dependa de que esa
     * consulta conserve su ORDER BY.
     */
    private function ordenarPorAntiguedad(array $pendientes): array
    {
        // Sin saldo (p. ej. ya consumido por un depósito anterior del mismo extracto) no se sugiere.
        $pendientes = array_filter($pendientes, fn (array $d) => round((float) ($d['saldo_pendiente'] ?? 0), 2) > 0.009);
        usort($pendientes, function (array $a, array $b): int {
            $fa = (string) ($a['fecha_emision'] ?? '');
            $fb = (string) ($b['fecha_emision'] ?? '');
            return [$fa, (int) ($a['id'] ?? 0)] <=> [$fb, (int) ($b['id'] ?? 0)];
        });
        return array_values($pendientes);
    }

    private function armarResultado(int $idCliente, array $doc, float $scoreFinal): array
    {
        return [
            'estado' => 'SUGERIDO',
            'id_cliente' => $idCliente,
            'tipo_documento' => $doc['tipo_documento'],
            'id_documento' => (int) $doc['id'],
            'score' => round(min(100.0, $scoreFinal), 2),
        ];
    }

    private function sinMatch(): array
    {
        return ['estado' => 'SIN_MATCH', 'id_cliente' => null, 'tipo_documento' => null, 'id_documento' => null, 'score' => null];
    }

    /** Similitud de texto (0-100) combinando similar_text() con un bonus por tokens (palabras de 4+ letras) encontrados. */
    private function scoreClienteTexto(string $descripcionNorm, string $razonSocialNorm): float
    {
        if ($razonSocialNorm === '' || $descripcionNorm === '') {
            return 0.0;
        }

        similar_text($descripcionNorm, $razonSocialNorm, $pct);

        $tokens = array_filter(explode(' ', $razonSocialNorm), fn ($t) => mb_strlen($t) >= 4);
        if (empty($tokens)) {
            return $pct;
        }

        $tokensEncontrados = 0;
        foreach ($tokens as $token) {
            if (str_contains($descripcionNorm, $token)) {
                $tokensEncontrados++;
            }
        }
        $scoreTokens = ($tokensEncontrados / count($tokens)) * 100;

        return max($pct, $scoreTokens);
    }

    /** Mayúsculas, sin tildes, solo letras/números/espacios (para comparar texto libre de bancos vs. razón social). */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
        ]);
        $texto = preg_replace('/[^A-Z0-9 ]/', ' ', $texto) ?? '';
        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }
}
