<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

		$p = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));

		$text_buscar = explode(' ', $p);
		$like="";
		for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		{
			$like .= "%".$text_buscar[$i];
		}

		 $aColumns = array('razon_social','nombre_comercial','ruc_proveedor');//Columnas de busqueda
		 $sTable = "proveedores";
		 $sWhere = "WHERE ruc_empresa = '".$ruc_empresa."'";
		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE ruc_empresa = '".$ruc_empresa."' and ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' and ruc_empresa = '".$ruc_empresa."' OR ";
			}
			$sWhere = substr_replace( $sWhere, " and ruc_empresa = '".$ruc_empresa."'", -3 );
			$sWhere .= '';
		}
		$sWhere.=" order by razon_social desc";

		//pagination variables
		$page = 1;
		$per_page = 10; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			$arreglo_proveedores = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_proveedores,"No hay datos");
			}else{
			while($palabras = mysqli_fetch_array($query)){
				$id_proveedor=$palabras['id_proveedor'];
				    $row_array['id_proveedor']=$id_proveedor;
					$row_array['value'] = $palabras['razon_social'];
					$row_array['razon_social']=$palabras['razon_social'];
					$row_array['nombre_comercial']=$palabras['nombre_comercial'];
					$row_array['ruc_proveedor']=$palabras['ruc_proveedor'];
					$row_array['mail_proveedor']=$palabras['mail_proveedor'];
				array_push($arreglo_proveedores,$row_array);
			}
			}
			echo json_encode($arreglo_proveedores);
			mysqli_close($con);
		}
?>