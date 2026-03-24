<?php
include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];

		$q = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		$text_buscar = explode(' ', $q);
		$like="";
		for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		{
			$like .= "%".$text_buscar[$i];
		}

		 $aColumns = array('nombres_apellidos','documento');//Columnas de busqueda
		 $sTable = "empleados";
		 $sWhere = "WHERE id_empresa='".$id_empresa."' and status=1" ;
		if ( $_GET['term'] != "" ){
			$sWhere = " WHERE id_empresa='".$id_empresa."' and status=1 AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ ){
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND id_empresa='".$id_empresa."' and status=1 OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND id_empresa='".$id_empresa."' and status=1 ", -3 );
			}
		$sWhere.=" order by nombres_apellidos desc";


		//pagination variables
		$page = 1;
		$per_page = 20; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '';
		//main query to fetch the data
		$sql="SELECT * FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			$arreglo_empleados = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_empleados,"No hay datos");
			}else{
			while($palabras = mysqli_fetch_array($query)){
					$row_array['value'] = $palabras['nombres_apellidos']." - ".$palabras['documento'];
					$row_array['id']=$palabras['id'];
					$row_array['nombres_apellidos']=$palabras['nombres_apellidos'];
					$row_array['documento']=$palabras['documento'];
					array_push($arreglo_empleados,$row_array);
			}
			}
			echo json_encode($arreglo_empleados);
			mysqli_close($con);
		}
?>