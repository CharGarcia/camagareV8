<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'datos_editar_novedad') {
    $id_novedad = $_GET['id_novedad'];
    $sql = mysqli_query($con, "SELECT * FROM novedades as nov INNER JOIN empleados as emp ON emp.id=nov.id_empleado 
    WHERE nov.id_empresa='" . $id_empresa . "' and nov.status !=0 and nov.id='" . $id_novedad . "'");
    $novedades = mysqli_fetch_array($sql);
    $data = array(
        'id_empleado' => $novedades['id_empleado'], 'empleado' => $novedades['nombres_apellidos'],
        'id_novedad' => $novedades['id_novedad'],
        'motivo_salida' => $novedades['motivo_salida'],
        'fecha_novedad' => date('d-m-Y', strtotime($novedades['fecha_novedad'])),
        'mes_afecta' => substr($novedades['mes_ano'], 0, 2),
        'ano_afecta' => substr($novedades['mes_ano'], 3, 4),
        'valor' => number_format($novedades['valor'], 2, '.', ''),
        'aplica_en' => $novedades['aplica_en'],
        'iess' => $novedades['iess'],
        'detalle' => $novedades['detalle']
    );
    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }
    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
    die();
}


if ($action == 'eliminar_novedad') {
    $id_novedad = intval($_POST['id_novedad']);

    $sql_novedad = mysqli_query($con, "SELECT * FROM novedades WHERE id='" . $id_novedad . "' ");
    $row_novedad = mysqli_fetch_array($sql_novedad);
    $mes_ano = $row_novedad['mes_ano'];
    $id_empleado = $row_novedad['id_empleado'];
    $aplica_en = $row_novedad['aplica_en'];

    $pagos = 0;
    if ($aplica_en == "R") {
        $sql_rol = mysqli_query($con, "SELECT det.id as id FROM rolespago as rol INNER JOIN detalle_rolespago as det ON
      det.id_rol=rol.id WHERE rol.id_empresa='" . $id_empresa . "' and rol.mes_ano='" . $mes_ano . "' 
      and det.id_empleado= '" . $id_empleado . "' and rol.status = 1 ");
        $row_rol = mysqli_fetch_array($sql_rol);
        $id_detalle_rol = isset($row_rol['id']) ? $row_rol['id'] : "0";
        if ($id_detalle_rol > 0) {
            $sql_egresos = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as pagos FROM detalle_ingresos_egresos WHERE tipo_ing_egr='CCXRPP' and codigo_documento_cv = concat('ROL_PAGOS', $id_detalle_rol) group by codigo_documento_cv ");
            $row_egresos = mysqli_fetch_array($sql_egresos);
            $pagos = isset($row_egresos['pagos']) ? $row_egresos['pagos'] : 0;
        }
    } else {
        $sql_quincena = mysqli_query($con, "SELECT det.id as id FROM quincenas as qui INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
       WHERE qui.id_empresa='" . $id_empresa . "' and qui.mes_ano='" . $mes_ano . "' and qui.status =1 and det.id_empleado='" . $id_empleado . "'");
        $row_quincena = mysqli_fetch_array($sql_quincena);
        $id_detalle_quincena = isset($row_quincena['id']) ? $row_quincena['id'] : "0";

        if ($id_detalle_quincena > 0) {
            $sql_egresos = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as pagos FROM detalle_ingresos_egresos WHERE tipo_ing_egr='CCXQPP' and codigo_documento_cv = concat('QUINCENA', $id_detalle_quincena) group by codigo_documento_cv ");
            $row_egresos = mysqli_fetch_array($sql_egresos);
            $pagos = isset($row_egresos['pagos']) ? $row_egresos['pagos'] : 0;
        }
    }

    if ($pagos > 0) {
        echo "<script>$.notify('No se puede eliminar. La novedad ya esta pagada.','error')</script>";
    } else {
        $deleteuno = mysqli_query($con, "UPDATE novedades SET status ='0' WHERE id='" . $id_novedad . "'");

        if ($id_novedad == '14') {
            $update_salida = mysqli_query($con, "UPDATE sueldos SET status ='1' WHERE id_empleado='" . $id_empleado . "' and status ='2'");
        }

        if ($deleteuno) {

            echo "<script>$.notify('Novedad eliminada.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar novedades
if ($action == 'buscar_novedades') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('detalle', 'nombres_apellidos', 'documento', 'mes_ano'); //Columnas de busqueda
    $sTable = "novedades as nov INNER JOIN empleados as emp ON emp.id=nov.id_empleado";
    $sWhere = "WHERE nov.id_empresa='" . $id_empresa . "' and nov.status !=0";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (nov.id_empresa='" . $id_empresa . "' and nov.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND nov.id_empresa='" . $id_empresa . "' and nov.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, "AND nov.id_empresa='" . $id_empresa . "' and nov.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by mid(nov.mes_ano,4,4) desc, mid(nov.mes_ano,1,2) desc";


    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 20; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '../novedades.php';
    //main query to fetch the data
    $sql = "SELECT nov.id as id, nov.id_empleado as id_empleado,
    nov.mes_ano as mes_ano, nov.id_novedad as id_novedad, emp.nombres_apellidos as nombres_apellidos,
     emp.documento as documento, round(nov.valor,2) as valor, nov.aplica_en as aplica_en, nov.iess as iess,
     nov.motivo_salida as motivo_salida, nov.detalle as detalle FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("mes_ano");'>Período</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombres_apellidos");'>Nombres_apellidos</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_novedad");'>Novedad</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("valor");'>Valor</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("detalle");'>Detalle</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("aplica_en");'>Aplica_en</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("iess");'>Aporta_IESS</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("motivo_salida");'>Motivo_Salida</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $id_empleado = $row['id_empleado'];
                        $periodo = $row['mes_ano'];
                        $id_novedad = $row['id_novedad'];
                        $empleado = strtoupper($row['nombres_apellidos']);
                        $documento = $row['documento'];
                        $valor = $row['valor'];
                        $aplica_en = $row['aplica_en'];
                        $iess = $row['iess'];
                        if ($iess == 1) {
                            $iess = "SI";
                        } else if ($iess == 0) {
                            $iess = "NO";
                        } else {
                            $iess = "";
                        }

                        $salida = $row['motivo_salida'];
                        $detalle = $row['detalle'];

                    ?>
                        <tr>
                            <td><?php echo $periodo; ?></td>
                            <td><?php echo $empleado; ?></td>
                            <td><?php foreach (novedades_sueldos() as $novedad) {
                                    if ($novedad['codigo'] == $id_novedad) {
                                        echo strtoupper($novedad['nombre']);
                                    }
                                } ?></td>
                            <td class="text-right"><?php echo $valor; ?></td>
                            <td><?php echo strtoupper($detalle); ?></td>
                            <td><?php echo $aplica_en == "R" ? "Rol mensual" : "Quincena"; ?></td>
                            <td class="text-center"><?php echo $iess; ?></td>
                            <td><?php foreach (motivo_salida_iess() as $motivo) {
                                    if ($motivo['codigo'] == $salida) {
                                        echo strtoupper($motivo['nombre']);
                                    }
                                } ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'novedades')['u'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Editar novedad" onclick="editar_novedad('<?php echo $id_registro; ?>');" data-toggle="modal" data-target="#modalNovedades"><i class="glyphicon glyphicon-edit"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'novedades')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar novedad" onclick="eliminar_novedad('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>

                    <tr>
                        <td colspan="11"><span class="pull-right">
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

//guardar o editar novedades
if ($action == 'guardar_novedad') {
    $id_registro = intval($_POST['id_registro']);
    $id_empleado = intval($_POST['id_empleado']);
    $id_novedad = intval($_POST['id_novedad']);
    $motivo_salida = $_POST['motivo_salida'];
    $fecha_novedad = $_POST['fecha_novedad'];
    $mes_ano = $_POST['mes'] . "-" . $_POST['ano'];
    $aplica_en = $_POST['aplica_en'];
    $detalle = strClean($_POST['detalle']);
    $valor = $_POST['valor'];
    $iess = $_POST['iess'];
    $sms_aplica_en = $aplica_en == 'R' ? "El rol de este período ya ha sido pagado" : "La quincena de este período ya ha sido pagada";

    //la novedad dias no laborados siempre va aplicar en rol de pagos y no en quincena
    if ($id_novedad == 10) {
        $aplica_en = "R";
        $valor = number_format(floor($_POST['valor']), 2, '.', '');
    } else {
        $aplica_en = strClean($_POST['aplica_en']);
        $valor = number_format($_POST['valor'], 2, '.', '');
    }

    $sql_novedad_salida = mysqli_query($con, "SELECT count(*) AS numrows FROM novedades WHERE id_empresa='" . $id_empresa . "' and id_empleado = '" . $id_empleado . "' and id_novedad =14 and mes_ano='" . $mes_ano . "' and status = 1");
    $row_salidas = mysqli_fetch_array($sql_novedad_salida);
    $numrows_salida = $row_salidas['numrows'];



    if (empty($id_registro)) {
        $sql_novedad_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM novedades WHERE id_empresa='" . $id_empresa . "' and id_empleado = '" . $id_empleado . "' and id_novedad = '" . $id_novedad . "' and mes_ano='" . $mes_ano . "' and detalle= '" . $detalle . "' and valor = '" . $valor . "' and status = 1");
        $row_novedad_existente = mysqli_fetch_array($sql_novedad_existente);
        $numrows_novedad_existente = $row_novedad_existente['numrows'];
    } else {
        $numrows_novedad_existente = 0;
    }


    $pagos = 0;
    if ($aplica_en == "R") {
        $sql_rol = mysqli_query($con, "SELECT det.id as id FROM rolespago as rol INNER JOIN detalle_rolespago as det ON
      det.id_rol=rol.id WHERE rol.id_empresa='" . $id_empresa . "' and rol.mes_ano='" . $mes_ano . "' 
      and det.id_empleado= '" . $id_empleado . "' and rol.status = 1 ");
        $row_rol = mysqli_fetch_array($sql_rol);
        $id_detalle_rol = isset($row_rol['id']) ? $row_rol['id'] : "0";
        if ($id_detalle_rol > 0) {
            $sql_egresos = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as pagos FROM detalle_ingresos_egresos WHERE tipo_ing_egr='CCXRPP' and codigo_documento_cv = concat('ROL_PAGOS', $id_detalle_rol) group by codigo_documento_cv ");
            $row_egresos = mysqli_fetch_array($sql_egresos);
            $pagos = isset($row_egresos['pagos']) ? $row_egresos['pagos'] : 0;
        }
    } else {
        $sql_quincena = mysqli_query($con, "SELECT det.id as id FROM quincenas as qui INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
       WHERE qui.id_empresa='" . $id_empresa . "' and qui.mes_ano='" . $mes_ano . "' and qui.status =1 and det.id_empleado='" . $id_empleado . "'");
        $row_quincena = mysqli_fetch_array($sql_quincena);
        $id_detalle_quincena = isset($row_quincena['id']) ? $row_quincena['id'] : "0";

        if ($id_detalle_quincena > 0) {
            $sql_egresos = mysqli_query($con, "SELECT round(sum(valor_ing_egr),2) as pagos FROM detalle_ingresos_egresos WHERE tipo_ing_egr='CCXQPP' and codigo_documento_cv = concat('QUINCENA', $id_detalle_quincena) group by codigo_documento_cv ");
            $row_egresos = mysqli_fetch_array($sql_egresos);
            $pagos = isset($row_egresos['pagos']) ? $row_egresos['pagos'] : 0;
        }
    }

    if (empty($id_empleado)) {
        echo "<script>
            $.notify('Seleccione un empleado','error');
            </script>";
    } else if (empty($id_novedad)) {
        echo "<script>
        $.notify('Seleccione una novedad','error');
        </script>";
    } else if (empty($fecha_novedad)) {
        echo "<script>
        $.notify('Ingrese una fecha de la novedad','error');
        </script>";
    } else if (!date($fecha_novedad)) {
        echo "<script>
        $.notify('Ingrese una fecha de la novedad correcta','error');
        </script>";
    } else if ($id_novedad == 14 && empty($motivo_salida)) {
        echo "<script>
        $.notify('Seleccione un motivo de salida','error');
        </script>";
    } else if ($numrows_salida > 0) {
        echo "<script>
        $.notify('Ya existe un registro de salida en este período, elimine y cree uno nuevo','error');
        </script>";
    } else if ($numrows_novedad_existente > 0) {
        echo "<script>
        $.notify('Ya existe un registro similar de esta novedad en este período','error');
        </script>";
    } else if ($pagos > 0) {
        echo "<script>
        $.notify('$sms_aplica_en','error');
        </script>";
    } else {
        if (empty($id_registro)) {
            $guarda_novedad = mysqli_query($con, "INSERT INTO novedades (id_empleado,
                                                                        id_novedad,
                                                                        id_empresa,
                                                                        id_usuario,
                                                                        fecha_novedad,
                                                                        mes_ano,
                                                                        valor,
                                                                        detalle,
                                                                        iess,
                                                                        motivo_salida,
                                                                        aplica_en)
                                                                            VALUES ('" . $id_empleado . "',
                                                                                    '" . $id_novedad . "',
                                                                                    '" . $id_empresa . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . date("Y/m/d", strtotime($fecha_novedad)) . "',
                                                                                    '" . $mes_ano . "',
                                                                                    '" . $valor . "',
                                                                                    '" . $detalle . "',
                                                                                    '" . $iess . "',
                                                                                    '" . $motivo_salida . "',
                                                                                    '" . $aplica_en . "')");

            if ($guarda_novedad) {
                if ($id_novedad == 14) {
                    aviso_salida($con, $id_empleado, $id_empresa, date("Y/m/d", strtotime($fecha_novedad)));
                }
                echo "<script>
                $.notify('Novedad registrada','success');
                document.querySelector('#formNovedades').reset();
                load(1);
                </script>";
            } else {
                echo "<script>
                $.notify('No es posible registrar la novedad, intente de nuevo','error');
                </script>";
            }
        } else {
            //modificar el novedad
            $update_novedad = mysqli_query($con, "UPDATE novedades SET id_empleado='" . $id_empleado . "',
                                                                            id_novedad='" . $id_novedad . "',
                                                                            id_usuario='" . $id_usuario . "',
                                                                            fecha_novedad='" . date("Y/m/d", strtotime($fecha_novedad)) . "',
                                                                            mes_ano='" . $mes_ano . "',
                                                                            valor='" . $valor . "',
                                                                            detalle='" . $detalle . "',
                                                                            iess='" . $iess . "',
                                                                            motivo_salida='" . $motivo_salida . "',
                                                                            aplica_en='" . $aplica_en . "' 
                                                                            WHERE id='" . $id_registro . "'");
            if ($update_novedad) {
                if ($id_novedad == 14) {
                    aviso_salida($con, $id_empleado, $id_empresa, date("Y/m/d", strtotime($fecha_novedad)));
                }
                echo "<script>
                    $.notify('Novedad actualizada','success');
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

function aviso_salida($con, $idEmpleado, $idEmpresa, $fechaSalida)
{
    $sql_sueldos = mysqli_query($con, "UPDATE sueldos SET fecha_salida= '" . $fechaSalida . "' WHERE id_empleado = '" . $idEmpleado . "' and id_empresa= '" . $idEmpresa . "' and status=1 ");
    // $sql_empleados = mysqli_query($con, "UPDATE empleados SET status='2' WHERE id = '" . $idEmpleado . "' and id_empresa= '" . $idEmpresa . "' and status=1 ");
}

?>