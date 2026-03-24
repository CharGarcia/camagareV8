<?php
//para traer la serie de la sucursal primera
$con = conenta_login();
$ruc_empresa = $_SESSION['ruc_empresa'];
?>
<div class="modal fade" id="opcion_consignacion_venta" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Opciones consignación en ventas</h4>
				</div>
			<div class="modal-body">
	<form  method="POST" id="guardar_opcion_consignacion_venta" name="guardar_opcion_consignacion_venta">
				<div id="mensajes_opciones_consignacion_venta"></div>
				<div class="well well-sm" style="margin-bottom: 5px; margin-top: -10px;">
				<div class="panel-body" style="margin-bottom: 5px; margin-top: -15px;">
						<div class="col-sm-4">
						<div class="form-group">
							<div class="input-group">								
							<span class="input-group-addon"><b>Opción</b></span>
							<select class="form-control" style="height:30px;" title="Seleccione opción" name="opcion_salida" id="opcion_salida" >
								<option value="1" selected>Devolución</option>
								<option value="2" >Factura</option>
							</select>		
							</div>
							</div>
						</div>
						<div class="col-sm-8">
						<div class="form-group">
						<div class="input-group">
						  <span class="input-group-addon"><b>Cliente</b></span>
							<input type="hidden" name="id_cliente_opcion_consignacion_venta" id="id_cliente_opcion_consignacion_venta">
							<input type="text" class="form-control input-sm" name="cliente_opcion_consignacion_venta" id="cliente_opcion_consignacion_venta" onkeyup='buscar_clientes();' autocomplete="off"> 			
						</div>
						</div>
						</div>
						<div class="col-sm-3">
							<div class="form-group">
							<div class="input-group">								
							<span class="input-group-addon"><b>Fecha</b></span>
								<input type="text" class="form-control input-sm text-center" name="fecha_opcion_consignacion_salida" id="fecha_opcion_consignacion_salida" value="<?php echo date("d-m-Y");?>">					
							</div>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-group">
							<div class="input-group">								
							<span class="input-group-addon"><b>Serie</b></span>
							<select class="form-control" style="height:30px;" title="Seleccione serie." name="serie_opcion_consignacion" id="serie_opcion_consignacion" >
								<?php
								$conexion = conenta_login();
								$sql = "SELECT * FROM sucursales WHERE ruc_empresa ='".$ruc_empresa."'";
								$res = mysqli_query($conexion,$sql);
								while($o = mysqli_fetch_array($res)){
								?>
								<option value="<?php echo $o['serie']?>" selected><?php echo strtoupper($o['serie'])?></option>
								<?php
								}
								?>
							</select>		
							</div>
							</div>
						</div>

						<div class="col-sm-6">
						<div class="form-group">
						<div class="input-group">
						  <span class="input-group-addon"><b>Observaciones</b></span>
							<input type="text" class="form-control input-sm" name="observacion_opcion_consignacion_venta" id="observacion_opcion_consignacion_venta" > 			
						</div>
						</div>
						</div>
				</div>
					
					<div class="form-group" >
							<div class="panel panel-info" style="margin-bottom: -15px; margin-top: -20px;">
							<div class="table-responsive">							
							<table class="table table-bordered">
								<tr class="info">
								<th style ="padding: 2px;" class="text-center">No.CV</th>
								<th style ="padding: 2px;" >Producto</th>
								<th style ="padding: 2px;" class="text-center" id="titulo_lote">Lote</th>
								<th style ="padding: 2px;" class="text-center" id="titulo_caducidad">Caducidad</th>
								<th style ="padding: 2px;" class="text-center" id="titulo_medida">Medida</th>
								<th style ="padding: 2px;" class="text-center">Cantidad</th>
								<th style ="padding: 2px;" class="text-center" id="titulo_existencia">Existencia</th>
								<th style ="padding: 2px;" class="text-center">Agregar</th>
								</tr>
								<input type="hidden" name="id_producto" id="id_producto">
								<input type="hidden" id="inventario" name="inventario">
								<input type="hidden" id="muestra_medida">
								<input type="hidden" id="muestra_lote">
								<input type="hidden" id="muestra_vencimiento">
								<input type="hidden" name="stock_tmp" id="stock_tmp" >
								
								<td class='col-xs-2'>
								<div class="pull-center">
								  <input type="text" class="form-control input-sm" style="text-align:center;" title="Ingrese No. CV" name="numero_consignacion" id="numero_consignacion" placeholder="No." onkeyup="limpiar_producto();">
								</div>
								</td>
								<td class='col-xs-5'>
								<input type="text" class="form-control input-sm" id="nombre_producto" name="nombre_producto" placeholder="Producto" onkeyup="buscar_productos();">
								</td>
								<td class='col-xs-2' id="lista_lote">
								  <select class="form-control" style="height:30px;" title="Seleccione lote" name="lote_agregar" id="lote_agregar" >
									</select>
								</td>
								<td class="col-xs-2" id="lista_caducidad">
								  	<select class="form-control" style="height:30px;" title="Seleccione caducidad" name="caducidad_agregar" id="caducidad_agregar" >
									</select>
								</td>
								<td class="col-xs-2" id="lista_medida">
								  <select class="form-control" style="height:30px;" title="Seleccione medida" name="medida_agregar" id="medida_agregar" >
									</select>
								</td>
								<td class='col-xs-2'>
								<div class="pull-right">
								  <input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" placeholder="Cantidad" >
								</div>
								</td>
								<td class='col-xs-2' id="lista_existencia">
								<div class="pull-right">
								  <input type="text" class="form-control input-sm" style="text-align:right;" title="Existencia" name="existencia_consignacion" id="existencia_consignacion" readonly >
								</div>
								</td>
								<td class="col-sm-1" style="text-align:center;">
								<button type="button" class="btn btn-info btn-sm" title="Agregar productos" onclick="agregar_item()"><span class="glyphicon glyphicon-plus"></span></button>
								</td>
							</table>
							</div>
							</div>
					</div>
				</div>
						<div id="muestra_detalle_opciones_consignacion"></div><!-- Carga gif animado -->
						<div class="outer_divdet_opciones_consignacion" ></div><!-- Datos ajax Final -->
				  
				  
			</div>
				<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_detalle_consignacion">Cerrar</button>
						<button type="submit" class="btn btn-info" id="guardar_datos">Guardar</button>
				</div>
		</form>
	</div>
</div>
</div>
	
<script>

$('#lote_agregar').change(function(){	
		var lote = $("#lote_agregar").val();
		var vencimiento = $("#caducidad_agregar").val();
		var producto = $("#id_producto").val();
		var numero_consignacion = $("#numero_consignacion").val();
		$.post( '../ajax/saldo_producto_consignaciones.php', {action:'saldo_consignacion_venta_lote', numero_consignacion: numero_consignacion, id_producto: producto, lote: lote}).done( function( res_opciones_lote ){
			$("#existencia_consignacion").val(res_opciones_lote);
		});
		
			//para reinicie vencimiento
			$.post( '../ajax/saldo_producto_consignaciones.php', {action:'saldo_consignacion_venta_vencimiento', numero_consignacion: numero_consignacion, id_producto: producto, vencimiento: vencimiento}).done( function( res_opciones_caducidad ){
				$("#caducidad_agregar").html(res_opciones_caducidad);
			});	
		document.getElementById('cantidad_agregar').focus();
	});
	
$('#caducidad_agregar').change(function(){
	var vencimiento = $("#caducidad_agregar").val();
	var producto = $("#id_producto").val();

//para reinicie vencimiento
		$.post( '../ajax/saldo_producto_consignaciones.php', {action:'saldo_consignacion_venta_vencimiento', numero_consignacion: numero_consignacion, id_producto: producto, vencimiento: vencimiento}).done( function( res_opciones_caducidad ){
			$("#caducidad_agregar").html(res_opciones_caducidad);
		});	
		document.getElementById('cantidad_agregar').focus();
});
	

</script>

