<?php
$con = conenta_login();
?>
<div class="modal fade" id="nuevoCampus" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Registrar nuevo campus</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_nuevo_campus" name="guardar_nuevo_campus">
		<div id="resultados_ajax_campus"></div>
				<div class="form-group">
					<label for="" class="col-sm-4 control-label"> Nombre</label>
					<div class="col-sm-7">
					   <input type="text" class="form-control" id="nombre_campus" name="nombre_campus" placeholder="Nombre" maxlength="150" title="Nombre del campus" required >
					</div>
				 </div>

				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_campus" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
