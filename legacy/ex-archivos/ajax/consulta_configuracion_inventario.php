	<?php
//para traer los datos de la sucursal y rellenar en los campos mediante el cambio de serie
		include("../conexiones/conectalogin.php");
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
	
	//para la consulta desde la configuracion del inventario
	if (isset($_POST['configura']) && ($_POST['configura']=='opcion')){	
		if (isset($_POST['serie_consultada'])){
		$serie_consultada =$_POST['serie_consultada'];
		$conexion = conenta_login();
		$sql = "SELECT * FROM configuracion_inventarios where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_consultada."';";
		$resultado_busqueda = mysqli_query($conexion,$sql);
		$info_configuracion = mysqli_fetch_array($resultado_busqueda);
		$resultado_configuracion = $info_configuracion['opcion'];
		?>
		<input type="hidden" value="<?php echo $resultado_configuracion;?>" id="opcion_inventario">
		<?php
		
		}
	}
	//para la consulta desde nueva factura
	
	if (isset($_POST['nueva_factura']) && ($_POST['nueva_factura']=='opcion')){	
		if (isset($_POST['serie_consultada'])){
		$serie_consultada =$_POST['serie_consultada'];
		$conexion = conenta_login();
		$sql = "SELECT * FROM configuracion_inventarios where ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_consultada."';";
		$resultado_busqueda = mysqli_query($conexion,$sql);
		$info_configuracion = mysqli_fetch_array($resultado_busqueda);
		$resultado_configuracion = $info_configuracion['opcion'];
		echo $resultado_configuracion;
		}
	}
	
	
	
	
?>		
		