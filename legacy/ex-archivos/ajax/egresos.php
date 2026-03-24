<?php
//include("../ajax/detalle_documento.php");
include("../conexiones/conectalogin.php");
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
//include("../validadores/generador_codigo_unico.php");
include("../clases/asientos_contables.php");
$egresos = new egresos();
if (!isset($_SESSION['ruc_empresa'])) {
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
}
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
$con = conenta_login();


//para eliminar un iten de formas de pago del egreso temporal 
if ($action == 'eliminar_pago_egreso') {
	$intid = $_GET['id_fp_tmp'];
	$arrData = $_SESSION['arrayFormaPagoEgreso'];
	for ($i = 0; $i < count($arrData); $i++) {
		if ($arrData[$i]['id'] == $intid) {
			unset($arrData[$i]);
			echo "<script>
            $.notify('Eliminado','error');
            </script>";
		}
	}
	sort($arrData); //para reordenar el array
	$_SESSION['arrayFormaPagoEgreso'] = $arrData;
}

//para agregar la forma de pago al pagos tmp
if ($action == 'forma_de_pago_egreso') {
	$forma_pago_egreso = $_POST['forma_pago_egreso'];
	$valor_pago_egreso = $_POST['valor_pago_egreso'];
	$numero_cheque_egreso = $_POST['numero_cheque_egreso'];
	$origen = $_POST['origen'];
	$tipo = $_POST['tipo'];
	$fecha_cobro_egreso = date('Y-m-d H:i:s', strtotime($_POST['fecha_cobro_egreso']));
	//para ver si el cheque ya esta agregado al tmp o egresos anteriores

	//para ver si has cheques en los pagos ya registrados con anterioridad
	if ($numero_cheque_egreso > 0) {
		$busca_cheque_registrado = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE tipo_documento='EGRESO' and id_cuenta='" . $forma_pago_egreso . "' and cheque='" . $numero_cheque_egreso . "'");
		$cheque_registrado = mysqli_num_rows($busca_cheque_registrado);
		if ($cheque_registrado > 0) {
			echo "
			<script src='../js/notify.js'></script>
			<script>
			$.notify('El número de cheque ya esta registrado.','error');
			</script>";
		} else {
			//para guardar en PAGOS temporal
			formas_pago_egreso($forma_pago_egreso, $valor_pago_egreso, $tipo, $origen, $numero_cheque_egreso, $fecha_cobro_egreso);
		}
	} else {
		formas_pago_egreso($forma_pago_egreso, $valor_pago_egreso, $tipo, $origen, $numero_cheque_egreso, $fecha_cobro_egreso);
	}
	echo $egresos->detalle_pagos_agregados($con, $ruc_empresa);
}

function formas_pago_egreso($forma_pago, $valor_pago, $tipo, $origen, $cheque, $fecha_cheque)
{
	$arrayFormaPago = array();
	$arrayDatos = array('id' => rand(5, 500), 'id_forma' => $forma_pago, 'tipo' => $tipo, 'valor' => $valor_pago, 'origen' => $origen, 'cheque' => $cheque, 'fecha_cheque' => $fecha_cheque);
	if (isset($_SESSION['arrayFormaPagoEgreso'])) {
		$on = true;
		$arrayFormaPago = $_SESSION['arrayFormaPagoEgreso'];
		for ($pr = 0; $pr < count($arrayFormaPago); $pr++) {

			if ($tipo == "C") {
				if ($arrayFormaPago[$pr]['id_forma'] == $forma_pago && $origen == $arrayFormaPago[$pr]['origen'] && $cheque == $arrayFormaPago[$pr]['cheque']) {
					$arrayFormaPago[$pr]['valor'] += $valor_pago;
					$on = false;
				}
			} else {

				if ($arrayFormaPago[$pr]['id_forma'] == $forma_pago && $origen == $arrayFormaPago[$pr]['origen']) {
					$arrayFormaPago[$pr]['valor'] += $valor_pago;
					$on = false;
				}
			}
		}
		if ($on) {
			array_push($arrayFormaPago, $arrayDatos);
		}
		$_SESSION['arrayFormaPagoEgreso'] = $arrayFormaPago;
	} else {
		array_push($arrayFormaPago, $arrayDatos);
		$_SESSION['arrayFormaPagoEgreso'] = $arrayFormaPago;
	}
	echo "
			<script src='../js/notify.js'></script>
			<script>
				$.notify('Agregado','success');
				</script>";
}


//para sueldos por pagar
if ($action == 'agrega_sueldos_por_pagar') {
	if (isset($_POST['id'])) {
		$id_rol = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_rol) and !empty($a_pagar)) {
		$nombre_empleado = $_POST['nombre_empleado'];
		$numero_documento = $_POST['mes_ano'] . " " . $nombre_empleado;
		$codigo_documento = "ROL_PAGOS" . $_POST['id'];
		$nombre_comprobante = "Rol de pagos";

		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'EGRESO','" . $nombre_empleado . "','" . $nombre_comprobante . " " . $numero_documento . "','" . $a_pagar . "','CCXRPP','" . $id_usuario . "','" . $codigo_documento . "')");
	}
}

//para quincenas por pagar
if ($action == 'agrega_quincena_por_pagar') {
	if (isset($_POST['id'])) {
		$id_rol = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_rol) and !empty($a_pagar)) {
		$nombre_empleado = $_POST['nombre_empleado'];
		$numero_documento = $_POST['mes_ano'] . " " . $nombre_empleado;
		$codigo_documento = "QUINCENA" . $_POST['id'];
		$nombre_comprobante = "Quincena";

		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'EGRESO','" . $nombre_empleado . "','" . $nombre_comprobante . " " . $numero_documento . "','" . $a_pagar . "','CCXQPP','" . $id_usuario . "','" . $codigo_documento . "')");
	}
}

//para otros diferentes egresos
if ($action == 'agrega_diferentes_egresos') {
	$tipo_egreso = $_GET["tipo_egreso"];
	$valor_egreso = $_GET["valor_egreso"];
	$nombre_beneficiario = $_GET["nombre_beneficiario"];
	$detalle_egreso = $_GET["detalle_egreso"];
	$agregar_egreso = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null, 'EGRESO', '" . $nombre_beneficiario . "', '" . $detalle_egreso . "', '" . $valor_egreso . "', '" . $tipo_egreso . "', '" . $id_usuario . "','0')");
}

//para eliminar un iten del egreso temporal
if ($action == 'eliminar_item_egreso') {
	if (isset($_GET['id'])) {
		$id_tmp = intval($_GET['id']);
		$delete = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_tmp='" . $id_tmp . "'");
		echo $egresos->detalle_documentos_agregados($con, $id_usuario);
	}
}

//cada vez que inicia el formulario
if ($action == 'nuevo_egreso') {
	unset($_SESSION['arrayFormaPagoEgreso']);
	$delete_compras_tmp = mysqli_query($con, "DELETE FROM saldos_compras_tmp WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$delete_ingreso_tmp_cero = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario = '0'");
	$delete_ingreso_tmp = mysqli_query($con, "DELETE FROM ingresos_egresos_tmp WHERE id_usuario = '" . $id_usuario . "'");
	$delete_diario_tmp = mysqli_query($con, "DELETE FROM detalle_diario_tmp WHERE id_usuario='" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' ");
}

//para proveedores
if ($action == 'agrega_facturas_compras') {
	if (isset($_POST['id'])) {
		$id_compra = $_POST['id'];
	}
	if (isset($_POST['a_pagar'])) {
		$a_pagar = $_POST['a_pagar'];
	}

	if (!empty($id_compra) and !empty($a_pagar)) {
		//para buscar datos de la compra a pagar
		$sql_compra = mysqli_query($con, "SELECT * from saldos_compras_tmp as sc, proveedores as pro WHERE mid(sc.ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and sc.id_saldo = '" . $id_compra . "' and sc.id_proveedor = pro.id_proveedor");
		$row_compra = mysqli_fetch_array($sql_compra);
		$nombre_proveedor = $row_compra['razon_social'];
		$numero_documento = $row_compra['numero_documento'] . " " . $nombre_proveedor;
		$codigo_documento = $row_compra['codigo_documento'];
		$id_comprobante = $row_compra["id_comprobante"];
		//tipo de documento
		$busca_tipo_documento = "SELECT *  FROM comprobantes_autorizados WHERE id_comprobante = '" . $id_comprobante . "'";
		$result_tipo_documento = $con->query($busca_tipo_documento);
		$row_tipo_comprobante = mysqli_fetch_array($result_tipo_documento);
		$nombre_comprobante = $row_tipo_comprobante['comprobante'];

		if ($id_comprobante == "04") {
			$a_pagar = $a_pagar * -1;
		}
		//para guardar en el egreso temporal
		$insert_tmp = mysqli_query($con, "INSERT INTO ingresos_egresos_tmp VALUES (null,'EGRESO','" . $nombre_proveedor . "','" . $nombre_comprobante . " " . $numero_documento . "','" . $a_pagar . "','CCXPP','" . $id_usuario . "','" . $codigo_documento . "')");
		echo $egresos->detalle_documentos_agregados($con, $id_usuario);
	}
}


//para buscar informacion para hacer el egreso
if ($action == 'cuentas_por_pagar_proveedores') {
	echo $egresos->saldo_por_pagar_nuevo_egreso($con);
	echo $egresos->actualiza_egreso_tmp($con);
	echo $egresos->buscar_compras_por_pagar($con);
}

if ($action == 'buscar_por_pagar') {
	echo $egresos->actualiza_egreso_tmp($con);
	echo $egresos->buscar_compras_por_pagar($con);
}

//para guardar y editar
if ($action == 'guardar_egreso') {
	$id_egreso = $_POST['id_egreso'];
	$fecha_egreso = date('Y-m-d', strtotime($_POST['fecha_egreso']));
	$id_proveedor = mysqli_real_escape_string($con, (strip_tags($_POST["id_proveedor"], ENT_QUOTES)));
	$nombre_beneficiario = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["nombre_beneficiario"]), ENT_QUOTES)));
	$total_egreso = mysqli_real_escape_string($con, (strip_tags($_POST["total_egreso"], ENT_QUOTES)));
	$pagos_egreso = mysqli_real_escape_string($con, (strip_tags($_POST["total_pagos_egreso"], ENT_QUOTES)));
	$detalle_adicional = mysqli_real_escape_string($con, (strip_tags(strClean($_POST["detalle_adicional"]), ENT_QUOTES)));
	$id_proyecto = mysqli_real_escape_string($con, (strip_tags($_POST["proyecto"], ENT_QUOTES)));
	$codigo_documento = codigo_unico(20);
	$fecha_registro = date("Y-m-d H:i:s");

	//para ver si hay documentos agregados al egreso
	$sql_ingresos_egresos_tmp = mysqli_query($con, "SELECT * from ingresos_egresos_tmp where id_usuario = '" . $id_usuario . "' and tipo_documento='EGRESO'");
	$count_documentos_agregados = mysqli_num_rows($sql_ingresos_egresos_tmp);

	//para ver si hay informacion en el asiento contable											
	$sql_diario_temporal = mysqli_query($con, "SELECT count(id_cuenta) as id_cuenta, sum(debe) as debe, sum(haber) as haber from detalle_diario_tmp where id_usuario = '" . $id_usuario . "' and mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'");
	$row_asiento_contable = mysqli_fetch_array($sql_diario_temporal);
	$debe = $row_asiento_contable['debe'];
	$haber = $row_asiento_contable['haber'];
	$count_asientos = $row_asiento_contable['id_cuenta'];

	//para buscar el numero de egreso que continua
	$busca_siguiente_egreso = mysqli_query($con, "SELECT max(numero_ing_egr) as numero FROM ingresos_egresos WHERE ruc_empresa = '" . $ruc_empresa . "' and tipo_ing_egr = 'EGRESO'");
	$row_siguiente_egreso = mysqli_fetch_array($busca_siguiente_egreso);
	$numero_egreso = $row_siguiente_egreso['numero'] + 1;


	if (empty($fecha_egreso)) {
		echo "<script>
            $.notify('Ingrese fecha de emisión','error');
            </script>";
	} else if (empty($id_proveedor)) {
		echo "<script>
        $.notify('Ingrese un beneficiario','error');
        </script>";
	} else if (empty($total_egreso)) {
		echo "<script>
        $.notify('Agregue documentos o detalles en el egreso','error');
        </script>";
	} else if (empty($pagos_egreso)) {
		echo "<script>
        $.notify('Agregue documentos o detalles en el egreso','error');
        </script>";
	} else if ($pagos_egreso != $total_egreso) {
		echo "<script>
        $.notify('El total del egreso no es igual al total de formas de pago','error');
        </script>";
	} else if ($count_documentos_agregados == 0) {
		echo "<script>
        $.notify('No hay documentos o detalles agregados al egreso','error');
        </script>";
	} else if ($count_asientos > 0 && ($debe != $haber)) {
		echo "<script>
        $.notify('El asiento contable no cumple con partida doble','error');
        </script>";
	} else {
		//para guardar y editar
		if (empty($id_egreso)) {
			$query_encabezado_egreso = mysqli_query($con, "INSERT INTO ingresos_egresos VALUES (null, '" . $ruc_empresa . "',
			'" . $fecha_egreso . "','" . $nombre_beneficiario . "','" . $numero_egreso . "','" . $total_egreso . "','EGRESO','" .
				$id_usuario . "','" . $fecha_registro . "','0','" . $codigo_documento . "','" . $detalle_adicional . "'
				,'OK','" . $id_proveedor . "')");
			$guarda_egreso = mysqli_query($con, "INSERT INTO ingresos_egresos (ruc_empresa,
                                                                        fecha_ing_egr,
                                                                        nombre_ing_egr,
                                                                        numero_ing_egr,
                                                                        valor_ing_egr,
                                                                        tipo_ing_egr,
                                                                        id_usuario,
                                                                        codigo_documento,
                                                                        detalle_adicional,
                                                                        id_cli_pro ,
                                                                        id_proyecto)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $fecha_egreso . "',
                                                                                    '" . $nombre_beneficiario . "',
                                                                                    '" . $numero_egreso . "',
                                                                                    '" . $total_egreso . "',
                                                                                    'EGRESO',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $codigo_documento . "',
                                                                                    '" . $detalle_adicional . "',
                                                                                    '" . $id_proveedor . "',
                                                                                    '" . $id_proyecto . "')");
			$id_ing_egr = mysqli_insert_id($con);
			//guardar asiento contable manual
			if ($count_asientos > 0 && ($debe ==  $haber)) {
				$asiento_contable = new asientos_contables();
				$guarda_asiento = $asiento_contable->guarda_asiento($con, $fecha_egreso, 'EGRESO N.' . $numero_egreso . " " . $nombre_beneficiario, 'EGRESOS', $id_ing_egr, $ruc_empresa, $id_usuario, $id_proveedor);
			}
			//para generar el asiento contable automatico
			$contabilizacion->documentosEgresos($con, $ruc_empresa, $fecha_egreso, $fecha_egreso);
			$guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'egresos');

			//guardar detalle del egreso
			guarda_detalle_egreso($con, $ruc_empresa, $numero_egreso, $codigo_documento, $id_usuario);
			//guardar formas de pago
			guardar_formas_pago_egreso($con, $ruc_empresa, $fecha_egreso, $numero_egreso, $codigo_documento);

			echo "<script>
			$.notify('Egreso guardado con éxito','success');
			setTimeout(function () {location.reload()}, 60 * 20); 
			</script>";
		} else {
			//editar egreso
		}
	}
}

//para guardar los pagos del egreso
function guardar_formas_pago_egreso($con, $ruc_empresa, $fecha_egreso, $numero_egreso, $codigo_documento)
{
	if (isset($_SESSION['arrayFormaPagoEgreso'])) {
		foreach ($_SESSION['arrayFormaPagoEgreso'] as $detalle) {
			$origen = $detalle['origen'];
			if ($origen == '1') {
				$codigo_forma_pago = $detalle['id_forma'];
				$id_cuenta = '0';
			} else {
				$id_cuenta = $detalle['id_forma'];
				$codigo_forma_pago = '0';
			}
			$valor_pago = number_format($detalle['valor'], 2, '.', '');
			$tipo = $detalle['tipo'];
			$cheque = $tipo == 'C' ? $detalle['cheque'] : 0;
			if ($tipo == 'C') {
				$fecha_cheque = $detalle['fecha_cheque'];
			} else {
				$fecha_cheque = $fecha_egreso;
			}
			$estado_pago = $cheque > 0 ? "ENTREGAR" : "PAGADO";

			$detalle_formas_pago = mysqli_query($con, "INSERT INTO formas_pagos_ing_egr (ruc_empresa,
																						tipo_documento, 
																						numero_ing_egr, 
																						valor_forma_pago, 
																						codigo_forma_pago, 
																						id_cuenta, 
																						detalle_pago, 
																						codigo_documento, 
																						fecha_emision, 
																						fecha_entrega,
																						fecha_pago, 
																						estado_pago,
																						cheque)
																						VALUES ('" . $ruc_empresa . "',
																						'EGRESO', 
																						'" . $numero_egreso . "', 
																						'" . $valor_pago . "',
																						'" . $codigo_forma_pago . "', 
																						'" . $id_cuenta . "', 
																						'" . $tipo . "', 
																						'" . $codigo_documento . "', 
																						'" . $fecha_egreso . "', 
																						'" . $fecha_egreso . "', 
																						'" . $fecha_cheque . "',
																						'" . $estado_pago . "',
																						'" . $cheque . "')");
			unset($_SESSION['arrayFormaPagoEgreso']);
			return true;
		}
	} else {
		return false;
	}
}

//funcion para guardar detalle de egreso
function guarda_detalle_egreso($con, $ruc_empresa, $numero_egreso, $codigo_documento, $id_usuario)
{
	//para guardar el detalle del egreso							
	$detalle_egreso = mysqli_query($con, "INSERT INTO detalle_ingresos_egresos (ruc_empresa, beneficiario_cliente, valor_ing_egr, detalle_ing_egr, numero_ing_egr, tipo_ing_egr, tipo_documento, codigo_documento_cv, estado, codigo_documento)
	SELECT '" . $ruc_empresa . "', beneficiario_cliente, valor, detalle, '" . $numero_egreso . "', tipo_transaccion, 'EGRESO', id_documento,'OK', '" . $codigo_documento . "'  
	FROM ingresos_egresos_tmp where id_usuario = '" . $id_usuario . "' and tipo_documento='EGRESO'");
}

if ($action == 'mostrar_formas_de_pago_egreso') {
	$mostrar_formas_de_pago = new egresos();
	echo $mostrar_formas_de_pago->formas_de_pago_egreso($con, $ruc_empresa, $id_usuario);
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

	//para mostrar en las facturas de compras por pagar al hacer el egreso
	public function buscar_compras_por_pagar($con)
	{
		$ruc_empresa = $_SESSION['ruc_empresa'];
		if (isset($ruc_empresa)) {
			$q = $_REQUEST['documento_proveedor_por_buscar'];
			$aColumns = array('numero_documento', 'razon_social', 'nombre_comercial', 'fecha_compra'); //Columnas de busqueda
			$sTable = "saldos_compras_tmp";
			$sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' ";
			if ($_GET['documento_proveedor_por_buscar'] != "") {
				$sWhere = "WHERE (ruc_empresa = '" . $ruc_empresa . "' AND ";

				for ($i = 0; $i < count($aColumns); $i++) {
					$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" . $ruc_empresa . "' OR ";
				}
				$sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' ", -3);
				$sWhere .= ')';
			}
			$sWhere .= " order by fecha_compra asc, razon_social asc";

			include("../ajax/pagination.php"); //include pagination file
			//pagination variables
			$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
			$per_page = 10; //how much records you want to show
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
				<div class="panel panel-info" style="height: 300px;overflow-y: auto;">
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
										<td style="padding: 2px; width:auto;" class="col-sm-2"><input type="number" id="a_pagar<?php echo $id_encabezado_compra; ?>" class="form-control col-sm-2 text-right" value="<?php echo $valor_compra; ?>"></td>
										<td style="padding: 2px;" class='text-center'><a onclick="agrega_por_pagar_proveedor('<?php echo $id_encabezado_compra; ?>')" class="btn btn-info btn-sm"><i class="glyphicon glyphicon-plus"></i></a></td>
									</tr>
							<?php
								}
							}
							?>
							<!-- <tr>
									<td colspan="7"><span class="pull-right">
											<?php
											//echo paginate($reload, $page, $total_pages, $adjacents);
											?>
										</span></td>
								</tr> -->
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
	public function formas_de_pago_egreso($con, $ruc_empresa, $id_usuario)
	{

		//consulta el valor a pagar y el valor de pagos agregados y me saca la diferencia que esta por agregar a pagos
		$sql_agregados = mysqli_query($con, "SELECT round(sum(valor),2) as total_documentos from ingresos_egresos_tmp where id_usuario = '" . $id_usuario . "' and tipo_documento='EGRESO'");
		$row_agregados = mysqli_fetch_array($sql_agregados);
		$suma_agregados = $row_agregados["total_documentos"];
		$sumaPagosAgregados = 0;
		if (isset($_SESSION['arrayFormaPagoEgreso'])) {
			foreach ($_SESSION['arrayFormaPagoEgreso'] as $formaPago) {
				$sumaPagosAgregados += number_format($formaPago['valor'], 2, '.', '');
			}
		}
		$total_pagar = number_format($suma_agregados - $sumaPagosAgregados, 2, '.', '');

		?>
		<div class="panel panel-info">
			<div class="table-responsive">
				<table class="table table-bordered">
					<tr class="info">
						<td style="padding: 2px;">Opciones de pago</th>
						<td style="padding: 2px;" class="text-center">Tipo</td>
						<td style="padding: 2px;" class="text-center">Valor</td>
						<td style="padding: 2px;" class="text-center"># Cheque</td>
						<td style="padding: 2px;" class="text-center">Fecha cobro</td>
						<td style="padding: 2px;" class="text-center">Agregar</td>
					</tr>
					<tr>
						<td style="padding: 2px;" class="col-sm-4">
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
								<!-- <option value="0">N/A</option> -->
								<option value="0" selected>Seleccione</option>
								<option value="C">Cheque</option>
								<option value="D">Débito</option>
								<option value="T">Transferencia</option>
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<input type="number" class="form-control input-sm" style="text-align:right;" title="Ingrese valor" id="valor_pago_egreso" placeholder="Valor" value="<?php echo $total_pagar; ?>">
							</select>
						</td>
						<td style="padding: 2px;" class="col-sm-1">
							<input type="number" class="form-control input-sm" pattern="[0-9]{3}-[0-9]{3}-[0-9]{9}" style="text-align:right;" title="Ingrese número de cheque" id="numero_cheque_egreso" placeholder="Cheque">
						</td>
						<td style="padding: 2px;" class="col-sm-2">
							<div class="pull-right">
								<input type="text" class="form-control input-sm text-center" id="fecha_cobro_egreso" value="<?php echo date("d-m-Y"); ?>">
							</div>
						</td>
						<td style="padding: 2px;" class="col-sm-1">
							<div class="text-center">
								<button type="button" class="btn btn-info btn-sm" title="Agregar forma de pago" onclick="agrega_pagos_egreso();"><span class="glyphicon glyphicon-plus"></span></button>
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
				document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
				document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
				document.getElementById("tipo").style.visibility = "hidden";
				//var total_egreso = $("#suma_egreso").val();
				//$("#valor_pago_egreso").val(total_egreso);
			});


			function cambio_opciones(forma_pago, tipo) {
				var origen = forma_pago.substring(0, 1);
				if (origen == "1") {
					$("#tipo").val("0");
					$("#numero_cheque_egreso").val("");
					document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
					document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
					document.getElementById("tipo").style.visibility = "hidden";
					document.getElementById('valor_pago_egreso').focus();
				} else {
					document.getElementById("tipo").style.visibility = "";
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
								document.getElementById("tipo").style.visibility = "";
								$("#numero_cheque_egreso").val(ultimo_cheque);
								document.getElementById('numero_cheque_egreso').focus();
							}

							if (tipo_cuenta_bancaria == "1") {
								document.getElementById("numero_cheque_egreso").style.visibility = "hidden";
								document.getElementById("fecha_cobro_egreso").style.visibility = "hidden";
								document.getElementById("tipo").style.visibility = "";
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


	public function detalle_documentos_agregados($con, $id_usuario)
	{
		?>
		<div style="padding: 2px; margin-bottom: 5px;" class="alert alert-info text-center" role="alert">
			<b>Detalle de documentos agregados al egreso</b>
		</div>
		<div class="table-responsive">
			<table class="table">
				<tr class="info">
					<td style="padding: 2px;">Nombre</td>
					<td style="padding: 2px;">Detalle</td>
					<td style="padding: 2px;" class='text-center'>Valor</td>
					<td style="padding: 2px;">Tipo</td>
					<td style="padding: 2px;" class="text-center">Eliminar</td>
				</tr>
				<?php
				// PARA MOSTRAR LOS ITEMS DEL EGRESO
				$total_egreso = 0;
				$sql = mysqli_query($con, "SELECT * from ingresos_egresos_tmp where id_usuario = '" . $id_usuario . "' and tipo_documento='EGRESO'");
				while ($row = mysqli_fetch_array($sql)) {
					$id_tmp = $row["id_tmp"];
					$beneficiario_cliente = strtolower($row['beneficiario_cliente']);
					$detalle = ucfirst($row['detalle']);
					$valor = number_format($row['valor'], 2, '.', '');
					$tipo_transaccion = $row['tipo_transaccion'];

					if (!is_numeric($tipo_transaccion)) {
						$tipo_asiento = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo='" . $tipo_transaccion . "' ");
						$row_asiento = mysqli_fetch_assoc($tipo_asiento);
						$transaccion = $row_asiento['tipo_asiento'];
					} else {
						$tipo_pago = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_transaccion . "' ");
						$row_tipo_pago = mysqli_fetch_assoc($tipo_pago);
						$transaccion = $row_tipo_pago['descripcion'];
					}
					$total_egreso += $row['valor'];
				?>
					<tr>
						<td style="padding: 2px;"><?php echo strtoupper($beneficiario_cliente); ?></td>
						<td style="padding: 2px;"><?php echo $detalle; ?></td>
						<td style="padding: 2px;" class='text-right'><?php echo $valor; ?></td>
						<td style="padding: 2px;"><?php echo strtoupper($transaccion); ?></td>
						<td style="padding: 2px;" class="text-center">
							<a href="#" class='btn btn-danger btn-xs' onclick="eliminar_item_egreso('<?php echo $id_tmp; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
						</td>
					</tr>
				<?php
				}
				?>
				<tr>
					<td style="padding: 2px;" class="info" colspan="2"><b>Total</b></td>
					<td style="padding: 2px;" class="info text-right"><b><?php echo number_format($total_egreso, 2, '.', ''); ?></b></td>
					<td style="padding: 2px;" class="info" colspan="2"></td>
				</tr>
			</table>
		</div>
	<?php
	}

	public function detalle_pagos_agregados($con, $ruc_empresa)
	{
	?>
		<div style="padding: 2px; margin-bottom: 5px;" class="alert alert-info text-center" role="alert">
			<b>Detalle de formas de pago agregados al egreso</b>
		</div>
		<div class="table-responsive">
			<table class="table table-bordered">
				<tr class="info">
					<td style="padding: 2px;">Forma</td>
					<td style="padding: 2px;" class="text-center">Tipo</td>
					<td style="padding: 2px;" class="text-center">Valor</td>
					<td style="padding: 2px;" class="text-center">Cheque</td>
					<td style="padding: 2px;" class="text-center">Fecha</td>
					<td style="padding: 2px;" class="text-center">Eliminar</td>
				</tr>
				<?php
				$valor_total_pago = 0;
				if (isset($_SESSION['arrayFormaPagoEgreso'])) {
					foreach ($_SESSION['arrayFormaPagoEgreso'] as $detalle) {
						$id = $detalle['id'];
						$id_forma = $detalle['id_forma'];
						$tipo = $detalle['tipo'];
						switch ($tipo) {
							case "0":
								$tipo = 'N/A';
								break;
							case "C":
								$tipo = 'Cheque';
								break;
							case "D":
								$tipo = 'Débito';
								break;
							case "T":
								$tipo = 'Transferencia';
								break;
						}
						$origen = $detalle['origen'];
						$valor_pago = number_format($detalle['valor'], 2, '.', '');
						$valor_total_pago += $valor_pago;

						if ($origen == 1) {
							$query_cobros_pagos = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id='" . $id_forma . "' and ruc_empresa='" . $ruc_empresa . "' ");
							$row_cobros_pagos = mysqli_fetch_array($query_cobros_pagos);
							$forma_pago = strtoupper($row_cobros_pagos['descripcion']);
						} else {

							$cuentas_bancarias = mysqli_query($con, "SELECT concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_forma . "'");
							$row_cuentas_bancarias = mysqli_fetch_array($cuentas_bancarias);
							$forma_pago = strtoupper($row_cuentas_bancarias['cuenta_bancaria']);
						}
				?>
						<tr>
							<td style="padding: 2px;" class='col-xs-2'><?php echo $forma_pago; ?></td>
							<td style="padding: 2px;" class='col-xs-2'><?php echo $tipo; ?></td>
							<td style="padding: 2px;" class='col-xs-1 text-right'><?php echo $valor_pago; ?></td>
							<td style="padding: 2px;" class='col-xs-1 text-center'><?php echo $detalle['cheque'] > 0 ? $detalle['cheque'] : ""; ?></td>
							<td style="padding: 2px;" class='col-xs-2 text-center'><?php echo $detalle['fecha_cheque'] != 0 ? date('d-m-Y', strtotime($detalle['fecha_cheque'])) : ""; ?></td>
							<td style="padding: 2px;" class='col-xs-1 text-center'>
								<a href="#" class='btn btn-danger btn-xs' onclick="eliminar_fila_pago_egreso('<?php echo $id; ?>')" title="Eliminar item"><i class="glyphicon glyphicon-trash"></i></a>
							</td>
						</tr>
				<?php
					}
				}
				?>
				<tr>
					<td style="padding: 2px;" class="info" colspan="2"><b>Total</b></td>
					<td style="padding: 2px;" class="info text-right"><b><?php echo number_format($valor_total_pago, 2, '.', ''); ?></b></td>
					<td style="padding: 2px;" class="info" colspan="3"></td>
				</tr>
			</table>
		</div>

	<?php
	}

	// desde aqui asiento contable
	public function detalle_asiento_contable_agregados()
	{
	?>
		<div class="table-responsive">
			<input type="hidden" name="codigo_unico" id="codigo_unico">
			<input type="hidden" name="id_cuenta" id="id_cuenta">
			<input type="hidden" name="cod_cuenta" id="cod_cuenta">
			<div class="panel panel-info" style="margin-bottom: 5px; margin-top: -0px;">
				<table class="table table-bordered">
					<tr class="info">
						<th style="padding: 2px;">Cuenta</th>
						<th class="text-center" style="padding: 2px;">Debe</th>
						<th class="text-center" style="padding: 2px;">Haber</th>
						<th style="padding: 2px;">Detalle</th>
						<th class="text-center" style="padding: 2px;">Agregar</th>
					</tr>
					<td class='col-xs-4'>
						<input type="text" class="form-control input-sm" name="cuenta_diario" id="cuenta_diario" onkeyup='buscar_cuentas();' autocomplete="off">
					</td>
					<td class='col-xs-2'><input type="text" class="form-control input-sm" name="debe_diario" id="debe_diario"></td>
					<td class='col-xs-2'><input type="text" class="form-control input-sm" name="haber_cuenta" id="haber_cuenta"></td>
					<td class='col-xs-4'><input type="text" class="form-control input-sm" name="det_cuenta" id="det_cuenta"></td>
					<td class='col-xs-1 text-center'><button type="button" class="btn btn-info btn-xs" title="Agregar detalle de diario" onclick="agregar_item_diario()"><span class="glyphicon glyphicon-plus"></span></button> </td>
				</table>
			</div>
			<div id="muestra_detalle_diario"></div><!-- Carga gif animado -->
			<div class="outer_divdet"></div><!-- Datos ajax Final -->
		</div>
<?php
	}
} //fin de la clase egresos
?>