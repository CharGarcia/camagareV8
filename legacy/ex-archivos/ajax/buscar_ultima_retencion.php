<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_re'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_re"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_retenciones = mysqli_query($con,"SELECT * FROM encabezado_retencion WHERE ruc_empresa = '".$ruc_empresa."' and serie_retencion = '".$serie."' ");
		$count = mysqli_num_rows($cuenta_retenciones);
	
		$busca_retencion_inicial = mysqli_query($con,"SELECT inicial_cr FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'");
		$inicial_retencion = mysqli_fetch_array($busca_retencion_inicial);
		$inicial = $inicial_retencion['inicial_cr'];
		
		if ($count ==0){
			echo $inicial;
		}else{
				$busca_retencion = mysqli_query($con, "SELECT MIN(secuencial_retencion) as minimo, MAX(secuencial_retencion) as maximo FROM encabezado_retencion WHERE ruc_empresa = '".$ruc_empresa."' and serie_retencion = '".$serie."' ");
				$res_sql = mysqli_fetch_assoc($busca_retencion);
				$inicial = intval($inicial);//$res_sql['minimo'];
				$retencion_final = intval($res_sql['maximo'])+1;

				if($inicial>$retencion_final){
					$retencion_final = $inicial + 1;
				}
				
					$serie_inicio_fin=array();
						foreach(range($inicial, $retencion_final) as $toda_la_serie ){
						$serie_inicio_fin[]= $toda_la_serie;
						}
						$retenciones_registradas ="SELECT secuencial_retencion as retenciones FROM encabezado_retencion WHERE ruc_empresa = '".$ruc_empresa."' and serie_retencion = '".$serie."' and secuencial_retencion >= '".$inicial."' ";
						$result_todas = $con->query($retenciones_registradas);
					$solo_registradas = array();
						while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
						$solo_registradas[] = $todas_las_encontradas['retenciones'];
									}
						$retenciones_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
						if ($retenciones_faltantes == false){
						echo $retencion_final;					
						}else{
						echo min($retenciones_faltantes);
						}
		}	
					
	}

?>