<?php
ini_set('date.timezone','America/Guayaquil');
function control_usuario_entrada($id_usuario, $accion){
$con = conenta_login();
if ($accion=="entrada"){
$fecha_entrada=date("Y-m-d H:i:s");
$fecha_salida='0';
}
if ($accion=="salida"){
$fecha_salida=date("Y-m-d H:i:s");
$fecha_entrada='0';
}
$ip_pc=getRealIP();
$actualizar_entrada=mysqli_query($con, "UPDATE control_usuarios SET estado='OFFLINE' WHERE id_usuario='".$id_usuario."'");
$registra_entrada_usuario=mysqli_query($con, "INSERT INTO control_usuarios VALUES (null, '".$id_usuario."','".$fecha_entrada."','".$fecha_salida."','".$ip_pc."','ONLINE')");
mysqli_close($con);
}

function control_usuario_salida($id_usuario, $accion){
include(dirname(__DIR__, 2) . "/conexiones/conectalogin.php");
$con = conenta_login();
if ($accion=="entrada"){
$fecha_entrada=date("Y-m-d H:i:s");
$fecha_salida='0';
}
if ($accion=="salida"){
$fecha_salida=date("Y-m-d H:i:s");
$fecha_entrada='0';
}
$ip_pc=getRealIP();
//actualizar el estado de la entrada a offline
$actualizar_entrada=mysqli_query($con, "UPDATE control_usuarios SET estado='OFFLINE' WHERE id_usuario='".$id_usuario."'");
$registra_entrada_usuario=mysqli_query($con, "INSERT INTO control_usuarios VALUES (null, '".$id_usuario."','".$fecha_entrada."','".$fecha_salida."','".$ip_pc."','OFFLINE')");
mysqli_close($con);
}


function getRealIP(){
    if (!empty($_SERVER['HTTP_CLIENT_IP']))
        return $_SERVER['HTTP_CLIENT_IP'];
       
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
   
    return $_SERVER['REMOTE_ADDR'];
}
?>