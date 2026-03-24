<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $id_empresa = $_SESSION['id_empresa'];
    $ruc_empresa = $_SESSION['ruc_empresa'];
    $con = conenta_login();
    require_once dirname(__DIR__) . '/paginas/verificar_permiso_modulo.php';
?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <title>Localización</title>
        <?php include("../paginas/menu_de_empresas.php"); ?>
    </head>

    <body>

        <div class="container">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="btn-group pull-right">
                        <?php
                        if (getPermisos($con, $id_usuario, $ruc_empresa, 'localizacion')['w'] == 1) {
                        ?>
                            <button type='submit' class="btn btn-info" data-toggle="modal" data-target="#localizacion" onclick="nueva_key_localizacion();"><span class="glyphicon glyphicon-plus"></span> Nueva key</button>
                        <?php
                        }
                        ?>
                    </div>
                    <h4><i class='glyphicon glyphicon-search'></i> Key Google maps</h4>
                </div>
                <div class="panel-body">
                    <?php
                    include("../modal/localizacion.php");
                    ?>
                    <form class="form-horizontal" method="POST">
                        <div class="form-group row">
                            <div class="col-md-5">
                                <input type="hidden" id="ordenado" value="nombre">
                                <input type="hidden" id="por" value="desc">
                                <div class="input-group">
                                    <span class="input-group-addon"><b>Buscar:</b></span>
                                    <input type="text" class="form-control input-sm" id="q" placeholder="Nombre" onkeyup='load(1);'>
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
                                    </span>
                                </div>
                            </div>
                            <span id="loader"></span>
                        </div>
                    </form>
                    <div id="resultados"></div><!-- Carga los datos ajax -->
                    <div class="outer_div"></div><!-- Carga los datos ajax -->
                </div>
            </div>

        </div>
    <?php

} else {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
    exit;
}
    ?>
    <script src="../js/notify.js"></script>
    </body>

    </html>
    <script>
        $(document).ready(function() {
            window.addEventListener("keypress", function(event) {
                if (event.keyCode == 13) {
                    event.preventDefault();
                }
            }, false);
            load(1);
        });

        function load(page) {
            var q = $("#q").val();
            var ordenado = $("#ordenado").val();
            var por = $("#por").val();
            $("#loader").fadeIn('slow');
            $.ajax({
                url: '../ajax/localizacion.php?action=buscar_key&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
                beforeSend: function(objeto) {
                    $('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
                },
                success: function(data) {
                    $(".outer_div").html(data).fadeIn('slow');
                    $('#loader').html('');

                }
            })
        }

        function ordenar(ordenado) {
            $("#ordenado").val(ordenado);
            var por = $("#por").val();
            var q = $("#q").val();
            var ordenado = $("#ordenado").val();
            $("#loader").fadeIn('slow');
            var value_por = document.getElementById('por').value;
            if (value_por == "asc") {
                $("#por").val("desc");
            }
            if (value_por == "desc") {
                $("#por").val("asc");
            }
            load(1);
        }

        function nueva_key_localizacion() {
            document.querySelector("#titleModalKeyLocalizacion").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva key";
            document.querySelector("#formLocalizacion").reset();
            document.querySelector("#id_key").value = "";
            document.querySelector("#btnActionFormKeyLocalizacion").classList.replace("btn-info", "btn-primary");
            document.querySelector("#btnTextKeyLocalizacion").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
            document.querySelector('#btnActionFormKeyLocalizacion').title = "Guardar";
        }

        function editar_key(id) {
            document.querySelector('#titleModalKeyLocalizacion').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar key";
            document.querySelector("#formLocalizacion").reset();
            document.querySelector("#id_key").value = id;
            document.querySelector('#btnActionFormKeyLocalizacion').classList.replace("btn-primary", "btn-info");
            document.querySelector("#btnTextKeyLocalizacion").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
            document.querySelector('#btnActionFormKeyLocalizacion').title = "Actualizar";

            var key = $("#key_google" + id).val();
            var status = $("#status" + id).val();

            $("#key_google").val(key);
            $("#status").val(status);
        }

        function eliminar_key(id) {
            var q = $("#q").val();
            if (confirm("Realmente desea eliminar la llave?")) {
                $.ajax({
                    type: "POST",
                    url: "../ajax/localizacion.php?action=eliminar_key",
                    data: "id_key=" + id,
                    "q": q,
                    beforeSend: function(objeto) {
                        $("#resultados").html("Mensaje: Cargando...");
                    },
                    success: function(datos) {
                        $("#resultados").html(datos);
                        load(1);
                    }
                });
            }
        }
    </script>