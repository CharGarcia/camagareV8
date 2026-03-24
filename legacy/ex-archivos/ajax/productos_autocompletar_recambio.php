<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
		 $q = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $text_buscar = explode(' ', $q);
		 $like="";
		 for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		 {
			 $like .= "%".$text_buscar[$i];
		 }

		 $id_cliente = mysqli_real_escape_string($con,(strip_tags($_GET['id_cliente'], ENT_QUOTES)));
		 $aColumns = array('pro_ser.nombre_producto','pro_ser.codigo_producto','cam_fac.lote_anterior');//Columnas de busqueda
		 $sTable = "cambio_productos_facturados as cam_fac INNER JOIN productos_servicios as pro_ser ON pro_ser.id=cam_fac.id_nuevo_producto";
		 $sWhere = "WHERE cam_fac.ruc_empresa='".$ruc_empresa."' and cam_fac.id_cliente='".$id_cliente."' " ;
		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE (cam_fac.ruc_empresa='".$ruc_empresa."' and cam_fac.id_cliente='".$id_cliente."' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND cam_fac.ruc_empresa='".$ruc_empresa."' and cam_fac.id_cliente='".$id_cliente."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND cam_fac.ruc_empresa='".$ruc_empresa."' and cam_fac.id_cliente='".$id_cliente."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by pro_ser.nombre_producto desc";

		//pagination variables
		$page = 1;
		$per_page = 50; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '/';
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
				$id_producto=$palabras['id_nuevo_producto'];
					$row_array['value'] = $palabras['codigo_producto']." - ".$palabras['nombre_producto']." Factura:".$palabras['factura']." Lote:".$palabras['nuevo_lote'];
					$row_array['id_producto']=$id_producto;
					$row_array['nombre']=$palabras['nombre_producto'];
					$row_array['codigo']=$palabras['codigo_producto'];
					$row_array['id_registro']=$palabras['id_cambio'];
					$row_array['cantidad']=$palabras['cant_cambiada'];
				array_push($arreglo_productos,$row_array);
			}
			}
			echo json_encode($arreglo_productos);
			mysqli_close($con);
		}
?>