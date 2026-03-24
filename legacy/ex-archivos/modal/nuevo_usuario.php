<div class="modal fade" id="nuevoUsuario" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Registrarse</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_usuario" name="guardar_usuario">
		<div id="resultados_ajax_usuarios"></div>
				<div class="form-group">
					<div class="col-sm-12">
					<div class="input-group">
					<span class="input-group-addon"><b>Nombre</b></span>
					   <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombres y apellidos" maxlength="100" title="Nombre de usuario ( sólo letras y números, 4-64 caracteres)" required >
					</div>
					</div>
				 </div>
				<div class="form-group">
				<div class="col-md-6">
					<div class="input-group">
						<span class="input-group-addon"><b>Tipo id</b></span>
							<select class="form-control input-md" id="tipo_id" name="tipo_id" required>
							<option value="1" selected> Cedula</option>
							<option value="2"> Otro</option>
							</select>
					</div>
				</div>
				  <div class="col-sm-6">
					  <div class="input-group">
						<span class="input-group-addon"><b>Documento</b></span>
						 <input type="text" class="form-control" id="cedula" name="cedula" placeholder="Cedula" maxlength="10" required title="Documento de identidad">
					  </div>
				  </div>
				</div>
				<div class="form-group">
					<div class="col-sm-12">
						<div class="input-group">
							<span class="input-group-addon"><b>Mail</b></span>
							<input type="email" class="form-control" id="mail" name="mail" placeholder="Mail" required>
						</div>
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-12">
						<div class="input-group">
									<span class="input-group-addon"><b>Contraseña</b></span>
							<input type="password" class="form-control" id="password" name="password" placeholder="De 4 a 30 caracteres" pattern=".{4,}" maxlength="30" required title="4 caracteres mínimo">
						</div>
					</div>
                 </div>
				<div class="form-group">
					<div class="col-sm-12">
						<div class="input-group">
									<span class="input-group-addon"><b>Confirmar Contraseña</b></span>
							<input type="password" class="form-control" id="confirmar_password" name="confirmar_password" placeholder="De 4 a 30 caracteres" pattern=".{4,}" maxlength="30" required title="4 caracteres mínimo">
						</div>
					</div>
                 </div>
				<div class="form-group">
				<label for="" class="col-sm-3 control-label"></label>
				<div class="col-sm-8">				
				 <div class="g-recaptcha" data-sitekey="6LdJLE0UAAAAAC6nmvCHnevGLAoRLwlq28XJdkXp"></div>
				 </div>
				 </div>
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos" >Guardar</button>
				</div>
    </form>
	</div>
	</div>
 </div>
