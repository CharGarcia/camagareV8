<?php

/**
 * Servicio para consultar identificación (RUC/Cédula) al web service SRI
 * Reutilizable en múltiples módulos del sistema.
 */

declare(strict_types=1);

namespace App\Services;

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

    /** Si en config/app.php está en false, no se llama al API (útil si el servicio externo no es alcanzable). */
    public static function estaHabilitado(): bool
    {
        $config = require MVC_CONFIG . '/app.php';
        return ($config['sri_identification_enabled'] ?? true) !== false;
    }

    /**
     * Consulta el web service y devuelve los datos normalizados para el formulario.
     *
     * @param int|null $idEmpresa Empresa activa de quien consulta. Obligatorio para
     *   que la búsqueda local (clientes/proveedores) no cruce datos entre empresas
     *   (regla multiempresa, CLAUDE.md §4/§6). Si se omite, la búsqueda local se
     *   salta por completo y se va directo al SRI — nunca se busca sin filtrar.
     * @return array{ok: bool, data?: array, error?: string, source?: string}
     */
    public function consultar(string $identificacion, ?int $idEmpresa = null): array
    {
        if (!self::estaHabilitado()) {
            return [
                'ok' => false,
                'error' => 'La consulta automática al SRI está desactivada (sri_identification_enabled en config/app.php). Ingrese los datos manualmente.',
            ];
        }

        // 1. BUSCAR LOCALMENTE PRIMERO (clientes/proveedores de la MISMA empresa)
        $local = $this->buscarLocalmente($identificacion, $idEmpresa);
        if ($local !== null) {
            return [
                'ok' => true,
                'data' => $local['data'],
                'source' => $local['source'],
            ];
        }

        $identificacion = preg_replace('/\D/', '', $identificacion);
        $longitud = strlen($identificacion);

        if ($longitud !== 10 && $longitud !== 13) {
            return ['ok' => false, 'error' => 'La identificación debe tener 10 (cédula) o 13 (RUC) dígitos.'];
        }

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'PHP no tiene la extensión cURL habilitada en el servidor.'];
        }

        $payload = ['identification' => $identificacion];

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return [
                'ok' => false,
                'error' => 'No se pudo contactar el servicio de consulta (' . $this->apiUrl . '). ' . $curlError
                    . ' Compruebe firewall/salida HTTPS, que el API esté en marcha, o ingrese los datos a mano.',
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = is_string($response) ? mb_substr(trim($response), 0, 200) : '';
            return [
                'ok' => false,
                'error' => 'El servicio de consulta respondió HTTP ' . $httpCode . '. '
                    . ($snippet !== '' ? $snippet : 'Revise sri_identification_url en config/app.php.'),
            ];
        }

        $responseData = json_decode($response ?? '', true);
        if (!is_array($responseData) || empty($responseData['data'])) {
            return ['ok' => false, 'error' => 'No encontrado.'];
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

    /**
     * Busca en `clientes` y `proveedores` de la empresa activa ($idEmpresa).
     * Sin $idEmpresa no se busca nada (evita cruzar datos entre empresas —
     * regla multiempresa, CLAUDE.md §4/§6). Prioriza `clientes`: si la
     * identificación ya es cliente de esta empresa, eso es lo primero que el
     * llamador necesita saber (evitar un duplicado), antes que enriquecer con
     * datos de proveedor.
     *
     * @return array{data: array, source: 'cliente'|'proveedor'}|null
     */
    private function buscarLocalmente(string $identificacion, ?int $idEmpresa): ?array
    {
        if ($idEmpresa === null || $idEmpresa <= 0) {
            return null;
        }

        try {
            $db = \App\core\Database::getConnection();

            $stCli = $db->prepare(
                "SELECT id, nombre, direccion, provincia, ciudad, telefono, email, identificacion
                 FROM clientes
                 WHERE identificacion = :id AND id_empresa = :id_empresa AND eliminado = false
                 LIMIT 1"
            );
            $stCli->execute([':id' => $identificacion, ':id_empresa' => $idEmpresa]);
            $cli = $stCli->fetch(\PDO::FETCH_ASSOC);
            if ($cli) {
                return [
                    'source' => 'cliente',
                    'data' => [
                        'id' => (int) $cli['id'],
                        'ruc' => $cli['identificacion'],
                        'establecimiento' => '001',
                        'nombre' => $cli['nombre'],
                        'nombre_comercial' => $cli['nombre'],
                        'direccion' => $cli['direccion'],
                        'cod_prov' => $cli['provincia'],
                        'cod_ciudad' => $cli['ciudad'],
                        'telefono' => $cli['telefono'],
                        'mail' => $cli['email'],
                        'tipo' => strlen($cli['identificacion']) === 13 ? '01' : '04',
                    ],
                ];
            }

            $stProv = $db->prepare(
                "SELECT id, razon_social, nombre_comercial, direccion, provincia, ciudad, telefono, email, identificacion
                 FROM proveedores
                 WHERE identificacion = :id AND id_empresa = :id_empresa AND eliminado = false
                 LIMIT 1"
            );
            $stProv->execute([':id' => $identificacion, ':id_empresa' => $idEmpresa]);
            $prov = $stProv->fetch(\PDO::FETCH_ASSOC);
            if ($prov) {
                return [
                    'source' => 'proveedor',
                    'data' => [
                        'id' => (int) $prov['id'],
                        'ruc' => $prov['identificacion'],
                        'establecimiento' => '001',
                        'nombre' => $prov['razon_social'],
                        'nombre_comercial' => $prov['nombre_comercial'] ?: $prov['razon_social'],
                        'direccion' => $prov['direccion'],
                        'cod_prov' => $prov['provincia'],
                        'cod_ciudad' => $prov['ciudad'],
                        'telefono' => $prov['telefono'],
                        'mail' => $prov['email'],
                        'tipo' => strlen($prov['identificacion']) === 13 ? '01' : '04',
                    ],
                ];
            }
        } catch (\Exception $e) {
            // Si hay error en DB local, simplemente ignoramos y seguimos al SRI
        }

        return null;
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
