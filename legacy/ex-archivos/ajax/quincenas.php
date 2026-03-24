<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
setlocale(LC_ALL, "es_ES");
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'actualizar_quincena') {
    $id_quincena = intval($_GET['id_quincena']);
    $mes_ano = $_GET['mes_ano'];
    $res = actualizar_quincena($con, $id_usuario, $id_quincena, $id_empresa, $mes_ano);
    return $res;
}

if ($action == 'detalle_quincenas') {
    $id_quincena = intval($_GET['id_quincena']);

    $qui = mysqli_real_escape_string($con, (strip_tags($_REQUEST['qui'], ENT_QUOTES)));
    $aColumns = array('emp.nombres_apellidos'); //Columnas de busqueda
    $sTable = "detalle_quincena as qui INNER JOIN empleados as emp ON emp.id=qui.id_empleado"; //INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
    $sWhere = "WHERE qui.id_quincena='" . $id_quincena . "'";

    $text_buscar = explode(' ', $qui);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['qui'] != "") {
        $sWhere = "WHERE (qui.id_quincena='" . $id_quincena . "' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND qui.id_quincena='" . $id_quincena . "' OR ";
        }
        $sWhere = substr_replace($sWhere, " AND qui.id_quincena='" . $id_quincena . "' ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by emp.nombres_apellidos asc";


    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 10; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT qui.id as id, round(qui.quincena,2) as quincena, round(qui.adicional,2) as adicional,
round(qui.descuento,2) as descuento, round(qui.arecibir,2) as arecibir, round(qui.abonos,2) as abonos, 
emp.nombres_apellidos as empleado, emp.email as correo_empleado FROM $sTable $sWhere LIMIT $offset,$per_page"; //round(sum(det.arecibir),2) as total_quincena
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Empleado</th>
                        <th>Quincena</th>
                        <th>Adicionales</th>
                        <th>Descuentos</th>
                        <th>A_Recibir</th>
                        <th>Saldo</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row_quincena = mysqli_fetch_array($query)) {
                        $empleado = strtoupper($row_quincena['empleado']);
                        $id_registro = $row_quincena['id'];
                        $quincena = $row_quincena['quincena'];
                        $adicional = $row_quincena['adicional'];
                        $descuento = $row_quincena['descuento'];
                        $arecibir = $row_quincena['arecibir'];
                        $abonos = $row_quincena['abonos'];
                        $correo_empleado = $row_quincena['correo_empleado'];
                        $id_encrypt = encrypt_decrypt("encrypt", $id_registro);
                        $path = encrypt_decrypt("encrypt", ""); //para ver la carpeta donde se va a descargar el pdf 
                        $tipoDescarga = encrypt_decrypt("encrypt", "D");
                    ?>
                        <tr>
                            <td><?php echo $empleado; ?></td>
                            <td class="text-right"><?php echo $quincena; ?></td>
                            <td class="text-right"><?php echo $adicional; ?></td>
                            <td class="text-right"><?php echo $descuento; ?></td>
                            <td class="text-right"><?php echo $arecibir; ?></td>
                            <td class="text-right"><?php echo number_format($arecibir - $abonos, 2, '.', ''); ?></td>
                            <td><span class="pull-right">
                                    <button type="button" class="btn btn-info btn-xs" onclick="enviar_quincena_mail('<?php echo $id_registro; ?>', '<?php echo $correo_empleado; ?>');" title="Enviar a: <?php echo $correo_empleado; ?>" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope"></span></button>
                                    <a title="Imprimir quincena en pdf" href="../ajax/imprime_documento.php?action=pdf_quincena_individual&path=<?php echo $path; ?>&tipoDescarga=<?php echo $tipoDescarga; ?>&id_quincena=<?php echo $id_encrypt; ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="7"><span class="pull-right">
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


if ($action == 'eliminar_quincena') {
    $id_quincena = intval($_POST['id_quincena']);

    $sql_quincena = mysqli_query($con, "SELECT round(sum(abonos),2) as abonos FROM detalle_quincena WHERE id_quincena='" . $id_quincena . "' group by id_quincena");
    $row_quincena = mysqli_fetch_array($sql_quincena);
    $abonos = $row_quincena['abonos'];


    if ($abonos > 0) {
        echo "<script>$.notify('No se puede eliminar. La quincena tiene registros pagados.','error')</script>";
    } else {
        if ($deleteuno = mysqli_query($con, "UPDATE quincenas SET status ='0' WHERE id='" . $id_quincena . "'")) {
            echo "<script>$.notify('Quincena eliminada.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar quincenas
if ($action == 'buscar_quincenas') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('qui.datecreated', 'qui.mes_ano'); //Columnas de busqueda
    $sTable = "quincenas as qui"; //INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
    $sWhere = "WHERE qui.id_empresa='" . $id_empresa . "' and qui.status !=0 ";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (qui.id_empresa='" . $id_empresa . "' and qui.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND qui.id_empresa='" . $id_empresa . "' and qui.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, " AND qui.id_empresa='" . $id_empresa . "' and qui.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by mid(qui.mes_ano,4,4) desc, mid(qui.mes_ano,1,2) desc";


    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 12; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '../quincenas.php';
    //main query to fetch the data
    $sql = "SELECT qui.id as id, qui.datecreated as datecreated, qui.mes_ano as mes_ano
    , (select round(sum(arecibir),2) from detalle_quincena where id_quincena=qui.id group by id_quincena) as total_quincena FROM $sTable $sWhere LIMIT $offset,$per_page"; //round(sum(det.arecibir),2) as total_quincena
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Fecha</th>
                        <th class="text-center">Mes-Año</th>
                        <th class="text-right">Total</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $mes_ano = $row['mes_ano'];
                        $datecreated = $row['datecreated'];
                        $total_quincena = $row['total_quincena'];

                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($datecreated)); ?></td>
                            <td class="text-center"><?php echo $mes_ano; ?></td>
                            <td class="text-right"><?php echo $total_quincena; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'quincenas')['r'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Detalle quincenas" onclick="detalle_quincena('<?php echo $id_registro; ?>', '<?php echo $mes_ano; ?>');" data-toggle="modal" data-target="#modalViewQuincenas"><i class="glyphicon glyphicon-list"></i></a>
                                    <?php
                                    }
                                    /*                                     if (getPermisos($con, $id_usuario, $ruc_empresa, 'quincenas')['u'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Editar quincena" onclick="editar_quincena('<?php echo $id_registro; ?>');" data-toggle="modal" data-target="#modalQuincenas"><i class="glyphicon glyphicon-edit"></i></a>
                                    <?php
                                    } */
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'quincenas')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar quincena" onclick="eliminar_quincena('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="4"><span class="pull-right">
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

//guardar o editar quincenas
if ($action == 'guardar_quincena') {
    $id_quincena = intval($_POST['id_quincena']);
    $mes = $_POST['mes'];
    $ano = $_POST['ano'];
    $mes_ano = $mes . "-" . $ano;

    if (empty($mes)) {
        echo "<script>
            $.notify('Seleccione mes','error');
            </script>";
    } else if (empty($ano)) {
        echo "<script>
        $.notify('Seleccione año','error');
        </script>";
    } else {
        if (empty($id_quincena)) {
            $busca_quincena = mysqli_query($con, "SELECT * FROM quincenas WHERE id_empresa = '" . $id_empresa . "' and status !=0 and mes_ano='" . $mes_ano . "' ");
            $count = mysqli_num_rows($busca_quincena);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe una quincena con este período que puede editar','error');
                </script>";
            } else {
                $guarda_quincena = mysqli_query($con, "INSERT INTO quincenas (id_empresa, id_usuario, mes_ano) VALUES('" . $id_empresa . "','" . $id_usuario . "','" . $mes_ano . "')");
                $lastid = mysqli_insert_id($con);
                if ($guarda_quincena) {
                    detalle_quincena($con, $id_empresa, $lastid, $mes_ano);
                    echo "<script>
                $.notify('Quincena registrada','success');
                document.querySelector('#formQuincenas').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No es posible registrar la quincena, intente de nuevo','error');
                </script>";
                }
            }
        } else {
            $update_quincena = actualizar_quincena($con, $id_usuario, $id_quincena, $id_empresa, $mes_ano);
            if ($update_quincena) {
                echo "<script>
                    $.notify('Quincena actualizada','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
            } else {
                echo "<script>
                        $.notify('No es posible actualizar, intente de nuevo','error');
                        </script>";
            }
        }
    }
}

function actualizar_quincena($con, $id_usuario, $id_quincena, $id_empresa, $mes_ano)
{
    $update_quincena = mysqli_query($con, "UPDATE quincenas SET id_usuario='" . $id_usuario . "' WHERE id='" . $id_quincena . "'");
    detalle_quincena($con, $id_empresa, $id_quincena, $mes_ano);
    return $update_quincena;
}


function detalle_quincena($con, $id_empresa, $id_quincena, $mes_ano)
{
    $sql_delete_no_pagados = mysqli_query($con, "DELETE FROM detalle_quincena WHERE id_quincena = '" . $id_quincena . "' and abonos = 0");

    /* $request_sueldos = mysqli_query($con, "SELECT sue.id_empleado as id_empleado, round(sue.quincena,2) as quincena, round(sue.sueldo,2) as sueldo
     FROM sueldos as sue INNER JOIN empleados as emp ON emp.id=sue.id_empleado 
     WHERE sue.status = '1' and sue.id_empresa= '" . $id_empresa . "' and emp.status = '1' and sue.quincena > 0 
     and sue.id_empleado NOT IN (SELECT det.id_empleado FROM detalle_quincena as det WHERE det.id_quincena='" . $id_quincena . "')"); */

    $date = DateTime::createFromFormat('m-Y', $mes_ano);
    $mes_inicio = $date->format('Y-m-01'); // ahora sí: "2025-08-01"
   

    $request_sueldos = mysqli_query($con, "SELECT 
        sue.id_empleado AS id_empleado, 
        ROUND(sue.quincena,2) AS quincena, 
        ROUND(sue.sueldo,2)   AS sueldo
    FROM sueldos AS sue
    INNER JOIN empleados AS emp ON emp.id = sue.id_empleado
    WHERE sue.status = '1'
      AND sue.id_empresa = '" . mysqli_real_escape_string($con, $id_empresa) . "'
      AND emp.status = '1'
      AND sue.quincena > 0
      AND sue.id_empleado NOT IN (
            SELECT det.id_empleado 
            FROM detalle_quincena AS det 
            WHERE det.id_quincena = '" . mysqli_real_escape_string($con, $id_quincena) . "'
      )
      -- Debe haber ingresado antes del mes siguiente
      AND sue.fecha_ingreso < DATE_ADD('" . mysqli_real_escape_string($con, $mes_inicio) . "', INTERVAL 1 MONTH)
      -- Debe estar sin salida o que la salida sea después o igual al inicio del mes
      AND (
            sue.fecha_salida IS NULL 
            OR sue.fecha_salida = '0000-00-00'
            OR sue.fecha_salida >= '" . mysqli_real_escape_string($con, $mes_inicio) . "'
      )
");


    $calculos_salarios = calculos_rol_pagos(substr($mes_ano, -4), $con);
    $incremento_hora_nocturna = 1 + $calculos_salarios['hora_nocturna'] / 100;
    $incremento_hora_suplementaria = 1 + $calculos_salarios['hora_suplementaria'] / 100;
    $incremento_hora_extraordinaria = 1 + $calculos_salarios['hora_extraordinaria'] / 100;

    foreach ($request_sueldos as $detalle) {
        $hora_normal = number_format($detalle['sueldo'] / $calculos_salarios['hora_normal'], 2, '.', '');
        $calculo_hora_nocturna = number_format($hora_normal * $incremento_hora_nocturna, 2, '.', '');
        $calculo_hora_suplementaria = number_format($hora_normal * $incremento_hora_suplementaria, 2, '.', '');
        $calculo_hora_extraordinaria = number_format($hora_normal * $incremento_hora_extraordinaria, 2, '.', '');

        $codigos_descuentos = array(2, 3, 7, 8, 9); //codigos de helpers funcion novedades_sueldos()

        $sql_adicionales = mysqli_query($con, "SELECT round(sum(valor),2) as otros_ingresos FROM novedades WHERE id_novedad=1 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
        $request_adcionales = mysqli_fetch_array($sql_adicionales);
        $otros_ingresos = isset($request_adcionales['otros_ingresos']) ? $request_adcionales['otros_ingresos'] : 0;

        $suma_descuentos = 0;
        foreach ($codigos_descuentos as $des) {
            $sql_descuentos = mysqli_query($con, "SELECT round(sum(valor),2) as suma_descuentos FROM novedades WHERE id_novedad='" . $des . "' and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
            $row_descuentos = mysqli_fetch_array($sql_descuentos);
            $suma_descuentos += isset($row_descuentos['suma_descuentos']) ? $row_descuentos['suma_descuentos'] : 0;
        }

        $sql_horas_nocturnas = mysqli_query($con, "SELECT round(sum(valor),2) as horas_nocturnas FROM novedades WHERE id_novedad=4 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
        $request_horas_nocturnas = mysqli_fetch_array($sql_horas_nocturnas);
        $horas_nocturnas = isset($request_horas_nocturnas['horas_nocturnas']) ? $calculo_hora_nocturna * $request_horas_nocturnas['horas_nocturnas'] : 0;

        $sql_horas_suplementarias = mysqli_query($con, "SELECT round(sum(valor),2) as horas_suplementarias FROM novedades WHERE id_novedad=5 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
        $request_horas_suplementaria = mysqli_fetch_array($sql_horas_suplementarias);
        $horas_suplementarias = isset($request_horas_suplementaria['horas_suplementarias']) ? $calculo_hora_suplementaria * $request_horas_suplementaria['horas_suplementarias'] : 0;

        $sql_horas_extraordinarias = mysqli_query($con, "SELECT round(sum(valor),2) as horas_extraordinarias FROM novedades WHERE id_novedad=6 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
        $request_horas_extraordinaria = mysqli_fetch_array($sql_horas_extraordinarias);
        $horas_extraordinarias = isset($request_horas_extraordinaria['horas_extraordinarias']) ? $calculo_hora_extraordinaria * $request_horas_extraordinaria['horas_extraordinarias'] : 0;

        $a_recibir = number_format($detalle['quincena'] + $otros_ingresos + $horas_nocturnas + $horas_suplementarias + $horas_extraordinarias - $suma_descuentos, 2, '.', '');

        $adicionales = number_format($otros_ingresos + $horas_nocturnas + $horas_suplementarias + $horas_extraordinarias, 2, '.', '');
        $descuentos = number_format($suma_descuentos, 2, '.', '');

        $query_insert  = mysqli_query($con, "INSERT INTO detalle_quincena(id_quincena, 
                                                                                id_empleado, 
                                                                                quincena,
                                                                                adicional,
                                                                                descuento,
                                                                                arecibir) 
            VALUES('" . $id_quincena . "','" . $detalle['id_empleado'] . "','" . $detalle['quincena'] . "','" . $adicionales . "','" . $descuentos . "','" . $a_recibir . "')");
    }
}

?>