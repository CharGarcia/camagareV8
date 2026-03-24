<?php
require_once("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if ($action == 'nuevo_registro') {
    unset($_SESSION['arrItems']);
}
//para eliminar el registro o sea actualizar a status 0
if ($action == 'eliminar_opciones_scfp') {
    $intid = $_GET['id'];
    $delete = mysqli_query($con, "DELETE FROM calculo_saldo_forma_pago WHERE id = '" . $intid . "'");
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

if ($action == 'agregar_item') {
    $uno = intval($_GET['uno']);
    $dos = $_GET['dos'];

    $arrItems = array();
    $arrayDatosItems = array('id' => numAleatorio(4), 'uno' => $uno, 'dos' => $dos);
    if (isset($_SESSION['arrItems'])) {
        $on = true;
		$arrItems = $_SESSION['arrItems'];
		for ($pr = 0; $pr < count($arrItems); $pr++) {
			if ($arrItems[$pr]['dos'] == $dos) {
				$arrayDatosItems[$pr]['dos'] = $dos;
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
    detalle_scfp_tmp($con);
}

if ($action == 'eliminar_item') {
    $intid = $_GET['id'];
    $arrData = $_SESSION['arrItems'];
    for ($i = 0; $i < count($arrData); $i++) {
        if ($arrData[$i]['id'] == $intid) {
            unset($arrData[$i]);
            echo "<script>
            $.notify('Item eliminado','error');
            </script>";
        }
    }
    $_SESSION['arrItems'] = $arrData;
    detalle_scfp_tmp($con);
}

function detalle_scfp_tmp($con)
{
?>
    <div class="panel panel-info">
        <table class="table table-hover">
            <tr class="info">
                <th style="padding: 2px;">Formas de pago para calculo</th>
                <th style="padding: 2px;" class='text-right'>Eliminar</th>
            </tr>
            <?php
            foreach ($_SESSION['arrItems'] as $detalle) {
                $id_detalle = $detalle['id'];
                $dos = $detalle['dos'];

                $sql = mysqli_query($con,"SELECT * FROM opciones_cobros_pagos where id='".$dos."'");
				$row = mysqli_fetch_assoc($sql);
				
            ?>
                <tr>
                    <td style="padding: 2px;"><?php echo $row['descripcion']; ?></td>
                    <td style="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_item('<?php echo $id_detalle; ?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
                </tr>
            <?php
            }
            ?>
        </table>
    </div>
    <?php
}

if ($action == 'eliminar_registro_scfp') {
    $intid = $_GET['id'];
    $consulta_pedido = mysqli_query($con, "DELETE FROM calculo_saldo_forma_pago WHERE id='" . $intid . "' ");
    if ($consulta_pedido) {
        echo "<script>
       $.notify('Registro eliminado','success');
       setTimeout(function (){location.reload()}, 1000);
       </script>";
    }
}

if ($action == 'guardar_opciones_scfp') {
    $idRegistro = intval($_POST['idRegistro']);
    $uno = intval($_POST['listFormaPagoPrincipal']);
        if (empty($idRegistro)) {
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM calculo_saldo_forma_pago WHERE id_forma_cobro_pago='" . $uno . "' ");
            $row_existente = mysqli_fetch_array($consulta_existente);
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Esta opción ya esta registrada','error');
                </script>";
            }else{           
                
                if (isset($_SESSION['arrItems'])) {
                    $codigos=array();
                    $arrData=$_SESSION['arrItems'];
                    for ($i = 0; $i < count($arrData); $i++) {
                        $codigos[] = $arrData[$i]['dos'];
                    }
                    $codigos=implode(',', $codigos);

                    $guarda_registro = mysqli_query($con, "INSERT INTO calculo_saldo_forma_pago (id_forma_cobro_pago, 
                                                                                     codigo_forma_pago,
                                                                                            ruc_empresa)
                                                                                    VALUES ('" . $uno . "',
                                                                                            '" . $codigos . "',
                                                                                            '" . $ruc_empresa . "')");

                    
                    unset($_SESSION['arrItems']);
                    echo "<script>
                    $.notify('Registrado','success');
                    setTimeout(function (){location.reload()}, 1000);
                    </script>";
                    }else{
                        echo "<script>
                        $.notify('No hay formas de pago agregadas','error');
                        </script>"; 
                    }
            }
        } else {
            //modificar 
            $consulta_existente = mysqli_query($con, "SELECT count(*) AS numrows FROM calculo_saldo_forma_pago WHERE id !='" . $idRegistro . "' and id_forma_cobro_pago='" . $uno . "' ");
            $row_existente = mysqli_fetch_array($consulta_existente);
            $codigos=implode(',',$_SESSION['arrItems']);           
            if($row_existente['numrows']>0){
                echo "<script>
                $.notify('Esta opción ya esta registrada','error');
                </script>";
            }else{   
                $update_registro = mysqli_query($con, "UPDATE calculo_saldo_forma_pago SET id_forma_cobro_pago='" . $uno . "',
                                                                                            codigo_forma_pago='" . $codigos . "'
                                                                                    WHERE id='" . $idRegistro . "'");

                echo "<script>
                $.notify('Actualizado','success');
                setTimeout(function (){location.reload()}, 1000);
                </script>";
            }
        }
    }

if ($action == 'buscar_opciones_scfp') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('ofp.descripcion'); //Columnas de busqueda
    $sTable = "calculo_saldo_forma_pago as cfp INNER JOIN opciones_cobros_pagos as ofp ON ofp.id=cfp.id_forma_cobro_pago";//
    $sWhere = "WHERE cfp.ruc_empresa ='" . $ruc_empresa . " ' ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (cfp.ruc_empresa ='" . $ruc_empresa . " ' AND ";

        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND cfp.ruc_empresa ='" . $ruc_empresa . " ' OR ";
        }

        $sWhere = substr_replace($sWhere, " AND cfp.ruc_empresa ='" . $ruc_empresa . " ' ", -3);
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
    $sql = "SELECT cfp.id as id, ofp.descripcion as descripcion, cfp.codigo_forma_pago as codigos FROM $sTable $sWhere LIMIT $offset,$per_page";
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
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ofp.descripcion");'>Detalle del calculo</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>

                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id = $row['id'];
                        $descripcion = $row['descripcion'];
                        $codigos = explode(',', $row['codigos']);
                        $detalle="";
                        foreach($codigos as $codigo){
                        $sql = mysqli_query($con,"SELECT * FROM opciones_cobros_pagos where id='".$codigo."'");
				        $row = mysqli_fetch_assoc($sql);
                        $detalle .= $row['descripcion'] ." - " ;
                        }
                    ?>
                        <tr>
                            <td><b>Saldo de <?php echo $descripcion; ?> es = </b><?php echo $descripcion; ?> - <?php echo substr($detalle, 0, -2);?></td>

                            <td class="col-sm-2 text-right">
                                <a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_registro('<?php echo $id; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
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