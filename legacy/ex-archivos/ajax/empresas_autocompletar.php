<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$nivel = $_SESSION['nivel'];
		$empresa = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $aColumns = array('nombre','nombre_comercial','ruc');//Columnas de busqueda
		 if ($nivel==3){
			$sTable = "empresas";
		 }
		 if ($nivel==2){
		 $sTable = "empresas INNER JOIN empresa_asignada ON empresas.id = empresa_asignada.id_empresa and empresa_asignada.id_usuario = '".$id_usuario."' and empresas.estado='1' ";
		 }
		 //esta consulta si funciona cuando es un usuario administrador, cuando es super administrador no debe permitir registrar una empresa repetida
		 $sWhere = " " ;
		 if ( $_GET['term'] != "" ){
			$sWhere = "WHERE ( ";		
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$empresa."%' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, " ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by empresas.nombre asc";

		//pagination variables
		$page = 1;
		$per_page = 10; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		//main query to fetch the data
		$sql="SELECT empresas.ruc as ruc, empresas.id as id_empresa, empresas.nombre_comercial as nombre_comercial, empresas.nombre as nombre FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			$arreglo_empresas = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_empresas,"No hay datos.");
			}else{
			while($palabras = mysqli_fetch_array($query)){
				$id_empresa=$palabras['id_empresa'];
				    $row_array['id_empresa']=$id_empresa;
					$row_array['value'] = $palabras['nombre_comercial'];
					$row_array['nombre']=$palabras['nombre'];
					$row_array['ruc']=$palabras['ruc'];
					$row_array['nombre_comercial']=$palabras['nombre_comercial'];
				array_push($arreglo_empresas,$row_array);
			}
			}
			echo json_encode($arreglo_empresas);
			mysqli_close($con);
		}
?>