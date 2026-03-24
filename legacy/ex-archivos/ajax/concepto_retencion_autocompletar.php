<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
		$p = mysqli_real_escape_string($con,(strip_tags($_GET['term'], ENT_QUOTES)));
		 $aColumns = array('codigo_ret','concepto_ret','porcentaje_ret','impuesto_ret','cod_anexo_ret');//Columnas de busqueda
		 $sTable = "retenciones_sri";
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
		$sWhere.=" order by concepto_ret desc";

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
			$arreglo_retenciones_compras = array();
			if (mysqli_num_rows($query) ==0){
				array_push($arreglo_retenciones_compras,"No hay datos");
			}else{
			while($palabras = mysqli_fetch_array($query)){
				$id_ret=$palabras['id_ret'];
				    $row_array['id_ret']=$id_ret;
					$row_array['value'] = $palabras['cod_anexo_ret']." - ".$palabras['concepto_ret'];
					$row_array['concepto_ret']=$palabras['concepto_ret'];
					$row_array['porcentaje_ret']=$palabras['porcentaje_ret'];
				array_push($arreglo_retenciones_compras,$row_array);
			}
			}
			echo json_encode($arreglo_retenciones_compras);
			mysqli_close($con);
		}
?>