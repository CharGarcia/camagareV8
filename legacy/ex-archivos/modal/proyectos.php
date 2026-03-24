<!-- Modal -->
<div data-backdrop="static" id="proyectos" class="modal fade" role="dialog">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">×</button>
                <h5 class="modal-title" id="titleModalProyecto"></h5>
            </div>
            <div class="modal-body">
                <form id="formProyecto">
                    <input type="hidden" id="id_proyecto" name="id_proyecto" value="">
                    <div class="form-group row">
                        <div class="col-sm-7">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Nombre *</b></span>
                                <input type="text" name="nombre_proyecto" class="form-control" id="nombre_proyecto" value="" placeholder="Nombre del proyecto">
                            </div>
                        </div>
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
                <span id="resultados_modal_proyectos"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" onclick="guarda_proyecto();" id="btnActionFormProyecto" class="btn btn-primary" title="Guardar proyecto"><span id="btnTextProyecto"></span></button>
            </div>
            </form>
        </div>
    </div>
</div>

<script>
    function guarda_proyecto() {
        $('#btnTextProyecto').attr("disabled", true);
        var id_proyecto = $("#id_proyecto").val();
        var nombre_proyecto = $("#nombre_proyecto").val();
        var status = $("#status").val();

        $.ajax({
            type: "POST",
            url: "../ajax/proyectos.php?action=guardar_proyecto",
            data: "id_proyecto=" + id_proyecto + "&nombre_proyecto=" + encodeURIComponent(nombre_proyecto) + "&status=" + status,
            beforeSend: function(objeto) {
                $("#resultados_modal_proyectos").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_proyectos").html(datos);
                $('#btnTextProyecto').attr("disabled", false);
            }
        });
        event.preventDefault();
    }
</script>