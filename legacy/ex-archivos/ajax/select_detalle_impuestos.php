<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();

if (isset($_POST['impuesto'])) {
	//para consultar de la base de tarifa iva	
	if ($_POST['impuesto'] == "2") {
		$sql = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE status ='1' order by porcentaje_iva asc ");
		while ($o = mysqli_fetch_assoc($sql)) {
?>
			<option value="<?php echo $o['codigo'] ?>" selected><?php echo $o['tarifa'] ?> </option>
		<?php
		}
	}
	//para consultar de la tabla de tarifa ice
	if ($_POST['impuesto'] == "3") {
		?>
		<option value="0" selected>ICE</option>
	<?php
	}
	//para consultar de la tabla de botellas
	if ($_POST['impuesto'] == "5") {
	?>
		<option value="0" selected>IRBPNR</option>
<?php
	}
}
?>