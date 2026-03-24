<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


//para mostrar pedidos pendientes
if ($action == 'muestra_pedidos_pendientes') {

    //para mostrar aviso de pedidos
    $result_avisos_pedidos = mysqli_query($con, "SELECT count(status) as pedidos FROM encabezado_pedido WHERE ruc_empresa='" . $ruc_empresa . "' and status = '1'");
    $row_avisos_pedido = mysqli_fetch_array($result_avisos_pedidos);
    $aviso_pedido = $row_avisos_pedido['pedidos'];
    if ($aviso_pedido == 0) {
        $aviso_pedido = "";
    } else if ($aviso_pedido == 1) {
        $aviso_pedido = "<span class='badge' style='background-color: red;'>" . $row_avisos_pedido['pedidos'] . "</span>" . " pedido pendiente por despachar.";
    } else if ($aviso_pedido > 1) {
        $aviso_pedido = "<span class='badge' style='background-color: red;'>" . $row_avisos_pedido['pedidos'] . "</span>"  . " pedidos pendientes por despachar.";
    } else {
        $aviso_pedido = "";
    }
    echo $aviso_pedido;
}

//para eliminar un iten del egreso temporal
if ($action == 'agregar_producto') {
    $id_producto = intval($_GET['id_producto']);
    $cantidad_agregar = $_GET['cantidad_agregar'];
    $nombre_producto = $_GET['nombre_producto'];
    $codigo_producto = $_GET['codigo_producto'];

    $arrItems = array();
    $arrayDatosItems = array('id' => numAleatorio(4), 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'cantidad_agregar' => $cantidad_agregar, 'nombre_producto' => $nombre_producto, 'id_usuario' => $id_usuario);
    if (isset($_SESSION['arrItems'])) {
        $arrItems = $_SESSION['arrItems'];
        array_push($arrItems, $arrayDatosItems);
        $_SESSION['arrItems'] = $arrItems;
    } else {
        array_push($arrItems, $arrayDatosItems);
        $_SESSION['arrItems'] = $arrItems;
    }
    detalle_pedido_tmp();
}

if ($action == 'eliminar_detalle_pedido') {
    $intid = $_GET['id'];
    $arrData = $_SESSION['arrItems'];
    for ($i = 0; $i < count($arrData); $i++) {
        if ($arrData[$i]['id'] == $intid) {
            unset($arrData[$i]);
            echo "<script>
            $.notify('Producto eliminado','error');
            </script>";
        }
    }
    //sort($arrData); //para reordenar el array
    $_SESSION['arrItems'] = $arrData;
    detalle_pedido_tmp();
}

if ($action == 'nuevo_pedido') {
    unset($_SESSION['arrItems']);
}

if ($action == 'editar_pedido') {
    unset($_SESSION['arrItems']);
    $intid = $_GET['id'];
    $consulta_pedido = mysqli_query($con, "SELECT * FROM detalle_pedido WHERE id_pedido='" . $intid . "' ");
    $arrItems = array();
    $arrayDatosItems = array();
    while ($row = mysqli_fetch_array($consulta_pedido)) {
        $arrayDatosItems = array('id' => numAleatorio(4), 'id_producto' => $row['id_producto'], 'codigo_producto' => $row['codigo_producto'], 'cantidad_agregar' => $row['cantidad'], 'nombre_producto' => $row['producto'], 'id_usuario' => $id_usuario);
        if (isset($_SESSION['arrItems'])) {
            $arrItems = $_SESSION['arrItems'];
            array_push($arrItems, $arrayDatosItems);
            $_SESSION['arrItems'] = $arrItems;
        } else {
            array_push($arrItems, $arrayDatosItems);
            $_SESSION['arrItems'] = $arrItems;
        }
    }
    detalle_pedido_tmp();
}

if ($action == 'eliminar_pedido') {
    $intid = $_GET['id'];
    $consulta_pedido = mysqli_query($con, "SELECT status FROM encabezado_pedido WHERE id='" . $intid . "' ");
    $row_status = mysqli_fetch_array($consulta_pedido);
    $status = $row_status['status'];
    if ($status == 2) {
        echo "<script>
        $.notify('No es posible eliminar el pedido, su etatus es procesado.','error');
        </script>";
    } else {
        $update_pedido = mysqli_query($con, "UPDATE encabezado_pedido SET status='3', id_usuario_mod='" . $id_usuario . "' WHERE id='" . $intid . "' and status !=2");
        echo "<script>
       $.notify('Pedido anulado','success');
       setTimeout(function (){location.reload()}, 1000);
       </script>";
    }
}

if ($action == 'guardar_pedido') {
    $idPedido = intval($_POST['idPedido']);
    $fecha_pedido = date('Y-m-d', strtotime($_POST['fecha_pedido']));
    $responsable_traslado = intval($_POST['responsable_traslado']);
    $hora_entrega_desde = date('H:i A', strtotime($_POST['hora_entrega_desde']));
    $hora_entrega_hasta = date('H:i A', strtotime($_POST['hora_entrega_hasta']));
    $hora_actual = date('H:i A');
    $id_cliente_pedido = intval($_POST['id_cliente_pedido']);
    $observacion_pedido_cliente = strClean($_POST['observacion_pedido_cliente']);
    $observacion_pedido_interna = strClean($_POST['observacion_pedido_interna']);
    $listStatus = intval($_POST['listStatus']);
    $consulta_pedido = mysqli_query($con, "SELECT max(numero_pedido) as ultimo FROM encabezado_pedido WHERE ruc_empresa='" . $ruc_empresa . "' ");
    $row_ultimo = mysqli_fetch_array($consulta_pedido);
    $numero_pedido = $row_ultimo['ultimo'] + 1;
    $fecha_actual = date("Y/m/d");
    if (empty($_POST['fecha_pedido'])) {
        echo "<script>
            $.notify('Ingrese fecha de pedido','error');
            </script>";
    } else if (empty($id_cliente_pedido)) {
        echo "<script>
        $.notify('Seleccione un cliente','error');
        </script>";
        //} else if (DateTime::createFromFormat('d-m-Y', $fecha_pedido) < DateTime::createFromFormat('d-m-Y', $fecha_actual)) {
    } else if (date('Y/m/d', strtotime($_POST['fecha_pedido'])) < $fecha_actual) {
        if (!empty($idPedido)) {
            $update_pedido = mysqli_query($con, "UPDATE encabezado_pedido SET observaciones_cliente='" . $observacion_pedido_cliente . "',
                                                                                     observaciones_interna='" . $observacion_pedido_interna . "',
                                                                                        datecreated='" . date("Y-m-d H:i:s") . "',
                                                                                        status='" . $listStatus . "',
                                                                                        id_usuario_mod='0' 
                                                                                        WHERE id='" . $idPedido . "'");
            echo "<script>
                    $.notify('Solamente se actualizó las observaciones y el status','success');
                    </script>";
        } else {
            echo "<script>
                $.notify('La fecha de entrega no puede ser menor a la fecha actual','error');
                </script>";
        }
    } else if (checkdate(date('m', strtotime($_POST['fecha_pedido'])), date('d', strtotime($_POST['fecha_pedido'])), date('Y', strtotime($_POST['fecha_pedido']))) == false) {
        echo "<script>
            $.notify('Ingrese fecha correcta dd-mm-aaaa','error');
            </script>";
    } else if (empty($responsable_traslado)) {
        echo "<script>
        $.notify('Ingrese responsable de traslado','error');
        </script>";
    } else if (empty($_POST['hora_entrega_desde'])) {
        echo "<script>
        $.notify('Ingrese hora de entrega desde','error');
        </script>";
    } else if (empty($_POST['hora_entrega_hasta'])) {
        echo "<script>
        $.notify('Ingrese hora de entrega hasta','error');
        </script>";
    } else if (substr($_POST['hora_entrega_desde'], 0, 2) < 0) {
        echo "<script>
        $.notify('Ingrese hora de entrega desde correcta entre 1 y 24','error');
        </script>";
    } else if (substr($_POST['hora_entrega_hasta'], 0, 2) < 0) {
        echo "<script>
        $.notify('Ingrese hora de entrega hasta correcta entre 1 y 24','error');
        </script>";
    } else if (substr($_POST['hora_entrega_desde'], 0, 2) > 24) {
        echo "<script>
        $.notify('Ingrese hora de entrega correcta desde entre 1 y 24','error');
        </script>";
    } else if (substr($_POST['hora_entrega_hasta'], 0, 2) > 24) {
        echo "<script>
        $.notify('Ingrese hora de entrega correcta hasta entre 1 y 24','error');
        </script>";
    } else if (substr($_POST['hora_entrega_desde'], 3, 2) < 0) {
        echo "<script>
        $.notify('Ingrese minutos de entrega desde correctos entre 1 y 60','error');
        </script>";
    } else if (substr($_POST['hora_entrega_hasta'], 3, 2) < 0) {
        echo "<script>
        $.notify('Ingrese minutos de entrega hasta correctos entre 1 y 60','error');
        </script>";
    } else if (substr($_POST['hora_entrega_desde'], 3, 2) > 60) {
        echo "<script>
        $.notify('Ingrese minutos de entrega desde correctos entre 1 y 60','error');
        </script>";
    } else if (substr($_POST['hora_entrega_hasta'], 3, 2) > 60) {
        echo "<script>
        $.notify('Ingrese minutos de entrega hasta correctos entre 1 y 60','error');
        </script>";
    } else if ($fecha_actual == date('Y/m/d', strtotime($_POST['fecha_pedido'])) && $hora_entrega_desde < $hora_actual) {
        echo "<script>
        $.notify('Hora entrega desde, es incorrecta. Ya es '+'" . $hora_actual . "','error');
        </script>";
    } else if ($fecha_actual == date('Y/m/d', strtotime($_POST['fecha_pedido'])) && $hora_entrega_hasta < $hora_actual) {
        echo "<script>
        $.notify('Hora entrega hasta es incorrecta. Ya es '+'" . $hora_actual . "','error');
        </script>";
    } else if ($hora_entrega_hasta < $hora_entrega_desde) {
        echo "<script>
        $.notify('La hora hasta no puede ser menor a la hora desde.','error');
        </script>";
    } else if (!is_array($_SESSION['arrItems'])) {
        echo "<script>
        $.notify('Ingrese productos al pedido','error');
        </script>";
    } else {
        if (empty($idPedido)) {
            $guarda_encabezado_pedido = mysqli_query($con, "INSERT INTO encabezado_pedido (ruc_empresa,
                                                                                    fecha_entrega,
                                                                                    datecreated,
                                                                                    responsable,
                                                                                    hora_entrega_desde,
                                                                                    hora_entrega_hasta,
                                                                                    id_cliente,
                                                                                    observaciones_cliente,
                                                                                    observaciones_interna,
                                                                                    id_usuario,
                                                                                    id_usuario_mod,
                                                                                    numero_pedido)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . date('Y-m-d H:i:s', strtotime($fecha_pedido)) . "',
                                                                                    '" . date("Y-m-d H:i:s") . "',
                                                                                    '" . $responsable_traslado . "',
                                                                                    '" . $hora_entrega_desde . "',
                                                                                    '" . $hora_entrega_hasta . "',
                                                                                    '" . $id_cliente_pedido . "',
                                                                                    '" . $observacion_pedido_cliente . "',
                                                                                    '" . $observacion_pedido_interna . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '0',
                                                                                    '" . $numero_pedido . "')");
            $lastid = mysqli_insert_id($con);

            //detalle del pedido
            guarda_detalle_pedido($_SESSION['arrItems'], $lastid, $con);

            echo "<script>
        $.notify('Pedido registrado','success');
        setTimeout(function (){location.reload()}, 1000);
        </script>";
            unset($_SESSION['arrItems']);
        } else {
            //modificar el pedido
            $update_pedido = mysqli_query($con, "UPDATE encabezado_pedido SET fecha_entrega='" . date('Y-m-d H:i:s', strtotime($fecha_pedido))  . "',
                                                                                responsable='" . $responsable_traslado . "',
                                                                                hora_entrega_desde='" . $hora_entrega_desde . "',
                                                                                hora_entrega_hasta='" . $hora_entrega_hasta . "',
                                                                                id_cliente='" . $id_cliente_pedido . "',
                                                                                observaciones_cliente='" . $observacion_pedido_cliente . "',
                                                                                observaciones_interna='" . $observacion_pedido_interna . "',
                                                                                status='" . $listStatus . "',
                                                                                id_usuario_mod='" . $id_usuario . "',
                                                                                dateedited='" . date("Y-m-d H:i:s") . "' 
                                                                                WHERE id='" . $idPedido . "'");
            $delete_detalle_pedido = mysqli_query($con, "DELETE FROM detalle_pedido WHERE id_pedido = '" . $idPedido . "'");

            guarda_detalle_pedido($_SESSION['arrItems'], $idPedido, $con);
            echo "<script>
       $.notify('Pedido actualizado','success');
       setTimeout(function (){location.reload()}, 1000);
       </script>";
            unset($_SESSION['arrItems']);
        }
    }
}

if ($action == 'detalle_pedido') {
    detalle_pedido($_GET['id']);
}

function guarda_detalle_pedido($data, $id_pedido, $con)
{
    foreach ($data as $detalle) {
        $guarda_detalle = mysqli_query($con, "INSERT INTO detalle_pedido (id_pedido,
                                                                id_producto,
                                                                codigo_producto,
                                                                producto,
                                                                cantidad)
                                                        VALUES ('" . $id_pedido . "',
                                                                '$detalle[id_producto]',
                                                                '$detalle[codigo_producto]',
                                                                '$detalle[nombre_producto]',
                                                                '$detalle[cantidad_agregar]')");
    }
}

function detalle_pedido_tmp()
{
?>
    <div class="panel panel-info">
        <table class="table table-hover">
            <tr class="info">
                <th style="padding: 2px;">Código</th>
                <th style="padding: 2px;">Producto</th>
                <th style="padding: 2px;">Cant</th>
                <th style="padding: 2px;" class='text-right'>Eliminar</th>
            </tr>
            <?php
            foreach ($_SESSION['arrItems'] as $detalle) {
                $id_detalle = $detalle['id'];
                $codigo_producto = $detalle['codigo_producto'];
                $nombre_producto = $detalle['nombre_producto'];
                $cantidad = $detalle['cantidad_agregar'];
            ?>
                <tr>
                    <td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
                    <td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
                    <td style="padding: 2px;"><?php echo $cantidad; ?></td>
                    <td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
                </tr>
            <?php
            }
            ?>
        </table>
    </div>
    <?php
}

if ($action == 'buscar_pedidos') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('fecha_entrega', 'datecreated', 'responsable', 'hora_entrega_desde', 'cli.nombre', 'observaciones_cliente', 'observaciones_interna', 'numero_pedido'); //Columnas de busqueda
    $sTable = "encabezado_pedido as enc INNER JOIN clientes as cli ON enc.id_cliente=cli.id LEFT JOIN usuarios as usu ON usu.id=enc.id_usuario_mod";
    $sWhere = "WHERE enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 AND ";

        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 OR ";
        }

        $sWhere = substr_replace($sWhere, "AND enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by $ordenado $por, enc.datecreated desc, enc.numero_pedido desc"; //, 
    include("../ajax/pagination.php"); //include pagination file
    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 20; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '../pedidos.php';
    //main query to fetch the data
    $sql = "SELECT cli.id as id_cliente, enc.id as id, enc.fecha_entrega as fecha_entrega,
   enc.datecreated as datecreated, enc.hora_entrega_desde as hora_entrega_desde, enc.hora_entrega_hasta as hora_entrega_hasta, cli.nombre as cliente,
   enc.responsable as responsable, enc.numero_pedido as numero_pedido, enc.observaciones_cliente as observaciones_cliente,
   enc.observaciones_interna as observaciones_interna, enc.status as status, usu.nombre as usuario_mod FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
    ?>
        <div class="table-responsive">
            <div class="panel panel-info">
                <table class="table table-hover">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("datecreated");'>Registro</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_entrega");'>Entrega</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("hora_entrega_desde");'>Hora</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_pedido");'>Número</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.nombre");'>Cliente</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("observaciones_cliente");'>Obs Cliente</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("observaciones_interna");'>Obs Internas</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("enc.status");'>Status</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Editado</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>

                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_pedido = $row['id'];
                        $fecha_entrega = date('d/m/Y', strtotime($row['fecha_entrega']));
                        $fecha_registro = date('d/m/Y', strtotime($row['datecreated']));
                        $hora_entrega_desde = date('H:i', strtotime($row['hora_entrega_desde']));
                        $hora_entrega_hasta = date('H:i', strtotime($row['hora_entrega_hasta']));
                        $cliente = strtoupper($row['cliente']);
                        $usuario_mod = strtoupper($row['usuario_mod']);
                        $id_cliente = $row['id_cliente'];
                        $responsable = strtoupper($row['responsable']);
                        $numero = $row['numero_pedido'];
                        $observaciones_cliente = strtoupper($row['observaciones_cliente']);
                        $observaciones_interna = strtoupper($row['observaciones_interna']);
                        $status = $row['status'];
                        if ($status == 1) {
                            $status_final = '<span class="label label-warning">Pendiente</span>';
                        } else if ($status == 2) {
                            $status_final = '<span class="label label-success">Procesado</span>';
                        } else {
                            $status_final = '<span class="label label-danger">Anulado</span>';
                        }

                    ?>
                        <input type="hidden" value="<?php echo $numero; ?>" id="numero_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo date('d-m-Y', strtotime($row['fecha_entrega'])); ?>" id="fecha_entrega_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $fecha_registro; ?>" id="fecha_registro_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $responsable; ?>" id="responsable_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $hora_entrega_desde; ?>" id="hora_entrega_desde_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $hora_entrega_hasta; ?>" id="hora_entrega_hasta_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $id_cliente; ?>" id="id_cliente_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $cliente; ?>" id="cliente_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $id_usuario; ?>" id="asesor_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $id_usuario; ?>" id="usuario_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $observaciones_cliente; ?>" id="observaciones_cliente_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $observaciones_interna; ?>" id="observaciones_interna_mod<?php echo $id_pedido; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status_mod<?php echo $id_pedido; ?>">
                        <tr>
                            <td><?php echo $fecha_registro; ?></td>
                            <td><?php echo $fecha_entrega; ?></td>
                            <td><?php echo $hora_entrega_desde; ?> a <?php echo $hora_entrega_hasta; ?></td>
                            <td><?php echo $numero; ?></td>
                            <td><?php echo $cliente; ?></td>
                            <td><?php echo $observaciones_cliente; ?></td>
                            <td><?php echo $observaciones_interna; ?></td>
                            <td><?php echo $status_final; ?></td>
                            <td><?php echo $usuario_mod; ?></td>

                            <td class="col-sm-2 text-right">
                                <a title='Imprimir pdf' href="../pdf/pdf_pedido.php?action=pdf_pedido&id=<?php echo $id_pedido ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
                                <a href="#" class='btn btn-info btn-xs' title='Editar pedido' onclick="editar_pedido('<?php echo $id_pedido; ?>');" data-toggle="modal" data-target="#pedidos"><i class="glyphicon glyphicon-edit"></i></a>
                                <a href="#" class='btn btn-info btn-xs' title='Detalle pedido' onclick="detalle_pedido('<?php echo $id_pedido; ?>');" data-toggle="modal" data-target="#modalViewPedido"><i class="glyphicon glyphicon-list"></i></a>
                                <a href="#" class='btn btn-danger btn-xs' title='Eliminar pedido' onclick="eliminar_pedido('<?php echo $id_pedido; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="11"><span class="pull-right">
                                <?php
                                echo paginate($reload, $page, $total_pages, $adjacents);
                                ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    <?php
    }
}

function detalle_pedido($id)
{
    $con = conenta_login();
    $ruc_empresa = $_SESSION['ruc_empresa'];
    $busca_encabezado = mysqli_query($con, "SELECT cli.id as id_cliente, enc.id as id, enc.fecha_entrega as fecha_entrega,
    enc.datecreated as datecreated, enc.dateedited as dateedited, enc.hora_entrega_desde as hora_entrega_desde, enc.hora_entrega_hasta as hora_entrega_hasta, cli.nombre as cliente,
    enc.responsable as responsable, enc.numero_pedido as numero_pedido, enc.observaciones_cliente as observaciones_cliente,
    enc.observaciones_interna as observaciones_interna, enc.status as status, usu.nombre as usuario, enc.id_usuario_mod as id_usuario_mod FROM encabezado_pedido as enc INNER JOIN clientes as cli ON enc.id_cliente=cli.id INNER JOIN usuarios as usu ON usu.id=enc.id_usuario WHERE enc.id = '" . $id . "' ");
    $row = mysqli_fetch_array($busca_encabezado);
    $id_pedido = $row['id'];
    $fecha_entrega = date('d-m-Y', strtotime($row['fecha_entrega']));
    $fecha_registro = date('d-m-Y H:i', strtotime($row['datecreated']));
    $fecha_editado = date('d-m-Y H:i', strtotime($row['dateedited']));
    $hora_entrega_desde = date('H:i', strtotime($row['hora_entrega_desde']));
    $hora_entrega_hasta = date('H:i', strtotime($row['hora_entrega_hasta']));
    $cliente = strtoupper($row['cliente']);
    $usuario = strtoupper($row['usuario']);
    $id_usuario_mod = $row['id_usuario_mod'];
    $responsable = $row['responsable'];
    $numero = $row['numero_pedido'];
    $observaciones_cliente = strtoupper($row['observaciones_cliente']);
    $observaciones_interna = strtoupper($row['observaciones_interna']);
    $status = $row['status'];
    if ($status == 1) {
        $status_final = '<span class="label label-warning">Pendiente</span>';
    } else if ($status == 2) {
        $status_final = '<span class="label label-success">Procesado</span>';
    } else {
        $status_final = '<span class="label label-danger">Anulado</span>';
    }

    $sql_traslado = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ruc_empresa ='" . $ruc_empresa . "' order by nombre asc");
    foreach ($sql_traslado as $resp) {
        if ($responsable == $resp['id']) {
            $responsable_final = $resp['nombre'];
        }
    }

    $sql_usuario_mod = mysqli_query($con, "SELECT nombre as usuario_modificado FROM usuarios WHERE id='" . $id_usuario_mod . "'");
    $row_usuario_modificado = mysqli_fetch_array($sql_usuario_mod);
    $usuario_modificado = isset($row_usuario_modificado['usuario_modificado']) ? $row_usuario_modificado['usuario_modificado'] : '';

    $busca_detalle = mysqli_query($con, "SELECT * FROM detalle_pedido WHERE id_pedido = '" . $id_pedido . "' ");
    ?>

    <div class="panel panel-info">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <td>Número de pedido:</td>
                        <td><?php echo $numero; ?></td>
                        <td>Status:</td>
                        <td><?php echo $status_final; ?></td>
                        <td>Fecha emisión:</td>
                        <td><?php echo $fecha_registro; ?></td>
                    </tr>
                    <tr>
                        <td>Fecha entrega:</td>
                        <td><?php echo $fecha_entrega; ?></td>
                        <td>Hora entrega de:</td>
                        <td><?php echo $hora_entrega_desde; ?> a <?php echo $hora_entrega_hasta; ?></td>
                        <td>Responsable:</td>
                        <td><?php echo $responsable_final; ?></td>
                    </tr>
                    <tr>
                        <td>Cliente:</td>
                        <td colspan="5"><?php echo $cliente; ?></td>
                    </tr>
                    <tr>
                        <td>Creado por:</td>
                        <td colspan="3"><?php echo $usuario; ?></td>
                        <td>Fecha creado:</td>
                        <td><?php echo $fecha_registro; ?></td>
                    </tr>
                    <tr>
                        <td>Modificado por:</td>
                        <td colspan="3"><?php echo $usuario_modificado; ?></td>
                        <td>Fecha editado:</td>
                        <td><?php echo !empty($usuario_modificado) ? $fecha_editado : ''; ?></td>

                    </tr>
                    <tr>
                        <td>Observaciones cliente:</td>
                        <td colspan="5"><?php echo $observaciones_cliente; ?></td>
                    </tr>
                    <tr>
                        <td>Observaciones internas:</td>
                        <td colspan="5"><?php echo $observaciones_interna; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel panel-info">
        <div class="table-responsive">
            <table class="table table-hover">
                <tr class="info">
                    <th style="padding: 2px;">Código</th>
                    <th style="padding: 2px;">Producto</th>
                    <th style="padding: 2px;">Cant_Pedida</th>
                    <th style="padding: 2px;">Despachado</th>
                    <th style="padding: 2px;">Observaciones</th>
                </tr>
                <?php
                while ($detalle = mysqli_fetch_array($busca_detalle)) {
                    $codigo_producto = $detalle['codigo_producto'];
                    $nombre_producto = $detalle['producto'];
                    $cantidad = $detalle['cantidad'];
                    $observaciones = $detalle['observaciones'];
                    $despachado = $detalle['despachado'];
                ?>
                    <tr>
                        <td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
                        <td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
                        <td style="padding: 2px;"><?php echo number_format($cantidad, 2, '.', '') ?></td>
                        <td style="padding: 2px;"><?php echo number_format($despachado, 2, '.', '') ?></td>
                        <td style="padding: 2px;"><?php echo $observaciones; ?></td>
                    </tr>
                <?php
                }
                ?>
            </table>

        </div>
    </div>

<?php
}

?>