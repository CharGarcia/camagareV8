<div class="modal fade" id="modalSueldos" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="titleModalSueldos"></h5>
            </div>
            <div class="modal-body">
                <form id="formSueldos">
                    <input type="hidden" id="idSueldo" value="">
                    <input type="hidden" id="idEmpleado" value="">
                    <div class="form-group row">
                        <div class="col-sm-12">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Empleado *</b></span>
                                <input type="text" name="datalistEmpleados" class="form-control input-sm" id="datalistEmpleados" value="" placeholder="Escribir para buscar un empleado" onkeyup='agregar_empleado();' autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Fondos de reserva *</b></span>
                                <select class="form-control input-sm" id="datalistFR" name="datalistFR" tabindex="2">
                                    <option value="0" selected>Seleccione</option>
                                    <option value="1">Se paga mediante rol mensual</option>
                                    <option value="2">Se paga mediante planilla IESS</option>
                                    <option value="4">No se paga</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Aporta IESS</b></span>
                                <select class="form-control input-sm" id="aportaIess" name="aportaIess">
                                    <option value="1" selected>SI</option>
                                    <option value="0">NO</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Status</b></span>
                                <select class="form-control input-sm" id="listStatus" name="listStatus">
                                    <option value="1" selected>Activo</option>
                                    <option value="2">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Mensualiza 13 sueldo</b></span>
                                <select class="form-control input-sm" id="decimotercero" name="decimotercero">
                                    <option value="1" selected>SI</option>
                                    <option value="0">NO</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Mensualiza 14 sueldo</b></span>
                                <select class="form-control input-sm" id="decimocuarto" name="decimocuarto">
                                    <option value="1" selected>SI</option>
                                    <option value="0">NO</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Departamento</b></span>
                                <select class="form-control input-sm" id="departamento" name="departamento">
                                    <option value="0" selected>Ninguno</option>
                                    <?php
                                    $con = conenta_login();
                                    $sql = mysqli_query($con, "SELECT id, nombre FROM departamentos WHERE id_empresa='" . $id_empresa . "' order by nombre asc");
                                    while ($row = mysqli_fetch_assoc($sql)) {
                                    ?>
                                        <option value="<?php echo $row['id']; ?>"><?php echo strtoupper($row['nombre']); ?></option>
                                    <?php
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Fecha ingreso *</b></span>
                                <input type="text" id="fecha_ingreso" name="fecha_ingreso" class="form-control input-sm text-center">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Fecha salida</b></span>
                                <input type="text" id="fecha_salida" name="fecha_salida" class="form-control input-sm text-center">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Cargo *</b></span>
                                <input type="text" id="cargo" class="form-control input-sm text-center">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-3">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Ap personal *</b></span>
                                <input type="number" id="ap_personal" name="ap_personal" class="form-control input-sm text-right">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Ap patronal *</b></span>
                                <input type="number" id="ap_patronal" name="ap_patronal" class="form-control input-sm text-right">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Código Actividad Sectorial IESS</b></span>
                                <input type="text" id="codigo_iess" name="codigo_iess" class="form-control input-sm text-left">
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Sueldo *</b></span>
                                <input type="number" id="sueldo" name="sueldo" class="form-control input-sm text-right">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Quincena</b></span>
                                <input type="number" id="quincena" name="quincena" class="form-control input-sm text-right">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Región</b></span>
                                <select class="form-control input-sm" id="listRegion" name="listRegion">
                                    <option value="1">Costa</option>
                                    <option value="2" selected>Sierra</option>
                                    <option value="3">Oriente</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h5><i class='glyphicon glyphicon-list'></i> Detalle de ingresos y descuentos fijos mensuales</h5>
                            <div class="form-group row">
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Tipo</b></span>
                                        <select class="form-control input-sm" id="listTipo">
                                            <option value="1" selected>Ingreso</option>
                                            <option value="2">Descuento</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Valor</b></span>
                                        <input type="number" id="valor" name="valor" class="form-control input-sm text-right">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Detalle</b></span>
                                        <input type="text" id="detalle" name="detalle" class="form-control input-sm text-left">
                                    </div>
                                </div>
                                <div class="col-sm-2">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Iess</b></span>
                                        <select class="form-control input-sm" id="iess_otros" title="Aporta al IESS?">
                                            <option value="0">NO</option>
                                            <option value="1">SI</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-1">
                                    <div class="input-group input-group-sm">
                                        <button type="button" onClick="agrega_ingreso_descuento()" class="btn btn-info btn-sm" title="Agregar ingreso o descuento"><i class='glyphicon glyphicon-plus'></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="resultados_ingresos_descuentos"></div><!-- Carga los datos ajax -->
                        <div class="outer_div_ingresos_descuentos"></div><!-- Carga los datos ajax -->
                    </div>
            </div>
            <div class="modal-footer">
                <span id="resultados_modal_sueldo"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal sueldo">Cerrar</button>
                <button type="button" onclick="guarda_sueldo();" id="btnActionFormSueldo" class="btn btn-primary" title=""><span id="btnTextSueldo"></span></button>
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
    jQuery(function($) {
        $("#fecha_ingreso").mask("99-99-9999");
        $("#fecha_salida").mask("99-99-9999");
    });

    function agregar_empleado() {
        $("#datalistEmpleados").autocomplete({
            source: '../ajax/empleado_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#idEmpleado').val(ui.item.id);
                $('#datalistEmpleados').val(ui.item.nombres_apellidos);
                busca_porcentaje_aportes();
            }

        });

        $("#datalistEmpleados").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#idEmpleado").val("");
                $("#datalistEmpleados").val("");
            }
            if (event.keyCode == $.ui.keyCode.DELETE) {
                $("#datalistEmpleados").val("");
                $("#idEmpleado").val("");
            }
        });

    }

    function busca_porcentaje_aportes() {
        let request = (window.XMLHttpRequest) ?
            new XMLHttpRequest() :
            new ActiveXObject('Microsoft.XMLHTTP');
        let ajaxUrl = '../ajax/sueldos.php?action=busca_porcentaje_aportes';
        request.open("GET", ajaxUrl, true);
        request.send();
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                let objData = JSON.parse(request.responseText);
                if (objData.status) {
                    let objSalario = objData.data;
                    document.querySelector("#ap_personal").value = objSalario.ap_personal;
                    document.querySelector("#ap_patronal").value = objSalario.ap_patronal;
                    document.querySelector("#sueldo").value = objSalario.sbu;
                } else {
                    $.notify(objData.msg, "error");
                }
            }
            return false;
        }
    }


    function guarda_sueldo() {
        $('#btnTextSueldo').attr("disabled", true);
        var id_sueldo = $("#idSueldo").val();
        var id_empleado = $("#idEmpleado").val();
        var datalistFR = $("#datalistFR").val();
        var aportaIess = $("#aportaIess").val();
        var listStatus = $("#listStatus").val();
        var decimotercero = $("#decimotercero").val();
        var decimocuarto = $("#decimocuarto").val();
        var departamento = $("#departamento").val();
        var fecha_ingreso = $("#fecha_ingreso").val();
        var fecha_salida = $("#fecha_salida").val();
        var cargo = $("#cargo").val();
        var ap_personal = $("#ap_personal").val();
        var ap_patronal = $("#ap_patronal").val();
        var codigo_iess = $("#codigo_iess").val();
        var sueldo = $("#sueldo").val();
        var quincena = $("#quincena").val();
        var listRegion = $("#listRegion").val();

        if (id_empleado == "") {
            alert('Seleccione un empleado');
            document.getElementById('datalistEmpleados').focus();
            return false;
        }

        if (datalistFR == "0") {
            alert('Seleccione una opción de fondos de reserva');
            document.getElementById('datalistFR').focus();
            return false;
        }

        if (fecha_ingreso == "") {
            alert('Ingrese fecha de ingreso');
            document.getElementById('fecha_ingreso').focus();
            return false;
        }

        if (cargo == "") {
            alert('Ingrese un cargo');
            document.getElementById('cargo').focus();
            return false;
        }

        if (sueldo == "") {
            alert('Ingrese sueldo');
            document.getElementById('sueldo').focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/sueldos.php?action=guardar_sueldo",
            data: "id_empleado=" + id_empleado + "&id_sueldo=" + id_sueldo +
                "&fr=" + datalistFR + "&iess=" + aportaIess + "&status=" + listStatus +
                "&decimotercero=" + decimotercero + "&decimocuarto=" + decimocuarto + "&departamento=" + departamento +
                "&fecha_ingreso=" + fecha_ingreso + "&fecha_salida=" + fecha_salida + "&cargo=" + cargo + "&ap_personal=" + ap_personal +
                "&ap_patronal=" + ap_patronal + "&codigo_iess=" + codigo_iess + "&sueldo=" + sueldo + "&quincena=" + quincena + "&region=" + listRegion,
            beforeSend: function(objeto) {
                $("#resultados_modal_sueldo").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_modal_sueldo").html(datos);
                $('#btnTextSueldo').attr("disabled", false);
            }
        });
        event.preventDefault();
    }


    //agregar informacion adicional
    function agrega_ingreso_descuento() {
        var tipo = $("#listTipo").val();
        var valor = $("#valor").val();
        var detalle = $("#detalle").val();
        var iess = $("#iess_otros").val();

        if (valor == "") {
            alert('Ingrese valor');
            document.getElementById('valor').focus();
            return false;
        }

        if (isNaN(valor)) {
            alert('El dato ingresado en valor, no es un número');
            document.getElementById('valor').focus();
            return false;
        }

        if (detalle == "") {
            alert('Ingrese detalle');
            document.getElementById('detalle').focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/sueldos.php?action=agrega_ingreso_descuento",
            data: "tipo=" + tipo + "&valor=" + valor + "&detalle=" + detalle + "&iess=" + iess,
            beforeSend: function(objeto) {
                $("#resultados_ingresos_descuentos").html("Cargando...");
            },
            success: function(datos) {
                $("#resultados_ingresos_descuentos").html('');
                $(".outer_div_ingresos_descuentos").html(datos).fadeIn('slow');
                $("#valor").val('');
                $("#detalle").val('');
            }
        });
    }
    //para una fila de info adicional
    function eliminar_ingreso_descuento(id) {
        if (confirm("Realmente desea eliminar?")) {
            $.ajax({
                type: "POST",
                url: "../ajax/sueldos.php?action=eliminar_ingreso_descuento",
                data: "id=" + id,
                beforeSend: function(objeto) {
                    $("#resultados_ingresos_descuentos").html("Eliminando...");
                },
                success: function(datos) {
                    $("#resultados_ingresos_descuentos").html('');
                    $(".outer_div_ingresos_descuentos").html(datos).fadeIn('slow');
                }
            });
        }
    }
</script>