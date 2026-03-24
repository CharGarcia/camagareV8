<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$c = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $aColumns = array('nombre','cedula');//Columnas de busqueda
		 $sTable = "usuarios LEFT JOIN usuario_asignado ON usuarios.id=usuario_asignado.id_usuario and usuario_asignado.id_adm = '".$id_usuario."' ";
		 $sWhere = " WHERE usuario_asignado.id_usuario is null and usuarios.estado = '1' and usuarios.nivel < 2 " ;
		 if ( $_GET['term'] != "" ){
			$sWhere = "WHERE ( usuario_asignado.id_usuario is null and usuarios.estado = '1' and usuarios.nivel < 2 and ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$c."%' and usuario_asignado.id_usuario is null and usuarios.estado = '1' and usuarios.nivel < 2 OR ";
			}
			
			$sWhere = substr_replace( $sWhere, " and usuario_asignado.id_usuario is null and usuarios.estado = '1' and usuarios.nivel < 2 ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by usuarios.nombre desc";

		//pagination variables
		$page = 1;
		$per_page = 10; //how much records you want to show
		//$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		//main query to fetch the data
		$sql="SELECT usuarios.id as id, usuarios.nombre as nombre, usuarios.cedula as cedula FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			$arreglo_usuarios = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_usuarios,"No hay datos.");
			}else{
			while($palabras = mysqli_fetch_array($query)){
				$id_usuario=$palabras['id'];
				    $row_array['id']=$id_usuario;
					$row_array['value'] = $palabras['nombre'];
					$row_array['nombre']=$palabras['nombre'];
					$row_array['cedula']=$palabras['cedula'];
				array_push($arreglo_usuarios,$row_array);
			}
			}
			echo json_encode($arreglo_usuarios);
			mysqli_close($con);
		}
?>