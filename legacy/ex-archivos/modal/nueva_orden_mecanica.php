<?php
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$sql_usuario=mysqli_query($con,"SELECT * FROM usuarios WHERE id='".$id_usuario."'");
$row_usuario = mysqli_fetch_array($sql_usuario);
$nombre_usuario=$row_usuario['nombre'];
		
?>
<div class="modal fullscreen-modal fade" data-backdrop="static" id="nuevaOrdenMecanica" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Registrar nueva orden de servicio</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="POST" id="guardar_orden_mecanica" name="guardar_orden_mecanica">
		<div id="resultados_ajax_mecanica"></div>
				<div class="panel panel-info" style ="margin-bottom: 15px; margin-top: -10px;">
				 <div class="panel-heading" style ="padding: 2px;">Datos del ingreso</div>
				 <div class="panel-body" style ="margin-bottom: -20px;">
					<div class="form-group">
						<div class="col-sm-6">	
						 <div class="input-group">
								<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control" id="fecha_entrada_vehiculo" name="fecha_entrada_vehiculo" value="<?php echo date("d-m-Y");?>">
								<span class="input-group-addon"><b>Hora</b></span>
								<input type="text" class="form-control" id="hora_entrada" name="hora_entrada" value="<?php echo date("h:i:s A");?>" > 								
						  </div>
						</div>
						
						<div class="col-sm-6">
						<div class="input-group">
								<span class="input-group-addon"><b>Asesor</b></span>
								<input type="text" class="form-control" name="nombre_asesor"  value="<?php echo $nombre_usuario; ?>"readonly>							
						  </div>
						</div>
					 </div>
				 </div>
				</div>
				<div class="panel panel-info" style ="margin-bottom: 5px; margin-top: -10px;">
				 <div class="panel-heading" style ="padding: 2px;">Datos del vehículo</div>
				 <div class="panel-body" style ="margin-bottom: -20px;">
					<div class="form-group">
						<div class="col-sm-12">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Placa</b></span>
								<input type="text" class="form-control" name="placa" id="placa"> 
								<span class="input-group-addon"><b>Marca</b></span>
								<input type="text" class="form-control" name="marca" >
								<span class="input-group-addon"><b>Año</b></span>
								<input type="text" class="form-control" name="anio" value="2022"> 								
						  </div>
						</div>
					 </div>
					 <div class="form-group">
						<div class="col-sm-12">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Propietario</b></span>
								<input type="text" class="form-control" name="propietario" value="Privado"> 
								<span class="input-group-addon"><b>Chasis</b></span>
								<input type="text" class="form-control" name="chasis" value="123456789">
							
						  </div>
						</div>
					 </div>
				 </div>
				</div>
				
				<div class="panel panel-info" style ="margin-bottom: 5px;">
				 <div class="panel-heading" style ="padding: 2px;">Datos del usuario</div>
				 <div class="panel-body" style ="margin-bottom: -20px;">
					<div class="form-group">
						<div class="col-sm-12">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Nombres y apellidos</b></span>
								<input type="text" class="form-control" name="usuario" value="Usuario final"> 
								<span class="input-group-addon"><b>Movil</b></span>
								<input type="text" class="form-control" name="contacto" value="123456789">
								<span class="input-group-addon"><b>Mail</b></span>
								<input type="text" class="form-control" name="correo_usuario" value="info@camagare.com">
							
						  </div>
						</div>
					 </div>
					 
				 </div>
				</div>
				
				<div class="panel panel-info" style ="margin-bottom: -10px;">
				 <div class="panel-heading" style ="padding: 2px;">Observaciones y condiciones de entrada</div>
				 <div class="panel-body">
					<div class="form-group">
						<div class="col-sm-4">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Estado</b></span>
								<select class="form-control" title="Estado" name="estado" id="estado">
								<option value="EN ESPERA">EN ESPERA</option>
								<option value="EN TALLER" selected>EN TALLER</option>
								</select>
						  </div>
						</div>
						<div class="col-sm-8">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Observaciones</b></span>
								<textarea type="textarea" class="form-control" name="observaciones" ></textarea>
						  </div>
						</div>
					 </div>
					 
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
