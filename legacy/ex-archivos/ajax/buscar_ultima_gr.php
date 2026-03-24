<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_gr'])){
	if (empty($_POST['serie_gr'])) {
          $ultima_gr = "Seleccione una sucursal.";
        }else if (!empty($_POST['serie_gr'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_gr"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_gr = mysqli_query($con,"SELECT * FROM encabezado_gr WHERE ruc_empresa = '".$ruc_empresa."' and serie_gr = '".$serie."' ");
		$count = mysqli_num_rows($cuenta_gr);
		
		$busca_gr_inicial = "SELECT inicial_gr FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'";
			$result = $con->query($busca_gr_inicial);
			$inicial_gr = mysqli_fetch_row($result);
			$inicial = $inicial_gr['0'];
		
		
		if ($count ==0){
			echo ($inicial);
		}else{
				$busca_gr = "SELECT MIN(secuencial_gr) as minimo, MAX(secuencial_gr) as maximo FROM encabezado_gr WHERE ruc_empresa = '".$ruc_empresa."' and serie_gr = '".$serie."' ";
				$result = $con->query($busca_gr);
				$res_sql = mysqli_fetch_assoc($result);
				$gr_inicial = $res_sql['minimo'];
				$gr_final = $res_sql['maximo']+1;

				if($gr_inicial>$gr_final){
					$gr_final = $gr_inicial + 1;
				}


					$serie_inicio_fin=array();
						foreach(range($inicial, $gr_final) as $toda_la_serie ){
						$serie_inicio_fin[]= $toda_la_serie;
						}
						$gr_registradas ="SELECT secuencial_gr as guias FROM encabezado_gr WHERE ruc_empresa = '".$ruc_empresa."' and serie_gr = '".$serie."' and secuencial_gr >= '".$inicial."' ";
						$result_todas = $con->query($gr_registradas);
					$solo_registradas = array();
						while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
						$solo_registradas[] = $todas_las_encontradas['guias'];
									}
						$gr_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
						if ($gr_faltantes == false){
						echo ($gr_final);					
						}else{
						echo min($gr_faltantes);
						}
		}	
					
	}
}

?>