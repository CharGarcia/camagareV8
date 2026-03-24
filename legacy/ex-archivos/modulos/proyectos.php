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
        <title>Proyectos</title>
        <?php include("../paginas/menu_de_empresas.php"); ?>
    </head>

    <body>

        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="btn-group pull-right">
                        <?php
                        $con = conenta_login();
                        if (getPermisos($con, $id_usuario, $ruc_empresa, 'proyectos')['w'] == 1) {
                        ?>
                            <button type='submit' class="btn btn-info" data-toggle="modal" data-target="#proyectos" onclick="nuevo_proyecto();"><span class="glyphicon glyphicon-plus"></span> Nuevo Proyecto</button>
                        <?php
                        }
                        ?>
                    </div>
                    <h4><i class='glyphicon glyphicon-search'></i> Proyectos</h4>
                </div>
                <div class="panel-body">
                    <?php
                    include("../modal/proyectos.php");
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

        function nuevo_proyecto() {
            document.querySelector("#titleModalProyecto").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nuevo Proyecto";
            document.querySelector("#formProyecto").reset();
            document.querySelector("#id_proyecto").value = "";
            document.querySelector("#btnActionFormProyecto").classList.replace("btn-info", "btn-primary");
            document.querySelector("#btnTextProyecto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
            document.querySelector('#btnActionFormProyecto').title = "Guardar Proyecto";
        }

        function load(page) {
            var q = $("#q").val();
            var ordenado = $("#ordenado").val();
            var por = $("#por").val();
            $("#loader").fadeIn('slow');
            $.ajax({
                url: '../ajax/proyectos.php?action=buscar_proyectos&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
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

        function editar_proyecto(id) {
            document.querySelector('#titleModalProyecto').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar Proyecto";
            document.querySelector("#formProyecto").reset();
            document.querySelector("#id_proyecto").value = id;
            document.querySelector('#btnActionFormProyecto').classList.replace("btn-primary", "btn-info");
            document.querySelector("#btnTextProyecto").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";
            document.querySelector('#btnActionFormProyecto').title = "Actualizar Proyecto";

            var nombre_proyecto = $("#nombre_proyecto" + id).val();
            var status = $("#status_proyecto" + id).val();

            $("#nombre_proyecto").val(nombre_proyecto);
            $("#status").val(status);
        }

        function eliminar_proyecto(id) {
            var q = $("#q").val();
            if (confirm("Realmente desea eliminar el proyecto?")) {
                $.ajax({
                    type: "POST",
                    url: "../ajax/proyectos.php?action=eliminar_proyecto",
                    data: "id_proyecto=" + id,
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