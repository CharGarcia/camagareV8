<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


//para mostrar mora empresas
if ($action == 'muestra_mora_empresa') {
    $result_avisos_mora = mysqli_query($con, "SELECT * FROM avisos_camagare WHERE ruc_empresa='" . $ruc_empresa . "'");
    $row_avisos_mora = mysqli_fetch_array($result_avisos_mora);
    $aviso_mora = isset($row_avisos_mora['detalle_aviso']) ? $row_avisos_mora['detalle_aviso'] : "";
    if (!empty($aviso_mora)) {
        echo $row_avisos_mora['detalle_aviso'];
    }
}
