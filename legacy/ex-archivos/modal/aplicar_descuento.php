<div class="modal fade" data-backdrop="static" id="aplicarDescuento" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="myModalLabel"><i class="glyphicon glyphicon-pushpin"></i> Opciones de descuentos</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" method="post" id="guardar_descuento" name="guardar_descuento">
					<div class="panel panel-info" style="margin-bottom: -5px; margin-top: -10px;">
						<div class="table-responsive">
							<table class="table table-bordered">
								<tr class="info">
									<th style="padding: 2px;" class="col-sm-2">En %</th>
									<th style="padding: 2px;" class="col-sm-2">En valor</th>
									<th style="padding: 2px;" class="text-center" colspan="2">Aplicar descuento</th>
								</tr>
								<tr>
									<input type="hidden" id="id_tmp_descuento" >
									<input type="hidden" id="subtotal_inicial" >
									<input type="hidden" id="tarifa" >
									<input type="hidden" id="serie_factura_descuento" >

									<td class="col-sm-1" style="padding: 2px;">
										<input type="text" style="text-align:right; height:20px;" oninput="aplicar_descuento_porcentaje();" class="form-control input-sm" id="porcentaje_descuento" placeholder="%">
									</td>
									<td class="col-sm-2" style="padding: 2px;">
										<input type="text" style="text-align:right; height:20px;" oninput="aplicar_descuento_cantidad();" class="form-control input-sm" id="valor_descuento" placeholder="0.00">
									</td>
									<td class="col-sm-1" style="padding: 2px; text-align:center;">
										<button type="button" style="height:20px;" class="btn btn-info btn-xs" title="Aplica el descuento solo al item seleccionado" onclick="aplicar_descuento_item();">Al item</button>
									</td>
									<td class="col-sm-1" style="padding: 2px; text-align:center;">
										<button type="button" style="height:20px;" class="btn btn-info btn-xs" title="Aplica el descuento a todos los items" onclick="aplicar_descuento_todos();">A todos</button>
									</td>
								</tr>
							</table>
						</div>
					</div>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default btn-sm" data-dismiss="modal" reset>Cerrar</button>
			</div>
			</form>
		</div>
	</div>
</div>
<script>
	function aplicar_descuento_porcentaje() {
		var porcentaje = document.getElementById("porcentaje_descuento").value;
		if (isNaN(porcentaje)) {
			alert('El valor ingresado, no es un número');
			$("#porcentaje_descuento").val('0');
			document.getElementById('porcentaje_descuento' + id).focus();
			return false;
		}
		if (porcentaje < 0) {
			alert('El valor debe ser mayor a cero');
			$("#porcentaje_descuento").val('0');
			document.getElementById('porcentaje_descuento' + id).focus();
			return false;
		}
		if (porcentaje > 100) {
			alert('El porcentaje no puede ser mayor a 100');
			$("#porcentaje_descuento").val('0');
			document.getElementById('porcentaje_descuento' + id).focus();
			return false;
		}

		var tarifa = document.getElementById("tarifa").value;
		var subtotal_inicial = document.getElementById("subtotal_inicial").value;
		var total_descuento = (subtotal_inicial * porcentaje / 100).toFixed(2);
		$("#valor_descuento").val(total_descuento);

	}

	function aplicar_descuento_cantidad() {
		var subtotal_inicial = document.getElementById("subtotal_inicial").value;
		var tarifa = document.getElementById("tarifa").value;
		var valor_descuento = document.getElementById("valor_descuento").value;
		if (isNaN(valor_descuento)) {
			alert('El valor ingresado, no es un número');
			$("#valor_descuento").val('0');
			document.getElementById('valor_descuento' + id).focus();
			return false;
		}
		if (valor_descuento < 0) {
			alert('El valor debe ser mayor a cero');
			$("#valor_descuento").val('0');
			document.getElementById('valor_descuento' + id).focus();
			return false;
		}

		if (parseFloat(valor_descuento) > parseFloat(subtotal_inicial)) {
			alert('El descuento no puede ser mayor al subtotal');
			$("#valor_descuento").val('0');
			document.getElementById('valor_descuento' + id).focus();
			return false;
		}
		
		var total_porcentaje = (valor_descuento / subtotal_inicial * 100).toFixed(2);
		$("#porcentaje_descuento").val(total_porcentaje);

	}


</script>