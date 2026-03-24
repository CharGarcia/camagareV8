<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
	<title>Crear empresa</title>
	<?php include("../head.php"); ?>
	<script src="../js/jquery.min.js"></script>
	<script type="text/javascript" src="../js/select_ciudad.js"></script>
</head>

<body>

	<?php
	include("../conexiones/conectalogin.php");
	session_start();
	if (isset($_SESSION['id_usuario'])) {
		$titulo_info = "Registrar una nueva Empresa ";
		include("../navbar_confi.php");

	?>
		<div class="col-md-12">
			<div class="row">
				<div class="col-md-8 col-md-offset-2">
					<div class="panel panel-success">
						<div class="panel-heading">

							<div class="btn-group pull-right">
								<span id="resultados_ajax"></span>
							</div>
							<h4><i class='glyphicon glyphicon-pencil'></i> Registrar Nueva Empresa</h4>
						</div>
						<div class="panel-body">
							<form class="form-horizontal" id="nueva_empresa" name="nueva_empresa" method="POST" enctype="multipart/form-data">

								<div class="form-group">
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>RUC</b></span>
											<input type="text" class="form-control input-sm" onkeyup="info_contribuyente();" name="ruc" id="ruc" placeholder="RUC" pattern=".{13,13}" required title="Ingrese 13 dígitos">
										</div>
									</div>
									<div class="col-sm-9">
										<div class="input-group">
											<span class="input-group-addon"><b>Razón social</b></span>
											<input type="text" class="form-control input-sm" name="razon_social" id="razon_social" placeholder="Razon social" required>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-3">
										<div class="input-group">
											<span class="input-group-addon"><b>Serie</b></span>
											<input type="text" class="form-control input-sm" name="serie" id="serie" placeholder="001-001" required title="Ingrese 3 dígitos">
										</div>
									</div>
									<div class="col-sm-9">
										<div class="input-group">
											<span class="input-group-addon"><b>Nombre comercial</b></span>
											<input type="text" class="form-control input-sm" name="nombre_comercial" id="nombre_comercial" placeholder="Nombre comercial" required>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Contribuyente</b></span>
											<select class="form-control input-sm" name="tipo" id="tipo">
												<?php
												$conexion = conenta_login();
												$sql = mysqli_query($conexion, "SELECT * FROM tipo_empresa ;");
												?> <option value="">Seleccione tipo empresa</option>
												<?php
												while ($o = mysqli_fetch_assoc($sql)) {
												?>
													<option value="<?php echo $o['codigo'] ?>"><?php echo $o['nombre'] ?> </option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-sm-8">
										<div class="input-group">
											<span class="input-group-addon"><b>Dirección</b></span>
											<input type="text" class="form-control input-sm" name="direccion" id="direccion" placeholder="Dirección" required>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Régimen</b></span>
											<select class="form-control input-sm" name="tipo_regimen" id="tipo_regimen">
												<option value="1" Selected>General</option>
												<option value="2">Rimpe emprendedor</option>
												<option value="3">Rimpe negocio popular</option>
											</select>
										</div>
									</div>
									<div class="col-sm-8">
										<div class="input-group">
											<span class="input-group-addon"><b>Mail</b></span>
											<input type="text" class="form-control input-sm" name="mail" placeholder="Mail" required>
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-8">
										<div class="input-group">
											<span class="input-group-addon"><b>Nombre Rep. Legal</b></span>
											<input type="text" class="form-control input-sm" name="rep_legal" id="rep_legal" placeholder="Nombre del representante legal">
										</div>
									</div>
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>Ced. Rep. Legal</b></span>
											<input type="text" class="form-control input-sm" name="ced_rep_legal" id="ced_rep_legal" placeholder="Cédula, pasaporte del representante legal">
										</div>
									</div>
								</div>
								<div class="form-group">
									<div class="col-sm-8">
										<div class="input-group">
											<span class="input-group-addon"><b>Nombre Contador</b></span>
											<input type="text" class="form-control input-sm" name="nombre_contador" id="nombre_contador" placeholder="Nombre del contador">
										</div>
									</div>
									<div class="col-sm-4">
										<div class="input-group">
											<span class="input-group-addon"><b>RUC Contador</b></span>
											<input type="text" class="form-control input-sm" name="ruc_contador" id="ruc_contador" placeholder="Ruc contador">
										</div>
									</div>
								</div>

								<div class="form-group">
									<div class="col-sm-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Provincia</b></span>
											<select class="form-control input-sm" name="provincia" id="provincia">
												<?php
												$conexion = conenta_login();
												$sql = "SELECT * FROM provincia ;";
												$res = mysqli_query($conexion, $sql);
												?> <option value="">Seleccione una provincia</option>
												<?php
												while ($o = mysqli_fetch_assoc($res)) {
												?>
													<option value="<?php echo $o['codigo'] ?>"><?php echo $o['nombre'] ?> </option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-sm-6">
										<div class="input-group">
											<span class="input-group-addon"><b>Ciudad</b></span>
											<select class="form-control input-sm" name="ciudad" id="ciudad">
												<option value="">Seleccione una ciudad</option>
											</select>
										</div>
									</div>
								</div>

						</div>
						<div class="modal-footer">
							<span id="resultados_info_sri"></span>
							<button type="submit" class="btn btn-primary" name="guarda_empresa" value="Guardar">Guardar</button>
						</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	<?php
	} else {
		header('Location: ../includes/logout.php');
		exit;
	}
	?>
</body>

</html>
<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script>
	jQuery(function($) {
		$("#serie").mask("999-999");
	});

	$(function() {
		$("#nueva_empresa").on("submit", function(e) {
			e.preventDefault();
			var f = $(this);
			var formData = new FormData(document.getElementById("nueva_empresa"));
			formData.append("dato", "valor");
			$.ajax({
					url: "../ajax/nueva_empresa.php",
					type: "post",
					dataType: "html",
					data: formData,
					cache: false,
					contentType: false,
					processData: false,
					beforeSend: function(objeto) {
						$("#resultados_ajax").html("Guardando...");
					},
				})
				.done(function(res) {
					$("#resultados_ajax").html(res);
				});
		});
	});

	$(function() {
		$('#tipo').change(function() {
			var tipo = document.getElementById('tipo').value;
			var nombre_comercial = document.getElementById('razon_social').value;
			var ced_rl = document.getElementById('ruc').value;
			var cedula = ced_rl.substr(0, 10);
			switch (tipo) {
				case "01":
					$("#nombre_comercial").val(nombre_comercial);
					$("#rep_legal").val(nombre_comercial);
					$("#ced_rep_legal").val(cedula);
					break;
				case "02":
					$("#nombre_comercial").val(nombre_comercial);
					$("#rep_legal").val(nombre_comercial);
					$("#ced_rep_legal").val(cedula);
					break;
				default:
					var nombre_comercial = "";
					var ced_rl = "";
					$("#nombre_comercial").val(nombre_comercial);
					$("#rep_legal").val(nombre_comercial);
					$("#ced_rep_legal").val(ced_rl);
			}
		});
	})

	function info_contribuyente() {
		var ruc = document.getElementById('ruc').value;
		if ( ruc.length == 13) {
			$.ajax({
				type: "POST",
				url: "../clases/info_ruc_sri.php?action=info_ruc",
				data: "numero=" + ruc,
				beforeSend: function(objeto) {
					$("#resultados_info_sri").html('Cargando...');
				},
				success: function(datos) {
					$.each(datos, function(i, item) {
						$("#razon_social").val(item.nombre);
						$("#direccion").val(item.direccion);
						$("#nombre_comercial").val(item.nombre_comercial);
						$("#tipo").val(item.tipo);
						if (item.tipo == "01" || item.tipo == "01") {
							var ced_rl = document.getElementById('ruc').value;
							var cedula = ced_rl.substr(0, 10);
							$("#rep_legal").val(item.nombre);
							$("#ced_rep_legal").val(cedula);
						} else {
							$("#rep_legal").val('');
							$("#ced_rep_legal").val('');
						}

					});
					$("#resultados_info_sri").html('');
				}
			});
		}
	}
</script>