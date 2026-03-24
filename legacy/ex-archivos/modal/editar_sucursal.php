
<div class="modal fade" id="editaSucursal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar sucursal</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="editar_sucursal" name="editar_sucursal">
	<div id="resultados_ajax_sucursal"></div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Serie</label>
					<div class="col-sm-4">
					<input type="hidden" id="mod_id_sucursal" name="mod_id_sucursal">
					   <input type="text" class="form-control" id="mod_serie" name="mod_serie" placeholder="001-001" maxlength="7" title="Serie">
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Dirección</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="mod_direccion_sucursal" name="mod_direccion_sucursal" placeholder="Dirección de la sucursal" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Nombre sucursal</label>
					<div class="col-sm-8">
					   <input type="text" class="form-control" id="mod_nombre_sucursal" name="mod_nombre_sucursal" placeholder="Nombre de la sucursal" required >
					</div>
				 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Teléfonos</label>
					<div class="col-sm-6">
					   <input type="text" class="form-control" id="mod_telefono_sucursal" name="mod_telefono_sucursal" placeholder="Teléfonos">
					</div>
				 </div> 
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_sucursal" >Actualizar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
