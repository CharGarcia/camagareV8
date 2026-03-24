<div class="modal fade" id="modalNovedades" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title " id="titleModalNovedades"></h5>
            </div>
            <div class="modal-body">
                <form id="formNovedades">
                    <input type="hidden" id="idNovedad" name="idNovedad" value="">
                    <input type="hidden" id="idEmpleado" name="idEmpleado" value="">
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Empleado *</b></span>
                                <input type="text" name="datalistEmpleados" class="form-control" id="datalistEmpleados" value="" placeholder="Escribir para buscar un empleado" onkeyup='agregar_empleado();' autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Novedad *</b></span>
                            <select class="form-control form-control-sm" tabindex="2" id="datalistNovedad" name="datalistNovedad" data-live-search="true" data-size="10">
                                <option value="0" selected>Seleccione</option>
                                <?php foreach (novedades_sueldos() as $novedad) { ?>
                                    <option value="<?= $novedad['codigo']; ?>"><?= ucwords($novedad['nombre']); ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon" id="titulo_motivo"><b>Motivo *</b></span>
                            <select class="form-control form-control-sm" id="datalistMotivo" name="datalistMotivo" tabindex="3">
                                <option value="0" selected>Seleccione</option>
                                <?php foreach (motivo_salida_iess() as $motivo) { ?>
                                    <option value="<?= $motivo['codigo']; ?>"><?= ucwords($motivo['nombre']); ?></option>
                                <?php
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    </div>
                    <div class="form-group row">
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Fecha novedad *</b></span>
                            <input type="text" id="txtFechaNovedad" name="txtFechaNovedad" class="form-control text-center focusNext" tabindex="4" value="<?php echo date('d-m-Y'); ?>">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Mes afecta *</b></span>
                            <select class="form-control form-control-sm focusNext" id="datalistMes" name="datalistMes" tabindex="5">
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
                    </div>
                    <div class="form-group row">
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Valor novedad *</b></span>
                            <input type="text" id="txtValor" name="txtValor" class="form-control text-right focusNext" tabindex="6" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Año afecta *</b></span>
                            <select class="form-control form-control-sm focusNext" id="datalistAno" name="datalistAno" tabindex="7">
                                <?php foreach (anios(1, 5) as $anios) {
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
                    <div class="form-group row">
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Aplica en *</b></span>
                            <select class="form-control form-control-sm focusNext" tabindex="8" id="datalistAplicaEn" name="datalistAplicaEn">
                                <option value="R" selected>Rol mensual</option>
                                <option value="Q">Quincena</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Aporta IESS</b></span>
                            <select class="form-control form-control-sm focusNext" tabindex="8" id="aportaIess" name="aportaIess">
                                <option value="1">SI</option>
                                <option value="0" selected>NO</option>
                            </select>   
                        </div>
                    </div>
                    </div>
                    <div class="form-group row">
                    <div class="col-sm-12">
                        <div class="input-group">
                            <span class="input-group-addon"><b>Detalle</b></span>
                            <input type="text" id="txtDetalle" name="txtDetalle" class="form-control text-left focusNext" tabindex="10" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
                        </div>
                    </div>
                    </div>

            </div>
            <div class="modal-footer">
            <span id="resultados_modal_novedad"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal novedad">Cerrar</button>
                <button type="button" tabindex="11" onclick="guarda_novedad();" id="btnActionFormNovedad" class="btn btn-primary" title=""><span id="btnTextNovedad"></span></button>
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
    $('#txtFechaNovedad').css('z-index', 1500);
    jQuery(function($) {
            $("#txtFechaNovedad").mask("99-99-9999");
        });

        $(function() {
        $("#txtFechaNovedad").datepicker({
            dateFormat: "dd-mm-yy",
            firstDay: 1,
            dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
            dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
            monthNames: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
                "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"
            ],
            monthNamesShort: ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
                "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"
            ]
        });
    });

    $('#datalistNovedad').change(function(){
        let tipo_novedad = document.getElementById('datalistNovedad').value;
            if (tipo_novedad == 14){
            document.getElementById("titulo_motivo").style.display="";
            document.getElementById("datalistMotivo").style.display="";
            document.getElementById('txtValor').value=1;
            document.getElementById("txtValor").readOnly = true;
            document.getElementById("txtDetalle").readOnly = true;
            }else{
            document.getElementById("titulo_motivo").style.display="none";
            document.getElementById("datalistMotivo").style.display="none";
            document.getElementById("txtValor").readOnly = false;
            document.getElementById("txtDetalle").readOnly = false;
        }

        if (tipo_novedad == 1 || tipo_novedad == 4 || tipo_novedad == 5 || tipo_novedad == 6 ){
            document.getElementById("aportaIess").disabled = false;
            }else{
            document.getElementById("aportaIess").disabled = true;
        }

        if (tipo_novedad == 10){
            document.getElementById("datalistAplicaEn").value = "R";
        }
        });


    function agregar_empleado(){
        $("#datalistEmpleados").autocomplete({
            source:'../ajax/empleado_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#idEmpleado').val(ui.item.id);
                $('#datalistEmpleados').val(ui.item.nombres_apellidos);
                //document.getElementById('cantidad').focus();
            }

        });

        $("#datalistEmpleados" ).on( "keydown", function( event ) {
            if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
            {
                $("#idEmpleado" ).val("");
                $("#datalistEmpleados" ).val("");
            }
            if (event.keyCode==$.ui.keyCode.DELETE){
                $("#datalistEmpleados" ).val("");
                $("#idEmpleado" ).val("");
            }
        });
    }


    function guarda_novedad() {
        $('#btnTextNovedad').attr("disabled", true);
        var idNovedad = $("#idNovedad").val();
        var id_empleado = $("#idEmpleado").val();
        var datalistNovedad = $("#datalistNovedad").val();
        var datalistMotivo = $("#datalistMotivo").val();
        var txtFechaNovedad = $("#txtFechaNovedad").val();
        var datalistMes = $("#datalistMes").val();
        var txtValor = $("#txtValor").val();
        var datalistAno = $("#datalistAno").val();
        var datalistAplicaEn = $("#datalistAplicaEn").val();
        var checkIess = $("#aportaIess").val();
        var txtDetalle = $("#txtDetalle").val();

        if (id_empleado == "") {
            alert('Seleccione un empleado');
            document.getElementById('datalistEmpleados').focus();
            return false;
        }

        if (datalistNovedad == "0") {
            alert('Seleccione una novedad');
            document.getElementById('datalistNovedad').focus();
            return false;
        }

        if (txtValor == "") {
            alert('Ingrese valor');
            document.getElementById('txtValor').focus();
            return false;
        }

        if (isNaN(txtValor)) {
            alert('El dato ingresado en valor, no es un número');
            document.getElementById('txtValor').focus();
            return false;
        }
        
        $.ajax({
            type: "POST",
            url: "../ajax/novedades.php?action=guardar_novedad",
            data: "id_empleado=" + id_empleado + "&id_registro=" + idNovedad +
                "&id_novedad=" + datalistNovedad + "&motivo_salida=" + datalistMotivo + "&fecha_novedad=" + txtFechaNovedad
                + "&mes=" + datalistMes + "&valor=" + txtValor + "&ano=" + datalistAno
                + "&aplica_en=" + datalistAplicaEn + "&iess=" + checkIess + "&detalle=" + txtDetalle,
            beforeSend: function(objeto) {
                $("#resultados_modal_novedad").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_novedad").html(datos);
                $('#btnTextNovedad').attr("disabled", false);
            }
        });
        event.preventDefault();
        
    }
</script>