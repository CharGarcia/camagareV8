<div class="modal fade" id="modalDecimoCuarto" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title" id="titleModalDecimoCuarto"></h5>
      </div>
      <div class="modal-body">
        <form id="formDecimoCuarto">
          <input type="hidden" id="idDecimoCuarto" name="idDecimoCuarto" value="">
          <div class="form-group row">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Región *</b></span>
                <select class="form-control" id="region" name="region">
                  <option value="1" selected>Sierra - Amazonica</option>
                  <option value="2">Costa</option>
                </select>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Año *</b></span>
                <select class="form-control" id="datalistAno" name="datalistAno">
                  <?php foreach (anios(0, 5) as $anios) {
                    if (date("Y") == $anios) {
                  ?>
                      <option value="<?= $anios; ?>" selected><?= ucwords($anios); ?></option>
                    <?php
                    } else {
                    ?>
                      <option value="<?= $anios; ?>"><?= ucwords($anios); ?></option>
                  <?php
                    }
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <span id="resultados_modal_decimocuarto"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal"><i class="fa fa-window-close"></i> Cerrar</button>
        <button type="button" onclick="guarda_decimocuarto();" id="btnActionFormDecimoCuarto" class="btn btn-primary"><span id="btnTextDecimoCuarto"></span></button>
      </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalViewDecimoCuarto" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true" style="overflow-y: scroll;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title"><i class="glyphicon glyphicon-list"></i> Detalle de décimo cuarto</h5>
      </div>
      <div class="modal-body">
        <form method="POST" id="formDetDecimoCuarto" action="../ajax/imprime_documento.php?action=pdf_decimocuarto_general" method="POST" target="_blanck">
          <input type="hidden" id="idDcPrint" name="idDcPrint" value="">
          <div class="form-group row">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Buscar</b></span>
                <input type="text" id="dc" class="form-control input-sm" onkeyup='load(1);'>
                <span class="input-group-btn">
                  <button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
                </span>
              </div>
            </div>
            <div class="col-sm-2">
              <div class="input-group">
                <span class="input-group-addon"><b>Año</b></span>
                <input type="text" id="periodo_decimocuarto" name="periodo_decimocuarto" class="form-control input-sm" readonly>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="input-group">
                <span class="input-group-addon"><b>Región</b></span>
                <select class="form-control" id="region_generada" name="region_generada" disabled>
                  <option value="1">Sierra - Amazonica</option>
                  <option value="2">Costa</option>
                </select>
              </div>
            </div>
          </div>
          <div id="resultados_decimocuarto"></div><!-- Carga los datos ajax -->
          <div class="outer_div_decimocuarto"></div><!-- Carga los datos ajax -->
      </div>
      <div class="modal-footer">
        <span id="loader_decimocuarto"></span>
        <a href="../xml/decimocuarto.csv" class="btn btn-default btn-md" title="Archivo ministerio de trabajo" download><i class="glyphicon glyphicon-duplicate"> </i> MT</a>
        <button type="submit" class="btn btn-info" title="Imprimir todos los empleados" target="_blank"><i class="glyphicon glyphicon-print"></i> Imprimir</button>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar detalle" onclick='load(1);'>Cerrar</button>
      </div>
      </form>
    </div>
  </div>
</div>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script src="../js/notify.js"></script>
<script>
  function guarda_decimocuarto() {
    $('#btnTextDecimoCuarto').attr("disabled", true);
    var idDecimoCuarto = $("#idDecimoCuarto").val();
    var region = $("#region").val();
    var anio = $("#datalistAno").val();

    $.ajax({
      type: "POST",
      url: "../ajax/decimocuarto.php?action=guardar_decimocuarto",
      data: "id_dc=" + idDecimoCuarto + "&region=" + region + "&anio=" + anio,
      beforeSend: function(objeto) {
        $("#resultados_modal_decimocuarto").html("Guardando...");
      },
      success: function(datos) {
        $("#resultados_modal_decimocuarto").html(datos);
        $('#btnTextDecimoCuarto').attr("disabled", false);
      }
    });
    event.preventDefault();
  }
</script>