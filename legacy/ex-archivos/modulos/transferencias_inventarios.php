<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<!DOCTYPE html>
	<html lang="en">

	<head>
		<meta charset="utf-8">
		<title>Transferencias inventarios</title>
		<?php
		include("../paginas/menu_de_empresas.php");
		$con = conenta_login();
		?>
	</head>

	<body>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class='glyphicon glyphicon-list-alt'></i> Transferencias entre bodegas</h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal" role="form" method="POST" target="_blank" action="">
						<div class="form-group row">
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Existencia</b></span>
									<input type="hidden" id="id_producto" name="id_producto">
									<select class="form-control input-sm" id="tipo_existencia" name="tipo_existencia" required>
										<option value="1" selected> Por lote</option>
									</select>
								</div>
							</div>
							<div class="col-sm-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Bodega</b></span>
									<select class="form-control input-sm" id="id_bodega_existente">
										<?php
										$sql_bod_final = mysqli_query($con, "SELECT * FROM bodega WHERE ruc_empresa= '" . $ruc_empresa . "' order by nombre_bodega asc");
										while ($row_bod_final = mysqli_fetch_assoc($sql_bod_final)) {
										?>
											<option value="<?php echo $row_bod_final['id_bodega'] ?>"><?php echo strtoupper($row_bod_final['nombre_bodega']) ?> </option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="input-group">
									<span class="input-group-addon"><b>Producto</b></span>
									<input type="text" class="form-control input-sm" name="producto" id="producto" onkeyup='agregar_productos();' placeholder="Código, nombre" autocomplete="off" title="Buscar producto por código o nombre">
								</div>
							</div>
							<div class="col-sm-2" id="label_lote">
								<div class="input-group">
									<span class="input-group-addon"><b>Lote</b></span>
									<input type="text" class="form-control input-sm" name="lote" id="lote" placeholder="Lote" autocomplete="off">
								</div>
							</div>
							<div class="col-sm-2" id="label_caducidad">
								<div class="input-group">
									<span class="input-group-addon"><b>Caducidad</b></span>
									<input type="text" class="form-control input-sm" name="caducidad" id="caducidad" placeholder="Caducidad" autocomplete="off">
								</div>
							</div>

							<div class="col-sm-1">
								<button type="button" class="btn btn-info btn-sm" onclick='mostrar_existencia();'><span class="glyphicon glyphicon-search"></span> Ver</button>
								</button><span id="loader"></span>
							</div>

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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
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
		});

		//para buscar productos
		function agregar_productos() {
			var keycode = event.keyCode;
			var codigo_producto = $("#producto").val();

			if (keycode == '13') {
				let request = (window.XMLHttpRequest) ?
					new XMLHttpRequest() :
					new ActiveXObject('Microsoft.XMLHTTP');
				let ajaxUrl = '../ajax/buscar_orden_mecanica.php?action=bar_code&codigo_producto=' + codigo_producto;
				request.open("GET", ajaxUrl, true);
				request.send();
				request.onreadystatechange = function() {
					if (request.readyState == 4 && request.status == 200) {
						let objData = JSON.parse(request.responseText);
						if (objData.status) {
							let objProducto = objData;

							document.querySelector("#id_producto").value = objProducto.id_producto;
							document.querySelector("#producto").value = objProducto.nombre_producto;
						} else {
							$.notify(objData.msg, "error");
						}
					}
					return false;
				}
			}

			$("#producto").autocomplete({
				source: '../ajax/productos_autocompletar_inventario.php',
				minLength: 2,
				select: function(event, ui) {
					event.preventDefault();
					$('#producto').val(ui.item.nombre);
					$('#id_producto').val(ui.item.id);
					mostrar_existencia();
				}
			});

		}

		$("#producto").on("keydown", function(event) {
			if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
				$("#producto").val("");
				$("#id_producto").val("");
				mostrar_existencia();
			}
			if (event.keyCode == $.ui.keyCode.DELETE) {
				$("#producto").val("");
				$("#id_producto").val("");
				mostrar_existencia();
			}
		});

		function mostrar_existencia() {
			var tipo = $("#tipo_existencia").val();
			var id_producto = $("#id_producto").val();
			var nombre_producto = $("#producto").val();
			var por = $("#por").val();
			var ordenado = $("#ordenado").val();
			var lote = $("#lote").val();
			var caducidad = $("#caducidad").val();
			var bodega = $("#id_bodega_existente").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_existencias_inventarios.php?action=saldos_para_transferencias&tipo=' + tipo +
					'&id_producto=' + id_producto + '&lote=' + lote + '&caducidad=' + caducidad + '&bodega=' + bodega,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif">');
				},
				success: function(datos) {
					$(".outer_div").html(datos).fadeIn('slow');
					$('#loader').html('');
				}
			});
		}


		function transferir(id) {
			var id_producto = $("#id_producto").val();
			var existencia = $("#existencia" + id).val();
			var id_medida_transferir = $("#medida_transferir" + id).val();
			var id_bodega_transferir = $("#id_bodega_transferir" + id).val();
			var id_bodega_existente = $("#id_bodega_existente").val();
			var id_medida_producto = $("#id_medida_entrada" + id).val();
			var lote = $("#lote" + id).val();

			if (id_producto == "") {
				$.notify('Seleccione un producto.', 'error')
				document.getElementById('producto').focus();
				return false;
			}

			var cantidad = $("#cantidad_transferir" + id).val();

			if (cantidad == "") {
				$.notify('Ingrese cantidad.', 'error')
				document.getElementById('cantidad_transferir' + id).focus();
				return false;
			}


			if (cantidad == 0) {
				$.notify('Ingrese cantidad mayor a cero.', 'error')
				document.getElementById('cantidad_transferir' + id).focus();
				return false;
			}

			if (cantidad < 0) {
				$.notify('Ingrese cantidad mayor a cero.', 'error')
				document.getElementById('cantidad_transferir' + id).focus();
				return false;
			}

			if (parseFloat(cantidad) > parseFloat(existencia)) {
				$.notify('La cantidad ingresada es mayor a la existencia.', 'error')
				document.getElementById('cantidad_transferir' + id).focus();
				return false;
			}

			if (id_bodega_existente == id_bodega_transferir) {
				$.notify('La bodega que envia debe ser diferente a la que recibe', 'error')
				return false;
			}

			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/buscar_existencias_inventarios.php?action=transferir&id_producto=' + id_producto + '&cantidad=' +
					cantidad + '&id_medida_transferir=' + id_medida_transferir + '&id_bodega_transferir=' +
					id_bodega_transferir + '&id_bodega_existente=' + id_bodega_existente + '&id=' + id +
					'&existencia=' + existencia + '&id_medida_producto=' + id_medida_producto + '&lote=' + lote,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Transfiriendo...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					mostrar_existencia();
				}
			});
			event.preventDefault();
		}


		$('#id_bodega_existente').change(function() {
			mostrar_existencia();
		});
	</script>