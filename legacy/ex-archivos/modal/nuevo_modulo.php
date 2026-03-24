<div class="modal fade" id="nuevoModulo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
	<div class="modal-content">
		<div class="modal-header">
			  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Crear nuevo módulo</h4>
		</div>
        <div class="modal-body">
		<form class="form-horizontal" id= "nuevo_modulo" method="post" > 
				<div id="resultados_ajax"></div>
				<div class="form-group">
				<label for="nombre" class="col-sm-2 control-label"> Nombre</label>
					<div class="col-sm-5">
					<input type="text" class="form-control" name="nombre_modulo" id="nombre_modulo" maxlength="100" placeholder="Nombre módulo" required>
					</div>
				</div>
				<div class="form-group">
						<label for="estado" class="col-sm-2 control-label">Icono</label>
						<div class="col-md-2">
							<?php	$conexion = conenta_login(); ?>
							<select class="selectpicker" id="id_icono" name="id_icono" required>
								<?php
									$sql = "SELECT * FROM iconos_fontawesome ;";
									$respuesta = mysqli_query($conexion,$sql);
									while($datos_iconos = mysqli_fetch_assoc($respuesta)){
									?>
								<option data-icon="<?php echo $datos_iconos['nombre_icono']?>" value="<?php echo $datos_iconos['id_icono']?>" ><?php echo $datos_iconos['nombre_icono'] ?></option>
								<?php 
									}
									?>
							</select>
						</div>
				</div>
		</div>
		<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guarda_modulo" value="guardar">Guardar</button>
		</div>
		</form>
	</div>
	</div>
</div>
	