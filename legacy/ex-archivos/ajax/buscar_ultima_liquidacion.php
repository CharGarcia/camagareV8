<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_liq'])){
	if (empty($_POST['serie_liq'])) {
          $ultima_liquidacion = "Seleccione serie.";
        }else if (!empty($_POST['serie_liq'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_liq"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_liquidacion = mysqli_query($con,"SELECT * FROM encabezado_liquidacion WHERE ruc_empresa = '".$ruc_empresa."' and serie_liquidacion = '".$serie."' ");
		$count = mysqli_num_rows($cuenta_liquidacion);

		//la liquidacion inicial segun se configura en el sistema camagare
		$busca_liquidacion_inicial = "SELECT inicial_liq FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'";
			$result = $con->query($busca_liquidacion_inicial);
			$inicial_liquidacion = mysqli_fetch_row($result);
			$inicial = $inicial_liquidacion['0'];
		
		if ($count ==0){
			echo ($inicial);
		}else{
				$busca_liquidacion = "SELECT MIN(secuencial_liquidacion) as minimo, MAX(secuencial_liquidacion) as maximo FROM encabezado_liquidacion WHERE ruc_empresa = '".$ruc_empresa."' and serie_liquidacion = '".$serie."' ";
				$result_liq = $con->query($busca_liquidacion);
				$res_sql = mysqli_fetch_assoc($result_liq);
				$liquidacion_inicial = intval($inicial); 
				$liquidacion_final = intval($res_sql['maximo'])+1;

				if($liquidacion_inicial>$liquidacion_final){
					$liquidacion_final = $liquidacion_inicial + 1;
				}

					$serie_inicio_fin=array();
						foreach(range($inicial, $liquidacion_final) as $toda_la_serie ){
						$serie_inicio_fin[]= intval($toda_la_serie);
						}
						$liquidaciones_registradas ="SELECT secuencial_liquidacion as liquidaciones FROM encabezado_liquidacion WHERE ruc_empresa = '".$ruc_empresa."' and serie_liquidacion = '".$serie."' and secuencial_liquidacion >= '".$inicial."' ";
						$result_todas = $con->query($liquidaciones_registradas);
					
					$solo_registradas = array();
						while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
						$solo_registradas[] = intval($todas_las_encontradas['liquidaciones']);
						}
						
						$liquidaciones_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
						if ($liquidaciones_faltantes == false){
						echo ($liquidacion_final);					
						}else{
						echo min($liquidaciones_faltantes);
						}

		}	
					
	}
}

?>