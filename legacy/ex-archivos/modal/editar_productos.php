<?php 
//session_start();
$conexion = conenta_login();
?>
	<!-- Modal -->
	<div class="modal fade" id="editarProducto" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar productos o servicios</h4>
		  </div>
		  <div class="modal-body">
			<form class="form-horizontal" method="post" id="editar_producto" name="editar_producto">
			<div id="resultados_ajax2"></div>
			<input type="hidden" name="mod_id" id="mod_id">
			<div class="form-group">
				<div class="col-sm-12">
				<div class="input-group" >
				<span class="input-group-addon"><b>Código</b></span>
				  <input type="text" class="form-control" id="mod_codigo" name="mod_codigo" placeholder="Código del producto o servicio" readonly>
				</div>
				</div>
			</div>
			  <div class="form-group">
				<div class="col-sm-12">
				<div class="input-group" >
				<span class="input-group-addon"><b>Descripción</b></span>
					<textarea class="form-control" id="mod_nombre" name="mod_nombre" placeholder="Nombre del producto o servicio"></textarea>
				</div>
				</div>
				</div>	  
			  <div class="form-group">
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>Precio venta</b></span>
				  <input type="text" class="form-control" id="mod_precio" name="mod_precio" placeholder="Precio" required title="Ingresa sólo números hasta 4 decimales" >
				</div>
			  </div>
				<div class="col-sm-6">
				<div class="input-group" >
				<span class="input-group-addon"><b>IVA</b></span>
				 <select class="form-control" id="mod_iva" name="mod_iva" required >
					<?php
					$sql = "SELECT * FROM tarifa_iva ;";
					$res = mysqli_query($conexion,$sql);
					while($op_iva = mysqli_fetch_assoc($res)){
					?>
						<option value="<?php echo $op_iva['codigo'] ?>"><?php echo $op_iva['tarifa'] ?> </option>
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
				<span class="input-group-addon"><b>Tipo</b></span>
				 <select class="form-control" id="mod_tipo" name="mod_tipo">
					<?php
					$sql = "SELECT * FROM tipo_produccion ;";
					$res = mysqli_query($conexion,$sql);
					while($op_tipo = mysqli_fetch_assoc($res)){
					?>
						<option value="<?php echo $op_tipo['codigo']?>"><?php echo $op_tipo['nombre'] ?> </option>
						<?php
						}
						?>	
				</select>
				</div>
				</div>
				<div class="col-sm-6" id="label_mod_marca">
					<div class="input-group">
						<span class="input-group-addon"><b>Marca</b></span>
							<select class="form-control" title="Marca" name="mod_marca" id="mod_marca">
							<?php
								$sql_marca = mysqli_query($conexion,"SELECT * FROM marca where ruc_empresa='".$ruc_empresa."'");
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
            <div class="col-sm-6" id="label_mod_medida">
			<div class="input-group" >
				<span class="input-group-addon"><b>Medida</b></span>
             <?php
				$conexion = conenta_login();
					?>
					<select class="form-control" title="Tipo de medida del producto" name="mod_tipo_medida" id="mod_tipo_medida" >
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
            <div class="col-sm-6" id="label_mod_unidad">
			<div class="input-group" >
				<span class="input-group-addon"><b>Unidad</b></span>
					<select class="form-control" title="Unidad de medida del producto" name="mod_unidad_medida" id="mod_unidad_medida" >
					<option value="0">Seleccione</option>
					</select>
            </div>
			</div>
          </div>
		  </div>
		  <div class="modal-footer">
		  <span id="resultados_actualizar_producto"></span>
			<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
			<button type="submit" class="btn btn-primary" id="actualizar_datos">Actualizar</button>
		  </div>
		  </form>
		</div>
	  </div>
	</div>

<script>
$(document).ready(function(){
	var tipo = $("#mod_tipo").val();
		if (tipo=='02'){
		document.getElementById("label_mod_marca").style.display="none";
		document.getElementById("label_mod_medida").style.display="none";
		document.getElementById("label_mod_unidad").style.display="none";
		}		
});
//para mostrar las unidades de medida de acuerdo al seleccionado	
$( function() {
//para cuando cambie el tipo de transaccion aparezca o desaparezca la unidad_medida	
	$('#mod_tipo').change(function(){
		var tipo = $("#mod_tipo").val();
		if (tipo=='01'){
		document.getElementById("label_mod_marca").style.display="";
		document.getElementById("label_mod_medida").style.display="";
		document.getElementById("label_mod_unidad").style.display="";
		}
		if (tipo=='02'){
		document.getElementById("label_mod_marca").style.display="none";
		document.getElementById("label_mod_medida").style.display="none";
		document.getElementById("label_mod_unidad").style.display="none";
		}
	});
});
</script>