	<!-- Modal -->
	<div class="modal fade" id="nuevoItemMenu" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Agregar nuevo item</h4>
		  </div>
		  <div class="modal-body">
			<form class="form-horizontal" method="post" id="guardar_item_menu" name="guardar_item_menu">
						<div id="resultados_ajax"></div>
						  <div class="form-group">
							<label class="col-sm-3 control-label">Nombre</label>
							<div class="col-sm-8">
							  <input type="text" class="form-control" id="nombre_item" name="nombre_item" required>
							</div>
						  </div>
						  <div class="form-group">
							<label class="col-sm-3 control-label">Ruta</label>
							<div class="col-sm-8">
							  <input type="text" class="form-control" id="ruta_item" name="ruta_item" value="/sistema/"required>
							</div>
						  </div>
						  <div class="form-group">
							<label for="estado" class="col-sm-3 control-label">Nivel</label>
							<div class="col-sm-8">
							 <select class="form-control" id="nivel" name="nivel" required>
								<option value="1" selected >1</option>
								<option value="2" >2</option>
								<option value="3" >3</option>
							  </select>
							</div>
						  </div>
						  <div class="form-group">
							<label for="estado" class="col-sm-3 control-label">Estado</label>
							<div class="col-sm-8">
										
							 <?php	$conexion = conenta_login(); ?>
							 <select class="form-control" id="estado" name="estado" required>
									<?php
									$sql = "SELECT * FROM estado_del_registro ;";
									$respuesta = mysqli_query($conexion,$sql);
									while($datos_estado = mysqli_fetch_assoc($respuesta)){
									?>	
									<option value="<?php echo $datos_estado['idestado'] ?>" ><?php echo $datos_estado['nombre'] ?></option> 
									<?php 
									}
									?>
							  </select>
							</div>
						  </div>
					  </div>
					  <div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
						<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar datos</button>
					  </div>
		  </form>
		</div>
	  </div>
	</div>
	