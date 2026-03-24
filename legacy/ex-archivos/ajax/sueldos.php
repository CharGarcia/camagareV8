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

if ($action == 'nuevo_sueldo') {
    nuevo_sueldo();
}

function nuevo_sueldo()
{
    unset($_SESSION['arrayIngresosDescuentos']);
}

if ($action == 'agrega_ingreso_descuento') {
    $tipo = $_POST['tipo'];
    $valor = $_POST['valor'];
    $detalle = strClean($_POST['detalle']);
    $iess = $_POST['iess'];

    if (!empty($valor) && !empty($detalle)) {
        $arrayIngresosDescuentos = array();
        $arrayDatos = array('id' => rand(5, 500), 'tipo' => $tipo, 'valor' => $valor, 'detalle' => $detalle, 'iess' => $iess);
        if (isset($_SESSION['arrayIngresosDescuentos'])) {
            $arrayIngresosDescuentos = $_SESSION['arrayIngresosDescuentos'];
            array_push($arrayIngresosDescuentos, $arrayDatos);
            $_SESSION['arrayIngresosDescuentos'] = $arrayIngresosDescuentos;
        } else {
            array_push($arrayIngresosDescuentos, $arrayDatos);
            $_SESSION['arrayIngresosDescuentos'] = $arrayIngresosDescuentos;
        }
    } else {
        echo "<script>
		$.notify('Ingrese valor y detalle','error');
		</script>";
    }
    informacion_ingresos_descuentos();
}

function informacion_ingresos_descuentos()
{
?>

    <div class="panel panel-info" style="padding: 0px; margin-bottom: 0px;">
        <div class="table-responsive">
            <table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
                <tr class="info">
                    <th style="padding: 2px;" class="text-left">Tipo</th>
                    <th style="padding: 2px;" class="text-right">Valor</th>
                    <th style="padding: 2px;" class="text-left">Detalle</th>
                    <th style="padding: 2px;" class="text-center">IESS</th>
                    <th style="padding: 2px;" class="text-left">Eliminar</th>
                </tr>
                <?php
                if (isset($_SESSION['arrayIngresosDescuentos'])) {
                    foreach ($_SESSION['arrayIngresosDescuentos'] as $det) {
                        $tipo = $det['tipo'] == 1 ? "Ingreso" : "Descuento";
                        $valor = number_format($det['valor'], 2, '.', '');
                        $detalle = $det['detalle'];
                        $iess = $det['iess'] == 0 ? "NO" : "SI";
                        $id = $det['id'];
                ?>
                        <tr>
                            <td style="padding: 2px;" class="col-xs-2"><?php echo $tipo; ?></td>
                            <td style="padding: 2px;" class="col-xs-2 text-right"><?php echo $valor; ?></td>
                            <td style="padding: 2px;" class="col-xs-6"><?php echo $detalle; ?></td>
                            <td style="padding: 2px;" class="col-xs-2 text-center"><?php echo $iess; ?></td>
                            <td style="padding: 2px;" class="col-xs-1 text-center"><button type="button" style="height:17px;" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_ingreso_descuento('<?php echo $id; ?>')"><span class="glyphicon glyphicon-remove"></span></button></td>
                        </tr>
                <?php
                    }
                }
                ?>
            </table>
        </div>
    </div>
    <?php
}


if ($action == 'eliminar_ingreso_descuento') {
    $intid = $_POST['id'];
    $arrData = $_SESSION['arrayIngresosDescuentos'];
    for ($i = 0; $i < count($arrData); $i++) {
        if ($arrData[$i]['id'] == $intid) {
            unset($arrData[$i]);
            echo "<script>
            $.notify('Eliminado','error');
            </script>";
        }
    }
    sort($arrData); //para reordenar el array
    $_SESSION['arrayIngresosDescuentos'] = $arrData;
    informacion_ingresos_descuentos();
}

if ($action == 'muestra_ingresos_descuentos_editar') {
    unset($_SESSION['arrayIngresosDescuentos']);
    $id_sueldo = $_POST['id_sueldo'];
    //para informacion de los descuentos fijos
    $info_adicional = mysqli_query($con, "SELECT * FROM detalle_sueldos WHERE id_sueldo = '" . $id_sueldo . "' ");
    $arrayInfoAdicional = array();
    while ($row_info_adicional = mysqli_fetch_array($info_adicional)) {
        $arrayDatos = array('id' => rand(5, 500), 'tipo' => $row_info_adicional['tipo'], 'valor' => $row_info_adicional['valor'], 'detalle' => $row_info_adicional['detalle'], 'iess' => $row_info_adicional['aporta_al_iess'] == "false" ? "0" : "1");
        array_push($arrayInfoAdicional, $arrayDatos);
    }
    $_SESSION['arrayIngresosDescuentos'] = $arrayInfoAdicional;
    informacion_ingresos_descuentos();
}



if ($action == 'busca_porcentaje_aportes') {
    $sql = mysqli_query($con, "SELECT * FROM salarios WHERE ano='" . date('Y') . "' and status =1 ");
    $aportes = mysqli_fetch_array($sql);
    $data = array('ap_personal' => $aportes['aporte_personal'], 'ap_patronal' => $aportes['aporte_patronal'], 'sbu' => $aportes['sbu']);
    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }
    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
    die();
}

if ($action == 'datos_editar_sueldo') {
    $id_sueldo = $_GET['id_sueldo'];
    //para informacion del sueldo
    $sql = mysqli_query($con, "SELECT sue.id_empleado as id_empleado,
    emp.nombres_apellidos as nombres_apellidos, sue.id as id, sue.fondo_reserva as fondo_reserva,
    sue.fecha_ingreso as fecha_ingreso, sue.fecha_salida as fecha_salida,
    sue.aporta_al_iess as aporta_al_iess, sue.status as status, sue.decimo_tercero_mensual as decimo_tercero_mensual,
    sue.decimo_cuarto_mensual as decimo_cuarto_mensual, sue.departamento as departamento, sue.cargo_empresa as cargo_empresa,
    sue.ap_personal as ap_personal, sue.ap_patronal as ap_patronal, sue.cargo_iess as cargo_iess, sue.sueldo as sueldo,
    sue.quincena as quincena, sue.region as region FROM sueldos as sue INNER JOIN empleados as emp ON emp.id=sue.id_empleado 
    WHERE sue.id_empresa='" . $id_empresa . "' and sue.status !=0 and sue.id='" . $id_sueldo . "'");
    $sueldos = mysqli_fetch_array($sql);
    $data = array(
        'id_empleado' => $sueldos['id_empleado'], 'empleado' => $sueldos['nombres_apellidos'],
        'id_sueldo' => $sueldos['id'],
        'fondo_reserva' => $sueldos['fondo_reserva'],
        'fecha_ingreso' => date('d-m-Y', strtotime($sueldos['fecha_ingreso'])),
        'fecha_salida' => $sueldos['fecha_salida'] > date('d-m-Y', strtotime('01-01-2000')) ? date('d-m-Y', strtotime($sueldos['fecha_salida'])) : "00-00-0000",
        'aporta_al_iess' => $sueldos['aporta_al_iess'],
        'status' => $sueldos['status'],
        'decimo_tercero_mensual' => $sueldos['decimo_tercero_mensual'],
        'decimo_cuarto_mensual' => $sueldos['decimo_cuarto_mensual'],
        'departamento' => $sueldos['departamento'],
        'cargo_empresa' => $sueldos['cargo_empresa'],
        'ap_personal' => $sueldos['ap_personal'],
        'ap_patronal' => $sueldos['ap_patronal'],
        'cargo_iess' => $sueldos['cargo_iess'],
        'sueldo' => number_format($sueldos['sueldo'], 2, '.', ''),
        'quincena' => number_format($sueldos['quincena'], 2, '.', ''),
        'region' => $sueldos['region']
    );
    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }
    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
    die();
}


if ($action == 'eliminar_sueldo') {
    $id_sueldo = intval($_POST['id_sueldo']);
    if ($deleteuno = mysqli_query($con, "UPDATE sueldos SET status ='0' WHERE id='" . $id_sueldo . "'")) {
        echo "<script>$.notify('Novedad eliminada.','success')</script>";
    } else {
        echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
    }
}

//buscar sueldos
if ($action == 'buscar_sueldos') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('emp.nombres_apellidos', 'sue.sueldo', 'sue.quincena'); //Columnas de busqueda
    $sTable = "sueldos as sue INNER JOIN empleados as emp ON emp.id=sue.id_empleado";
    $sWhere = "WHERE sue.id_empresa='" . $id_empresa . "' and sue.status !=0";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (sue.id_empresa='" . $id_empresa . "' and sue.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND sue.id_empresa='" . $id_empresa . "' and sue.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, "AND sue.id_empresa='" . $id_empresa . "' and sue.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by $ordenado $por";

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
    $reload = '../sueldos.php';
    //main query to fetch the data
    $sql = "SELECT sue.id as id, emp.nombres_apellidos as nombres_apellidos, sue.fecha_ingreso as fecha_ingreso,
    sue.sueldo as sueldo, sue.quincena as quincena, sue.aporta_al_iess as aporta_al_iess,
    sue.decimo_tercero_mensual as decimo_tercero_mensual, sue.decimo_cuarto_mensual as decimo_cuarto_mensual,
    sue.fondo_reserva as fondo_reserva, sue.status as status, sue.ap_personal as ap_personal, sue.ap_patronal as ap_patronal FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombres_apellidos");'>Nombres_apellidos</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_ingreso");'>Ingreso</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("sueldo");'>Sueldo</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("quincena");'>Quincena</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("aporta_al_iess");'>IESS</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("decimo_tercero_mensual");'>D.Tercero</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("decimo_cuarto_mensual");'>D. Cuarto</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fondo_reserva");'>F. Reserva</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ap_personal");'>A. Personal</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ap_patronal");'>A. Patronal</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $empleado = strtoupper($row['nombres_apellidos']);
                        $fecha_ingreso = $row['fecha_ingreso'];
                        $sueldo = $row['sueldo'];
                        $quincena = $row['quincena'];
                        $iess = $row['aporta_al_iess'];
                        $decimo_tercero_mensual = $row['decimo_tercero_mensual'];
                        $decimo_cuarto_mensual = $row['decimo_cuarto_mensual'];
                        $fondo_reserva = $row['fondo_reserva'];
                        $status = $row['status'];
                        $ap_personal = $row['ap_personal'];
                        $ap_patronal = $row['ap_patronal'];

                        if ($decimo_tercero_mensual == 0) {
                            $decimo_tercero_mensual = '<span class="label label-info">Acumula</span>';
                        } else {
                            $decimo_tercero_mensual = '<span class="label label-success">Pago mensual</span>';
                        }

                        if ($decimo_cuarto_mensual == 0) {
                            $decimo_cuarto_mensual = '<span class="label label-info">Acumula</span>';
                        } else {
                            $decimo_cuarto_mensual = '<span class="label label-success">Pago mensual</span>';
                        }

                        if ($fondo_reserva == 1) {
                            $fondo_reserva = '<span class="label label-info">Se paga en rol mensual</span>';
                        } else if ($fondo_reserva == 2) {
                            $fondo_reserva = '<span class="label label-info">Se paga al IESS mediante planilla</span>';
                        } else if ($fondo_reserva == 3) {
                            $fondo_reserva = '<span class="label label-info">Se paga al partir del año</span>';
                        } else {
                            $fondo_reserva = '<span class="label label-danger">No se paga</span>';
                        }

                        if ($iess == 1) {
                            $iess = '<span class="label label-success">SI</span>';
                        } else {
                            $iess = '<span class="label label-danger">NO</span>';
                        }

                    ?>
                        <tr>
                            <td><?php echo $empleado; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($fecha_ingreso)); ?></td>
                            <td class="text-right"><?php echo $sueldo; ?></td>
                            <td class="text-right"><?php echo $quincena; ?></td>
                            <td class="text-center"><?php echo $iess; ?></td>
                            <td class="text-center"><?php echo $decimo_tercero_mensual; ?></td>
                            <td class="text-center"><?php echo $decimo_cuarto_mensual; ?></td>
                            <td class="text-left"><?php echo $fondo_reserva; ?></td>
                            <td class="text-left"><?php echo $ap_personal . "%"; ?></td>
                            <td class="text-left"><?php echo $ap_patronal . "%"; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'sueldos')['u'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Editar sueldo" onclick="editar_sueldo('<?php echo $id_registro; ?>');" data-toggle="modal" data-target="#modalSueldos"><i class="glyphicon glyphicon-edit"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'sueldos')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar sueldo" onclick="eliminar_sueldo('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="13"><span class="pull-right">
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

//guardar o editar sueldos
if ($action == 'guardar_sueldo') {
    $id_sueldo = intval($_POST['id_sueldo']);
    $id_empleado = intval($_POST['id_empleado']);
    $fr = intval($_POST['fr']);
    $iess = $_POST['iess'];
    $status = $_POST['status'];
    $decimotercero = $_POST['decimotercero'];
    $decimocuarto = $_POST['decimocuarto'];
    $departamento = $_POST['departamento'];
    $fecha_ingreso = $_POST['fecha_ingreso'];

    if (empty($_POST['fecha_salida'])) {
        $fecha_salida = null;
    } else {
        $fecha_salida = date("Y/m/d", strtotime($_POST['fecha_salida']));
    }
    $cargo = strClean($_POST['cargo']);
    $ap_personal = $_POST['ap_personal'];
    $ap_patronal = $_POST['ap_patronal'];
    $codigo_iess = strClean($_POST['codigo_iess']);
    $sueldo = $_POST['sueldo'];
    $quincena = $_POST['quincena'];
    $region = intval($_POST['region']);

    if (empty($id_empleado)) {
        echo "<script>
            $.notify('Seleccione un empleado','error');
            </script>";
    } else if ($fr == "0") {
        echo "<script>
        $.notify('Seleccione un tipo de fondo de reserva','error');
        </script>";
    } else if (!date($fecha_ingreso)) {
        echo "<script>
        $.notify('Ingrese una fecha de ingreso correcta','error');
        </script>";
    } else if (empty($cargo)) {
        echo "<script>
        $.notify('Ingrese cargo del empleado','error');
        </script>";
    } else if ($sueldo <= 0) {
        echo "<script>
        $.notify('Ingrese sueldo','error');
        </script>";
    } else if ($ap_personal <= 0) {
        echo "<script>
        $.notify('Ingrese aporte personal','error');
        </script>";
    } else if ($ap_patronal <= 0) {
        echo "<script>
        $.notify('Ingrese aporte patronal','error');
        </script>";
    } else {
        if (empty($id_sueldo)) {
            $busca_sueldo = mysqli_query($con, "SELECT * FROM sueldos WHERE id_empleado = '" . $id_empleado . "' and status !=0 ");
            $count = mysqli_num_rows($busca_sueldo);
            if ($count > 0) {
                echo "<script>
                $.notify('El empleado tiene un sueldo vigente','error');
                </script>";
            } else {
                $guarda_sueldo = mysqli_query($con, "INSERT INTO sueldos (id_empresa,
                                                                        id_empleado,
                                                                        sueldo,
                                                                        decimo_tercero_mensual,
                                                                        decimo_cuarto_mensual,
                                                                        fondo_reserva,
                                                                        aporta_al_iess,
                                                                        id_usuario,
                                                                        quincena,
                                                                        fecha_ingreso,
                                                                        cargo_empresa,
                                                                        cargo_iess,
                                                                        departamento,
                                                                        region,
                                                                        ap_personal,
                                                                        ap_patronal)
                                                                            VALUES ('" . $id_empresa . "',
                                                                                    '" . $id_empleado . "',
                                                                                    '" . $sueldo . "',
                                                                                    '" . $decimotercero . "',
                                                                                    '" . $decimocuarto . "',
                                                                                    '" . $fr . "',
                                                                                    '" . $iess . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $quincena . "',
                                                                                    '" . date("Y/m/d", strtotime($fecha_ingreso)) . "',
                                                                                    '" . $cargo . "',
                                                                                    '" . $codigo_iess . "',
                                                                                    '" . $departamento . "',
                                                                                    '" . $region . "',
                                                                                    '" . $ap_personal . "',
                                                                                    '" . $ap_patronal . "')");

                if ($guarda_sueldo) {
                    $lastid = mysqli_insert_id($con);
                    guarda_ingresos_descuentos_fijos($con, $lastid);
                    echo "<script>
                $.notify('Sueldo registrado','success');
                document.querySelector('#formSueldos').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No es posible registrar la novedad, intente de nuevo','error');
                </script>";
                }
            }
        } else {
            //modificar el sueldo
            $busca_sueldo = mysqli_query($con, "SELECT * FROM sueldos WHERE id_empleado = '" . $id_empleado . "' and id != '" . $id_sueldo . "' and status !=0 ");
            $count = mysqli_num_rows($busca_sueldo);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe un sueldo vigente con este empleado','error');
                </script>";
            } else {
                $update_sueldo = mysqli_query($con, "UPDATE sueldos SET sueldo='" . $sueldo . "',
                                                                    decimo_tercero_mensual='" . $decimotercero . "',
                                                                    decimo_cuarto_mensual='" . $decimocuarto . "',
                                                                    fondo_reserva='" . $fr . "',
                                                                    aporta_al_iess='" . $iess . "',
                                                                    status='" . $status . "',
                                                                    id_usuario='" . $id_usuario . "',
                                                                    quincena='" . $quincena . "',
                                                                    fecha_ingreso='" . date("Y/m/d", strtotime($fecha_ingreso)) . "',
                                                                    fecha_salida='" . $fecha_salida . "',
                                                                    cargo_empresa='" . $cargo . "',
                                                                    cargo_iess='" . $codigo_iess . "',
                                                                    departamento  ='" . $departamento  . "',
                                                                    region ='" . $region  . "',
                                                                    ap_personal ='" . $ap_personal  . "',
                                                                    ap_patronal ='" . $ap_patronal  . "'
                                                                            WHERE id='" . $id_sueldo . "'");
                if ($update_sueldo) {
                    guarda_ingresos_descuentos_fijos($con, $id_sueldo);
                    echo "<script>
                    $.notify('Sueldo actualizado','success');
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
}

function guarda_ingresos_descuentos_fijos($con, $id_sueldo)
{
    if (isset($_SESSION['arrayIngresosDescuentos'])) {
        $query_delete = mysqli_query($con, "DELETE FROM detalle_sueldos WHERE id_sueldo='" . $id_sueldo . "'");
        foreach ($_SESSION['arrayIngresosDescuentos'] as $det) {
            $tipo = $det['tipo'];
            $valor = number_format($det['valor'], 2, '.', '');
            $iess = $det['iess'] == "0" ? "false" : "true";
            $detalle = strClean($det['detalle']);
            $query_guarda = mysqli_query($con, "INSERT INTO detalle_sueldos (id_sueldo, tipo, valor, aporta_al_iess, detalle) VALUES ('" . $id_sueldo . "', '" . $tipo . "', '" . $valor . "', '" . $iess . "', '" . $detalle . "')");
        }
        // return $query_guarda;
    }
}
?>