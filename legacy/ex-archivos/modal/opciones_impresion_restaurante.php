<div class="modal fade" data-backdrop="static" id="opciones_impresiones" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalImpresion"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="guardar_opciones_mpresiones" name="guardar_opciones_mpresiones">
					<div id="mensaje_opciones_mpresiones"></div>
					<input type="hidden" name="idRegistro" id="idRegistro">
						<div class="panel-body">
						<div class="col-sm-6">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Categoría</b></span>
										<select class="form-control input-sm" id="listCategoria" name="listCategoria" required>
										<?php
										$con = conenta_login();
										$ruc_empresa = $_SESSION['ruc_empresa'];
												$sql_marca = mysqli_query($con,"SELECT * FROM marca where ruc_empresa='".$ruc_empresa."' order by nombre_marca asc");
												while($tipo = mysqli_fetch_assoc($sql_marca)){
											?>
												<option value="<?php echo $tipo['id_marca'] ?>" selected><?php echo strtoupper ($tipo['nombre_marca']) ?> </option>
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
										<span class="input-group-addon"><b>Enviar impresión a</b></span>
										<select class="form-control input-sm" id="listOpcion" name="listOpcion" required>
											<option value="1" selected>Cocina</option>
											<option value="2">Barra</option>
										</select>
									</div>
								</div>
							</div>
						</div>
			</div>
			<div class="modal-footer">
				<span id="loader_opciones_impresion"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_opciones_impresion" title="Cancelar">Cerrar</button>
				<button type="submit" class="btn btn-info" id="btnActionFormopciones_impresion" class="btn btn-primary" title=""><span id="btnTextopciones_impresion"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>
