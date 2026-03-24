<!-- Modal -->
<div data-backdrop="static" id="responsable_traslado" class="modal fade" role="dialog">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h5 class="modal-title" id="titleModalresponsable_traslado"></h5>
            </div>
            <div class="modal-body">
                <form id="formresponsable_traslado">
                    <input type="hidden" id="id_responsable_traslado" name="id_responsable_traslado" value="">
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Nombre *</b></span>
                                <input type="text" name="nombre" class="form-control" id="nombre" value="" placeholder="Nombre">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Correo *</b></span>
                                <input type="email" name="correo" class="form-control" id="correo" value="" placeholder="Correo">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Código *</b></span>
                                <input type="password" name="codigo" class="form-control" id="codigo" value="" placeholder="Código">
                            </div>
                        </div>
                        <div class="col-sm-6">
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
                <span id="resultados_modal_responsable_traslado"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" onclick="guarda_responsable_traslado();" id="btnActionFormresponsable_traslado" class="btn btn-primary" title="Guardar"><span id="btnTextresponsable_traslado"></span></button>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
    function guarda_responsable_traslado() {
        $('#btnTextresponsable_traslado').attr("disabled", true);
        var id_responsable_traslado = $("#id_responsable_traslado").val();
        var nombre = $("#nombre").val();
        var correo = $("#correo").val();
        var codigo = $("#codigo").val();
        var status = $("#status").val();

        $.ajax({
            type: "POST",
            url: "../ajax/responsable_traslado.php?action=guardar_responsable_traslado",
            data: "id=" + id_responsable_traslado + "&nombre=" + nombre + "&status=" + status + "&correo=" + correo + "&codigo=" + codigo,
            beforeSend: function(objeto) {
                $("#resultados_modal_responsable_traslado").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_responsable_traslado").html(datos);
                $('#btnTextresponsable_traslado').attr("disabled", false);
            }
        });
        event.preventDefault();
    }
</script>