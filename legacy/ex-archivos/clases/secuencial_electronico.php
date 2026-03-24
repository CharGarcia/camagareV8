<?php
	
class secuencial_electronico{
	public function consecutivo_siguiente($con, $ruc_empresa, $documento, $serie){
	switch ($documento) {
		case "factura":
			$tabla_consultada="encabezado_factura";
			$serie_consultada="serie_factura";
			$campo_documento_inicial="inicial_factura";
			$campo_secuencial="secuencial_factura";
			break;
		case "retencion":
			$tabla_consultada="encabezado_retencion";
			$serie_consultada="serie_retencion";
			$campo_documento_inicial="inicial_cr";
			$campo_secuencial="secuencial_retencion";
			break;
		case "guia_remision":
			$tabla_consultada="encabezado_gr";
			$serie_consultada="serie_gr";
			$campo_documento_inicial="inicial_gr";
			$campo_secuencial="secuencial_gr";
			break;
		case "liquidacion":
			$tabla_consultada="encabezado_liquidacion";
			$serie_consultada="serie_liquidacion";
			$campo_documento_inicial="inicial_liq";
			$campo_secuencial="secuencial_liquidacion";
			break;
		case "nota_credito":
			$tabla_consultada="encabezado_nc";
			$serie_consultada="serie_nc";
			$campo_documento_inicial="inicial_nc";
			$campo_secuencial="secuencial_nc";
			break;
		case "nota_debito":
			$tabla_consultada="encabezado_nd";
			$serie_consultada="serie_nd";
			$campo_documento_inicial="inicial_nd";
			$campo_secuencial="secuencial_nd";
			break;
		case "recibo_venta":
			$tabla_consultada="encabezado_recibo";
			$serie_consultada="serie_recibo";
			$campo_documento_inicial="";
			$campo_secuencial="secuencial_recibo";
			break;
			}

		$cuenta_documentos = mysqli_query($con,"SELECT * FROM $tabla_consultada WHERE ruc_empresa = '".$ruc_empresa."' and $serie_consultada = '".$serie."' ");
		$count = mysqli_num_rows($cuenta_documentos);

		//inicial segun se configura en el sistema camagare
		if($campo_documento_inicial !=""){
		$busca_documento_inicial = mysqli_query($con,"SELECT $campo_documento_inicial FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'");
		$inicial_documento = mysqli_fetch_row($busca_documento_inicial);
		$inicial = $inicial_documento['0'];
		}else{
			$inicial =1;
		}
		
		if ($count ==0){
			return ($inicial);
		}else{
			$busca_documento = mysqli_query($con,"SELECT MAX($campo_secuencial) as maximo FROM $tabla_consultada WHERE ruc_empresa = '".$ruc_empresa."' and $serie_consultada = '".$serie."' ");
			$row_busqueda = mysqli_fetch_assoc($busca_documento);
			$final = intval($row_busqueda['maximo'])+1;

					$serie_inicio_fin=array();
					foreach(range($inicial, $final) as $toda_la_serie ){
					$serie_inicio_fin[]= intval($toda_la_serie);
					}
					
					$documentos_registrados = mysqli_query($con,"SELECT $campo_secuencial as documentos FROM $tabla_consultada WHERE ruc_empresa = '".$ruc_empresa."' and $serie_consultada = '".$serie."' and $campo_secuencial >= '".$inicial."' ");
				
					$solo_registrados = array();
					while ($row_encontrados=mysqli_fetch_array($documentos_registrados)){
					$solo_registrados[] = intval($row_encontrados['documentos']);
					}
					
					$documentos_faltantes = array_diff($serie_inicio_fin,$solo_registrados);
					if ($documentos_faltantes == false){
					return ($final);					
					}else{
					return min($documentos_faltantes);
					}
		}
		
		mysqli_close($con);
			
	}
	
}

	

	
if (isset($_POST['serie_fe'])){
	if (empty($_POST['serie_fe'])) {
          $ultima_factura = "Seleccione serie.";
        }else if (!empty($_POST['serie_fe'])){
		$serie=mysqli_real_escape_string($con,(strip_tags($_POST["serie_fe"],ENT_QUOTES)));
		// hay que contar cuantos registros existen
		$cuenta_facturas = mysqli_query($con,"SELECT * FROM encabezado_factura WHERE ruc_empresa = '".$ruc_empresa."' and serie_factura = '".$serie."' and tipo_factura = 'ELECTRÓNICA'");
		$count = mysqli_num_rows($cuenta_facturas);

		//la factura inicial segun se configura en el sistema camagare
		$busca_factura_inicial = "SELECT inicial_factura FROM sucursales WHERE ruc_empresa = '".$ruc_empresa."' and serie = '".$serie."'";
			$result = $con->query($busca_factura_inicial);
			$inicial_factura = mysqli_fetch_row($result);
			$inicial = $inicial_factura['0'];
		
		if ($count ==0){
			echo ($inicial);
		}else{
			$busca_factura = "SELECT MIN(secuencial_factura) as minimo, MAX(secuencial_factura) as maximo FROM encabezado_factura WHERE ruc_empresa = '".$ruc_empresa."' and serie_factura = '".$serie."' and tipo_factura = 'ELECTRÓNICA'";
			$result = $con->query($busca_factura);
			$res_sql = mysqli_fetch_assoc($result);
			$factura_inicial = intval($inicial); //$res_sql['minimo'];
			$factura_final = intval($res_sql['maximo'])+1;

				$serie_inicio_fin=array();
					foreach(range($inicial, $factura_final) as $toda_la_serie ){
					$serie_inicio_fin[]= intval($toda_la_serie);
					}
					$facturas_registradas ="SELECT secuencial_factura as facturas FROM encabezado_factura WHERE ruc_empresa = '".$ruc_empresa."' and serie_factura = '".$serie."' and tipo_factura = 'ELECTRÓNICA' and secuencial_factura >= '".$inicial."' ";
					$result_todas = $con->query($facturas_registradas);
				
				$solo_registradas = array();
					while ($todas_las_encontradas=mysqli_fetch_array($result_todas)){
					$solo_registradas[] = intval($todas_las_encontradas['facturas']);
					}
					
					$facturas_faltantes = array_diff($serie_inicio_fin,$solo_registradas);
					if ($facturas_faltantes == false){
					echo ($factura_final);					
					}else{
					echo min($facturas_faltantes);
					}
		}	
					
	}
}

?>