<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'declaracion_iva') {
	$datos_declaracion = mysqli_real_escape_string($con, (strip_tags($_POST["dato_declaracion"], ENT_QUOTES)));
	$mes_semestre = mysqli_real_escape_string($con, (strip_tags($_POST["mes_semestre"], ENT_QUOTES)));
	$anio = mysqli_real_escape_string($con, (strip_tags($_POST["anio_periodo"], ENT_QUOTES)));

	if (empty($_POST['mes_semestre'])) {
		$errors[] = "Seleccione un mes o semestre.";
	} else if (empty($_POST['anio_periodo'])) {
		$errors[] = "Seleccione año.";
	} else if (!empty($_POST['mes_semestre']) && !empty($_POST['anio_periodo'])) {

		//ventas
		$declaracion_iva = new declaracion_iva();
		$ventas_diferentes_cero = $declaracion_iva->venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['subtotal'];
		$ventas_tarifa_cinco = $declaracion_iva->venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['subtotal'];
		$iva_ventas_diferentes_cero = $declaracion_iva->venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['iva'];
		$iva_ventas_tarifa_cinco = $declaracion_iva->venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['iva'];
		$nc_ventas_diferentes_cero = $declaracion_iva->nc_venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['subtotal'];
		$nc_ventas_tarifa_cinco = $declaracion_iva->nc_venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['subtotal'];
		$iva_nc_ventas_diferentes_cero = $declaracion_iva->nc_venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['iva'];
		$iva_nc_ventas_tarifa_cinco = $declaracion_iva->nc_venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)['iva'];

		$casillero_401 = number_format($ventas_diferentes_cero - $ventas_tarifa_cinco, 2, '.', '');
		$casillero_411 = number_format($ventas_diferentes_cero - $ventas_tarifa_cinco - $nc_ventas_diferentes_cero - $nc_ventas_tarifa_cinco, 2, '.', '');
		$casillero_421 = number_format($iva_ventas_diferentes_cero - $iva_ventas_tarifa_cinco - $iva_nc_ventas_diferentes_cero - $iva_nc_ventas_tarifa_cinco, 2, '.', '');

		$casillero_425 = number_format($ventas_tarifa_cinco, 2, '.', '');
		$casillero_435 = number_format($ventas_tarifa_cinco - $nc_ventas_tarifa_cinco, 2, '.', '');
		$casillero_445 = number_format($iva_ventas_tarifa_cinco - $iva_nc_ventas_tarifa_cinco, 2, '.', '');

		$total_ventas_tarifa_cero = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_ventas', '0');
		$total_nc_tarifa_cero = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_ventas', '0');

		$casillero_405 = number_format($total_ventas_tarifa_cero, 2, '.', '');
		$casillero_415 = number_format($total_ventas_tarifa_cero - $total_nc_tarifa_cero, 2, '.', '');

		$total_ventas_tarifa_noobjeto = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_ventas', '6');
		$total_nc_tarifa_noobjeto = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_ventas', '6');
		$total_ventas_tarifa_exento = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_ventas', '7');
		$total_nc_tarifa_exento = $declaracion_iva->otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_ventas', '7');

		$casillero_431 = number_format($total_ventas_tarifa_noobjeto + $total_ventas_tarifa_exento, 2, '.', '');
		$casillero_441 = number_format($total_ventas_tarifa_noobjeto  + $total_ventas_tarifa_exento - $total_nc_tarifa_noobjeto - $total_nc_tarifa_exento, 2, '.', '');

		$casillero_409 = number_format($casillero_401 + $casillero_425 + $casillero_405, 2, '.', '');
		$casillero_419 = number_format($casillero_411 + $casillero_435 + $casillero_415, 2, '.', '');
		$casillero_429 = number_format($casillero_421 + $casillero_445, 2, '.', '');

		$casillero_480 = $casillero_411;
		$casillero_482 = $casillero_429;
		$casillero_484 = $casillero_429;
		$casillero_499 = $casillero_429;

		//total de numero de documentos de ventas
		$total_facturas_emitidas = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_ventas', 'AUTORIZADO');
		$total_facturas_anuladas = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_ventas', 'ANULADA');

		$casillero_111 = $total_facturas_emitidas;
		$casillero_113 = $total_facturas_anuladas;
		//compras
		$total_facturas_compras_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '1')['subtotal']; // 1 es facturas de compras
		$total_facturas_compras_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '1')['subtotal']; // 1 es facturas de compras
		$total_nc_compras_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '4')['subtotal']; // 4 es nc de compras
		$total_nc_compras_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '4')['subtotal']; // 4 es nc de compras
		$iva_compras_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '1')['iva']; // 1 es facturas de compras
		$iva_compras_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '1')['iva']; // 1 es facturas de compras
		$iva_nc_compras_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '4')['iva']; // 4 es nc de compras
		$iva_nc_compras_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '4')['iva']; // 4 es nc de compras
		$total_liquidacion_compras_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '3')['subtotal']; // 3 es liq de compras
		$total_liquidacion_compras_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '3')['subtotal']; // 3 es liq de compras
		$iva_lc_diferente_cero = $declaracion_iva->compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '3')['iva']; // 1 es liq de compras
		$iva_lc_tarifa_cinco = $declaracion_iva->compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, '3')['iva']; // 1 es liq de compras

		$casillero_500 = number_format($total_facturas_compras_diferente_cero + $total_liquidacion_compras_diferente_cero - $total_liquidacion_compras_tarifa_cinco - $total_facturas_compras_tarifa_cinco, 2, '.', '');
		$casillero_510 = number_format($total_facturas_compras_diferente_cero + $total_liquidacion_compras_diferente_cero - $total_liquidacion_compras_tarifa_cinco - $total_facturas_compras_tarifa_cinco - $total_nc_compras_diferente_cero - $total_nc_compras_tarifa_cinco, 2, '.', '');
		$casillero_520 = number_format($iva_compras_diferente_cero + $iva_lc_diferente_cero - $iva_lc_tarifa_cinco - $iva_compras_tarifa_cinco - $iva_nc_compras_diferente_cero - $iva_nc_compras_tarifa_cinco, 2, '.', '');

		$casillero_540 = number_format($total_facturas_compras_tarifa_cinco + $total_liquidacion_compras_tarifa_cinco, 2, '.', '');
		$casillero_550 = number_format($total_facturas_compras_tarifa_cinco + $total_liquidacion_compras_tarifa_cinco - $total_nc_compras_tarifa_cinco, 2, '.', '');
		$casillero_560 = number_format($iva_compras_tarifa_cinco + $iva_lc_tarifa_cinco - $iva_nc_compras_tarifa_cinco, 2, '.', '');

		$total_compras_tarifa_cero = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_compras', '2', '0');
		$total_nc_compras_tarifa_cero = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_compras', '2', '0');

		$casillero_507 = number_format($total_compras_tarifa_cero, 2, '.', '');
		$casillero_517 = number_format($total_compras_tarifa_cero - $total_nc_compras_tarifa_cero, 2, '.', '');

		$total_compras_rise_tarifa_doce = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_de_venta', '2', '2');
		$total_compras_rise_tarifa_cero = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_de_venta', '2', '0');
		$total_compras_rise_tarifa_noobjeto = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_de_venta', '2', '6');
		$total_compras_rise_tarifa_exento = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_de_venta', '2', '7');

		$total_nc_rise_tarifa_doce = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nc_notas_de_venta', '2', '2');
		$total_nc_rise_tarifa_cero = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nc_notas_de_venta', '2', '0');
		$total_nc_rise_tarifa_noobjeto = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nc_notas_de_venta', '2', '6');
		$total_nc_rise_tarifa_exento = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nc_notas_de_venta', '2', '7');

		$casillero_508 = number_format($total_compras_rise_tarifa_doce + $total_compras_rise_tarifa_cero + $total_compras_rise_tarifa_noobjeto + $total_compras_rise_tarifa_exento, 2, '.', '');
		$casillero_518 = number_format($casillero_508 - $total_nc_rise_tarifa_doce - $total_nc_rise_tarifa_cero - $total_nc_rise_tarifa_noobjeto - $total_nc_rise_tarifa_exento, 2, '.', '');

		$casillero_509 = number_format($casillero_500 + $casillero_540 + $casillero_507 + $casillero_508, 2, '.', '');
		$casillero_519 = number_format($casillero_510 + $casillero_550 + $casillero_517 + $casillero_518, 2, '.', '');
		$casillero_529 = number_format($casillero_520 + $casillero_560, 2, '.', '');

		$total_compras_tarifa_noobjeto = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_compras', '2', '6');
		$total_liq_compras_tarifa_noobjeto = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'liquidacion_compra', '2', '6');
		$total_nc_compras_tarifa_noobjeto = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_compras', '2', '6');

		$casillero_531 = number_format($total_compras_tarifa_noobjeto + $total_liq_compras_tarifa_noobjeto, 2, '.', '');
		$casillero_541 = number_format($total_compras_tarifa_noobjeto + $total_liq_compras_tarifa_noobjeto - $total_nc_compras_tarifa_noobjeto, 2, '.', '');

		$total_compras_tarifa_exento = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_compras', '2', '7');
		$total_liq_compras_tarifa_exento = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'liquidacion_compra', '2', '7');
		$total_nc_compras_tarifa_exento = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_credito_compras', '2', '7');

		$casillero_532 = number_format($total_compras_tarifa_exento + $total_liq_compras_tarifa_exento, 2, '.', '');
		$casillero_542 = number_format($total_compras_tarifa_exento + $total_liq_compras_tarifa_exento - $total_nc_compras_tarifa_exento, 2, '.', '');

		$casillero_563 = $casillero_419 > 0 ? number_format((($casillero_411 + $casillero_415 + $casillero_435) / $casillero_419), 4, '.', '') : 0;
		$casillero_564 = number_format(($casillero_520 + $casillero_560) * $casillero_563, 2, '.', '');


		//total numero de documentos de compras
		$numero_total_facturas_compras = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'facturas_compras', '');
		$numero_total_notas_credito_compras = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nota_credito_compra', '');
		$numero_total_notas_debito_compras = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'nota_debito_compra', '');
		$numero_total_notas_venta = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'notas_venta', '');
		$numero_total_liquidaciones_compras = $declaracion_iva->contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'liquidaciones_compras', '');

		$casillero_115 = number_format($numero_total_facturas_compras + $numero_total_notas_credito_compras + $numero_total_notas_debito_compras + $numero_total_liquidaciones_compras, 2, '.', '');
		$casillero_117 = $numero_total_notas_venta;

		$total_liq_compras_tarifa_cero = $declaracion_iva->otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'liquidacion_compra', '2', '0');
		$casillero_119 = number_format($total_liq_compras_tarifa_cero, 2, '.', '');

		$casillero_601 = ($casillero_499 - $casillero_564) > 0 ? ($casillero_499 - $casillero_564) : 0;
		$casillero_602 = ($casillero_499 - $casillero_564) < 0 ? ($casillero_499 - $casillero_564) * -1 : 0;

		//retenciones
		$total_retenciones_iva_ventas = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_ventas', '0');
		$total_retenciones_iva_compras_diez = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '10');
		$total_retenciones_iva_compras_veinte = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '20');
		$total_retenciones_iva_compras_treinta = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '30');
		$total_retenciones_iva_compras_cincuenta = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '50');
		$total_retenciones_iva_compras_setenta = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '70');
		$total_retenciones_iva_compras_cien = $declaracion_iva->detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, 'retenciones_compras', '100');

		$casillero_609 = number_format($total_retenciones_iva_ventas, 2, '.', '');
		$casillero_620 = ($casillero_601 - $casillero_602 - $casillero_609) > 0 ? number_format(($casillero_601 - $casillero_602 - $casillero_609), 2, '.', '') : 0;
		$casillero_699 = number_format($casillero_620, 2, '.', '');

		$casillero_615 = $casillero_602 > 0 ? $casillero_602 : 0;
		$casillero_617 = $casillero_615 > 0 ? $casillero_609 : 0;

		$casillero_721 = number_format($total_retenciones_iva_compras_diez, 2, '.', '');
		$casillero_723 = number_format($total_retenciones_iva_compras_veinte, 2, '.', '');
		$casillero_725 = number_format($total_retenciones_iva_compras_treinta, 2, '.', '');
		$casillero_727 = number_format($total_retenciones_iva_compras_cincuenta, 2, '.', '');
		$casillero_729 = number_format($total_retenciones_iva_compras_setenta, 2, '.', '');
		$casillero_731 = number_format($total_retenciones_iva_compras_cien, 2, '.', '');
		$casillero_799 = number_format($casillero_721 + $casillero_723 + $casillero_725 + $casillero_727 + $casillero_729 + $casillero_731, 2, '.', '');
		$casillero_801 = number_format($casillero_799, 2, '.', '');
		$casillero_859 = number_format($casillero_699 + $casillero_801, 2, '.', '');
		$casillero_902 = number_format($casillero_859, 2, '.', '');


?>
		<div class="panel-group" id="accordion">
			<div class="panel panel-info">
				<!--<div class="panel-heading">
		   <h4 class="panel-title">-->
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse1"><span class="caret"></span> VENTAS</a>
				<!--</h4>
	  </div>-->
				<div id="collapse1" class="panel-collapse collapse">
					<div class="panel-body">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="panel panel-info">
									<table class="table">
										<tr>
											<td class='col-md-6' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="6">
												<FONT SIZE=2>RESUMEN DE VENTAS Y OTRAS OPERACIONES DEL PERÍODO QUE DECLARA</FONT>
											</td>
											<td class='col-md-2' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">
												<FONT SIZE=2>VALOR BRUTO</FONT>
											</td>
											<td class='col-md-2' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">
												<FONT SIZE=2>VALOR NETO</FONT>
											</td>
											<td class='col-md-2' style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">
												<FONT SIZE=2>IMPUESTO GENERADO</FONT>
											</td>
										</tr>
										<tr>
											<td colspan="8"> </td>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">
												<FONT SIZE=1>(VALOR BRUTO - N/C)</FONT>
											</td>
											<td colspan="2"> </td>
										</tr>
										<tr>
											<td colspan="6">Ventas locales (excluye activos fijos) gravadas tarifa diferente de cero</td>
											<td class="col-md-1 text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">401</td>
											<td class="col-md-1 text-right" style="padding: 1px;"><?php echo number_format($casillero_401, 2, '.', ''); ?></td>
											<td class="col-md-1 text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">411</td>
											<td class="col-md-1 text-right" style="padding: 1px;"><?php echo number_format($casillero_411, 2, '.', ''); ?></td>
											<td class="col-md-1 text-center" style="background: silver; color: rgb(0, 0, 0); padding: 1px;">421</td>
											<td class="col-md-1 text-right" style="padding: 1px;"><?php echo number_format($casillero_421, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Ventas de activos fijos gravadas tarifa diferente de cero</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">402</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">412</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">422</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Ventas locales (excluye activos fijos) gravadas tarifa 5%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">425</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">435</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">445</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">IVA generado en la diferencia entre ventas y notas de crédito con distinta tarifa (ajuste a pagar)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">423</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">IVA generado en la diferencia entre ventas y notas de crédito con distinta tarifa (ajuste a favor)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">424</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Ventas locales (excluye activos fijos) gravadas tarifa 0% que no dan derecho a crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">403</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">413</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Ventas de activos fijos gravadas tarifa 0% que no dan derecho a crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">404</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">414</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Ventas locales (excluye activos fijos) gravadas tarifa 0% que dan derecho a crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">405</td>
											<td class="text-right"><?php echo number_format($casillero_405, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">415</td>
											<td class="text-right"><?php echo number_format($casillero_415, 2, '.', ''); ?></td>
											<td></td>
											<td></td>
										</tr>
										<tr>
											<td colspan="6">Ventas de activos fijos gravadas tarifa 0% que dan derecho a crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">406</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">416</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Exportaciones de bienes</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">407</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">417</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Exportaciones de servicios y/o derechos</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">408</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">418</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL VENTAS Y OTRAS OPERACIONES</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">409</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_409, 2, '.', ''); ?></td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">419</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_419, 2, '.', ''); ?></td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">429</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_429, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Transferencias no objeto o exentas de IVA</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">431</td>
											<td class="text-right"><?php echo number_format($casillero_431, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">441</td>
											<td class="text-right"><?php echo number_format($casillero_441, 2, '.', ''); ?></td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Notas de crédito tarifa 0% por compensar próximo mes</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">442</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Notas de crédito tarifa diferente de cero por compensar próximo mes</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">443</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">453</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Ingresos por reembolso como intermediario / valores facturados por operadoras de transporte (informativo)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">434</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">444</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">454</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="12">LIQUIDACIÓN DEL IVA EN EL MES</td>
										</tr>
										<tr>
											<td colspan="10">Total transferencias gravadas tarifa diferente de cero a contado este mes</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">480</td>
											<td class="text-right"><?php echo number_format($casillero_480, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Total transferencias gravadas tarifa diferente de cero a crédito este mes</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">481</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Total impuesto generado</td>
											<td colspan="4">(trasládese campo 429)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">482</td>
											<td class="text-right"><?php echo number_format($casillero_482, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Impuesto a liquidar del mes anterior</td>
											<td colspan="4">(trasládese el campo 485 de la declaración del período anterior)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">483</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">Impuesto a liquidar en este mes</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">484</td>
											<td class="text-right"><?php echo number_format($casillero_484, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Impuesto a liquidar en el próximo mes</td>
											<td colspan="4">482-484</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">485</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;" colspan="6">TOTAL IMPUESTO A LIQUIDAR EN ESTE MES</td>
											<td style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;" colspan="4">483+484</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">499</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_499, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="5">Total comprobantes de venta emitidos</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">111</td>
											<td class="text-center"><?php echo number_format($casillero_111, 0, '.', ''); ?></td>
											<td colspan="3">Total comprobantes de venta anulados</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">113</td>
											<td class="text-center"><?php echo number_format($casillero_113, 0, '.', ''); ?></td>
										</tr>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>


			<div class="panel panel-info">
				<!--<div class="panel-heading">
	   <h4 class="panel-title">-->
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse2"><span class="caret"></span> COMPRAS</a>
				<!--</h4>
	  </div>-->

				<div id="collapse2" class="panel-collapse collapse">
					<div class="panel-body">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="panel panel-info">

									<table class="table">
										<tr>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="6">RESUMEN DE ADQUISICIONES Y PAGOS DEL PERÍODO QUE DECLARA</td>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">VALOR BRUTO</td>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">VALOR NETO</td>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">IMPUESTO GENERADO</td>
										</tr>
										<tr>
											<td colspan="8"> </td>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="2">
												<FONT SIZE=1>(VALOR BRUTO - N/C)</FONT>
											</td>
											<td colspan="2"> </td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones y pagos (excluye activos fijos) gravados tarifa diferente de cero (con derecho a crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">500</td>
											<td class="text-right"><?php echo number_format($casillero_500, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">510</td>
											<td class="text-right"><?php echo number_format($casillero_510, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">520</td>
											<td class="text-right"><?php echo number_format($casillero_520, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones locales de activos fijos gravados tarifa diferente de cero (con derecho a crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">501</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">511</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">521</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones y pagos locales (excluye activos fijos) gravados con tarifa 5% (con derecho a crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">540</td>
											<td class="text-right"><?php echo number_format($casillero_540, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">550</td>
											<td class="text-right"><?php echo number_format($casillero_550, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">560</td>
											<td class="text-right"><?php echo number_format($casillero_560, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Otras adquisiciones y pagos gravados tarifa diferente de cero (sin derecho a crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">502</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">512</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">522</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Importaciones de servicios y/o derechos gravados tarifa diferente de cero</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">503</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">513</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">523</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Importaciones de bienes (excluye activos fijos) gravados tarifa diferente de cero</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">504</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">514</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">524</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Importaciones de activos fijos gravados tarifa diferente de cero</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">505</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">515</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">525</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">IVA generado en la diferencia entre adquisiciones y notas de crédito con distinta tarifa (ajuste en positivo al crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">526</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">IVA generado en la diferencia entre adquisiciones y notas de crédito con distinta tarifa (ajuste en negativo al crédito tributario)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">527</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Importaciones de bienes (incluye activos fijos) gravados tarifa 0%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">506</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">516</td>
											<td class="text-right">-</td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones y pagos (incluye activos fijos) gravados tarifa 0%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">507</td>
											<td class="text-right"><?php echo number_format($casillero_507, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">517</td>
											<td class="text-right"><?php echo number_format($casillero_517, 2, '.', ''); ?></td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones realizadas a contribuyentes RISE (hasta diciembre 2021), NEGOCIOS POPULARES (desde enero 2022)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">508</td>
											<td class="text-right"><?php echo number_format($casillero_508, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">518</td>
											<td class="text-right"><?php echo number_format($casillero_518, 2, '.', ''); ?></td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL ADQUISICIONES Y PAGOS </td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">509</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_509, 2, '.', ''); ?></td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">519</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_519, 2, '.', ''); ?></td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">529</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_529, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones no objeto de IVA</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">531</td>
											<td class="text-right"><?php echo number_format($casillero_531, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">541</td>
											<td class="text-right"><?php echo number_format($casillero_541, 2, '.', ''); ?></td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6">Adquisiciones exentas del pago de IVA</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">532</td>
											<td class="text-right"><?php echo number_format($casillero_532, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">542</td>
											<td class="text-right"><?php echo number_format($casillero_542, 2, '.', ''); ?></td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6">Notas de crédito tarifa 0% por compensar próximo mes</td>
											<td></td>
											<td></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">543</td>
											<td></td>
											<td colspan="2">-</td>
										</tr>
										<tr>
											<td colspan="6">Notas de crédito tarifa diferente de cero por compensar próximo mes</td>
											<td class="text-right">-</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">544</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">554</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Pagos netos por reembolso como intermediario / valores facturados por socios a operadoras de transporte (informativo)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">535</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">545</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">555</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="6">Factor de proporcionalidad para crédito tributario</td>
											<td colspan="4">(411+412+415+416+417+418) / 419</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">563</td>
											<td class="text-right"><?php echo number_format($casillero_563, 4, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="8">Crédito tributario aplicable en este período (de acuerdo al factor de proporcionalidad o a su contabilidad) (520+521+523+524+525+526-527) x 563</td>
											<td>Valor sugerido:</td>
											<td class="text-right"><?php echo number_format($casillero_564, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">564</td>
											<td class="text-right"><?php echo number_format($casillero_564, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="5">Total comprobantes de venta recibidos por adquisiciones y pagos (excepto notas de venta)</td>
											<td style="background: silver; color: rgb(0, 0, 0);">115</td>
											<td class="text-center"><?php echo number_format($casillero_115, 0, '.', ''); ?></td>
											<td colspan="3">Total notas de venta recibidas</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">117</td>
											<td class="text-right"><?php echo number_format($casillero_117, 0, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Total liquidaciones de compra emitidas (por pagos tarifa 0% de IVA, o por reembolsos en relación de dependencia)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">119</td>
											<td class="text-right"><?php echo number_format($casillero_119, 0, '.', ''); ?></td>
										</tr>
									</table>

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>


			<div class="panel panel-info">
				<!--<div class="panel-heading">
	   <h4 class="panel-title">-->
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse3"><span class="caret"></span> RESUMEN IMPOSITIVO</a>
				<!--</h4>
	  </div>-->

				<div id="collapse3" class="panel-collapse collapse">
					<div class="panel-body">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="panel panel-info">
									<table class="table">

										<tr>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="12">RESUMEN IMPOSITIVO: AGENTE DE PERCEPCIÓN DEL IMPUESTO AL VALOR AGREGADO</td>
										</tr>
										<tr>
											<td colspan="4">Impuesto causado</td>
											<td colspan="6">(si la diferencia de los campos 499-564 es mayor que cero)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">601</td>
											<td class="text-right"><?php echo number_format($casillero_601, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="4">Crédito tributario aplicable en este período</td>
											<td colspan="6">(si la diferencia de los campos 499-564 es menor que cero)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">602</td>
											<td class="text-right"><?php echo number_format($casillero_602, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">(-) Compensación de IVA por ventas efectuadas en zonas afectadas - Ley de solidaridad, restitución de crédito tributario en resoluciones </td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">604</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="12">(-) Saldo crédito tributario del mes anterior</td>
										</tr>
										<tr>
											<td class="text-right"></td>
											<td colspan="4">Por adquisiciones e importaciones</td>
											<td colspan="5">(trasládese el campo 615 de la declaración del período anterior)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">605</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td class="text-right"></td>
											<td colspan="4">Por retenciones en la fuente de IVA que le han sido efectuadas</td>
											<td colspan="5">(trasládese el campo 617 de la declaración del período anterior)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">606</td>
											<td class="text-right">-</td>
										</tr>

										<tr>
											<td class="text-right"></td>
											<td colspan="4">Por compensación de IVA por ventas efectuadas en zonas afectadas - Ley de solidaridad</td>
											<td colspan="5">(trasládese el campo 619 de la declaración del período anterior)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">608</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">(-) Retenciones en la fuente de IVA que le han sido efectuadas en este período</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">609</td>
											<td class="text-right"><?php echo number_format($casillero_609, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">(-) IVA devuelto o descontado por transacciones realizadas con personas adultas mayores o personas con discapacidad</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">622</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">(+) Ajuste por IVA devuelto o descontado por adquisiciones efectuadas con medio electrónico</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">610</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">(+) Ajuste por IVA devuelto e IVA rechazado (por concepto de devoluciones de IVA), ajuste de IVA por procesos de control y otros (adquisiciones en importaciones), imputables al crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">612</td>
											<td class="text-right">-</td>
										</tr>

										<tr>
											<td colspan="10">(+) Ajuste por IVA devuelto e IVA rechazado, ajuste de IVA por procesos de control y otros (por concepto retenciones en la fuente de IVA), imputables al crédito tributario</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">613</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="10">(+) Ajuste por IVA devuelto por otras instituciones del sector público imputable al crédito tributario en el mes</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">614</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="12">Saldo crédito tributario para el próximo mes</td>
										</tr>
										<tr>
											<td colspan="2"></td>
											<td colspan="6">Por adquisiciones e importaciones</td>
											<td colspan="1">Valor sugerido:</td>
											<td class="text-right"><?php echo number_format($casillero_615, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">615</td>
											<td class="text-right"><?php echo $casillero_615; ?></td>
										</tr>
										<tr>
											<td colspan="2"></td>
											<td colspan="6">Por retenciones en la fuente de IVA que le han sido efectuadas</td>
											<td>Valor sugerido:</td>
											<td class="text-right"><?php echo number_format($casillero_617, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">617</td>
											<td class="text-right"><?php echo number_format($casillero_617, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="2"></td>
											<td colspan="6">Por compensación de IVA por ventas efectuadas en zonas afectadas - Ley de solidaridad, restitución de crédito tributario en resoluciones </td>
											<td>Valor sugerido:</td>
											<td class="text-right">-</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">619</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="4">SUBTOTAL A PAGAR</td>
											<td colspan="4">Si (601-602-603-604-605-606-607-608-609+610+611+612+613+614) > 0</td>
											<td>Valor sugerido:</td>
											<td class="text-right"> <?php echo number_format($casillero_620, 2, '.', ''); ?></td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">620</td>
											<td class="text-right"> <?php echo number_format($casillero_620, 2, '.', ''); ?></td>
										</tr>


										<tr>
											<td colspan="10">IVA presuntivo de salas de juego (bingo mecánicos) y otros juegos de azar (aplica para ejercicios anteriores al 2013)</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">621</td>
											<td class="text-right">-</td>
										</tr>
										<tr>
											<td colspan="8" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL IMPUESTO A PAGAR POR PERCEPCIÓN</td>
											<td class="text-right" colspan="2" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">620+621</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">699</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_699, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;" colspan="12">AGENTE DE RETENCIÓN DEL IMPUESTO AL VALOR AGREGADO</td>
										</tr>
										<tr>
											<td colspan="10">Retención del 10%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">721</td>
											<td class="text-right"><?php echo number_format($casillero_721, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Retención del 20%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">723</td>
											<td class="text-right"><?php echo number_format($casillero_723, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Retención del 30%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">725</td>
											<td class="text-right"><?php echo number_format($casillero_725, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Retención del 50%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">727</td>
											<td class="text-right"><?php echo number_format($casillero_727, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Retención del 70%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">729</td>
											<td class="text-right"><?php echo number_format($casillero_729, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Retención del 100%</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">731</td>
											<td class="text-right"><?php echo number_format($casillero_731, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="8">TOTAL IMPUESTO RETENIDO</td>
											<td colspan="2">721+723+725+727+729+731</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">799</td>
											<td class="text-right"><?php echo number_format($casillero_799, 2, '.', ''); ?></td>
										</tr>
										<tr>
											<td colspan="10">Devolución provisional de IVA mediante compensación con retenciones efectuadas</td>
											<td class="text-center" style="background: silver; color: rgb(0, 0, 0);">800</td>
											<td class="text-right">0.00</td>
										</tr>
										<tr>
											<td colspan="8" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL IMPUESTO A PAGAR POR RETENCIÓN</td>
											<td class="text-right" colspan="2" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">799-800</td>
											<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">801</td>
											<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><?php echo number_format($casillero_801, 2, '.', ''); ?></td>
										</tr>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div class="panel panel-info">
				<!--<div class="panel-heading">
	   <h4 class="panel-title">-->
				<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse4"><span class="caret"></span> TOTALES</a>
				<!--</h4>
	  </div>-->
				<div id="collapse4" class="panel-collapse collapse in">
					<div class="panel-body">
						<div class="form-group">
							<div class="col-sm-12">
								<div class="panel panel-info">

									<div class="table-responsive">
										<table class="table">
											<tr>
												<td colspan="8" style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;"> TOTAL CONSOLIDADO DE IMPUESTO AL VALOR AGREGADO</td>
												<td colspan="2" style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;">699 + 801</td>
												<td class="text-center" style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;">859</td>
												<td class="text-right" style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;"><?php echo number_format($casillero_859, 2, '.', ''); ?></td>
												<td colspan="12" style="background: #FFFF; color: rgb(247, 248, 250); padding: 1px;"> - </td>
											</tr>
											<tr>
												<td colspan="12" style="background: #2E64FE; color: rgb(247, 248, 250); padding: 1px;">VALORES A PAGAR (luego de imputación al pago en declaraciones sustitutivas)</td>
											</tr>
											<tr>
												<td colspan="8" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">TOTAL IMPUESTO A PAGAR</td>
												<td colspan="2" class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">859-898</td>
												<td class="text-center" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;">902</td>
												<td class="text-right" style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;"><b><input style="background: #FAAC58; color: rgb(0, 0, 0); padding: 1px;" class="text-right" value="<?php echo number_format($casillero_902, 2, '.', ''); ?>"></b></td>
											</tr>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>


		</div>

	<?php
	} else {
		$errors[] = "Error desconocido";
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


class declaracion_iva
{
	//para sacar detalle de encabezado y detalle de ventas y notas de credito por ventas
	public function otras_ventas($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, $tabla, $tarifa_iva)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		switch ($tabla) {
			case "facturas_ventas":
				$condicion_fecha_mensual = ' month(enc.fecha_factura)=' . $mes_semestre . ' and year(enc.fecha_factura)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_factura between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_factura between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
			case "notas_credito_ventas":
				$condicion_fecha_mensual = ' month(enc.fecha_nc)=' . $mes_semestre . ' and year(enc.fecha_nc)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_nc between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_nc between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
		}

		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$info_ambiente = mysqli_query($con, "SELECT * FROM config_electronicos WHERE ruc_empresa='" . $ruc_empresa . "'");
		$tipo_ambiente = mysqli_fetch_array($info_ambiente)['tipo_ambiente'];


		switch ($tabla) {
			case "facturas_ventas":
				$info_ventas = mysqli_query($con, "SELECT round(sum(cue.subtotal_factura - cue.descuento),2) as subtotal 
				FROM cuerpo_factura as cue INNER JOIN encabezado_factura as enc ON enc.serie_factura=cue.serie_factura 
				and enc.secuencial_factura=cue.secuencial_factura 
					WHERE $condicion_fechas and $condicion_ruc_empresa and cue.tarifa_iva = '" . $tarifa_iva . "' 
					and enc.ambiente='" . $tipo_ambiente . "' ");
				$subtotal_ventas = mysqli_fetch_array($info_ventas)['subtotal'];
				return number_format($subtotal_ventas, 2, '.', '');
				break;
			case "notas_credito_ventas":
				$info_nc = mysqli_query($con, "SELECT sum(cue.subtotal_nc-cue.descuento) as subtotal FROM cuerpo_nc as cue 
				INNER JOIN encabezado_nc as enc ON enc.serie_nc=cue.serie_nc and enc.secuencial_nc=cue.secuencial_nc 
				WHERE $condicion_fechas and $condicion_ruc_empresa and cue.tarifa_iva = '" . $tarifa_iva . "' 
				and enc.ambiente='" . $tipo_ambiente . "'");
				$subtotal_nc = mysqli_fetch_array($info_nc)['subtotal'];
				return number_format($subtotal_nc, 2, '.', '');
				break;
		}
	}

	//para sacar detalle de encabezado y detalle de ventas y notas de credito por ventas
	public function otras_compras($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, $tabla, $tipo_impuesto, $tarifa_iva)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		switch ($tabla) {
			case "facturas_compras" || "notas_credito_compras" || "notas_de_venta" || "liquidacion_compra":
				$condicion_fecha_mensual = ' month(enc.fecha_compra)=' . $mes_semestre . ' and year(enc.fecha_compra)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_compra between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_compra between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
		}

		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}


		switch ($tabla) {
			case "facturas_compras":
				$info_compras = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal 
				FROM cuerpo_compra cue INNER JOIN encabezado_compra enc ON enc.codigo_documento=cue.codigo_documento 
				and enc.id_comprobante ='1' 
				WHERE $condicion_fechas and $condicion_ruc_empresa and cue.impuesto='" . $tipo_impuesto . "' 
				and cue.det_impuesto = '" . $tarifa_iva . "' and enc.deducible_en='04'");
				$subtotal_compras = mysqli_fetch_array($info_compras)['subtotal'];
				return number_format($subtotal_compras, 2, '.', '');
				break;
			case "notas_credito_compras":
				$info_compras = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal 
				FROM cuerpo_compra cue INNER JOIN encabezado_compra enc ON enc.codigo_documento=cue.codigo_documento 
				and enc.id_comprobante ='4' WHERE $condicion_fechas and $condicion_ruc_empresa and enc.cod_doc_mod !='02' 
				and cue.impuesto='" . $tipo_impuesto . "' and cue.det_impuesto = '" . $tarifa_iva . "' 
				and enc.deducible_en='04'");
				$subtotal_compras = mysqli_fetch_array($info_compras)['subtotal'];
				return number_format($subtotal_compras, 2, '.', '');
				break;
			case "notas_de_venta":
				$info_compras = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal 
				FROM cuerpo_compra cue INNER JOIN encabezado_compra enc 
				ON enc.codigo_documento=cue.codigo_documento and enc.id_comprobante ='2' 
				WHERE $condicion_fechas and $condicion_ruc_empresa and cue.impuesto='" . $tipo_impuesto . "' 
				and cue.det_impuesto = '" . $tarifa_iva . "' and enc.deducible_en='04'");
				$subtotal_compras = mysqli_fetch_array($info_compras)['subtotal'];
				return number_format($subtotal_compras, 2, '.', '');
				break;

			case "nc_notas_de_venta":
				$info_compras = mysqli_query($con, "SELECT sum(cue.subtotal) as subtotal 
				FROM cuerpo_compra cue INNER JOIN encabezado_compra enc ON enc.codigo_documento=cue.codigo_documento 
				and enc.id_comprobante ='4' WHERE $condicion_fechas and $condicion_ruc_empresa and enc.cod_doc_mod='02' 
				and cue.impuesto='" . $tipo_impuesto . "' and cue.det_impuesto = '" . $tarifa_iva . "' 
				and enc.deducible_en='04'");
				$subtotal_compras = mysqli_fetch_array($info_compras)['subtotal'];
				return number_format($subtotal_compras, 2, '.', '');
				break;
			case "liquidacion_compra":
				$info_compras = mysqli_query($con, "SELECT sum(cue.subtotal) as subtotal 
				FROM cuerpo_compra cue INNER JOIN encabezado_compra enc ON enc.codigo_documento=cue.codigo_documento 
				and enc.id_comprobante ='3' WHERE $condicion_fechas and $condicion_ruc_empresa 
				and cue.impuesto='" . $tipo_impuesto . "' and cue.det_impuesto = '" . $tarifa_iva . "'");
				$subtotal_compras = mysqli_fetch_array($info_compras)['subtotal'];
				return number_format($subtotal_compras, 2, '.', '');
				break;
		}
	}

	//para sacar detalle de de retenciones
	public function detalle_retenciones($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, $tabla, $porcentaje)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		switch ($tabla) {
			case "retenciones_ventas" || "retenciones_compras ":
				$condicion_fecha_mensual = ' month(enc.fecha_emision)=' . $mes_semestre . ' and year(enc.fecha_emision)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_emision between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_emision between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
		}

		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}


		switch ($tabla) {
			case "retenciones_ventas":
				$info_retenciones_ventas = mysqli_query($con, "SELECT round(sum(cue.valor_retenido),2) as subtotal FROM cuerpo_retencion_venta cue INNER JOIN encabezado_retencion_venta enc ON enc.codigo_unico=cue.codigo_unico WHERE $condicion_fechas and $condicion_ruc_empresa and cue.impuesto = '2' ");
				$subtotal_retencion = mysqli_fetch_array($info_retenciones_ventas)['subtotal'];
				return number_format($subtotal_retencion, 2, '.', '');
				break;
			case "retenciones_compras":
				$info_retenciones_compras = mysqli_query($con, "SELECT round(sum(cue.valor_retenido),2) as subtotal FROM cuerpo_retencion cue INNER JOIN encabezado_retencion enc ON enc.serie_retencion=cue.serie_retencion and enc.secuencial_retencion=cue.secuencial_retencion WHERE $condicion_fechas and $condicion_ruc_empresa and cue.impuesto = 'IVA' and cue.porcentaje_retencion= '" . $porcentaje . "' ");
				$subtotal_retencion = mysqli_fetch_array($info_retenciones_compras)['subtotal'];
				return number_format($subtotal_retencion, 2, '.', '');
				break;
		}
	}


	//para contar documentos
	public function contar_documentos($con, $datos_declaracion, $mes_semestre, $anio, $ruc_empresa, $tabla, $estado_documento)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		switch ($tabla) {
			case "facturas_ventas":
				$condicion_fecha_mensual = ' month(enc.fecha_factura)=' . $mes_semestre . ' and year(enc.fecha_factura)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_factura between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_factura between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
			case "facturas_compras" || "notas_venta" || "liquidaciones_compras" || "nota_credito_compra" || "nota_debito_compra":
				$condicion_fecha_mensual = ' month(enc.fecha_compra)=' . $mes_semestre . ' and year(enc.fecha_compra)=' . $anio;
				$condicion_fecha_primer_semestre = " enc.fecha_compra between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
				$condicion_fecha_segundo_semestre = " enc.fecha_compra between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;
				break;
		}

		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}


		switch ($tabla) {
			case "facturas_ventas":
				$info_ventas = mysqli_query($con, "SELECT count(enc.secuencial_factura) as total_documentos FROM encabezado_factura enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.estado_sri = '" . $estado_documento . "' ");
				$total_documentos = mysqli_fetch_array($info_ventas)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
			case "facturas_compras":
				$info_compras = mysqli_query($con, "SELECT count(enc.numero_documento) as total_documentos FROM encabezado_compra enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.id_comprobante = '1' and enc.deducible_en='04'");
				$total_documentos = mysqli_fetch_array($info_compras)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
			case "notas_venta":
				$info_compras = mysqli_query($con, "SELECT count(enc.numero_documento) as total_documentos FROM encabezado_compra enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.id_comprobante = '2' and enc.deducible_en='04'");
				$total_documentos = mysqli_fetch_array($info_compras)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
			case "liquidaciones_compras":
				$info_compras = mysqli_query($con, "SELECT count(enc.numero_documento) as total_documentos FROM encabezado_compra enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.id_comprobante = '3' and enc.deducible_en='04'");
				$total_documentos = mysqli_fetch_array($info_compras)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
			case "nota_credito_compra":
				$info_compras = mysqli_query($con, "SELECT count(enc.numero_documento) as total_documentos FROM encabezado_compra enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.id_comprobante = '4' and enc.deducible_en='04'");
				$total_documentos = mysqli_fetch_array($info_compras)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
			case "nota_debito_compra":
				$info_compras = mysqli_query($con, "SELECT count(enc.numero_documento) as total_documentos FROM encabezado_compra enc WHERE $condicion_fechas and $condicion_ruc_empresa and enc.id_comprobante = '5' and enc.deducible_en='04'");
				$total_documentos = mysqli_fetch_array($info_compras)['total_documentos'];
				return number_format($total_documentos, 2, '.', '');
				break;
		}
	}



	public function venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc.fecha_factura)=' . $mes_semestre . ' and year(enc.fecha_factura)=' . $anio;
		$condicion_fecha_primer_semestre = " enc.fecha_factura between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc.fecha_factura between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT  round(sum(cue_fac.subtotal_factura-cue_fac.descuento),2) as subtotal, 
		round(sum((cue_fac.subtotal_factura - cue_fac.descuento ) * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_factura as cue_fac 
		INNER JOIN encabezado_factura as enc ON enc.serie_factura=cue_fac.serie_factura 
		and enc.secuencial_factura=cue_fac.secuencial_factura 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue_fac.tarifa_iva 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.porcentaje_iva > 0 ");
		$row_ventas = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_ventas['subtotal'], 'iva' => $row_ventas['total_iva']);
	}

	public function venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue_fac.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc.fecha_factura)=' . $mes_semestre . ' and year(enc.fecha_factura)=' . $anio;
		$condicion_fecha_primer_semestre = " enc.fecha_factura between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc.fecha_factura between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT  round(sum(cue_fac.subtotal_factura-cue_fac.descuento),2) as subtotal, 
		round(sum((cue_fac.subtotal_factura - cue_fac.descuento ) * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_factura as cue_fac 
		INNER JOIN encabezado_factura as enc ON enc.serie_factura=cue_fac.serie_factura 
		and enc.secuencial_factura=cue_fac.secuencial_factura 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue_fac.tarifa_iva 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.codigo = '5' ");
		$row_ventas = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_ventas['subtotal'], 'iva' => $row_ventas['total_iva']);
	}

	public function nc_venta_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc_nc.fecha_nc)=' . $mes_semestre . ' and year(enc_nc.fecha_nc)=' . $anio;
		$condicion_fecha_primer_semestre = " enc_nc.fecha_nc between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc_nc.fecha_nc between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT  round(sum(cue_nc.subtotal_nc-cue_nc.descuento),2) as subtotal, 
		round(sum((cue_nc.subtotal_nc - cue_nc.descuento) * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_nc as cue_nc 
		INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc 
		and enc_nc.secuencial_nc=cue_nc.secuencial_nc 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue_nc.tarifa_iva 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.codigo = '5' ");
		$row_ventas = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_ventas['subtotal'], 'iva' => $row_ventas['total_iva']);
	}

	public function nc_venta_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc_nc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc_nc.fecha_nc)=' . $mes_semestre . ' and year(enc_nc.fecha_nc)=' . $anio;
		$condicion_fecha_primer_semestre = " enc_nc.fecha_nc between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc_nc.fecha_nc between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT  round(sum(cue_nc.subtotal_nc-cue_nc.descuento),2) as subtotal, 
		round(sum((cue_nc.subtotal_nc - cue_nc.descuento) * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_nc as cue_nc 
		INNER JOIN encabezado_nc as enc_nc ON enc_nc.serie_nc=cue_nc.serie_nc 
		and enc_nc.secuencial_nc=cue_nc.secuencial_nc 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue_nc.tarifa_iva 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.porcentaje_iva > 0 ");
		$row_ventas = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_ventas['subtotal'], 'iva' => $row_ventas['total_iva']);
	}


	public function compras_diferente_cero($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, $documento)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc.fecha_compra)=' . $mes_semestre . ' and year(enc.fecha_compra)=' . $anio;
		$condicion_fecha_primer_semestre = " enc.fecha_compra between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc.fecha_compra between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
		round(sum(cue.subtotal * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_compra as cue 
		INNER JOIN encabezado_compra as enc ON enc.codigo_documento=cue.codigo_documento 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue.det_impuesto 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.porcentaje_iva > 0 
		and enc.id_comprobante ='" . $documento . "' and enc.deducible_en='04' ");
		$row_compras = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_compras['subtotal'], 'iva' => $row_compras['total_iva']);
	}

	public function compras_tarifa_cinco($con, $datos_declaracion, $ruc_empresa, $mes_semestre, $anio, $documento)
	{
		$inicio_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-01-01")) . "'";;
		$final_primer_semestre = "'" . date("Y-m-d", strtotime($anio . "-06-30")) . "'";
		$inicio_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-07-01")) . "'";
		$final_segundo_semestre = "'" . date("Y-m-d", strtotime($anio . "-12-31")) . "'";
		$condicion_ruc_empresa = "mid(cue.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and mid(enc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'";

		$condicion_fecha_mensual = ' month(enc.fecha_compra)=' . $mes_semestre . ' and year(enc.fecha_compra)=' . $anio;
		$condicion_fecha_primer_semestre = " enc.fecha_compra between " . $inicio_primer_semestre . " and " . $final_primer_semestre;
		$condicion_fecha_segundo_semestre = " enc.fecha_compra between " . $inicio_segundo_semestre . " and " . $final_segundo_semestre;


		if ($datos_declaracion == "mensual") {
			$condicion_fechas = $condicion_fecha_mensual;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "01") {
			$condicion_fechas = $condicion_fecha_primer_semestre;
		}
		if ($datos_declaracion == "semestral" && $mes_semestre == "02") {
			$condicion_fechas = $condicion_fecha_segundo_semestre;
		}

		$resultado = mysqli_query($con, "SELECT round(sum(cue.subtotal),2) as subtotal, 
		round(sum(cue.subtotal * (tar.porcentaje_iva /100)),2) as total_iva
		FROM cuerpo_compra as cue 
		INNER JOIN encabezado_compra as enc ON enc.codigo_documento=cue.codigo_documento 
		INNER JOIN tarifa_iva as tar ON tar.codigo=cue.det_impuesto 
		WHERE $condicion_fechas and $condicion_ruc_empresa and tar.codigo = '5' 
		and enc.id_comprobante ='" . $documento . "' and enc.deducible_en='04'");
		$row_compras = mysqli_fetch_array($resultado);
		return array('subtotal' => $row_compras['subtotal'], 'iva' => $row_compras['total_iva']);
	}
}
?>