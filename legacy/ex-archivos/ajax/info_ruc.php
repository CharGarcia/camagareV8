<?php
/**
 * Endpoint para consulta RUC/Cédula SRI
 * POST numero= (RUC 13 dígitos o cédula 10 dígitos)
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');

$numero = isset($_POST['numero']) ? trim($_POST['numero']) : (isset($_GET['numero']) ? trim($_GET['numero']) : '');
if (empty($numero)) {
    ob_end_clean();
    echo json_encode([]);
    exit;
}

$urlApi = "http://137.184.159.242:4000/api/sri-identification";
$ch = curl_init($urlApi);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['identification' => $numero]));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);

$datos = [];
$responseData = $response ? json_decode($response, true) : null;
if (is_array($responseData) && isset($responseData['data']) && is_array($responseData['data'])) {
    $data = $responseData['data'];
    $longitud = strlen($numero);
    $tercerDigito = substr($numero, 2, 1);
    $tipo = ($tercerDigito === '9') ? "03" : (($tercerDigito === '6') ? "05" : "01");

    if ($longitud === 10) {
        $nombre = $data['nombreCompleto'] ?? '';
        if (!empty($nombre)) {
            $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
            $datos[] = [
                'nombre' => $nombre,
                'tipo' => '01',
                'direccion' => '',
                'nombre_comercial' => $nombre,
                'codigo_provincia' => '17',
                'codigo_ciudad' => '189',
                'email' => '',
                'telefono' => ''
            ];
        }
    } elseif ($longitud === 13) {
        $contrib = $data['datosContribuyente'][0] ?? null;
        if ($contrib) {
            $razonSocial = $contrib['razonSocial'] ?? '';
            $nombre_comercial = $razonSocial;
            $ubicacion_establecimiento = '';
            if (!empty($data['establecimientos'])) {
                foreach ($data['establecimientos'] as $est) {
                    $estado = $est['estado'] ?? '';
                    $matriz = strtoupper($est['matriz'] ?? '');
                    if ($estado === 'ABIERTO' && $matriz === 'SI') {
                        $nombre_comercial = $est['nombreFantasiaComercial'] ?? $razonSocial;
                        $ubicacion_establecimiento = $est['direccionCompleta'] ?? '';
                        break;
                    }
                }
                if (empty($ubicacion_establecimiento)) {
                    foreach ($data['establecimientos'] as $est) {
                        if (($est['estado'] ?? '') === 'ABIERTO') {
                            $nombre_comercial = $est['nombreFantasiaComercial'] ?? $razonSocial;
                            $ubicacion_establecimiento = $est['direccionCompleta'] ?? '';
                            break;
                        }
                    }
                }
            }
            $datos[] = [
                'nombre' => trim(preg_replace('/\s+/', ' ', $razonSocial)),
                'tipo' => $tipo,
                'direccion' => trim(preg_replace('/\s+/', ' ', $ubicacion_establecimiento)),
                'nombre_comercial' => trim(preg_replace('/\s+/', ' ', $nombre_comercial)),
                'codigo_provincia' => '17',
                'codigo_ciudad' => '189',
                'email' => '',
                'telefono' => ''
            ];
        }
    }
}

ob_end_clean();
echo json_encode($datos, JSON_UNESCAPED_UNICODE);
