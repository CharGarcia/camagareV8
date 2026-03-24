<div class="modal fade" id="formasDePago" name="formasDePago" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-credit-card'></i> Formas de pago</h4>
        </div>
        <div class="modal-body">
			<div id="mensaje_detalle_formas_pago"></div>
			<form class="form-horizontal" id="formas_pago" name="formas_pago">
			<?php
			$con = conenta_login();
			?>
			<div class="form-group row" style="margin-bottom: 5px; margin-top: -10px;">
				<label class="col-sm-2 control-label text-right">Total $</label>
				<div class="col-sm-3">
				<input type="text" class="form-control input-sm text-right" name="total_factura_pago" id="total_factura_pago" readonly>
				</div>
				<label class="col-sm-2 control-label text-right">Documento</label>
				<div class="col-sm-4">
				<input type="text" class="form-control input-sm text-right" name="documento_numero" id="documento_numero" readonly>
				</div>
			</div>

			<div class="panel panel-success" style ="padding: 1px;">
				<table class="table table-bordered" > 
					<tr class="success" >
						<th style ="padding: 2px;">Forma pago</th>
						<th style ="padding: 2px;">Valor</th>
						<th style ="padding: 2px;" class='text-right'>Agregar</th>
					</tr>
					<tr>
						<td class="col-xs-8">
						<select class="form-control" name="forma_pago" id="forma_pago" required>
						<option value="0" >Seleccione</option>
						<?php
						$sql = mysqli_query($con, "SELECT * FROM formas_de_pago where aplica_a ='VENTAS' ;");
						while($o = mysqli_fetch_assoc($sql)){
						?>
						<option value="<?php echo $o['codigo_pago']?>" selected><?php echo $o['nombre_pago'] ?> </option>
						<?php
						}
						?>
						</select>
						</td>
						<td class="col-xs-2">
						<input type="text" class="form-control input-sm" id="valor_pago" name="valor_pago" >
						</td>
						<td class='text-right'>
						<a href="#" class='btn btn-info btn-sm' title='Agregar item' onclick="agregar_item_forma_pago()" ><i class="glyphicon glyphicon-plus"></i></a>
						</td>
							<input type="hidden" id="serie_factura_pago" name="serie_factura_pago" >
							<input type="hidden" id="secuencial_factura_pago" name="secuencial_factura_pago" >
					</tr>
				</table>
			</div>
				<div id="muestra_detalle_formas_pago"></div><!-- Carga los datos ajax -->
				<div class='outer_div_pago'></div><!-- Carga los datos ajax -->
				   
		</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" id="btnCerrar" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_formas_pago">Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
