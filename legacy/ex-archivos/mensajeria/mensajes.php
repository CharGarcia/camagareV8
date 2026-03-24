<?php
require '../vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Http\CurlClient;

// Configura el contexto de stream para cURL
$sid    = getenv('TWILIO_SID') ?: "YOUR_TWILIO_ACCOUNT_SID";   // Define en .env o variables de entorno
$token  = getenv('TWILIO_TOKEN') ?: "YOUR_TWILIO_AUTH_TOKEN"; // Define en .env o variables de entorno
$cacertPath = "../sistema/mensajeria/cacert.pem"; ///var/www/html
$from = "whatsapp:+17478886431"; // Número de WhatsApp del Sandbox de Twilio
$to = "whatsapp:+593958924831"; // El número de teléfono de destino
// Configurar opciones curl para Twilio
$httpClient = new CurlClient([
    CURLOPT_CAINFO => $cacertPath,
]);

echo $cacertPath;
/* $path_pem = "C:\wamp64\www\sistema\mensajeria\cacert.pem";
$httpClient = new CurlClient([
    CURLOPT_CAINFO => $path_pem,
]);

$twilio = new Client($sid, $token, null, null, $httpClient);

function whatsapp($twilio, $body, $from, $to)
{
    $from = "whatsapp:+" . $from; //+17478886431 Este es el número de WhatsApp de Twilio sandbox
    $to = "whatsapp:+593" . $to; // El número de teléfono de destino
    $message = $twilio->messages
        ->create(
            $to, // to
            array(
                "from" => $from,
                "body" => $body
            )
        );
}

function sms($twilio, $body, $from, $to)
{
    $from = "+" . $from; //+17478886431 Este es el número de Twilio
    $to = "+593" . $to; // El número de teléfono de destino
    $message = $twilio->messages
        ->create(
            $to, // to 982691677
            [
                "body" => $body,
                "from" => $from // Your Twilio number
            ]
        );
}


//para enviar mensajes de texto sms
$httpClient = new CurlClient([
    CURLOPT_CAINFO => '/var/www/html/sistema/mensajeria/cacert.pem',
]); */

$twilio = new Client($sid, $token, null, null, $httpClient);

try {
    $message = $twilio->messages
        ->create(
            $to, // a quién enviar el mensaje
            [
                "body" => "Es un mensaje de pruebas desde camagare",
                "from" => $from
            ]
        );
    print($message->sid);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}


//para enviar mensajes a whatsapp

// Números de teléfono en formato E.164
/* $from = "whatsapp:+593958924831"; //+17478886431 Este es el número de WhatsApp de Twilio sandbox
$to = "whatsapp:+593982691677"; // El número de teléfono de destino
$body = "Aqui va el cuerpo del mensaje que queremos enviar ";

$httpClient = new CurlClient([
    CURLOPT_CAINFO => 'C:\wamp64\www\sistema\mensajeria\cacert.pem',
]);

$twilio = new Client($sid, $token, null, null, $httpClient);

$message = $twilio->messages
    ->create(
        $to, // to
        array(
            "from" => $from,
            "body" => $body
        )
    );

print($message->sid); */
