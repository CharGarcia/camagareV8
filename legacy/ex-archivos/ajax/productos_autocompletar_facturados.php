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
		 $aColumns = array('cue_fac.nombre_producto','cue_fac.codigo_producto','cue_fac.secuencial_factura','cue_fac.lote');//Columnas de busqueda
		 $sTable = "cuerpo_factura as cue_fac INNER JOIN encabezado_factura as enc_fac ON enc_fac.serie_factura=cue_fac.serie_factura and enc_fac.secuencial_factura=cue_fac.secuencial_factura";
		 $sWhere = "WHERE cue_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.id_cliente='".$id_cliente."' and cue_fac.tipo_produccion='01'" ;
		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE (cue_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.id_cliente='".$id_cliente."' and cue_fac.tipo_produccion='01' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND cue_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.id_cliente='".$id_cliente."' and cue_fac.tipo_produccion='01' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND cue_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.ruc_empresa='".$ruc_empresa."' and enc_fac.id_cliente='".$id_cliente."' and cue_fac.tipo_produccion='01' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by cue_fac.nombre_producto desc";

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
				$id_producto=$palabras['id_producto'];
					$row_array['value'] = $palabras['codigo_producto']." - ".$palabras['nombre_producto']." Fact:".$palabras['serie_factura']."-".$palabras['secuencial_factura']." Lote:".$palabras['lote'];
					$row_array['id_producto']=$id_producto;
					$row_array['nombre']=$palabras['nombre_producto'];
					$row_array['codigo']=$palabras['codigo_producto'];
					$row_array['id_registro']=$palabras['id_cuerpo_factura'];
					$row_array['cantidad']=$palabras['cantidad_factura'];
				array_push($arreglo_productos,$row_array);
			}
			}
			echo json_encode($arreglo_productos);
			mysqli_close($con);
		}
?>