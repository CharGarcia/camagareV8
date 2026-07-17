<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\IngresoRepository;

/**
 * Sugiere el cliente y el documento (factura/saldo inicial/recibo) de CxC que
 * corresponde a una línea del extracto bancario, comparando el texto de la
 * descripción contra los clientes activos de la empresa y cruzando el monto
 * (o el número de documento, si aparece en la descripción) contra las
 * facturas pendientes de cobro de IngresoRepository::getFacturasPendientes().
 *
 * Es solo una sugerencia: el usuario siempre confirma o corrige antes de
 * generar el Ingreso (ver ConciliacionCobrosService::generarIngresos()).
 */
class ConciliacionMatchService
{
    /** Score mínimo (0-100) de similitud de texto para considerar un cliente como candidato. */
    private const UMBRAL_SCORE_CLIENTE = 35.0;

    public function __construct(private IngresoRepository $ingresoRepository)
    {
    }

    /**
     * @param array $fila ['descripcion' => string, 'monto' => float, ...]
     * @param array $clientes Lista de clientes activos de la empresa: [['id'=>, 'nombre'=>, 'identificacion'=>], ...]
     */
    public function sugerir(array $fila, array $clientes, int $idEmpresa): array
    {
        $descripcionNorm = $this->normalizar($fila['descripcion']);
        $digitosDescripcion = preg_replace('/\D/', '', $fila['descripcion']) ?? '';

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
        $candidatos = array_slice($candidatos, 0, 5);

        foreach ($candidatos as $candidato) {
            $idCliente = (int) $candidato['cliente']['id'];
            $pendientes = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
            if (empty($pendientes)) {
                continue;
            }

            // 1) Match exacto de monto (tolerancia de 1 centavo) — la señal más confiable.
            foreach ($pendientes as $doc) {
                if (abs((float) $doc['saldo_pendiente'] - (float) $fila['monto']) <= 0.01) {
                    return $this->armarResultado($idCliente, $doc, $candidato['score'] + 20);
                }
            }

            // 2) Número de documento mencionado en la descripción del banco.
            foreach ($pendientes as $doc) {
                $digitosDoc = preg_replace('/\D/', '', (string) $doc['numero_documento']) ?? '';
                if ($digitosDoc !== '' && strlen($digitosDoc) >= 4 && str_contains($digitosDescripcion, $digitosDoc)) {
                    return $this->armarResultado($idCliente, $doc, $candidato['score'] + 15);
                }
            }
        }

        // Ningún documento calzó por monto exacto ni por número de documento en la descripción
        // (lo usual en transferencias, que solo traen el nombre de quien paga). Aun así, si el
        // mejor cliente candidato tiene facturas pendientes, se sugiere la de saldo más parecido
        // al monto de la línea — con confianza baja, el usuario debe confirmarla o cambiarla —
        // para que "Documento Sugerido" siempre muestre un número de factura real del sistema
        // en vez de quedar vacío cuando sí hay un candidato razonable.
        $idMejorCliente = (int) $candidatos[0]['cliente']['id'];
        $pendientesMejorCliente = $this->ingresoRepository->getFacturasPendientes($idMejorCliente, $idEmpresa);
        if (!empty($pendientesMejorCliente)) {
            usort($pendientesMejorCliente, fn ($a, $b) =>
                abs((float) $a['saldo_pendiente'] - (float) $fila['monto']) <=> abs((float) $b['saldo_pendiente'] - (float) $fila['monto'])
            );
            return $this->armarResultado($idMejorCliente, $pendientesMejorCliente[0], max(20.0, $candidatos[0]['score'] - 25));
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
     * busca otro documento pendiente del mismo cliente para el resto. Mismas señales que
     * sugerir() (monto exacto, número de documento en la descripción, o el de saldo más
     * parecido), pero sin tener que volver a identificar al cliente por texto.
     */
    public function sugerirParaClienteConocido(int $idCliente, string $descripcion, float $monto, int $idEmpresa): array
    {
        $pendientes = $this->ingresoRepository->getFacturasPendientes($idCliente, $idEmpresa);
        if (empty($pendientes)) {
            return ['estado' => 'SIN_MATCH', 'id_cliente' => $idCliente, 'tipo_documento' => null, 'id_documento' => null, 'score' => null];
        }

        $digitosDescripcion = preg_replace('/\D/', '', $descripcion) ?? '';

        foreach ($pendientes as $doc) {
            if (abs((float) $doc['saldo_pendiente'] - $monto) <= 0.01) {
                return $this->armarResultado($idCliente, $doc, 90.0);
            }
        }
        foreach ($pendientes as $doc) {
            $digitosDoc = preg_replace('/\D/', '', (string) $doc['numero_documento']) ?? '';
            if ($digitosDoc !== '' && strlen($digitosDoc) >= 4 && str_contains($digitosDescripcion, $digitosDoc)) {
                return $this->armarResultado($idCliente, $doc, 85.0);
            }
        }

        usort($pendientes, fn ($a, $b) =>
            abs((float) $a['saldo_pendiente'] - $monto) <=> abs((float) $b['saldo_pendiente'] - $monto)
        );
        return $this->armarResultado($idCliente, $pendientes[0], 40.0);
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
