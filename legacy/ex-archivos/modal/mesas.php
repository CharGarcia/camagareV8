<!-- Modal -->
<div id="mesas" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
		<h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Nueva mesa</h4>
      </div>  
      <div class="modal-body">
	  <form class="form-horizontal"  method="post" name="guarda_mesa" id="guarda_mesa" >
	  <div id="resultados_ajax_mesas"></div>
	  <div class="form-group">
            <label class="col-sm-4 control-label">Nombre mesa</label>
            <div class="col-sm-6">
			  <input type="hidden" name="id_mesa" id="id_mesa">
              <input type="text" name="nombre_mesa" class="form-control" id="nombre_mesa" placeholder="Nombre">
            </div>
      </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" id="cerrar_mesa" data-dismiss="modal">Cerrar</button>
		<button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
      </div>
	   </form>
    </div>

  </div>
</div> 
