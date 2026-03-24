<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
if (isset($_POST['serie_nc'])){
	if (empty($_POST['serie_nc'])) {
          $ultima_retencion = "Seleccione una sucursal.";
        }else if (!empty($_POST['serie_nc'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_nc"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_nc = mysqli_query($con,"SELECT * FROM encabezado_nc WHERE ruc_empresa = '$ruc_empresa' and serie_nc = '$serie' ");
		$count = mysqli_num_rows($cuenta_nc);
		if ($count ==0){
			$busca_nc_inicial = "SELECT inicial_nc FROM sucursales WHERE ruc_empresa = '$ruc_empresa' and serie = '$serie'";
			$result = $con->query($busca_nc_inicial);
			$inicial_nc = mysqli_fetch_row($result);
			$inicial = $inicial_nc['0'];
			echo ($inicial);
		}else{
				$busca_nc = "SELECT MIN(secuencial_nc) as minimo, MAX(secuencial_nc) as maximo FROM encabezado_nc WHERE ruc_empresa = '$ruc_empresa' and serie_nc = '$serie' ";
				$result = $con->query($busca_nc);
				$res_sql = mysqli_fetch_assoc($result);
				$nc_inicial = $res_sql['minimo'];
				$nc_final = $res_sql['maximo'];

				if($nc_inicial>$nc_final){
					$nc_final = $nc_inicial + 1;
				}
				
					$serie_inicio_fin=array();
						foreach(range($nc_inicial, $nc_final) as $toda_la_serie ){
						$serie_inicio_fin[]= $toda_la_serie;
						}
						$nc_registradas ="SELECT secuencial_nc as nc FROM encabezado_nc WHERE ruc_empresa = '$ruc_empresa' and serie_nc = '$serie' ";
						$result_todas = $con->query($nc_registradas);
					$solo_registradas = array();
						while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
						$solo_registradas[] = $todas_las_encontradas['nc'];
									}
						$nc_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
						if ($nc_faltantes == false){
						echo ($nc_final+1);					
						}else{
						echo min($nc_faltantes);
						}
		}	
					
	}
}

?>