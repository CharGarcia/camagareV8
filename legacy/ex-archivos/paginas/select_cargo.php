<?php
include("../conexiones/conectalogin.php");
$codigo_rama = $_POST['cargo'];
$conexion = conenta_login();
$sql = "SELECT * FROM cargo_iess WHERE codigo_rama = $codigo_rama ;";
$res = mysqli_query($conexion,$sql);
?>							
<option value="">Seleccione un cargo</option>
<?php
while($cargo = mysqli_fetch_assoc($res)){
?>
<option value="<?php echo $cargo['id_rama'] ?>"> <?php echo $cargo['cargo_rama'] ?> </option>
<?php
}
?>
