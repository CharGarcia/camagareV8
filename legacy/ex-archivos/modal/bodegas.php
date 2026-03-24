<!-- Modal -->
<div data-backdrop="static" id="bodegas" class="modal fade" role="dialog">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h5 class="modal-title" id="titleModalBodega"></h5>
      </div>
      <div class="modal-body">
        <form id="formBodega">
          <input type="hidden" id="idBodega" name="idBodega" value="">
          <div class="form-group row">
            <div class="col-sm-7">
              <div class="input-group">
                <span class="input-group-addon"><b>Nombre *</b></span>
                <input type="text" name="nombre_bodega" class="form-control" id="nombre_bodega" value="" placeholder="Nombre de la bodega">
              </div>
            </div>
            <div class="col-sm-5">
              <div class="input-group">
                <span class="input-group-addon"><b>Status *</b></span>
                <select class="form-control form-control-sm" id="listStatus" name="listStatus">
                  <option value="1" selected>Activo</option>
                  <option value="2">Inactivo</option>
                </select>
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <span id="resultados_modal_bodega"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        <button type="button" onclick="guarda_bodega();" id="btnActionFormBodega" class="btn btn-primary" title="Guardar bodega"><span id="btnTextBodega"></span></button>
      </div>
      </form>
    </div>
  </div>
</div>


<!--modal bodegas asigandas -->
<div data-backdrop="static" id="bodegas_asignadas" class="modal fade" role="dialog">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h5 class="modal-title"><i class='glyphicon glyphicon-ok'></i> Bodega Asignada a Usuarios</h5>
      </div>
      <div class="modal-body">
        <form id="formBodegaAsignada">
        <input type="hidden" id="idBodegaAsignada" name="idBodegaAsignada" value="">
        <div class="form-group row">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Bodega</b></span>
                <input type="text" name="nombre_bodega_asignada" class="form-control" id="nombre_bodega_asignada" value="" readonly>
              </div>
            </div>
          </div>
          <div id="resultados_asignaciones"></div><!-- Carga los datos ajax -->
			  <div class="outer_div_asiganciones"></div><!-- Carga los datos ajax -->
      </div>
      <div class="modal-footer">
        <span id="resultados_modal_bodega_asignada"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
      </form>
    </div>
  </div>
</div>
<script>
  function guarda_bodega() {
    $('#btnTextBodega').attr("disabled", true);
    var id_bodega = $("#idBodega").val();
    var nombre_bodega = $("#nombre_bodega").val();
    var listStatus = $("#listStatus").val();

    $.ajax({
      type: "POST",
      url: "../ajax/bodegas.php?action=guardar_bodega",
      data: "id_bodega=" + id_bodega + "&nombre_bodega=" + nombre_bodega +
        "&status=" + listStatus,
      beforeSend: function(objeto) {
        $("#resultados_modal_bodega").html("Guardando...");
      },
      success: function(datos) {
        $("#resultados_modal_bodega").html(datos);
        $('#btnTextBodega').attr("disabled", false);
      }
    });
    event.preventDefault();
  }
</script>