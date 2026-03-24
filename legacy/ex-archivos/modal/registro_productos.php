	<!-- Modal -->
	<div class="modal fade" id="nuevoProducto" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog" role="document" >
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Guardar nuevo producto o servicio</h4>
		  </div>
		  <div class="modal-body">
			<form class="form-horizontal" method="POST" id="guardar_producto" name="guardar_producto">
			<div id="resultados_ajax_productos"></div>
			  <div class="form-group">
				<div class="col-sm-12">
				<div class="input-group" >
				<span class="input-group-addon"><b>Código</b></span>
				  <input type="text" class="form-control" id="codigo" name="codigo" placeholder="Código del producto o servicio" maxlength="100" title="25 carácteres máximo" >
				</div>
				</div>
			  </div>
			  <div class="form-group">
				<div class="col-sm-12">
				<div class="input-group" >
				<span class="input-group-addon"><b>Descripción</b></span>
					<textarea type="text" class="form-control" id="nombre" name="nombre" title="Nombre del producto o servicio" maxlength="300" placeholder="Max 300 caracteres"></textarea>
				</div>
				</div>
				</div>
			  <div class="form-group">
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>Tipos de IVA</b></span>
				<select class="form-control" name="iva" id="iva" onchange="cambia_iva()">
					<?php
					$codigo="";
					$sql = "SELECT * FROM tarifa_iva ;";
					$res = mysqli_query($conexion,$sql);
					?> 
					<option value="">Seleccione</option>
					<?php
					while($o = mysqli_fetch_assoc($res)){
					?>
					<option value="<?php echo $o['codigo'] ?>"><?php echo $o['tarifa'] ?> </option>
					<?php
					}
					?>
				</select>
				</div>
				</div>
			  </div>
			  <div class="form-group">
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>Precio sin IVA</b></span>
				  <input type="text" class="form-control" id="precio" name="precio" placeholder="Precio" oninput="precio_sin_impuesto();" title="Precio de venta sin impuestos">
				</div>
				</div>
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>Precio con IVA</b></span>
				  <input type="text" class="form-control" id="precio_final" name="precio_final" placeholder="Precio" oninput="precio_con_impuesto();" title="Precio de venta con impuestos">
				</div>
				</div>
			  </div>
			  <div class="form-group">
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>Tipo</b></span>
				<?php
				$conexion = conenta_login();
				?>
					<select class="form-control" name="tipo" id="tipo" >
				<?php
					$sql = "SELECT * FROM tipo_produccion";
					$res = mysqli_query($conexion,$sql);
				?> <option value="">Seleccione</option>
				 <?php
					while($o = mysqli_fetch_assoc($res)){
				?>
						<option value="<?php echo $o['codigo'] ?>"><?php echo $o['nombre'] ?> </option>
						<?php
					}
				?>
					</select>
				</div>
				</div>
				<div class="col-sm-6" id="label_marca">
					<div class="input-group">
						<span class="input-group-addon"><b>Marca</b></span>
							<select class="form-control" title="Marca" name="marca" id="marca">
							<?php
								$sql_marca = mysqli_query($conexion,"SELECT * FROM marca where ruc_empresa='".$ruc_empresa."' order by nombre_marca asc");
							?> <option value="">Seleccione</option>
							 <?php
								while($tipo = mysqli_fetch_assoc($sql_marca)){
							?>
								<option value="<?php echo $tipo['id_marca'] ?>"><?php echo strtoupper ($tipo['nombre_marca']) ?> </option>
								<?php
								}
							?>
							</select>
					</div>
				</div>
			  </div>
		  <div class="form-group">
            <div class="col-sm-6" id="label_medida">
			<div class="input-group" >
				<span class="input-group-addon"><b>Medida</b></span>
             <?php
				$conexion = conenta_login();
					?>
					<select class="form-control" title="Tipo de medida del producto" name="tipo_medida" id="tipo_medida" >
					<?php
						$sql_tipo = "SELECT * FROM tipo_medida";
						$res_tipo = mysqli_query($conexion,$sql_tipo);
					?> <option value="">Seleccione</option>
					 <?php
						while($tipo = mysqli_fetch_assoc($res_tipo)){
					?>
						<option value="<?php echo $tipo['id_tipo'] ?>"><?php echo strtoupper ($tipo['nombre_tipo']) ?> </option>
						<?php
						}
					?>
					</select>
            </div>
			</div>
            <div class="col-sm-6" id="label_unidad">
			<div class="input-group" >
				<span class="input-group-addon"><b>Unidad</b></span>
					<select class="form-control" title="Unidad de medida del producto" name="unidad_medida" id="unidad_medida" >
					<option value="0">Seleccione</option>
					</select>
            </div>
			</div>
          </div>
	
		  </div>
		  <div class="modal-footer">
		  <span id="resultados_ajax_guardar"></span>
			<button type="button" class="btn btn-default" id="cerrar_nuevo_producto" data-dismiss="modal">Cerrar</button>
			<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
		  </div>
		  </form>
		</div>
	  </div>
	</div>
<script>
$(document).ready(function(){
		document.getElementById("label_marca").style.display="none";
		document.getElementById("label_medida").style.display="none";
		document.getElementById("label_unidad").style.display="none";	
});
	//para mostrar las unidades de medida de acuerdo al seleccionado
$( function() {
	$('#tipo_medida').change(function(){
		var tipo_medida = $("#tipo_medida").val();
		var unidad_medida = 0;
			$.post( '../ajax/select_tipo_medida.php', {tipo_medida: tipo_medida, id_unidad_medida: unidad_medida}).done( function( respuesta )
		{
			$("#unidad_medida").html(respuesta);
		});
	});

//para cuando cambie el tipo de transaccion aparezca o desaparezca la unidad_medida	
	$('#tipo').change(function(){
		var tipo = $("#tipo").val();
		if (tipo=='01'){	
		document.getElementById("label_marca").style.display="";
		document.getElementById("label_medida").style.display="";
		document.getElementById("label_unidad").style.display="";
		}
		if (tipo=='02'){
		document.getElementById("label_marca").style.display="none";
		document.getElementById("label_medida").style.display="none";
		document.getElementById("label_unidad").style.display="none";
		}
	});
});
</script>