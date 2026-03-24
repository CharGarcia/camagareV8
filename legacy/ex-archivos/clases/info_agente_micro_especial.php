<?php
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if (($action == 'info_agente_micro_especial') && isset($_POST['ruc_proveedor']) && (!empty($_POST['ruc_proveedor']))) {

	$url_almacenados = "http://137.184.159.242:4000/api/sri-identification";
	$data_almacenados = array_map('strval', array(
		"identification" => $_POST['ruc_proveedor']
	));

	$ch = curl_init($url_almacenados);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_almacenados));
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		echo 'Error en la petición: ' . curl_error($ch);
	} else {
		// Decodificar la respuesta JSON
		$responseData = json_decode($response, true);

		//cuando es ruc
	}
	//header('Content-Type: application/json');
	//echo json_encode($datos_ruc, JSON_UNESCAPED_UNICODE);

	//die();
?>
	<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong> Información del contribuyente <?php print_r($responseData['data']['datosContribuyente'][0]['razonSocial']) ?><br></strong>
		<strong> Estado: <?php print_r($responseData['data']['datosContribuyente'][0]['estadoContribuyenteRuc']) ?> Regimen: <?php print_r($responseData['data']['datosContribuyente'][0]['regimen']) ?> Tipo: <?php print_r($responseData['data']['datosContribuyente'][0]['tipoContribuyente']) ?><br></strong>
		<strong> Agente de retención: <?php print_r($responseData['data']['datosContribuyente'][0]['agenteRetencion']) ?> Contribuyente especial: <?php print_r($responseData['data']['datosContribuyente'][0]['contribuyenteEspecial']) ?><br></strong>
	</div>
<?php
}




/* $ruc_a_buscar = $_POST['ruc_proveedor'];
	$carpeta_agente = file_get_contents('../agente_micro_especial/agente_retencion.txt');
	$resultado_agente = strpos($carpeta_agente, $ruc_a_buscar);

	$carpeta_contribuyente_especial = file_get_contents('../agente_micro_especial/contribuyente_especial.txt');
	$resultado_contriguyente_especial = strpos($carpeta_contribuyente_especial, $ruc_a_buscar);

	$carpeta_rimpe = file_get_contents('../agente_micro_especial/rimpe.txt');
	$resultado_rimpe = strpos($carpeta_rimpe, $ruc_a_buscar);

	$carpeta_negocio_popular = file_get_contents('../agente_micro_especial/negocio_popular.txt');
	$resultado_negocio_popular = strpos($carpeta_negocio_popular, $ruc_a_buscar);

	$carpeta_grandes_contribuyentes = file_get_contents('../agente_micro_especial/grandes_contribuyentes.txt');
	$resultado_grandes_contribuyentes = strpos($carpeta_grandes_contribuyentes, $ruc_a_buscar); */

/* if ($resultado_contriguyente_especial) {
?>
		<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong> El proveedor es contribuyente especial </strong>
		</div>
	<?php
	}

	if ($resultado_agente) {
	?>
		<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong> El proveedor es Agente de Retención</strong>
		</div>
	<?php
	}

	if ($resultado_rimpe) {
	?>
		<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong> El proveedor es contribuyente régimen RIMPE (cód ret 343)</strong>
		</div>
	<?php
	}

	if ($resultado_negocio_popular) {
	?>
		<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong> El proveedor es contribuyente RIMPE NEGOCIO POPULAR (cód ret 332)</strong>
		</div>
	<?php
	}

	if ($resultado_grandes_contribuyentes) {
	?>
		<div class="alert alert-warning" role="alert" style="padding: 4px; margin-bottom: 5px; margin-top: -5px;">
			<button type="button" class="close" data-dismiss="alert">&times;</button>
			<strong> El proveedor es un gran contribuyente. No se debe retener RENTA</strong>
		</div>
<?php
	} */
