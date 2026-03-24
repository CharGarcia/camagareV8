<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'agregar_producto') {
    $id_producto = intval($_GET['id_producto']);
    $cantidad_agregar = $_GET['cantidad_agregar'];
    $nombre_producto = $_GET['nombre_producto'];
    $codigo_producto = $_GET['codigo_producto'];
    $id_medida = $_GET['id_medida'];
    $consulta_medida = mysqli_query($con, "SELECT * FROM unidad_medida WHERE id_medida='" . $id_medida . "' ");
    $row_medida=mysqli_fetch_array($consulta_medida);
    $nombre_medida=$row_medida['nombre_medida'];

    $arrItems = array();
    $arrayDatosItems = array('id' => numAleatorio(4), 'id_producto' => $id_producto, 'codigo_producto' => $codigo_producto, 'cantidad_agregar' => $cantidad_agregar, 'nombre_producto' => $nombre_producto, 'id_usuario' => $id_usuario, 'id_medida'=>$id_medida, 'nombre_medida' => $nombre_medida);
    if (isset($_SESSION['arrItems'])) {
        $on = true;
        $arrItems = $_SESSION['arrItems'];

        for ($pr = 0; $pr < count($arrItems); $pr++) {
			if ($arrItems[$pr]['id_producto'] == $id_producto ) {
				$arrItems[$pr]['cantidad_agregar'] += $cantidad_agregar;
				$on = false;
			}
		}
        if ($on) {
            array_push($arrItems, $arrayDatosItems);
		}
        $_SESSION['arrItems'] = $arrItems;
    } else {
        array_push($arrItems, $arrayDatosItems);
        $_SESSION['arrItems'] = $arrItems;
    }
    detalle_agregados_producto_tmp();
}

if ($action == 'eliminar_detalle_agregados') {
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
    sort($arrData); //para reordenar el array
    $_SESSION['arrItems'] = $arrData;
    detalle_agregados_producto_tmp();
}

if ($action == 'nuevo_agregados_producto') {
    unset($_SESSION['arrItems']);
}

if ($action == 'editar_agregados_producto') {
    unset($_SESSION['arrItems']);
    $intid = $_GET['id'];
    $consulta_detalle = mysqli_query($con, "SELECT * FROM detalle_agregados_producto WHERE id_agregado='" . $intid . "' ");
    $arrItems = array();
    $arrayDatosItems = array();
    while ($row = mysqli_fetch_array($consulta_detalle)) {
        $consulta_medida = mysqli_query($con, "SELECT * FROM unidad_medida WHERE id_medida='" . $row['id_medida'] . "' ");
        $row_medida=mysqli_fetch_array($consulta_medida);
        $nombre_medida=$row_medida['nombre_medida'];
        $arrayDatosItems = array('id' => numAleatorio(4), 'id_producto' => $row['id_producto'], 'codigo_producto' => $row['codigo_producto'], 'cantidad_agregar' => $row['cantidad'], 'nombre_producto' => $row['nombre_producto'], 'id_usuario' => $id_usuario, 'id_medida'=>$row['id_medida'], 'nombre_medida'=>$nombre_medida);
        if (isset($_SESSION['arrItems'])) {
            $arrItems = $_SESSION['arrItems'];
            array_push($arrItems, $arrayDatosItems);
            $_SESSION['arrItems'] = $arrItems;
        } else {
            array_push($arrItems, $arrayDatosItems);
             $_SESSION['arrItems'] = $arrItems;
        }
    }
    detalle_agregados_producto_tmp();
}


//para eliminar el registro o sea actualizar a status 0
if ($action == 'eliminar_agregados_producto') {
    $intid = $_GET['id'];
    $consulta_detalle = mysqli_query($con, "SELECT status FROM encabezado_agregados_producto WHERE id='" . $intid . "' ");
    $row_status = mysqli_fetch_array($consulta_detalle);
    $status = $row_status['status'];
    if ($status == 2) {
        echo "<script>
        $.notify('No es posible eliminar el registro, su status es anulado.','error');
        </script>";
    } else {
        $update_pedido = mysqli_query($con, "UPDATE encabezado_agregados_producto SET status=0, id_usuario='" . $id_usuario . "' WHERE id='" . $intid . "' and status !=2");
        echo "<script>
       $.notify('Registro anulado','success');
       setTimeout(function (){location.reload()}, 1000);
       </script>";
    }
}

if ($action == 'guardar_agregados_producto') {
    $idRegistro = intval($_POST['idAgregados_producto']);
    $listStatus = intval($_POST['listStatus']);
    $id_producto_principal = intval($_POST['id_producto_principal']);
        if (empty($idRegistro)) {
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM encabezado_agregados_producto WHERE id_producto='" . $id_producto_principal . "' and status !=0 ");
            $row_existente = mysqli_fetch_array($consulta_existente);
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Producto ya registrado','error');
                </script>";
            }else{             
                $guarda_encabezado = mysqli_query($con, "INSERT INTO encabezado_agregados_producto (ruc_empresa,
                                                                                        id_producto,
                                                                                        fecha_agregado,
                                                                                        id_usuario)
                                                                                VALUES ('" . $ruc_empresa . "',
                                                                                        '" . $id_producto_principal . "',
                                                                                        '".date("Y-m-d H:i:s")."',
                                                                                        '" . $id_usuario . "')");
                $lastid = mysqli_insert_id($con);

                //detalle
                guarda_detalle($_SESSION['arrItems'], $lastid, $con);
                echo "<script>
                $.notify('Registrado','success');
                setTimeout(function (){location.reload()}, 1000);
                </script>";
                unset($_SESSION['arrItems']);
            }
        } else {
            //modificar 
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM encabezado_agregados_producto WHERE id !='" . $idRegistro . "' and id_producto='" . $id_producto_principal . "' and status !=0 ");
            $row_existente = mysqli_fetch_array($consulta_existente);
            
            $consulta_existente_detalle = mysqli_query($con, "SELECT count(*) AS numrows_detalle FROM detalle_agregados_producto WHERE id_agregado='" . $idRegistro . "' and id_producto='" . $id_producto_principal . "' ");
            $row_existente_detalle = mysqli_fetch_array($consulta_existente_detalle);
            
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Producto ya registrado','error');
                </script>";
            }else if($row_existente_detalle['numrows_detalle']>0){ 
                echo "<script>
                $.notify('El producto consta como agregado','error');
                </script>";
            }else{   
                $update_pedido = mysqli_query($con, "UPDATE encabezado_agregados_producto SET id_producto='" . $id_producto_principal . "',
                                                                                    id_usuario='" . $id_usuario . "',                                                                    
                                                                                    fecha_agregado='".date("Y-m-d H:i:s")."',
                                                                                    status='" . $listStatus . "'
                                                                                    WHERE id='" . $idRegistro . "'");
                $delete_detalle = mysqli_query($con, "DELETE FROM detalle_agregados_producto WHERE id_agregado = '" . $idRegistro . "'");

                guarda_detalle($_SESSION['arrItems'], $idRegistro, $con);
                echo "<script>
                $.notify('Actualizado','success');
                setTimeout(function (){location.reload()}, 1000);
                </script>";
                unset($_SESSION['arrItems']);
            }
        }
    }


if ($action == 'detalle_agregados_producto') {
    detalle_agregados_producto($_GET['id']);
}

function guarda_detalle($data, $id_registro, $con)
{
    foreach ($data as $detalle) {
        $guarda_detalle = mysqli_query($con, "INSERT INTO detalle_agregados_producto (id_agregado,
                                                                id_producto,
                                                                codigo_producto,
                                                                nombre_producto,
                                                                cantidad,
                                                                id_medida)
                                                        VALUES ('" . $id_registro . "',
                                                                '$detalle[id_producto]',
                                                                '$detalle[codigo_producto]',
                                                                '$detalle[nombre_producto]',
                                                                '$detalle[cantidad_agregar]',
                                                                '$detalle[id_medida]')");
    }
}

function detalle_agregados_producto_tmp()
{
?>
    <div class="panel panel-info">
        <table class="table table-hover">
            <tr class="info">
                <th style="padding: 2px;">Código</th>
                <th style="padding: 2px;">Producto</th>
                <th style="padding: 2px;">Cantidad</th>
                <th style="padding: 2px;">Medida</th>
                <th style="padding: 2px;" class='text-right'>Eliminar</th>
            </tr>
            <?php
            foreach ($_SESSION['arrItems'] as $detalle) {
                $id_detalle = $detalle['id'];
                $codigo_producto = $detalle['codigo_producto'];
                $nombre_producto = $detalle['nombre_producto'];
                $cantidad = $detalle['cantidad_agregar'];
                $medida = $detalle['nombre_medida'];
            ?>
                <tr>
                    <td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
                    <td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
                    <td style="padding: 2px;"><?php echo $cantidad; ?></td>
                    <td style="padding: 2px;"><?php echo $medida; ?></td>
                    <td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
                </tr>
            <?php
            }
            ?>
        </table>
    </div>
    <?php
}

if ($action == 'buscar_agregados_producto') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('pro.nombre_producto'); //Columnas de busqueda
    $sTable = "encabezado_agregados_producto as enc INNER JOIN productos_servicios as pro ON pro.id=enc.id_producto";//
    $sWhere = "WHERE enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 AND ";

        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 OR ";
        }

        $sWhere = substr_replace($sWhere, " AND enc.ruc_empresa ='" . $ruc_empresa . " ' and enc.status !=0 ", -3);
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
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT enc.id_producto as id_producto, pro.nombre_producto as producto, enc.status as status, enc.id as id FROM $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
        //onclick='ordenar("pro.nombre_producto");'
        //onclick='ordenar("enc.status");'
    ?>
        <div class="table-responsive">
            <div class="panel panel-info">
                <table class="table table-hover">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("pro.nombre_producto");'>Producto</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("enc.status");'>Status</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>

                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_agregado = $row['id'];
                        $id_producto = $row['id_producto'];
                        $producto = $row['producto'];
                        
                        $status = $row['status'];
                        if ($status == 1) {
                            $status_final = '<span class="label label-success">Activo</span>';
                        } else {
                            $status_final = '<span class="label label-danger">Pasivo</span>';
                        }

                    ?>
                        <input type="hidden" value="<?php echo $producto; ?>" id="producto_mod<?php echo $id_agregado; ?>">
                        <input type="hidden" value="<?php echo $id_producto; ?>" id="id_producto_mod<?php echo $id_agregado; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status_mod<?php echo $id_agregado; ?>">
                        <tr>
                            <td><?php echo $producto; ?></td>
                            <td><?php echo $status_final; ?></td>

                            <td class="col-sm-2 text-right">
                                <a href="#" class='btn btn-info btn-xs' title='Editar' onclick="editar_agregados_producto('<?php echo $id_agregado; ?>');" data-toggle="modal" data-target="#agregados"><i class="glyphicon glyphicon-edit"></i></a>
                                <a href="#" class='btn btn-info btn-xs' title='Detalle' onclick="detalle_agregados_producto('<?php echo $id_agregado; ?>');" data-toggle="modal" data-target="#modalViewAgregados_producto"><i class="glyphicon glyphicon-list"></i></a>
                                <a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_agregado('<?php echo $id_agregado; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="3"><span class="pull-right">
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



function detalle_agregados_producto($id)
{
    $con = conenta_login();
    $busca_encabezado = mysqli_query($con, "SELECT pro.nombre_producto as producto,  
    usu.nombre as usuario, enc.fecha_agregado as fecha_agregado, enc.status as status, enc.id as id FROM encabezado_agregados_producto as enc INNER JOIN productos_servicios as pro ON pro.id=enc.id_producto INNER JOIN usuarios as usu ON usu.id=enc.id_usuario WHERE enc.id = '".$id."' ");
    $row = mysqli_fetch_array($busca_encabezado);
    $id_agregado = $row['id'];
    $fecha_registro = date('d-m-Y H:i', strtotime($row['fecha_agregado']));
    $producto = strtoupper($row['producto']);
    $usuario = strtoupper($row['usuario']);
    $status = $row['status'];
    if ($status == 1) {
        $status_final = '<span class="label label-success">Activo</span>';
    } else {
        $status_final = '<span class="label label-danger">Pasivo</span>';
    }



    $busca_detalle = mysqli_query($con, "SELECT * FROM detalle_agregados_producto as det INNER JOIN unidad_medida as med ON med.id_medida=det.id_medida WHERE det.id_agregado = '".$id_agregado."' ");
    ?>
    
        <div class="panel panel-info">
        <div class="table-responsive">
            <table class="table">
                <tbody>
                    <tr>
                        <td>Producto:</td>
                        <td><?php echo $producto;?></td>
                        <td>Status:</td>
                        <td><?php echo $status_final;?></td>
                    </tr>
                    <tr>
                        <td>Fecha agregado:</td>
                        <td><?php echo $fecha_registro;?></td>
                        <td>Registrado por:</td>
                        <td><?php echo $usuario;?></td>
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
                <th style="padding: 2px;">Cantidad</th>
                <th style="padding: 2px;">Medida</th>
            </tr>
            <?php
            while ($detalle = mysqli_fetch_array($busca_detalle)) {
                $codigo_producto = $detalle['codigo_producto'];
                $nombre_producto = $detalle['nombre_producto'];
                $cantidad = $detalle['cantidad'];
                $medida = $detalle['nombre_medida'];
            ?>
                <tr>
                    <td style="padding: 2px;"><?php echo $codigo_producto; ?></td>
                    <td style="padding: 2px;"><?php echo $nombre_producto; ?></td>
                    <td style="padding: 2px;"><?php echo number_format($cantidad, 6, '.', '') ?></td>
                    <td style="padding: 2px;"><?php echo $medida; ?></td>
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