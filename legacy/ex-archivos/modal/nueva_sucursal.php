
<div class="modal fade" id="nuevaSucursal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Nueva sucursal</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_sucursal" name="guardar_sucursal">
	<div id="resultados_ajax"></div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Serie</label>
					<div class="col-sm-4">
					   <input type="hidden" id="ruc_empresa" name="ruc_empresa" >
					   <input type="text" class="form-control" id="serie" name="serie" placeholder="001-001" maxlength="7" title="Serie" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Dirección</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="direccion_sucursal" name="direccion_sucursal" placeholder="Dirección de la sucursal" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Nombre sucursal</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="nombre_sucursal" name="nombre_sucursal" placeholder="Nombre de la sucursal" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Teléfonos</label>
					<div class="col-sm-6">
					   <input type="text" class="form-control" id="telefono_sucursal" name="telefono_sucursal" placeholder="Teléfonos" required >
					</div>
				 </div> 
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_sucursal" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
