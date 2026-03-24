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

if ($action == 'nuevo_presupuesto') {
    nuevo_presupuesto();
}

function nuevo_presupuesto()
{
    unset($_SESSION['arrayCuentasPresupuesto']);
}

if ($action == 'agrega_cuenta') {
    $id_cuenta = $_POST['id_cuenta'];
    $valor = $_POST['valor'];
    $codigo = $_POST['codigo'];
    $cuenta = $_POST['cuenta'];

    if (!empty($valor) && !empty($cuenta)) {
        $arrayCuentasPresupuesto = array();
        $arrayDatos = array('id' => rand(5, 500), 'id_cuenta' => $id_cuenta, 'codigo' => $codigo, 'cuenta' => $cuenta, 'valor' => $valor);
        if (isset($_SESSION['arrayCuentasPresupuesto'])) {
            $arrayCuentasPresupuesto = $_SESSION['arrayCuentasPresupuesto'];
            array_push($arrayCuentasPresupuesto, $arrayDatos);
            $_SESSION['arrayCuentasPresupuesto'] = $arrayCuentasPresupuesto;
        } else {
            array_push($arrayCuentasPresupuesto, $arrayDatos);
            $_SESSION['arrayCuentasPresupuesto'] = $arrayCuentasPresupuesto;
        }
    } else {
        echo "<script>
		$.notify('Ingrese cuenta y valor','error');
		</script>";
    }
    informacion_cuentas_presupuesto();
}

function informacion_cuentas_presupuesto()
{
?>
    <div class="panel panel-info" style="padding: 0px; margin-bottom: 0px;">
        <div class="table-responsive">
            <table class="table table-bordered" style="padding: 0px; margin-bottom: 0px;">
                <tr class="info">
                    <th style="padding: 2px;" class="text-left">Código</th>
                    <th style="padding: 2px;" class="text-left">Cuenta</th>
                    <th style="padding: 2px;" class="text-right">Valor</th>
                    <th style="padding: 2px;" class="text-left">Eliminar</th>
                </tr>
                <?php
                if (isset($_SESSION['arrayCuentasPresupuesto'])) {
                    foreach ($_SESSION['arrayCuentasPresupuesto'] as $det) {
                        $codigo = $det['codigo'];
                        $valor = $det['valor'];
                        $cuenta = $det['cuenta'];
                        $id = $det['id'];
                ?>
                        <tr>
                            <td style="padding: 2px;" class="col-xs-2"><?php echo $codigo; ?></td>
                            <td style="padding: 2px;" class="col-xs-7"><?php echo $cuenta; ?></td>
                            <td style="padding: 2px;" class="col-xs-2 text-right"><?php echo number_format($valor, 2, '.', ''); ?></td>
                            <td style="padding: 2px;" class="col-xs-1 text-center"><button type="button" style="height:17px;" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_cuenta('<?php echo $id; ?>','<?php echo $valor; ?>')"><span class="glyphicon glyphicon-remove"></span></button></td>
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


if ($action == 'eliminar_cuenta') {
    $intid = $_POST['id'];
    $arrData = $_SESSION['arrayCuentasPresupuesto'];
    for ($i = 0; $i < count($arrData); $i++) {
        if ($arrData[$i]['id'] == $intid) {
            unset($arrData[$i]);
            echo "<script>
            $.notify('Eliminado','error');
            </script>";
        }
    }
    sort($arrData); //para reordenar el array
    $_SESSION['arrayCuentasPresupuesto'] = $arrData;
    informacion_cuentas_presupuesto();
}

if ($action == 'muestra_cuentas_editar') {
    unset($_SESSION['arrayCuentasPresupuesto']);
    $id_presupuesto = $_POST['id_presupuesto'];
    $info_cuentas = mysqli_query($con, "SELECT plan.id_cuenta as id_cuenta, plan.codigo_cuenta as codigo, 
     plan.nombre_cuenta as cuenta, det.valor as valor 
     FROM detalle_presupuesto as det INNER JOIN plan_cuentas as plan on plan.id_cuenta=det.id_cuenta
     WHERE det.id_pre = '" . $id_presupuesto . "' ");
    $arrayInfoAdicional = array();
    while ($row_info_adicional = mysqli_fetch_array($info_cuentas)) {
        $arrayDatos = array('id' => rand(5, 5000), 'id_cuenta' => $row_info_adicional['id_cuenta'], 
        'codigo' => $row_info_adicional['codigo'], 'cuenta' => $row_info_adicional['cuenta'], 
        'valor' => $row_info_adicional['valor']);
        array_push($arrayInfoAdicional, $arrayDatos);
    }
    $_SESSION['arrayCuentasPresupuesto'] = $arrayInfoAdicional;
    informacion_cuentas_presupuesto();
}


if ($action == 'datos_editar_presupuesto') {
    $id_presupuesto = $_GET['id_presupuesto'];
    $sql = mysqli_query($con, "SELECT enc.proyecto as proyecto, round(sum(det.valor),2) as total, 
    enc.status as status, enc.fecha_inicio as fecha_inicio, enc.fecha_fin as fecha_fin 
    FROM encabezado_presupuesto as enc INNER JOIN detalle_presupuesto as det ON det.id_pre=enc.id 
    WHERE enc.ruc_empresa='" . $ruc_empresa . "' and enc.status !=0 and enc.id='" . $id_presupuesto . "' group by enc.id");
    $presu = mysqli_fetch_array($sql);
    $data = array(
        'proyecto' => $presu['proyecto'],
        'status' => $presu['status'],
        'desde' => date('d-m-Y', strtotime($presu['fecha_inicio'])),
        'hasta' => date('d-m-Y', strtotime($presu['fecha_fin'])),
        'total' => number_format($presu['total'], 2, '.', ''));
    if ($sql) {
        $arrResponse = array("status" => true, "data" => $data);
    } else {
        $arrResponse = array("status" => false, "msg" => 'Datos no encontrados');
    }
    echo json_encode($arrResponse, JSON_UNESCAPED_UNICODE); //, JSON_UNESCAPED_UNICODE
    die();
}


if ($action == 'eliminar_presupuesto') {
    $id_presupuesto = intval($_POST['id_presupuesto']);
        if ($deleteuno = mysqli_query($con, "UPDATE encabezado_presupuesto SET status ='0' WHERE id='" . $id_presupuesto . "'")) {
            echo "<script>$.notify('Presupuesto eliminado.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
}

//buscar pre
if ($action == 'buscar_presupuestos') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('proyecto', 'valor', 'fecha_inicio', 'fecha_fin'); //Columnas de busqueda
    $sTable = "encabezado_presupuesto";
    $sWhere = "WHERE ruc_empresa='" . $ruc_empresa . "' and status !=0";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (ruc_empresa='" . $ruc_empresa . "' and status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND ruc_empresa='" . $ruc_empresa . "' and status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, "AND ruc_empresa='" . $ruc_empresa . "' and status !=0 ", -3);
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
    $reload = '../.php';
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
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("proyecto");'>Proyecto</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_inicio");'>Desde</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_fin");'>Hasta</button></th>
                        <th style="padding: 0px;" class="text-right"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("valor");'>Valor</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $proyecto = strtoupper($row['proyecto']);
                        $fecha_inicio = $row['fecha_inicio'];
                        $fecha_fin = $row['fecha_fin'];
                        $status = $row['status'];
                        $valor = $row['valor'];
                    ?>
                        <tr>
                            <td><?php echo $proyecto; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($fecha_inicio)); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($fecha_fin)); ?></td>
                            <td><?php echo $valor; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-info'>En ejecución</span>" : "<span class='label label-success'>Ejecutado</span>"; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'presupuestos')['u'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Editar" onclick="editar_presupuesto('<?php echo $id_registro; ?>');" data-toggle="modal" data-target="#modalPresupuestos"><i class="glyphicon glyphicon-edit"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'presupuestos')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_presupuesto('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="6"><span class="pull-right">
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

//guardar o editar presu
if ($action == 'guardar_presupuesto') {
    $id_presupuesto = intval($_POST['id_presupuesto']);
    $status = $_POST['status'];
    $desde = $_POST['desde'];
    $hasta = $_POST['hasta'];
    $total = $_POST['total'];
    $proyecto = strClean($_POST['proyecto']);

    if (empty($proyecto)) {
        echo "<script>
            $.notify('Ingrese un nombre del presupuesto proyecto','error');
            </script>";
    } else if (!date($desde)) {
        echo "<script>
        $.notify('Ingrese una fecha inicial','error');
        </script>";
    } else if (!date($hasta)) {
        echo "<script>
        $.notify('Ingrese una fecha final','error');
        </script>";
    } else {
        if (empty($id_presupuesto)) {
            $busca_presu = mysqli_query($con, "SELECT * FROM encabezado_presupuesto WHERE proyecto = '".$proyecto."' and status !=0 ");
            $count = mysqli_num_rows($busca_presu);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe un presupuesto con este nombre de proyecto','error');
                </script>";
            }else{
            $guarda_presupuesto = mysqli_query($con, "INSERT INTO encabezado_presupuesto (ruc_empresa,
                                                                        proyecto,
                                                                        valor,
                                                                        fecha_inicio,
                                                                        fecha_fin,
                                                                        id_usuario)
                                                                        VALUES ('" . $ruc_empresa . "',
                                                                                '" . $proyecto . "',
                                                                                '" . number_format($total, 2, '.', '') . "',
                                                                                '" . date("Y/m/d", strtotime($desde)) . "',
                                                                                '" . date("Y/m/d", strtotime($hasta)) . "',
                                                                                '" . $id_usuario . "')");

            if ($guarda_presupuesto) {
                $lastid = mysqli_insert_id($con);
                guarda_detalle_cuentas($con, $lastid);
                echo "<script>
                $.notify('Presupuesto registrado','success');
                document.querySelector('#formPresupuestos').reset();
                load(1);
                setTimeout(function () {location.reload()}, 1000);
                </script>";
            } else {
                echo "<script>
                $.notify('No es posible registrar, intente de nuevo','error');
                </script>";
            }
        }
        } else {
            //modificar el busca_presu
            $busca_presu = mysqli_query($con, "SELECT * FROM encabezado_presupuesto WHERE proyecto = '".$proyecto."' and id != '".$id_presupuesto."' and status !=0 ");
            $count = mysqli_num_rows($busca_presu);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe un presupuesto vigente con este nombre','error');
                </script>";
            }else{
            $update_presupuesto = mysqli_query($con, "UPDATE encabezado_presupuesto SET proyecto='" . $proyecto . "',
                                                                    valor='" . number_format($total, 2, '.', '') . "',
                                                                    status='" . $status . "',
                                                                    fecha_inicio='" . date("Y/m/d", strtotime($desde)) . "',
                                                                    fecha_fin='" . date("Y/m/d", strtotime($hasta)) . "',
                                                                    id_usuario='" . $id_usuario . "' WHERE id = '".$id_presupuesto."' ");
            if ($update_presupuesto) {
                guarda_detalle_cuentas($con, $id_presupuesto);
                echo "<script>
                    $.notify('Presupuesto actualizado','success');
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

function guarda_detalle_cuentas($con, $id_presupuesto)
{
	if (isset($_SESSION['arrayCuentasPresupuesto'])) {
        $query_delete = mysqli_query($con, "DELETE FROM detalle_presupuesto WHERE id_pre='".$id_presupuesto."'");
		foreach ($_SESSION['arrayCuentasPresupuesto'] as $det) {
			$id_cuenta = $det['id_cuenta'];
            $codigo = $det['codigo'];
			$valor = number_format($det['valor'], 2, '.', '');
			$query_guarda = mysqli_query($con, "INSERT INTO detalle_presupuesto (id_pre, id_cuenta, codigo_cuenta, valor) 
            VALUES ('" . $id_presupuesto . "', '" . $id_cuenta . "', '" . $codigo . "', '" . $valor . "')");
		}
	}
	
}
?>