<?php 
$conexion = conenta_login();
?>
<div class="modal fade bs-example-modal-lg" id="nuevoEmpleado" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Nuevo empleado</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_empleado" name="guardar_empleado">
		<div id="resultados_ajax_empleados"></div>
				<div class="form-group">
					<label for="" class="col-sm-2 control-label"> Nombres</label>
					<div class="col-sm-4">
					   <input type="text" class="form-control" id="nombres" name="nombres" placeholder="Nombres" maxlength="100" title="Nombres del empleados ( sólo letras)" required >
					</div>
					<label for="" class="col-sm-2 control-label"> Apellidos</label>
					<div class="col-sm-4">
					   <input type="text" class="form-control" id="apellidos" name="apellidos" placeholder="Apellidos" maxlength="100" title="Apellidos del empleado ( sólo letras)" required >
					</div>
				 </div>
				<div class="form-group">
					  <label for="" class="col-sm-2 control-label"> Cedula</label>
					  <div class="col-sm-4">
						 <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Cedula" maxlength="10" pattern=".{10,}" required title="10 caracteres mínimo">
					  </div>
				<label for="" class="col-sm-2 control-label"> Nacimiento:</label>
					  <div class="col-sm-4">
						 <input type="text" class="form-control" id="nacimiento" name="nacimiento" placeholder="Fecha nacimiento" value="<?php echo date("d-m-Y");?>" required>
					  </div>
				</div>
				<div class="form-group">
					
					<label for="" class="col-sm-2 control-label"> Dirección</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" id="direccion" name="direccion" placeholder="Dirección del domicilio">
					</div>
					<label for="" class="col-sm-2 control-label"> Sexo</label>
					<div class="col-sm-4">
					<select class="form-control" name="sexo" id="sexo">
				    <option value="">Seleccione sexo</option>
				 	<option value="M" > Masculino </option>
					<option value="F" > Femenino </option>
					</select>
					</div>

                 </div>
				
				<div class="form-group">
					<label for="" class="col-sm-2 control-label"> Teléfonos</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" id="telefono" name="telefono" placeholder="Teléfonos" >
					</div>
					<label for="" class="col-sm-2 control-label"> E-mail</label>
					<div class="col-sm-4">
					<input type="text" class="form-control" id="mail" name="mail" placeholder="Mail del empleado" maxlength="100" title="Mail del empleado" >
					</div>
                 
                 </div> 
				<div class="form-group">
					<label for="" class="col-sm-2 control-label"> Rama</label>
					<div class="col-sm-10">
					
				<?php
				$conexion = conenta_login();
				?>
					<select class="form-control" name="rama" id="rama">
				<?php
					$sql = "SELECT * FROM rama_trabajo order by nombre_rama asc ;";
					$res = mysqli_query($conexion,$sql);
				?> <option value="">Seleccione una rama de trabajo</option>
				 <?php
					while($rama = mysqli_fetch_assoc($res)){
				?>
						<option value="<?php echo $rama['codigo_rama'] ?> " ><?php echo $rama['nombre_rama'] ?> </option>
						<?php
					}
				?>
					</select>

		
					</div>
                 </div> 
				 <div class="form-group">
					<label for="" class="col-sm-2 control-label"> Cargo</label>
					<div class="col-sm-10">
					<select class="form-control" name="cargo_empleado" id="cargo_empleado">
				    <option value="">Seleccione un cargo</option>
					</select>
		
					</div>
                 </div>
				<div class="form-group">
					<label for="" class="col-sm-2 control-label"> Rel. Lab. IESS</label>
					<div class="col-sm-10">
					<?php
				$conexion = conenta_login();
				?>
					<select class="form-control" name="rel_lab" id="rel_lab">
				<?php
					$sql = "SELECT * FROM relacion_laboral order by nombre_rel asc ;";
					$res = mysqli_query($conexion,$sql);
				?> <option value="">Seleccione una relación laboral</option>
				 <?php
					while($relacion = mysqli_fetch_assoc($res)){
				?>
						<option value="<?php echo $relacion['codigo_rel'] ?> " ><?php echo $relacion['nombre_rel'] ?> </option>
						<?php
					}
				?>
					</select>
					
					</div>
					
				</div>
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
