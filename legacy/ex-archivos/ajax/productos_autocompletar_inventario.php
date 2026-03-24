<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
		$condicion_ruc_empresa= compartirClientesProductos($con, $ruc_empresa)['productos'];
		$q = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));

		$text_buscar = explode(' ',$q);
		$like="";
		for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		{
			$like .= "%".$text_buscar[$i];
		}

		 $aColumns = array('nombre_producto','codigo_producto','codigo_auxiliar');//Columnas de busqueda
		 $sTable = "productos_servicios INNER JOIN unidad_medida ON unidad_medida.id_medida=productos_servicios.id_unidad_medida  ";
		 $sWhere = "WHERE $condicion_ruc_empresa and tipo_produccion ='01' and status=1" ;
		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE $condicion_ruc_empresa and tipo_produccion ='01' and status=1 AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND $condicion_ruc_empresa and tipo_produccion ='01' and status=1 OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND $condicion_ruc_empresa and tipo_produccion ='01' and status=1", -3 );
		}
		$sWhere.=" order by nombre_producto desc";


		//pagination variables
		$page = 1;
		$per_page = 10; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			$arreglo_productos = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_productos,"No hay datos");
			}else{
			while($palabras = mysqli_fetch_array($query)){
					$row_array['value'] = $palabras['codigo_producto']." - ".$palabras['nombre_producto'];
					$row_array['id']=$palabras['id'];
					$row_array['nombre']=$palabras['nombre_producto'];
					$row_array['precio']=$palabras['precio_producto'];
					$row_array['tipo']=$palabras['tipo_produccion'];
					$row_array['codigo']=$palabras['codigo_producto'];
					$row_array['unidad_medida'] = $palabras['nombre_medida'];
					$row_array['medida']=$palabras['id_unidad_medida'];
				array_push($arreglo_productos,$row_array);
			}
			}
			echo json_encode($arreglo_productos);
		}

		mysqli_close($con);
?>