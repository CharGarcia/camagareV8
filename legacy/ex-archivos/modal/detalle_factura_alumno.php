<div class="modal fade" data-backdrop="static" id="detalleFacturaAlumno" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle de factura</h4>
				</div>
			<div class="modal-body">
		<div id="resultados_generar_factura"></div>
	<h5 style="margin-bottom: 5px; margin-top: -10px;"><input id="alumno" class="form-control" readonly ></h5>
					<div class="form-group">
					<div class="panel panel-info">
					<div class="table-responsive">					
							<table class="table table-bordered">
								<tr class="info">
										<th style ="padding: 2px;">Producto</th>
										<th style ="padding: 2px;">Cantidad</th>
										<th style ="padding: 2px;">Precio</th>
										<th style ="padding: 2px;">Cuando facturar</th>
										<th style ="padding: 2px;" class="text-center">Agregar</th>
								</tr>
								<td class='col-xs-6'>
									<select class="form-control" id="id_producto" name="id_producto" required>
										<option value="" selected >Seleccione producto</option>
										<?php
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '".$ruc_empresa."' and status='1' order by nombre_producto asc;";
										$respuesta = mysqli_query($con,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['id']?>"><?php echo strtoupper($datos_producto['nombre_producto']); ?></option> 
										<?php 
										}
										?>
									</select>
								</td>
								<td class='col-xs-1'><input type="text" class="form-control input-sm" name="cantidad" id="cantidad" value="1"></td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm" name="precio" id="precio" ></td>
								<td class='col-xs-4'>												
									<select class="form-control" id="periodo" name="periodo" required>
										<option value="" >Seleccione período</option>
										<option value="02" selected>Mensual</option>
										<?php
										$sql = "SELECT * FROM periodo_a_facturar order by detalle_periodo asc";
										$respuesta = mysqli_query($con,$sql);
										while($datos_periodo = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_periodo['codigo_periodo']?>"><?php echo $datos_periodo['detalle_periodo'] ?></option> 
										<?php 
										}
										?>
									</select>
								</td>
								<td class="text-center"><a class='btn btn-info' onclick="agregar_detalle_factura_alumno()"><i class="glyphicon glyphicon-plus"></i></a></td>
							</table>
					</div>
					</div>
						<div id="muestra_detalle_factura_alumno"></div><!-- Carga gif animado -->
						<div class="outer_divdet" ></div><!-- Datos ajax Final -->
				</div>
			</div>
		<form class="form-horizontal" method="POST" id="generar_factura" name="generar_factura">
			<input type="hidden" name="id_reg_alumno" id="id_reg_alumno">
				<div class="modal-footer">
				<div class="form-group">
					<div class="col-sm-4">
						<div class="input-group">
						  <span class="input-group-addon"><b>Período/detalle: </b></span>
							<input type="text" class="form-control input-md text-right" id="detalle" name="detalle" placeholder="Periodo" title="ejemplo: 05-2018" value="<?php echo date("m-Y");?>">			
						</div>
					</div>
					<div class="col-sm-4">
					<div class="input-group">
						  <span class="input-group-addon"><b>Opción: </b></span>
						<select class="form-control" id="periodo_facturar" name="periodo_facturar" required>
							<option value="">Items a facturar</option>
							<option value="00">Todos</option>
							<option value="02"selected>Mensual</option>
							<?php
							$sql = "SELECT * FROM periodo_a_facturar order by detalle_periodo asc";
							$respuesta = mysqli_query($con,$sql);
							while($datos_periodo = mysqli_fetch_assoc($respuesta)){
							?>	
							<option value="<?php echo $datos_periodo['codigo_periodo']?>"><?php echo $datos_periodo['detalle_periodo'] ?></option> 
							<?php 
							}
							?>
						</select>
					</div>
					</div>
					
					<div class="col-sm-4">
					<span id="loader_detalle_factura_alumno"></span><!-- Carga gif animado -->
					<button type="submit" class="btn btn-info" id="guardar_datos">Generar factura</button>			
					<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_detalle_a_facturar">Cerrar</button>
					</div>

					
				</div>
				</div>
		</form>
	</div>
</div>
</div>
