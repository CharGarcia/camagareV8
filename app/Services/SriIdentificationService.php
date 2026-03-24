<?php
/**
 * Servicio para consultar identificación (RUC/Cédula) al web service SRI
 * Reutilizable en múltiples módulos del sistema.
 */

declare(strict_types=1);

namespace App\services;

use App\models\Provincia;
use App\models\Ciudad;

class SriIdentificationService
{
    private string $apiUrl;
    private int $timeout;

    public function __construct(?string $apiUrl = null, int $timeout = 15)
    {
        $config = require MVC_CONFIG . '/app.php';
        $this->apiUrl = $apiUrl ?? ($config['sri_identification_url'] ?? 'http://137.184.159.242:4000/api/sri-identification');
        $this->timeout = $timeout;
    }

    /**
     * Consulta el web service y devuelve los datos normalizados para el formulario.
     * @return array{ok: bool, data?: array, error?: string}
     */
    public function consultar(string $identificacion): array
    {
        $identificacion = preg_replace('/\D/', '', $identificacion);
        $longitud = strlen($identificacion);

        if ($longitud !== 10 && $longitud !== 13) {
            return ['ok' => false, 'error' => 'La identificación debe tener 10 (cédula) o 13 (RUC) dígitos.'];
        }

        $payload = ['identification' => $identificacion];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['ok' => false, 'error' => 'Error de conexión: ' . $curlError];
        }

        $responseData = json_decode($response ?? '', true);
        if (!is_array($responseData) || empty($responseData['data'])) {
            return ['ok' => false, 'error' => 'No se encontró información para esta identificación.'];
        }

        $data = $responseData['data'];

        if ($longitud === 10) {
            return ['ok' => true, 'data' => $this->parsearCedula($data, $identificacion)];
        }

        return ['ok' => true, 'data' => $this->parsearRuc($data, $identificacion)];
    }

    private function parsearCedula(array $data, string $cedula): array
    {
        $nombreCompleto = $this->strClean($data['nombreCompleto'] ?? '');
        $nombreCompleto = $this->fixEncoding($nombreCompleto);

        return [
            'ruc' => $cedula,
            'establecimiento' => '',
            'nombre' => $nombreCompleto,
            'nombre_comercial' => $nombreCompleto,
            'direccion' => '',
            'cod_prov' => '',
            'cod_ciudad' => '',
            'telefono' => '',
            'mail' => '',
            'tipo' => '04', // Persona natural
        ];
    }

    private function parsearRuc(array $data, string $ruc): array
    {
        $datosContribuyente = $data['datosContribuyente'][0] ?? [];
        $razonSocial = $this->strClean($datosContribuyente['razonSocial'] ?? '');
        $razonSocial = $this->fixEncoding($razonSocial);

        $tipoContribuyente = $datosContribuyente['tipoContribuyente'] ?? '01';
        $nombreComercial = $razonSocial;
        $direccion = '';
        $codProv = '';
        $codCiud = '';
        $establecimiento = '';

        // Buscar establecimiento matriz (tipoEstablecimiento = MAT)
        $establecimientos = $data['establecimientos'] ?? [];
        $estMatriz = null;
        foreach ($establecimientos as $est) {
            if (($est['tipoEstablecimiento'] ?? '') === 'MAT' && ($est['estado'] ?? '') === 'ABIERTO') {
                $estMatriz = $est;
                break;
            }
        }
        if ($estMatriz === null) {
            foreach ($establecimientos as $est) {
                if (($est['matriz'] ?? '') === 'SI' && ($est['estado'] ?? '') === 'ABIERTO') {
                    $estMatriz = $est;
                    break;
                }
            }
        }

        if ($estMatriz !== null) {
            $nombreComercial = $this->strClean($estMatriz['nombreFantasiaComercial'] ?? $razonSocial);
            $nombreComercial = $this->fixEncoding($nombreComercial);
            $direccionCompleta = $this->strClean($estMatriz['direccionCompleta'] ?? '');
            $direccionCompleta = $this->fixEncoding($direccionCompleta);
            $establecimiento = $this->extraerEstablecimiento($estMatriz);

            // Parsear direccionCompleta: "PICHINCHA / QUITO / LA CONCEPCIÓN / MANUEL SERRANO..."
            // Provincia = primer /, Ciudad = segundo /, Dirección = último / (calle)
            $partes = array_map('trim', explode('/', $direccionCompleta));
            $nombreProvincia = $partes[0] ?? '';
            $nombreCiudad = $partes[1] ?? '';
            $direccion = !empty($partes) ? trim($partes[count($partes) - 1]) : '';
            if ($nombreProvincia !== '' || $nombreCiudad !== '') {
                $modeloProv = new Provincia();
                $modeloCiud = new Ciudad();
                $codProvBuscado = $modeloProv->getCodigoPorNombre($nombreProvincia);
                if ($codProvBuscado !== null) {
                    $codProv = $codProvBuscado;
                    $codCiudBuscado = $modeloCiud->getCodigoPorNombreYProvincia($nombreCiudad, $codProv);
                    if ($codCiudBuscado !== null) {
                        $codCiud = $codCiudBuscado;
                    }
                }
            }
        }

        return [
            'ruc' => $ruc,
            'establecimiento' => $establecimiento,
            'nombre' => $razonSocial,
            'nombre_comercial' => $nombreComercial,
            'direccion' => $direccion,
            'cod_prov' => $codProv,
            'cod_ciudad' => $codCiud,
            'telefono' => '',
            'mail' => '',
            'tipo' => $this->mapearTipoContribuyente($tipoContribuyente),
        ];
    }

    /**
     * Extrae numeroEstablecimiento (ej. "001") del establecimiento matriz.
     */
    private function extraerEstablecimiento(array $est): string
    {
        $v = $est['numeroEstablecimiento'] ?? $est['numero_establecimiento'] ?? $est['codigoEstablecimiento'] ?? $est['establecimiento'] ?? null;
        if ($v !== null && $v !== '') {
            return str_pad((string) $v, 3, '0', STR_PAD_LEFT);
        }
        return '';
    }

    private function mapearTipoContribuyente(string $tipo): string
    {
        return match (strtoupper($tipo)) {
            '04', 'PERSONA NATURAL' => '04',
            '05', 'SOCIEDAD' => '05',
            '06', 'EMPRESA UNIPERSONAL' => '06',
            '07', 'ESTABLECIMIENTO' => '07',
            default => '01',
        };
    }

    private function strClean(string $str): string
    {
        $str = trim($str);
        $str = preg_replace('/\s+/', ' ', $str);
        return $str;
    }

    private function fixEncoding(string $str): string
    {
        if ($str === '') return '';
        $detected = mb_detect_encoding($str, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = @mb_convert_encoding($str, 'UTF-8', $detected);
            return $converted !== false ? $converted : $str;
        }
        return $str;
    }
}
