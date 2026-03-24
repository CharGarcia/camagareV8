
			<!-- Modal -->
			<div class="modal fade bs-example-modal-lg" id="entradas_salidas_caja" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
			  <div class="modal-dialog modal-md" role="document">
				<div class="modal-content">
				  <div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Entradas y salidas de caja</h4>
				  </div>
			<div class="modal-body">
				  <form class="form-horizontal"  method="post" name="guardar_entrada_salida_caja" id="guardar_entrada_salida_caja">
				  <div id="resultados_ajax_caja"></div>
				  <div class="form-group">
						<label class="col-sm-3 control-label">Tipo</label>
						<div class="col-sm-3">
						  <input type="text" name="tipo_registro" class="form-control" id="tipo_registro" readonly>
						</div>
				  </div>
				  <div class="form-group">
						<label class="col-sm-3 control-label">Detalle</label>
						<div class="col-sm-8">
						  <input type="text" name="detalle_entrada_salida" class="form-control" id="detalle_entrada_salida" placeholder="Detalle de entrada o salida">
						</div>
				  </div>
				  <div class="form-group">
						<label class="col-sm-3 control-label">Forma de pago</label>
						<div class="col-sm-8">
						   <?php
							$conexion = conenta_login();
								?>
								<select class="form-control" title="forma de pago" name="forma_pago" id="forma_pago" required>
								<?php
									$sql = "SELECT * FROM formas_de_pago where aplica_a='VENTAS';";
									$res = mysqli_query($conexion,$sql);
								?> <option value="">Seleccione</option>
								 <?php
									while($row = mysqli_fetch_assoc($res)){
								?>
									<option value="<?php echo $row['codigo_pago'] ?>" selected ><?php echo strtoupper ($row['nombre_pago']) ?> </option>
									<?php
									}
								?>
								</select>
						</div>
					</div>
				  <div class="form-group">
						<label class="col-sm-3 control-label">Valor</label>
						<div class="col-sm-3">
						  <input type="text" title="Valor entrada o salida" name="valor_entrada_salida" class="form-control" id="valor_entrada_salida" placeholder="Valor" required>
						</div>
					  </div>
				  </div>
				  <div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
					<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
				  </div>
				   </form>
				</div>
			  </div>
			</div>
