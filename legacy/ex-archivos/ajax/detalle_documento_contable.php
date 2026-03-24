<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../clases/empresas.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
//para mostrar detalle de diario
if ($action == 'detalle_asiento') {
	$id_diario = $_GET['codigo_unico'];

	//detalle de encabezado diario
	$detalle_encabezado = mysqli_query($con, "SELECT * FROM encabezado_diario WHERE id_diario = '" . $id_diario . "' ");
	$row_encabezado_diario = mysqli_fetch_array($detalle_encabezado);
	$fecha_diario = $row_encabezado_diario['fecha_asiento'];
	$concepto_diario = $row_encabezado_diario['concepto_general'];
	$numero_diario = $row_encabezado_diario['id_diario'];
	$estado = $row_encabezado_diario['estado'];
	$tipo = $row_encabezado_diario['tipo'];
	//$codigo_unico=$estado=='ok'?$row_encabezado_diario['codigo_unico']:0;
	$codigo_unico = $row_encabezado_diario['codigo_unico'];
	$codigo_unico_bloque = $row_encabezado_diario['codigo_unico_bloque'];
?>
	<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px; height: 14%;" class="alert alert-info" role="alert">
		<b>Asiento No.:</b> <?php echo $numero_diario; ?> <b>Fecha:</b> <?php echo date("d/m/Y", strtotime($fecha_diario)); ?> <b>Estado:</b> <?php echo $estado; ?> <b>Tipo:</b> <?php echo $tipo; ?><br><b>Concepto: </b><?php echo $concepto_diario; ?></p>
	</div>
	<div class="panel panel-info" style="margin-bottom: -10px;">
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<th style="padding: 2px;">Código</th>
					<th style="padding: 2px;">Cuenta</th>
					<th class='text-center' style="padding: 2px;">Debe</th>
					<th class='text-right' style="padding: 2px;">Haber</th>
					<th class='text-center' style="padding: 2px;">Detalle</th>
				</tr>
				<?php
				// PARA MOSTRAR LOS ITEMS 
				$subtotal_debe = 0;
				$subtotal_haber = 0;
				$detalle_diario = mysqli_query($con, "SELECT * FROM detalle_diario_contable as det INNER JOIN plan_cuentas as plan ON plan.id_cuenta=det.id_cuenta 
	WHERE det.codigo_unico = '" . $codigo_unico . "' and det.codigo_unico_bloque = '" . $codigo_unico_bloque . "' and det.ruc_empresa='" . $ruc_empresa . "'");
				while ($row = mysqli_fetch_array($detalle_diario)) {
					$codigo_cuenta = $row['codigo_cuenta'];
					$cuenta = $row['nombre_cuenta'];
					$debe = number_format($row['debe'], 2, '.', '');
					$haber = number_format($row['haber'], 2, '.', '');
					$detalle = $row['detalle_item'];
					$subtotal_debe += $debe;
					$subtotal_haber += $haber;
				?>
					<tr>
						<td class='text-left' style="padding: 2px;"><?php echo strtoupper($codigo_cuenta); ?></td>
						<td class='text-left' style="padding: 2px;"><?php echo strtoupper($cuenta); ?></td>
						<td class='text-right' style="padding: 2px;"><?php echo $debe; ?></td>
						<td class='text-right' style="padding: 2px;"><?php echo $haber; ?></td>
						<td style="padding: 2px;"><?php echo strtoupper($detalle); ?></td>
					</tr>
				<?php
				}
				?>
				<tr class="info">
					<input type="hidden" value="<?php echo number_format($subtotal_debe, 2, '.', ''); ?>" id="subtotal_debe">
					<input type="hidden" value="<?php echo number_format($subtotal_haber, 2, '.', ''); ?>" id="subtotal_haber">
					<td style="padding: 2px;"></td>
					<td class='text-right' style="padding: 2px;">Sumas: </td>
					<td class='text-right' style="padding: 2px;"><?php echo number_format($subtotal_debe, 2, '.', ''); ?></td>
					<td class='text-right' style="padding: 2px;"><?php echo number_format($subtotal_haber, 2, '.', ''); ?></td>
					<td style="padding: 2px;"></td>
				</tr>
			</table>
		</div>
	</div>
<?php
}
?>