<div class="modal fade" id="modalQuincenas" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title" id="titleModalQuincenas"></h5>  
    </div>
      <div class="modal-body">
        <form id="formQuincenas">
          <input type="hidden" id="idQuincena" name="idQuincena" value="">         
          <div class="form-group row">
            <div class="col-sm-6">
              <div class="input-group">
              <span class="input-group-addon"><b>Mes *</b></span>
                <select class="form-control" id="datalistMes" name="datalistMes" tabindex="5">
                  <?php foreach (Meses() as $mes) {
                    if (date("m") == $mes['codigo']) {
                  ?>
                      <option value="<?= $mes['codigo']; ?>" selected><?= ucwords($mes['nombre']); ?></option>
                    <?php
                    } else {
                    ?>
                      <option value="<?= $mes['codigo']; ?>"><?= ucwords($mes['nombre']); ?></option>
                  <?php
                    }
                  }
                  ?>
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
      <span id="resultados_modal_quincena"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal"><i class="fa fa-window-close"></i> Cerrar</button>
        <button type="button" onclick="guarda_quincena();" id="btnActionFormQuincena" class="btn btn-primary"><span id="btnTextQuincena"></span></button>
      </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalViewQuincenas" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true" style="overflow-y: scroll;">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title"><i class="glyphicon glyphicon-list"></i> Detalle de quincenas</h5>  
    </div>
      <div class="modal-body">
      <form method="POST" id="formDetQuincenas" action="../ajax/imprime_documento.php?action=pdf_quincena_general" method="POST" target="_blanck">
          <input type="hidden" id="idQuincenaPrint" name="idQuincenaPrint" value="">
          <div class="form-group row">
          <div class="col-sm-8">
              <div class="input-group">
                <span class="input-group-addon"><b>Buscar</b></span>
                <input type="text" id="qui" class="form-control input-sm" onkeyup='load(1);'>
                <span class="input-group-btn">
                    <button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
                </span>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="input-group">
                <span class="input-group-addon"><b>Quincena</b></span>
                <input type="text" id="periodo_quincena" name="periodo_quincena" class="form-control input-sm" readonly>
              </div>
            </div>
          </div>
        
        <div id="resultados_quincenas"></div><!-- Carga los datos ajax -->
		<div class="outer_div_quincenas"></div><!-- Carga los datos ajax -->
      </div>
      <div class="modal-footer">
      <span id="loader_quincenas"></span>
        <button type="submit" class="btn btn-info" title="Imprimir todos los empleados" target="_blank"><i class="glyphicon glyphicon-print"></i> Imprimir todos</button>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal detalle quincena" onclick='load(1);'>Cerrar</button>
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
    function guarda_quincena() {
        $('#btnTextQuincena').attr("disabled", true);
        var id_quincena = $("#idQuincena").val();
        var mes = $("#datalistMes").val();
        var ano = $("#datalistAno").val();
        
        $.ajax({
            type: "POST",
            url: "../ajax/quincenas.php?action=guardar_quincena",
            data: "id_quincena=" + id_quincena + "&mes=" + mes + "&ano=" + ano,
            beforeSend: function(objeto) {
                $("#resultados_modal_quincena").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_quincena").html(datos);
                $('#btnTextQuincena').attr("disabled", false);
            }
        });
        event.preventDefault();
    }

</script>