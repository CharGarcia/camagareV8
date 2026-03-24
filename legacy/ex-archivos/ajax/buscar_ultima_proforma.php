<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['siguiente_proforma'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_proformas = mysqli_query($con,"SELECT * FROM encabezado_proforma WHERE ruc_empresa = '".$ruc_empresa."' ");
		$count = mysqli_num_rows($cuenta_proformas);
		
		
		$sql_proforma_inicial = mysqli_query($con, "SELECT inicial_proforma FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'");
		$row_inicial_proforma = mysqli_fetch_array($sql_proforma_inicial);
		$inicial = $row_inicial_proforma['inicial_proforma'];
				
		if ($count ==0){
			echo $inicial;
		}else{
				$busca_proforma = "SELECT MIN(secuencial_proforma) as minimo, MAX(secuencial_proforma) as maximo FROM encabezado_proforma WHERE ruc_empresa = '".$ruc_empresa."' ";
				$result = $con->query($busca_proforma);
				$res_sql = mysqli_fetch_assoc($result);
				$inicial = $inicial;
				$proforma_final = intval($res_sql['maximo'])+1;

				if($inicial>$proforma_final){
					$proforma_final = $inicial + 1;
				}

					$serie_inicio_fin=array();
						foreach(range($inicial, $proforma_final) as $toda_la_serie ){
						$serie_inicio_fin[]= intval($toda_la_serie);
						}
						$proformas_registradas ="SELECT secuencial_proforma as proformas FROM encabezado_proforma WHERE ruc_empresa = '".$ruc_empresa."' and secuencial_proforma >= '".$inicial."' ";
						$result_todas = $con->query($proformas_registradas);
					
					$solo_registradas = array();
						while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
						$solo_registradas[] = intval($todas_las_encontradas['proformas']);
						}
						
						$proformas_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
						if ($proformas_faltantes == false){
							echo ($proforma_final);					
						}else{
							echo min($proformas_faltantes);
						}

		}	
}

?>