<?php
declare(strict_types=1);

namespace App\Services\Sri;

/**
 * Consulta los datos de un vehículo por placa (o CAMV/CPN) en el servicio público
 * del SRI que usa su propia página "Valores a pagar por placa".
 *
 * No es una API oficial ni documentada: el SRI puede cambiarla sin aviso. Por eso
 * es solo una ayuda para llenar el formulario; cualquier fallo se informa como
 * mensaje y el usuario puede escribir los datos a mano. No guarda nada en BD.
 * Devuelve marca, modelo, año, país y CAMV/CPN; el SRI no entrega el propietario.
 */
class SriConsultaVehiculoService
{
    private const URL = 'https://srienlinea.sri.gob.ec/sri-matriculacion-vehicular-recaudacion-servicio-internet/rest/BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn';
    private const TIMEOUT = 10;

    /**
     * Normaliza la placa al formato que espera el SRI: mayúsculas, sin guiones ni
     * espacios, y con un 0 delante del número en las placas antiguas de 3 dígitos
     * (ABC-123 → ABC0123).
     */
    public static function normalizarPlaca(string $placa): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $placa) ?? '');
        if (preg_match('/^([A-Z]{3})(\d{3})$/', $p, $m)) {
            $p = $m[1] . '0' . $m[2];
        }
        return $p;
    }

    /**
     * @return array{placa:string, marca:string, modelo:string, anio:?int, pais:string, camv_cpn:string}
     * @throws \RuntimeException con un mensaje apto para mostrar al usuario.
     */
    public function consultar(string $placa): array
    {
        $placa = self::normalizarPlaca($placa);
        if (!preg_match('/^[A-Z0-9]{5,12}$/', $placa)) {
            throw new \RuntimeException('Escriba una placa válida (por ejemplo ABC1234).');
        }

        $ch = curl_init(self::URL . '?' . http_build_query(['numeroPlacaCampvCpn' => $placa]));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (CaMaGaRe)',
            // Mismo criterio que SriWebserviceService: en XAMPP los certificados raíz suelen faltar.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body     = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errNo    = curl_errno($ch);
        curl_close($ch);

        if ($body === false || $errNo !== 0) {
            throw new \RuntimeException('El SRI no respondió. Intente de nuevo o escriba los datos a mano.');
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException('El SRI no está disponible en este momento (HTTP ' . $httpCode . '). Escriba los datos a mano.');
        }

        $json = json_decode((string) $body, true);
        if (!is_array($json)) {
            throw new \RuntimeException('El SRI devolvió una respuesta inesperada. Escriba los datos a mano.');
        }

        // Placa inexistente: {"objeto":null,"mensajeServidor":{"texto":"El vehículo no existe"}}
        if (empty($json['descripcionMarca']) && empty($json['numeroPlaca'])) {
            $msg = trim((string) ($json['mensajeServidor']['texto'] ?? ''));
            throw new \RuntimeException(($msg !== '' ? $msg : 'El vehículo no existe') . ' en el SRI (placa ' . $placa . ').');
        }

        $anio = (int) ($json['anioAuto'] ?? 0);
        return [
            'placa'    => (string) ($json['numeroPlaca'] ?? $placa),
            'marca'    => trim((string) ($json['descripcionMarca'] ?? '')),
            'modelo'   => trim((string) ($json['descripcionModelo'] ?? '')),
            'anio'     => ($anio >= 1900 && $anio <= 2100) ? $anio : null,
            'pais'     => trim((string) ($json['descripcionPais'] ?? '')),
            'camv_cpn' => trim((string) ($json['numeroCamvCpn'] ?? '')),
        ];
    }
}
