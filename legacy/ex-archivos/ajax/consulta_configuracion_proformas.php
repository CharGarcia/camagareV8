	<?php
	include("../conexiones/conectalogin.php");
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$con = conenta_login();
	$configuracion_proformas = new consulta_configuracion();
	
	//para la consultar si trabaja con inventario
	if (isset($_POST['opcion_mostrar']) && ($_POST['opcion_mostrar']=='inventario')){	
		$serie_consultada =$_POST['serie_consultada'];
		$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada);
		echo $consulta_configuracion['inventario'];
	}
	
	//para la consultar si se muestra medida
	if (isset($_POST['opcion_mostrar']) && ($_POST['opcion_mostrar']=='medida')){	
		$serie_consultada =$_POST['serie_consultada'];
		$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada);
		echo $consulta_configuracion['medida'];
	}
	
	//para la consultar si se muestra lote
	if (isset($_POST['opcion_mostrar']) && ($_POST['opcion_mostrar']=='lote')){	
		$serie_consultada =$_POST['serie_consultada'];
		$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada);
		echo $consulta_configuracion['lote'];
	}
	
	//para la consultar si se muestra bodega
	if (isset($_POST['opcion_mostrar']) && ($_POST['opcion_mostrar']=='bodega')){	
		$serie_consultada =$_POST['serie_consultada'];
		$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada);
		echo $consulta_configuracion['bodega'];
	}
	
	//para la consultar si se muestra vencimiento
	if (isset($_POST['opcion_mostrar']) && ($_POST['opcion_mostrar']=='vencimiento')){	
		$serie_consultada =$_POST['serie_consultada'];
		$consulta_configuracion=$configuracion_proformas->consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada);
		echo $consulta_configuracion['vencimiento'];
	}
	
	
	class consulta_configuracion{
		///para consultar datos de la tabla
		public function consulta_configuracion_proformas($ruc_empresa, $con, $serie_consultada){	
		$consulta_configuracion = mysqli_query($con, "SELECT * FROM configuracion_proformas WHERE ruc_empresa ='".$ruc_empresa."' and serie_sucursal ='".$serie_consultada."';");
		return mysqli_fetch_array($consulta_configuracion);
		}
	
	}
	
?>		
		