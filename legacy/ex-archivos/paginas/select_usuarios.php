<?php
include("../conexiones/conectalogin.php");
session_start();
$conexion = conenta_login();
$id = $_SESSION['id'];
$sql = "SELECT u.id as id_usuario, u.nombre as nombre, u.cedula as cedula FROM usuarios u, usuario_asignado ua where u.id = ua.id_usuario  and ua.id_adm = $id ;";
$res = mysqli_query($conexion,$sql);
?>
<option value="0" >Seleccione un usuario</option>
<?php while($p = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $p['id_usuario'] ?>"> <?php echo $p['nombre'] ?> C: <?php echo $p['cedula'] ?> </option> 
<?php
}

?>