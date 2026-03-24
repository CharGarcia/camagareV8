<?php
include("../conexiones/conectalogin.php");
session_start();
$id = $_SESSION['id_usuario'];
$id_usu = $_POST['id_usu'];
$conexion = conenta_login();
$sql = "SELECT ea.id_empresa as id_empresa, e.nombre_comercial as nombre FROM empresas e, empresa_asignada ea WHERE e.id = ea.id_empresa and ea.id_usuario = $id_usu and e.estado = '1' ;";
$res = mysqli_query($conexion,$sql);
?>							
<option value="0">Seleccione una empresa</option>
<?php
while($p = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $p['id_empresa'] ?>" selected> <?php echo $p['nombre'] ?> </option>
<?php
}
?>
