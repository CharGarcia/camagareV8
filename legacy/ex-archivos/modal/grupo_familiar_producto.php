<!-- Modal -->
<div id="grupo_familiar_producto" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title" id="TituloModalGrupoFamiliarproducto"></h4>
      </div>
      <div class="modal-body">
	  <form class="form-horizontal"  method="post" name="guarda_grupo_familiar_producto" id="guarda_grupo_familiar_producto" >
	  <div id="resultados_ajax_grupo_familiar_producto"></div>
	  <div class="form-group">
            <label class="col-sm-4 control-label">Nombre grupo</label>
            <div class="col-sm-6">
			  <input type="hidden" name="id_grupo_familiar_producto" id="id_grupo_familiar_producto">
              <input type="text" name="nombre_grupo_familiar_producto" class="form-control" id="nombre_grupo_familiar_producto" placeholder="Nombre">
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		<button type="submit" class="btn btn-primary" id="guardar_datos_grupo_familiar_producto">Guardar</button>
      </div>
	   </form>
    </div>

  </div>
</div> 
