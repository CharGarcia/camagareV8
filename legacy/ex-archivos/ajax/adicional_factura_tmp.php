<?PHP
	include("../conexiones/conectalogin.php");
	session_start();
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
		
//para agregar un adicional al temporal de facturas adicional
if (isset($_GET['agregar'])){
	$serie_factura = $_GET['serie_factura'];
	$secuencial_factura = $_GET['secuencial_factura'];
	$concepto = $_GET['adicional_concepto'];
	$detalle = $_GET['adicional_descripcion'];
	
	$detalle_adicional_tmp = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, $id_usuario, '$serie_factura', $secuencial_factura, '$concepto','$detalle')");
	include("../ajax/muestra_adicional_factura_tmp.php");
}

//para eliminar un adicional de la factura
if (isset($_GET['id_detalle'])){
	$id_detalle = $_GET['id_detalle'];
	$elimina_detalle_por_facturarse = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_ad_tmp=$id_detalle");
	include("../ajax/muestra_adicional_factura_tmp.php");			
}

//para mostrar los datos de adicionales y formas de pago en la ventana modal a modificar datos
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		$serie_factura = $_GET['serie_factura'];
		$secuencial_factura = $_GET['secuencial_factura'];
		$busca_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = $id_usuario and serie_factura = '$serie_factura' and secuencial_factura = secuencial_factura";
		$query = mysqli_query($con, $busca_adicional_tmp);
		//para ver si ya estan agregados adicionales a la factura que se va hacer o sino se guarda el mail y la direccion como adicional
		$adicionales_encontradas = mysqli_num_rows($query);
			if ($adicionales_encontradas ==0){
				$id_cliente=$_GET['id_cliente'];
				
				$busca_empresa_detalle = "SELECT * FROM clientes WHERE id = $id_cliente ";
				$result_detalle = $con->query($busca_empresa_detalle);
				$datos_detalle = mysqli_fetch_array($result_detalle);
				$email=$datos_detalle['email'];
				$direccion=$datos_detalle['direccion'];
				$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, $id_usuario, '$serie_factura', $secuencial_factura, 'Mail','$email')");
				$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, $id_usuario, '$serie_factura', $secuencial_factura, 'Dirección','$direccion')");
			}
		include("../ajax/muestra_adicional_factura_tmp.php");
	}	
?>