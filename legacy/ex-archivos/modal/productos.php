	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="productos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="titleModalProducto"></h4>
				</div>

				<div class="modal-body">
					<div id="resultados_ajax_productos"></div>
					<form class="form-horizontal" method="POST" id="guardar_producto" name="guardar_producto">
						<input type="hidden" id="id_producto" name="id_producto">

						<div role="tabproducto">
							<!-- Nav tabs -->
							<ul class="nav nav-tabs" role="tablist">
								<li role="producto" class="active"><a href="#generalProducto" aria-controls="generalProducto" role="tab" data-toggle="tab">General</a></li>
								<li role="producto"><a href="#adicionalesProducto" aria-controls="adicionalesProducto" role="tab" data-toggle="tab">Adicionales</a></li>
							</ul>
							<!-- Tab panes -->
							<div class="tab-content">
								<div role="tabproducto" class="tab-pane active" id="generalProducto">
									<div class="panel panel-default">
										<div class="modal-body">
											<div class="form-group">
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Código</b></span>
														<input type="text" class="form-control" id="codigo_producto" name="codigo_producto" placeholder="Código principal" onchange="verifica_producto();" maxlength="25" title="25 carácteres máximo" value="">
													</div>
												</div>
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Cód_Barras</b></span>
														<input type="text" class="form-control" id="codigo_auxiliar" name="codigo_auxiliar" placeholder="Opcional" onchange="verifica_producto();" maxlength="25" title="25 carácteres máximo">
													</div>
												</div>
											</div>
											<div class="form-group">
												<div class="col-sm-12">
													<div class="input-group">
														<span class="input-group-addon"><b>Descripción</b></span>
														<textarea type="text" class="form-control" id="nombre_producto" name="nombre_producto" title="Nombre del producto o servicio" maxlength="300" placeholder="Max 300 caracteres"></textarea>
													</div>
												</div>
											</div>
											<div class="form-group">
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Tipo</b></span>
														<?php
														$conexion = conenta_login();
														?>
														<select class="form-control" name="tipo_producto" id="tipo_producto">
															<?php
															//$sql = "SELECT * FROM tipo_produccion";
															$sql_produccion = mysqli_query($conexion, "SELECT * FROM tipo_produccion");
															?> <option value="">Seleccione</option>
															<?php
															while ($row = mysqli_fetch_assoc($sql_produccion)) {
															?>
																<option value="<?php echo $row['codigo'] ?>"><?php echo $row['nombre'] ?> </option>
															<?php
															}
															?>
														</select>
													</div>
												</div>
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Tarifa IVA</b></span>
														<select class="form-control" name="iva_producto" id="iva_producto">
															<?php
															$tarifa_iva = mysqli_query($conexion, "SELECT * FROM tarifa_iva WHERE status ='1' order by porcentaje_iva asc");
															?>
															<option value="">Seleccione</option>
															<?php
															while ($row = mysqli_fetch_assoc($tarifa_iva)) {
															?>
																<option value="<?php echo $row['codigo'] ?>"><?php echo $row['tarifa'] ?> </option>
															<?php
															}
															?>
														</select>
													</div>
												</div>

											</div>
											<div class="form-group">
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Precio sin IVA</b></span>
														<input type="text" class="form-control text-right" id="precio_producto" name="precio_producto" placeholder="Precio" oninput="precio_sin_impuesto();" title="Precio de venta sin impuestos">
													</div>
												</div>
												<div class="col-sm-6" id="label_iva_producto">
													<div class="input-group">
														<span class="input-group-addon"><b>Precio con IVA</b></span>
														<input type="text" class="form-control text-right" id="precio_final" name="precio_final" placeholder="Precio" oninput="precio_con_impuesto();" title="Precio de venta con impuestos">
													</div>
												</div>
											</div>

											<div class="form-group">
												<div class="col-sm-6" id="label_medida_producto">
													<div class="input-group">
														<span class="input-group-addon"><b>Medida</b></span>
														<?php
														//$conexion = conenta_login();
														?>
														<select class="form-control" title="Tipo de medida del producto" name="tipo_medida_producto" id="tipo_medida_producto">
															<?php
															$res_tipo = mysqli_query($conexion, "SELECT * FROM tipo_medida");
															?>
															<option value="">Seleccione</option>
															<?php
															while ($tipo = mysqli_fetch_assoc($res_tipo)) {
															?>
																<option value="<?php echo $tipo['id_tipo'] ?>"><?php echo strtoupper($tipo['nombre_tipo']) ?> </option>
															<?php
															}
															?>
														</select>
													</div>
												</div>
												<div class="col-sm-6" id="label_unidad_producto">
													<div class="input-group">
														<span class="input-group-addon"><b>Unidad</b></span>
														<select class="form-control" title="Unidad de medida del producto" name="unidad_medida_producto" id="unidad_medida_producto">
															<option value="">Seleccione</option>
														</select>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
								<div role="tabproducto" class="tab-pane" id="adicionalesProducto">
									<div class="panel panel-default">
										<div class="modal-body">
											<div class="form-group">
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Status</b></span>
														<select class="form-control" title="Status" name="status_producto" id="status_producto">
															<option value="1" selected>Activo</option>
															<option value="2">Pasivo</option>
														</select>
													</div>
												</div>
											</div>
											<div class="form-group">
												<div class="col-sm-6" id="label_marca_producto">
													<div class="input-group">
														<span class="input-group-addon"><b>Marca</b></span>
														<select class="form-control" title="Marca" name="marca_producto" id="marca_producto">
															<?php
															$sql_marca = mysqli_query($conexion, "SELECT * FROM marca where ruc_empresa='" . $ruc_empresa . "' order by nombre_marca asc");
															?> <option value="">Seleccione</option>
															<?php
															while ($tipo = mysqli_fetch_assoc($sql_marca)) {
															?>
																<option value="<?php echo $tipo['id_marca'] ?>"><?php echo strtoupper($tipo['nombre_marca']) ?> </option>
															<?php
															}
															?>
														</select>
													</div>
												</div>
												<div class="col-sm-6">
													<div class="input-group">
														<span class="input-group-addon"><b>Grupo</b></span>
														<select class="form-control" title="Grupo" name="grupo_producto" id="grupo_producto">
															<?php
															$sql_grupo = mysqli_query($conexion, "SELECT * FROM grupo_familiar_producto where ruc_empresa='" . $ruc_empresa . "' order by nombre_grupo asc");
															?> <option value="">Seleccione</option>
															<?php
															while ($tipo = mysqli_fetch_assoc($sql_grupo)) {
															?>
																<option value="<?php echo $tipo['id_grupo'] ?>"><?php echo strtoupper($tipo['nombre_grupo']) ?> </option>
															<?php
															}
															?>
														</select>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!--</div>-->
				</div>
				<div class="modal-footer">
					<span id="resultados_ajax_guarda_producto"></span>
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="button" class="btn btn-primary" onclick="guarda_producto();" id="btnActionFormProducto"><span id="btnTextProducto"></span></button>
				</div>
				</form>
			</div>
		</div>
	</div>
	<script>
		$(document).ready(function() {
			// Ocultar labels al cargar
			$("#label_marca_producto, #label_medida_producto, #label_unidad_producto, #label_iva_producto").hide();

		});

		//para mostrar las unidades de medida de acuerdo al seleccionado

		$(function() {
			$('#tipo_medida_producto').change(function() {
				var tipo_medida = $("#tipo_medida_producto").val();
				var unidad_medida = 0;
				$.post('../ajax/productos.php?action=tipo_medida', {
					tipo_medida: tipo_medida,
					id_unidad_medida: unidad_medida
				}).done(function(respuesta) {
					$("#unidad_medida_producto").html(respuesta);
				});
			});

			//cuando cambie la tarifa de iva
			$(function() {
				var porcentaje = 0;
				$('#iva_producto').change(function() {
					$("#precio_producto").val('');
					$("#precio_final").val('');
					document.getElementById('precio_producto').focus();
					var tarifa_iva = $("#iva_producto").val();
					$.post('../ajax/productos.php?action=porcentajes_iva', {
						codigo: tarifa_iva
					}).done(function(res) {
						if (res > 0) {
							document.getElementById("label_iva_producto").style.display = "";
						} else {
							document.getElementById("label_iva_producto").style.display = "none";
						}
					});
				});
			});

			//para cuando cambie el tipo de transaccion aparezca o desaparezca la unidad_medida	
			$('#tipo_producto').change(function() {
				var tipo = $("#tipo_producto").val();
				if (tipo == '01') {
					document.getElementById("label_marca_producto").style.display = "";
					document.getElementById("label_medida_producto").style.display = "";
					document.getElementById("label_unidad_producto").style.display = "";
				}
				if (tipo == '02') {
					document.getElementById("label_marca_producto").style.display = "none";
					document.getElementById("label_medida_producto").style.display = "none";
					document.getElementById("label_unidad_producto").style.display = "none";
				}
			});
		});


		function precio_sin_impuesto() {
			var tipo_iva = $("#iva_producto").val();
			var precio_sin_impuesto = document.getElementById("precio_producto").value;
			var precio_con_impuesto = document.getElementById("precio_final").value;
			//Inicia validacion
			if (tipo_iva == '') {
				alert('Seleccione un tarifa de IVA.');
				$("#precio_producto").val("");
				document.getElementById('iva_producto').focus();
				return false;
			}

			$.post('../ajax/productos.php?action=porcentajes_iva', {
				codigo: tipo_iva
			}).done(function(porcentaje) {
				if (porcentaje > 0) {
					$("#precio_final").val((precio_sin_impuesto * (porcentaje / 100 + 1)).toFixed(4));
				} else {
					$("#precio_final").val((precio_sin_impuesto * 1).toFixed(4));
				}
			});
		}

		function precio_con_impuesto() {
			var tipo_iva = $("#iva_producto").val();
			var precio_sin_impuesto = document.getElementById("precio_producto").value;
			var precio_con_impuesto = document.getElementById("precio_final").value;
			//Inicia validacion
			if (tipo_iva == '') {
				alert('Seleccione una tarifa de IVA.');
				$("#precio_producto").val("");
				document.getElementById('iva_producto').focus();
				return false;
			}

			$.post('../ajax/productos.php?action=porcentajes_iva', {
				codigo: tipo_iva
			}).done(function(porcentaje) {
				if (porcentaje > 0) {
					$("#precio_producto").val((precio_con_impuesto / (porcentaje / 100 + 1)).toFixed(4));
				} else {
					$("#precio_producto").val((precio_con_impuesto * 1).toFixed(4));
				}
			});

		}

		function guarda_producto() {
			$('#btnTextProducto').attr("disabled", true);
			var id_producto = $("#id_producto").val();
			var codigo_producto = $("#codigo_producto").val();
			var codigo_auxiliar = $("#codigo_auxiliar").val();
			var nombre_producto = $("#nombre_producto").val();
			var iva_producto = $("#iva_producto").val();
			var precio_producto = $("#precio_producto").val();
			var tipo_producto = $("#tipo_producto").val();
			var marca_producto = $("#marca_producto").val();
			var grupo_producto = $("#grupo_producto").val();
			var tipo_medida_producto = $("#tipo_medida_producto").val();
			var unidad_medida_producto = $("#unidad_medida_producto").val();
			var status_producto = $("#status_producto").val();

			$.ajax({
				type: "POST",
				url: "../ajax/productos.php?action=guardar_producto",
				data: "id_producto=" + id_producto + "&codigo_producto=" + codigo_producto +
					"&nombre_producto=" + encodeURIComponent(nombre_producto) + "&iva_producto=" + iva_producto +
					"&precio_producto=" + precio_producto + "&tipo_producto=" +
					tipo_producto + "&marca_producto=" + marca_producto + "&tipo_medida_producto=" +
					tipo_medida_producto + "&unidad_medida_producto=" + unidad_medida_producto + "&status_producto=" +
					status_producto + "&codigo_auxiliar=" + codigo_auxiliar + "&grupo_producto=" + grupo_producto,
				beforeSend: function(objeto) {
					$("#resultados_ajax_guarda_producto").html("Guardando...");
				},
				success: function(datos) {
					$("#resultados_ajax_guarda_producto").html(datos);
					$('#btnTextProducto').attr("disabled", false);
					nuevo_codigo_producto();
				}
			});
			event.preventDefault();
		}


		function verifica_producto() {
			var codigo_producto = $("#codigo_producto").val();
			var codigo_auxiliar = $("#codigo_auxiliar").val();
			$.ajax({
				type: "POST",
				url: "../ajax/productos.php?action=verificar_producto_existente",
				data: "codigo_producto=" + codigo_producto + "&codigo_auxiliar=" + codigo_auxiliar,
				beforeSend: function(objeto) {
					$("#resultados_ajax_guarda_producto").html("Verificando registrados...");
				},
				success: function(datos) {
					$("#resultados_ajax_guarda_producto").html(datos);
				}
			});
			//event.preventDefault();
		}


		function nuevo_codigo_producto() {
			$.ajax({
				type: "POST",
				url: "../ajax/productos.php?action=nuevo_codigo",
				beforeSend: function() {
					$("#resultados_ajax_guarda_producto").html("Creando nuevo código...");
				},
				success: function(datos) {
					$("#codigo_producto").val(datos); // ✅ Muestra el código en el input
					$("#resultados_ajax_guarda_producto").html('');
				},
				error: function(xhr, status, error) {
					console.log("❌ Error AJAX:", error);
				}
			});
		}
	</script>