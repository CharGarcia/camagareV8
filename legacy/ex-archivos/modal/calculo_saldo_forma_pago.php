<div class="modal fade" data-backdrop="static" id="opciones_csfp" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="titleModalcsfp"></h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="POST" id="guardar_csfp" name="guardar_csfp">
					<div id="mensaje_csfp"></div>
					<input type="hidden" name="idRegistro" id="idRegistro">
						<div class="panel-body">
						<div class="col-sm-12">
								<div class="form-group">
									<div class="input-group">
										<?php
										$con = conenta_login();
										$ruc_empresa = $_SESSION['ruc_empresa'];
												$sql = mysqli_query($con,"SELECT * FROM opciones_cobros_pagos where ruc_empresa='".$ruc_empresa."' and tipo_opcion='1' order by descripcion asc");
											?>
											<span class="input-group-addon"><b>Saldo para:</b></span>
											<select class="form-control input-sm" id="listFormaPagoPrincipal" name="listFormaPagoPrincipal" required>
											<?php
											while($tipo = mysqli_fetch_assoc($sql)){
											?>
												<option value="<?php echo $tipo['id'] ?>" selected><?php echo strtoupper ($tipo['descripcion']) ?> </option>
												<?php
												}
											?>
										</select>
									</div>
								</div>
								</div>
								<div class="col-sm-9">
								<div class="form-group">
									<div class="input-group">
										<span class="input-group-addon"><b>Restar de:</b></span>
										<select class="form-control input-sm" id="listFormaPagoOpcional" name="listFormaPagoOpcional" required>
											<?php
											$sql = mysqli_query($con,"SELECT * FROM opciones_cobros_pagos where ruc_empresa='".$ruc_empresa."' and tipo_opcion='2' order by descripcion asc");
											while($tipo = mysqli_fetch_assoc($sql)){
											?>
												<option value="<?php echo $tipo['id'] ?>" selected><?php echo strtoupper ($tipo['descripcion']) ?> </option>
												<?php
												}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-sm-3">
								<div class="form-group">
									<div class="input-group">
									<button type="button" class="btn btn-info btn-sm" title="Agregar forma de pago" onclick="agregar_item();"><span class="glyphicon glyphicon-plus"></span> Agergar</button>
									</div>
								</div>
								
							</div>
						</div>
						<div id="muestra_detalle_scfp"></div><!-- Carga gif animado -->
						<div class="outer_divdet_scfp"></div><!-- Datos ajax Final -->
			</div>
			<div class="modal-footer">
				<span id="loader_opciones_scfp"></span>
				<button type="button" class="btn btn-default" data-dismiss="modal" id="cerrar_opciones_scfp" title="Cancelar">Cerrar</button>
				<button type="submit" class="btn btn-info" id="btnActionFormopciones_scfp" class="btn btn-primary" title=""><span id="btnTextopciones_scfp"></span></button>
			</div>
			</form>
		</div>
	</div>
</div>
