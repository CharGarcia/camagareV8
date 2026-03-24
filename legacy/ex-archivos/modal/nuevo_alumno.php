<?php
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
?>
<div class="modal fade" data-backdrop="static" id="nuevoAlumno" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Registrar nuevo alumno</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_alumno" name="guardar_alumno">
		<div id="resultados_ajax_alumnos"></div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Cédula/pasaporte</label>
					<div class="col-sm-3">
					<select class="form-control" name="tipo_id" id="tipo_id" required>
						<option value="">Seleccione</option>
						<option value="1" selected>Cédula</option>
						<option value="2">Pasaporte</option>
						<option value="3">Otro</option>
					</select>
					</div>
					<div class="col-sm-5">
					   <input type="text" class="form-control" id="cedula" name="cedula" placeholder="número de identidad" maxlength="150" title="identidad" required >
					</div>
				 </div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Nombres Apellidos</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="nombres_alumno" name="nombres_alumno" placeholder="Nombres" maxlength="150" title="Nombre del alumno" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Fecha de nacimiento</label>
					<div class="col-sm-3">
					   <input type="text" class="form-control" id="fecha_nacimiento_alumno" name="fecha_nacimiento_alumno" placeholder="F. Nacimiento" required value="<?php echo date("d-m-Y");?>" >
					</div>
				 
					<label for="" class="col-sm-2 control-label"> Ingreso</label>
					<div class="col-sm-3">
					   <input type="text" class="form-control" id="fecha_ingreso_alumno" name="fecha_ingreso_alumno" placeholder="Fecha ingreso" required value="<?php echo date("d-m-Y");?>">
					</div>
				 </div>
				 <div class="form-group">
				 	<label for="" class="col-sm-3 control-label"> Sexo/género</label>
					  <div class="col-sm-3">
						 <select class="form-control" name="sexo_alumno" id="sexo_alumno">
								<option value="0">Seleccione</option>
								<option value="M" selected>Masculino</option>
								<option value="F">Femenino</option>
						</select>
					  </div>
					<label class="col-sm-2 control-label"> Horario</label>
					   <div class="col-sm-3">
						<select class="form-control" name="horario_alumno" id="horario_alumno" required>
						<?php
						$sql = "SELECT * FROM horarios_alumnos where ruc_empresa = '".$ruc_empresa."' ;";
						$res = mysqli_query($con,$sql);
						?> 
						<option value="">Seleccione horario</option>
						<?php
						while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_horario'] ?>" selected><?php echo $o['nombre_horario'] ?> </option>
						<?php
						}
						?>
					</select>
					</div>
				</div>
				<div class="form-group">
						<label for="" class="col-sm-3 control-label"> Campus</label>
					  <div class="col-sm-3">			  
					  <select class="form-control" name="sucursal_alumno" id="sucursal_alumno" required>
						<?php
						$sql = "SELECT * FROM campus_alumnos where ruc_empresa = '$ruc_empresa' ;";
						$res = mysqli_query($con,$sql);
						?> 
						<option value="">Seleccione campus</option>
						<?php
						while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_campus'] ?>" selected><?php echo $o['nombre_campus'] ?> </option>
						<?php
						}
						?>
					</select>
					  </div>
						<label for="" class="col-sm-2 control-label"> Nivel</label>
					  <div class="col-sm-3">
					<select class="form-control" name="paralelo_alumno" id="paralelo_alumno" required>
						<?php
						$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '$ruc_empresa' ;";
						$res = mysqli_query($con,$sql);
						?> 
						<option value="">Seleccione nivel</option>
						<?php
						while($o = mysqli_fetch_assoc($res)){
						?>
						<option value="<?php echo $o['id_nivel'] ?>" selected><?php echo $o['nombre_nivel'] ?> </option>
						<?php
						}
						?>
					</select>
					  </div>
				</div>
				<div class="form-group">
				<label class="col-sm-3 control-label"> facturar con</label>
				<div class="col-md-8">
							<select class="form-control" id="serie_facturar" name="serie_facturar" required>
								<option value="" Selected>Seleccione sucursal</option>
								<?php
								$sql_sucursal = "SELECT * FROM sucursales WHERE ruc_empresa='".$ruc_empresa."'";
								$respuesta_sucursales = mysqli_query($con,$sql_sucursal);
								while($datos_sucursales = mysqli_fetch_assoc($respuesta_sucursales)){
								?>	
								<option value="<?php echo $datos_sucursales['serie']?>" selected><?php echo $datos_sucursales['nombre_sucursal'] ?></option> 
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
