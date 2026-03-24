<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//para eliminar el registro o sea actualizar a status 0
if ($action == 'eliminar_opciones_impresion_restaurante') {
    $intid = $_GET['id'];
    $delete = mysqli_query($con, "DELETE FROM opciones_envio_impresion WHERE id = '" . $intid . "'");
    if (!$delete) {
        echo "<script>
        $.notify('No es posible eliminar el registro','error');
        </script>";
    } else {
       
        echo "<script>
       $.notify('Registro anulado','success');
       setTimeout(function (){location.reload()}, 1000);
       </script>";
    }
}

if ($action == 'guardar_opciones_impresion_restaurante') {
    $idRegistro = intval($_POST['idRegistro']);
    $listCategoria = intval($_POST['listCategoria']);
    $listOpcion = intval($_POST['listOpcion']);
        if (empty($idRegistro)) {
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM opciones_envio_impresion WHERE id_categoria='" . $listCategoria . "' ");
            $row_existente = mysqli_fetch_array($consulta_existente);
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Esta categoría ya esta registrada','error');
                </script>";
            }else{             
                $guarda_registro = mysqli_query($con, "INSERT INTO opciones_envio_impresion (id_categoria, id_opcion,
                                                                                        ruc_empresa)
                                                                                VALUES ('" . $listCategoria . "',
                                                                                        '" . $listOpcion . "',
                                                                                        '" . $ruc_empresa . "')");

                //detalle
                echo "<script>
                $.notify('Registrado','success');
                setTimeout(function (){location.reload()}, 1000);
                </script>";
            }
        } else {
            //modificar 
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM opciones_envio_impresion WHERE id !='" . $idRegistro . "' and id_categoria='" . $listCategoria . "' ");
            $row_existente = mysqli_fetch_array($consulta_existente);
                        
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Esta categoría ya esta registrada','error');
                </script>";
            }else{   
                $update_registro = mysqli_query($con, "UPDATE opciones_envio_impresion SET id_categoria='" . $listCategoria . "',
                                                                                            id_opcion='" . $listOpcion . "'
                                                                                    WHERE id='" . $idRegistro . "'");

                echo "<script>
                $.notify('Actualizado','success');
                setTimeout(function (){location.reload()}, 1000);
                </script>";
            }
        }
    }

if ($action == 'buscar_opciones_impresion_restaurante') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('mar.nombre_marca'); //Columnas de busqueda
    $sTable = "opciones_envio_impresion as opc INNER JOIN marca as mar ON mar.id_marca=opc.id_categoria";//
    $sWhere = "WHERE opc.ruc_empresa ='" . $ruc_empresa . " ' ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (opc.ruc_empresa ='" . $ruc_empresa . " ' AND ";

        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND opc.ruc_empresa ='" . $ruc_empresa . " ' OR ";
        }

        $sWhere = substr_replace($sWhere, " AND opc.ruc_empresa ='" . $ruc_empresa . " ' ", -3);
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
    $sql = "SELECT opc.id_categoria as id_categoria, mar.nombre_marca as categoria, opc.id_opcion as opcion, opc.id as id FROM $sTable $sWhere LIMIT $offset,$per_page";
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
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("mar.nombre_marca");'>Categoría</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("opc.id_opcion");'>Imprimir en</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>

                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id = $row['id'];
                        $id_categoria = $row['id_categoria'];
                        $categoria = $row['categoria'];
                        $opcion = $row['opcion'];
                        
                        if ($opcion == 1) {
                            $status_final = 'Cocina';
                        } else {
                            $status_final = 'Barra';
                        }

                    ?>
                        <input type="hidden" value="<?php echo $id_categoria; ?>" id="categoria_mod<?php echo $id; ?>">
                        <input type="hidden" value="<?php echo $opcion; ?>" id="opcion_mod<?php echo $id; ?>">
                        <tr>
                            <td><?php echo $categoria; ?></td>
                            <td><?php echo $status_final; ?></td>

                            <td class="col-sm-2 text-right">
                                <a href="#" class='btn btn-info btn-xs' title='Editar' onclick="editar_opciones_impresion_restaurante('<?php echo $id; ?>');" data-toggle="modal" data-target="#opciones_impresiones"><i class="glyphicon glyphicon-edit"></i></a>
                                <a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_opciones_impresion('<?php echo $id; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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

?>