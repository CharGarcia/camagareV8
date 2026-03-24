<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">

<head>
    <title>Retención ventas</title>
</head>
<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $id_empresa = $_SESSION['id_empresa'];
    $ruc_empresa = $_SESSION['ruc_empresa'];

    include("../paginas/menu_de_empresas.php");
    ini_set('date.timezone', 'America/Guayaquil');
    //para borrar los datos de la retencion que este en temporal

    $con = conenta_login();
    if (isset($_SESSION['id_usuario'])) {
        $id_tmp = $_SESSION['id_usuario'];
        $delete_retencion_tmp = mysqli_query($con, "DELETE FROM retencion_tmp WHERE id_usuario = '$id_tmp';");
        $delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = $id_tmp;");
    }
?>

    <body>
        <?php
        include("../modal/clientes.php");
        //include("../modal/nuevo_cliente_factura.php");				
        ?>

        <div class="container">
            <div class="panel panel-info">

                <div class="panel-heading">
                    <div class="btn-group pull-right">
                        <form class="form-group" id="guardar_retencion_venta" name="guardar_retencion_venta" method="POST">
                            <button id="guardar_datos_retencion_venta" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
                    </div>
                    <h4><i class='glyphicon glyphicon-edit'></i> Nueva retención en ventas</h4>
                </div>

                <div class="panel-body">
                    <div id="resultados_ajax"></div>
                    <div id="resultados_guardar_retencion_electronica"></div>
                    <div id="observacion_retener_iva"></div>
                    <div class="well well-sm">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tr class="info">
                                    <th>Fecha retención</th>
                                    <th>No. Retención</th>
                                    <th>Cliente</th>
                                </tr>
                                <td class='col-xs-2'>
                                    <input type="text" class="form-control input-sm" name="fecha_retencion_venta" id="fecha_retencion_venta" value="<?php echo date("d-m-Y"); ?>">
                                </td>
                                <td class="col-md-2"><input type="text" class="form-control input-sm" id="numero_retencion_venta" name="numero_retencion_venta"></td>

                                <td class="col-xs-6">
                                    <div class="input-group">
                                        <input class="form-control input-sm" id="nombre_cliente_nueva_ret_vta" name="nombre_cliente_nueva_ret_vta" placeholder="Cliente" onkeyup='buscar_clientes();' autocomplete="off">
                                        <span class="input-group-btn btn-md"><button class="btn btn-info btn-md" data-toggle="modal" onclick="carga_modal();" data-target="#nuevoCliente" type="button" title="Agregar cliente"><span class="glyphicon glyphicon-plus"></span></button></span>
                                    </div>
                                </td>

                                <input type="hidden" id="id_cliente_ret" name="id_cliente_ret">
                                <input type="hidden" id="id_concepto_ret" name="id_concepto_ret">
                                <input type="hidden" id="total_retencion_e" name="total_retencion_e">
                            </table>

                            <table class="table table-bordered">
                                <tr class="info">
                                    <th>Tipo comprobante</th>
                                    <th>No. comprobante</th>
                                    <th>Concepto</th>
                                    <th>Porcentaje</th>
                                    <th>Base imponible</th>
                                    <th>Agregar</th>
                                </tr>
                                <td class="col-xs-3"><select class="form-control" name="tipo_comprobante" id="tipo_comprobante">
                                        <option value="0">Seleccione comprobante</option>
                                        <option value="01" selected>Factura</option>
                                        <?php
                                        $conexion = conenta_login();
                                        $sql = "SELECT * FROM comprobantes_autorizados order by comprobante asc ";
                                        $res = mysqli_query($conexion, $sql);
                                        while ($o = mysqli_fetch_assoc($res)) {
                                        ?>
                                            <option value="<?php echo $o['codigo_comprobante'] ?> "><?php echo $o['comprobante'] ?> </option>
                                        <?php
                                        }
                                        ?>
                                    </select></td>
                                <td class="col-xs-2">
                                    <input type="text" class="form-control input-sm" pattern="[0-9]{3}-[0-9]{3}-[0-9]{9}" maxlength="17" id="numero_comprobante" name="numero_comprobante" placeholder="001-001-000000009" title="formato: 001-001-000000009" required>
                                </td>
                                <td class='col-xs-3'>
                                    <input type="text" class="form-control input-sm" id="concepto_retencion" name="concepto_retencion" placeholder="Concepto" title="Busque un concepto de retención" onkeyup='buscar_conceptos_retenciones();' autocomplete="off">
                                </td>
                                <td class='col-xs-1'>
                                    <input type="text" class="form-control input-sm" id="porcentaje_retencion" name="porcentaje_retencion" placeholder="%">
                                </td>
                                <td class='col-xs-2'>
                                    <input type="text" class="form-control input-sm" id="base_retencion" name="base_retencion" placeholder="Base">
                                </td>
                                <td class='col-xs-1'>
                                    <button type="button" class="btn btn-info btn-md" title="Agregar retenciones" onclick="agregar_concepto_retencion()"><span class="glyphicon glyphicon-plus"></span></button>
                                </td>
                            </table>
                        </div>
                    </div>
                    </form>
                    <div id="resultados"></div><!-- Carga los datos ajax del detalle de la factura -->
                    <span id="loader"></span>
                </div>
            </div>
        </div>


    <?php
} else {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
    ?>
    </body>

    <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="../js/notify.js"></script>
    <script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

</html>


<script>
    jQuery(function($) {
        $("#fecha_retencion_venta").mask("99-99-9999");
        $("#numero_retencion_venta").mask("999-999-9?99999999");
        $("#numero_comprobante").mask("999-999-9?99999999");
    });

    $(document).ready(function() {
        document.getElementById('numero_retencion_venta').focus();
    });

    function carga_modal() {
        document.querySelector("#titleModalCliente").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Cliente";
        document.querySelector("#guardar_cliente").reset();
        document.querySelector("#id_cliente").value = "";
        document.querySelector("#btnActionFormCliente").classList.replace("btn-info", "btn-primary");
        document.querySelector("#btnTextCliente").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
        document.querySelector('#btnActionFormCliente').title = "Guardar cliente";
    }

    function buscar_clientes() {
        $("#nombre_cliente_nueva_ret_vta").autocomplete({
            source: '../ajax/clientes_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#id_cliente_ret').val(ui.item.id);
                $('#nombre_cliente_nueva_ret_vta').val(ui.item.nombre);
                document.getElementById('numero_comprobante').focus();
            }
        });
        $("#nombre_cliente_nueva_ret_vta").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_cliente_ret").val("");
                $("#nombre_cliente_nueva_ret_vta").val("");
            }
            if (event.keyCode == $.ui.keyCode.DELETE) {
                $("#nombre_cliente_nueva_ret_vta").val("");
                $("#id_cliente_ret").val("");
            }
        });

    }


    function buscar_conceptos_retenciones() {
        $("#concepto_retencion").autocomplete({
            source: '../ajax/concepto_retencion_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#id_concepto_ret').val(ui.item.id_ret);
                $('#concepto_retencion').val(ui.item.concepto_ret);
                $('#porcentaje_retencion').val(ui.item.porcentaje_ret);
                document.getElementById('base_retencion').focus();
                //para ver si debe retener iva
            }
        });

        $("#concepto_retencion").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_concepto_ret").val("");
                $("#concepto_retencion").val("");
                $("#porcentaje_retencion").val("");
            }
            if (event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_concepto_ret").val("");
                $("#concepto_retencion").val("");
                $("#porcentaje_retencion").val("");
            }
        });
    }


    $(function() {
        $("#fecha_retencion_venta").datepicker({
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


    function eliminar_concepto_retencion(id) {
        $.ajax({
            type: "GET",
            url: "../ajax/agregar_retenciones.php",
            data: "id=" + id,
            beforeSend: function(objeto) {
                $("#resultados").html("Mensaje: Cargando...");
            },
            success: function(datos) {
                $("#resultados").html(datos);
            }
        });

    };

    function agregar_concepto_retencion() { //viene del boton de agregar en el modal de buscar_concepto_retencion.php
        var id_cliente = $("#id_cliente_ret").val();
        var id_ret = $("#id_concepto_ret").val();
        var numero_retencion = $("#numero_retencion_venta").val();
        var numero_comprobante = $("#numero_comprobante").val();
        var porcentaje_ret = $("#porcentaje_retencion").val();
        var base_imponible_ret = $("#base_retencion").val();
        var fecha_ret = $("#fecha_retencion_venta").val();
        var codigo_cliente = $("#id_cliente_ret").val();
        //Inicia validacion
        if (numero_retencion == "") {
            alert('Ingrese número de retención');
            document.getElementById('numero_retencion_venta').focus();
            return false;
        }
        if (id_cliente == "") {
            alert('Seleccione un cliente');
            document.getElementById('nombre_cliente_nueva_ret_vta').focus();
            return false;
        }
        if (numero_comprobante == "") {
            alert('Ingrese número de comprobante');
            document.getElementById('numero_comprobante').focus();
            return false;
        }
        if (id_ret == "") {
            alert('Seleccione un concepto de retención');
            document.getElementById('concepto_retencion').focus();
            return false;
        }
        if (isNaN(porcentaje_ret)) {
            alert('El dato ingresado en porcentaje, no es un número');
            document.getElementById('porcentaje_retencion').focus();
            return false;
        }
        if (isNaN(base_imponible_ret)) {
            alert('El dato ingresado en base imponible, no es un número');
            document.getElementById('base_retencion').focus();
            return false;
        }
        if ((base_imponible_ret) == 0) {
            alert('Ingrese valor en base imponible');
            document.getElementById('base_retencion').focus();
            return false;
        }
        //Fin validacion
        $.ajax({
            type: "POST",
            url: "../ajax/agregar_retenciones.php",
            data: "id_ret=" + id_ret + "&porcentaje_ret=" + porcentaje_ret + "&base_imponible_ret=" + base_imponible_ret + "&fecha_ret=" + fecha_ret + "&id_cliente_ret=" + id_cliente,
            beforeSend: function(objeto) {
                $("#resultados").html("Mensaje: Cargando...");
            },
            success: function(datos) {
                $("#resultados").html(datos);
                $("#concepto_retencion").val("");
                $("#porcentaje_retencion").val("");
                $("#base_retencion").val("");
                document.getElementById('concepto_retencion').focus();
            }
        });

    };



    //para guardar la retencion en ventas
    $("#guardar_retencion_venta").submit(function(event) {
        $('#guardar_datos_retencion_venta').attr("disabled", true);
        //para pasar el total de la retencion de un text a otro text
        var total_retencion = $("#suma_retencion").val();
        $("#total_retencion_e").val(total_retencion);
        //de aqui para abajo para guardar la factura
        var parametros = $(this).serialize();
        $.ajax({
            type: "POST",
            url: '../ajax/guardar_retencion_ventas.php',
            data: parametros,
            beforeSend: function(objeto) {
                $("#resultados_guardar_retencion_electronica").html("Mensaje: Cargando...");
            },
            success: function(datos) {
                $("#resultados_guardar_retencion_electronica").html(datos);
                $('#guardar_datos_retencion_venta').attr("disabled", false);
            }
        });
        event.preventDefault();
    });

    //para cuando cambia en el secuencial de retencion y factura y se aplique los ceros a la izquierda
    $(function() {
        //para el numero de retencion
        $('#numero_retencion_venta').change(function() {
            var numero_comprobante = $("#numero_retencion_venta").val();
            var serie = numero_comprobante.substr(0, 8);
            var secuencial = numero_comprobante.substr(8, 9);
            while (secuencial.length < 9) {
                var secuencial = '0' + secuencial;
                $("#numero_retencion_venta").val(serie + secuencial);
            }
            document.getElementById('nombre_cliente_nueva_ret_vta').focus();
        });

        //para el numero de factura que aplica la retencion
        $('#numero_comprobante').change(function() {
            var numero_comprobante = $("#numero_comprobante").val();
            var serie = numero_comprobante.substr(0, 8);
            var secuencial = numero_comprobante.substr(8, 9);
            while (secuencial.length < 9) {
                var secuencial = '0' + secuencial;
                $("#numero_comprobante").val(serie + secuencial);
            }
            document.getElementById('concepto_retencion').focus();
        });
    });

    //para verificar mes y año que sean del periodo actual y muestre una advertencia
    $(function() {
        $('#fecha_retencion_venta').change(function() {
            var fecha_input = $("#fecha_retencion_venta").val();
            let date = new Date();
            if (fecha_input.length = 10) {
                let fecha_hoy = String(date.getDate()).padStart(2, '0') + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + date.getFullYear();
                let mes_entra = fecha_input.substr(3, 2);
                let mes_hoy = fecha_hoy.substr(3, 2);

                let anio_entra = fecha_input.substr(7, 4);
                let anio_hoy = fecha_hoy.substr(7, 4);
                if (mes_entra != mes_hoy) {
                    $("#fecha_retencion_venta").notify("El mes ingresado no es igual al mes actual", {
                        position: "top center"
                    });
                }

                if (anio_entra != anio_hoy) {
                    $("#fecha_retencion_venta").notify("El año ingresado no es igual al año actual", {
                        position: "top center"
                    });
                }
            }
            //document.getElementById('nombre_beneficiario').focus();
        });
    });
</script>