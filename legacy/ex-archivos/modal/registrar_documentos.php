
<!-- Modal -->
<div class="modal fade" id="procesarDocumentos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-pencil'></i> Registrar documentos</h4>
				</div>
			<div class="modal-body">
				<div class="form-group">
					<div class="col-sm-12">
					<input type="textarea" class="form-control" id="detalle_transaccion" readonly>
					</div>
				</div>
					<div class="row">
						<div class="form-group">
							<div class="col-sm-6">
							<input type="hidden" class="form-control" id="p" placeholder="Buscar productos" onkeyup="load(1)">
							<div id="loaderdoc" ></div><!-- Carga gif animado -->
							<div class="outer_divdoc" ></div><!-- Datos ajax Final -->
							</div>
						</div>
						<br>
						<div class="form-group">
						<label class="col-sm-2 control-label">Fecha emisión:</label>
							<div class="col-sm-2">		
							<input type="text" class="form-control input-sm" id="fecha_emision" name="fecha_emision" value="<?php echo date("d-m-Y");?>">
							</div>
						</div>
						<br>
						<div class="form-group">
						<label class="col-sm-2 control-label">Documento:</label>
										<div class="col-sm-4">
										<?php	$conexion = conenta_login(); ?>
										<select class="form-control" name="documento" required>
											<?php
											$sql = "SELECT * FROM comprobantes_autorizados ;";
											$respuesta = mysqli_query($conexion,$sql);
											while($documento = mysqli_fetch_assoc($respuesta)){		
											?>
											<option value="<?php echo $documento['codigo_comprobante']; ?>"><?php echo $documento['comprobante']; ?> </option>
											<?php
											}
											?>
										</select>
										</div>
						</div>
						<br>
						<div class="form-group">
										<label class="col-sm-2 control-label">Prov/Cliente:</label>
										<div class="col-sm-4">		
										<input type="text" class="form-control input-sm" name="procli">
										</div>
						</div>
						<br>
						<div class="form-group">
						<label class="col-sm-2 control-label">#.documento:</label>
											<div class="col-sm-2">		
											<input type="text" class="form-control input-sm"  name="serie" placeholder="Serie">
											</div>
											<div class="col-sm-2">
											<input type="text" class="form-control input-sm"  name="secuencial" placeholder="Secuencial">
											</div>
						</div>
						<div class="form-group">
						<label class="col-sm-2 control-label">Transacción:</label>
											<div class="col-sm-4">
											<label class="radio-inline">
      <input type="radio" name="optradio">Compra
    </label>
    <label class="radio-inline">
      <input type="radio" name="optradio">Venta
    </label>
											</div>								
						</div>
						<br>
						<div class="form-group">
							<div class="col-md-6">
									<ul id="registro-nav" class="nav nav-tabs" role="tablist">
										<li class="nav-item">
										<a class="nav-link active" href="#transaccion" id="transaccion-tab" role="tab" data-toggle="tab" aria-controls="transaccion" aria-expanded="true">Compras/Ventas</a>
										</li>
										<!-- Dropdown -->
										<li class="nav-item">
										<a class="nav-link" href="#retenciones" id="retenciones-tab" role="tab" data-toggle="tab" aria-controls="retenciones" aria-expanded="true">Retenciones</a>
										</li>
										<li class="nav-item dropdown">
										<a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">Prov/Cli <span class="caret"></span></a>
										<div class="dropdown-menu">
										<a class="dropdown-item" href="#dropdown-pro" role="tab" id="dropdown-pro-tab" data-toggle="tab" aria-controls="dropdownpro">Proveedor </a><br>
										<a class="dropdown-item" href="#dropdown-cli" role="tab" id="dropdown-cli-tab" data-toggle="tab" aria-controls="dropdowncli">Cliente </a>
										</div>
										</li>
									</ul>
								
									<!-- Content Panel -->
								<div id="registro-nav-content" class="tab-content">
									<div role="tabpanel" class="tab-pane fade in active" id="transaccion" aria-labelledby="transaccion-tab">
									<br>
									<div class="form-group">
									 <form>
																					
											<label class="col-sm-4 control-label">Subtotal iva 0</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="cero" placeholder="Subtotal cero">
											</div>
											<label class="col-sm-4 control-label">Subtotal iva 12</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="doce" placeholder="Subtotal con iva">
											</div>
											<label class="col-sm-4 control-label">No obj de iva</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="exento" placeholder="Subtotal no objeto de iva">
											</div>
											<label class="col-sm-4 control-label">Exento de iva</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="exento" placeholder="Subtotal exento">
											</div>
											<label class="col-sm-4 control-label">ICE</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="exento" placeholder="Subtotal ice">
											</div>
											<label class="col-sm-4 control-label">Descuento</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="exento" placeholder="Subtotal descuento">
											</div>
											<label class="col-sm-4 control-label">Propina</label>
											<div class="col-sm-6">		
											<input type="text" class="form-control input-sm" name="exento" placeholder="propina">
											</div>
											<label class="col-sm-4 control-label">Detalle</label>
											<div class="col-sm-8">		
											<input type="text" class="form-control input-sm" name="detalle_compra" placeholder="Un breve detalle">
											</div>
											<div class="col-sm-1">
											</div>
											<button type="button" class="btn btn-info" data-dismiss="modal">Registrar</button>
									</form>
									</div>
									</div>
									<div role="tabpanel" class="tab-pane fade" id="retenciones" aria-labelledby="retenciones-tab">
									<div class="form-group">
									<label class="col-sm-4 control-label">Doc. Retenido</label>
										<div class="col-sm-8">
										<?php	$conexion = conenta_login(); ?>
										<select class="form-control" name="documento" required>
											<?php
											$sql = "SELECT * FROM comprobantes_autorizados ;";
											$respuesta = mysqli_query($conexion,$sql);
											while($documento = mysqli_fetch_assoc($respuesta)){		
											?>
											<option value="<?php echo $documento['codigo_comprobante']; ?>"><?php echo $documento['comprobante']; ?> </option>
											<?php
											}
											?>
										</select>
										</div>
									</div>
									<div class="form-group">
									<label class="col-sm-4 control-label">#.doc retenido</label>
										<div class="col-sm-4">		
										<input type="text" class="form-control input-sm"  name="serie" placeholder="Serie">
										</div>
										<div class="col-sm-4">
										<input type="text" class="form-control input-sm"  name="secuencial" placeholder="Secuencial">
										</div>
									</div>
									<div class="form-group">
									<label class="col-sm-4 control-label">Base imponible</label>
										<div class="col-sm-8">		
										<input type="text" class="form-control input-sm"  name="serie" value="0.00">
										</div>
									</div>
									
									<div class="form-group">
									<label class="col-sm-4 control-label">Impuesto</label>
									<div class="col-sm-8">
										<select class="form-control" name="documento" required>
											<option value="01">Renta </option>
											<option value="01">Iva </option>
										</select>
										</div>
									</div>
									<div class="form-group">
									<label class="col-sm-4 control-label">Concepto</label>
									<div class="col-sm-8">
										<?php	$conexion = conenta_login(); ?>
										<select class="form-control" name="documento" required>
											<?php
											$sql = "SELECT * FROM comprobantes_autorizados ;";
											$respuesta = mysqli_query($conexion,$sql);
											while($documento = mysqli_fetch_assoc($respuesta)){		
											?>
											<option value="<?php echo $documento['codigo_comprobante']; ?>"><?php echo $documento['comprobante']; ?> </option>
											<?php
											}
											?>
										</select>
										</div>
									<label class="col-sm-4 control-label">Valor retenido</label>
										<div class="col-sm-4">		
										<input type="text" class="form-control input-sm"  name="serie" value="0.00">
										</div>
									</div>
											<button type="button" class="btn btn-info"><span class="glyphicon glyphicon-arrow-down"></span></button>
									
									
									
	<div id="table" class="table-editable">
    <span class="table-add glyphicon glyphicon-plus"></span>
    <table class="table">
      <tr>
        <th>Base Imp</th>
        <th>Impuesto</th>
        <th>Porcentaje</th>
        <th>Valor</th>
      </tr>
      <tr>
        <td contenteditable="true">Stir Fry</td>
        <td contenteditable="true">stir-fry</td>
        <td>
          <span class="glyphicon glyphicon-remove"></span>
        </td>

      </tr>
      <!-- This is our clonable table line -->
      <tr class="hide">
        <td contenteditable="true">Untitled</td>
        <td contenteditable="true">undefined</td>
        <td>
          <span class="table-remove glyphicon glyphicon-remove"></span>
        </td>
        <td>
          <span class="table-up glyphicon glyphicon-arrow-up"></span>
          <span class="table-down glyphicon glyphicon-arrow-down"></span>
        </td>
      </tr>
    </table>
  </div>
									
									
									
									
									</div>
									<div role="tabpanel" class="tab-pane fade" id="dropdown-ventas" aria-labelledby="dropdown-ventas-tab">
									<p>Aqui se registra las retenciones de ventas</p>
									</div>
									<div role="tabpanel" class="tab-pane fade" id="dropdown-pro" aria-labelledby="dropdown-pro-tab">
									<p>Aqui se registra los proveedores</p>
									</div>
									<div role="tabpanel" class="tab-pane fade" id="dropdown-cli" aria-labelledby="dropdown-cli-tab">
									<p>Aqui se registra los clientes</p>
									</div>
									
								</div>	
							</div>			
						</div>		
					
				</div>
			</div>
						<div class="modal-footer">
							<button type="button" id="cerrar" class="btn btn-default" data-dismiss="modal">Cerrar</button>
						</div>
		</div>
	</div>
</div>

