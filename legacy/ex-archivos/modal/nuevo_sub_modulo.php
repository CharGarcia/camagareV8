	<div class="modal fade" id="nuevosubModulo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
	<div class="modal-content">

		<div class="modal-header">
			  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <h4 class="modal-title" ><i class='glyphicon glyphicon-edit'></i> Crear nuevo sub módulo</h4>
		</div>
        <div class="modal-body">
		<form class="form-horizontal" id="nuevo_submodulo" method="POST" > 
		<div id="resultados_ajax_submodulo"></div>
					<div class="form-group">
					<label class="col-sm-4 control-label"> Dentro del Módulo</label>
						<div class="col-sm-6">
							<?php	$con = conenta_login(); ?>
							<select class="selectpicker" name="id_modulo" id="id_modulo"> 
								<?php
									$sql = "SELECT mo.nombre_modulo as nombre_modulo, mo.id_modulo as id_modulo, ic.nombre_icono as nombre_icono FROM modulos_menu mo, iconos_fontawesome ic WHERE ic.id_icono=mo.id_icono ";
									$respuesta = mysqli_query($conexion,$sql);
									while($datos_modulos = mysqli_fetch_assoc($respuesta)){
									?>
								<option data-icon="<?php echo $datos_modulos['nombre_icono']?>" value="<?php echo $datos_modulos['id_modulo']?>" ><?php echo $datos_modulos['nombre_modulo'] ?></option>
								<?php 
									}
									?>
							</select>			
						</div>
					</div>
					<div class="form-group">
					<label class="col-sm-4 control-label"> Nombre de sub módulo</label>
						<div class="col-sm-6">
					<input type="text" class="form-control" id="nombre_submodulo" name="nombre_submodulo" maxlength="100" placeholder="Nombre del sub módulo" required>
					</div>
					</div>
					<div class="form-group">
					<label class="col-sm-4 control-label"> Nombre del archivo php</label>
					<div class="col-sm-6">
					<input type="text" class="form-control" id="ruta" name="ruta" value ="/sistema/modulos/" placeholder="Ruta del Archivo php" maxlength="150" required>
					</div>
					</div>
					<div class="form-group">
						<label for="estado" class="col-sm-4 control-label">Icono</label>
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
				   <button type="submit" class="btn btn-primary" id="guarda_submodulo" value="guardar">Guardar</button>
		</div>

</form>
</div>
</div>
</div>

