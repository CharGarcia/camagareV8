<?php
//session_start();
$ruc_empresa = $_POST['ruc_empresa'];
?>
<div class="modal fade bs-example-modal-lg" id="nuevoClientefb" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Programar nuevo cliente</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_en_bloque" name="guardar_en_bloque">
		<div id="resultados_ajax_nueva_cfb"></div>
				<div class="form-group">
					<label for="" class="col-sm-1 control-label"> Cliente</label>
					<div class="col-sm-7">
					<select class="form-control" id="cliente" name="cliente" required>
									<option value="" selected >Seleccione cliente</option>
									<?php
									$sql = "SELECT * FROM clientes WHERE ruc_empresa = '$ruc_empresa';";
									$respuesta = mysqli_query($conexion,$sql);
									while($datos_cliente = mysqli_fetch_assoc($respuesta)){
									?>	
									<option value="<?php echo $datos_cliente['id']?>"><?php echo $datos_cliente['nombre'] ?></option> 
									<?php 
									}
									?>
					</select>
					</div>
				 
						<label for="" class="col-sm-1 control-label"> Período</label>
					  <div class="col-sm-3">
						  <select class="form-control" id="periodo" name="periodo" required>
										<option value="" Selected>Seleccione período</option>
										<option value="01">Semanal</option>
										<option value="02">Mensual</option>
										<option value="03">Trimestral</option>
										<option value="04">Semestral</option>
										<option value="05">Anual</option>
						  </select>
					  </div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-1 control-label"> Producto</label>
					  <div class="col-sm-5">
						<select class="form-control" id="productouno" name="productouno" required>
										<option value="" selected >Seleccione producto</option>
										<?php
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '$ruc_empresa';";
										$respuesta = mysqli_query($conexion,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['codigo_producto']?>"><?php echo $datos_producto['nombre_producto'] ?></option> 
										<?php 
										}
										?>
						</select>
					  </div>
					  
					  <label for="" class="col-sm-1 control-label"> Cantidad</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="cantidaduno" name="cantidaduno" value="0">
					</div>
					<label for="" class="col-sm-1 control-label"> Precio</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="preciouno" name="preciouno" value="0.00">
					</div>
					  
				</div>
				<div class="form-group">
				<label for="" class="col-sm-1 control-label"> Producto</label>
					  <div class="col-sm-5">
						<select class="form-control" id="productodos" name="productodos" >
										<option value="" selected >Seleccione producto</option>
										<?php
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '$ruc_empresa';";
										$respuesta = mysqli_query($conexion,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['codigo_producto']?>"><?php echo $datos_producto['nombre_producto'] ?></option> 
										<?php 
										}
										?>
						</select>
					  </div>
					  
					  <label for="" class="col-sm-1 control-label"> Cantidad</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="cantidaddos" name="cantidaddos" value="0">
					</div>
					<label for="" class="col-sm-1 control-label"> Precio</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="preciodos" name="preciodos" value="0.00">
					</div>
					  
				</div>
				<div class="form-group">
				<label for="" class="col-sm-1 control-label"> Producto</label>
					  <div class="col-sm-5">
						<select class="form-control" id="productotres" name="productotres" >
										<option value="" selected >Seleccione producto</option>
										<?php
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '$ruc_empresa';";
										$respuesta = mysqli_query($conexion,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['codigo_producto']?>"><?php echo $datos_producto['nombre_producto'] ?></option> 
										<?php 
										}
										?>
						</select>
					  </div>
					  
					  <label for="" class="col-sm-1 control-label"> Cantidad</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="cantidadtres" name="cantidadtres" value="0">
					</div>
					<label for="" class="col-sm-1 control-label"> Precio</label>
					  <div class="col-md-2">
							<input type="text" class="form-control input-sm" id="preciotres" name="preciotres" value="0.00">
					</div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-1 control-label"> Detalle</label>
					  <div class="col-md-11">
							<input type="text" class="form-control input-sm" id="detalle" name="detalle">
					</div>
				</div>
				
				
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_factura_bloque" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
