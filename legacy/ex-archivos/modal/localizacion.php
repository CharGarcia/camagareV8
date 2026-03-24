<!-- Modal -->
<div data-backdrop="static" id="localizacion" class="modal fade" role="dialog">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h5 class="modal-title" id="titleModalKeyLocalizacion"></h5>
            </div>
            <div class="modal-body">
                <form id="formLocalizacion">
                    <input type="hidden" id="id_key" name="id_key" value="">
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Key Google maps *</b></span>
                                <input type="text" name="key_google" class="form-control" id="key_google" value="" placeholder="key">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-5">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Status *</b></span>
                                <select class="form-control form-control-sm" id="status" name="status">
                                    <option value="1" selected>Activo</option>
                                    <option value="2">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
            </div>
            <div class="modal-footer">
                <span id="resultados_modal_localizacion"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" onclick="guarda_key();" id="btnActionFormKeyLocalizacion" class="btn btn-primary" title="Guardar"><span id="btnTextKeyLocalizacion"></span></button>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
    function guarda_key() {
        $('#btnTextKeyLocalizacion').attr("disabled", true);
        var id_key = $("#id_key").val();
        var key_google = $("#key_google").val();
        var status = $("#status").val();

        $.ajax({
            type: "POST",
            url: "../ajax/localizacion.php?action=guardar_key",
            data: "id_key=" + id_key + "&key_google=" + key_google + "&status=" + status,
            beforeSend: function(objeto) {
                $("#resultados_modal_localizacion").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_localizacion").html(datos);
                $('#btnTextKeyLocalizacion').attr("disabled", false);
            }
        });
        event.preventDefault();
    }
</script>