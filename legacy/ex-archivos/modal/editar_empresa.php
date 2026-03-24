<!-- Modal -->

<div class="modal fade bs-example-modal-md" id="edita_empresa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Modificar estado de empresa</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" id="editar_empresas" name="editar_empresas">
				<div id="resultados_ajax2"></div>
				
				<?php $con = conenta_login(); ?>
				<div class="form-group">
				<label class="col-sm-2 control-label control-label">Estado</label>
				<div class="col-sm-4">
				<input type="hidden" name="mod_id_empresa" id="mod_id_empresa">
				<select class="form-control" name="mod_estado_empresa" id="mod_estado_empresa">
					<?php
						$sql = "SELECT * FROM estado_del_registro ;";
						$res = mysqli_query($con,$sql);
						while($o = mysqli_fetch_assoc($res)){
					?>
							<option value="<?php echo $o['idestado']?>"><?php echo $o['nombre'] ?> </option>
							<?php
						}
					?>
				</select>
				</div>
				</div>
				 </div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-primary" id="actualizar_datos">Actualizar</button>
				</div>
			</form>
		</div>
	</div>
</div>

