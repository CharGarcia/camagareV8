<div class="modal fade" id="formasCobroIngreso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Detalle de formas de cobros</h4>
				</div>
			<div class="modal-body">
			<input type="hidden" name="numero_ingreso" id="numero_ingreso">
					<div class="form-group">					 
							<table class="table table-bordered">
								<tr  class="warning">
										<th>Forma de pago</th>
										<th>Valor</th>
										<th>Cuenta Bancaria</th>
										<th>Detalle</th>
										<th class="text-center">Agregar</th>
								</tr>
								<td class='col-xs-3'>
									<select class="form-control" id="forma_pago" name="forma_pago" required>
										<option value="" selected >Seleccione</option>
										<?php
										$con = conenta_login();
										$sql = "SELECT * FROM formas_de_pago WHERE aplica_a = 'INGRESO';";
										$respuesta = mysqli_query($con,$sql);
										while($datos_formas = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_formas['id_forma_pago']?>"><?php echo $datos_formas['nombre_pago'] ?></option> 
										<?php 
										}
										?>
									</select>
								</td>
								<td class='col-xs-2'><input type="text" class="form-control input-sm" name="valor" id="valor" ></td>
								<td class='col-xs-3'>												
								<select class="form-control" id="cuenta_bancaria" name="cuenta_bancaria" required>
										<option value="0" Selected>Seleccione</option>
										<?php
										$con = conenta_login();
										$sql = "SELECT * FROM cuentas_bancarias cb, bancos_ecuador be where cb.ruc_empresa='$ruc_empresa' and cb.id_banco = be.id_bancos ";
										$respuesta = mysqli_query($con,$sql);
										while($datos_cuenta = mysqli_fetch_assoc($respuesta)){
											$banco = $datos_cuenta['nombre_banco'];
											$numero = $datos_cuenta['numero_cuenta'];

											switch ($datos_cuenta['id_tipo_cuenta']) {
											case "1":
												$tipo_cuenta='AHORROS';
												break;
											case "2":
												$tipo_cuenta='CORRIENTE';
												break;
											case "3":
												$tipo_cuenta='VIRTUAL';
												break;
											case "4":
												$tipo_cuenta='TARJETA';
												break;
												}
												
										$detalle_cuenta = strtoupper($banco ."-".$tipo_cuenta ."-". $numero) ;
										?>	
										<option value="<?php echo $datos_cuenta['id_cuenta']?>"><?php echo $detalle_cuenta ?></option> 
										<?php 
										}
										?>
									</select>
								</td>
								<td class='col-xs-3'><input type="text" class="form-control input-sm" name="detalle" id="detalle" ></td>
								<td class="text-center"><a class='btn btn-info' onclick="agregar_forma_cobro()"><i class="glyphicon glyphicon-plus"></i></a></td>
							</table>
					</div>
						<div id="muestra_detalle_formas_cobro"></div><!-- Carga gif animado -->
						<div class="outer_divdet" ></div><!-- Datos ajax Final -->
			</div>

				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				</div>
		</div>
</div>
</div>
