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

if ($action == 'eliminar_departamento') {
    $id_departamento=intval($_POST['id_departamento']);
        
    $query_departamentos_usados=mysqli_query($con, "select * from sueldos where departamento='".$id_departamento."' and status='1'");
    $count=mysqli_num_rows($query_departamentos_usados);
    
    if ($count > 0){
        echo "<script>$.notify('No se puede eliminar. Existen registros realizados con este departamento.','error')</script>";
    }else{
            if ($deleteuno=mysqli_query($con,"UPDATE departamentos SET status='0' WHERE id='".$id_departamento."'")){
                echo "<script>$.notify('Departamento eliminado.','success')</script>";
            } else{
                echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
            }
    }
 }

//buscar departamentos
if ($action == 'buscar_departamentos') {
     $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
     $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
     $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
     $aColumns = array('nombre');//Columnas de busqueda
     $sTable = "departamentos";
     $sWhere = "WHERE id_empresa='".$id_empresa."' and status !=0";

     $text_buscar = explode(' ',$q);
     $like="";
     for ( $i=0 ; $i<count($text_buscar) ; $i++ )
     {
         $like .= "%".$text_buscar[$i];
     }

    if ( $_GET['q'] != "" )
    {
        $sWhere = "WHERE (id_empresa='".$id_empresa."' and status !=0 AND ";
        for ( $i=0 ; $i<count($aColumns) ; $i++ )
        {
            $sWhere .= $aColumns[$i]." LIKE '".$like."%' AND id_empresa='".$id_empresa."' and status !=0 OR ";
        }
        $sWhere = substr_replace( $sWhere, "AND id_empresa='".$id_empresa."' and status !=0 ", -3 );
        $sWhere .= ')';
    }
    $sWhere.=" order by $ordenado $por";
    
    
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
    $reload = '../departamentos.php';
    //main query to fetch the data
    $sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows>0){
        
        ?>
        <div class="panel panel-info">
        <div class="table-responsive">
          <table class="table">
            <tr  class="info">
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Nombre</button></th>
                <th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>
                <th class='text-right'>Opciones</th>				
            </tr>
            <?php
            while ($row=mysqli_fetch_array($query)){
                    $id_departamento=$row['id'];
                    $status=$row['status'];
                    $nombre=strtoupper($row['nombre']);
                ?>
                <input type="hidden" value="<?php echo $nombre;?>" id="nombre_dep_mod<?php echo $id_departamento;?>">
				<input type="hidden" value="<?php echo $status;?>" id="status_departamento_mod<?php echo $id_departamento;?>">
                <tr>						
                    <td><?php echo $nombre; ?></td>
                    <td><?php echo $status==1?"<span class='label label-success'>Activo</span>":"<span class='label label-danger'>Inactivo</span>"; ?></td>
                <td><span class="pull-right">
                <?php
                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'departamentos')['u'] == 1) {
                        ?>
                <a href="#" class="btn btn-info btn-xs" title="Editar departamento" onclick="editar_departamento('<?php echo $id_departamento;?>');" data-toggle="modal" data-target="#modalDepartamentos"><i class="glyphicon glyphicon-edit"></i></a> 
                <?php
                    }
                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'departamentos')['u'] == 1) {
                        ?>
                <a href="#" class="btn btn-danger btn-xs" title="Eliminar departamento" onclick="eliminar_departamento('<?php echo $id_departamento;?>');"><i class="glyphicon glyphicon-trash"></i></a> 	
                <?php
                    }
                    ?>  
                    </span></td> 
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

//guardar o editar departamentos
if ($action == 'guardar_departamento') {
    $id_departamento = intval($_POST['id_departamento']);
    $nombre = strClean($_POST['nombre']);
    $status = $_POST['status'];
    
    if (empty($nombre)) {
        echo "<script>
            $.notify('Ingrese nombre del departamento','error');
            </script>";
    } else {
        if (empty($id_departamento)) {
            $busca_departamento = mysqli_query($con, "SELECT * FROM departamentos WHERE nombre = '".$nombre."' and id_empresa = '".$id_empresa."' and status !='0'");
            $count = mysqli_num_rows($busca_departamento);
            if ($count > 0) {
                echo "<script>
                $.notify('El departamento ya esta registrado','error');
                </script>";
            }else{
            $guarda_departamento = mysqli_query($con, "INSERT INTO departamentos (id_empresa,
                                                                        nombre,
                                                                        id_usuario,
                                                                        status)
                                                                            VALUES ('" . $id_empresa . "',
                                                                                    '" . $nombre . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $status."')");
               
               if($guarda_departamento){
               echo "<script>
                $.notify('Departamento registrado','success');
                document.querySelector('#formDepartamentos').reset();
                load(1);
                </script>";
               }else{
                echo "<script>
                $.notify('No se admite caracteres especiales','error');
                </script>";
               }
            }
        } else {
            //modificar el departamento
            $busca_departamento = mysqli_query($con, "SELECT * FROM departamentos WHERE id != '".$id_departamento."' and nombre = '".$nombre."' and id_empresa = '".$id_empresa."' and status !='0'");
            $count = mysqli_num_rows($busca_departamento);
            if ($count > 0) {
                echo "<script>
                $.notify('El departamento ya esta registrado','error');
                </script>";
            }else{
            $update_departamento = mysqli_query($con, "UPDATE departamentos SET nombre='" . $nombre . "',
                                                                        id_usuario='" . $id_usuario . "',
                                                                        status='" . $status . "' WHERE id ='".$id_departamento."'");
                if($update_departamento){
                    echo "<script>
                    $.notify('Departamento actualizado','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
                    }else{
                        echo "<script>
                        $.notify('No se admite caracteres especiales','error');
                        </script>";
                    }
                }
        }
    }
}
    
?>