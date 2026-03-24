<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

// Info RUC/Cédula SRI - delega a ClienteController (MVC) - no requiere ruc_empresa
if ($action == 'info_ruc') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->infoRuc();
    exit;
}

// Ciudades por provincia - delega a ClienteController (MVC)
if ($action == 'ciudades') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->ciudades();
    exit;
}

// Estadísticas del cliente - delega a ClienteController (MVC)
if ($action == 'stats_cliente') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->statsCliente();
    exit;
}

// Guardar o actualizar cliente - delega a ClienteController (MVC)
if ($action == 'guardar_cliente') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->guardarCliente();
    exit;
}

// Buscar clientes - delega a ClienteController (MVC) - usa BD camagare_v8
if ($action == 'buscar_clientes') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->buscarClientes();
    exit;
}

// Eliminar cliente - delega a ClienteController (MVC) - usa BD camagare_v8
if ($action == 'eliminar_cliente') {
    if (!defined('ROOT_PATH')) require_once dirname(__DIR__) . '/app/bootstrap.php';
    require_once dirname(__DIR__) . '/app/Controllers/ClienteController.php';
    (new ClienteController())->eliminarCliente();
    exit;
}

/* buscar_clientes y eliminar_cliente delegados a ClienteController (MVC) - BD camagare_v8 */

/* Código legacy guardar_cliente - ya delegado a api/cliente.php
if ($action == 'guardar_cliente') {
    $ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
    $id_usuario  = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

    $ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
    $id_usuario  = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

    $id_cliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
    $tipo_id    = isset($_POST['tipo_id']) ? $_POST['tipo_id'] : '';

    if ($tipo_id == "07") {
        $ruc_cliente    = "9999999999999";
        $nombre_cliente = "CONSUMIDOR FINAL";
    } else {
        $ruc_cliente    = strClean(isset($_POST['ruc']) ? $_POST['ruc'] : '');
        $nombre_cliente = strClean(isset($_POST['nombre']) ? $_POST['nombre'] : '');
    }

    if ($ruc_cliente == "9999999999999") {
        $tipo_id = "07";
    }

    $email_cliente     = strClean(isset($_POST['email']) ? $_POST['email'] : '');
    $direccion_cliente = strClean(isset($_POST['direccion']) ? $_POST['direccion'] : '');
    $telefono_cliente  = strClean(isset($_POST['telefono']) ? $_POST['telefono'] : '');
    $plazo_cliente     = isset($_POST['plazo']) ? intval($_POST['plazo']) : 0;

    $provincia   = strClean(isset($_POST['provincia']) ? $_POST['provincia'] : '');
    $ciudad      = strClean(isset($_POST['ciudad']) ? $_POST['ciudad'] : '');
    $status      = strClean(isset($_POST['status']) ? $_POST['status'] : '');
    $id_vendedor = isset($_POST['id_vendedor']) ? intval($_POST['id_vendedor']) : 0;

    // Validaciones
    if (empty($ruc_cliente)) {
        notify('Ingrese número de identificación', 'error');
        exit;
    }

    if ($tipo_id == "05" && !validador_cedula($ruc_cliente)) {
        notify('Cédula incorrecta', 'error');
        exit;
    }

    if ($tipo_id == "04" && !validador_ruc($ruc_cliente)) {
        notify('RUC incorrecto', 'error');
        exit;
    }

    if (empty($nombre_cliente)) {
        notify('Ingrese nombre del cliente', 'error');
        exit;
    }

    if (empty($email_cliente)) {
        notify('Ingrese email del cliente', 'error');
        exit;
    }

    if (!validarCorreo($email_cliente)) {
        notify('Error en mail, puede ingresar varios correos separados por coma y espacio', 'error');
        exit;
    }

    if ($plazo_cliente < 0) {
        notify('Ingrese días de plazo', 'error');
        exit;
    }

    if (empty($ruc_empresa) || empty($id_usuario)) {
        notify('La sesión ha expirado, reingrese al sistema.', 'error');
        exit;
    }

    $fecha = date("Y-m-d H:i:s");

    // ==========================
    // INSERT
    // ==========================
    if ($id_cliente == 0) {

        $stmt = mysqli_prepare($con, "SELECT 1 FROM clientes WHERE ruc = ? AND ruc_empresa = ? LIMIT 1");
        if (!$stmt) {
            notify('Error interno (prepare select)', 'error');
            exit;
        }
        mysqli_stmt_bind_param($stmt, "ss", $ruc_cliente, $ruc_empresa);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            notify('El cliente ya está registrado', 'error');
            exit;
        }
        mysqli_stmt_close($stmt);

        $sql = "INSERT INTO clientes
                (ruc_empresa, nombre, tipo_id, ruc, telefono, email, direccion, fecha_agregado, plazo, id_usuario, provincia, ciudad, status, id_vendedor)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            notify('Error interno (prepare insert)', 'error');
            exit;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssiisssi",
            $ruc_empresa,
            $nombre_cliente,
            $tipo_id,
            $ruc_cliente,
            $telefono_cliente,
            $email_cliente,
            $direccion_cliente,
            $fecha,
            $plazo_cliente,
            $id_usuario,
            $provincia,
            $ciudad,
            $status,
            $id_vendedor
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo "<script>
                $.notify('Cliente registrado','success');
                document.querySelector('#guardar_cliente').reset();
                load(1);
            </script>";
            exit;
        } else {
            mysqli_stmt_close($stmt);
            notify('No se pudo guardar el cliente. Revise caracteres y datos.', 'error');
            exit;
        }
    }

    // ==========================
    // UPDATE
    // ==========================
    $stmt = mysqli_prepare($con, "SELECT 1 FROM clientes WHERE id != ? AND ruc = ? AND ruc_empresa = ? LIMIT 1");
    if (!$stmt) {
        notify('Error interno (prepare select update)', 'error');
        exit;
    }

    mysqli_stmt_bind_param($stmt, "iss", $id_cliente, $ruc_cliente, $ruc_empresa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        notify('El cliente ya está registrado', 'error');
        exit;
    }
    mysqli_stmt_close($stmt);

    // Nota: NO actualizo fecha_agregado para no perder histórico.
    $sql = "UPDATE clientes SET
                nombre = ?,
                tipo_id = ?,
                ruc = ?,
                telefono = ?,
                email = ?,
                direccion = ?,
                plazo = ?,
                id_usuario = ?,
                provincia = ?,
                ciudad = ?,
                status = ?,
                id_vendedor = ?
            WHERE id = ? AND ruc_empresa = ?";

    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        notify('Error interno (prepare update)', 'error');
        exit;
    }

    // tipos:
    // nombre(s) tipo_id(s) ruc(s) telefono(s) email(s) direccion(s) plazo(i) id_usuario(i) provincia(s) ciudad(s) status(s) id_vendedor(i) id(i) ruc_empresa(s)
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssiisssiiis",
        $nombre_cliente,
        $tipo_id,
        $ruc_cliente,
        $telefono_cliente,
        $email_cliente,
        $direccion_cliente,
        $plazo_cliente,
        $id_usuario,
        $provincia,
        $ciudad,
        $status,
        $id_vendedor,
        $id_cliente,
        $ruc_empresa
    );

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo "<script>
            $.notify('Cliente actualizado','success');
            setTimeout(function () {location.reload()}, 1000);
        </script>";
        exit;
    } else {
        mysqli_stmt_close($stmt);
        notify('No se pudo actualizar el cliente. Revise caracteres y datos.', 'error');
        exit;
    }
} */


?>