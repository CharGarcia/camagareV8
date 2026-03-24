<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'anexo_ats') {
	if (empty($_POST['mes'])) {
		$errors[] = "Seleccione mes.";
	} else if (!empty($_POST['mes'])) {
		$mes = mysqli_real_escape_string($con, (strip_tags($_POST["mes"], ENT_QUOTES)));
		$anio = mysqli_real_escape_string($con, (strip_tags($_POST["anio_periodo"], ENT_QUOTES)));
		$semestre = mysqli_real_escape_string($con, (strip_tags($_POST["microempresa"], ENT_QUOTES)));

		$informante = informante($ruc_empresa, $mes, $anio, $con, $semestre);
		$compras = compras($ruc_empresa, $mes, $anio, $con, $semestre);

		$string = '<?xml version="1.0" encoding="UTF-8" standalone="no"?><iva>' . $informante . $compras . '</iva>';

		$nombre = "AT-" . $mes . $anio . ".xml";
		$file = fopen("../xml/" . $nombre, "w") or die("Problemas en la creacion"); //En esta linea lo que hace PHP es crear el archivo, si ya existe lo sobreescribe
		fwrite($file, $string);
		fclose($file);
		$dir = '../xml/';
		//Si no existe la carpeta la creamos
		if (!file_exists($dir))
			mkdir($dir);
		//Declaramos la ruta y nombre del archivo a generar
		$archivo_xml = $dir . $nombre;
?>
		<!-- <div class="col-md-6 col-md-offset-4">
			<div class="panel-heading">
				<h4> Fecha de actualización del ATS: 5 de febrero 2025</h4>
				<h4><a class="list-group-item list-group-item-success text-center" href="<?php echo $archivo_xml ?>" download><span class="glyphicon glyphicon-download-alt"></span> Descargar xml</a></h4>
			</div>
		</div> -->
		<div class="col-md-6 col-md-offset-4">
    <div class="panel-heading">
      
        <!-- Botón para descargar XML -->
        <h4>
            <a class="list-group-item list-group-item-success text-center"
               href="<?php echo $archivo_xml ?>" download>
                <span class="glyphicon glyphicon-download-alt"></span> Descargar XML
            </a>
        </h4>

        <!-- Botón para descargar Excel -->
        <h4>
            <a class="list-group-item list-group-item-info text-center"
				href="../excel/export_ats_excel.php" target="_blank">
				<span class="glyphicon glyphicon-download-alt"></span> Descargar detalle del ATS en Excel
			</a>
        </h4>
    </div>
</div>

	<?php
	} else {
		$errors[] = "Error desconocido.";
	}
}

function informante($ruc_empresa, $mes, $anio, $con, $semestre)
{
	$datos_empresa = mysqli_query($con, "SELECT * from empresas WHERE mid(ruc,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$row_empresas = mysqli_fetch_array($datos_empresa);
	$razon_social = strtoupper(clear_cadena(strClean($row_empresas['nombre'])));
	$datos_sucursales = mysqli_query($con, "SELECT * from sucursales WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$total_sucursales = str_pad(mysqli_num_rows($datos_sucursales), 3, "000", STR_PAD_LEFT);
	if ($semestre == "1") {
		$leyenda_microempresa = "<regimenMicroempresa>SI</regimenMicroempresa>";
	} else {
		$leyenda_microempresa = "";
	}

	$encabezado = '<TipoIDInformante>R</TipoIDInformante><IdInformante>' . substr($ruc_empresa, 0, 10) . '001</IdInformante>
<razonSocial>' . $razon_social . '</razonSocial><Anio>' . $anio . '</Anio><Mes>' . $mes . '</Mes>' . $leyenda_microempresa . '<numEstabRuc>' . $total_sucursales . '</numEstabRuc>
<totalVentas>0.00</totalVentas><codigoOperativo>IVA</codigoOperativo>';
	return $encabezado;
}


function compras($ruc_empresa, $mes, $anio, $con, $semestre)
{
	if ($semestre == "1") {
		if ($mes == "06") {
			$desde = $anio . "/01/01";
			$hasta = $anio . "/06/30";
		}
		if ($mes == "12") {
			$desde = $anio . "/07/01";
			$hasta = $anio . "/12/31";
		}
		$condicion_microempresa = "and enc_com.fecha_compra between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "'";
	} else {
		$condicion_microempresa = "and month(enc_com.fecha_compra)='" . $mes . "' and year(enc_com.fecha_compra)='" . $anio . "'";
	}
	$detalle_compras = array();
	$datos_compras = mysqli_query($con, "SELECT enc_com.id_encabezado_compra as id_compras, sus_tri.codigo_sustento as codigo_sustento, pro.tipo_id_proveedor as tipo_id_proveedor, pro.ruc_proveedor as ruc_proveedor,
	enc_com.id_proveedor as id_proveedor, com_aut.codigo_comprobante as codigo_comprobante, pro.relacionado as parte_relacionada, enc_com.fecha_compra as fecha_compra, enc_com.numero_documento as numero_documento, 
	enc_com.aut_sri as aut_sri, enc_com.codigo_documento as codigo_documento, enc_com.cod_doc_mod as docModificado, enc_com.factura_aplica_nc_nd as factura_aplica_nc_nd,
	 pro.tipo_empresa as tipo_empresa, pro.razon_social as razon_social 
	 FROM encabezado_compra as enc_com 
	 INNER JOIN sustento_tributario as sus_tri ON sus_tri.id_sustento=enc_com.id_sustento 
	INNER JOIN proveedores as pro ON pro.id_proveedor=enc_com.id_proveedor
	INNER JOIN comprobantes_autorizados as com_aut ON com_aut.id_comprobante=enc_com.id_comprobante 
	WHERE mid(enc_com.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' $condicion_microempresa "); //and enc_com.aut_sri='2011202401170898459400120010010000192142024124411'


	$cuenta_compras = mysqli_num_rows($datos_compras);
	if ($cuenta_compras > 0) {

		while ($row_compras = mysqli_fetch_array($datos_compras)) {
			$codigo_documento = $row_compras['codigo_documento'];
			$codSustento = $row_compras['codigo_sustento'];
			$idProv = $row_compras['ruc_proveedor'];
			$id_proveedor = $row_compras['id_proveedor'];
			$tipo_id_proveedor = $row_compras['tipo_id_proveedor'];
			$tipo_empresa = $row_compras['tipo_empresa'];
			$razon_social = strtoupper(clear_cadena($row_compras['razon_social']));
			$id_compras = $row_compras['id_compras'];

			switch ($tipo_id_proveedor) {
				case "04":
					$tpIdProv = "01";
					break;
				case "05":
					$tpIdProv = "02";
					break;
				case "06":
					$tpIdProv = "03";
					break;
				case "08":
					$tpIdProv = "03";
					break;
			}
			$tipoComprobante = trim($row_compras['codigo_comprobante']);
			if ($tipoComprobante === "04" || $tipoComprobante === "05") {
				$docModificado = $row_compras['docModificado'];
				$estabModificado = substr($row_compras['factura_aplica_nc_nd'], 0, 3);
				$ptoEmiModificado = substr($row_compras['factura_aplica_nc_nd'], 4, 3);
				$secModificado = str_pad(substr($row_compras['factura_aplica_nc_nd'], 8, 9), 9, "000000000", STR_PAD_LEFT);
				$dato_modificado = mysqli_query($con, "SELECT * from encabezado_compra WHERE numero_documento = '" . $row_compras['factura_aplica_nc_nd'] . "'");
				$row_modificado = mysqli_fetch_array($dato_modificado);
				$autModificado = ($row_modificado['aut_sri']) == "" ? "000" : $row_modificado['aut_sri'];
				$documento_modificado = '
	<docModificado>' . $docModificado . '</docModificado>
	<estabModificado>' . $estabModificado . '</estabModificado>
	<ptoEmiModificado>' . $ptoEmiModificado . '</ptoEmiModificado>
	<secModificado>' . $secModificado . '</secModificado>
	<autModificado>' . $autModificado . '</autModificado>';
			} else {
				$documento_modificado = "";
			}

			$parteRel = ($row_compras['parte_relacionada'] == "2") ? "SI" : "NO";
			$fechaRegistro = date('d/m/Y', strtotime($row_compras['fecha_compra']));
			$establecimiento = substr($row_compras['numero_documento'], 0, 3);
			$puntoEmision = substr($row_compras['numero_documento'], 4, 3);
			$secuencial = str_pad(substr($row_compras['numero_documento'], 8, 9), 9, "000000000", STR_PAD_LEFT);
			$autorizacion = $row_compras['aut_sri'];
			$codigo_documento = $row_compras['codigo_documento'];
			$numero_documento_retenido = $row_compras['numero_documento'];

			$dato_baseNoGraIva = mysqli_query($con, "SELECT round(sum(subtotal),2) as baseNoGraIva from cuerpo_compra 
			WHERE impuesto = '2' and det_impuesto = '6' and codigo_documento = '" . $codigo_documento . "'");
			$row_baseNoGraIva = mysqli_fetch_array($dato_baseNoGraIva);
			$baseNoGraIva = number_format($row_baseNoGraIva['baseNoGraIva'], 2, '.', '');

			$dato_baseice = mysqli_query($con, "SELECT round(sum(subtotal),2) as baseIce from cuerpo_compra 
			WHERE impuesto = '3' and codigo_documento = '" . $codigo_documento . "'");
			$row_baseIce = mysqli_fetch_array($dato_baseice);
			$baseIce = number_format($row_baseIce['baseIce'], 2, '.', '');

			$dato_baseImponible = mysqli_query($con, "SELECT round(sum(subtotal),2) as baseImponible from cuerpo_compra 
			WHERE impuesto = '2' and det_impuesto = '0' and codigo_documento = '" . $codigo_documento . "'");
			$row_baseImponible = mysqli_fetch_array($dato_baseImponible);
			$baseImponible = number_format($row_baseImponible['baseImponible'], 2, '.', '');

			//para base 12
			$dato_baseImpGrav12 = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '2' 
			and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGrav12 = mysqli_fetch_array($dato_baseImpGrav12);
			$baseImpGrav12 = number_format($row_baseImpGrav12['subtotal'], 2, '.', '');
			$montoIva12 = number_format($row_baseImpGrav12['monto_iva'], 2, '.', '');

			//para base 14
			$dato_baseImpGrav14 = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
			round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '3' and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGrav14 = mysqli_fetch_array($dato_baseImpGrav14);
			$baseImpGrav14 = number_format($row_baseImpGrav14['subtotal'], 2, '.', '');
			$montoIva14 = number_format($row_baseImpGrav14['monto_iva'], 2, '.', '');

			//para base 15
			$dato_baseImpGrav15 = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '4' and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGrav15 = mysqli_fetch_array($dato_baseImpGrav15);
			$baseImpGrav15 = number_format($row_baseImpGrav15['subtotal'], 2, '.', '');
			$montoIva15 = number_format($row_baseImpGrav15['monto_iva'], 2, '.', '');

			//para base 5
			$dato_baseImpGrav5 = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
			round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '5' and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGrav5 = mysqli_fetch_array($dato_baseImpGrav5);
			$baseImpGrav5 = number_format($row_baseImpGrav5['subtotal'], 2, '.', '');
			$montoIva5 = number_format($row_baseImpGrav5['monto_iva'], 2, '.', '');

			//para base 13
			$dato_baseImpGrav13 = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
			round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '10' and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGrav13 = mysqli_fetch_array($dato_baseImpGrav13);
			$baseImpGrav13 = number_format($row_baseImpGrav13['subtotal'], 2, '.', '');
			$montoIva13 = number_format($row_baseImpGrav13['monto_iva'], 2, '.', '');

			//para base diferenciado
			$dato_baseImpGravDif = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
			round(sum((cue.subtotal * tar.porcentaje_iva) /100),2) as monto_iva 
			FROM cuerpo_compra as cue INNER JOIN tarifa_iva as tar 
			ON tar.codigo=cue.det_impuesto WHERE cue.impuesto = '2' and cue.det_impuesto = '8' and cue.codigo_documento = '" . $codigo_documento . "' ");
			$row_baseImpGravDif = mysqli_fetch_array($dato_baseImpGravDif);
			$baseImpGravDif = number_format($row_baseImpGravDif['subtotal'], 2, '.', '');
			$montoIvaDif = number_format($row_baseImpGravDif['monto_iva'], 2, '.', '');

			$baseImpGrav = number_format($baseImpGrav15 + $baseImpGrav12 + $baseImpGrav14 + $baseImpGrav5 + $baseImpGrav13 + $baseImpGravDif, 2, '.', '');
			$montoIva = number_format($montoIva15 + $montoIva12 + $montoIva14 + $montoIva5 + $montoIva13 + $montoIvaDif, 2, '.', '');

			$dato_baseImpExe = mysqli_query($con, "SELECT sum(subtotal) as baseImpExe from cuerpo_compra WHERE impuesto = '2' and det_impuesto = '7' and codigo_documento = '" . $codigo_documento . "'");
			$row_baseImpExe = mysqli_fetch_array($dato_baseImpExe);
			$baseImpExe = number_format($row_baseImpExe['baseImpExe'], 2, '.', '');

			$dato_ret_iva10 = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret10 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' 
			and enc_ret.id_proveedor='" . $id_proveedor . "' and enc_ret.tipo_comprobante='" . $tipoComprobante . "' 
			and cue_ret.impuesto = 'IVA' and cue_ret.porcentaje_retencion='10' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_ret_iva10 = mysqli_fetch_array($dato_ret_iva10);
			$valRetBien10 = number_format($row_ret_iva10['total_ret10'], 2, '.', '');

			$dato_valRetServ20 = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret20 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' 
			and enc_ret.id_proveedor='" . $id_proveedor . "' and enc_ret.tipo_comprobante='" . $tipoComprobante . "' 
			and cue_ret.impuesto = 'IVA' and cue_ret.porcentaje_retencion='20' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_valRetServ20 = mysqli_fetch_array($dato_valRetServ20);
			$valRetServ20 = number_format($row_valRetServ20['total_ret20'], 2, '.', '');

			$dato_valorRetBienes = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret30 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' and enc_ret.id_proveedor='" . $id_proveedor . "' 
			and enc_ret.tipo_comprobante='" . $tipoComprobante . "' and cue_ret.impuesto = 'IVA' 
			and cue_ret.porcentaje_retencion='30' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_valorRetBienes = mysqli_fetch_array($dato_valorRetBienes);
			$valorRetBienes = number_format($row_valorRetBienes['total_ret30'], 2, '.', '');

			$dato_valRetServ50 = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret50 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' and enc_ret.id_proveedor='" . $id_proveedor . "' 
			and enc_ret.tipo_comprobante='" . $tipoComprobante . "' and cue_ret.impuesto = 'IVA' 
			and cue_ret.porcentaje_retencion='50' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_valRetServ50 = mysqli_fetch_array($dato_valRetServ50);
			$valRetServ50 = number_format($row_valRetServ50['total_ret50'], 2, '.', '');

			$dato_valorRetServicios = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret70 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' and enc_ret.id_proveedor='" . $id_proveedor . "' 
			and enc_ret.tipo_comprobante='" . $tipoComprobante . "' and cue_ret.impuesto = 'IVA' 
			and cue_ret.porcentaje_retencion='70' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_valorRetServicios = mysqli_fetch_array($dato_valorRetServicios);
			$valorRetServicios = number_format($row_valorRetServicios['total_ret70'], 2, '.', '');

			$dato_valRetServ100 = mysqli_query($con, "SELECT sum(valor_retenido) as total_ret100 
			from encabezado_retencion as enc_ret 
			INNER JOIN cuerpo_retencion as cue_ret 
			ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
			WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
			and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' and enc_ret.id_proveedor='" . $id_proveedor . "' 
			and enc_ret.tipo_comprobante='" . $tipoComprobante . "' and cue_ret.impuesto = 'IVA' 
			and cue_ret.porcentaje_retencion='100' and enc_ret.id_compras = '" . $id_compras . "'");
			$row_valRetServ100 = mysqli_fetch_array($dato_valRetServ100);
			$valRetServ100 = number_format($row_valRetServ100['total_ret100'], 2, '.', '');

			//para ver si hay retenciones	
			$suma_retenciones = mysqli_query($con, "SELECT round(sum(valor_retenido),2) as total_ret 
	FROM encabezado_retencion as enc_ret 
	INNER JOIN cuerpo_retencion as cue_ret ON 
	enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
	WHERE enc_ret.estado_sri='AUTORIZADO' and enc_ret.ruc_empresa='" . $ruc_empresa . "' 
	and cue_ret.ruc_empresa='" . $ruc_empresa . "' and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' 
	and enc_ret.tipo_comprobante='" . $tipoComprobante . "' and enc_ret.id_proveedor='" . $id_proveedor . "' 
	and cue_ret.impuesto = 'RENTA' and enc_ret.id_compras = '" . $id_compras . "' group by cue_ret.impuesto ");
			$row_sum_retenciones = mysqli_fetch_array($suma_retenciones);
			$total_retenciones = isset($row_sum_retenciones['total_ret']) ? number_format($row_sum_retenciones['total_ret'], 2, '.', '') : "null";

			if ($total_retenciones != 'null') {
				$info_retencion = "";
				$dato_retenciones = mysqli_query($con, "SELECT * from encabezado_retencion WHERE ruc_empresa='" . $ruc_empresa . "' 
	and estado_sri='AUTORIZADO' and numero_comprobante ='" . $numero_documento_retenido . "' 
	and id_proveedor='" . $id_proveedor . "' and id_compras = '" . $id_compras . "'");
				while ($row_retenciones = mysqli_fetch_array($dato_retenciones)) {
					$serie_ret = $row_retenciones['serie_retencion'];
					$secuencial_ret = $row_retenciones['secuencial_retencion'];
					$estabRetencion1 = substr($row_retenciones['serie_retencion'], 0, 3);
					$ptoEmiRetencion1 = substr($row_retenciones['serie_retencion'], 4, 3);
					$secRetencion1 = str_pad($row_retenciones['secuencial_retencion'], 9, "000000000", STR_PAD_LEFT);
					$autRetencion1 = strlen($row_retenciones['aut_sri']) != "" ? $row_retenciones['aut_sri'] : "1234567890";
					$fechaEmiRet1 = date('d/m/Y', strtotime($row_retenciones['fecha_emision']));

					$detalleAir = "";
					$dato_detalle_retenciones = mysqli_query($con, "SELECT * from cuerpo_retencion 
				WHERE ruc_empresa='" . $ruc_empresa . "' and serie_retencion='" . $serie_ret . "' 
				and secuencial_retencion='" . $secuencial_ret . "' and impuesto = 'RENTA' ");
					while ($row_detalle_retenciones = mysqli_fetch_array($dato_detalle_retenciones)) {
						$codRetAir = $row_detalle_retenciones['codigo_impuesto'];
						$baseImpAir = number_format($row_detalle_retenciones['base_imponible'], 2, '.', '');
						$porcentajeAir = number_format($row_detalle_retenciones['porcentaje_retencion'], 2, '.', '');
						$valRetAir = number_format($row_detalle_retenciones['valor_retenido'], 2, '.', '');
						$detalleAir .= "<detalleAir><codRetAir>" . $codRetAir . "</codRetAir>
				<baseImpAir>" . $baseImpAir . "</baseImpAir><porcentajeAir>" . $porcentajeAir . "</porcentajeAir>
				<valRetAir>" . $valRetAir . "</valRetAir></detalleAir>";
					}
					$detalleAir = $detalleAir;
					$info_retencion .= "<estabRetencion1>" . $estabRetencion1 . "</estabRetencion1>
			<ptoEmiRetencion1>" . $ptoEmiRetencion1 . "</ptoEmiRetencion1>
			<secRetencion1>" . $secRetencion1 . "</secRetencion1>
			<autRetencion1>" . $autRetencion1 . "</autRetencion1>
			<fechaEmiRet1>" . $fechaEmiRet1 . "</fechaEmiRet1>";
				}
				$air = "<air>" . $detalleAir . "</air>" . $info_retencion;
			} else {
				$suma_retenciones = mysqli_query($con, "SELECT round(sum(cue_ret.base_imponible),2) as subtotal_retencion 
		FROM encabezado_retencion as enc_ret 
		INNER JOIN cuerpo_retencion as cue_ret 
		ON enc_ret.serie_retencion=cue_ret.serie_retencion and enc_ret.secuencial_retencion=cue_ret.secuencial_retencion 
		WHERE enc_ret.ruc_empresa='" . $ruc_empresa . "' and cue_ret.ruc_empresa='" . $ruc_empresa . "' 
		and enc_ret.estado_sri='AUTORIZADO' and enc_ret.numero_comprobante ='" . $numero_documento_retenido . "' 
		and enc_ret.id_proveedor='" . $id_proveedor . "' and cue_ret.impuesto = 'RENTA' 
		and enc_ret.id_compras = '" . $id_compras . "' ");
				$row_retenciones = mysqli_fetch_array($suma_retenciones);
				$base_retenciones = isset($row_retenciones['subtotal_retencion']) ? $row_retenciones['subtotal_retencion'] : 0;

				$subtotal_no_ret = mysqli_query($con, "SELECT round(sum(subtotal),2) as subtotal FROM cuerpo_compra WHERE codigo_documento = '" . $codigo_documento . "'");
				$row_subtotal = mysqli_fetch_array($subtotal_no_ret);
				$subtotal = isset($row_subtotal['subtotal']) ? $row_subtotal['subtotal'] : 0;
				$suma_total_bases = number_format($subtotal - $base_retenciones, 2, '.', '');

				if ($suma_total_bases > 0) {
					$detalleAir = "<air><detalleAir><codRetAir>332</codRetAir>
		<baseImpAir>" . $suma_total_bases . "</baseImpAir><porcentajeAir>0</porcentajeAir>
		<valRetAir>0.00</valRetAir></detalleAir></air>";
				} else {
					$detalleAir = "";
				}

				if ($tipoComprobante == "04") {
					$air = "";
				} else {
					$air = $detalleAir;
				}
			}

			//if ($tipoComprobante == "04" || $tipoComprobante == "05") {
			if ($tipoComprobante == "04") {
				$formaPago = "";
			} else {

				if (date('Y', strtotime($row_compras['fecha_compra'])) >= 2024) {
					$monto_aplica_forma_pago = 500;
				} else {
					$monto_aplica_forma_pago = 1000;
				}
				//para ver si el valor de de pago es mayor a 1000 o 500
				$dato_total_pago = mysqli_query($con, "SELECT round(sum(total_pago),2) as total_pago from formas_pago_compras WHERE codigo_documento='" . $codigo_documento . "' ");
				$row_total_pago = mysqli_fetch_array($dato_total_pago);
				$total_pago = number_format($row_total_pago['total_pago'], 2, '.', '');

				if ($total_pago > $monto_aplica_forma_pago) {
					$pagos = "";
					$dato_formasDePago = mysqli_query($con, "SELECT * from formas_pago_compras WHERE codigo_documento='" . $codigo_documento . "' ");
					while ($row_formasDePago = mysqli_fetch_array($dato_formasDePago)) {
						$pagos .= "<formaPago>" . $row_formasDePago['forma_pago'] . "</formaPago>";
					}
					$formaPago = "<formasDePago>" . $pagos . "</formasDePago>";
				} else {
					$formaPago = "";
				}
			}

			if ($tpIdProv == "03" &&  $tipoComprobante == "03") {
				$pasaporte_en_liq_com = "<tipoProv>" . $tipo_empresa . "</tipoProv><denoProv>" . $razon_social . "</denoProv>";
			} else {
				$pasaporte_en_liq_com = "";
			}

			if ($tipoComprobante === "05") {
				$detalle_ret_fp_dm = $formaPago . $air . $documento_modificado;
			} else {
				$detalle_ret_fp_dm = $documento_modificado . $formaPago . $air;
			}

			$detalle_compras[] = '	
<detalleCompras>
<codSustento>' . $codSustento . '</codSustento>
<tpIdProv>' . $tpIdProv . '</tpIdProv>
<idProv>' . $idProv . '</idProv>
<tipoComprobante>' . $tipoComprobante . '</tipoComprobante>' . $pasaporte_en_liq_com . '
<parteRel>' . $parteRel . '</parteRel>
<fechaRegistro>' . $fechaRegistro . '</fechaRegistro>
<establecimiento>' . $establecimiento . '</establecimiento>
<puntoEmision>' . $puntoEmision . '</puntoEmision>
<secuencial>' . $secuencial . '</secuencial>
<fechaEmision>' . $fechaRegistro . '</fechaEmision>
<autorizacion>' . $autorizacion . '</autorizacion>
<baseNoGraIva>' . $baseNoGraIva . '</baseNoGraIva>
<baseImponible>' . $baseImponible . '</baseImponible>
<baseImpGrav>' . $baseImpGrav . '</baseImpGrav>
<baseImpExe>' . $baseImpExe . '</baseImpExe>
<montoIce>' . $baseIce . '</montoIce>
<montoIva>' . $montoIva . '</montoIva>
<valRetBien10>' . $valRetBien10 . '</valRetBien10>
<valRetServ20>' . $valRetServ20 . '</valRetServ20>
<valorRetBienes>' . $valorRetBienes . '</valorRetBienes>
<valRetServ50>' . $valRetServ50 . '</valRetServ50>
<valorRetServicios>' . $valorRetServicios . '</valorRetServicios>
<valRetServ100>' . $valRetServ100 . '</valRetServ100>
<totbasesImpReemb>0.00</totbasesImpReemb>
<pagoExterior>
<pagoLocExt>01</pagoLocExt>
<paisEfecPago>NA</paisEfecPago>
<aplicConvDobTrib>NA</aplicConvDobTrib>
<pagExtSujRetNorLeg>NA</pagExtSujRetNorLeg>
</pagoExterior>
' . $detalle_ret_fp_dm . '
</detalleCompras>';
		}
		$detalle_final = "";
		foreach ($detalle_compras as $detalle) {
			$detalle_final .= $detalle;
		}
		return "<compras>" . $detalle_final . "</compras>";
	} else {
		return "";
	}
}


if (isset($errors)) {

	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}

?>