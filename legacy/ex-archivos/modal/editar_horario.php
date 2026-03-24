<?php
$con = conenta_login();
?>
<div class="modal fade" id="editarHorario" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar horario</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="editar_horario" name="editar_horario">
		<div id="resultados_ajax_editar_horario"></div>
				<div class="form-group">
					<label for="" class="col-sm-4 control-label"> Detalle</label>
					<div class="col-sm-7">
					   <input type="hidden" id="mod_id_horario" name="mod_id_horario" >
					   <input type="text" class="form-control" id="mod_nombre_horario" name="mod_nombre_horario" placeholder="Detalle" maxlength="150" title="Detalle del horario" required >
					</div>
				 </div>

				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_horario" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
