<?php $conexion = conenta_login(); ?>
<!-- Modal -->
<div class="modal fade" id="myModal2" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar usuario</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="editar_usuario" name="editar_usuario">
				<div id="resultados_ajax2"></div>
					<div class="form-group">
						<label for="mod_estado" class="col-sm-3 control-label control-label">Estado</label>
						<div class="col-sm-6">
						<input type="hidden" id="mod_id" name="mod_id">
							<select class="form-control" id="mod_estado" name="mod_estado" required>
								<?php
									$sql = "SELECT * FROM estado_del_registro ;";
									$respuesta = mysqli_query($conexion,$sql);
									while($estados = mysqli_fetch_assoc($respuesta)){
									?>
								<option value="<?php echo $estados['idestado'];?>" ><?php echo $estados['nombre'];?></option>
								<?php 
									}
									?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label control-label">Tipo</label>
						<div class="col-sm-6">
							<select class="form-control" id="mod_tipo" name="mod_tipo" required>
								<?php
									$sql = "SELECT * FROM tipo_usuario ;";
									$respuesta = mysqli_query($conexion,$sql);
									while($tipo_usuarios = mysqli_fetch_assoc($respuesta)){
									?>
								<option value="<?php echo $tipo_usuarios['nombre'];?>" ><?php echo $tipo_usuarios['nombre'];?></option>
								<?php 
									}
									?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-3 control-label control-label">Nombre</label>
						<div class="col-sm-6">
							<td>
								<input class="form-control input-sm" type="text" id="mod_nombre" name="mod_nombre" required>
							</td>
						</div>
					</div>
					<div class="form-group">
						<label for="estado" class="col-sm-3 control-label">Mail</label>
						<div class="col-sm-6">
							<td>
								<input class="form-control input-sm" type="text" id="mod_mail" name="mod_mail">
							</td>
						</div>
					</div>
					<div class="form-group">
						<label for="estado" class="col-sm-3 control-label">Cédula</label>
						<div class="col-sm-6">
							<input type="text" class="form-control input-sm" id="mod_cedula" name="mod_cedula" READONLY>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-primary" id="actualizar_datos">Actualizar</button>
				</div>
			</form>
	</div>
</div>
</div>
