<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];

//PARA BUSCAR LAS FACTURAS de ventas	
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$tipo_reporte = $_POST['action'];
$id_empleado = $_POST['id_empleado'];
$periodo = $_POST['periodo'];
$desde = $_POST['desde'];
$hasta = $_POST['hasta'];
$id_novedad = $action;


if ($action == 'R') {
	reporte_roles($con, $id_empresa, $periodo, $id_empleado);
}
if ($action == 'Q') {
	reporte_quincena($con, $id_empresa, $periodo, $id_empleado);
}

if ($action > 0 && $action < 15) {
	reporte_novedades($con, $id_empresa, $desde, $hasta, $id_empleado, $id_novedad);
}

if ($action == 'T') {
	reporte_novedades($con, $id_empresa, $desde, $hasta, $id_empleado, $id_novedad);
}

function reporte_novedades($con, $id_empresa, $desde, $hasta, $id_empleado, $id_novedad)
{
	if (empty($id_empleado)) {
		$condicion_empleado = "";
	} else {
		$condicion_empleado = " and nov.id_empleado=" . $id_empleado;
	}

	if ($id_novedad == 'T') {
		$condicion_novedad = "";
	} else {
		$condicion_novedad = " and nov.id_novedad = " . $id_novedad;
	}

	$resultado = mysqli_query($con, "SELECT * FROM novedades as nov 
				INNER JOIN empleados as emp 
				ON emp.id=nov.id_empleado 
				WHERE nov.id_empresa = '" . $id_empresa . "' 
				and DATE_FORMAT(nov.fecha_novedad, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
				and '" . date("Y/m/d", strtotime($hasta)) . "' $condicion_empleado $condicion_novedad and nov.status=1 order by emp.nombres_apellidos asc");

	if (mysqli_num_rows($resultado) > 0) {
?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Período</th>
						<th>empleado</th>
						<th>Documento</th>
						<th>Novedad</th>
						<th>Detalle</th>
						<th>Aplica en</th>
						<th>Iess</th>
						<th class="text-right">Valor</th>
					</tr>
					<?php
					$suma_total = 0;
					while ($row = mysqli_fetch_array($resultado)) {
						$periodo = $row['mes_ano'];
						$empleado = $row['nombres_apellidos'];
						$documento = $row['documento'];
						$valor = $row['valor'];
						$detalle = $row['detalle'];
						$aplica_en = $row['aplica_en'] == 'R' ? 'Rol de pagos' : 'Quincena';
						$iess = $row['iess'] == '0' ? 'NO' : 'SI';
						$suma_total += $valor;
						foreach (novedades_sueldos() as $novedades) {
							if (intval($novedades['codigo']) === intval($row['id_novedad'])) {
								$novedad = $novedades['nombre'];
							}
						}
					?>
						<tr>
							<td><?php echo $periodo; ?></td>
							<td><?php echo strtoupper($empleado); ?></td>
							<td><?php echo $documento; ?></td>
							<td><?php echo strtoupper($novedad); ?></td>
							<td><?php echo strtoupper($detalle); ?></td>
							<td><?php echo strtoupper($aplica_en); ?></td>
							<td><?php echo strtoupper($iess); ?></td>
							<td class="text-right"><?php echo number_format($valor, 2, '.', ''); ?></td>
						<?php
					}
						?>
						<tr class="info">
							<th colspan="7">Totales</th>
							<td class="text-right"><?php echo number_format($suma_total, 2, '.', ''); ?></td>
						</tr>
				</table>
			</div>
		</div>
	<?php
	} else {
		echo "No hay datos para mostrar";
	}
}


function reporte_roles($con, $id_empresa, $periodo, $id_empleado)
{
	if (empty($id_empleado)) {
		$condicion_empleado = "";
	} else {
		$condicion_empleado = " and det.id_empleado=" . $id_empleado;
	}

	$resultado = mysqli_query($con, "SELECT * FROM rolespago as rol 
				INNER JOIN detalle_rolespago as det ON det.id_rol=rol.id
				INNER JOIN empleados as emp ON emp.id=det.id_empleado 
				WHERE rol.id_empresa = '" . $id_empresa . "' and rol.mes_ano = '" . $periodo . "' 
				$condicion_empleado and rol.status=1 order by emp.nombres_apellidos asc");

	if (mysqli_num_rows($resultado) > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Período</th>
						<th>empleado</th>
						<th>Documento</th>
						<th>Sueldo</th>
						<th>Ingresos_gravados</th>
						<th>Ingresos_excentos</th>
						<th>Aporte_personal</th>
						<th>Aporte_patronal</th>
						<th>Préstamos</th>
						<th>Descuentos</th>
						<th>Quincena</th>
						<th>Total_egresos</th>
						<th>Décimo_tercero</th>
						<th>Décimo_cuarto</th>
						<th>Fondos_reserva</th>
						<th>A_recibir</th>
						<th>Abonos</th>
						<th>Saldo</th>
					</tr>
					<?php
					$suma_sueldo = 0;
					$suma_ing_gravado = 0;
					$suma_ing_excento = 0;
					$suma_aporte_personal = 0;
					$suma_aporte_patronal = 0;
					$suma_prestamos = 0;
					$suma_descuento = 0;
					$suma_quincena = 0;
					$suma_egresos = 0;
					$suma_tercero = 0;
					$suma_cuarto = 0;
					$suma_fondo_reserva = 0;
					$suma_a_recibir = 0;
					$suma_abonos = 0;
					$suma_saldo = 0;
					while ($row = mysqli_fetch_array($resultado)) {
						$periodo = $row['mes_ano'];
						$empleado = $row['nombres_apellidos'];
						$documento = $row['documento'];
						$sueldo = $row['sueldo'];
						$ingresos_gravados = $row['ingresos_gravados'];
						$ingresos_excentos = $row['ingresos_excentos'];
						$aporte_personal = $row['aporte_personal'];
						$aporte_patronal = $row['aporte_patronal'];
						$prestamos = $row['prestamos'];
						$descuentos = $row['descuentos'];
						$quincena = $row['quincena'];
						$total_egresos = $row['total_egresos'];
						$tercero = $row['tercero'];
						$cuarto = $row['cuarto'];
						$fondo_reserva = $row['fondo_reserva'];
						$a_recibir = $row['a_recibir'];
						$abonos = $row['abonos'];
						$saldo = $a_recibir - $abonos;

						$suma_sueldo += $sueldo;
						$suma_ing_gravado += $ingresos_gravados;
						$suma_ing_excento += $ingresos_excentos;
						$suma_aporte_personal += $aporte_personal;
						$suma_aporte_patronal += $aporte_patronal;
						$suma_prestamos += $prestamos;
						$suma_descuento += $descuentos;
						$suma_quincena += $quincena;
						$suma_egresos += $total_egresos;
						$suma_tercero += $tercero;
						$suma_cuarto += $cuarto;
						$suma_fondo_reserva += $fondo_reserva;
						$suma_a_recibir += $a_recibir;
						$suma_abonos += $abonos;
						$suma_saldo += $saldo;

					?>
						<tr>
							<td><?php echo $periodo; ?></td>
							<td><?php echo strtoupper($empleado); ?></td>
							<td><?php echo $documento; ?></td>
							<td><?php echo number_format($sueldo, 2, '.', ''); ?></td>
							<td><?php echo number_format($ingresos_gravados, 2, '.', ''); ?></td>
							<td><?php echo number_format($ingresos_excentos, 2, '.', ''); ?></td>
							<td><?php echo number_format($aporte_personal, 2, '.', ''); ?></td>
							<td><?php echo number_format($aporte_patronal, 2, '.', ''); ?></td>
							<td><?php echo number_format($prestamos, 2, '.', ''); ?></td>
							<td><?php echo number_format($descuentos, 2, '.', ''); ?></td>
							<td><?php echo number_format($quincena, 2, '.', ''); ?></td>
							<td><?php echo number_format($total_egresos, 2, '.', ''); ?></td>
							<td><?php echo number_format($tercero, 2, '.', ''); ?></td>
							<td><?php echo number_format($cuarto, 2, '.', ''); ?></td>
							<td><?php echo number_format($fondo_reserva, 2, '.', ''); ?></td>
							<td><?php echo number_format($a_recibir, 2, '.', ''); ?></td>
							<td><?php echo number_format($abonos, 2, '.', ''); ?></td>
							<td><?php echo number_format($saldo, 2, '.', ''); ?></td>
						<?php
					}
						?>
						<tr class="info">
							<th colspan="3">Totales</th>
							<td><?php echo number_format($suma_sueldo, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_ing_gravado, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_ing_excento, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_aporte_personal, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_aporte_patronal, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_prestamos, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_descuento, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_quincena, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_egresos, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_tercero, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_cuarto, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_fondo_reserva, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_a_recibir, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_abonos, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_saldo, 2, '.', ''); ?></td>
						</tr>

				</table>
			</div>
		</div>
	<?php
	} else {
		echo "No hay datos para mostrar";
	}
}


function reporte_quincena($con, $id_empresa, $periodo, $id_empleado)
{
	if (empty($id_empleado)) {
		$condicion_empleado = "";
	} else {
		$condicion_empleado = " and det.id_empleado=" . $id_empleado;
	}

	$resultado = mysqli_query($con, "SELECT * FROM quincenas as qui 
				INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
				INNER JOIN empleados as emp ON emp.id=det.id_empleado 
				WHERE qui.id_empresa = '" . $id_empresa . "' and qui.mes_ano = '" . $periodo . "' 
				$condicion_empleado and qui.status=1 order by emp.nombres_apellidos asc");

	if (mysqli_num_rows($resultado) > 0) {
	?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-hover">
					<tr class="info">
						<th>Período</th>
						<th>empleado</th>
						<th>Documento</th>
						<th>Quincena</th>
						<th>Adicional</th>
						<th>Descuento</th>
						<th>A recibir</th>
						<th>Abonos</th>
						<th>Saldo</th>
					</tr>
					<?php
					$suma_quincena = 0;
					$suma_adicional = 0;
					$suma_descuento = 0;
					$suma_arecibir = 0;
					$suma_abonos = 0;
					$suma_saldo = 0;
					while ($row = mysqli_fetch_array($resultado)) {
						$periodo = $row['mes_ano'];
						$empleado = $row['nombres_apellidos'];
						$documento = $row['documento'];
						$quincena = $row['quincena'];
						$adicional = $row['adicional'];
						$descuento = $row['descuento'];
						$arecibir = $row['arecibir'];
						$abonos = $row['abonos'];
						$saldo = $arecibir - $abonos;
						$suma_quincena += $quincena;
						$suma_adicional += $adicional;
						$suma_descuento += $descuento;
						$suma_arecibir += $arecibir;
						$suma_abonos += $abonos;
						$suma_saldo += $saldo;
					?>
						<tr>
							<td><?php echo $periodo; ?></td>
							<td><?php echo strtoupper($empleado); ?></td>
							<td><?php echo $documento; ?></td>
							<td><?php echo number_format($quincena, 2, '.', ''); ?></td>
							<td><?php echo number_format($adicional, 2, '.', ''); ?></td>
							<td><?php echo number_format($descuento, 2, '.', ''); ?></td>
							<td><?php echo number_format($arecibir, 2, '.', ''); ?></td>
							<td><?php echo number_format($abonos, 2, '.', ''); ?></td>
							<td><?php echo number_format($saldo, 2, '.', ''); ?></td>
						<?php
					}
						?>
						<tr class="info">
							<th colspan="3">Totales</th>
							<td><?php echo number_format($suma_quincena, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_adicional, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_descuento, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_arecibir, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_abonos, 2, '.', ''); ?></td>
							<td><?php echo number_format($suma_saldo, 2, '.', ''); ?></td>
						</tr>

				</table>
			</div>
		</div>
<?php
	} else {
		echo "No hay datos para mostrar";
	}
}
?>