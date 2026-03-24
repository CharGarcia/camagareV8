<!-- Modal -->
<div class="modal fade" data-backdrop="static" id="recibo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" style="overflow-y: scroll;">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="titleModalRecibo"></h4>
            </div>
            <div class="modal-body">

                <form class="form-horizontal" method="POST" id="guardar_recibo">
                    <input type="hidden" id="id_recibo">
                    <input type="hidden" id="id_cliente_recibo">
                    <div class="well well-sm" style="margin-bottom: -10px; margin-top: -10px; height: 14%;">
                        <div class="form-group row">
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Emisión</b></span>
                                    <input type="text" class="form-control input-sm" id="fecha_recibo" value="<?php echo date("d-m-Y"); ?>">
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Serie</b></span>
                                    <select class="form-control input-sm" id="serie_recibo">
                                        <option value="">Seleccione</option>
                                        <?php
                                        $con = conenta_login();
                                        $sql = "SELECT * FROM sucursales where ruc_empresa ='" . $ruc_empresa . "' order by serie desc;";
                                        $res = mysqli_query($con, $sql);
                                        while ($serie = mysqli_fetch_assoc($res)) {
                                        ?>
                                            <option value="<?php echo $serie['serie'] ?>" selected><?php echo $serie['serie'] ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Secuencial</b></span>
                                    <input type="text" class="form-control input-sm text-right" id="secuencial_recibo" placeholder="000000001" readonly>
                                </div>
                            </div>
                            <div class="col-sm-3" id="label_bodega_producto_recibo">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Bodega</b></span>
                                    <select class="form-control input-sm" id="bodega_producto_recibo">
                                        <?php
                                        $sql_bodega = mysqli_query($con, "SELECT bod.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega FROM bodega as bod LEFT JOIN bodega_restringida as res ON res.id_bodega = bod.id_bodega and res.id_usuario='" . $id_usuario . "' WHERE res.id_usuario is null and bod.ruc_empresa ='" . $ruc_empresa . "' and bod.status ='1' order by bod.nombre_bodega asc");
                                        while ($row_bodega = mysqli_fetch_array($sql_bodega)) {
                                        ?>
                                            <option value="<?php echo $row_bodega['id_bodega'] ?>"><?php echo strtoupper($row_bodega['nombre_bodega']) ?></option>
                                        <?php
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                        </div>
                        <div class="form-group row">
                            <div class="col-sm-9">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Cliente</b></span>
                                    <span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Agregar consumidor final" onclick="consumidor_final()">CF</button></span>
                                    <input type="text" class="form-control input-sm" id="nombre_cliente_recibo" placeholder="Agregue un cliente por ruc, cedula o nombre" title="Buscar un cliente." onkeyup='buscar_clientes();' autocomplete="off">
                                    <span class="input-group-btn btn-md"><button class="btn btn-info btn-sm" type="button" title="Nuevo cliente" onclick="crear_cliente()" data-toggle="modal" data-target="#nuevoCliente"><span class="glyphicon glyphicon-pencil"></span></button></span>
                                </div>
                            </div>
                            <div class="col-sm-3" id="label_existencia_producto_recibo">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Existencia</b></span>
                                    <input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto_recibo" placeholder="0" readonly>
                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- para agregar los productos a la recibo -->

                    <div class="panel panel-info">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr class="info">
                                    <th style="padding: 2px;">Producto/Servicio</th>
                                    <th style="padding: 2px;" class="text-center" id="label_lote_producto_recibo">Lote</th>
                                    <th style="padding: 2px;" class="text-center" id="label_caducidad_producto_recibo">Caducidad</th>
                                    <th style="padding: 2px;" class="text-center">Cantidad</th>
                                    <th style="padding: 2px;" class="text-center" id="label_medida_producto_recibo">Medida</th>
                                    <th style="padding: 2px;" class="text-center">Pr. Sin IVA</th>
                                    <th style="padding: 2px;" class="text-center">Pr. Con IVA</th>
                                    <th style="padding: 2px;" class="text-center">$</th>
                                    <th style="padding: 2px;" class="text-center">↓</th>
                                </tr>

                                <td class="col-xs-6" style="padding: 1px;">
                                    <input type="hidden" id="inventario_producto_recibo" value="SI">
                                    <input type="hidden" id="id_producto_recibo">
                                    <input type="hidden" id="tipo_producto_recibo">
                                    <input type="hidden" id="precio_tmp_producto_recibo">
                                    <input type="hidden" id="stock_tmp_producto_recibo">
                                    <input type="hidden" id="muestra_medida_producto_recibo">
                                    <input type="hidden" id="muestra_lote_producto_recibo">
                                    <input type="hidden" id="muestra_bodega_producto_recibo">
                                    <input type="hidden" id="muestra_vencimiento_producto_recibo">
                                    <input type="hidden" id="suma_recibo">
                                    <input type="hidden" id="porcentaje_iva">
                                    <div class="input-group">
                                        <input style="z-index:inherit; height:25px;" type="text" class="form-control input-sm" title="Buscar o leer código de barras de un producto o servicio." name="nombre_producto_recibo" id="nombre_producto_recibo" placeholder="Ingresar o leer código de barras de un producto" onkeyup='buscar_productos();' autocomplete="off">
                                        <span class="input-group-btn btn-md">
                                            <button class="btn btn-info btn-sm" style="height:25px;" type="button" title="Nuevo producto o servicio" data-toggle="modal" data-target="#productos" onclick="crear_producto();"><span class="glyphicon glyphicon-pencil"></span></button>
                                        </span>
                                    </div>
                                </td>
                                <td id="lista_lote_producto_recibo" style="padding: 1px;">
                                    <select class="form-control" style="text-align:left; width: auto; height:25px; padding-top:3px;" title="Seleccione lote." id="lote_producto_recibo">
                                    </select>
                                </td>
                                <td id="lista_caducidad_producto_recibo" style="padding: 1px;">
                                    <select class="form-control" style="text-align:left; width: auto; height:25px; padding-top:3px;" title="Seleccione caducidad." id="caducidad_producto_recibo">
                                    </select>
                                </td>
                                <td style="padding: 1px;">
                                    <div class="pull-right">
                                        <input type="text" class="form-control input-sm" style="text-align:right; width: fixed; height:25px;" title="Ingrese cantidad" id="cantidad_producto_recibo" placeholder="Cantidad">
                                    </div>
                                </td>
                                <td id="lista_medida_producto_recibo" style="padding: 1px;">
                                    <select class="form-control" style="text-align:left; width: auto; height:25px; padding-top:3px;" title="Seleccione medida" id="medida_producto_recibo">
                                    </select>
                                </td>
                                <td style="padding: 1px;">
                                    <div class="pull-right">
                                        <input type="text" class="form-control input-sm" style="text-align:right; width: fixed; height:25px;" title="Precio con iva" id="precio_producto_recibo" oninput="precio_sin_iva();" placeholder="Precio sin iva">
                                    </div>
                                </td>
                                <td style="padding: 1px;">
                                    <div class="pull-right">
                                        <input type="text" class="form-control input-sm" style="text-align:right; width: fixed; height:25px;" title="Precio incluido iva" id="precio_producto_recibo_coniva" oninput="precio_con_iva();" placeholder="Precio con iva">
                                    </div>
                                </td>
                                <td class="col-xs-1" style="padding: 1px;">
                                    <select style="text-align:right; height:25px;" class="form-control btn-sm" title="Seleccione precio" id="lista_precios_producto_recibo">
                                    </select>
                                </td>
                                <td style="text-align:center; padding: 1px;">
                                    <button type="button" style="height:25px;" class="btn btn-info btn-sm" title="Agregar producto o servicio a la recibo" onclick="agregar_item_recibo()"><span class="glyphicon glyphicon-plus"></span></button>
                                </td>
                            </table>
                        </div>
                    </div>

                    <div class="panel panel-info" style="margin-bottom: 4px; margin-top: -15px; height: 150px;overflow-y: auto;">
                        <div id="detalle_recibo"></div><!-- Carga los datos ajax -->
                    </div>

                    <!-- para mostrar los adicionales de la recibo  formas de pago y subtotales-->

                    <div class="panel panel-info" style="margin-bottom: -10px; height: 14%">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr class="info">
                                    <th style="padding: 2px;" class="text-left">Información adicional</th>
                                    <th style="padding: 2px;" class="text-left">Subtotales</th>
                                </tr>

                                <td class="col-xs-5" style="padding: 0px;">
                                    <div id="detalle_informacion_adicional"></div>
                                </td>

                                <td class="col-xs-3" style="padding: 0px;">
                                    <div id="detalle_subtotales_recibo"></div>
                                </td>
                            </table>
                        </div>
                    </div>
            </div>
            <div class="modal-footer">
                <span id="resultados_ajax_guardar"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="guarda_recibo();" id="btnActionFormRecibo"><span id="btnTextRecibo"></span></button>
            </div>
            </form>
        </div>
    </div>
</div>
<?php
include("../modal/clientes.php");
include("../modal/productos.php");
include("../modal/aplicar_descuento.php");
//include("../modal/cobro_pago_directo.php");
?>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script src="../js/notify.js"></script>

<script>
    $('#fecha_recibo').css('z-index', 1500);

    jQuery(function($) {
        $("#fecha_recibo").mask("99-99-9999");
    });

    $(function() {
        $("#fecha_recibo").datepicker({
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

    function crear_cliente() {
        document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
        document.querySelector("#guardar_cliente").reset();
        document.querySelector("#id_cliente").value = "";
        document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
        document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
        document.querySelector('#btnActionFormCliente').title = "Guardar cliente";
    }

    function crear_producto() {
        document.querySelector("#titleModalProducto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo producto o servicio";
        document.querySelector("#guardar_producto").reset();
        document.querySelector("#id_producto").value = "";
        document.querySelector("#btnActionFormProducto").classList.replace("btn-info", "btn-primary");
        document.querySelector("#btnTextProducto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
        document.querySelector('#btnActionFormProducto').title = "Guardar Producto";

        document.getElementById("label_marca_producto").style.display = "none";
        document.getElementById("label_medida_producto").style.display = "none";
        document.getElementById("label_unidad_producto").style.display = "none";
        document.getElementById("label_iva_producto").style.display = "none";
    }

    function consumidor_final() {
        let request = (window.XMLHttpRequest) ?
            new XMLHttpRequest() :
            new ActiveXObject('Microsoft.XMLHTTP');
        let ajaxUrl = '../ajax/facturas.php?action=busca_consumidor_final';
        request.open("GET", ajaxUrl, true);
        request.send();
        request.onreadystatechange = function() {
            if (request.readyState == 4 && request.status == 200) {
                let objData = JSON.parse(request.responseText);
                if (objData.status) {
                    let objCf = objData;
                    document.querySelector("#id_cliente_recibo").value = objCf.id_cliente;
                    document.querySelector("#nombre_cliente_recibo").value = objCf.nombre_cliente;
                    document.getElementById('nombre_producto_recibo').focus();
                    cambia_cliente();
                } else {
                    document.getElementById('nombre_cliente_factura').focus();
                    $.notify(objData.msg, "error");
                }
            }
            return false;
        }
    }
    //para mostrar la recibo que continua segun la serie seleccionada
    $(function() {
        $('#serie_recibo').change(function() {
            var id_serie = $("#serie_recibo").val();
            $.post('../ajax/buscar_ultimo_recibo.php', {
                serie_fe: id_serie
            }).done(function(respuesta) {
                var recibo_final = respuesta;
                $("#secuencial_recibo").val(recibo_final);
            });

            //limpia todos los campos
            $("#inventario_producto_recibo").val("");
            $("#muestra_medida_producto_recibo").val("");
            $("#muestra_lote_producto_recibo").val("");
            $("#muestra_bodega_producto_recibo").val("");
            $("#muestra_vencimiento_producto_recibo").val("");
            $('#inventario_producto_recibo').val('SI');

            //para traer y ver si trabaja con medida
            $.post('../ajax/consulta_configuracion_facturacion.php', {
                opcion_mostrar: 'medida',
                serie_consultada: id_serie
            }).done(function(respuesta_medida) {
                var resultado_medida = $.trim(respuesta_medida);
                $('#muestra_medida_producto_recibo').val(resultado_medida);
            });

            //para traer y ver si trabaja con lote
            $.post('../ajax/consulta_configuracion_facturacion.php', {
                opcion_mostrar: 'lote',
                serie_consultada: id_serie
            }).done(function(respuesta_lote) {
                var resultado_lote = $.trim(respuesta_lote);
                $('#muestra_lote_producto_recibo').val(resultado_lote);
            });

            //para traer y ver si trabaja con bodega
            $.post('../ajax/consulta_configuracion_facturacion.php', {
                opcion_mostrar: 'bodega',
                serie_consultada: id_serie
            }).done(function(respuesta_bodega) {
                var resultado_bodega = $.trim(respuesta_bodega);
                $('#muestra_bodega_producto_recibo').val(resultado_bodega);
            });

            //para traer y ver si trabaja con vencimiento
            $.post('../ajax/consulta_configuracion_facturacion.php', {
                opcion_mostrar: 'vencimiento',
                serie_consultada: id_serie
            }).done(function(respuesta_vencimiento) {
                var resultado_vencimiento = $.trim(respuesta_vencimiento);
                $('#muestra_vencimiento_producto_recibo').val(resultado_vencimiento);
            });

        });
    })

    //para buscar los clientes
    function buscar_clientes() {
        $("#nombre_cliente_recibo").autocomplete({
            appendTo: "#recibo",
            source: '../ajax/clientes_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#id_cliente_recibo').val(ui.item.id);
                $('#nombre_cliente_recibo').val(ui.item.nombre);
                document.getElementById('nombre_producto_recibo').focus();
                cambia_cliente();
            }
        });

        $("#nombre_cliente_recibo").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_cliente_recibo").val("");
                $("#nombre_cliente_recibo").val("");
            }
            if (event.keyCode == $.ui.keyCode.DELETE) {
                $("#nombre_cliente_recibo").val("");
                $("#id_cliente_recibo").val("");
            }
        });
    }

    //al cambiar de cliente debe actualizarse los datos del mismo en adicionales
    function cambia_cliente() {
        var id_cliente = $("#id_cliente_recibo").val();
        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=informacion_cliente",
            data: "id_cliente=" + id_cliente,
            beforeSend: function(objeto) {
                $("#detalle_informacion_adicional").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_informacion_adicional").html(datos);
            }
        });
    }

    //para buscar un producto ya sea con pistola o ingresando por teclado
    function buscar_productos() {
        var id_cliente = $("#id_cliente_recibo").val();
        var id_serie = $("#serie_recibo").val();

        //para usar el lector de barras
        var keycode = event.keyCode;
        if (keycode == '13') {
            document.getElementById("label_existencia_producto_recibo").style.display = "";
            var codigo_producto = $("#nombre_producto_recibo").val();
            var bodega = $("#bodega_producto_recibo").val();
            let request = (window.XMLHttpRequest) ?
                new XMLHttpRequest() :
                new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = '../ajax/facturas.php?action=bar_code&codigo_producto=' + codigo_producto;
            request.open("GET", ajaxUrl, true);
            request.send();
            request.onreadystatechange = function() {
                if (request.readyState == 4 && request.status == 200) {
                    let objData = JSON.parse(request.responseText);
                    if (objData.status) {
                        let objProducto = objData;

                        $.post('../ajax/saldo_producto_inventario.php', {
                            id_bodega: bodega,
                            id_producto: objProducto.id_producto
                        }).done(function(respuesta) {
                            var saldo_producto = respuesta;
                            $("#existencia_producto_recibo").val(saldo_producto);
                            $('#stock_tmp_producto_recibo').val(saldo_producto);
                        });

                        document.querySelector("#id_producto_recibo").value = objProducto.id_producto;
                        document.querySelector("#nombre_producto_recibo").value = objProducto.nombre_producto;
                        document.querySelector("#precio_producto_recibo").value = objProducto.precio_producto;
                        document.querySelector("#precio_producto_recibo_coniva").value = objProducto.precio_iva;
                        document.querySelector("#porcentaje_iva").value = objProducto.porcentaje_iva;
                        document.querySelector("#medida_producto_recibo").value = objProducto.id_medida;
                        document.querySelector("#tipo_producto_recibo").value = objProducto.tipo_produccion;
                        document.querySelector("#cantidad_producto_recibo").value = 1;
                        document.getElementById('cantidad_producto_recibo').focus();
                    } else {
                        $.notify(objData.msg, "error");
                    }
                }
                return false;
            }
        }

        //para cuando se busca ingresando manualmente
        $("#nombre_producto_recibo").autocomplete({
            appendTo: "#recibo",
            source: '../ajax/productos_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#id_producto_recibo').val(ui.item.id);
                $('#nombre_producto_recibo').val(ui.item.nombre);
                $('#precio_producto_recibo').val(ui.item.precio);
                $('#precio_tmp_producto_recibo').val(ui.item.precio);
                $('#tipo_producto_recibo').val(ui.item.tipo);
                $('#porcentaje_iva').val(ui.item.porcentaje_iva);
                var id_medida = ui.item.medida;

                var tipo_producto = $("#tipo_producto_recibo").val();
                var configuracion_inventario = "SI"; //document.getElementById('inventario_producto_recibo').value;
                var configuracion_medida = document.getElementById('muestra_medida_producto_recibo').value;
                var configuracion_lote = document.getElementById('muestra_lote_producto_recibo').value;
                var configuracion_bodega = document.getElementById('muestra_bodega_producto_recibo').value;
                var configuracion_vencimiento = document.getElementById('muestra_vencimiento_producto_recibo').value;
                var producto = $("#id_producto_recibo").val();

                if (tipo_producto == "01") {
                    document.getElementById("label_medida_producto_recibo").style.display = "";
                    document.getElementById("lista_medida_producto_recibo").style.display = "";
                    $.post('../ajax/recibo_venta.php?action=tipo_medida_producto', {
                        id_medida: id_medida
                    }).done(function(respuesta) {
                        $("#medida_producto_recibo").html(respuesta);
                    });
                }

                if (tipo_producto == "02") {
                    document.getElementById("label_bodega_producto_recibo").style.display = "none";
                    document.getElementById("label_lote_producto_recibo").style.display = "none";
                    document.getElementById("label_caducidad_producto_recibo").style.display = "none";
                    document.getElementById("label_medida_producto_recibo").style.display = "none";
                    document.getElementById("label_existencia_producto_recibo").style.display = "none";
                    document.getElementById("lista_lote_producto_recibo").style.display = "none";
                    document.getElementById("lista_caducidad_producto_recibo").style.display = "none";
                    document.getElementById("lista_medida_producto_recibo").style.display = "none";
                }

                //aqui controla cuando se selecciona producto y trabaja con inventario
                if (tipo_producto == "01" && configuracion_inventario == 'SI') {


                    if (configuracion_lote == 'SI') {
                        document.getElementById("label_lote_producto_recibo").style.display = "";
                        document.getElementById("lista_lote_producto_recibo").style.display = "";
                    }
                    if (configuracion_vencimiento == 'SI') {
                        document.getElementById("label_caducidad_producto_recibo").style.display = "";
                        document.getElementById("lista_caducidad_producto_recibo").style.display = "";
                    }
                    if (configuracion_medida == "SI") {
                        document.getElementById("label_medida_producto_recibo").style.display = "";
                        document.getElementById("lista_medida_producto_recibo").style.display = "";
                    }

                    document.getElementById("label_bodega_producto_recibo").style.display = "";
                    document.getElementById("label_existencia_producto_recibo").style.display = "";

                    $("#existencia_producto_recibo").val("0");
                    var bodega = $("#bodega_producto_recibo").val();
                    //var producto = $("#id_producto_agregar").val();

                    //cuando trae se busca el producto me trae que tipo de medida tiene
                    $.post('../ajax/recibo_venta.php?action=tipo_medida_producto', {
                        id_medida: id_medida
                    }).done(function(respuesta) {
                        $("#medida_producto_recibo").html(respuesta);
                    });

                    //para que se cargue el stock del producto al momento de buscar el producto dependiendo de la bodega que esta seleeccionada por default
                    $.post('../ajax/saldo_producto_inventario.php', {
                        id_bodega: bodega,
                        id_producto: producto
                    }).done(function(respuesta) {
                        var saldo_producto = respuesta;
                        $("#existencia_producto_recibo").val(saldo_producto);
                        $('#stock_tmp_producto_recibo').val(saldo_producto);
                    });

                    //para traer todos los lotes en base a una bodega al momento de buscar un producto
                    $.post('../ajax/select_opciones_inventario.php', {
                        opcion: 'lote',
                        id_producto: producto,
                        bodega: bodega
                    }).done(function(res_opciones_lote) {
                        $("#lote_producto_recibo").html(res_opciones_lote);
                    });

                    //para traer todos las caducidades en base a una bodega al momento de buscar un producto
                    $.post('../ajax/select_opciones_inventario.php', {
                        opcion: 'caducidad',
                        id_producto: producto,
                        bodega: bodega
                    }).done(function(res_opciones_caducidad) {
                        $("#caducidad_producto_recibo").html(res_opciones_caducidad);
                    });

                }

                //para traer todos los precios de ese producto
                $.post('../ajax/select_tipo_precio.php', {
                    id_producto: producto,
                    serie_sucursal: id_serie
                }).done(function(res_tipos_precios) {
                    $("#lista_precios_producto_recibo").html(res_tipos_precios);
                });

                //hasta aqui me controla si trabaja con inventario
                $('#cantidad_producto_recibo').val('1');
                document.getElementById('cantidad_producto_recibo').focus();
            }
        });

        //$("#nombre_producto_recibo").autocomplete("widget").addClass("fixedHeight"); //para que aparezca la barra de desplazamiento en el buscar

        $("#nombre_producto_recibo").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_producto_recibo").val("");
                $("#nombre_producto_recibo").val("");
                $("#precio_producto_recibo").val("");
                $("#tipo_producto_recibo").val("");
                $("#existencia_producto_recibo").val("");
                $("#medida_producto_recibo").val("");
                $("#stock_tmp_producto_recibo").val("");
            }
        });
    }

    //cuando cambian los selects al seleccionar un producto para agregar a la recibo
    $(function() {
        //para cuando se cambia el select de bodega que me cargue el saldo de ese producto
        $('#bodega_producto_recibo').change(function() {
            //reinicia la medida
            $("#id_producto_recibo").val("");
            $("#nombre_producto_recibo").val("");
            $("#precio_producto_recibo").val("");
            $("#tipo_producto_recibo").val("");
            $("#existencia_producto_recibo").val("");
            $("#medida_producto_recibo").val("");
            $("#stock_tmp_producto_recibo").val("");
            document.getElementById('nombre_producto_recibo').focus();
        });

        //para traer el valor de conversion de medidas en el producto cuando se cambia el select de lote
        $('#lote_producto_recibo').change(function() {
            var id_bodega = $("#bodega_producto_recibo").val();
            var id_producto = $("#id_producto_recibo").val();
            var lote = $("#lote_producto_recibo").val();
            var id_medida = $("#medida_producto_recibo").val();
            $.post('../ajax/saldo_producto_inventario.php', {
                opcion_lote: lote,
                id_producto: id_producto,
                bodega: id_bodega
            }).done(function(respuesta_lote) {
                $("#existencia_producto_recibo").val(respuesta_lote);
            });

            //reinicia la medida
            $.post('../ajax/recibo_venta.php?action=tipo_medida_producto', {
                id_medida: id_medida
            }).done(function(res_id_medidas) {
                $("#medida_producto_recibo").html(res_id_medidas);
            });

            //reinicie el precio
            var precio_venta = $("#precio_tmp_producto_recibo").val();
            $("#precio_producto_recibo").val(precio_venta);

            //para reinicie vencimiento
            $.post('../ajax/select_opciones_inventario.php', {
                opcion: 'caducidad',
                id_producto: id_producto,
                bodega: id_bodega
            }).done(function(res_opciones_caducidad) {
                $("#caducidad_producto_recibo").html(res_opciones_caducidad);
            });
            document.getElementById('cantidad_producto_recibo').focus();
        });

        //para traer el valor de conversion de medidas en el producto cuando se cambia el select de medida
        $('#medida_producto_recibo').change(function() {
            var id_producto = $("#id_producto_recibo").val();
            var id_medida = $("#medida_producto_recibo").val();
            var precio_venta = $("#precio_tmp_producto_recibo").val();
            var stock_tmp = $("#stock_tmp_producto_recibo").val();
            $.post('../ajax/saldo_producto_inventario.php', {
                id_medida_seleccionada: id_medida,
                id_producto: id_producto,
                precio_venta: precio_venta,
                stock_tmp: stock_tmp,
                dato_obtener: 'saldo'
            }).done(function(respuesta_saldo) {
                $("#existencia_producto_recibo").val(respuesta_saldo);
            });

            $.post('../ajax/saldo_producto_inventario.php', {
                id_medida_seleccionada: id_medida,
                id_producto: id_producto,
                precio_venta: precio_venta,
                stock_tmp: stock_tmp,
                dato_obtener: 'precio'
            }).done(function(respuesta_precio) {
                $("#precio_producto_recibo").val(respuesta_precio);
            });
        });


        //para traer el valor de conversion de medidas en el producto cuando se cambia el select de caducidad
        $('#caducidad_producto_recibo').change(function() {
            var id_producto = $("#id_producto_recibo").val();
            var id_medida = $("#medida_producto_recibo").val();
            var precio_venta = $("#precio_tmp_producto_recibo").val();
            var caducidad = $("#caducidad_producto_recibo").val();
            $.post('../ajax/saldo_producto_inventario.php', {
                opcion_caducidad: caducidad,
                id_producto: id_producto
            }).done(function(respuesta_caducidad) {
                $("#existencia_producto_recibo").val(respuesta_caducidad);
            });

            //reinicia la medida
            $.post('../ajax/recibo_venta.php?action=tipo_medida_producto', {
                id_medida: id_medida
            }).done(function(res_id_medidas) {
                $("#medida_producto_recibo").html(res_id_medidas);
            });
            //reinicie el precio
            $("#precio_producto_recibo").val(precio_venta);
        });

        //para cuando se selecciona un precio
        $('#lista_precios_producto_recibo').change(function() {
            var precio_seleccionado = $("#lista_precios_producto_recibo").val();
            $("#precio_producto_recibo").val(precio_seleccionado);
        });
        document.getElementById('cantidad_producto_recibo').focus();
    });

    //agregar informacion adicional
    function agrega_info_adicional() {
        var adicional_concepto = $("#adicional_concepto").val();
        var adicional_detalle = $("#adicional_detalle").val();

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=agrega_info_adicional",
            data: "adicional_concepto=" + adicional_concepto + "&adicional_detalle=" + adicional_detalle,
            beforeSend: function(objeto) {
                $("#detalle_informacion_adicional").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_informacion_adicional").html(datos);
            }
        });
    }
    //para una fila de info adicional
    function eliminar_info_adicional(id) {
        if (confirm("Realmente desea eliminar?")) {
            $.ajax({
                type: "POST",
                url: "../ajax/recibo_venta.php?action=eliminar_info_adicional",
                data: "id=" + id,
                beforeSend: function(objeto) {
                    $("#detalle_informacion_adicional").html("Eliminando...");
                },
                success: function(datos) {
                    $("#detalle_informacion_adicional").html(datos);
                }
            });
        }
    }

    //agregar item al detalle de la recibo
    function agregar_item_recibo() {
        var id_serie = document.getElementById('serie_recibo').value;
        var id_producto_recibo = document.getElementById('id_producto_recibo').value;
        var tipo_producto_recibo = document.getElementById('tipo_producto_recibo').value;
        var cantidad_producto_recibo = document.getElementById('cantidad_producto_recibo').value;
        var medida_producto_recibo = document.getElementById('medida_producto_recibo').value;
        var lote_producto_recibo = document.getElementById('lote_producto_recibo').value;
        var caducidad_producto_recibo = document.getElementById('caducidad_producto_recibo').value;
        var precio_producto_recibo = document.getElementById('precio_producto_recibo').value;
        var bodega_producto_recibo = document.getElementById('bodega_producto_recibo').value;
        var existencia_producto_recibo = document.getElementById('existencia_producto_recibo').value;
        var inventario_producto_recibo = document.getElementById('inventario_producto_recibo').value;
        var muestra_bodega_producto_recibo = document.getElementById('muestra_bodega_producto_recibo').value;
        var muestra_lote_producto_recibo = document.getElementById('muestra_lote_producto_recibo').value;
        var muestra_vencimiento_producto_recibo = document.getElementById('muestra_vencimiento_producto_recibo').value;

        //para mostrar aviso de minimos en saldos del producto
        $.post('../ajax/facturas.php?action=aviso_minimo', {
            id_producto: id_producto_recibo,
            id_bodega: bodega_producto_recibo,
            saldo: existencia_producto_recibo
        }).done(function(respuesta) {
            $.notify(respuesta, 'info');
        });

        //Inicia validacion
        if (id_serie == "0") {
            alert('Seleccione serie');
            document.getElementById('serie_recibo').focus();
            return false;
        }

        if (id_producto_recibo == "") {
            alert('Seleccione un producto o servicio');
            document.getElementById('nombre_producto_recibo').focus();
            return false;
        }
        if (cantidad_producto_recibo == "") {
            alert('Ingrese cantidad');
            document.getElementById('cantidad_producto_recibo').focus();
            return false;
        }
        if (isNaN(cantidad_producto_recibo)) {
            alert('El dato ingresado en cantidad, no es un número');
            document.getElementById('cantidad_producto_recibo').focus();
            return false;
        }
        if (isNaN(precio_producto_recibo)) {
            alert('El dato ingresado en precio, no es un número');
            document.getElementById('precio_producto_recibo').focus();
            return false;
        }
        if (inventario_producto_recibo == 'SI' && tipo_producto_recibo == '01' && muestra_bodega_producto_recibo == 'SI' && bodega_producto_recibo == '') {
            alert('Seleccione una bodega');
            document.getElementById('bodega_producto_recibo').focus();
            return false;
        }

        if (inventario_producto_recibo == 'SI' && tipo_producto_recibo == '01' && muestra_lote_producto_recibo == 'SI' && lote_producto_recibo == '0') {
            alert('Seleccione un lote');
            document.getElementById('lote_producto_recibo').focus();
            return false;
        }

        if (inventario_producto_recibo == 'SI' && tipo_producto_recibo == '01' && muestra_vencimiento_producto_recibo == 'SI' && caducidad_producto_recibo == '0') {
            alert('Seleccione fecha de vencimiento');
            document.getElementById('caducidad_producto_recibo').focus();
            return false;
        }

        /*
        if (parseFloat(cantidad_producto_recibo) > parseFloat(existencia_producto_recibo) && inventario_producto_recibo == 'SI' && tipo_producto_recibo == '01') {
            alert('El saldo en inventarios es menor a la cantidad ingresada');
            document.getElementById('cantidad_producto_recibo').focus();
            return false;
        }
        */

        //Fin validacion
        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=agregar_item_recibo",
            data: "id_producto=" + id_producto_recibo + "&precio=" + precio_producto_recibo + "&cantidad=" + cantidad_producto_recibo + "&id_bodega=" + bodega_producto_recibo + "&id_medida=" + medida_producto_recibo + "&lote=" + lote_producto_recibo + "&caducidad=" + caducidad_producto_recibo + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                $("#nombre_producto_recibo").val("");
                $("#id_producto_recibo").val("");
                $("#precio_producto_recibo").val("");
                $("#precio_producto_recibo_coniva").val("");
                var select = document.getElementById("lista_precios_producto_recibo");
                var length = select.options.length;
                for (i = length - 1; i >= 0; i--) {
                    select.options[i] = null;
                }
                $("#tipo_producto_recibo").val("");
                $("#existencia_producto_recibo").val("0");
                $("#cantidad_producto_recibo").val("1");
                muestra_subtotales();
                document.getElementById('nombre_producto_recibo').focus();
            }
        });


    }

    //para agregar los subtotales al recibo
    function muestra_subtotales() {
        var id_serie = document.getElementById('serie_recibo').value;
        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=subtotales_recibo",
            data: "serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_subtotales_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_subtotales_recibo").html(datos);
                var total_recibo = document.getElementById('total_recibo').value;
                $("#suma_recibo").val(total_recibo);
                //muestra_formas_pagos(total_recibo);
            }
        });
    }

    //eliminar iten de la recibo
    function eliminar_item_recibo(id) {
        if (confirm("Realmente desea eliminar?")) {
            var id_serie = document.getElementById('serie_recibo').value;
            $.ajax({
                type: "POST",
                url: "../ajax/recibo_venta.php?action=eliminar_item_recibo",
                data: "id=" + id + "&serie_recibo=" + id_serie,
                beforeSend: function(objeto) {
                    $("#detalle_recibo").html("Cargando...");
                },
                success: function(datos) {
                    $("#detalle_recibo").html(datos);
                    muestra_subtotales();
                    document.getElementById('nombre_producto_recibo').focus();
                }
            });
        }
    }


    //calcular el precio de un item sin iva
    function precio_item_sin_iva(id) {
        var porcentaje_item = document.getElementById('porcentaje_item' + id).value;
        var precio_sin_iva = document.getElementById('precio_item_sin_iva' + id).value;
        var precio_sin_iva_inicial = document.getElementById('precio_sin_iva_inicial' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;

        if (isNaN(precio_sin_iva)) {
            alert('El precio ingresado, no es un número');
            $("#precio_item_sin_iva" + id).val(precio_sin_iva_inicial);
            document.getElementById('precio_item_sin_iva' + id).focus();
            return false;
        }

        if ((precio_sin_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            $("#precio_item_sin_iva" + id).val(precio_sin_iva_inicial);
            document.getElementById('precio_item_sin_iva' + id).focus();
            return false;
        }

        var precio_con_iva = (parseFloat(precio_sin_iva) + (parseFloat(precio_sin_iva) * parseFloat(porcentaje_item)));
        $("#precio_item_con_iva" + id).val(precio_con_iva.toFixed(2));

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=calculo_precio_item",
            data: "id=" + id + "&precio=" + precio_sin_iva + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //calcular el precio de un item con iva
    function precio_item_con_iva(id) {
        var porcentaje_item = document.getElementById('porcentaje_item' + id).value;
        var precio_con_iva = document.getElementById('precio_item_con_iva' + id).value;
        var precio_con_iva_inicial = document.getElementById('precio_con_iva_inicial' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;


        if (isNaN(precio_con_iva)) {
            alert('El precio ingresado, no es un número');
            $("#precio_item_con_iva" + id).val(precio_con_iva_inicial);
            document.getElementById('precio_item_con_iva' + id).focus();
            return false;
        }

        if ((precio_con_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            $("#precio_item_con_iva" + id).val(precio_con_iva_inicial);
            document.getElementById('precio_item_con_iva' + id).focus();
            return false;
        }

        var precio_sin_iva = (parseFloat(precio_con_iva) / (parseFloat(1) + parseFloat(porcentaje_item)));
        $("#precio_item_sin_iva" + id).val(precio_sin_iva.toFixed(2));

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=calculo_precio_item",
            data: "id=" + id + "&precio=" + precio_sin_iva + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }


    //agregar info adicional en cada item
    function info_adicional_item(id) {
        var info_adicional_item = document.getElementById('info_adicional_item' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=info_adicional_item",
            data: "id=" + id + "&info_adicional=" + info_adicional_item + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });

    }

    //calcular el precio de un item con iva
    function actualiza_cantidad(id) {
        var cantidad_producto = document.getElementById('cantidad_producto' + id).value;
        var cantidad_inicial = document.getElementById('cantidad_inicial' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;
        var id_producto = document.getElementById('id_producto' + id).value;
        var inventario = document.getElementById('inventario_producto_recibo').value;
        var tipo_produccion = document.getElementById('tipo_produccion' + id).value;
        var id_bodega = document.getElementById('bodega_producto_recibo').value;
        var muestra_lote = document.getElementById('muestra_lote_producto_recibo').value;
        var lote = document.getElementById('lote' + id).value;

        if (inventario == 'SI' && tipo_produccion == '01') {
            document.getElementById("label_existencia_producto_recibo").style.display = "";
            if (muestra_lote == 'SI') {
                $.post('../ajax/saldo_producto_inventario.php', {
                    opcion_lote: lote,
                    id_producto: id_producto,
                    bodega: id_bodega
                }).done(function(respuesta) {
                    var saldo_producto = respuesta;
                    $("#existencia_producto_recibo").val(parseFloat(saldo_producto) + parseFloat(cantidad_inicial));
                });
            } else {
                $.post('../ajax/saldo_producto_inventario.php', {
                    id_bodega: id_bodega,
                    id_producto: id_producto
                }).done(function(respuesta) {
                    var saldo_producto = respuesta;
                    $("#existencia_producto_recibo").val(parseFloat(saldo_producto) + parseFloat(cantidad_inicial));
                });
            }
            /*
            var existencia = document.getElementById('existencia_producto_recibo').value;

            if (parseFloat(cantidad_producto) > parseFloat(existencia)) {
                    alert('El saldo es insuficiente.');
                    $("#cantidad_producto" + id).val(cantidad_inicial);
                    document.getElementById('cantidad_producto' + id).focus();
                    return false;
                }
                */
        }


        if (isNaN(cantidad_producto)) {
            alert('La cantidad ingresada, no es un número');
            $("#cantidad_producto" + id).val(cantidad_inicial);
            document.getElementById('cantidad_producto' + id).focus();
            return false;
        }

        if ((cantidad_producto < 0)) {
            alert('La cantidad, debe ser mayor a cero');
            $("#cantidad_producto" + id).val(cantidad_inicial);
            document.getElementById('cantidad_producto' + id).focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=actualiza_cantidad",
            data: "id=" + id + "&cantidad_producto=" + cantidad_producto + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }


    //descuento en item individual
    function descuento_item(id) {
        var descuento_item = document.getElementById('descuento_item' + id).value;
        var descuento_inicial = document.getElementById('descuento_inicial' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;

        if (isNaN(descuento_item)) {
            alert('El valor ingresado, no es un número');
            $("#descuento_item" + id).val(descuento_inicial);
            document.getElementById('descuento_item' + id).focus();
            return false;
        }

        if ((descuento_item < 0)) {
            alert('El valor ingresado debe ser mayor a cero');
            $("#descuento_item" + id).val(descuento_inicial);
            document.getElementById('descuento_item' + id).focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=actualiza_descuento_item",
            data: "id=" + id + "&descuento_item=" + descuento_item + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //para pasar el id de descuento al modal de aplicar descuento
    function opciones_descuentos(id) {
        var subtotal_item = document.getElementById('subtotal_item' + id).value;
        var descuento_inicial = document.getElementById('descuento_inicial' + id).value;
        var tarifa_item = document.getElementById('tarifa_item' + id).value;
        var id_serie = document.getElementById('serie_recibo').value;
        $("#id_tmp_descuento").val(id);
        $("#subtotal_inicial").val(subtotal_item);
        $("#valor_descuento").val(descuento_inicial);
        $("#tarifa").val(tarifa_item);
        $("#serie_factura_descuento").val(id_serie);
        $("#porcentaje_descuento").val(Number.parseFloat(descuento_inicial / subtotal_item * 100).toFixed(2));
    }

    //descuento en un solo item individual desde el modal del descuento
    function aplicar_descuento_item() {
        var descuento_item = document.getElementById('valor_descuento').value;
        var id_serie = document.getElementById('serie_factura_descuento').value;
        var id = document.getElementById('id_tmp_descuento').value;

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=actualiza_descuento_item",
            data: "id=" + id + "&descuento_item=" + descuento_item + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //aplicar descuento a todos los items
    function aplicar_descuento_todos() {
        var porcentaje_descuento = document.getElementById('porcentaje_descuento').value;
        var id_serie = document.getElementById('serie_factura_descuento').value;
        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=aplicar_descuento_todos",
            data: "porcentaje_descuento=" + porcentaje_descuento + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //agregar servicio a la recibo
    function agrega_propina() {
        var id_serie = document.getElementById('serie_recibo').value;
        var propina = $("#propina").val();

        if (isNaN(propina)) {
            alert('El valor ingresado, no es un número');
            $("#propina").val('0');
            document.getElementById('propina').focus();
            return false;
        }

        if ((propina < 0)) {
            alert('El valor ingresado debe ser mayor a cero');
            $("#propina").val(propina);
            document.getElementById('propina').focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=agrega_propina",
            data: "propina=" + propina + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_subtotales_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_subtotales_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //agregar tasa a la recibo
    function agrega_tasa() {
        var id_serie = document.getElementById('serie_recibo').value;
        var tasa = $("#tasa").val();

        if (isNaN(tasa)) {
            alert('El valor ingresado, no es un número');
            $("#tasa").val('0');
            document.getElementById('tasa').focus();
            return false;
        }

        if ((tasa < 0)) {
            alert('El valor ingresado debe ser mayor a cero');
            $("#tasa").val(tasa);
            document.getElementById('tasa').focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=agrega_tasa",
            data: "tasa=" + tasa + "&serie_recibo=" + id_serie,
            beforeSend: function(objeto) {
                $("#detalle_subtotales_recibo").html("Cargando...");
            },
            success: function(datos) {
                $("#detalle_subtotales_recibo").html(datos);
                muestra_subtotales();
            }
        });
    }

    //guardar o editar la recibo
    function guarda_recibo() {
        $('#btnActionFormRecibo').attr("disabled", true);
        var id_recibo = $("#id_recibo").val();
        var id_cliente_recibo = $("#id_cliente_recibo").val();
        var fecha_recibo = $("#fecha_recibo").val();
        var serie_recibo = $("#serie_recibo").val();
        var secuencial_recibo = $("#secuencial_recibo").val();
        var suma_recibo = $("#total_recibo").val();
        var propina = $("#propina").val();
        var tasa = $("#tasa").val();
        $.ajax({
            type: "POST",
            url: "../ajax/recibo_venta.php?action=guardar_recibo",
            data: "id_recibo=" + id_recibo + "&id_cliente_recibo=" + id_cliente_recibo +
                "&fecha_recibo=" + fecha_recibo + "&serie_recibo=" + serie_recibo +
                "&secuencial_recibo=" + secuencial_recibo + "&suma_recibo=" +
                suma_recibo + "&propina=" + propina + "&tasa=" + tasa,
            beforeSend: function(objeto) {
                $("#resultados_ajax_guardar").html("Guardando...");
            },
            success: function(datos) {
                //$("#resultados_ajax_guardar").html(datos);
                $("#resultados_ajax_guardar").html(datos);
                $('#btnActionFormRecibo').attr("disabled", false);

            }
        });
        event.preventDefault();
    }


    //calcular el precio de un producto sin iva antes de agregar el producto al cuerpo
    function precio_sin_iva() {
        var porcentaje_item = document.getElementById('porcentaje_iva').value;
        var precio_sin_iva = document.getElementById('precio_producto_recibo').value;

        if (isNaN(precio_sin_iva)) {
            alert('El precio ingresado, no es un número');
            document.getElementById('precio_producto_recibo').focus();
            return false;
        }

        if ((precio_sin_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            document.getElementById('precio_producto_recibo').focus();
            return false;
        }

        var precio_con_iva = (parseFloat(precio_sin_iva) + (parseFloat(precio_sin_iva) * parseFloat(porcentaje_item)));
        $("#precio_producto_recibo_coniva").val(precio_con_iva.toFixed(4));

    }

    //calcular el precio de un producto antes de agregar al cuerpo
    function precio_con_iva() {
        var porcentaje_item = document.getElementById('porcentaje_iva').value;
        var precio_con_iva = document.getElementById('precio_producto_recibo_coniva').value;

        if (isNaN(precio_con_iva)) {
            alert('El precio ingresado, no es un número');
            document.getElementById('precio_producto_recibo_coniva').focus();
            return false;
        }

        if ((precio_con_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            document.getElementById('precio_producto_recibo_coniva').focus();
            return false;
        }

        var precio_sin_iva = (parseFloat(precio_con_iva) / (parseFloat(1) + parseFloat(porcentaje_item)));
        $("#precio_producto_recibo").val(precio_sin_iva.toFixed(4));

    }
</script>