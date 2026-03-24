
<div class="modal fade" id="RecuperarClave" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-sm" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Recuperar contraseña</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="recuperar_clave" name="recuperar_clave">
		<div id="resultados_ajax_clave"></div>
				
				<div class="form-group">
						<label for="" class="col-sm-3 control-label"> Cedula</label>
					  <div class="col-sm-8">
						 <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Cedula" maxlength="10" pattern=".{10,}" required title="10 caracteres mínimo" >
					  </div>
				</div>
				   
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="enviar_datos" >Enviar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
