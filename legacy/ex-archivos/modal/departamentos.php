<div class="modal fade" id="modalDepartamentos" data-backdrop="static" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title" id="titleModalDepartamento"></h5> 
    </div>
      <div class="modal-body">
        <form id="formDepartamentos">
          <input type="hidden" id="idDepartamento" name="idDepartamento" value="">
          <div class="form-group row">
            <div class="col-sm-7">
              <div class="input-group">
              <span class="input-group-addon"><b>Nombre *</b></span>
                <input type="text" id="txtNombreDepartamento" name="txtNombreDepartamento" class="form-control">
              </div>
            </div>
            <div class="col-sm-5">
              <div class="input-group">
              <span class="input-group-addon"><b>Status *</b></span>
                <select class="form-control form-control-sm" id="listStatusDepartamento" name="listStatusDepartamento">
                  <option value="1" selected>Activo</option>
                  <option value="2">Inactivo</option>
                </select>
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer">
      <span id="resultados_modal_departamento"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal departamento">Cerrar</button>
        <button type="button" onclick="guarda_departamento();" id="btnActionFormDepartamento" class="btn btn-primary" title="Guardar departamento"><span id="btnTextDepartamento"></span></button>
      </div>
      </form>
    </div>
  </div>
</div>
    <script>
    function guarda_departamento() {
                $('#btnTextDepartamento').attr("disabled", true);
                var id_departamento = $("#idDepartamento").val();
                var nombre = $("#txtNombreDepartamento").val();
                var status = $("#listStatusDepartamento").val();
                
                $.ajax({
                    type: "POST",
                    url: "../ajax/departamentos.php?action=guardar_departamento",
                    data: "id_departamento=" + id_departamento + "&nombre=" + nombre +
                        "&status=" + status,
                    beforeSend: function(objeto) {
                        $("#resultados_modal_departamento").html("Guardando...");
                    },
                    success: function(datos) {
                        $("#resultados_modal_departamento").html(datos);
                        $('#btnTextDepartamento').attr("disabled", false);
                    }
                });
                event.preventDefault();
            }
        </script>