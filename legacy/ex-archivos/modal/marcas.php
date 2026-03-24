<!-- Modal -->
<div id="marcas" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title" id="TituloModal"></h4>
      </div>
      <div class="modal-body">
	  <form class="form-horizontal"  method="post" name="guarda_marca" id="guarda_marca" >
	  <div id="resultados_ajax_marcas"></div>
	  <div class="form-group">
            <label class="col-sm-4 control-label">Nombre marca</label>
            <div class="col-sm-6">
			  <input type="hidden" name="id_marca" id="id_marca">
              <input type="text" name="nombre_marca" class="form-control" id="nombre_marca" placeholder="Nombre">
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
