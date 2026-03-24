<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();

session_start();
$id_usuario =$_SESSION['id_usuario'];
$id_empresa = $_SESSION['id_empresa'];

		 $p = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $aColumns = array('nombre_submodulo');//Columnas de busqueda
		 $sTable = "modulos_asignados as mod_asi INNER JOIN submodulos_menu as sub_men ON mod_asi.id_submodulo=sub_men.id_submodulo and mod_asi.id_usuario='".$id_usuario."' and mod_asi.id_empresa='".$id_empresa."'";
		 $sWhere = "" ;
		if ( $_GET['term'] != "" )
		{
			$sWhere = "WHERE (";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$p."%' OR ";
			}
			
			$sWhere = substr_replace($sWhere, "", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by sub_men.nombre_submodulo asc";

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
			$arreglo_submodulos = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_submodulos,"No hay datos");
			}else{
				while($palabras = mysqli_fetch_array($query)){
					$id_submodulo=$palabras['id_submodulo'];
						$row_array['id_submodulo']=$id_submodulo;
						$row_array['value'] = $palabras['nombre_submodulo'];
						$row_array['nombre']=$palabras['nombre_submodulo'];
						$row_array['ruta']=$palabras['ruta'];
					array_push($arreglo_submodulos,$row_array);
				}
			}
			echo json_encode($arreglo_submodulos);
			mysqli_close($con);
		}
?>