<div class="modal fade" id="editarfactura_e" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Editar adicionales y formas de pago de la factura</h4>
		</div>
        <div class="modal-body">
					<div id="resultados_ajax_edit_factura"></div>
			<form class="form-horizontal" method="post" id="editar_factura" >
					<div class="form-group">
						<label for="" class="col-sm-2 control-label"> Fecha emisión</label>
						<div class="col-sm-2">
						<input type="text" class="form-control input-sm" name="edita_fecha_f" id="edita_fecha_f" >
						<input type="hidden"  name="id_factura_electronica" id="id_factura_electronica" >
						<input type="hidden"  name="edita_serie_f" id="edita_serie_f" >
						<input type="hidden"  name="edita_secuencial_f" id="edita_secuencial_f" >
						</div>
					 </div>
			<div id="loaderedit"></div><!-- Carga gif animado -->
			<div class="outer_divedit" ></div><!-- Datos ajax Final -->
		</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_fecha_modificada" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
