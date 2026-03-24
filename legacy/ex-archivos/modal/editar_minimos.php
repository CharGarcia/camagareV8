<!-- Modal -->
<div id="EditarMinimos" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Editar mínimos</h4>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" method="post" name="editar_minimo" id="editar_minimo">
          <div id="resultados_ajax_editar_minimos"></div>
          <div class="form-group">
            <label class="col-sm-3 control-label">Mínimo</label>
            <div class="col-sm-4">
              <input type="hidden" name="mod_id_minimo" id="mod_id_minimo">
              <input type="hidden" name="mod_id_producto" id="mod_id_producto">
              <input type="hidden" name="mod_id_bodega" id="mod_id_bodega">
              <input type="number" name="mod_valor_minimo" class="form-control" id="mod_valor_minimo" value="" placeholder="Valor">
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" id="guardar_datos" onclick="guardar_minimo();">Guardar</button>
      </div>
      </form>
    </div>

  </div>
</div>