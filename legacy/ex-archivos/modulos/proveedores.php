<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$con = conenta_login();
	require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<title>Proveedores</title>
		<?php include("../paginas/menu_de_empresas.php");
		?>
	</head>

	<body>

		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<?php
						if (getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['w'] == 1) {
						?>
							<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#modalProveedor" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nuevo</button>
						<?php
						}
						?>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Proveedores</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/proveedores.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="nombre">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Razon social, ruc, nombre comercial, dirección" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<div class="col-md-1">
								<?php
								if (getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['r'] == 1) {
								?>
									<a href="../excel/proveedores.php" class="btn btn-success" title='Descargar en Excel' target="_blank"><img src="../image/excel.ico" width="25" height="20"></a>
								<?php
								}
								?>
							</div>
							<span id="loader"></span>
						</div>
					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class='outer_div'></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>


	<?php
} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		function openModal() {
			document.querySelector("#titleModalProveedor").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo proveedor";
			document.querySelector("#formProveedor").reset();
			document.querySelector("#idProveedor").value = "";
			document.querySelector("#btnActionFormProveedor").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextProveedor").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormProveedor').title = "Guardar nuevo proveedor";
		}

		function editar_proveedor(id) {
			document.querySelector('#titleModalProveedor').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar proveedor";
			document.querySelector('#btnActionFormProveedor').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormProveedor').title = "Actualizar proveedor";
			document.querySelector('#btnTextProveedor').innerHTML = "Actualizar";
			document.querySelector("#formProveedor").reset();
			document.querySelector("#idProveedor").value = id;

			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/proveedores.php?action=datos_editar_proveedor&id_proveedor=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objProveedor = objData.data;
						document.querySelector("#idProveedor").value = objProveedor.id_proveedor;
						document.querySelector("#tipo_id").value = objProveedor.tipo_id_proveedor;
						document.querySelector("#ruc_proveedor").value = objProveedor.ruc_proveedor;
						document.querySelector("#razon_social").value = objProveedor.razon_social;
						document.querySelector("#nombre_comercial").value = objProveedor.nombre_comercial;
						document.querySelector("#tipo_empresa").value = objProveedor.tipo_empresa;
						document.querySelector("#direccion_proveedor").value = objProveedor.dir_proveedor;
						document.querySelector("#mail_proveedor").value = objProveedor.mail_proveedor;
						document.querySelector("#telefono_proveedor").value = objProveedor.telf_proveedor;
						document.querySelector("#plazo").value = objProveedor.plazo;
						document.querySelector("#listBanco").value = objProveedor.id_banco;
						document.querySelector("#listTipoCta").value = objProveedor.tipo_cta;
						document.querySelector("#txtNumeroCuenta").value = objProveedor.numero_cta;
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function load(page) {
			var q = $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/proveedores.php?action=buscar_proveedores&page=' + page + '&q=' + q,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}
		function eliminar_proveedor(id) {
			var q = $("#q").val();
			if (confirm("Realmente deseas eliminar el proveedor?")) {
				$.ajax({
					type: "GET",
					url: "../ajax/proveedores.php",
					data: "action=eliminar_proveedor&id_proveedor=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}
	</script>
