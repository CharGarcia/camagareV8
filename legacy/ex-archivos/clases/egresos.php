<?php
//include("../ajax/detalle_documento.php");
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
if (!isset($_SESSION['ruc_empresa'])) {
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
}
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$con = conenta_login();
//para buscar informacion para hacer el egreso
if ($action == 'actualiza_compras_por_pagar') {
	$actualiza_compras_por_pagar = new egresos();
	echo $actualiza_compras_por_pagar->saldo_por_pagar_nuevo_egreso($con);
}

//para actualizar cada vez que se agrega una factura al egreso actual que se esta haciendo
if ($action == 'actualiza_egreso_tmp') {
	$actualiza_compras_por_pagar = new egresos();
	echo $actualiza_compras_por_pagar->actualiza_egreso_tmp($con);
}

if ($action == 'buscar_por_pagar') {
	$buscar_por_pagar = new egresos();
	echo $buscar_por_pagar->buscar_compras_por_pagar($con);
}

if ($action == 'mostrar_formas_de_pago_egreso') {
	$mostrar_formas_de_pago = new egresos();
	echo $mostrar_formas_de_pago->formas_de_pago_egreso($con, $ruc_empresa);
}

if ($action == 'buscar_nomina_por_pagar') {
	$buscar_nomina_por_pagar = new egresos();
	echo $buscar_nomina_por_pagar->buscar_sueldos_por_pagar($con);
}

if ($action == 'buscar_quincena_por_pagar') {
	$buscar_quincena_por_pagar = new egresos();
	echo $buscar_quincena_por_pagar->buscar_quincena_por_pagar($con);
}


//clase para egresos
class egresos
{
	/*
	para sacar saldo y me muestra al momento de hacer el egreso
	la diferencia es que al hacer el egreso me debe mostrar el saldo menos las retenciones
	sin importar las fechas o sea es un saldo general sin depender de fechas
	*/
	public function saldo_por_pagar_nuevo_egreso($con)
	{
		$ruc_empresa = $_SESSION['ruc_empresa'];
		//para vaciar la tabla cuando ingresa	
		if (isset($ruc_empresa)) {
			$delete_compras_tmp = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "'"); 

			$query_guarda_compras_por_pagar = mysqli_query($con, "INSERT INTO saldos_compras_tmp (id_saldo, fecha_compra, razon_social, nombre_comercial, id_proveedor, codigo_documento, id_comprobante, numero_documento, total_compra,total_egresos,total_retencion,total_egresos_tmp, ruc_empresa, id_compra) 
		SELECT null, ec.fecha_compra, pro.razon_social, pro.nombre_comercial, ec.id_proveedor, ec.codigo_documento, ec.id_comprobante, ec.numero_documento, ec.total_compra, 0,0,0, ec.ruc_empresa, ec.id_encabezado_compra 
		FROM encabezado_compra as ec INNER JOIN proveedores as pro ON pro.id_proveedor=ec.id_proveedor WHERE ec.ruc_empresa = '" . $ruc_empresa . "' ");
			$update_egresos = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT detie.codigo_documento_cv as codigo_registro, sum(detie.valor_ing_egr) as suma_egresos FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=detie.codigo_documento WHERE detie.estado ='OK' and detie.tipo_documento='EGRESO' and detie.tipo_ing_egr='CCXPP' and detie.ruc_empresa = '" . $ruc_empresa . "' group by detie.codigo_documento_cv ) as total_egresos SET sal_tmp.total_egresos = total_egresos.suma_egresos WHERE sal_tmp.codigo_documento=total_egresos.codigo_registro  ");
			$update_retenciones = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT er.numero_comprobante as factura, er.id_proveedor as proveedor, er.total_retencion as suma_retenciones, er.id_compras as id_compra FROM encabezado_retencion as er WHERE er.estado_sri !='ANULADA' and mid(er.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "') as total_retenciones SET sal_tmp.total_retencion = total_retenciones.suma_retenciones WHERE sal_tmp.numero_documento=total_retenciones.factura and sal_tmp.id_proveedor=total_retenciones.proveedor and sal_tmp.id_comprobante !='4' and total_retenciones.id_compra=sal_tmp.id_compra");
			$update_egresos_tmp = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT iet.id_documento as registro, sum(iet.valor) as suma_egreso_tmp FROM ingresos_egresos_tmp as iet WHERE iet.tipo_documento='EGRESO' group by iet.id_documento) as total_egreso_tmp SET sal_tmp.total_egresos_tmp = total_egreso_tmp.suma_egreso_tmp WHERE total_egreso_tmp.registro=sal_tmp.codigo_documento ");
			$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and  total_compra <= (total_egresos + total_retencion + total_egresos_tmp)");
			$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and total_compra + total_egresos = 0 and id_comprobante=4 ");
		}
	}

	//ACTUALIZA EL REGISTRO que se agrego al egreso actual que se esta haciendo
	public function actualiza_egreso_tmp($con)
	{
		$ruc_empresa = $_SESSION['ruc_empresa'];
		//para borrar las que tienen saldo cero
		$update_egresos_tmp = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT iet.id_documento as registro, sum(iet.valor) as suma_egreso_tmp FROM ingresos_egresos_tmp as iet WHERE iet.tipo_documento='EGRESO' group by iet.id_documento) as total_egreso_tmp SET sal_tmp.total_egresos_tmp = total_egreso_tmp.suma_egreso_tmp WHERE total_egreso_tmp.registro=sal_tmp.codigo_documento ");
		$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and  total_compra <= (total_egresos + total_retencion + total_egresos_tmp)");
		$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and total_compra + total_egresos = 0 and id_comprobante=4 ");
	}

	//para sacar reportes dependiendo de fechas
	public function saldos_por_pagar($con, $desde, $hasta)
	{
		$ruc_empresa = $_SESSION['ruc_empresa'];
		//para vaciar la tabla cuando ingresa	
		$delete_compras_tmp = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "'");
		$query_guarda_compras_por_pagar = mysqli_query($con, "INSERT INTO saldos_compras_tmp (id_saldo, fecha_compra, razon_social, nombre_comercial, id_proveedor, codigo_documento, id_comprobante, numero_documento, total_compra, total_egresos, total_retencion, total_egresos_tmp, ruc_empresa, id_compra) 
	SELECT null, ec.fecha_compra, pro.razon_social, pro.nombre_comercial, ec.id_proveedor, ec.codigo_documento, ec.id_comprobante, ec.numero_documento, ec.total_compra, 0,0,0, ec.ruc_empresa, ec.id_encabezado_compra 
	FROM encabezado_compra as ec INNER JOIN proveedores as pro ON pro.id_proveedor=ec.id_proveedor WHERE ec.ruc_empresa = '" . $ruc_empresa . "' and ec.fecha_compra between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "'");
		$update_egresos = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT detie.codigo_documento_cv as codigo_registro, sum(detie.valor_ing_egr) as suma_egresos FROM detalle_ingresos_egresos as detie INNER JOIN ingresos_egresos as ing_egr ON ing_egr.codigo_documento=detie.codigo_documento WHERE detie.estado ='OK' and detie.tipo_ing_egr='CCXPP' and detie.tipo_documento='EGRESO' and detie.ruc_empresa = '" . $ruc_empresa . "' and ing_egr.fecha_ing_egr between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' group by detie.codigo_documento_cv ) as total_egresos SET sal_tmp.total_egresos = total_egresos.suma_egresos WHERE sal_tmp.codigo_documento=total_egresos.codigo_registro "); 
		$update_retenciones = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT er.numero_comprobante as factura, er.id_proveedor as proveedor, er.total_retencion as suma_retenciones, er.id_compras as id_compra FROM encabezado_retencion as er WHERE er.estado_sri !='ANULADA' and mid(er.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and er.fecha_emision between '" . date("Y-m-d", strtotime($desde)) . "' and '" . date("Y-m-d", strtotime($hasta)) . "' ) as total_retenciones SET sal_tmp.total_retencion = total_retenciones.suma_retenciones WHERE sal_tmp.numero_documento=total_retenciones.factura and sal_tmp.id_proveedor=total_retenciones.proveedor and sal_tmp.id_comprobante !='4' and total_retenciones.id_compra=sal_tmp.id_compra");
		$update_egresos_tmp = mysqli_query($con, "UPDATE saldos_compras_tmp as sal_tmp, (SELECT iet.id_documento as registro, sum(iet.valor) as suma_egreso_tmp FROM ingresos_egresos_tmp as iet WHERE iet.tipo_documento='EGRESO' group by iet.id_documento) as total_egreso_tmp SET sal_tmp.total_egresos_tmp = total_egreso_tmp.suma_egreso_tmp WHERE total_egreso_tmp.registro=sal_tmp.codigo_documento ");
		$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and total_compra <= (total_egresos + total_retencion + total_egresos_tmp)");
		$eliminar_saldos_cero = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and total_compra + total_egresos = 0 and id_comprobante=4 ");
	}

	//para mostrar en las facturas por pagar al hacer el egreso
	public function buscar_compras_por_pagar($con)
	{
		$ruc_empresa = $_SESSION['ruc_empresa'];
		if (isset($ruc_empresa)) {
			$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['por_buscar'], ENT_QUOTES)));
			$ordenado = "fecha_compra"; //mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
			$por = "asc"; //mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
			$aColumns = array('numero_documento', 'razon_social', 'nombre_comercial', 'fecha_compra'); //Columnas de busqueda
			$sTable = "saldos_compras_tmp";
			$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' ";
			if ($_GET['por_buscar'] != "") {
				$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' AND ";

				for ($i = 0; $i < count($aColumns); $i++) {
					$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" . $ruc_empresa . "' OR ";
				}
				$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' ", -3);
				$sWhere .= ')';
			}
			$sWhere .= " order by $ordenado $por";

			include("../ajax/pagination.php"); //include pagination file
			//pagination variables
			$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
			$per_page = 5; //how much records you want to show
			$adjacents  = 4; //gap between pages after number of adjacents
			$offset = ($page - 1) * $per_page;
			//Count the total number of row in your table*/
			$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
			$row = mysqli_fetch_array($count_query);
			$numrows = $row['numrows'];
			$total_pages = ceil($numrows / $per_page);
			$reload = '';
			//main query to fetch the data
			$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
			$query = mysqli_query($con, $sql);
			//loop through fetched data
			if ($numrows > 0) {
?>

				<div class="panel panel-info" style="height: 350px;overflow-y: auto;">
					<div class="table-responsive">
						<table class="table">
							<tr class="info">
								<th style="padding: 2px;">Fecha</th>
								<th style="padding: 2px;">Proveedor</th>
								<th style="padding: 2px;">Documento</th>
								<th style="padding: 2px;">Días</span></th>
								<th style="padding: 2px;">Deuda</span></th>
								<th style="padding: 2px;" class='text-center'>A pagar</span></th>
								<th class='text-center' style="width: 36px; padding: 2px;">Agregar</th>
							</tr>
							<?php
							while ($row = mysqli_fetch_array($query)) {
								$nombre_proveedor = $row['razon_social'];
								$valor_compra_inicial = $row["total_compra"];
								$valor_egresos = $row["total_egresos"];
								$valor_retencion = $row["total_retencion"];
								$valor_egreso_tmp = $row["total_egresos_tmp"];
								$numero_documento = $row["numero_documento"];
								$codigo_documento = $row["codigo_documento"];
								$id_comprobante = $row["id_comprobante"];
								$id_proveedor = $row["id_proveedor"];

								//tipo de documento
								$busca_tipo_documento = "SELECT *  FROM comprobantes_autorizados WHERE id_comprobante = '" . $id_comprobante . "'";
								$result_tipo_documento = $con->query($busca_tipo_documento);
								$row_tipo_comprobante = mysqli_fetch_array($result_tipo_documento);
								$nombre_comprobante = $row_tipo_comprobante['comprobante'];

								if ($id_comprobante == 4) {
									$valor_egreso_tmp = $valor_egreso_tmp * -1;
									$valor_egresos = $valor_egresos * -1;
								}

								$valor_compra = number_format($valor_compra_inicial - $valor_egresos - $valor_retencion - $valor_egreso_tmp, 2, '.', '');
								$fecha_compra = date("d-m-Y", strtotime($row["fecha_compra"]));
								$id_encabezado_compra = $row["id_saldo"];

								//para traer plazo de pago y forma
								$busca_datos_plazo = "SELECT *  FROM formas_pago_compras WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and codigo_documento = '" . $codigo_documento . "' ";
								$result_plazo = $con->query($busca_datos_plazo);
								$datos_plazo = mysqli_fetch_array($result_plazo);
								$plazo_pago = empty($datos_plazo['plazo_pago']) ? 0 : intval($datos_plazo['plazo_pago']);
								//$tiempo_pago = $datos_plazo['tiempo_pago']=null?0:$datos_plazo['tiempo_pago'];
								$fecha_actual = date("d-m-Y");
								$fecha_final = date("d-m-Y", strtotime($row["fecha_compra"] . "+ $plazo_pago days"));

								$dias_vencidos = round((strtotime($fecha_actual) - strtotime($fecha_final)) / 86400, 0);
								$dias_vencidos = ($dias_vencidos < 0) ? substr($dias_vencidos, 1) : $dias_vencidos;

								if (strtotime($fecha_actual) >= strtotime($fecha_final)) {
									$plazo = "";
									$label_class = 'label-danger';
								} else {
									$plazo = "";
									$label_class = 'label-success';
								}

								if ($valor_compra > 0) {
							?>
									<input type="hidden" value="<?php echo $valor_compra; ?>" id="total_documento_por_pagar<?php echo $id_encabezado_compra; ?>">
									<input type="hidden" value="<?php echo $nombre_proveedor; ?>" id="nombre_proveedor<?php echo $id_encabezado_compra; ?>">
									<input type="hidden" value="<?php echo $id_proveedor; ?>" id="id_proveedor_seleccionado<?php echo $id_encabezado_compra; ?>">
									<input type="hidden" value="<?php echo $page; ?>" id="pagina">
									<tr>
										<td style="padding: 2px;"><?php echo $fecha_compra; ?></td>
										<td style="padding: 2px;"><?php echo $nombre_proveedor; ?></td>
										<td style="padding: 2px;"><?php echo $nombre_comprobante . " " . $numero_documento; ?></td>
										<td style="padding: 2px;"><span class="label <?php echo $label_class; ?>"><?php echo $plazo . " " . $dias_vencidos; ?></span></td>
										<td style="padding: 2px;"><?php echo $valor_compra; ?></td>
										<td style="padding: 2px; width:auto;" class="col-sm-2"><input type="text" style="text-align:right;" id="a_pagar<?php echo $id_encabezado_compra; ?>" class="form-control col-sm-2" value="<?php echo $valor_compra; ?>"></td>
										<td style="padding: 2px;" class='text-center'><a href="#" onclick="agrega_por_pagar_proveedor('<?php echo $id_encabezado_compra; ?>')" class="btn btn-info"><i class="glyphicon glyphicon-plus"></i></a></td>
									</tr>
							<?php
								}
							}
							?>
							<tr>
								<td colspan="7"><span class="pull-right">
										<?php
										echo paginate($reload, $page, $total_pages, $adjacents);
										?>
									</span></td>
							</tr>
						</table>

					</div>
				</div>
		<?php
			}
		} else {
			echo "<script>$.notify('Sesión expirada, vuelva a ingresar al sistema.','error')</script>";
		}
	}


	//para mostrar en el modal el formulario de pagos
	public function formas_de_pago_egreso($con, $ruc_empresa)
	{
		?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr class="info">
						<td style="padding: 2px;">Forma</th>
						<td style="padding: 2px;" class="text-center">Tipo</td>
						<td style="padding: 2px;" class="text-center">Valor</td>
						<td style="padding: 2px;" class="text-center">Cheque</td>
						<td style="padding: 2px;" class="text-center">Fecha cobro</td>
						<td style="padding: 2px;" class="text-center">Agregar</td>
					</tr>
					<tr>
						<td style="padding: 2px;" class="col-sm-3">
							<select style="height: 30px;" class="form-control" title="Seleccione forma de pago." id="forma_pago_egreso">
								<option value="0" selected>Seleccione</option>
								<?php
								$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_opcion='2' and status='1' order by descripcion asc");
								while ($row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos)) {
									//el 1 junto al id en el value es para saber que los datos son de la lista de opciones de cobro
								?>
									<option value="<?php echo "1" . $row_cobros_pagos['id']; ?>"><?php echo strtoupper($row_cobros_pagos['descripcion']); ?></option>
								<?php
								}
								$cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='" . $ruc_empresa . "' and cue_ban.status ='1'");
								while ($row = mysqli_fetch_array($cuentas)) {
									//el 2 junto al id en el value es para saber que los datos son desde bancos
								?>
									<option value="<?php echo "2" . $row['id_cuenta']; ?>"><?php echo strtoupper($row['cuenta_bancaria']); ?></option>
								<?php
								}
								?>
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<select class="form-control" style="height: 30px" title="Seleccione" name="tipo" id="tipo">
								<option value="0">N/A</option>
								<option value="C">Cheque</option>
								<option value="D">Débito</option>
								<option value="T">Transferencia</option>
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<input type="number" class="form-control input-sm text-left" title="Ingrese valor" id="valor_pago_egreso" placeholder="Valor">
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<input type="number" class="form-control input-sm text-left" pattern="[0-9]{3}-[0-9]{3}-[0-9]{9}" title="Ingrese número de cheque" id="numero_cheque_egreso" placeholder="Cheque">
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<div class="pull-right">
								<input type="text" class="form-control input-sm text-center" id="fecha_cobro_egreso" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</td>
						<td style="padding: 2px;" class="col-sm-1">
							<div class="text-center">
								<button type="button" class="btn btn-info btn-md" title="Agregar forma de pago" onclick="agrega_pagos_egreso();"><span class="glyphicon glyphicon-plus"></span></button>
							</div>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<script>
			jQuery(function($) {
				$("#fecha_cobro_egreso").mask("99-99-9999");
			});


			$(function() {
				$("#fecha_cobro_egreso").datepicker({
					dateFormat: "dd-mm-yy",
					firstDay: 1,
					dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
					dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
					monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
						"Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
					],
					monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
						"Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
					]
				});
			});


			$(function() {
				//cuando cambia el forma de pago
				$('#forma_pago_egreso').change(function() {
					var forma_pago = $("#forma_pago_egreso").val();
					var tipo = $("#tipo").val();
					$("#tipo").val("0");
					cambio_opciones(forma_pago, tipo);
				});

				//para cuando cambia el tipo
				$('#tipo').change(function() {
					var forma_pago = $("#forma_pago_egreso").val();
					var tipo = $("#tipo").val();
					cambio_opciones(forma_pago, tipo);
				});
			});

			//para pasar el valor de detalles de documentos al total de egreso en el modal de formas de pagos
			$(function() {
				//document.getElementById("cuenta_bancaria_egreso").style.visibility = "hidden";
				document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
				document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
				var total_egreso = $("#suma_egreso").val();
				$("#valor_pago_egreso").val(total_egreso);
			});


			function cambio_opciones(forma_pago, tipo) {

				var origen = forma_pago.substring(0, 1);
				if (origen == "1") {
					$("#tipo").val("0");
					$("#numero_cheque_egreso").val("");
					document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
					document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
					document.getElementById('valor_pago_egreso').focus();
				}

				if (origen == "2" && tipo == "C") {
					$("#numero_cheque_egreso").val("");
					document.getElementById("numero_cheque_egreso").style.visibility = "";
					document.getElementById("fecha_cobro_egreso").style.visibility = "";

					var id_cuenta = forma_pago.substring(1, forma_pago.length);
					$.post('../ajax/buscar_tipo_cuenta_bancaria.php', {
						id_cuenta: id_cuenta
					}).done(function(respuesta) {
						$.each(respuesta, function(i, item) {
							var tipo_cuenta_bancaria = item.tipo_cuenta;
							var ultimo_cheque = item.ultimo_cheque;

							if (tipo_cuenta_bancaria == "2") {
								document.getElementById("numero_cheque_egreso").style.visibility = "";
								document.getElementById("fecha_cobro_egreso").style.visibility = "";
								$("#numero_cheque_egreso").val(ultimo_cheque);
								document.getElementById('numero_cheque_egreso').focus();
							}

							if (tipo_cuenta_bancaria == "1") {
								document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
								document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
							}
						});
					});
				}

				if (origen == "2" && tipo != "C") {
					document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
					document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
				}

			};
		</script>
		<?php
	}

	//para mostrar la nomina por pagar
	public function buscar_sueldos_por_pagar($con)
	{

		$status = '1';
		$id_empresa = $_SESSION['id_empresa'];
		$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['por_buscar_nomina'], ENT_QUOTES)));
		$ordenado = "rol.mes_ano"; //mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		$por = "desc"; //mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		$aColumns = array('emp.nombres_apellidos', 'emp.documento', 'rol.mes_ano'); //Columnas de busqueda

		$sTable = "detalle_rolespago as det INNER JOIN empleados as emp ON emp.id=det.id_empleado 
		INNER JOIN rolespago as rol ON rol.id=det.id_rol ";

		$sWhere = "WHERE emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.a_recibir - det.abonos > 0  and rol.status =1 ";
		if ($_GET['por_buscar_nomina'] != "") {
			$sWhere = "WHERE (emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.a_recibir - det.abonos > 0 and rol.status =1 AND ";

			for ($i = 0; $i < count($aColumns); $i++) {
				$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.a_recibir - det.abonos > 0 and rol.status =1 OR ";
			}
			$sWhere = substr_replace($sWhere, "AND emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.a_recibir - det.abonos > 0 and rol.status =1 ", -3);
			$sWhere .= ')';
		}
		$sWhere .= "  order by $ordenado $por";

		include("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
		$per_page = 5; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row = mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows / $per_page);
		$reload = '';
		//main query to fetch the data
		$sql = "SELECT rol.mes_ano as mes_ano, emp.nombres_apellidos as nombres_apellidos, emp.id as id_empleado,
		det.a_recibir - det.abonos as a_recibir, det.id as id_detalle FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows > 0) {
		?>

			<div class="panel panel-info" style="height: 350px;overflow-y: auto;">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<th style="padding: 2px;">Período</th>
							<th style="padding: 2px;">Apellidos y nombres</th>
							<th style="padding: 2px;">Deuda</span></th>
							<th style="padding: 2px;" class='text-center'>A pagar</span></th>
							<th class='text-center' style="width: 36px; padding: 2px;">Agregar</th>
						</tr>
						<?php
						while ($row = mysqli_fetch_array($query)) {
							$mes_ano = $row['mes_ano'];
							$empleado = $row["nombres_apellidos"];
							$a_recibir = $row["a_recibir"];
							$id_detalle = $row["id_detalle"];
							$id_empleado = $row["id_empleado"];

							$sql_abono_tmp = mysqli_query($con, "SELECT round(SUM(valor),2) as abonos_tmp FROM ingresos_egresos_tmp WHERE id_documento = concat('ROL_PAGOS', '" . $id_detalle . "') and tipo_transaccion ='CCXRPP' group by id_documento");
							$row_abonos = mysqli_fetch_array($sql_abono_tmp);
							$total_abonos_tmp = isset($row_abonos['abonos_tmp']) ? $row_abonos['abonos_tmp'] : 0;

							//$sql_egresos = mysqli_query($con, "SELECT round(SUM(valor_ing_egr),2) as egresos FROM detalle_ingresos_egresos WHERE tipo_documento='EGRESO' and codigo_documento_cv = concat('ROL_PAGOS','".$id_detalle."') and tipo_ing_egr ='CCXRPP' group by codigo_documento_cv");
							//$row_egresos = mysqli_fetch_array($sql_egresos);
							$total_egresos = number_format($a_recibir - $total_abonos_tmp, 2, '.', '');

							if ($total_egresos > 0) {
						?>
								<input type="hidden" value="<?php echo $total_egresos; ?>" id="total_sueldo_por_pagar<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $empleado; ?>" id="nombre_empleado<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $id_empleado; ?>" id="id_empleado<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $mes_ano; ?>" id="mes_ano<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $page; ?>" id="pagina">
								<tr>
									<td style="padding: 2px;"><?php echo $mes_ano; ?></td>
									<td style="padding: 2px;"><?php echo $empleado; ?></td>
									<td style="padding: 2px;"><?php echo $total_egresos; ?></td>
									<td style="padding: 2px; width:auto;" class="col-sm-1"><input type="text" style="text-align:right;" id="a_pagar_sueldo<?php echo $id_detalle; ?>" class="form-control col-sm-2" value="<?php echo $total_egresos; ?>"></td>
									<td style="padding: 2px;" class='text-center'><a href="#" onclick="agrega_por_pagar_nomina('<?php echo $id_detalle; ?>')" class="btn btn-info"><i class="glyphicon glyphicon-plus"></i></a></td>
								</tr>
						<?php
							}
						}
						?>
						<tr>
							<td colspan="5"><span class="pull-right">
									<?php
									echo paginate($reload, $page, $total_pages, $adjacents);
									?>
								</span></td>
						</tr>
					</table>

				</div>
			</div>
		<?php
		}
	}

	//para mostrar la quincena por pagar
	public function buscar_quincena_por_pagar($con)
	{

		$status = '1';
		$id_empresa = $_SESSION['id_empresa'];
		$q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['por_buscar_quincena'], ENT_QUOTES)));
		$ordenado = "qui.mes_ano"; //mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		$por = "desc"; //mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		$aColumns = array('emp.nombres_apellidos', 'emp.documento', 'qui.mes_ano'); //Columnas de busqueda

		$sTable = "detalle_quincena as det INNER JOIN empleados as emp ON emp.id=det.id_empleado 
			INNER JOIN quincenas as qui ON qui.id=det.id_quincena ";

		$sWhere = "WHERE emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.arecibir - det.abonos > 0 and qui.status =1 ";
		if ($_GET['por_buscar_quincena'] != "") {
			$sWhere = "WHERE (emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.arecibir - det.abonos > 0  and qui.status =1 AND ";

			for ($i = 0; $i < count($aColumns); $i++) {
				$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.arecibir - det.abonos > 0  and qui.status =1 OR ";
			}
			$sWhere = substr_replace($sWhere, "AND emp.status = '" . $status . "' and emp.id_empresa='" . $id_empresa . "' and det.arecibir - det.abonos > 0  and qui.status =1 ", -3);
			$sWhere .= ')';
		}
		$sWhere .= "  order by $ordenado $por";

		include("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
		$per_page = 5; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row = mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows / $per_page);
		$reload = '';
		//main query to fetch the data
		$sql = "SELECT qui.mes_ano as mes_ano, emp.nombres_apellidos as nombres_apellidos, emp.id as id_empleado,
			det.arecibir - det.abonos as a_recibir, det.id as id_detalle FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows > 0) {
		?>

			<div class="panel panel-info" style="height: 350px;overflow-y: auto;">
				<div class="table-responsive">
					<table class="table">
						<tr class="info">
							<th style="padding: 2px;">Período</th>
							<th style="padding: 2px;">Apellidos y nombres</th>
							<th style="padding: 2px;">Deuda</span></th>
							<th style="padding: 2px;" class='text-center'>A pagar</span></th>
							<th class='text-center' style="width: 36px; padding: 2px;">Agregar</th>
						</tr>
						<?php
						while ($row = mysqli_fetch_array($query)) {
							$mes_ano = $row['mes_ano'];
							$empleado = $row["nombres_apellidos"];
							$a_recibir = $row["a_recibir"];
							$id_detalle = $row["id_detalle"];
							$id_empleado = $row["id_empleado"];

							$sql_abono_tmp = mysqli_query($con, "SELECT round(SUM(valor),2) as abonos_tmp FROM ingresos_egresos_tmp WHERE id_documento = concat('QUINCENA', '" . $id_detalle . "') and tipo_transaccion ='CCXQPP' group by id_documento");
							$row_abonos = mysqli_fetch_array($sql_abono_tmp);
							$total_abonos_tmp = isset($row_abonos['abonos_tmp']) ? $row_abonos['abonos_tmp'] : 0;

							//$sql_egresos = mysqli_query($con, "SELECT round(SUM(valor_ing_egr),2) as egresos FROM detalle_ingresos_egresos WHERE tipo_documento='EGRESO' and codigo_documento_cv = concat('ROL_PAGOS','".$id_detalle."') and tipo_ing_egr ='CCXRPP' group by codigo_documento_cv");
							//$row_egresos = mysqli_fetch_array($sql_egresos);
							$total_egresos = number_format($a_recibir - $total_abonos_tmp, 2, '.', '');

							if ($total_egresos > 0) {
						?>
								<input type="hidden" value="<?php echo $total_egresos; ?>" id="total_quincena_por_pagar<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $empleado; ?>" id="nombre_empleado<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $id_empleado; ?>" id="id_empleado<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $mes_ano; ?>" id="mes_ano<?php echo $id_detalle; ?>">
								<input type="hidden" value="<?php echo $page; ?>" id="pagina">
								<tr>
									<td style="padding: 2px;"><?php echo $mes_ano; ?></td>
									<td style="padding: 2px;"><?php echo $empleado; ?></td>
									<td style="padding: 2px;"><?php echo $total_egresos; ?></td>
									<td style="padding: 2px; width:auto;" class="col-sm-1"><input type="text" style="text-align:right;" id="a_pagar_quincena<?php echo $id_detalle; ?>" class="form-control col-sm-2" value="<?php echo $total_egresos; ?>"></td>
									<td style="padding: 2px;" class='text-center'><a href="#" onclick="agrega_por_pagar_quincena('<?php echo $id_detalle; ?>')" class="btn btn-info"><i class="glyphicon glyphicon-plus"></i></a></td>
								</tr>
						<?php
							}
						}
						?>
						<tr>
							<td colspan="5"><span class="pull-right">
									<?php
									echo paginate($reload, $page, $total_pages, $adjacents);
									?>
								</span></td>
						</tr>
					</table>

				</div>
			</div>
<?php
		}
	}
} //fin de la clase egresos
?>