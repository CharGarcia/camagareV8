<?php
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
?>
<div class="modal fade" data-backdrop="static" id="editarAlumno" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar alumno</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="editar_alumno" name="editar_alumno">
		<div id="resultados_ajax_editar_alumnos"></div>
				<div class="form-group">
				<input type="hidden" id="mod_id_alumno" name="mod_id_alumno" >
					<label for="" class="col-sm-3 control-label"> Cédula/pasaporte</label>
					<div class="col-sm-3">
					<select class="form-control" name="mod_tipo_id" id="mod_tipo_id" required>
						<option value="0">Seleccione</option>
						<option value="1">Cédula</option>
						<option value="2">Pasaporte</option>
						<option value="3">Otro</option>
					</select>
					</div>
					<div class="col-sm-5">
					   <input type="text" class="form-control" id="mod_cedula_alumno" name="mod_cedula_alumno" placeholder="número de identidad" maxlength="150" title="identidad" required >
					</div>
				 </div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Nombres</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="mod_nombres_alumno" name="mod_nombres_alumno" placeholder="Nombres" maxlength="150" title="Nombre del alumno" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Fecha de nacimiento</label>
					<div class="col-sm-3">
					   <input type="text" class="form-control" id="mod_fecha_nacimiento_alumno" name="mod_fecha_nacimiento_alumno" placeholder="F. Nacimiento" required >
					</div>
				 
					<label for="" class="col-sm-2 control-label"> Ingreso</label>
					<div class="col-sm-3">
					   <input type="text" class="form-control" id="mod_fecha_ingreso_alumno" name="mod_fecha_ingreso_alumno" placeholder="Fecha ingreso" required >
					</div>
				 </div>
				 <div class="form-group">
				 	<label for="" class="col-sm-3 control-label"> Sexo/género</label>
					  <div class="col-sm-3">
						 <select class="form-control" name="mod_sexo_alumno" id="mod_sexo_alumno">
								<option value="0">Seleccione</option>
								<option value="M">Masculino</option>
								<option value="F">Femenino</option>
						</select>
					  </div>
					<label class="col-sm-2 control-label"> Horario</label>
					   <div class="col-sm-3">
						<select class="form-control" name="mod_horario_alumno" id="mod_horario_alumno" required>
						<?php
						$sql = "SELECT * FROM horarios_alumnos where ruc_empresa = '$ruc_empresa' ;";
						$res = mysqli_query($con,$sql);
						?> 
						<option value="">Seleccione horario</option>
						<?php
						while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_horario'] ?>"><?php echo $o['nombre_horario'] ?> </option>
						<?php
						}
						?>
					</select>
					</div>
				</div>
				<div class="form-group">
						<label for="" class="col-sm-3 control-label"> Centro infantil</label>
					  <div class="col-sm-3">
						<select class="form-control" name="mod_sucursal_alumno" id="mod_sucursal_alumno" required>
						<?php
						$sql = "SELECT * FROM campus_alumnos where ruc_empresa = '$ruc_empresa' ;";
						$res = mysqli_query($con,$sql);
						?> 
						<option value="">Seleccione campus</option>
						<?php
						while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_campus'] ?>"><?php echo $o['nombre_campus'] ?> </option>
						<?php
						}
						?>
					</select>
					  </div>
						<label for="" class="col-sm-2 control-label"> Nivel</label>
					  <div class="col-sm-3">
					<select class="form-control" name="mod_nivel_alumno" id="mod_nivel_alumno" required>
					<?php
					$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '$ruc_empresa' ;";
					$res = mysqli_query($con,$sql);
					?> 
					<option value="">Seleccione nivel</option>
						<?php
					while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_nivel'] ?>"><?php echo $o['nombre_nivel'] ?> </option>
						<?php
						}
						?>
					</select>
					  
					  </div>
				</div>
				<div class="form-group">
				 	<label for="" class="col-sm-3 control-label"> Estado</label>
					  <div class="col-sm-3">
						 <select class="form-control" name="mod_estado_alumno" id="mod_estado_alumno" required>
								<option value="0">Seleccione</option>
								<option value="1">Activo</option>
								<option value="2">Pasivo</option>
						</select>
					  </div>
					  <label class="col-sm-2 control-label"> facturar con</label>
					 <div class="col-md-3">
							<select class="form-control" id="mod_serie_facturar" name="mod_serie_facturar" required>
								<option value="" Selected>Seleccione sucursal</option>
								<?php
								$sql_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa='".$ruc_empresa."'";
								$respuesta_sucursales = mysqli_query($con,$sql_sucursal);
								while($datos_sucursales = mysqli_fetch_assoc($respuesta_sucursales)){
								?>	
								<option value="<?php echo $datos_sucursales['serie']?>"><?php echo $datos_sucursales['nombre_sucursal'] ?></option> 
								<?php 
								}
								?>
							</select>
					</div> 
					  
				</div>
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" id="cerrar_edita_alumno" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
