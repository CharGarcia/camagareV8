<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Maquila</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>
	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#maquila" onclick="nueva_maquila();"><span class="glyphicon glyphicon-plus"></span> Nueva Maquila</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Maquila</h4>
				</div>
				<div class="panel-body">
					<?php
					include("../modal/maquila.php");
					?>
					<form class="form-horizontal" method="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="hidden" id="ordenado" value="enc.id">
								<input type="hidden" id="por" value="asc">
								<div class="input-group">
									<input type="text" class="form-control" id="q" placeholder="Proveedor, factura, referencia, producto" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
									</span>
								</div>
							</div>
							<span id="loader"></span>
						</div>
					</form>
					<div id="resultados"></div>
					<div class="outer_div"></div>
				</div>
			</div>
		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>

<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

	
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
	
		function nueva_maquila() {
			document.querySelector("#titleModalMaquila").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Maquila";
			document.querySelector("#guardar_maquila").reset();
			document.querySelector("#id_maquila").value = "";
			document.querySelector("#btnActionFormMaquila").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextMaquila").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormMaquila').title = "Guardar Maquila";
			$.ajax({
				url: "../ajax/maquila.php?action=nueva_maquila",
				beforeSend: function(objeto) {
					$("#resultados_ajax_guarda_maquila").html("Cargando...");
				},
				success: function(data) {
					$('#resultados_ajax_guarda_maquila').html('');
					$('#detalle_pagos').html('');
					$('#detalle_gastos').html('');
				}
			});

		}

		
		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/maquila.php?action=buscar_maquila&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');

				}
			})
		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			$("#loader").fadeIn('slow');
			var value_por = document.getElementById('por').value;
			if (value_por == "asc") {
				$("#por").val("desc");
			}
			if (value_por == "desc") {
				$("#por").val("asc");
			}
			load(1);
		}
		

		function editar_maquila(id) {
			document.querySelector('#titleModalMaquila').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar maquila";
			document.querySelector("#guardar_maquila").reset();
			document.querySelector("#id_maquila").value = id;
			document.querySelector('#btnActionFormMaquila').classList.replace("btn-primary", "btn-info");
			document.querySelector("#btnTextMaquila").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
			let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/maquila.php?action=datos_editar&id_maquila=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objMaquila = objData.data;
						document.querySelector("#id_maquila").value = id;
						document.querySelector("#status").value = objMaquila.status;
						document.querySelector("#referencia").value = objMaquila.referencia;
						document.querySelector("#factura").value = objMaquila.factura;
						document.querySelector("#id_proveedor").value = objMaquila.id_proveedor;
						document.querySelector("#proveedor").value = objMaquila.proveedor;
						document.querySelector("#id_producto").value = objMaquila.id_producto;
						document.querySelector("#producto").value = objMaquila.producto;
						document.querySelector("#fecha_inicio").value = objMaquila.fecha_inicio;
						document.querySelector("#fecha_entrega").value = objMaquila.fecha_entrega;
						document.querySelector("#cantidad").value = objMaquila.cantidad;
						document.querySelector("#bultos").value = objMaquila.bultos;
						document.querySelector("#precio_costo").value = objMaquila.precio_costo;
						document.querySelector("#precio_venta").value = objMaquila.precio_venta;
						document.querySelector("#utilidad_por_unidad").value = objMaquila.utilidad_por_unidad;
						document.querySelector("#total_venta").value = objMaquila.total_venta;
						document.querySelector("#total_costos").value = objMaquila.total_costos;
						document.querySelector("#total_gastos").value = objMaquila.total_gastos;
						document.querySelector("#utilidad_neta").value = objMaquila.utilidad_neta;
						document.querySelector("#por_pagar").value = objMaquila.por_pagar;
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}

						//esperar 2 segundos para cargar
			setTimeout(function() {
				$.ajax({
					url: "../ajax/maquila.php?action=muestra_pagos",
					beforeSend: function(objeto) {
						$("#detalle_pagos").html("Cargando...");
					},
					success: function(pagos) {
						$('#detalle_pagos').html(pagos);
					}
				});


				$.ajax({
					url: "../ajax/maquila.php?action=muestra_gastos",
					beforeSend: function(objeto) {
						$("#detalle_gastos").html("Cargando...");
					},
					success: function(gastos) {
						$('#detalle_gastos').html(gastos);
					}
				});

			}, 1000);
		
		}

		function eliminar_maquila(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea anular?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/maquila.php?action=eliminar_maquila",
					data: "id_maquila=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#loader").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#loader").html(datos);
					}
				});
			}
		}

	</script>