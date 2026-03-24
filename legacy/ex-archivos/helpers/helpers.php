<?php
function dep($data)
{ //para depurar o mostrar array de forma entendible
    $format = print_r('<pre>');
    $format .= print_r($data);
    $format .= print_r('</pre>');
    return $format;
}

function mostrarBotonAnular($fecha_documento)
{
    $hoy = new DateTime();

    // 📅 Convertimos la fecha (por si trae hora)
    $fecha_sola = explode(' ', $fecha_documento)[0];

    // 🔢 Extraemos año y mes del documento
    $anio = date("Y", strtotime($fecha_sola));
    $mes = date("m", strtotime($fecha_sola));

    // ⏭ Sumamos un mes (controlando diciembre)
    if ($mes == 12) {
        $mes = 1;
        $anio++;
    } else {
        $mes++;
    }

    // 📆 Creamos la fecha límite: 7 del mes siguiente
    $fechaLimite = new DateTime();
    $fechaLimite->setDate($anio, $mes, 10);
    $fechaLimite->setTime(23, 59, 59);

    // ✅ Validamos: dentro del mismo mes o antes del 7 del mes siguiente
    return (
        date("Y-m", strtotime($fecha_sola)) == $hoy->format('Y-m') ||
        $hoy <= $fechaLimite
    );
}


function status_servidor()
{
    $status = "ok";
    if ($status == "ok") {
        return true;
    } else {
        return false;
    }
}

//para saber si el periodo contable esta disponible para editar transacciones o permitir crear
function periodosContables($con, $fecha, $ruc_empresa)
{
    $sql = mysqli_query($con, "SELECT mes_periodo, anio_periodo FROM periodo_contable 
    WHERE mes_periodo = '" . date("m", strtotime($fecha)) . "' and anio_periodo = '" . date("Y", strtotime($fecha)) . "' and ruc_empresa = '" . $ruc_empresa . "' and status = 2");
    if ($sql->num_rows > 0) {
        return true;
    } else {
        return false;
    }
}


function compartirClientesProductos($con, $ruc_empresa)
{
    $ruc_esc = mysqli_real_escape_string($con, trim($ruc_empresa));
    $chk = @mysqli_query($con, "SHOW TABLES LIKE 'configuracion_facturacion'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        return array('clientes' => " cli.ruc_empresa = '" . $ruc_esc . "'", 'productos' => " ruc_empresa = '" . $ruc_esc . "'");
    }
    $query_comparten = mysqli_query($con, "SELECT * FROM configuracion_facturacion WHERE ruc_empresa = '" . $ruc_esc . "' LIMIT 1");
    $row_comparten = $query_comparten ? mysqli_fetch_array($query_comparten) : null;
    $comparte_clientes_val = (isset($row_comparten['clientes']) && trim($row_comparten['clientes']) === 'SI');
    $comparte_productos_val = (isset($row_comparten['productos']) && trim($row_comparten['productos']) === 'SI');
    $comparte_clientes = $comparte_clientes_val ? " mid(cli.ruc_empresa,1,10) = '" . substr($ruc_esc, 0, 10) . "'" : " cli.ruc_empresa = '" . $ruc_esc . "'";
    $comparte_productos = $comparte_productos_val ? " mid(ruc_empresa,1,10) = '" . substr($ruc_esc, 0, 10) . "'" : " ruc_empresa = '" . $ruc_esc . "'";
    return array("clientes" => $comparte_clientes, "productos" => $comparte_productos);
}


function getPermisos($con, $usuario, $ruc_empresa, $modulo)
{
    $ruta = "/sistema/modulos/" . $modulo . ".php";
    $sql_submenu = mysqli_query($con, "SELECT id_submodulo FROM submodulos_menu WHERE ruta = '" . $ruta . "'");
    $row_submenu = mysqli_fetch_array($sql_submenu);
    if ($row_submenu === null) {
        return array('r' => 0, 'w' => 0, 'u' => 0, 'd' => 0);
    }
    $id_submodulo = $row_submenu['id_submodulo'];

    $sql_permisos = mysqli_query($con, "SELECT asi.r as r, asi.w as w, asi.u as u, asi.d as d FROM 
    modulos_asignados as asi INNER JOIN empresas as emp ON emp.id=asi.id_empresa
     WHERE emp.ruc = '" . $ruc_empresa . "' and asi.id_usuario ='" . $usuario . "' and asi.id_submodulo = '" . $id_submodulo . "'");
    $row_permisos = mysqli_fetch_array($sql_permisos);
    if ($row_permisos === null) {
        return array('r' => 0, 'w' => 0, 'u' => 0, 'd' => 0);
    }
    return array('r' => $row_permisos['r'], 'w' => $row_permisos['w'], 'u' => $row_permisos['u'], 'd' => $row_permisos['d']);
}

/**
 * Verifica permiso de lectura del módulo. Si no tiene permiso, redirige a sistema/empresa.
 * Uso: verificarPermisoModulo($con, 'nombre_modulo');
 */
function verificarPermisoModulo($con, $modulo)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['ruc_empresa'])) {
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
        exit;
    }
    $permisos = getPermisos($con, $_SESSION['id_usuario'], $_SESSION['ruc_empresa'], $modulo);
    if (!isset($permisos['r']) || $permisos['r'] != 1) {
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
        exit;
    }
}

//para transformar numeros a letras
function numero_letras($valor)
{
    include('../validadores/numero_letras.php');
    $cantidad_letras = num_letras($valor);
    return $cantidad_letras;
}

//para validar fechas separadas en excel en cada columna
function validar_fecha($fecha_validar)
{
    if (count($fecha_validar) == 3 && checkdate($fecha_validar[1], $fecha_validar[0], $fecha_validar[2])) {
        return true;
    }
    return false;
}

//compara dos fechas
function comparar_fecha($fecha_vence_array)
{
    if ($fecha_vence_array[2] > date('Y', time())) {
        return true;
    } else if ($fecha_vence_array[2] == date('Y', time()) && $fecha_vence_array[1] > date('m', time())) {
        return true;
    } else if ($fecha_vence_array[2] == date('Y', time()) && $fecha_vence_array[1] == date('m', time()) && $fecha_vence_array[0] > date('d', time())) {
        return true;
    }
    return false;
}

//para mostrar mensaje de error enviando array con los mensajes
function mensaje_error($mensajes)
{
?>
    <div class="alert alert-danger" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <?php
        foreach ($mensajes as $error) {
            echo $error . "<br>";
        }
        ?>
    </div>
<?php
}

//para generar un codigo unico

function codigo_aleatorio($n)
{
    $a = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
    $name = NULL;
    $e = count($a) - 1; //cuenta el número de elementos del arreglo y le resta 1
    for ($i = 1; $i <= $n; $i++) {
        $m = rand(0, $e); //devuelve un número randómico entre 0 y el número de elementos
        $name .= $a[$m];
    }
    return $name;
}

//Envio de correos desde camagare
function sendEmail($data)
{
    $docMailDir = dirname(__FILE__) . '/../documentos_mail';
    require_once($docMailDir . "/phpmailer.php");
    require_once($docMailDir . "/smtp.php");
    require_once($docMailDir . "/exception.php");

    $phpmailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    $GLOBALS['LAST_EMAIL_ERROR'] = null;

    try {
        $asunto       = $data['asunto'];
        $empresa      = $data['empresa'];
        $remitente    = $data['emisor'];
        $correo_host  = $data['host'];
        $correo_pass  = $data['pass'];
        $correo_port  = $data['port'];
        $emailDestino = explode(',', $data['receptor']);

        // CONFIGURACIÓN SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host       = $correo_host;
        $phpmailer->SMTPAuth   = true;
        $phpmailer->Username   = $remitente;
        $phpmailer->Password   = $correo_pass;
        $phpmailer->SMTPSecure = $data['smtp_secure'] ?? 'tls';
        $phpmailer->Port       = $correo_port;

        $phpmailer->setFrom($remitente, $empresa);
        foreach ($emailDestino as $destino) {
            $phpmailer->addAddress(trim($destino));
        }

        $phpmailer->Subject = $asunto;

        if (isset($data['pdf'])) {
            $phpmailer->addAttachment($data['pdf']);
        }
        if (isset($data['xml'])) {
            $phpmailer->addAttachment($data['xml']);
        }

        ob_start();
        $templateFile = $docMailDir . '/' . ($data['template'] ?? '') . '.php';
        if (file_exists($templateFile)) {
            require_once($templateFile);
        }
        $mensaje = ob_get_clean();
        $phpmailer->isHTML(true);
        $phpmailer->Body = $mensaje;

        return $phpmailer->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $err = $phpmailer->ErrorInfo ?? $e->getMessage();
        $GLOBALS['LAST_EMAIL_ERROR'] = $err;
        error_log('Mailer Error: ' . $err);
        return false;
    }
}



function datos_empresa($ruc_empresa, $con)
{
    $busca_empresa = "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'";
    $resultado_de_la_busqueda = $con->query($busca_empresa);
    $row = mysqli_fetch_array($resultado_de_la_busqueda);
    return $row;
}

function datos_correo($ruc_empresa, $con)
{
    $busca_info_fe = "SELECT * FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ";
    $result = $con->query($busca_info_fe);
    $info_fe = mysqli_fetch_array($result);
    $correoHost = empty($info_fe['correo_host']) ? 0 : 1;
    $correoPass = empty($info_fe['correo_pass']) ? 0 : 1;
    $correoPort = empty($info_fe['correo_port']) ? 0 : 1;
    $correoRemitente = empty($info_fe['correo_remitente']) ? 0 : 1;
    if (($correoHost + $correoPass + $correoPort + $correoRemitente) == 4) {
        return $info_fe;
    } else {
        $correoHost = "smtp.office365.com";
        $correoPass = "DOC2311*";
        $correoPort = "587";
        $correoRemitente = "documentos@camagare.com";

        $info_fe = array(
            'correo_port' => $correoPort,
            'correo_host' => $correoHost,
            'correo_pass' => $correoPass,
            'correo_remitente' => $correoRemitente,
            'correo_asunto' => $info_fe['correo_asunto']
        );
        return $info_fe;
    }
}

function validarCorreo($correos)
{
    $correos = trim($correos);

    // Patrón: correo válido seguido de (, correo válido) repetidas veces
    $pattern = '/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}(, [A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})*$/i';

    return preg_match($pattern, $correos) === 1;
}


/* function notify($msg, $type = 'error')
{
    $msg = addslashes($msg);
    echo "<script>$.notify('{$msg}','{$type}');</script>";
} */


//Elimina exceso de espacios entre palabras

function strClean($string)
{
    $string = trim(preg_replace('/\s+/', ' ', $string));
    $string = stripslashes($string);
    $string = strip_tags($string);

    $peligros = [
        "<script>",
        "</script>",
        "<script src>",
        "<script type=>",
        "SELECT * FROM",
        "DELETE FROM",
        "INSERT INTO",
        "SELECT COUNT(*) FROM",
        "DROP TABLE",
        "OR '1'='1",
        'OR "1"="1"',
        'OR ´1´=´1´',
        "is NULL; --",
        "LIKE '",
        'LIKE "',
        "LIKE ´",
        "OR 'a'='a",
        'OR "a"="a',
        "OR ´a´=´a",
        "--",
        "^",
        "[",
        "]",
        "==",
        "'"
    ];

    $string = str_ireplace($peligros, "", $string);
    $string = str_replace(["\n", "\r"], " ", $string);
    $string = str_replace("&", "Y", $string);

    return $string;
}

function clear_cadena($cadena)
{
    // Mapa de caracteres a reemplazar
    $replace = array(
        'Á' => 'A',
        'À' => 'A',
        'Â' => 'A',
        'Ä' => 'A',
        'á' => 'a',
        'à' => 'a',
        'ä' => 'a',
        'â' => 'a',
        'ª' => 'a',
        'É' => 'E',
        'È' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'é' => 'e',
        'è' => 'e',
        'ë' => 'e',
        'ê' => 'e',
        'Í' => 'I',
        'Ì' => 'I',
        'Ï' => 'I',
        'Î' => 'I',
        'í' => 'i',
        'ì' => 'i',
        'ï' => 'i',
        'î' => 'i',
        'Ó' => 'O',
        'Ò' => 'O',
        'Ö' => 'O',
        'Ô' => 'O',
        'ó' => 'o',
        'ò' => 'o',
        'ö' => 'o',
        'ô' => 'o',
        'Ú' => 'U',
        'Ù' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'ú' => 'u',
        'ù' => 'u',
        'ü' => 'u',
        'û' => 'u',
        'Ñ' => 'N',
        'ñ' => 'n',
        'Ç' => 'C',
        'ç' => 'c',
        ',' => '',
        '.' => '',
        ';' => '',
        ':' => ''
    );

    // Reemplazar caracteres utilizando strtr
    return strtr($cadena, $replace);
}


//Genera un token
/* function token()
{
    $r1 = bin2hex(random_bytes(10));
    $r2 = bin2hex(random_bytes(10));
    $r3 = bin2hex(random_bytes(10));
    $r4 = bin2hex(random_bytes(10));
    $token = $r1 . '-' . $r2 . '-' . $r3 . '-' . $r4;
    return $token;
} */

function token($segments = 4, $bytesPerSegment = 10)
{
    $tokenParts = [];

    for ($i = 0; $i < $segments; $i++) {
        // Generar bytes aleatorios usando OpenSSL
        $cryptoStrong = false;
        $bytes = openssl_random_pseudo_bytes($bytesPerSegment, $cryptoStrong);

        if ($cryptoStrong === true && $bytes !== false) {
            $tokenParts[] = bin2hex($bytes);
        } else {
            // Si falla, lanza un error
            throw new Exception("No se pudieron generar bytes aleatorios de forma segura.");
        }
    }

    // Unir los segmentos con un guion
    return implode('-', $tokenParts);
}


function Meses()
{
    $meses = array(
        ['codigo' => '01', 'nombre' => "Enero"],
        ['codigo' => '02', 'nombre' => "Febrero"],
        ['codigo' => '03', 'nombre' => "Marzo"],
        ['codigo' => '04', 'nombre' => "Abril"],
        ['codigo' => '05', 'nombre' => "Mayo"],
        ['codigo' => '06', 'nombre' => "Junio"],
        ['codigo' => '07', 'nombre' => "Julio"],
        ['codigo' => '08', 'nombre' => "Agosto"],
        ['codigo' => '09', 'nombre' => "Septiembre"],
        ['codigo' => '10', 'nombre' => "Octubre"],
        ['codigo' => '11', 'nombre' => "Noviembre"],
        ['codigo' => '12', 'nombre' => "Diciembre"]
    );
    return $meses;
}

function anios($up, $down)
{
    //anos arriba al actual, y anos abajo al actual
    $hoy = date("Y");
    $anios = array();
    $down = $down + 1;
    for ($i = $hoy + $up; $i > $hoy - $down; $i--) {
        $anios[] = $i;
    }
    return $anios;
}

function passGenerator($lenght = 10)
{
    $pass = "";
    $longitudPass = $lenght;
    $cadena = "ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
    //$cadena="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890";
    $longitudCadena = strlen($cadena);

    for ($i = 1; $i <= $longitudPass; $i++) {
        $pos = rand(0, $longitudCadena - 1);
        $pass .= substr($cadena, $pos, 1);
    }
    return $pass;
}

function numAleatorio($lenght = 5)
{
    $pass = "";
    $longitudPass = $lenght;
    $cadena = "123456789";
    $longitudCadena = strlen($cadena);

    for ($i = 1; $i <= $longitudPass; $i++) {
        $pos = rand(0, $longitudCadena - 1);
        $pass .= substr($cadena, $pos, 1);
    }
    return $pass;
}

//para sacar el formato d valor monetario con decimales
function formatMoney($cantidad, $decimales)
{
    $cantidad = number_format($cantidad, $decimales, ".", ",");
    return $cantidad;
}


function encrypt_decrypt($action, $string)
{
    $output = false;
    // global $encryption_method;
    // Pull the hashing method that will be used
    // Hash the password
    $secret_key = 'AA74CDCC2BBRT935136HH7B63C27';
    $key = hash('sha256', $secret_key);
    if ($action == 'encrypt') {
        // Generate a random string, hash it and get the first 16 character of the hashed string which will be ised as the IV
        /*
        $str = "qwertyuiopasdfghjklzxcvbnm,./;'\\[]-=`!@#\$%^&*()_+{}|\":?><0123456789QWERTYUIOPASDFGHJKLZXCVBNM";
        */
        $str = "0123456789QWERTYUIOPASDFGHJKLZXCVBNM";
        $shuffled = str_shuffle($str);
        $iv = substr(hash('sha256', $shuffled), 0, 16);
        $output = openssl_encrypt($string, "AES-256-CBC", $key, 0, $iv);
        $output = base64_encode($output);
        // Tidy up the string so that it survives the transport 100%
        $ivoutput = $iv . $output;
        // Concat the IV with the encrypted message
        return $ivoutput;
    } else {
        if ($action == 'decrypt') {
            $iv = substr($string, 0, 16);
            // Extract the IV from the encrypted string
            $string = substr($string, 16);
            // The rest of the encrypted string is the message
            $output = openssl_decrypt(base64_decode($string), "AES-256-CBC", $key, 0, $iv);
            return $output;
        }
    }
}


function validador_ruc($ruc)
{
    // Debe tener exactamente 13 dígitos numéricos
    if (!preg_match('/^\d{13}$/', $ruc)) {
        return false;
    }

    // Los últimos 3 dígitos deben ser 001
    if (substr($ruc, -3) !== '001') {
        return false;
    }

    return true;
}



/* function validador_cedula($cedula)
{
    // Debe tener exactamente 10 dígitos
    if (!preg_match('/^\d{10}$/', $cedula)) {
        return false;
    }

    $digits = str_split($cedula);

    // Región (01 a 24)
    $region = intval($digits[0] . $digits[1]);
    if ($region < 1 || $region > 24) {
        return false;
    }

    // Tercer dígito (cédula natural: 0-5). 
    // Si quieres, puedes omitir esta validación, pero es recomendable.
    $tercer = intval($digits[2]);
    if ($tercer < 0 || $tercer > 5) {
        return false;
    }

    $verificador = intval($digits[9]);

    // Suma pares: posiciones 2,4,6,8 (índices 1,3,5,7)
    $suma_pares = intval($digits[1]) + intval($digits[3]) + intval($digits[5]) + intval($digits[7]);

    // Suma impares: posiciones 1,3,5,7,9 (índices 0,2,4,6,8) con regla *2 -9 si >9
    $suma_impares = 0;
    foreach ([0, 2, 4, 6, 8] as $i) {
        $val = intval($digits[$i]) * 2;
        if ($val > 9) $val -= 9;
        $suma_impares += $val;
    }

    $total = $suma_pares + $suma_impares;

    // Dígito verificador: (10 - (total % 10)) % 10
    $calc = (10 - ($total % 10)) % 10;

    return $calc === $verificador;
} */

function validador_cedula($cedula)
{
    $cedula = trim($cedula);

    // 1) Debe tener exactamente 10 dígitos numéricos
    if (!preg_match('/^\d{10}$/', $cedula)) {
        return false;
    }

    // 2) Región 01 a 24
    $region = intval(substr($cedula, 0, 2));
    if ($region < 1 || $region > 24) {
        return false;
    }

    // (Opcional) 3er dígito 0-5 para persona natural
    // $tercer = intval(substr($cedula, 2, 1));
    // if ($tercer < 0 || $tercer > 5) return false;

    // 3) Último dígito (verificador)
    $ultimo = intval(substr($cedula, 9, 1));

    // 4) Suma de pares: posiciones 2,4,6,8 (índices 1,3,5,7)
    $pares = intval(substr($cedula, 1, 1))
        + intval(substr($cedula, 3, 1))
        + intval(substr($cedula, 5, 1))
        + intval(substr($cedula, 7, 1));

    // 5) Impares: posiciones 1,3,5,7,9 (índices 0,2,4,6,8)
    $impares = 0;
    $indices = array(0, 2, 4, 6, 8);
    foreach ($indices as $i) {
        $n = intval(substr($cedula, $i, 1)) * 2;
        if ($n > 9) $n -= 9;
        $impares += $n;
    }

    // 6) Suma total
    $suma_total = $pares + $impares;

    // 7-8) Decena inmediata superior y dígito validador
    // equivalente a: (10 - (suma_total % 10)) % 10
    $digito_validador = (10 - ($suma_total % 10)) % 10;

    // 9) Comparar
    return ($digito_validador == $ultimo);
}


//tipos de ambientes para emision de comprobantes electronicos
function tipo_ambiente()
{
    $result = array(['codigo' => 1, 'nombre' => 'Pruebas'], ['codigo' => 2, 'nombre' => 'Producción']);
    return $result;
}

//tipo de identificacion para ventas
function identificacion_venta()
{
    $result = array(
        ['codigo' => '04', 'nombre' => 'RUC'],
        ['codigo' => '05', 'nombre' => 'CEDULA'],
        ['codigo' => '06', 'nombre' => 'PASAPORTE'],
        ['codigo' => '07', 'nombre' => 'VENTA A CONSUMIDOR FINAL']
    );
    return $result;
}

//tipo de identificacion para compras
function identificacion_compra()
{
    $result = array(
        ['codigo' => '01', 'nombre' => 'RUC'],
        ['codigo' => '02', 'nombre' => 'CEDULA'],
        ['codigo' => '03', 'nombre' => 'PASAPORTE/IDENTIFICACION DEL EXTERIOR']
    );
    return $result;
}

//tipo de tarjetas de credito
function tarjetas_de_credito()
{
    $result = array(
        ['codigo' => '01', 'nombre' => 'AMERICAN EXPRESS'],
        ['codigo' => '02', 'nombre' => 'DINERS CLUB'],
        ['codigo' => '04', 'nombre' => 'MASTERCARD'],
        ['codigo' => '05', 'nombre' => 'VISA'],
        ['codigo' => '07', 'nombre' => 'OTRA TARJETA']
    );
    return $result;
}

//tipo de identificacion para LIQUIDACIONES DE COMPRAS, NOTAS DE CREDITO, NOTAS DE DEBITO, Y RETENCIONES
function identificacion_lc_nc_nd_ret()
{
    $result  = array(
        ['codigo' => '04', 'nombre' => 'RUC'],
        ['codigo' => '05', 'nombre' => 'CEDULA'],
        ['codigo' => '06', 'nombre' => 'PASAPORTE'],
        ['codigo' => '08', 'nombre' => 'IDENTIFICACION DEL EXTERIOR']
    );
    return $result;
}

//TIPOS DE IMPUESTOS
function impuesto()
{
    $result = array(['codigo' => 2, 'impuesto' => 'IVA'], ['codigo' => 3, 'impuesto' => 'ICE'], ['codigo' => 5, 'impuesto' => 'IRBPNR']);
    return $result;
}
//TARIFA IVA
function tarifa_iva()
{
    $result = array(
        ['codigo' => 0, 'porcentaje' => '0%'],
        ['codigo' => 2, 'porcentaje' => '12%'],
        ['codigo' => 6, 'porcentaje' => 'No Objeto de Impuesto'],
        ['codigo' => 7, 'porcentaje' => 'Exento de IVA']
    );
    return $result;
}

//impuestos a retener
function impuesto_a_retener()
{
    $result = array(['codigo' => 1, 'impuesto' => 'RENTA'], ['codigo' => 2, 'impuesto' => 'IVA'], ['codigo' => 6, 'impuesto' => 'ISD']);
    return $result;
}

// denominacion para crear nuevos productos servicios o activos fijos
function tipo_producto()
{
    $result = array(['codigo' => '01', 'descripcion' => 'PRODUCTO'], ['codigo' => '02', 'descripcion' => 'SERVICIO'], ['codigo' => '03', 'descripcion' => 'ACTIVO FIJO']);
    return $result;
}

//formas de pagos ventas
function formas_de_pago()
{
    $result = array(
        ['codigo' => '01', 'nombre' => 'SIN UTILIZACION DEL SISTEMA FINANCIERO'],
        ['codigo' => '15', 'nombre' => 'COMPENSACIÓN DE DEUDAS'],
        ['codigo' => '16', 'nombre' => 'TARJETA DE DÉBITO'],
        ['codigo' => '17', 'nombre' => 'DINERO ELECTRÓNICO'],
        ['codigo' => '18', 'nombre' => 'TARJETA PREPAGO'],
        ['codigo' => '19', 'nombre' => 'TARJETA DE CRÉDITO'],
        ['codigo' => '20', 'nombre' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO'],
        ['codigo' => '21', 'nombre' => 'ENDOSO DE TÍTULOS']
    );
    return $result;
}

//novedades sueldos LAS LETRAS SON  referencia para los calculos en la quincena o el rol
function novedades_sueldos()
{
    $result = array(
        ['codigo' => '1', 'nombre' => 'Otros Ingresos'],
        ['codigo' => '2', 'nombre' => 'Descuento'],
        ['codigo' => '3', 'nombre' => 'Anticípo'],
        ['codigo' => '4', 'nombre' => 'Horas Nocturnas'],
        ['codigo' => '5', 'nombre' => 'Horas Suplementarias'],
        ['codigo' => '6', 'nombre' => 'Horas Extraordinarias'],
        ['codigo' => '7', 'nombre' => 'Préstamo Quirografario'],
        ['codigo' => '8', 'nombre' => 'Préstamo hipotecario'],
        ['codigo' => '9', 'nombre' => 'Préstamo Empresa'],
        ['codigo' => '10', 'nombre' => 'Días no laborados'],
        ['codigo' => '14', 'nombre' => 'Aviso de salida']
    );
    return $result;
}

function motivo_salida_iess()
{
    $motivo = array(
        ['codigo' => 'T', 'nombre' => "Terminación del contrato"],
        ['codigo' => 'V', 'nombre' => "Renuncia voluntaria"],
        ['codigo' => 'B', 'nombre' => "Visto bueno"],
        ['codigo' => 'R', 'nombre' => "Despido unilateral por parte del empleador"],
        ['codigo' => 'S', 'nombre' => "Suspensión de partida"],
        ['codigo' => 'D', 'nombre' => "Desaparición del puesto dentro de la estructura de la empresa"],
        ['codigo' => 'I', 'nombre' => "Incapacidad permanente del trabajador"],
        ['codigo' => 'F', 'nombre' => "Muerte del trabajador"],
        ['codigo' => 'A', 'nombre' => "Abandono voluntario"]
    );
    return $motivo;
}

function calculos_rol_pagos($ano, $con)
{
    $sql = mysqli_query($con, "SELECT * FROM salarios WHERE ano= '" . $ano . "' and status ='1'");
    $row = mysqli_fetch_array($sql);
    return $row;
}


//me saca la letra de la columna y le asigna dependiendo el nombre que hay en un array
// array('enero', 'febrero') resultado en la columna A = enero, en la B febrero...
//la letra es el indice del array dependiendo el nombre
function letras_columnas_excel($titulosColumnas)
{

    $titulosConLetras = array();
    for ($i = 0; $i < count($titulosColumnas); $i++) {
        $letra = '';
        $dividendo = $i + 1;
        while ($dividendo > 0) {
            $modulo = ($dividendo - 1) % 26;
            $letra = chr(65 + $modulo) . $letra; // Convertir el valor ASCII en letra (A, B, C, ...)
            $dividendo = floor(($dividendo - 1) / 26);
        }
        $titulosConLetras[$letra] = $titulosColumnas[$i];
    }
    return ($titulosConLetras);
}

// obtiene la letra mediante un indice dado
function indice_letra($numColumnas)
{
    $letrasColumnas = array();
    for ($i = 0; $i < count($numColumnas); $i++) {
        $letra = '';
        $dividendo = $i + 1;

        while ($dividendo > 0) {
            $modulo = ($dividendo - 1) % 26;
            $letra = chr(65 + $modulo) . $letra; // Convertir el valor ASCII en letra (A, B, C, ...)
            $dividendo = floor(($dividendo - 1) / 26);
        }

        array_push($letrasColumnas, $letra);
    }
    return ($letrasColumnas);
}


function obtenerNombreMes($numeroMes)
{
    $nombreMeses = array(
        '01' => "Enero",
        '02' => "Febrero",
        '03' => "Marzo",
        '04' => "Abril",
        '05' => "Mayo",
        '06' => "Junio",
        '07' => "Julio",
        '08' => "Agosto",
        '09' => "Septiembre",
        '10' => "Octubre",
        '11' => "Noviembre",
        '12' => "Diciembre"
    );
    return $nombreMeses[$numeroMes];
}
?>