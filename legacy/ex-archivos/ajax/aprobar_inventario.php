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

if ($action == 'aprobar_documento') {
    require_once("../excel/lib/PHPExcel/PHPExcel/IOFactory.php");
    ini_set('date.timezone', 'America/Guayaquil');
    $id = intval($_POST['id']);
    $sql = mysqli_query($con, "SELECT * FROM aprobaciones_inventario WHERE id='" . $id . "'");
    $row_aprobado = mysqli_fetch_array($sql);
    $archivo = $row_aprobado['dir_documento'];
    $status = $row_aprobado['status'];
    if ($status == "1") {
        $objPHPExcel = PHPExcel_IOFactory::load($archivo);
        $objPHPExcel->setActiveSheetIndex(0);
        $numRows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();
        $estado_registro = array();
        for ($i = 2; $i <= $numRows; $i++) {
            $codigo_producto = strClean($objPHPExcel->getActiveSheet()->getCell('A' . $i)->getCalculatedValue());
            $busca_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE ruc_empresa= '" . $ruc_empresa . "' and codigo_producto = '" . $codigo_producto . "' ");
            $row_producto = mysqli_fetch_array($busca_producto);
            $id_producto = $row_producto['id'];
            $precio_producto = $row_producto['precio_producto'];
            $id_medida = $row_producto['id_unidad_medida'];
            $nombre_producto = $row_producto['nombre_producto'];
            $cantidad = $objPHPExcel->getActiveSheet()->getCell('C' . $i)->getCalculatedValue();
            $tipo = strtoupper(strClean($objPHPExcel->getActiveSheet()->getCell('D' . $i)->getCalculatedValue()));

            $dia = $objPHPExcel->getActiveSheet()->getCell('E' . $i)->getCalculatedValue();
            $mes = $objPHPExcel->getActiveSheet()->getCell('F' . $i)->getCalculatedValue();
            $anio = $objPHPExcel->getActiveSheet()->getCell('G' . $i)->getCalculatedValue();
            $fecha_vence = date('Y-m-d', strtotime(str_pad($anio, 4, "0000", STR_PAD_LEFT) . "-" . str_pad($mes, 2, "00", STR_PAD_LEFT) . "-" . str_pad($dia, 2, "00", STR_PAD_LEFT)));

            $referencia = strClean($objPHPExcel->getActiveSheet()->getCell('H' . $i)->getCalculatedValue());
            $id_bodega = $objPHPExcel->getActiveSheet()->getCell('I' . $i)->getCalculatedValue();
            $lote = $objPHPExcel->getActiveSheet()->getCell('J' . $i)->getCalculatedValue();
            $costo = $objPHPExcel->getActiveSheet()->getCell('K' . $i)->getCalculatedValue();


            $datos_procesados[] = array(
                'id_producto' => $id_producto,
                'precio' => $precio_producto,
                'cantidad' => number_format($cantidad, 6, '.', ''),
                'tipo' => $tipo,
                'vencimiento' => $fecha_vence,
                'referencia' => $referencia,
                'id_medida' => $id_medida,
                'id_bodega' => $id_bodega,
                'costo' =>  $costo,
                'lote' => $lote,
                'codigo_producto' => $codigo_producto,
                'nombre_producto' => $nombre_producto
            );
        }

        $total_registros = count($datos_procesados) - 1;
        $fecha_registro = date('Y-m-d H:i:s');
        $total_guardadas = array();
        for ($g = 0; $g <= $total_registros; $g++) {
            if ($datos_procesados[$g]['tipo'] == 'ENTRADA') {
                $cantidad_entrada = $datos_procesados[$g]['cantidad'];
                $cantidad_salida = 0;
            }
            if ($datos_procesados[$g]['tipo'] == 'SALIDA') {
                $cantidad_salida = $datos_procesados[$g]['cantidad'];
                $cantidad_entrada = 0;
            }
            $guardar_registros = mysqli_query($con, "INSERT INTO inventarios VALUES (null,'" . $ruc_empresa . "',
                                '" . $datos_procesados[$g]['id_producto'] . "','" . $datos_procesados[$g]['precio'] . "','" . $cantidad_entrada . "',
                                '" . $cantidad_salida . "','" . $fecha_registro . "', '" . $datos_procesados[$g]['vencimiento'] . "','" . $datos_procesados[$g]['referencia'] . "','" . $id_usuario . "',
                                '" . $datos_procesados[$g]['id_medida'] . "','" . $fecha_registro . "','C','" . $datos_procesados[$g]['id_bodega'] . "',
                                 '" . $datos_procesados[$g]['tipo'] . "','" . $datos_procesados[$g]['codigo_producto'] . "' ,
                                 '" . $datos_procesados[$g]['nombre_producto'] . "','" . $datos_procesados[$g]['costo'] . "','OK', 
                                 '" . $datos_procesados[$g]['lote'] . "','" . $codigo_unico . "')");
            $total_guardadas[] = 1;
        }

        $suma_registros = array_sum($total_guardadas);

        //de aqui para abajo 
        unlink($archivo);
        $update = mysqli_query($con, "UPDATE aprobaciones_inventario SET referencia = '" . $referencia . "', fecha_aprobado = '" . $fecha_registro . "', status = '2', id_usuario_aprobado='" . $id_usuario . "' WHERE id='" . $id . "'");
        echo "<script>
            var total_registros = '$suma_registros';
            $.notify(total_registros + ' productos(s) cargados al inventario.','success');
            </script>";
    } else {
        echo "<script>$.notify('No es posible aprobar por su estado actual.','error')</script>";
    }
}

if ($action == 'eliminar_documento') {
    $id = intval($_POST['id']);
    $fecha_registro = date('Y-m-d H:i:s');
    $sql = mysqli_query($con, "SELECT * FROM aprobaciones_inventario WHERE id='" . $id . "'");
    $row_aprobado = mysqli_fetch_array($sql);
    $archivo = $row_aprobado['dir_documento'];
    $status = $row_aprobado['status'];
    if ($status == "1") {
        unlink($archivo);
        $update = mysqli_query($con, "UPDATE aprobaciones_inventario SET fecha_aprobado = '" . $fecha_registro . "', status = '3', id_usuario_aprobado='" . $id_usuario . "' WHERE id='" . $id . "'");
        echo "<script>$.notify('Registro anulado.','success')</script>";
    } else {
        echo "<script>$.notify('No es posible eliminar por su estado actual.','error')</script>";
    }
}

//buscar aprobaciones_inventario
if ($action == 'buscar_aprobaciones') {
    // escaping, additionally removing everything that could be (html/javascript-) code
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('nombre', 'ruc', 'email', 'direccion', 'telefono', 'referencia'); //Columnas de busqueda
    $sTable = "aprobaciones_inventario as apro INNER JOIN usuarios as usu ON apro.id_usuario=usu.id ";
    $sWhere = "WHERE apro.ruc_empresa = '" . $ruc_empresa . "'";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (apro.ruc_empresa = '" . $ruc_empresa . "' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND apro.ruc_empresa = '" . $ruc_empresa . "' OR ";
        }
        $sWhere = substr_replace($sWhere, "AND apro.ruc_empresa = '" . $ruc_empresa . "' ", -3);
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
    $reload = '../aprobaciones_inventario.php';
    //main query to fetch the data
    $sql = "SELECT apro.id as id, apro.fecha_registro as fecha_registro,
    apro.fecha_aprobado as fecha_aprobado, apro.modulo as modulo, usu.nombre as ususario,
     apro.id_usuario_aprobado as id_usuario_aprobado, apro.dir_documento as dir_documento, apro.status as status, apro.referencia FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_registro");'>Fecha_Registro</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_aprobado");'>Aprobado/Eliminado</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("modulo");'>Módulo</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_usuario");'>Realizado_por</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_usuario_aprobado");'>Aprobado/Eliminado_por</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("referencia");'>Referencia</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("dir_documento");'>Documento</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id = $row['id'];
                        $fecha_registro = date("d-m-Y", strtotime($row['fecha_registro']));
                        $fecha_aprobado = $row['fecha_aprobado'] == 0 ? "" : date("d-m-Y", strtotime($row['fecha_aprobado']));
                        $modulo = $row['modulo'];
                        $realizado_por = $row['ususario'];
                        $id_aprobado_por = $row['id_usuario_aprobado'];
                        $sql = mysqli_query($con, "SELECT nombre as aprobado_por FROM usuarios WHERE id='" . $id_aprobado_por . "'");
                        $row_aprobado = mysqli_fetch_array($sql);
                        $aprobado_por = $row_aprobado['aprobado_por'];
                        $dir_documento = $row['dir_documento'];
                        $referencia = $row['referencia'];
                        $status = $row['status'];
                        if ($status == 1) {
                            $status_final = '<span class="label label-warning">Pendiente</span>';
                        } else if ($status == 2) {
                            $status_final = '<span class="label label-success">Aprobado</span>';
                        } else {
                            $status_final = '<span class="label label-danger">Anulado</span>';
                        }
                    ?>
                        <tr>
                            <td><?php echo $fecha_registro; ?></td>
                            <td><?php echo $fecha_aprobado; ?></td>
                            <td><?php echo $modulo; ?></td>
                            <td><?php echo $realizado_por; ?></td>
                            <td><?php echo $aprobado_por; ?></td>
                            <td><?php echo $referencia; ?></td>
                            <td>
                                <?php
                                if ($status == "1") {
                                ?>
                                    <a href="<?php echo $dir_documento; ?>" target="_blank"><span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> Documento excel </a>
                                <?php
                                } else {
                                ?>
                                    Sin documento
                                <?php
                                }
                                ?>
                            </td>
                            <td><?php echo $status_final; ?></td>

                            <td><span class="pull-right">
                                    <?php
                                    if ($status == "2") {
                                    ?>
                                        <a href="#" class="btn btn-success btn-xs" title="Aprobado" onClick='$.notify("Documento aprobado","success")'><i class="glyphicon glyphicon-ok"></i></a>
                                    <?php
                                    } else {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Aprobar" onclick="aprobar('<?php echo $id; ?>');" data-toggle="modal" data-target="#nuevoCliente"><i class="glyphicon glyphicon-ok"></i></a>
                                    <?php
                                    }
                                    ?>
                                    <a href="#" class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_aprobacion('<?php echo $id; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="8"><span class="pull-right">
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