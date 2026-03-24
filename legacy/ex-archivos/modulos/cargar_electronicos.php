<?php
require_once("../helpers/helpers.php");
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<meta charset="utf-8">
		<title>Cargar electrónicos</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		$con = conenta_login();
		$ruc_empresa_descargas = substr($ruc_empresa, 0, 10);
		$query_status_descargas = mysqli_query($con, "SELECT status FROM descargasri WHERE mid(ruc,1,10) = '" . $ruc_empresa_descargas . "' ");
		$row_status_descarga = mysqli_fetch_array($query_status_descargas);
		$status_ruc = isset($row_status_descarga['status']) ? $row_status_descarga['status'] : 0;

		$query_periodo = mysqli_query($con, "SELECT descarga FROM config_electronicos WHERE ruc_empresa = '" . $ruc_empresa . "' ");
		$row_periodo = mysqli_fetch_array($query_periodo);
		$descarga = isset($row_periodo['descarga']) ? $row_periodo['descarga'] : 0;

		switch ($row_periodo['descarga']) {
			case 1:
				$descarga = "<span class='label label-success'>Todos los días</span>";
				break;
			case 2:
				$descarga = "<span class='label label-success'>Semanal</span>";
				break;
			case 3:
				$descarga = "<span class='label label-success'>Quincenal</span>";
				break;
			case 4:
				$descarga = "<span class='label label-success'>Mensual</span>";
				break;
		}

		if ($status_ruc == 1) {
			$status_ruc = "<span class='label label-success'>Activo</span>";
		} else {
			$status_ruc = "<span class='label label-danger'>Inactivo</span>";
		}

		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-search'></i> Cargar Documentos Electrónicos</h4>
				</div>
				<div class="panel-body">
					<div class="panel-group" id="accordionDescargaElectronicos">
						<!-- carga por clave de accesso -->
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#descargaClaveAcceso"><span class="caret"></span> Cargar documento mediante una clave de acceso o número de autorización</a>
							<div id="descargaClaveAcceso" class="panel-collapse collapse">
								<div class="modal-body">
									<form>
										<div class="form-group row">
											<div class="col-sm-7">
												<div class="input-group">
													<span class="input-group-addon"><b>Clave Acceso / Número de autorización</b></span>
													<input type="text" class="form-control" id="clave_acceso" name="clave_acceso">
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<button type="button" class="btn btn-info" onclick='cargar_una_clave_acceso();'><span class="glyphicon glyphicon-upload"></span> Cargar</button>
												</div>
											</div>
											<div class="col-sm-3">
												<span id="loader_clave_acceso"></span>
											</div>
										</div>
									</form>
								</div>
							</div>
						</div>
						<!-- carga por archivo txt -->
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#descargaTxt"><span class="caret"></span> Cargar documentos mediante archivo txt descargado desde el SRI</a>
							<div id="descargaTxt" class="panel-collapse collapse">
								<div class="modal-body">
									<div class="form-group row">
										<form method="post" action="" id="cargar_archivos" name="cargar_archivos" enctype="multipart/form-data">
											<div class="col-sm-4">
												<div class="input-group">
													<input class="filestyle" data-buttonText=" Archivo" type="file" id="archivo" name="archivo[]" data-buttonText="Archivo txt" multiple />
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<button type="submit" class="btn btn-info" name="subir"><span class="glyphicon glyphicon-upload"></span> Cargar</button>
												</div>
											</div>
										</form>
										<div class="col-sm-3">
											<span id="loader_txt_sri"></span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- carga por carpeta xml -->
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#descargaXml"><span class="caret"></span> Cargar documento mediante carpeta de archivos xml descargado desde el SRI</a>
							<div id="descargaXml" class="panel-collapse collapse">
								<div class="modal-body">
									<div class="form-group row">
										<form method="post" action="" id="cargar_xml" name="cargar_xml" enctype="multipart/form-data">
											<div class="col-sm-4">
												<div class="input-group">
													<input class="filestyle" data-buttonText=" Carpeta" type="file" id="carpetaXml" name="carpetaXml[]" webkitdirectory directory multiple required />
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<button type="submit" class="btn btn-info" name="subir_xml"><span class="glyphicon glyphicon-upload"></span> Cargar</button>
												</div>
											</div>
										</form>
										<div class="col-sm-3">
											<span id="loader_xml_sri"></span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- carga por meses de años anteriores -->
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#descargaOtrosPeriodos" onclick="info_datos_sri();"><span class="caret"></span> Cargar documentos de otros periodos</a>
							<div id="descargaOtrosPeriodos" class="panel-collapse collapse">
								<div class="modal-body">
									<div class="form-group row">
										<form>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Clave SRI</b></span>
													<input type="hidden" id="ruc_empresa_descarga" name="ruc_empresa_descarga" value="<?php echo $ruc_empresa_descargas . "001"; ?>">
													<input type="text" class="form-control input-sm" id="clave_sri_descargas" name="clave_sri_descargas">
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Año</b></span>
													<select class="form-control input-sm" name="anio_descarga" id="anio_descarga">
														<option value="<?php echo date("Y") ?>"> <?php echo date("Y") ?></option>
														<?php for ($i = $anio2 = date("Y") - 1; $i > $anio1 = date("Y") - 4; $i += -1) {
														?>
															<option value="<?php echo $i ?>"> <?php echo $i ?></option>
														<?php }  ?>
													</select>
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Mes</b></span>
													<select class="form-control input-sm" name="mes_descarga" id="mes_descarga">
														<option value="1"> Enero</option>
														<option value="2"> Febrero</option>
														<option value="3"> Marzo</option>
														<option value="4"> Abril</option>
														<option value="5"> Mayo</option>
														<option value="6"> Junio</option>
														<option value="7"> Julio</option>
														<option value="8"> Agosto</option>
														<option value="9"> Septiembre</option>
														<option value="10"> Octubre</option>
														<option value="11"> Noviembre</option>
														<option value="12"> Diciembre</option>
													</select>
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Día</b></span>
													<select class="form-control input-sm" name="dia_descarga" id="dia_descarga">
														<option value="0" selected>Todos</option>
														<option value="1"> 1</option>
														<option value="2"> 2</option>
														<option value="3"> 3</option>
														<option value="4"> 4</option>
														<option value="5"> 5</option>
														<option value="6"> 6</option>
														<option value="7"> 7</option>
														<option value="8"> 8</option>
														<option value="9"> 9</option>
														<option value="10"> 10</option>
														<option value="11"> 11</option>
														<option value="12"> 12</option>
														<option value="13"> 13</option>
														<option value="14"> 14</option>
														<option value="15"> 15</option>
														<option value="16"> 16</option>
														<option value="17"> 17</option>
														<option value="18"> 18</option>
														<option value="19"> 19</option>
														<option value="20"> 20</option>
														<option value="21"> 21</option>
														<option value="22"> 22</option>
														<option value="23"> 23</option>
														<option value="24"> 24</option>
														<option value="25"> 25</option>
														<option value="26"> 26</option>
														<option value="27"> 27</option>
														<option value="28"> 28</option>
														<option value="29"> 29</option>
														<option value="30"> 30</option>
														<option value="31"> 31</option>
													</select>
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<span class="input-group-addon"><b>Documento</b></span>
													<select class="form-control input-sm" name="documento_descarga" id="documento_descarga">
														<option value="1" selected> Factura</option>
														<option value="2"> Liquidación CS</option>
														<option value="3"> Notas de Crédito</option>
														<option value="4"> Notas de Débito</option>
														<option value="6"> Comprobante de Retención</option>
													</select>
												</div>
											</div>
											<div class="col-sm-1">
												<div class="input-group">
													<button type="button" class="btn btn-info" id="cargar_por_periodos" onclick="event.preventDefault(); cargar_otros_periodos();">
														<span class="glyphicon glyphicon-upload"></span> Cargar
													</button>
												</div>
											</div>
										</form>
									</div>
									<div class="form-group row">
										<div class="col-sm-12">
											<span id="loader_otros_periodos"></span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- documentos que no quiero descargar -->
						<div class="panel panel-info">
							<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#noDescargar" onclick="mostrar_clave_acceso_no_descarga();"><span class="caret"></span> Documentos que no deseo descargar</a>
							<div id="noDescargar" class="panel-collapse collapse">
								<div class="modal-body">
									<form>
										<div class="form-group row">
											<div class="col-sm-7">
												<div class="input-group">
													<span class="input-group-addon"><b>Clave Acceso / Número de autorización</b></span>
													<input type="text" class="form-control" id="clave_acceso_no_descargar" name="clave_acceso_no_descargar">
												</div>
											</div>
											<div class="col-sm-2">
												<div class="input-group">
													<button type="button" class="btn btn-info" onclick='guardar_clave_acceso_no_descarga();'><span class="glyphicon glyphicon-floppy-disk"></span> Agregar</button>
												</div>
											</div>
											<div class="col-sm-3">
												<span id="loader_clave_acceso_no_descargar"></span>
											</div>
										</div>
									</form>
									<div class='outer_div_no_descargar'></div>
								</div>
							</div>
						</div>
						<?php
						//}
						?>
						<div style="padding: 2px; margin-bottom: 5px; margin-top: 10px;" class="alert alert-info" role="alert">
							La descarga automática de todos los documentos electrónicos se ejecutan <?php echo $descarga; ?> y su estado actual es </b><?php echo $status_ruc; ?> <b>
								<br>* Para configurar las descargas automáticas lo puede hacer desde >> <a href="../modulos/config_docs_electronicos.php">Aquí</a>
								<< en la segunda pestaña, Configuración del emisor electrónico y descargas del SRI
									</div>
						</div>
						<div class='outer_div_descargas'></div><!-- Carga los datos ajax -->
					</div>
				</div>
			</div>
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
	</body>

	</html>
	<script>
		async function info_datos_sri() {
			const ajaxUrl = '../ajax/config_docs_electronicos.php?action=informacion_emisor';
			try {
				const response = await fetch(ajaxUrl);
				if (!response.ok) {
					throw new Error('Error en la respuesta del servidor');
				}
				const objData = await response.json();
				if (objData.status) {
					let objEmisor = objData.data;
					document.querySelector("#clave_sri_descargas").value = objEmisor.clave_sri;
				} else {
					$.notify(objData.msg, "error");
				}
			} catch (error) {
				console.error('Error al obtener información del emisor:', error);
			}
		}


		$(function() {
			//para varios archivos txt
			$("#cargar_archivos").on("submit", function(e) {
				e.preventDefault();
				var formData = new FormData(document.getElementById("cargar_archivos"));
				formData.append("dato", "valor");

				$.ajax({
						url: "../ajax/subir_documentos_electronicos.php?action=archivo_documentos_electronicos",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$('#loader_txt_sri').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando...</div></div>');
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$(".outer_div_descargas").html(res);
						$("#loader_txt_sri").html('');
					});
			});

			//para el xml
			$("#cargar_xml").on("submit", function(e) {
				e.preventDefault();
				var formData = new FormData(document.getElementById("cargar_xml"));
				formData.append("dato", "valor");
				$.ajax({
						url: "../ajax/subir_documentos_electronicos.php?action=archivo_xml",
						type: "post",
						dataType: "html",
						data: formData,
						beforeSend: function(objeto) {
							$('#loader_xml_sri').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando...</div></div>');
						},
						cache: false,
						contentType: false,
						processData: false
					})
					.done(function(res) {
						$(".outer_div_descargas").html(res);
						$("#loader_xml_sri").html('');
					});
			});

		});


		function cargar_una_clave_acceso() {
			var clave_acceso = $("#clave_acceso").val();
			$("#loader_clave_acceso").fadeIn('slow');
			$.ajax({
				url: "../ajax/subir_documentos_electronicos.php?action=clave_documento_individual&clave_acceso=" + clave_acceso,
				beforeSend: function(objeto) {
					$('#loader_clave_acceso').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando...</div></div>');
				},
				success: function(data) {
					$(".outer_div_descargas").html(data).fadeIn('slow');
					$("#loader_clave_acceso").html('');
				}
			});
		}


		function guardar_clave_acceso_no_descarga() {
			var clave_acceso = $("#clave_acceso_no_descargar").val();
			$("#loader_clave_acceso_no_descargar").fadeIn('slow');
			$.ajax({
				url: "../ajax/subir_documentos_electronicos.php?action=clave_acceso_no_descargar&clave_acceso=" + clave_acceso,
				beforeSend: function(objeto) {
					$('#loader_clave_acceso_no_descargar').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Guardando...</div></div>');
				},
				success: function(data) {
					mostrar_clave_acceso_no_descarga();
					$(".outer_div_no_descargar").html(data).fadeIn('slow');
					$("#loader_clave_acceso_no_descargar").html('');
				}
			});
		}

		//muestra el listado de claves de acceso que no quiero que se descarguen
		function mostrar_clave_acceso_no_descarga() {
			$.ajax({
				url: "../ajax/subir_documentos_electronicos.php?action=mostrar_clave_acceso_no_descargar",
				beforeSend: function(objeto) {
					$('#loader_clave_acceso_no_descargar').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Mostrando...</div></div>');
				},
				success: function(data) {
					$(".outer_div_no_descargar").html(data).fadeIn('slow');
					$("#loader_clave_acceso_no_descargar").html('');
				}
			});
		}

		function eliminar_clave(id) {
			if (confirm("Realmente desea eliminar?")) {
				$.ajax({
					url: "../ajax/subir_documentos_electronicos.php?action=eliminar_clave_acceso_no_descargar&id_clave=" + id,
					beforeSend: function(objeto) {
						$('#loader_clave_acceso_no_descargar').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Eliminando...</div></div>');
					},
					success: function(data) {
						mostrar_clave_acceso_no_descarga();
						$(".outer_div_no_descargar").html(data).fadeIn('slow');
						$("#loader_clave_acceso_no_descargar").html('');
					}
				});
			}
		}

		//para cargar documentos de otros periodos 
		/* 	function cargar_otros_periodos() {
				var ruc_empresa_descarga = $("#ruc_empresa_descarga").val();
				var clave_sri_descargas = $("#clave_sri_descargas").val();
				var anio_descarga = $("#anio_descarga").val();
				var mes_descarga = $("#mes_descarga").val();
				var dia_descarga = $("#dia_descarga").val();
				var tipo_documento = $("#documento_descarga").val();
				$('#cargar_por_periodos').attr("disabled", true);
				$("#loader_otros_periodos").fadeIn('slow');
				$.ajax({
					url: "../facturacion_electronica/descargas_sri/cargarSriAutomaticos.php",
					type: "POST",
					data: {
						action: "cargar_otros_periodos",
						ruc_empresa_descarga: ruc_empresa_descarga,
						clave_sri_descargas: clave_sri_descargas,
						anio_descarga: anio_descarga,
						mes_descarga: mes_descarga,
						dia_descarga: dia_descarga,
						tipo_documento: tipo_documento
					},
					beforeSend: function() {
						$('#loader_otros_periodos').html(
							'<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando, espere por favor mientras se descargan los documentos solicitados...</div></div>'
						);
					},
					success: function(data) {
						$(".outer_div_descargas").html(data).fadeIn('slow');
						$("#loader_otros_periodos").html('');
						$('#cargar_por_periodos').attr("disabled", false);
					}
				});
			} */

		function cargar_otros_periodos() {
			var ruc_empresa_descarga = $("#ruc_empresa_descarga").val();
			var clave_sri_descargas = $("#clave_sri_descargas").val();
			var anio_descarga = $("#anio_descarga").val();
			var mes_descarga = $("#mes_descarga").val();
			var dia_descarga = $("#dia_descarga").val();
			var tipo_documento = $("#documento_descarga").val();

			// 1) Limpia resultados/mensajes previos
			$(".outer_div_descargas").stop(true, true).hide().empty();

			// 2) Deshabilita botón y muestra loader
			$('#cargar_por_periodos').prop("disabled", true);
			$("#loader_otros_periodos")
				.html(
					'<div class="alert alert-success" role="alert">🕒 La solicitud se encuentra <b>procesando</b>. ' +
					'Puede realizar más solicitudes en 1 minuto o continuar trabajando en otros módulos del sistema.</div>'
				)
				.fadeIn('fast');

			// 3) Llamada AJAX
			$.ajax({
				url: "../facturacion_electronica/descargas_sri/cargarSriAutomaticos.php",
				type: "POST",
				cache: false,
				data: {
					action: "cargar_otros_periodos_async",
					ruc_empresa_descarga: ruc_empresa_descarga,
					clave_sri_descargas: clave_sri_descargas,
					anio_descarga: anio_descarga,
					mes_descarga: mes_descarga,
					dia_descarga: dia_descarga,
					tipo_documento: tipo_documento
				},
				success: function(data) {
					// El backend devuelve el mensaje final
					$(".outer_div_descargas").html(data).fadeIn('fast');
					$("#loader_otros_periodos").html('');
				},
				error: function(xhr) {
					var msg = 'Hubo un problema al solicitar la descarga.';
					if (xhr && xhr.responseText) {
						msg += ' ' + xhr.responseText;
					}
					$(".outer_div_descargas")
						.html('<div class="alert alert-danger" role="alert"><b>Error:</b> ' + msg + '</div>')
						.fadeIn('fast');
					$("#loader_otros_periodos").html('');
				},
				complete: function() {
					// Reactiva botón
					$('#cargar_por_periodos').prop("disabled", false);
				}
			});
		}
	</script>