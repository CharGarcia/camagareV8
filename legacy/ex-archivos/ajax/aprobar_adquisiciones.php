<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");

$codigo_unico = codigo_aleatorio(20);
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


//mostrar compras aprobadas
if ($action == 'mostrar_compras_aprobadas') {
    $id = $_GET['id'];
    $sql = mysqli_query($con, "SELECT com.codigo_documento as codigo_documento, com.id_encabezado_compra as id, aut.comprobante as documento, pro.razon_social as proveedor,
    com.fecha_compra as fecha_compra, round(com.total_compra,2) as total_compra 
    FROM detalle_aprobaciones_adquisiciones as det
    INNER JOIN encabezado_compra as com ON com.id_encabezado_compra=det.id_documento
    INNER JOIN proveedores as pro ON pro.id_proveedor=com.id_proveedor
    INNER JOIN comprobantes_autorizados as aut ON
    aut.id_comprobante = com.id_comprobante WHERE det.id_aprobacion='" . $id . "'  order by com.fecha_compra desc, proveedor asc ");

    if ($sql->num_rows > 0) {
?>
        <div class="panel panel-info" style="height: 380px; overflow-y: auto; margin-bottom: 5px;">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Documento</th>
                        <th>Detalle</th>
                        <th>Total</th>
                    </tr>
                    <?php
                    $suma_total = 0;
                    while ($row = mysqli_fetch_array($sql)) {
                        $id = $row['id'];
                        $codigo_documento = $row['codigo_documento'];
                        $fecha_compra = date("d-m-Y", strtotime($row['fecha_compra']));
                        $proveedor = $row['proveedor'];
                        $documento = $row['documento'];
                        $total = $row['total_compra'];
                        $suma_total += $total;
                    ?>
                        <tr>
                            <td><?php echo $fecha_compra; ?></td>
                            <td><?php echo $proveedor; ?></td>
                            <td><?php echo $documento; ?></td>
                            <td><a href="#" class="btn btn-info btn-xs" onclick="detalle_compras('<?php echo $codigo_documento; ?>')" title="Detalle documento" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a></td>
                            <td class="text-right"><?php echo number_format($total, 2, '.', ''); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr class="info">
                        <th colspan="4" class='text-right'>Totales</th>
                        <th class='text-right'><?php echo number_format($suma_total, 2, '.', ''); ?></th>
                    </tr>
                </table>
            </div>
        </div>
    <?php
    } else {
    ?>
        <div class="alert alert-danger" role="alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php
            echo "No hay documentos para mostrar.";
            ?>
        </div>
    <?php

    }
}

if ($action == 'eliminar_aprobacion') {
    $id = intval($_POST['id']);
    $update = mysqli_query($con, "UPDATE aprobaciones_adquisiciones SET status = '0', id_usuario_edit='" . $id_usuario . "', date_edit = '" . date("Y-m-d H:i:s") . "' WHERE id='" . $id . "' and status='1'");
    if ($update) {
        $delete = mysqli_query($con, "DELETE FROM detalle_aprobaciones_adquisiciones WHERE id_aprobacion='" . $id . "'");
        echo "<script>$.notify('Registro anulado.','success')</script>";
    } else {
        echo "<script>$.notify('Intente de nuevo.','error')</script>";
    }
}

if ($action == 'guardar_compras_aprobadas') {
    $aprobar_compra = isset($_POST['aprobar_compra']);
    if (empty($aprobar_compra)) {
        echo "<script>
                    $.notify('Seleccione las compras que desea aprobar.','success');
                    </script>";
    } else {
        $mes_ano = $_POST['mes'] . '-' . $_POST['anio'];
        $compras = $_POST['aprobar_compra'];
        $query_encabezado = mysqli_query($con, "INSERT INTO aprobaciones_adquisiciones VALUES (NULL, '" . date("Y-m-d H:i:s") . "', '" . $mes_ano . "','1','" . $id_usuario . "','" . $ruc_empresa . "', '" . date("Y-m-d H:i:s") . "','0')");
        $id_aprobacion = mysqli_insert_id($con);
        if ($query_encabezado) {
            foreach ($compras as $clave => $valor) {
                $query_detalle = mysqli_query($con, "INSERT INTO detalle_aprobaciones_adquisiciones VALUES (NULL, '" . $id_aprobacion . "', '" . $clave . "')");
            }
            echo "<script>
                $.notify('Documento registrado.','success');
                setTimeout(function () {location.reload()}, 40 * 20);
                </script>";
        } else {
            echo "<script>$.notify('Intente de nuevo.','error')</script>";
        }
    }
}


//buscar compras para aprobar
if ($action == 'buscar_compras') {
    $mes = $_GET['mes'];
    $anio = $_GET['anio'];
    $sql = mysqli_query($con, "SELECT com.codigo_documento as codigo_documento, com.id_encabezado_compra as id, aut.comprobante as documento, pro.razon_social as proveedor,
    com.fecha_compra as fecha_compra, round(com.total_compra,2) as total_compra FROM encabezado_compra as com
    INNER JOIN proveedores as pro ON pro.id_proveedor=com.id_proveedor
    INNER JOIN comprobantes_autorizados as aut ON
    aut.id_comprobante = com.id_comprobante WHERE com.ruc_empresa='" . $ruc_empresa . "' and com.id_comprobante !='4'
    and DATE_FORMAT(com.fecha_compra, '%Y') = '" . $anio . "' and DATE_FORMAT(com.fecha_compra, '%m') = '" . $mes . "' 
    and com.id_encabezado_compra NOT IN (SELECT det.id_documento FROM detalle_aprobaciones_adquisiciones as det) 
    order by com.fecha_compra desc, proveedor asc ");

    if ($sql->num_rows > 0) {
    ?>
        <div class="panel panel-info" style="height: 380px; overflow-y: auto; margin-bottom: 5px;">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Fecha</th>
                        <th>Proveedor</th>
                        <th>Documento</th>
                        <th>Detalle</th>
                        <th>Total</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    $suma_total = 0;
                    while ($row = mysqli_fetch_array($sql)) {
                        $id = $row['id'];
                        $codigo_documento = $row['codigo_documento'];
                        $fecha_compra = date("d-m-Y", strtotime($row['fecha_compra']));
                        $proveedor = $row['proveedor'];
                        $documento = $row['documento'];
                        $total = $row['total_compra'];
                        $suma_total += $total;
                    ?>
                        <tr>
                            <td><?php echo $fecha_compra; ?></td>
                            <td><?php echo $proveedor; ?></td>
                            <td><?php echo $documento; ?></td>
                            <td><a href="#" class="btn btn-info btn-xs" onclick="detalle_compras('<?php echo $codigo_documento; ?>')" title="Detalle documento" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a></td>
                            <td class="text-right"><?php echo number_format($total, 2, '.', ''); ?></td>
                            <td class="text-right"><input type="checkbox" name="aprobar_compra[<?php echo $id; ?>]" checked></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr class="info">
                        <th colspan="4" class='text-right'>Totales</th>
                        <th class='text-right'><?php echo number_format($suma_total, 2, '.', ''); ?></th>
                        <th></th>
                    </tr>
                </table>
            </div>
        </div>
    <?php
    } else {
    ?>
        <div class="alert alert-danger" role="alert">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php
            echo "No hay documentos para procesar en este período.";
            ?>
        </div>
    <?php

    }
}


//buscar aprobaciones_adquisiciones
if ($action == 'buscar_aprobaciones') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('nombre', 'mes_ano', 'fecha_registro'); //Columnas de busqueda
    $sTable = "aprobaciones_adquisiciones as apro 
    INNER JOIN usuarios as usu ON apro.id_usuario=usu.id";
    $sWhere = "WHERE apro.ruc_empresa = '" . $ruc_empresa . "'";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (apro.ruc_empresa = '" . $ruc_empresa . "' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND apro.ruc_empresa = '" . $ruc_empresa . "' OR ";
        }
        $sWhere = substr_replace($sWhere, "AND apro.ruc_empresa = '" . $ruc_empresa . "' ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by mid(mes_ano,4,4) desc, mid(mes_ano,1,2) desc";


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
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT apro.id as id, apro.fecha_registro as fecha_registro,
    apro.mes_ano as mes_ano, usu.nombre as usuario, apro.status as status 
     FROM $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_registro");'>Fecha_Registro</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("mes_ano");'>Período</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("usuario");'>Aprobado por</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                        <th>Documentos</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id = $row['id'];
                        $fecha_registro = date("d-m-Y / H:i", strtotime($row['fecha_registro']));
                        $aprobado_por = $row['usuario'];
                        $mes_ano = $row['mes_ano'];

                        $sql_documentos = mysqli_query($con, "SELECT count(id_documento) as documentos 
                        FROM detalle_aprobaciones_adquisiciones
                        WHERE id_aprobacion='" . $id . "' ");
                        $documento = mysqli_fetch_array($sql_documentos);

                        $sql_compras = mysqli_query($con, "SELECT count(id_encabezado_compra) as compras 
                        FROM encabezado_compra
                        WHERE ruc_empresa='" . $ruc_empresa . "' and DATE_FORMAT(fecha_compra, '%Y') = '" . substr($mes_ano, 3, 4) . "' 
                        and DATE_FORMAT(fecha_compra, '%m') = '" . substr($mes_ano, 0, 2) . "' ");
                        $compras = mysqli_fetch_array($sql_compras);

                        $documentos = $documento['documentos'] . " de " . $compras['compras'];
                        $status = $row['status'] == 1 ? '<span class="label label-success">Aprobado</span>' : '<span class="label label-danger">Anulado</span>';
                    ?>
                        <tr>
                            <td><?php echo $fecha_registro; ?></td>
                            <td><?php echo $mes_ano; ?></td>
                            <td><?php echo $aprobado_por; ?></td>
                            <td><?php echo $status; ?></td>
                            <td><?php echo $documentos; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    $con = conenta_login();
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'aprobar_adquisiciones')['r'] == 1) {
                                    ?>
                                        <a class="btn btn-info btn-xs" title="Detalle de documentos" onclick="detalle_documentos('<?php echo $id; ?>');" data-toggle="modal" data-target="#detalleComprasAprobadas"><i class="glyphicon glyphicon-list"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'aprobar_adquisiciones')['d'] == 1) {
                                    ?>
                                        <a class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_aprobacion('<?php echo $id; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                            </td>
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

?>