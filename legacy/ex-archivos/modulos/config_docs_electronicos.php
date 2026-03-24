<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<html lang="es">

	<head>
		<meta charset="utf-8">
		<title>Datos Empresa</title>
		<?php include("../paginas/menu_de_empresas.php");
		$con = conenta_login();
		$busca_info_sucursales = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "'");
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="row justify-content-center">
				<div class="col-md-8 col-sm-offset-2">
					<div class="panel panel-info">
						<div class="panel-heading">
							<h4><i class='glyphicon glyphicon-pencil'></i> Configuraciones de la empresa</h4>
						</div>
						<div class="panel-body">
							<div class="panel-group" id="accordion">
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapseDatosEmpresa" onclick="info_general();"><span class="caret"></span> Información general de la empresa</a>
									<div id="collapseDatosEmpresa" class="panel-collapse collapse">
										<form class="form-horizontal" method="post" id="editar_empresa" name="editar_empresa" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b>Razón Social</b></span>
															<input type="hidden" name="id_empresa" id="id_empresa">
															<input type="text" class="form-control input-sm" name="razon_social" id="razon_social">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b>Nombre comercial</b></span>
															<input type="text" class="form-control input-sm" name="nombre_comercial" id="nombre_comercial">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>RUC</b></span>
															<input type="text" class="form-control input-sm" name="ruc" id="ruc" readonly>
														</div>
														<input type="hidden" name="ruc_empresa" value="<?php echo $ruc_empresa; ?>">
													</div>
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>Establecimiento</b></span>
															<input type="text" class="form-control input-sm" name="establecimiento" id="establecimiento" readonly>
														</div>
													</div>
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>Teléfono</b></span>
															<input type="text" class="form-control input-sm" name="telefono" id="telefono" value="">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b>Dirección</b></span>
															<input type="text" class="form-control input-sm" name="direccion" id="direccion" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Tipo contribuyente</b></span>
															<select class="form-control input-sm" name="tipo_contribuyente" id="tipo_contribuyente" required>
															</select>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Email</b></span>
															<input type="text" class="form-control input-sm" name="mail_empresa" id="mail_empresa">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Representante legal</b></span>
															<input type="text" class="form-control input-sm" name="representante_legal" id="representante_legal">
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>ID Representante legal</b></span>
															<input type="text" class="form-control input-sm" name="id_representante_legal" id="id_representante_legal">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Nombre contador</b></span>
															<input type="text" class="form-control input-sm" name="nombre_contador" id="nombre_contador" value="">
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Ruc contador</b></span>
															<input type="text" class="form-control input-sm" name="ruc_contador" id="ruc_contador" value="">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Provincia</b></span>
															<select class="form-control input-sm" name="provincia" id="provincia" required>
																<option value="">Seleccione una provincia</option>
															</select>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Ciudad</b></span>
															<select class="form-control input-sm" name="ciudad" id="ciudad" required>
															</select>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer">
												<div class="btn-group pull-left">
													<div id="resultados_informacion_general"></div>
												</div>
												<button type="submit" class="btn btn-primary" id="guardar_perfil_empresa">Actualizar</button>
											</div>
										</form>
									</div>
								</div>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapseEmisor" onclick="info_emisor();"><span class="caret"></span> Configuración del emisor electrónico y descargas del SRI</a>
									<div id="collapseEmisor" class="panel-collapse collapse">
										<form class="form-horizontal" method="POST" id="configura_emisor" name="configura_emisor" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número resolución contribuyente Especial</b></span>
															<input type="hidden" name="id_emisor" id="id_emisor">
															<input type="hidden" name="id_ruc_descargas" id="id_ruc_descargas">
															<input type="text" class="form-control input-sm" name="resol_ce" id="resol_ce" placeholder="Resolución" title="Número de resolución de contribuyente especial">
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Agente de retención</b></span>
															<select class="form-control input-sm" name="agente_ret" id="agente_ret">
																<option value="" Selected>No</option>
																<option value="1">Si</option>
															</select>
														</div>
													</div>

												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Tipo Régimen</b></span>
															<select class="form-control input-sm" name="tipo_regimen" id="tipo_regimen">
																<option value="1" Selected>General</option>
																<option value="2">Rimpe emprendedor</option>
																<option value="3">Rimpe negocio popular</option>
															</select>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>SSL Habilitado</b></span>
															<select class="form-control input-sm" name="ssl" id="ssl">
																<option value="true">True</option>
																<option value="false">False</option>
															</select>
														</div>
													</div>

												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Tipo ambiente</b></span>
															<select class="form-control input-sm" name="tipo_ambiente" id="tipo_ambiente">
																<option value="1">Pruebas</option>
																<option value="2" selected>Producción</option>
															</select>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Tipo emisión</b></span>
															<select class="form-control input-sm" name="tipo_emision" id="tipo_emision">
																<option value="1" Selected>Normal</option>
															</select>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>Clave SRI</b></span>
															<input type="password" class="form-control input-sm" id="clave_sri" name="clave_sri" placeholder="Contraseña" title="Contraseña" value="">
															<span class="input-group-btn btn-md"><button class="btn btn-default btn-md" type="button" title="Mostrar contraseña" id="showPasswordSRI"><span class="glyphicon glyphicon-eye-open"></span></button></span>
														</div>
													</div>
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>Estado</b></span>
															<select class="form-control input-sm" name="status_descargas_sri" id="status_descargas_sri">
																<option value="1" Selected>Activo</option>
																<option value="0">Inactivo</option>
															</select>
														</div>
													</div>
													<div class="col-sm-4">
														<div class="input-group">
															<span class="input-group-addon"><b>Descarga</b></span>
															<select class="form-control input-sm" name="periodo_descargas_sri" id="periodo_descargas_sri">
																<option value="1" Selected>Diario</option>
															</select>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer">
												<div class="btn-group pull-left">
													<div id="resultados_ajax_emisor"></div>
												</div>
												<button type="submit" class="btn btn-primary" name="guardar_emisor">Actualizar</button>

											</div>
										</form>
									</div>
								</div>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapseCorreo" onclick="info_correo();"><span class="caret"></span> Configuración del correo emisor</a>
									<div id="collapseCorreo" class="panel-collapse collapse">
										<form class="form-horizontal" method="POST" id="configura_correo" name="configura_correo" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b>Asunto</b></span>
															<input type="hidden" name="id_emisor_correo" id="id_emisor_correo">
															<input type="text" class="form-control input-sm" name="correo_asunto" id="correo_asunto" placeholder="Asunto en el correo" value="">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Host</b></span>
															<input type="text" class="form-control input-sm" name="correo_host" id="correo_host" placeholder="Correo host" value="">
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Puerto</b></span>
															<input type="text" class="form-control input-sm" name="correo_port" id="correo_port" placeholder="Correo port" value="">
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Correo emisor</b></span>
															<input type="email" class="form-control input-sm" name="correo_remitente" id="correo_remitente" placeholder="Correo remitente" value="">
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Contraseña</b></span>
															<input type="password" class="form-control input-sm" id="correo_pass" name="correo_pass" placeholder="Contraseña" title="Contraseña" value="">
															<span class="input-group-btn btn-md"><button class="btn btn-default btn-md" type="button" title="Mostrar contraseña" id="showPasswordCorreo"><span class="glyphicon glyphicon-eye-open"></span></button></span>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer">
												<div class="btn-group pull-left">
													<div id="resultados_ajax_correo"></div>
												</div>
												<button type="button" class="btn btn-info btn-sm" title="Enviar correo de prueba" id="enviar_correo" onclick="probar_correo();"><span class="glyphicon glyphicon-send"></span> Probar correo</button>
												<button type="submit" class="btn btn-primary" name="guardar_correo">Actualizar</button>
											</div>
										</form>
									</div>
								</div>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapseFirmaElectronica" onclick="info_firma_electronica();"><span class="caret"></span> Configuración de la firma electrónica</a>
									<div id="collapseFirmaElectronica" class="panel-collapse collapse">
										<form class="form-horizontal" method="POST" id="configura_firma" name="configura_firma" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="row">
													<div class="col-md-6">
														<div class="form-group">
															<div class="col-sm-12">
																<div class="input-group">
																	<span class="input-group-addon"><b>Archivo</b></span>
																	<input type="hidden" name="id_firma" id="id_firma">
																	<input class='filestyle' data-buttonText=" Firma" type="file" name="archivo" id="archivo">
																</div>
															</div>
														</div>
														<div class="form-group">
															<div class="col-sm-12">
																<div class="input-group">
																	<span class="input-group-addon"><b> Fecha de vencimiento</b></span>
																	<input type="text" class="form-control input-sm" name="vence_firma" id="vence_firma">
																</div>
															</div>
														</div>
														<div class="form-group">
															<div class="col-sm-12">
																<div class="input-group">
																	<span class="input-group-addon"><b>Contraseña</b></span>
																	<input type="password" class="form-control input-sm" id="clave_firma" name="clave_firma" placeholder="Contraseña" title="Contraseña" value="">
																	<span class="input-group-btn btn-md"><button class="btn btn-default btn-md" type="button" title="Mostrar contraseña" id="showPasswordFirma"><span class="glyphicon glyphicon-eye-open"></span></button></span>
																</div>
															</div>
														</div>

														<div class="form-group">
															<div class="col-sm-12">
																<div class="input-group">
																	<div id="archivo_firma"></div>
																</div>
															</div>
														</div>
														<div class="form-group">
															<div id="resultados_ajax_firma"></div>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="form-group">
															<div class="col-sm-6">
																<div id="loader_verifica_firma"></div>
															</div>
														</div>
														<div class="form-group">
															<div class="col-sm-12">
																<div id="resultados_ajax_verifica_firma"></div>
															</div>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer">
												<div class="btn-group pull-left">
													<div id="resultados_ajax_firma"></div>
												</div>
												<button type="button" class="btn btn-success btn-sm" title="Validar firma" onclick="validar_firma();">Validar Firma</button>
												<button type="submit" class="btn btn-primary" name="guardar_firma_electronica">Actualizar</button>
											</div>
										</form>
									</div>
								</div>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapseEstablecimiento" onclick="info_establecimiento();"><span class="caret"></span> Configuración de establecimiento y logo</a>
									<div id="collapseEstablecimiento" class="panel-collapse collapse">
										<form class="form-horizontal" id="confic_establecimiento" method="POST" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="form-group">
													<div class="col-sm-6">
														<input type="hidden" name="id_establecimiento" id="id_establecimiento">
														<div class="input-group">
															<span class="input-group-addon"><b> Punto de emisión</b></span>
															<select class="form-control" name="serie_sucursal" id="serie_sucursal">
																<?php
																while ($row_emision = mysqli_fetch_assoc($busca_info_sucursales)) {
																?>
																	<option value="<?php echo $row_emision['serie']; ?>" selected><?php echo substr($row_emision['serie'], 4, 3);  ?> </option>
																<?php
																}
																?>
															</select>
														</div>
													</div>

													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b> Moneda</b></span>
															<select class="form-control" name="moneda_sucursal" id="mon_sucursal">
																<option value="DOLAR" selected>Dólar</option>
																<option value="EURO">Euro</option>
															</select>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b> Decimales cantidad</b></span>
															<select class="form-control" name="decimales_cantidad" id="deci_cant">
																<option value="" selected>Seleccione</option>
																<option value="0">Cero</option>
																<option value="1">Uno</option>
																<option value="2">Dos</option>
																<option value="3">Tres</option>
																<option value="4">Cuatro</option>
																<option value="5">Cinco</option>
																<option value="6">Seis</option>
															</select>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b> Decimales precio</b></span>
															<select class="form-control" name="decimales_documento" id="deci_docu">
																<option value="" selected>Seleccione</option>
																<option value="0">Cero</option>
																<option value="1">Uno</option>
																<option value="2">Dos</option>
																<option value="3">Tres</option>
																<option value="4">Cuatro</option>
																<option value="5">Cinco</option>
																<option value="6">Seis</option>
															</select>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b> Dirección establecimiento</b></span>
															<input type="text" class="form-control" name="dir_sucursal" id="dir_sucursal" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<span class="input-group-addon"><b> Nombre establecimiento</b></span>
															<input type="text" class="form-control" name="nombre_sucursal" id="nombre_sucursal" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de facturas</b></span>
															<input type="number" class="form-control" name="inicial_factura" id="ini_factura" required>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de notas de crédito</b></span>
															<input type="number" class="form-control" name="inicial_nc" id="ini_nc" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de nota de débito</b></span>
															<input type="number" class="form-control" name="inicial_nd" id="ini_nd" required>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de guía de remisión</b></span>
															<input type="text" class="form-control" name="inicial_gr" id="ini_gr" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de retención en compras</b></span>
															<input type="number" class="form-control" name="inicial_cr" id="ini_cr" required>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de liquidaciones compras/servicios</b></span>
															<input type="number" class="form-control" name="inicial_liq" id="ini_liq" required>
														</div>
													</div>
												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Número inicial de proformas</b></span>
															<input type="number" class="form-control" name="inicial_proforma" id="inicial_proforma" required>
														</div>
													</div>
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b> Recibos de venta con impuestos</b></span>
															<select class="form-control" name="impuestos_recibo" id="impuestos_recibo">
																<option value="1" selected> No</option>
																<option value="2"> Si</option>
															</select>
														</div>
													</div>

												</div>
												<div class="form-group">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b> Logo establecimiento</b></span>
															<input class='filestyle' data-buttonText=" Logo" type="file" name="logo_sucursal">
														</div>
													</div>
													<label class="col-sm-6">* Cuando se actualizan los datos no es necesario agregar logo.</label>
												</div>
												<div class="form-group">
													<div class="col-sm-12">
														<div class="input-group">
															<div id="logo_establecimiento"></div>
														</div>
													</div>
												</div>
											</div>
											<div class="modal-footer">
												<div class="btn-group pull-left">
													<div id="resultados_ajax_sucursales"></div>
												</div>
												<button type="submit" class="btn btn-primary" name="guardar_secuencia">Actualizar</button>
											</div>
										</form>
									</div>
								</div>
								<div class="panel panel-success">
									<a class="list-group-item list-group-item-success" data-toggle="collapse" data-parent="#accordion" href="#collapsewhatsapp" onclick="generar_qr_whatsapp();"><span class="caret"></span> Conexión a Whatsapp</a>
									<div id="collapsewhatsapp" class="panel-collapse collapse">
										<form class="form-horizontal" id="confic_whatsapp" method="POST" enctype="multipart/form-data" autocomplete="off">
											<div class="panel-body">
												<div class="form-group">
													<input type="hidden" name="ruc_empresa_whatsapp" id="ruc_empresa_whatsapp" value="<?php echo $ruc_empresa; ?>">
													<div class="col-sm-6">
														<div class="input-group">
															<span class="input-group-addon"><b>Estado del Servicio</b></span>
															<select class="form-control input-sm" name="status_whatsapp" id="status_whatsapp" disabled>
																<option value="1">Activado</option>
																<option value="2" selected>Desactivado</option>
															</select>
														</div>
													</div>
													<div class="col-sm-4">
														<div class="btn-group pull-left">
															<div id="qr_whatsapp"></div>
														</div>
													</div>
													<div class="col-sm-2">
														<button type="button" class="btn btn-danger btn-sm" id="boton_desactivar" title="Desactivar" onclick="desactivar_whatsapp();">Desactivar</button>
													</div>
												</div>
												<div class="btn-group pull-left">
													<div id="resultados_ajax_whatsapp"></div>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div><!--fin del acordeon -->
						</div><!--fin del body de todo -->
					</div><!--fin del panel info que abarca a todo -->
				</div><!-- fin de la caja de 8 espacios -->
			</div>
		</div><!--fin del container -->
	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	</body>

	</html>
	<script>
		jQuery(function($) {
			$("#vence_firma").mask("99-99-9999");
		});

		document.addEventListener('DOMContentLoaded', () => {
			//para mostrar las claves y/ ocultar
			const passwordInputSRI = document.getElementById("clave_sri");
			const showPasswordSRI = document.getElementById("showPasswordSRI");
			const passwordInputCorreo = document.getElementById("correo_pass");
			const showPasswordCorreo = document.getElementById("showPasswordCorreo");
			const passwordInputFirma = document.getElementById("clave_firma");
			const showPasswordFirma = document.getElementById("showPasswordFirma");

			showPasswordSRI.addEventListener("click", function() {
				if (passwordInputSRI.type === "password") {
					passwordInputSRI.type = "text";
				} else {
					passwordInputSRI.type = "password";
				}
			});

			showPasswordCorreo.addEventListener("click", function() {
				if (passwordInputCorreo.type === "password") {
					passwordInputCorreo.type = "text";
				} else {
					passwordInputCorreo.type = "password";
				}
			});

			showPasswordFirma.addEventListener("click", function() {
				if (passwordInputFirma.type === "password") {
					passwordInputFirma.type = "text";
				} else {
					passwordInputFirma.type = "password";
				}
			});
		});

		async function info_general() {
			document.getElementById('provincia').addEventListener('change', () => {
				// Llama a la función ciudades cada vez que cambie la provincia
				ciudades();
			});
			const ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_general';
			try {
				const response = await fetch(ajaxUrl);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const objData = await response.json();
				if (objData.status) {
					$('#guardar_perfil_empresa').attr("disabled", true);
					const objGeneral = objData.data;
					const tipoContribuyenteActual = objGeneral.tipo_contribuyente;
					const provinciaActual = objGeneral.provincia;
					const ciudadActual = objGeneral.ciudad;
					document.querySelector("#razon_social").value = objGeneral.razon_social;
					document.querySelector("#id_empresa").value = objGeneral.id_empresa;
					document.querySelector("#nombre_comercial").value = objGeneral.nombre_comercial;
					document.querySelector("#ruc").value = objGeneral.ruc;
					document.querySelector("#establecimiento").value = objGeneral.establecimiento;
					document.querySelector("#telefono").value = objGeneral.telefono;
					document.querySelector("#direccion").value = objGeneral.direccion;
					document.querySelector("#mail_empresa").value = objGeneral.mail;
					document.querySelector("#representante_legal").value = objGeneral.representante_legal;
					document.querySelector("#id_representante_legal").value = objGeneral.ced_rep_legal;
					document.querySelector("#nombre_contador").value = objGeneral.nombre_contador;
					document.querySelector("#ruc_contador").value = objGeneral.ruc_contador;
					document.querySelector("#provincia").value = objGeneral.provincia;
					document.querySelector("#ciudad").value = objGeneral.ciudad;
					// Llama a la función para llenar el select y selecciona el valor
					await tipo_contribuyente(tipoContribuyenteActual);
					await provincias(provinciaActual);
					await ciudades(ciudadActual);
					$('#guardar_perfil_empresa').attr("disabled", false);
				} else {
					$.notify(objData.msg, "error");
				}
			} catch (error) {
				console.error('Error al obtener información general:', error);
			}
		}

		async function tipo_contribuyente(seleccionado = "") {
			const url = '../ajax/config_docs_electronicos.php?action=tipo_empresa';
			const selecTipoContribuyente = document.getElementById('tipo_contribuyente');
			try {
				const response = await fetch(url);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const tipoContribuyentes = await response.json();

				// Limpia las opciones existentes
				selecTipoContribuyente.innerHTML = '<option value="">Seleccione tipo contribuyente</option>';

				// Genera las opciones del select
				tipoContribuyentes.forEach(tipo_contribuyente => {
					const option = document.createElement('option');
					option.value = tipo_contribuyente.codigo; // Ajusta según tu columna
					option.textContent = tipo_contribuyente.nombre; // Ajusta según tu columna
					// Preselecciona si coincide con el valor recibido
					if (tipo_contribuyente.codigo === seleccionado) {
						option.selected = true;
					}
					selecTipoContribuyente.appendChild(option);
				});
			} catch (error) {
				console.error('Error al cargar tipo contribuyente:', error);
			}
		}

		async function ciudades(seleccionado = "") {
			const provincia = document.getElementById("provincia").value; // Obtener el código de la provincia seleccionada
			const url = `../ajax/config_docs_electronicos.php?action=ciudades&provincia=${provincia}`;
			const selectCiudad = document.getElementById('ciudad'); // Elemento del select de ciudades

			if (!provincia) {
				// Si no hay provincia seleccionada, limpiar las opciones del select
				selectCiudad.innerHTML = '<option value="">Seleccione una provincia primero</option>';
				return;
			}
			try {
				const response = await fetch(url);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const ciudades = await response.json(); // Datos de las ciudades obtenidos del servidor

				// Limpiar las opciones existentes
				selectCiudad.innerHTML = '<option value="">Seleccione una ciudad</option>';

				// Generar opciones dinámicas basadas en las ciudades obtenidas
				ciudades.forEach(ciudad => {
					const option = document.createElement('option');
					option.value = ciudad.codigo; // Ajusta según el nombre del campo en tu respuesta
					option.textContent = ciudad.nombre; // Ajusta según el nombre del campo en tu respuesta
					if (ciudad.codigo === seleccionado) {
						option.selected = true; // Preselecciona si coincide con el argumento "seleccionado"
					}
					selectCiudad.appendChild(option);
				});
			} catch (error) {
				console.error('Error al cargar ciudades:', error);
			}
		}

		async function provincias(seleccionado = "") {
			const url = '../ajax/config_docs_electronicos.php?action=provincias';
			// Elemento del select
			const selectProvincia = document.getElementById('provincia');

			try {
				const response = await fetch(url);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const provincias = await response.json();

				selectProvincia.innerHTML = '<option value="">Seleccione una provincia</option>';
				// Generar opciones del select
				provincias.forEach(provincia => {
					const option = document.createElement('option');
					option.value = provincia.codigo; // Ajusta según el nombre de tu columna
					option.textContent = provincia.nombre; // Ajusta según el nombre de tu columna
					if (provincia.codigo === seleccionado) {
						option.selected = true;
					}
					selectProvincia.appendChild(option);
				});
			} catch (error) {
				console.error('Error al cargar provincias:', error);
			}
		}

		async function info_emisor() {
			const ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_emisor';
			try {
				const response = await fetch(ajaxUrl);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const objData = await response.json();
				if (objData.status) {
					let objEmisor = objData.data;
					$('#guardar_emisor').attr("disabled", true);
					document.querySelector("#id_ruc_descargas").value = objEmisor.id_ruc_descargas;
					document.querySelector("#id_emisor").value = objEmisor.id_emisor;
					document.querySelector("#resol_ce").value = objEmisor.resol_cont;
					document.querySelector("#ssl").value = objEmisor.ssl_hab;
					document.querySelector("#agente_ret").value = objEmisor.agente_ret;
					document.querySelector("#tipo_regimen").value = objEmisor.tipo_regimen;
					document.querySelector("#tipo_ambiente").value = objEmisor.tipo_ambiente;
					document.querySelector("#tipo_emision").value = objEmisor.tipo_emision;
					document.querySelector("#clave_sri").value = objEmisor.clave_sri;
					document.querySelector("#status_descargas_sri").value = objEmisor.status_descargas;
					document.querySelector("#periodo_descargas_sri").value = objEmisor.descarga;
					$('#guardar_emisor').attr("disabled", true);
				} else {
					$.notify(objData.msg, "error");
				}
			} catch (error) {
				console.error('Error al obtener información del emisor:', error);
			}
		}

		async function info_correo() {
			const ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_correo';
			try {
				const response = await fetch(ajaxUrl);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const objData = await response.json();
				if (objData.status) {
					let objEmisor = objData.data;
					$('#guardar_correo').attr("disabled", true);
					document.querySelector("#id_emisor_correo").value = objEmisor.id_emisor_correo;
					document.querySelector("#correo_asunto").value = objEmisor.correo_asunto;
					document.querySelector("#correo_host").value = objEmisor.correo_host;
					document.querySelector("#correo_pass").value = objEmisor.correo_pass;
					document.querySelector("#correo_port").value = objEmisor.correo_port;
					document.querySelector("#correo_remitente").value = objEmisor.correo_remitente;
					$('#guardar_correo').attr("disabled", true);
				} else {
					$.notify(objData.msg, "error");
				}
			} catch (error) {
				console.error('Error al obtener información del correo:', error);
			}
		}

		async function info_firma_electronica() {
			const ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_firma_electronica';
			try {
				const response = await fetch(ajaxUrl);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const objData = await response.json();
				if (objData.status) {
					let objEmisor = objData.data;
					document.querySelector("#id_firma").value = objEmisor.id_firma;
					document.querySelector("#vence_firma").value = objEmisor.fecha_fin_firma;
					document.querySelector("#clave_firma").value = objEmisor.pass_firma;
					const link_firma = document.getElementById('archivo_firma');
					link_firma.innerHTML = objEmisor.archivo_firma;
				} else {
					$.notify(objData.msg, "error");
				}
			} catch (error) {
				console.error('Error al obtener información del correo:', error);
			}
		}

		//para guardar la informacion general de la empresa
		$(function() {
			$("#editar_empresa").on("submit", function(e) {
				e.preventDefault();
				var f = $(this);
				var formData = new FormData(document.getElementById("editar_empresa"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/config_docs_electronicos.php?action=guarda_informacion_general",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$("#resultados_informacion_general").html("Actualizando...");
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_informacion_general").html(res);
					});
			});
		});

		//para guardar datos del emisor electronico
		$(function() {
			$("#configura_emisor").on("submit", function(e) {
				e.preventDefault();
				var f = $(this);
				var formData = new FormData(document.getElementById("configura_emisor"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/config_docs_electronicos.php?action=guarda_informacion_emisor",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$("#resultados_ajax_emisor").html("Actualizando...");
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_ajax_emisor").html(res);
					});
			});
		});


		//para guardar datos del correo electronico
		$(function() {
			$("#configura_correo").on("submit", function(e) {
				e.preventDefault();
				var f = $(this);
				var formData = new FormData(document.getElementById("configura_correo"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/config_docs_electronicos.php?action=guarda_correo_electronico",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$("#resultados_ajax_correo").html("Actualizando...");
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_ajax_correo").html(res);
					});
			});
		});


		//para enviar un correo de prueba
		function probar_correo() {
			var correo_asunto = $("#correo_asunto").val();
			var correo_pass = encodeURIComponent($("#correo_pass").val());
			var correo_remitente = $("#correo_remitente").val();
			var correo_port = $("#correo_port").val();
			var correo_host = $("#correo_host").val();

			$('#enviar_correo').attr("disabled", true);
			$.ajax({
				url: "../ajax/config_docs_electronicos.php?action=probar_correo&correo_asunto=" +
					correo_asunto + "&correo_pass=" + correo_pass + "&correo_remitente=" + correo_remitente + "&correo_port=" +
					correo_port + "&correo_host=" + correo_host,
				beforeSend: function(objeto) {
					$('#resultados_ajax_correo').html('Enviando correo...');
				},
				success: function(data) {
					$("#resultados_ajax_correo").html(data).fadeIn('slow');
					$("#resultados_ajax_correo").html('');
					$('#enviar_correo').attr("disabled", false);
				}
			});
		}

		//para guardar la firma
		$(function() {
			$("#configura_firma").on("submit", function(e) {
				e.preventDefault();
				var f = $(this);
				var formData = new FormData(document.getElementById("configura_firma"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/config_docs_electronicos.php?action=guarda_actualiza_firma",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$("#resultados_ajax_firma").html("Actualizando...");
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_ajax_firma").html(res);
					});
			});
		});


		//para validar la firma
		function validar_firma() {
			var formData = new FormData(document.getElementById("configura_firma"));
			formData.append("dato", "valor");
			$.ajax({
					type: "POST",
					url: "../ajax/config_docs_electronicos.php?action=verificar_firma",
					dataType: "html",
					data: formData,
					beforeSend: function(objeto) {
						$("#loader_verifica_firma").html("Validando archivo...");
					},
					cache: false,
					contentType: false,
					processData: false
				})
				.done(function(datos) {
					$("#loader_verifica_firma").html("");
					$("#resultados_ajax_verifica_firma").html(datos);
					var vence_firma = $("#fecha_vencimiento").val();
					$("#vence_firma").val(vence_firma);
				});
		}


		//para guardar datos de establecimiento
		$(function() {
			$("#confic_establecimiento").on("submit", function(e) {
				e.preventDefault();
				var f = $(this);
				var formData = new FormData(document.getElementById("confic_establecimiento"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/config_docs_electronicos.php?action=guarda_actualiza_establecimiento",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$("#resultados_ajax_sucursales").html("Actualizando...");
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$("#resultados_ajax_sucursales").html(res);
					});
			});
		});

		//para traer informacion de la sucursal cuando se seleccione la serie
		$(function() {
			$('#serie_sucursal').change(function() {
				info_establecimiento();
			});
		});


		//mostrar informacion del establecimiento al seleccionar punto de emicision
		function info_establecimiento() {
			var serie = $("#serie_sucursal").val();
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_establecimiento&serie_sucursal=' + serie;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objSucursal = objData.data;
						document.querySelector("#id_establecimiento").value = objSucursal.id_establecimiento;
						document.querySelector("#dir_sucursal").value = objSucursal.direccion_sucursal;
						document.querySelector("#nombre_sucursal").value = objSucursal.nombre_sucursal;
						document.querySelector("#mon_sucursal").value = objSucursal.moneda_sucursal;
						document.querySelector("#ini_factura").value = objSucursal.inicial_factura;
						document.querySelector("#ini_nc").value = objSucursal.inicial_nc;
						document.querySelector("#ini_nd").value = objSucursal.inicial_nd;
						document.querySelector("#ini_gr").value = objSucursal.inicial_gr;
						document.querySelector("#ini_cr").value = objSucursal.inicial_cr;
						document.querySelector("#ini_liq").value = objSucursal.inicial_liq;
						document.querySelector("#inicial_proforma").value = objSucursal.inicial_proforma;
						document.querySelector("#deci_docu").value = objSucursal.decimal_doc;
						document.querySelector("#deci_cant").value = objSucursal.decimal_cant;
						document.querySelector("#impuestos_recibo").value = objSucursal.impuestos_recibo;
						const logo_establecimiento = document.getElementById('logo_establecimiento');
						logo_establecimiento.innerHTML = objSucursal.logo_establecimiento;

					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		//para generar codigo qr
		function generar_qr_whatsapp() {
			var ruc_empresa_whatsapp = $("#ruc_empresa_whatsapp").val();
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../clases/whatsapp.php?action=generar_qr_whatsapp&ruc_empresa_whatsapp=' + encodeURIComponent(ruc_empresa_whatsapp);

			// Mostrar mensaje de carga antes de iniciar la solicitud
			const resultados_ajax_whatsapp = document.getElementById('resultados_ajax_whatsapp');
			resultados_ajax_whatsapp.innerHTML = '<span style="color: blue;">Cargando, por favor espera...</span>';

			// Ocultar el botón de desactivar por defecto
			document.getElementById('boton_desactivar').style.display = 'none';

			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objConectar = objData.data;
						document.querySelector("#status_whatsapp").value = objConectar.status;
						const qr = document.getElementById('qr_whatsapp');
						qr.innerHTML = objConectar.qr;
						const resultados_ajax_whatsapp = document.getElementById('resultados_ajax_whatsapp');
						resultados_ajax_whatsapp.innerHTML = objConectar.mensaje;
						if (objConectar.mensaje === "Cliente ya conectado") {
							document.getElementById('boton_desactivar').style.display = 'block';
						}
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}



		function desactivar_whatsapp() {
			var ruc_empresa_whatsapp = $("#ruc_empresa_whatsapp").val();
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../clases/whatsapp.php?action=desactivar_whatsapp&ruc_empresa_whatsapp=' + encodeURIComponent(ruc_empresa_whatsapp);

			// Mostrar mensaje de carga antes de iniciar la solicitud
			const resultados_ajax_whatsapp = document.getElementById('resultados_ajax_whatsapp');
			resultados_ajax_whatsapp.innerHTML = '<span style="color: blue;">Cargando, por favor espera...</span>';

			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objConectar = objData.data;
						const resultados_ajax_whatsapp = document.getElementById('resultados_ajax_whatsapp');
						resultados_ajax_whatsapp.innerHTML = objConectar.mensaje;
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}
	</script>