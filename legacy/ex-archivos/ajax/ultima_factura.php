<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];

        $busca_factura = "SELECT MIN(secuencial_factura) as minimo, MAX(secuencial_factura) as maximo FROM encabezado_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '001-001' and tipo_factura = 'ELECTRÓNICA'";
	    $result = $con->query($busca_factura);
		$res_sql = mysqli_fetch_assoc($result);
		$factura_inicial = $res_sql['minimo'];
		$factura_final = $res_sql['maximo'];
		
		$numr=array();

		foreach(range($factura_inicial, $factura_final) as $numero ){
		$numr[]= $numero;
		}
		
		$sql_facturas_todas ="SELECT secuencial_factura FROM encabezado_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '001-001' and tipo_factura = 'ELECTRÓNICA'";
		$result_todas = $con->query($sql_facturas_todas);
		//$facturas_todas = mysqli_fetch_row($result_todas);
		
		$arreglo_factura = array();
		while ($todas=mysqli_fetch_array($result_todas)){
		$arreglo_factura[] = $todas['0'];
		
					}
		$dif_recibos = array_diff($numr,$arreglo_factura);
		var_dump( $dif_recibos);
?>