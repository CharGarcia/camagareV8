<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'eliminar_opciones_ingresos_egresos') {
    $id=intval($_POST['id']);
     
    $query_registros=mysqli_query($con, "select * from detalle_ingresos_egresos where tipo_ing_egr='".$id."'");
    $count_registros=mysqli_num_rows($query_registros);
    
    if ($count_registros > 0){
        echo "<script>$.notify('No se puede eliminar. Existen registros realizados con este concepto.','error')</script>";
    }else{
            if ($deleteuno=mysqli_query($con,"DELETE FROM opciones_ingresos_egresos WHERE id='".$id."'")){
                echo "<script>$.notify('Registro eliminado.','success')</script>";
            } else{
                echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
            }
    
    }

 }

//buscar 
if ($action == 'buscar_opciones_ingresos_egresos') {
    // escaping, additionally removing everything that could be (html/javascript-) code
     $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
     $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
     $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
     $aColumns = array('descripcion');//Columnas de busqueda
     $sTable = "opciones_ingresos_egresos as opc INNER JOIN usuarios usu ON usu.id=opc.id_usuario 
     LEFT JOIN asientos_programados as ap ON (ap.tipo_asiento='opcion_ingreso' or ap.tipo_asiento='opcion_egreso') and ap.id_pro_cli=opc.id";
     $sWhere = "WHERE opc.ruc_empresa ='" . $ruc_empresa . " ' ";

     $text_buscar = explode(' ',$q);
     $like="";
     for ( $i=0 ; $i<count($text_buscar) ; $i++ )
     {
         $like .= "%".$text_buscar[$i];
     }

     if ($_GET['q'] != "") {
         $sWhere = "WHERE (opc.ruc_empresa ='" . $ruc_empresa . " ' AND ";
 
         for ($i = 0; $i < count($aColumns); $i++) {
             $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND opc.ruc_empresa ='" . $ruc_empresa . " ' OR ";
         }
 
         $sWhere = substr_replace($sWhere, "AND opc.ruc_empresa ='" . $ruc_empresa . " ' ", -3);
         $sWhere .= ')';
     }
     $sWhere .= " order by $ordenado $por";

   
    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
    $per_page = 20; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row= mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows/$per_page);
    $reload = '';
    //main query to fetch the data
    $sql="SELECT opc.id as id, opc.descripcion as descripcion, 
    opc.datecreated as datecreated, opc.status as status, opc.tipo_opcion as tipo_opcion, 
    usu.nombre as usuario, ap.id_cuenta as id_cuenta FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows>0){
        
        ?>
        <div class="panel panel-info">
        <div class="table-responsive">
          <table class="table">
            <tr  class="info">
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("descripcion");'>Descripción</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_opcion");'>Aplica en</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("datecreated");'>Creado</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Código</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Cuenta</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_usuario");'>Usuario</button></th>
                <th class='text-right'>Opciones</th>				
            </tr>
            <?php
            while ($row=mysqli_fetch_array($query)){
                    $id=$row['id'];
                    $descripcion=$row['descripcion'];
                    $tipo_opcion=$row['tipo_opcion']=="1"?"Ingreso":"Egreso";
                    $status=$row['status']=="1"?"Activo":"Pasivo";
                    $datecreated=$row['datecreated'];
                    $usuario=$row['usuario'];
                    $id_cuenta=$row['id_cuenta'];
                    $sql_cuentas=mysqli_query($con,"SELECT * FROM plan_cuentas WHERE id_cuenta= '".$id_cuenta."'");
                    $query_cuentas=mysqli_fetch_array($sql_cuentas);

                ?>
                <input type="hidden" value="<?php echo $descripcion;?>" id="descripcion_act<?php echo $id;?>">
                <input type="hidden" value="<?php echo $row['tipo_opcion'];?>" id="tipo_opcion_act<?php echo $id;?>">
                <input type="hidden" value="<?php echo $row['status'];?>" id="status_act<?php echo $id;?>">
                <tr>						
                    <td><?php echo ucfirst($descripcion); ?></td>
                    <td><?php echo $tipo_opcion; ?></td>
                    <td><?php echo $status; ?></td>
                    <td><?php echo date("d-m-Y", strtotime($datecreated)); ?></td>
                    <td><?php echo $query_cuentas['codigo_cuenta'];?></td>
                    <td><?php echo strtoupper($query_cuentas['nombre_cuenta']);?></td>
                    <td><?php echo strtoupper($usuario); ?></td>
                <td ><span class="pull-right">
                <a href="#" class="btn btn-info btn-xs" title="Editar registro" onclick="editar_opcion_ingresos_egresos('<?php echo $id;?>');" data-toggle="modal" data-target="#nuevaOpcionIngresoEgreso"><i class="glyphicon glyphicon-edit"></i></a> 
                <a href="#" class="btn btn-danger btn-xs" title="Eliminar registro" onclick="eliminar_opcion_ingresos_egresos('<?php echo $id;?>');"><i class="glyphicon glyphicon-trash"></i></a> 	
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

//guardar o editar
if ($action == 'guardar_opcion_ingreso_egreso') {
    $id = intval($_POST['id']);
    $tipo_opcion = $_POST['tipo_opcion'];
    $descripcion = strtolower(strClean($_POST['descripcion_opcion']));
    $status = $_POST['status'];

    if (empty($descripcion)) {
        echo "<script>
            $.notify('Ingrese un nombre o descripción','error');
            </script>";
    } else {
        if (empty($id)) {
            $busca_opcion = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE descripcion = '".$descripcion."' and ruc_empresa = '".$ruc_empresa."' and tipo_opcion='".$tipo_opcion."'");
            $count = mysqli_num_rows($busca_opcion);
            if ($count > 0) {
                echo "<script>
                $.notify('El nombre ya esta registrado','error');
                </script>";
            }else{
            $guarda_opcion = mysqli_query($con, "INSERT INTO opciones_ingresos_egresos (ruc_empresa,
                                                                        tipo_opcion,
                                                                        descripcion,
                                                                        datecreated,
                                                                        id_usuario)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $tipo_opcion . "',
                                                                                    '" . $descripcion . "',
                                                                                    '".date("Y-m-d H:i:s")."',
                                                                                    '" . $id_usuario . "')");
               
               if($guarda_opcion){
               echo "<script>
                $.notify('Guardado','success');
                setTimeout(function () {location.reload()}, 1000);
                </script>";
               }else{
                echo "<script>
                $.notify('No se admite caracteres especiales','error');
                </script>";
               }
            }
        } else {
            //modificar
            $busca_opcion = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id != '".$id."' and ruc_empresa = '".$ruc_empresa."' and descripcion = '".$descripcion."' and tipo_opcion='".$tipo_opcion."' ");
            $count = mysqli_num_rows($busca_opcion);
            if ($count > 0) {
                echo "<script>
                $.notify('La descripción ya esta registrada','error');
                </script>";
            }else{
            $update_opcion = mysqli_query($con, "UPDATE opciones_ingresos_egresos SET tipo_opcion='" . $tipo_opcion . "',
                                                                                    descripcion='" . $descripcion . "',
                                                                                    datecreated='".date("Y-m-d H:i:s")."',
                                                                                    id_usuario='" . $id_usuario . "', 
                                                                                    status='" . $status . "'
                                                                                    WHERE id='" . $id . "'");
                if($update_opcion){
                    echo "<script>
                    $.notify('Opción actualizada','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
                    }else{
                        echo "<script>
                        $.notify('No se admite caracteres especiales','error');
                        </script>";
                    }
                //setTimeout(function (){location.reload()}, 1000);
                }
        }
    }
}
    
?>