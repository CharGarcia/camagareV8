<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_recibo'])){
	//if (empty($_POST['serie_fe'])) {
        //  $ultima_recibo = "Seleccione serie.";
        //}else if (!empty($_POST['serie_fe'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_recibo"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		echo siguiente_documento($con, $ruc_empresa, $serie);			
	//}
}


function siguiente_documento($con, $ruc_empresa, $serie){
	$cuenta_recibos = mysqli_query($con,"SELECT * FROM encabezado_recibo WHERE ruc_empresa = '".$ruc_empresa."' and serie_recibo = '".$serie."'");
	$count = mysqli_num_rows($cuenta_recibos);

			$inicial = 1;
	
	if ($count ==0){
		return $inicial;
	}else{
			$sql_recibo = mysqli_query($con, "SELECT MIN(secuencial_recibo) as minimo, MAX(secuencial_recibo) as maximo FROM encabezado_recibo WHERE ruc_empresa = '".$ruc_empresa."' and serie_recibo = '".$serie."'");
			$row_recibo = mysqli_fetch_assoc($sql_recibo);
			$inicial = intval($inicial);
			$final = intval($row_recibo['maximo'])+1;

			if($inicial>$final){
				$final = $inicial + 1;
			}

				$serie_inicio_fin=array();
					foreach(range($inicial, $final) as $toda_la_serie ){
					$serie_inicio_fin[]= intval($toda_la_serie);
					}

					$recibos_registradas =mysqli_query($con, "SELECT secuencial_recibo as recibos FROM encabezado_recibo WHERE ruc_empresa = '".$ruc_empresa."' and serie_recibo = '".$serie."' and secuencial_recibo >= '".$inicial."' ");
				
				$solo_registradas = array();
					while ($todas_las_encontradas=mysqli_fetch_array($recibos_registradas)){
					$solo_registradas[] = intval($todas_las_encontradas['recibos']);
					}
					
					$recibos_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
					if ($recibos_faltantes == false){
					return $final;					
					}else{
					return min($recibos_faltantes);
					}

	}	
}

?>