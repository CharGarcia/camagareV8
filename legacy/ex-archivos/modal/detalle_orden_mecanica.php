<div class="modal fade" data-backdrop="static" id="detalleOrdenMecanica" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Detalle de OR</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" id="orden_mecanica" >
		<div id="resultados_ajax_mecanica"></div>
				<div class="panel-group" id="accordion">
				 <div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse1" ><span class="caret"></span> Fecha y hora de entrada y salida del vehículo</a>
				  <div id="collapse1" class="panel-collapse collapse">
				  
				<div class="panel-body">
				<input type="hidden" id="mod_codigo_unico">
					
					<div class="col-sm-12">
					<div class="form-group">
						 <div class="input-group">
								<span class="input-group-addon"><b>F. Entrada</b></span>
								<input type="text" class="form-control" id="mod_fecha_entrada">
								<span class="input-group-addon"><b>Hora</b></span>
								<input type="text" class="form-control" id="mod_hora_entrada"> 												
							  <span class="input-group-addon"><b>Estado</b></span>
								<select class="form-control" title="Estado" id="mod_estado" name="mod_estado">
								<option value="EN ESPERA">EN ESPERA</option>
								<option value="EN TALLER">EN TALLER</option>
								<option value="CERRADA">CERRADA</option>
								</select>
						  </div>
						</div>
					
					</div>
					<div class="col-sm-9">
					<div class="form-group">
						 <div class="input-group">
								<span class="input-group-addon"><b>F. Salida</b></span>
								<input type="text" class="form-control" id="mod_fecha_entrega" value="<?php echo date("d-m-Y");?>">
								<span class="input-group-addon"><b>Hora</b></span>
								<input type="text" class="form-control" id="mod_hora_entrega" value="<?php echo date("h:i:s A");?>" > 								
					</div>
					</div>
					</div>
					<div class="col-sm-1">
					<a href="#" class='btn btn-info btn-sm' title='Actualizar' onclick="actualizar_fechas();"><i class="glyphicon glyphicon-refresh"></i> </a>
					</div>
					
					<div class="form-group">
						<div class="col-sm-12">
						<div id="resultados_fechas_mecanica"></div><!-- Carga gif animado -->
						</div>
						</div>
				 </div>
				 </div>
				</div>

				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse2" ><span class="caret"></span> Información del vehículo</a>
				  <div id="collapse2" class="panel-collapse collapse">
					<div class="panel-body">
					<div class="form-group">
						<div class="col-sm-12">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Placa</b></span>
								<input type="text" class="form-control" id="mod_placa"> 
								<span class="input-group-addon"><b>Marca</b></span>
								<input type="text" class="form-control" id="mod_marca">
								<span class="input-group-addon"><b>Año</b></span>
								<input type="text" class="form-control" id="mod_anio"> 								
						  </div>
						</div>
					 </div>
					 <div class="form-group">
						<div class="col-sm-11">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Propietario</b></span>
								<input type="text" class="form-control" id="mod_propietario" > 
								<span class="input-group-addon"><b>Chasis</b></span>
								<input type="text" class="form-control" id="mod_chasis">
						  </div>
						</div>
						<div class="col-sm-1">
						<a href="#" class='btn btn-info btn-sm' title='Actualizar' onclick="actualizar_vehiculo();"><i class="glyphicon glyphicon-refresh"></i> </a>
						</div>
						<div class="form-group">
						<div class="col-sm-12">
						<div id="resultados_vehiculo_mecanica"></div><!-- Carga gif animado -->
						</div>
						</div>
					 </div>
					</div>
				 </div>
				</div>
				
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse3" ><span class="caret"></span> Información del usuario</a>
				  <div id="collapse3" class="panel-collapse collapse">
					<div class="panel-body">
					<div class="form-group">
						<div class="col-sm-11">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Nombres</b></span>
								<input type="text" class="form-control" id="mod_usuario" > 
								<span class="input-group-addon"><b>Movil</b></span>
								<input type="text" class="form-control" id="mod_telefono">
								<span class="input-group-addon"><b>Mail</b></span>
								<input type="text" class="form-control" id="mod_correo_usuario">
							
						  </div>
						</div>
						<div class="col-sm-1">
						<a href="#" class='btn btn-info btn-sm' title='Actualizar' onclick="actualizar_usuario();"><i class="glyphicon glyphicon-refresh"></i> </a>
						</div>
						<div class="form-group">
						<div class="col-sm-12">
						<div id="resultados_usuario_mecanica"></div><!-- Carga gif animado -->
						</div>
						</div>
					 </div>
					 </div>
				 </div>
				</div>
				
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse4" ><span class="caret"></span> Detalle de observaciones</a>
				  <div id="collapse4" class="panel-collapse collapse">
					<div class="panel-body">
						<div class="form-group">
							<div class="col-sm-11">	
								<div class="input-group">
								  <span class="input-group-addon"><b>Concepto</b></span>
									<input type="text" class="form-control" id="concepto" > 			
									<span class="input-group-addon"><b>detalle</b></span>
									<input type="text" class="form-control" id="detalle" >					
								</div>
							</div>
							<div class="col-sm-1">
							<a href="#" class='btn btn-info btn-sm' title='Actualizar' onclick="agregar_detalle_observaciones();"><i class="glyphicon glyphicon-plus"></i> </a>
							</div>
						</div>
						<div class="form-group">
						<div class="col-sm-12">
						<div id="muestra_detalle_observaciones_mecanica"></div><!-- Carga gif animado -->
						<div class="outer_divdet" ></div><!-- Datos ajax Final -->
						</div>
						</div>
					 </div>
					 </div>
				 </div>
								
				<div class="panel panel-info">
					<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#collapse5" ><span class="caret"></span> Proxima cita</a>
				  <div id="collapse5" class="panel-collapse collapse">
					<div class="panel-body">
					<div class="form-group">
						<div class="col-sm-4">	
						 <div class="input-group">
							  <span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control" id="proxima_cita" value="<?php echo date("d-m-Y");?>"> 			
						  </div>
						</div>
						<div class="col-sm-7">	
						 <div class="input-group">
								<span class="input-group-addon"><b>Recomendaciones proxima cita</b></span>
								<textarea type="text" class="form-control" id="obs_proxima_cita" ></textarea>					
						  </div>
						</div>
						<div class="col-sm-1">
							<a href="#" class='btn btn-info btn-md' title='Actualizar' onclick="actualizar_proxima_cita();"><i class="glyphicon glyphicon-refresh"></i> </a>
							</div>
							<div class="form-group">
						<div class="col-sm-12">
						<div id="resultados_proxima_cita_mecanica"></div><!-- Carga gif animado -->
						</div>
						</div>
					 </div>
					 </div>
				 </div>
				</div>
				</div>
		</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" id="cerrar_detalle" data-dismiss="modal" >Cerrar</button>
				</div>
    </form>
	</div>
	</div>
 </div>
