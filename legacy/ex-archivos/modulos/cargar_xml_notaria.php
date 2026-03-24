<?php
require_once("../helpers/helpers.php");
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $id_empresa = $_SESSION['id_empresa'];
    $ruc_empresa = $_SESSION['ruc_empresa'];
?>
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="utf-8">
        <title>Cargar xml</title>
        <?php
        include("../paginas/menu_de_empresas.php");
        $con = conenta_login();
        $con_des = conecta_descargas();
        $ruc_empresa_descargas = substr($ruc_empresa, 0, 10);

        ?>
    </head>

    <body>
        <div class="container-fluid">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h4><i class='glyphicon glyphicon-search'></i> Cargar Documentos xml</h4>
                </div>
                <div class="panel-body">
                    <div class="panel-group" id="accordionDescargaElectronicos">
                        <!-- carga por carpeta xml -->
                        <div class="panel panel-info">
                            <a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordionDescargaElectronicos" href="#descargaXml"><span class="caret"></span> Cargar carpeta con documentos xml </a>
                            <div id="descargaXml" class="panel-collapse">
                                <div class="modal-body">
                                    <div class="form-group row">
                                        <form method="post" action="" id="cargar_xml" name="cargar_xml" enctype="multipart/form-data">
                                            <div class="col-sm-4">
                                                <div class="input-group">
                                                    <input class="filestyle input-sm" data-buttonText=" Seleccionar carpeta" type="file" id="carpetaXml" name="carpetaXml[]" webkitdirectory directory multiple required />
                                                </div>
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="input-group">
                                                    <button type="submit" class="btn btn-info input-sm" name="subir_xml"><span class="glyphicon glyphicon-upload"></span> Cargar</button>
                                                </div>
                                            </div>
                                        </form>
                                        <div class="col-sm-3">
                                            <span id="loader_xml"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class='outer_div_xml'></div><!-- Carga los datos ajax -->
                    </div>
                </div>
            </div>
        <?php
    } else {
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
        exit;
    }
        ?>
        <script type="text/javascript" src="../js/style_bootstrap.js"> </script>
        <link rel="stylesheet" href="../css/jquery-ui.css">
        <script src="../js/jquery-ui.js"></script>
        <script src="../js/notify.js"></script>
    </body>

    </html>
    <script>
        $(function() {
            //para el xml
            $("#cargar_xml").on("submit", function(e) {
                e.preventDefault();
                var formData = new FormData(document.getElementById("cargar_xml"));
                formData.append("dato", "valor");
                $.ajax({
                        url: "../ajax/subir_documentos_electronicos.php?action=archivo_xml_notaria",
                        type: "post",
                        dataType: "html",
                        data: formData,
                        beforeSend: function(objeto) {
                            $('#loader_xml').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando...</div></div>');
                        },
                        cache: false,
                        contentType: false,
                        processData: false
                    })
                    .done(function(res) {
                        $(".outer_div_xml").html(res);
                        $("#loader_xml").html('');
                    });
            });

        });
    </script>