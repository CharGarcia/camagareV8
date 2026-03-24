<?php
$con = conenta_login();
?>
<div class="modal fade" id="nuevoNivelAlumno" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Registrar nuevo nivel o paralelo</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_nivel_alumno" name="guardar_nivel_alumno">
		<div id="resultados_ajax_nivel_alumnos"></div>
				<div class="form-group">
					<label for="" class="col-sm-4 control-label"> Nombre</label>
					<div class="col-sm-7">
					   <input type="text" class="form-control" id="nombre_nivel" name="nombre_nivel" placeholder="Nombre" maxlength="150" title="Nombre del nivel o paralelo" required >
					</div>
				 </div>

				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_nivel" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
