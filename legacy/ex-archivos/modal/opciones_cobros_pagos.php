	<!-- Modal -->
	<div class="modal fade" data-backdrop="static" id="nuevaOpcionCobroPago" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	  <div class="modal-dialog modal-sm" role="document" >
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="titleModalOpcionCobroPago"></h4>
		  </div>
		  <div class="modal-body">
			<form class="form-horizontal" method="POST" id="guardar_OpcionCobroPago" name="guardar_OpcionCobroPago">
			<input type="hidden" id="id_OpcionCobroPago" >
			  <div class="form-group">
              <div class="col-sm-12" >
					<div class="input-group" >
						<span class="input-group-addon"><b>Para</b></span>
							<select class="form-control" title="Tipo" id="tipo_opcion" >
								<option value="1" selected>Ingreso</option>
								<option value="2">Egreso</option>
							</select>
					</div>
                </div>
                </div>
                <div class="form-group">
				<div class="col-sm-12">
				<div class="input-group" >
				<span class="input-group-addon"><b>Nombre</b></span>
                    <input type="text" class="form-control" id="descripcion_opcion" maxlength="20" placeholder="Max 20 caracteres" required>
				</div>
				</div>
                </div>
                <div class="form-group">
                <div class="col-sm-12" >
					<div class="input-group" >
						<span class="input-group-addon"><b>Status</b></span>
							<select class="form-control" title="Status" id="status" >
								<option value="1" selected>Activo</option>
								<option value="2">Pasivo</option>
							</select>
					</div>
				</div>
				</div>
			  
		  </div>
		  <div class="modal-footer">
		  <span id="resultados_ajax_guardar_opcionCobroPago"></span>
          <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		  <button type="button" class="btn btn-primary" onclick="guarda_opcion();" id="btnActionFormOpcionCobroPago"><span id="btnTextOpcionCobroPago"></span></button>
        </div>
		  </form>
		</div>
	  </div>
	</div>
<script>
	function guarda_opcion() {
        $('#btnTextOpcionCobroPago').attr("disabled", true);
        var id = $("#id_OpcionCobroPago").val();
        var tipo_opcion = $("#tipo_opcion").val();
        var descripcion_opcion = $("#descripcion_opcion").val();
        var status = $("#status").val();

        $.ajax({
            type: "POST",
            url: "../ajax/opciones_cobros_pagos.php?action=guardar_opcion_cobro_pago",
            data: "id=" + id + "&tipo_opcion=" + tipo_opcion +
                "&descripcion_opcion=" + descripcion_opcion + "&status=" + status,
            beforeSend: function(objeto) {
                $("#resultados_ajax_guardar_opcionCobroPago").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_ajax_guardar_opcionCobroPago").html(datos);
                $('#btnTextOpcionCobroPago').attr("disabled", false);
            }
        });
        //event.preventDefault();
    }
</script>