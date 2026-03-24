<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
		 $q = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $cv = mysqli_real_escape_string($con,(strip_tags($_GET['cv'], ENT_QUOTES)));
		 $aColumns = array('det_con.nombre_producto','det_con.codigo_producto','det_con.lote','det_con.nup');
		 $sTable = "detalle_consignacion as det_con INNER JOIN encabezado_consignacion as enc_con 
		 ON enc_con.codigo_unico=det_con.codigo_unico INNER JOIN bodega as bod ON bod.id_bodega=det_con.id_bodega";
		 $sWhere = "WHERE det_con.ruc_empresa='".$ruc_empresa."' and enc_con.ruc_empresa='".$ruc_empresa."' and enc_con.numero_consignacion='".$cv."' and enc_con.operacion='ENTRADA'" ;

		 $text_buscar = explode(' ', $q);
		 $like="";
		 for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		 {
			 $like .= "%".$text_buscar[$i];
		 }

		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE det_con.ruc_empresa='".$ruc_empresa."' and enc_con.ruc_empresa='".$ruc_empresa."' and enc_con.numero_consignacion='".$cv."' and enc_con.operacion='ENTRADA' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND det_con.ruc_empresa='".$ruc_empresa."' and enc_con.ruc_empresa='".$ruc_empresa."' and enc_con.numero_consignacion='".$cv."' and enc_con.operacion='ENTRADA' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND det_con.ruc_empresa='".$ruc_empresa."' and enc_con.ruc_empresa='".$ruc_empresa."' and enc_con.numero_consignacion='".$cv."' and enc_con.operacion='ENTRADA'", -3 );
		}
		$sWhere.=" order by det_con.nombre_producto desc";
		
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
						$row_array['value'] = $palabras['codigo_producto']." - ".$palabras['nombre_producto']." Lote:".$palabras['lote']." Nup:".$palabras['nup']." Bod:".strtoupper(substr($palabras['nombre_bodega'],0,1));
						$row_array['id_producto']=$id_producto;
						$row_array['nombre']=$palabras['codigo_producto']." - ".$palabras['nombre_producto']." Lote:".$palabras['lote']." Nup:".$palabras['nup']." Bod:".strtoupper(substr($palabras['nombre_bodega'],0,1));
						$row_array['codigo']=$palabras['codigo_producto'];
						$row_array['id_cv']=$palabras['id_det_consignacion'];
						
						$facturado=mysqli_query($con,"SELECT sum(cant_consignacion) as facturado FROM encabezado_consignacion enc_con INNER JOIN detalle_consignacion det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa='".$ruc_empresa."' and det_con.ruc_empresa='".$ruc_empresa."' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='FACTURA' and det_con.numero_orden_entrada='".$cv."' and det_con.id_producto='".$id_producto."' and det_con.lote='".$palabras['lote']."' and det_con.nup='".$palabras['nup']."'");
						$row_facturado=mysqli_fetch_array($facturado);
						$total_facturado=$row_facturado['facturado'];
						$total_facturado_suma=$row_facturado['facturado'];

						$devuelto=mysqli_query($con,"SELECT sum(cant_consignacion) as devuelto FROM encabezado_consignacion enc_con INNER JOIN detalle_consignacion det_con ON enc_con.codigo_unico=det_con.codigo_unico WHERE enc_con.ruc_empresa='".$ruc_empresa."' and det_con.ruc_empresa='".$ruc_empresa."' and enc_con.tipo_consignacion='VENTA' and enc_con.operacion='DEVOLUCIÓN' and det_con.numero_orden_entrada='".$cv."' and det_con.id_producto='".$id_producto."' and det_con.lote='".$palabras['lote']."' and det_con.nup='".$palabras['nup']."'");
						$row_devuelto=mysqli_fetch_array($devuelto);
						$total_devuelto_suma=$row_devuelto['devuelto'];
						
						$saldo_final=$total_facturado_suma+$total_devuelto_suma;
						$row_array['saldo']=$palabras['cant_consignacion']-$saldo_final;
					array_push($arreglo_productos,$row_array);
				}
			}
			echo json_encode($arreglo_productos);
			mysqli_close($con);
		}
?>