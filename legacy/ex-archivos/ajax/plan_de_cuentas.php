<?php
include("../conexiones/conectalogin.php");
include("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$codigo_unico = codigo_aleatorio(20);
ini_set('date.timezone', 'America/Guayaquil');
$fecha_registro = date('Y-m-d H:i:s');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'cuentas_supercias') {
    $busqueda = $_GET['term'];
    $text_buscar = explode(' ', $busqueda);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    $aColumns = array('codigo', 'cuenta'); //Columnas de busqueda
    $sTable = 'plan_cuentas_supercias';
    $sWhere = "WHERE status=1";
    if ($busqueda != "") {
        $sWhere = " WHERE status=1 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' and status=1 OR ";
        }
        $sWhere = substr_replace($sWhere, "AND status=1 ", -3);
    }
    $sWhere .= " order by cuenta asc";

    //pagination variables
    $page = 1;
    $per_page = 100; //how much records you want to show
    //$adjacents  = 10; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT * FROM $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
        $arreglo_busqueda = array();
        if (mysqli_num_rows($query) == 0) {
            array_push($arreglo_busqueda, "No hay datos");
        } else {
            while ($palabras = mysqli_fetch_array($query)) {
                $row_array['value'] = $palabras['codigo'] . " - " . $palabras['cuenta'];
                $row_array['id'] = $palabras['id'];
                $row_array['cuenta'] = $palabras['cuenta'];
                $row_array['codigo'] = $palabras['codigo'];
                array_push($arreglo_busqueda, $row_array);
            }
        }
        echo json_encode($arreglo_busqueda);
        mysqli_close($con);
    }
}


if ($action == 'datos_editar_cuenta') {
    $id_cuenta = $_GET['id_cuenta'];
    $sql = mysqli_query($con, "SELECT plan.nombre_cuenta as nombre_cuenta, cias.cuenta as nombre_cuenta_supercias,
    plan.codigo_sri as codigo_sri, plan.codigo_supercias as codigo_supercias, plan.nivel_cuenta as nivel_cuenta,
    plan.codigo_cuenta as codigo_cuenta, plan.status as status_cuenta, plan.id_proyecto as id_proyecto 
    FROM plan_cuentas as plan 
    LEFT JOIN plan_cuentas_supercias as cias 
    ON cias.codigo=plan.codigo_supercias
    WHERE plan.id_cuenta='" . $id_cuenta . "'");
    $cuenta = mysqli_fetch_array($sql);

    $data = array(
        'nombre_cuenta' => $cuenta['nombre_cuenta'],
        'nombre_cuenta_sri' => '',
        'nombre_cuenta_supercias' => $cuenta['nombre_cuenta_supercias'],
        'codigo_sri' => $cuenta['codigo_sri'],
        'codigo_supercias' => $cuenta['codigo_supercias'],
        'nivel_cuenta' => $cuenta['nivel_cuenta'],
        'codigo_cuenta' => $cuenta['codigo_cuenta'],
        'status' => $cuenta['status_cuenta'],
        'id_proyecto' => $cuenta['id_proyecto']
    );
    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }
    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE);
    die();
}


if ($action == 'siguiente_codigo_cuenta') {
    $id_cuenta = $_POST['id_cuenta'];
    $siguiente_codigo = siguiente_codigo($con, $id_cuenta, $ruc_empresa);
    echo $siguiente_codigo;
}


if ($action == 'eliminar_cargas_plan_de_cuentas') {
    $codigo = $_POST['codigo'];
    $delete = mysqli_query($con, "DELETE FROM plan_cuentas WHERE codigo_unico = '" . $codigo . "' and id_cuenta NOT IN(SELECT id_cuenta FROM detalle_diario_contable)");
    echo (mysqli_error($con));
    if ($delete) {
        echo "<script>$.notify('Se eliminó correctamente la carga.','success');</script>";
    } else {
        echo "<script>$.notify('Intente otra vez.','error');</script>";
    }
}

if ($action == 'cargas_plan_de_cuentas') {
    $query = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa ='" .  $ruc_empresa . " '  group by codigo_unico LIMIT 5");
    $numrows = mysqli_num_rows($query);
    //loop through fetched data
    if ($numrows > 0) {
?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table table-hover">
                    <tr class="info">
                        <th>Fecha carga</th>
                        <th class='text-right'>Opciones</th>

                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $codigo_unico = $row['codigo_unico'];
                        $fecha_registro = $row['fecha_registro'];
                    ?>
                        <input type="hidden" value="<?php echo $codigo_unico; ?>" id="codigo_unico<?php echo $codigo_unico; ?>">
                        <tr>
                            <td><?php echo $fecha_registro; ?></td>
                            <td><span class="pull-right">
                                    <a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_carga('<?php echo $codigo_unico; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
                                </span></td>

                        </tr>
                    <?php
                    }
                    ?>
                </table>
            </div>
        </div>
        <?php
    }
}


if ($action == 'archivo_excel_plan_de_cuentas') {
    require "../excel/lib/PHPExcel/PHPExcel/IOFactory.php";
    $nombre_archivo = $_FILES['archivo']['name'];
    $archivo_guardado = $_FILES['archivo']['tmp_name'];
    $directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
    $dir = opendir($directorio); //Abrimos el directorio de destino
    $target_path = $directorio . '/plancuentas.xlsx';

    $imageFileType = pathinfo($nombre_archivo, PATHINFO_EXTENSION);

    if ($imageFileType == "xlsx") {

        if (move_uploaded_file($archivo_guardado, $target_path)) {
            $objPHPExcel = PHPExcel_IOFactory::load('../docs_temp/plancuentas.xlsx');
            $objPHPExcel->setActiveSheetIndex(0);
            $numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
        ?>
            <div class="panel panel-info">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <tr class="info">
                            <th>Código</th>
                            <th>Cuenta</th>
                            <th>Nivel</th>
                            <th>Código SRI</th>
                            <th>Código Supercias</th>
                            <th>Observación</th>
                        </tr>
                        <?php
                        $codigos_cuentas = array();
                        for ($c = 1; $c <= $numRows; $c++) {
                            $codigos_cuentas[] = $objPHPExcel->getActiveSheet()->getCell('A' . $c)->getCalculatedValue();
                        }

                        $estados_acumulados = array();
                        $nota_nivel_acumulado = array();
                        $datos_procesados = array();

                        for ($i = 2; $i <= $numRows; $i++) {
                            $codigo = $objPHPExcel->getActiveSheet()->getCell('A' . $i)->getCalculatedValue();
                            $cuenta = $objPHPExcel->getActiveSheet()->getCell('B' . $i)->getCalculatedValue();
                            $nivel_obtenido = $objPHPExcel->getActiveSheet()->getCell('C' . $i)->getCalculatedValue();
                            $codigo_sri = $objPHPExcel->getActiveSheet()->getCell('D' . $i)->getCalculatedValue();
                            $codigo_supercias = $objPHPExcel->getActiveSheet()->getCell('E' . $i)->getCalculatedValue();

                            $estado = array();
                            $nivel_inferior = array();
                            $largo_codigo = strlen($codigo);
                            switch ($largo_codigo) {
                                case '1':
                                    $nivel = '1';
                                    $uno = (!is_numeric(substr($codigo, 0, 1))) ? 1 : 0;
                                    $estado[] = $uno;
                                    break;
                                case '3':
                                    $nivel = '2';
                                    $uno = (!is_numeric(substr($codigo, 0, 1))) ? 1 : 0;
                                    $dos = (!is_numeric(substr($codigo, 2, 1))) ? 1 : 0;
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 1), $codigos_cuentas) == null) ? "Falta cuenta nivel 1 " : "";
                                    $estado[] = $uno . $dos;
                                    break;
                                case '6':
                                    $nivel = '3';
                                    $uno = (!is_numeric(substr($codigo, 0, 1))) ? 1 : 0;
                                    $dos = (!is_numeric(substr($codigo, 2, 1))) ? 1 : 0;
                                    $tres = (!is_numeric(substr($codigo, 4, 2))) ? 1 : 0;
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 1), $codigos_cuentas) == null) ? "Falta cuenta nivel 1 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 3), $codigos_cuentas) == null) ? "Falta cuenta nivel 2 " : "";
                                    $estado[] = $uno . $dos . $tres;
                                    break;
                                case '9':
                                    $nivel = '4';
                                    $uno = (!is_numeric(substr($codigo, 0, 1))) ? 1 : 0;
                                    $dos = (!is_numeric(substr($codigo, 2, 1))) ? 1 : 0;
                                    $tres = (!is_numeric(substr($codigo, 4, 2))) ? 1 : 0;
                                    $cuatro = (!is_numeric(substr($codigo, 7, 2))) ? 1 : 0;
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 1), $codigos_cuentas) == null) ? "Falta cuenta nivel 1 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 3), $codigos_cuentas) == null) ? "Falta cuenta nivel 2 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 6), $codigos_cuentas) == null) ? "Falta cuenta nivel 3 " : "";
                                    $estado[] = $uno . $dos . $tres . $cuatro;
                                    break;
                                case '13':
                                    $nivel = '5';
                                    $uno = (!is_numeric(substr($codigo, 0, 1))) ? 1 : 0;
                                    $dos = (!is_numeric(substr($codigo, 2, 1))) ? 1 : 0;
                                    $tres = (!is_numeric(substr($codigo, 4, 2))) ? 1 : 0;
                                    $cuatro = (!is_numeric(substr($codigo, 7, 2))) ? 1 : 0;
                                    $cinco = (!is_numeric(substr($codigo, 10, 3))) ? 1 : 0;
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 1), $codigos_cuentas) == null) ? "Falta cuenta nivel 1 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 3), $codigos_cuentas) == null) ? "Falta cuenta nivel 2 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 6), $codigos_cuentas) == null) ? "Falta cuenta nivel 3 " : "";
                                    $nivel_inferior[] = (array_search(substr($codigo, 0, 9), $codigos_cuentas) == null) ? "Falta cuenta nivel 4 " : "";
                                    $estado[] = $uno . $dos . $tres . $cuatro . $cinco;
                                    break;
                                default:
                                    $nivel = '0';
                                    $estado[] = 1;
                            }

                            $suma_estados = array_sum($estado);
                            $estados_acumulados[] = array_sum($estado);


                            if ($suma_estados > 0) {
                                $estado_final = "Error en código de cuenta contable.";
                            } else {
                                $estado_final = "";
                            }

                            $nota_nivel_inferior = "";
                            foreach ($nivel_inferior as $aviso_nivel_inferior) {
                                $nota_nivel_inferior .= $aviso_nivel_inferior;
                            }

                            $nota_nivel_acumulado[] = $nota_nivel_inferior;
                        ?>
                            <tr>
                                <td><?php echo $codigo; ?></td>
                                <td><?php echo $cuenta; ?></td>
                                <td><?php echo $nivel; ?></td>
                                <td><?php echo $codigo_sri; ?></td>
                                <td><?php echo $codigo_supercias; ?></td>
                                <td><?php echo $estado_final . $nota_nivel_inferior; ?></td>
                            </tr>
                        <?php

                            $datos_procesados[] = array('codigo' => $codigo, 'cuenta' => $cuenta, 'nivel' => $nivel, 'codigo_sri' => $codigo_sri, 'codigo_supercias' => $codigo_supercias);
                        }

                        $estados_finales = array_sum($estados_acumulados);
                        $notas_niveles = "";
                        foreach ($nota_nivel_acumulado as $aviso_niveles) {
                            $notas_niveles .= $aviso_niveles;
                        }

                        if ($estados_finales == 0 && $notas_niveles == "") {
                            $total_registros = count($datos_procesados) - 1;
                            ini_set('date.timezone', 'America/Guayaquil');
                            $fecha_registro = date('Y-m-d H:i:s');
                            $total_guardadas = array();
                            for ($g = 0; $g <= $total_registros; $g++) {
                                $consultar_cuenta = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE codigo_cuenta= '" . $datos_procesados[$g]['codigo'] . "' and ruc_empresa='" . $ruc_empresa . "' ");
                                $contar_codigos_registrados = mysqli_num_rows($consultar_cuenta);
                                if ($contar_codigos_registrados == 0 && $datos_procesados[$g]['codigo'] != "") {
                                    $guardar_cuenta = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'" . $datos_procesados[$g]['codigo'] . "','" . $datos_procesados[$g]['nivel'] . "','" . $datos_procesados[$g]['cuenta'] . "','" . $datos_procesados[$g]['codigo_sri'] . "','" . $datos_procesados[$g]['codigo_supercias'] . "','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");
                                    $total_guardadas[] = 1;
                                }
                            }

                            $suma_registros = array_sum($total_guardadas);
                            if ($suma_registros > 0) {
                                echo "<script>
                            var total_registros = '$suma_registros';
                            $.notify(total_registros+' Cuenta(s) han sido guardadas.','success');
                            setTimeout(function (){location.href ='../modulos/plan_de_cuentas.php'}, 2000);
                            </script>";
                            } else {
                                echo "<script>$.notify('Cuentas registradas con anterioridad.','error');
                            </script>";
                            }
                        } else {
                            echo "<script>$.notify('No se puede guardar, existen errores en los registros, revisar en la columna oservaciones.','error');
                            </script>";
                        }

                        ?>
                    </table>
                </div>
            </div>
        <?php
        } else {
            echo "<script>$.notify('El archivo no se pudo cargar.','error');</script>";
        }
        closedir($dir);
    } else {
        echo "<script>$.notify('El archivo no es de tipo excel.','error');</script>";
    }
}


if ($action == 'guardar_cuenta_contable') {
    if (empty($_POST['nuevo_codigo_cuenta'])) {
        echo "<script>$.notify('Seleccione una cuenta para generar un nuevo código','error');</script>";
    } else if (empty($_POST['nuevo_nombre_cuenta'])) {
        echo "<script>$.notify('Ingrese un nombre para la cuenta','error');</script>";
    } else if ((!empty($_POST['nuevo_codigo_cuenta'])) && (!empty($_POST['nuevo_nombre_cuenta']))) {
        $codigo_cuenta = mysqli_real_escape_string($con, (strip_tags($_POST["nuevo_codigo_cuenta"], ENT_QUOTES)));
        $nombre_cuenta = mysqli_real_escape_string($con, (strip_tags($_POST["nuevo_nombre_cuenta"], ENT_QUOTES)));
        $nivel_cuenta = mysqli_real_escape_string($con, (strip_tags($_POST["nuevo_nivel_cuenta"], ENT_QUOTES)));
        $codigo_sri = mysqli_real_escape_string($con, (strip_tags($_POST["nuevo_codigo_sri"], ENT_QUOTES)));
        $codigo_supercias = mysqli_real_escape_string($con, (strip_tags($_POST["nuevo_codigo_supercias"], ENT_QUOTES)));
        $id_proyecto = mysqli_real_escape_string($con, (strip_tags($_POST["proyecto"], ENT_QUOTES)));

        $buscar_cuenta = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE ruc_empresa='" . $ruc_empresa . "' and codigo_cuenta='" . $codigo_cuenta . "'");
        $contar_cuentas_registradas = mysqli_num_rows($buscar_cuenta);
        if ($contar_cuentas_registradas > 0) {
            $errors[] = "El código de cuenta ya está registrado, intente de nuevo.";
        } else {
            $guardar_cuenta = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'" . $codigo_cuenta . "','" . $nivel_cuenta . "','" . $nombre_cuenta . "','" . $codigo_sri . "','" . $codigo_supercias . "','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','" . $id_proyecto . "')");
            if ($guardar_cuenta) {
                echo "<script>
				$.notify('La cuenta ha sido guardada','success');
				$('.close:visible').click();
				</script>";
            } else {
                echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente','error');</script>";
            }
        }
    } else {
        echo "<script>$.notify('Intente de nuevo','error');</script>";
    }
}


if ($action == 'editar_cuenta_contable') {

    $cuenta_encontrada = 0;
    $sql_periodo_contable = mysqli_query($con, "SELECT mes_periodo, anio_periodo FROM periodo_contable WHERE ruc_empresa ='" . $ruc_empresa . "' and status = 2 ");
    while ($row_periodo_contable = mysqli_fetch_array($sql_periodo_contable)) {
        $periodo = $row_periodo_contable['anio_periodo'] . "-" . $row_periodo_contable['mes_periodo'];

        $sql_diario_contable = mysqli_query($con, "SELECT det.id_cuenta as id_cuenta FROM detalle_diario_contable as det
        INNER JOIN encabezado_diario as enc ON enc.codigo_unico=det.codigo_unico
         WHERE enc.ruc_empresa ='" . $ruc_empresa . "' and det.id_cuenta ='" . $_POST['mod_id_cuenta'] . "' 
         and DATE_FORMAT(enc.fecha_asiento, '%Y-%m') = '" . $periodo . "' ");
        $row_cuenta = mysqli_fetch_array($sql_diario_contable);
        $cuenta_encontrada += isset($row_cuenta['id_cuenta']) == $_POST['mod_id_cuenta'] ? 1 : 0;
    }


    if (empty($_POST['mod_id_cuenta'])) {
        echo "<script>$.notify('Seleccione una cuenta para modificar.','error');</script>";
    } else if (empty($_POST['mod_nombre_cuenta'])) {
        echo "<script>$.notify('Ingrese un nombre de cuenta contable.','error');</script>";
    } else if ((!empty($_POST['mod_id_cuenta'])) && (!empty($_POST['mod_nombre_cuenta']))) {
        $id_cuenta = mysqli_real_escape_string($con, (strip_tags($_POST["mod_id_cuenta"], ENT_QUOTES)));
        $status = mysqli_real_escape_string($con, (strip_tags($_POST["listStatus"], ENT_QUOTES)));
        $nombre_cuenta = mysqli_real_escape_string($con, (strip_tags($_POST["mod_nombre_cuenta"], ENT_QUOTES)));
        $codigo_sri = mysqli_real_escape_string($con, (strip_tags($_POST["mod_codigo_sri"], ENT_QUOTES)));
        $codigo_supercias = mysqli_real_escape_string($con, (strip_tags($_POST["mod_codigo_supercias"], ENT_QUOTES)));
        $mod_proyecto = mysqli_real_escape_string($con, (strip_tags($_POST["mod_proyecto"], ENT_QUOTES)));

        if ($cuenta_encontrada > 0) {
            $actualizar_cuenta = mysqli_query($con, "UPDATE plan_cuentas SET codigo_sri='" . $codigo_sri . "', codigo_supercias ='" . $codigo_supercias . "', id_usuario='" . $id_usuario . "', fecha_registro='" . $fecha_registro . "', status='" . $status . "', id_proyecto='" . $mod_proyecto . "' WHERE id_cuenta ='" . $id_cuenta . "'");
            echo "<script>$.notify('Registro actualizado. (Excepto el nombre de la cuenta ya que pertenece a un período cerrado.)','warning');
            </script>";
        } else {
            $actualizar_cuenta = mysqli_query($con, "UPDATE plan_cuentas SET nombre_cuenta ='" . $nombre_cuenta . "', codigo_sri='" . $codigo_sri . "', codigo_supercias ='" . $codigo_supercias . "', id_usuario='" . $id_usuario . "', fecha_registro='" . $fecha_registro . "', status='" . $status . "', id_proyecto='" . $mod_proyecto . "' WHERE id_cuenta ='" . $id_cuenta . "'");
            echo "<script>$.notify('Cuenta contable actualizada.','success');
            $('.close:visible').click();
            </script>";
        }
    } else {
        echo "<script>
        $.notify('Intenta de nuevo.','error');
        </script>";
    }
}


if ($action == 'plan_cuentas_inicial') {
    $cuentas_iniciales = array("1" => "ACTIVOS", "2" => "PASIVOS", "3" => "PATRIMONIO", "4" => "INGRESOS", "5" => "COSTOS", "6" => "GASTOS", "7" => "RESUMEN RESULTADOS");
    $codigo = array();
    for ($i = 1; $i <= 7; ++$i) {

        $buscar_cuenta = mysqli_query($con, "
            SELECT 1 
            FROM plan_cuentas 
            WHERE ruc_empresa='$ruc_empresa' 
            AND codigo_cuenta LIKE '$i%' 
            LIMIT 1
        ");

        if (mysqli_num_rows($buscar_cuenta) == 0) {
            $codigo[] = $i;
        }
    }
    foreach ($codigo as $valor) {
        $contar_cuentas_registradas = mysqli_num_fields($buscar_cuenta);
        $guardar_cuenta = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'" . $valor . "','1','" . $cuentas_iniciales[$valor] . "','','','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");

        if ($valor == '7') {
            $guardar_cuenta_nivel_dos = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'7.1','2','RESUMEN RESULTADOS','','','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");
            $guardar_cuenta_nivel_tres = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'7.1.01','3','RESUMEN RESULTADOS','','','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");
            $guardar_cuenta_nivel_cuatro = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'7.1.01.01','4','RESUMEN RESULTADOS','','','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");
            $guardar_cuenta_nivel_cinco = mysqli_query($con, "INSERT INTO plan_cuentas VALUES (null,'7.1.01.01.001','5','RESUMEN RESULTADOS-CIERRE','','','" . $ruc_empresa . "','" . $id_usuario . "','" . $fecha_registro . "','" . $codigo_unico . "','1','0')");
        }
    }

    echo "<script>
    $.notify('Plan de cuentas creado','success');
    load(1);
    </script>";
}


if ($action == 'eliminar_cuentas_contables') {
    $id_cuenta = intval($_GET['id_cuenta']);

    $consulta_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta ='" . $id_cuenta . "' ");
    $row_codigo_cuenta = mysqli_fetch_array($consulta_cuentas);
    $codigo_cuenta = $row_codigo_cuenta['codigo_cuenta'];
    $nivel_entrada = $row_codigo_cuenta['nivel_cuenta'];
    $siguiente_nivel = $nivel_entrada + 1;
    //buscar nivel superior al seleccionado

    switch ($nivel_entrada) {
        case "1":
            $mid_inicial_entrada = "1";
            $mid_largo_entrada = "1";
            $codigo_final = substr($codigo_cuenta, 0, 1);
            break;
        case "2":
            $mid_inicial_entrada = "1";
            $mid_largo_entrada = "3";
            $codigo_final = substr($codigo_cuenta, 0, 3);
            break;
        case "3":
            $mid_inicial_entrada = "1";
            $mid_largo_entrada = "6";
            $codigo_final = substr($codigo_cuenta, 0, 6);
            break;
        case "4":
            $mid_inicial_entrada = "1";
            $mid_largo_entrada = "9";
            $codigo_final = substr($codigo_cuenta, 0, 9);
            break;
        case "5":
            $mid_inicial_entrada = "1";
            $mid_largo_entrada = "13";
            $codigo_final = substr($codigo_cuenta, 0, 13);
            break;
    }

    $buscar_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and nivel_cuenta='" . $siguiente_nivel . "' and mid(codigo_cuenta, $mid_inicial_entrada, $mid_largo_entrada) = '" . $codigo_final . "'");
    $total_cuentas = mysqli_num_rows($buscar_cuentas);

    if ($total_cuentas == 0) {
        //aqui comprobar si hay registros con esa cuenta y empresa
        $buscar_cuentas_en_uso = mysqli_query($con, "SELECT * FROM detalle_diario_contable WHERE id_cuenta='" . $id_cuenta . "'");
        $total_cuentas_en_uso = mysqli_num_rows($buscar_cuentas_en_uso);
        if ($total_cuentas_en_uso == 0) {
            $deleteuno = mysqli_query($con, "DELETE FROM plan_cuentas WHERE id_cuenta='" . $id_cuenta . "'");
            $deletedos = mysqli_query($con, "DELETE FROM asientos_programados WHERE id_cuenta='" . $id_cuenta . "'");
            echo "<script>$.notify('Cuenta eliminada.','success')</script>";
        } else {
            echo "<script>$.notify('No es posible eliminar la cuenta, exiten registros en uso.','error')</script>";
        }
    } else {
        echo "<script>$.notify('No es posible eliminar la cuenta, exiten registros de cuentas con nivel superior al seleccionado.','error')</script>";
    }
}


if ($action == 'buscar_cuentas') {
    $q = $_GET['q'];
    $ordenado = $_GET['ordenado'];
    $por = $_GET['por'];
    $status = mysqli_real_escape_string($con, (strip_tags($_GET['status'], ENT_QUOTES)));
    $nivel = mysqli_real_escape_string($con, (strip_tags($_GET['nivel'], ENT_QUOTES)));
    $grupo = mysqli_real_escape_string($con, (strip_tags($_GET['grupo'], ENT_QUOTES)));
    $id_proyecto = mysqli_real_escape_string($con, (strip_tags($_GET['id_proyecto'], ENT_QUOTES)));

    if ($status === "") {
        $opciones_status = "";
    } else {
        $opciones_status = " and status = '" . $status . "' ";
    }
    if (empty($nivel)) {
        $opciones_nivel = "";
    } else {
        $opciones_nivel = " and nivel_cuenta = '" . $nivel . "' ";
    }
    if (empty($grupo)) {
        $opciones_grupo = "";
    } else {
        $opciones_grupo = " and codigo_cuenta LIKE '" . $grupo . "%' ";
    }

    if ($id_proyecto === "0") {
        $opciones_proyecto = "";
    } else {
        $opciones_proyecto = " and id_proyecto = '" . $id_proyecto . "' ";
    }

    $aColumns = array('nombre_cuenta', 'codigo_cuenta', 'codigo_sri', 'codigo_supercias'); //Columnas de busqueda
    $sTable = "plan_cuentas";
    $sWhere = "WHERE ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_nivel $opciones_grupo $opciones_proyecto";
    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }
    if ($_GET['q'] != "") {
        $sWhere = "WHERE ( ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_nivel $opciones_grupo $opciones_proyecto AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $like . "%' AND ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_nivel $opciones_grupo $opciones_proyecto OR ";
        }
        $sWhere = substr_replace($sWhere, "AND ruc_empresa = '" . $ruc_empresa . "' $opciones_status $opciones_nivel $opciones_grupo $opciones_proyecto ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by $ordenado $por";

    include("../ajax/pagination.php"); //include pagination file
    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 20; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    // echo mysqli_error($con);
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '../plan_de_cuentas.php';
    //main query to fetch the data
    $sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

        ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_cuenta");'>Código</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre_cuenta");'>Nombre</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nivel_cuenta");'>Nivel</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_sri");'>Código SRI</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_supercias");'>Código Supercias</button></th>
                        <th>Agregar</th>
                        <th>Status</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    $espacio = "---";
                    while ($row = mysqli_fetch_array($query)) {
                        $id_cuenta = $row['id_cuenta'];
                        $nombre_cuenta = $row['nombre_cuenta'];
                        $codigo_cuenta = $row['codigo_cuenta'];
                        $codigo_sri = $row['codigo_sri'];
                        $nivel_cuenta = $row['nivel_cuenta'];
                        $codigo_supercias = $row['codigo_supercias'];
                        $status = $row['status'];
                    ?>
                        <input type="hidden" value="<?php echo $nombre_cuenta; ?>" id="nombre_cuenta<?php echo $id_cuenta; ?>">
                        <input type="hidden" value="<?php echo $codigo_cuenta; ?>" id="codigo_cuenta<?php echo $id_cuenta; ?>">
                        <input type="hidden" value="<?php echo $nivel_cuenta; ?>" id="nivel_cuenta<?php echo $id_cuenta; ?>">
                        <input type="hidden" value="<?php echo $page; ?>" id="pagina">
                        <tr>
                            <td><?php echo $codigo_cuenta; ?></td>
                            <?php
                            if ($nivel_cuenta == '1') {
                            ?>
                                <td><?php echo $nombre_cuenta; ?></td>
                            <?php
                            }
                            if ($nivel_cuenta == '2') {
                            ?>
                                <td><?php echo "&nbsp" . "&nbsp" . "&nbsp" . $nombre_cuenta; ?></td>
                            <?php
                            }
                            if ($nivel_cuenta == '3') {
                            ?>
                                <td><?php echo "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . $nombre_cuenta; ?></td>
                            <?php
                            }
                            if ($nivel_cuenta == '4') {
                            ?>
                                <td><?php echo "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . $nombre_cuenta; ?></td>
                            <?php
                            }
                            if ($nivel_cuenta == '5') {
                            ?>
                                <td><?php echo "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . "&nbsp" . ucwords($nombre_cuenta); ?></td>
                            <?php
                            }
                            ?>
                            <td><?php echo $nivel_cuenta; ?></td>
                            <td><?php echo $codigo_sri; ?></td>
                            <td><?php echo $codigo_supercias; ?></td>
                            <td>
                                <?php
                                if ($nivel_cuenta <= '4') {
                                ?>
                                    <a href="#" class='btn btn-info btn-xs' title='Agregar nueva cuenta' onclick="mostrar_datos_nueva_cuenta('<?php echo $id_cuenta; ?>');" data-toggle="modal" data-target="#NuevaCuenta"><i class="glyphicon glyphicon-plus"></i>Agregar cuenta</a>
                                <?php
                                }
                                ?>
                            </td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activa</span>" : "<span class='label label-danger'>Inactiva</span>"; ?></td>
                            <td><span class="pull-right">
                                    <a href="#" class='btn btn-info btn-xs' title='Editar cuenta' onclick="obtener_datos_editar_cuenta('<?php echo $id_cuenta; ?>');" data-toggle="modal" data-target="#EditarCuentaContable"><i class="glyphicon glyphicon-edit"></i></a>
                                    <a href="#" class='btn btn-danger btn-xs' title='Eliminar cuenta' onclick="eliminar_cuenta_contable('<?php echo $id_cuenta; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="9"><span class="pull-right">
                                <?php
                                echo paginate($reload, $page, $total_pages, $adjacents);
                                ?></span></td>
                    </tr>
                </table>
            </div>
        </div>
<?php
    }
}


function siguiente_codigo($con, $id_cuenta, $ruc_empresa)
{

    $consulta_cuentas = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta ='" . $id_cuenta . "' ");
    $row_codigo_cuenta = mysqli_fetch_array($consulta_cuentas);
    $codigo_cuenta = $row_codigo_cuenta['codigo_cuenta'];
    $nivel_salida = $row_codigo_cuenta['nivel_cuenta'] + 1;

    switch ($nivel_salida) {
        case "2":
            $mid_largo = "1";
            $mid_inicial_salida = "3";
            $mid_largo_salida = "1";
            $codigo_inicial = substr($codigo_cuenta, 0, 3);
            break;
        case "3":
            $mid_largo = "3";
            $mid_inicial_salida = "5";
            $mid_largo_salida = "2";
            $codigo_inicial = substr($codigo_cuenta, 0, 6);
            break;
        case "4":
            $mid_largo = "6";
            $mid_inicial_salida = "8";
            $mid_largo_salida = "2";
            $codigo_inicial = substr($codigo_cuenta, 0, 9);
            break;
        case "5":
            $mid_largo = "9";
            $mid_inicial_salida = "11";
            $mid_largo_salida = "3";
            $codigo_inicial = substr($codigo_cuenta, 0, 13);
            break;
    }

    $consulta_cuentas = mysqli_query($con, "SELECT max(mid(codigo_cuenta, $mid_inicial_salida, $mid_largo_salida)) as ultimo FROM plan_cuentas WHERE ruc_empresa ='" . $ruc_empresa . "' and nivel_cuenta='" . $nivel_salida . "' and mid(codigo_cuenta,1, $mid_largo) = '" . $codigo_cuenta . "' ");
    $row_cuentas = mysqli_fetch_array($consulta_cuentas);
    $inicial = 1;
    $siguiente_codigo = $row_cuentas['ultimo'] + 1;

    $serie_inicio_fin = array();
    foreach (range($inicial, $siguiente_codigo) as $toda_la_serie) {
        $serie_inicio_fin[] = intval($toda_la_serie);
    }

    $solo_registrados = array();
    $todas_cuentas = mysqli_query($con, "SELECT mid(codigo_cuenta, $mid_inicial_salida, $mid_largo_salida) as codigos FROM plan_cuentas WHERE ruc_empresa ='" . $ruc_empresa . "' and nivel_cuenta='" . $nivel_salida . "' and mid(codigo_cuenta,1, $mid_largo) = '" . $codigo_cuenta . "' ");
    while ($todos_las_encontrados = mysqli_fetch_array($todas_cuentas)) {
        $solo_registrados[] = intval($todos_las_encontrados['codigos']);
    }

    $codigos_faltantes = array_diff($serie_inicio_fin, $solo_registrados);
    if ($codigos_faltantes == false) {

        if ($nivel_salida == "1") {
            $numero_final = str_pad($siguiente_codigo, 1, "0", STR_PAD_LEFT);
        }
        if ($nivel_salida == "2") {
            $numero_final = str_pad($siguiente_codigo, 1, "0", STR_PAD_LEFT);
        }
        if ($nivel_salida == "3") {
            $numero_final = str_pad($siguiente_codigo, 2, "00", STR_PAD_LEFT);
        }
        if ($nivel_salida == "4") {
            $numero_final = str_pad($siguiente_codigo, 2, "00", STR_PAD_LEFT);
        }
        if ($nivel_salida == "5") {
            $numero_final = str_pad($siguiente_codigo, 3, "000", STR_PAD_LEFT);
        }

        return $codigo_inicial . "." . $numero_final;
    } else {
        $codigo_faltante = min($codigos_faltantes);
        if ($nivel_salida == "1") {
            $numero_faltante = str_pad($codigo_faltante, 1, "0", STR_PAD_LEFT);
        }
        if ($nivel_salida == "2") {
            $numero_faltante = str_pad($codigo_faltante, 1, "0", STR_PAD_LEFT);
        }
        if ($nivel_salida == "3") {
            $numero_faltante = str_pad($codigo_faltante, 2, "00", STR_PAD_LEFT);
        }
        if ($nivel_salida == "4") {
            $numero_faltante = str_pad($codigo_faltante, 2, "00", STR_PAD_LEFT);
        }
        if ($nivel_salida == "5") {
            $numero_faltante = str_pad($codigo_faltante, 3, "000", STR_PAD_LEFT);
        }
        return $codigo_inicial . "." . $numero_faltante;
    }
}
