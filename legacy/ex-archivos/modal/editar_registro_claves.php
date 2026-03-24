<?php
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();
$busca_empresas = "SELECT em.ruc as ruc, em.nombre as nombre FROM empresa_asignada as ea, empresas as em WHERE ea.id_empresa = em.id and ea.id_usuario = $id_usuario and em.estado = '1'";
$resultado_de_la_busqueda = $con->query($busca_empresas);
?>
<div class="modal fade" id="editarRegistroClaves" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar contraseña</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="editar_clave" name="editar_clave">
		<div id="resultados_ajax2"></div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Empresa</label>
					<div class="col-sm-8">
					<select class="form-control" name="mod_empresa" id="mod_empresa" required>
									<option value="">Seleccione empresa</option>
									<?php
									while($empresa = mysqli_fetch_assoc($resultado_de_la_busqueda)){		
									?>
									<option value="<?php echo $empresa['ruc']; ?>"><?php echo $empresa['nombre']; ?> </option>
									<?php
									}
									?>
							</select>
					</div>
				 </div>
				<div class="form-group">
						<label for="" class="col-sm-3 control-label"> Institución</label>
					  <div class="col-sm-8">
					     <input type="hidden" name="mod_id" id="mod_id" >
						 <input type="text" class="form-control" name="mod_institucion" id="mod_institucion" placeholder="Institución de la cual va a guardar la clave " required >
					  </div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-3 control-label"> Usuario</label>
					<div class="col-sm-8">
						<input type="text" class="form-control" name="mod_usuario" id="mod_usuario" placeholder="usuario" >
					</div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-3 control-label"> Clave</label>
					<div class="col-sm-8">
						<input type="text" class="form-control" name="mod_clave" id="mod_clave" placeholder="contraseña">
					</div>
				</div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Detalle</label>
					<div class="col-sm-8">
						<input type="text" class="form-control" name="mod_detalle" id="mod_detalle" placeholder="Detalle relacionado a la clave">
					</div> 
                 </div>      
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="actualizar_clave" >Actualizar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
