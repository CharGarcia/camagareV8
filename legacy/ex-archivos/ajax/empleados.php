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

if ($action == 'eliminar_empleado') {
    $id_empleado = intval($_POST['id_empleado']);

    $query_empleados = mysqli_query($con, "select * from sueldos where id_empleado='" . $id_empleado . "' and status='1'");
    $count = mysqli_num_rows($query_empleados);

    if ($count > 0) {
        echo "<script>$.notify('No se puede eliminar. Existen registros realizados con este empleado.','error')</script>";
    } else {
        if ($deleteuno = mysqli_query($con, "UPDATE empleados SET status='0' WHERE id='" . $id_empleado . "'")) {
            echo "<script>$.notify('Empleado eliminado.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar empleados
if ($action == 'buscar_empleados') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('documento', 'nombres_apellidos', 'direccion', 'email', 'telefono'); //Columnas de busqueda
    $sTable = "empleados as emp LEFT JOIN bancos_ecuador as ban ON ban.id_bancos=emp.id_banco";
    $sWhere = "WHERE emp.id_empresa='" . $id_empresa . "' and emp.status !=0 ";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (emp.id_empresa='" . $id_empresa . "' and emp.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND emp.id_empresa='" . $id_empresa . "' and emp.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, "AND emp.id_empresa='" . $id_empresa . "' and emp.status !=0 ", -3);
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
    $reload = '../empleados.php';
    //main query to fetch the data
    $sql = "SELECT emp.tipo_id as tipo_id, emp.id as id, emp.documento as documento, emp.nombres_apellidos as nombres_apellidos, emp.direccion as direccion
    , emp.email as email, emp.telefono as telefono, emp.sexo as sexo,
     emp.fecha_nacimiento as fecha_nacimiento, emp.status as status, ban.nombre_banco as banco,
     emp.tipo_cta as tipo_cta, emp.numero_cta as numero_cta, emp.id_banco as id_banco FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("documento");'>Documento</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombres_apellidos");'>Nombres_apellidos</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("direccion");'>Dirección</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("email");'>Correo</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("sexo");'>Sexo</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("fecha_nacimiento");'>Nacimiento</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("id_banco");'>Banco</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_cta");'>Tipo</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_cta");'>Cuenta</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("status");'>Status</button></th>

                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_empleado = $row['id'];
                        $tipo_id = $row['tipo_id'];
                        $status = $row['status'];
                        $id_banco = $row['id_banco'];
                        $nombre = strtoupper($row['nombres_apellidos']);
                        $documento = $row['documento'];
                        $direccion = strtoupper($row['direccion']);
                        $correo = $row['email'];
                        $sexo = $row['sexo'];
                        $telefono = $row['telefono'];
                        if ($sexo == 1) {
                            $sexo = "Masculino";
                        } else if ($sexo == 2) {
                            $sexo = "Femenino";
                        } else {
                            $sexo = "Otro";
                        }

                        $fecha_nacimiento = $row['fecha_nacimiento'];
                        $banco = $row['banco'];
                        $tipo_cta = $row['tipo_cta'];
                        if ($tipo_cta == 1) {
                            $tipo_cta = "Ahorros";
                        } else if ($tipo_cta == 2) {
                            $tipo_cta = "Corriente";
                        } else if ($tipo_cta == 3) {
                            $tipo_cta = "Virtual";
                        } else {
                            $tipo_cta = "";
                        }
                        $numero_cta = $row['numero_cta'];
                    ?>
                        <input type="hidden" value="<?php echo $tipo_id; ?>" id="tipo_documento_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $documento; ?>" id="documento_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $nombre; ?>" id="nombre_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $direccion; ?>" id="direccion_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $correo; ?>" id="email_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $telefono; ?>" id="telefono_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $row['sexo']; ?>" id="sexo_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo date('d-m-Y', strtotime($fecha_nacimiento)); ?>" id="nacimiento_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $id_banco; ?>" id="id_banco_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $row['tipo_cta']; ?>" id="tipo_cta_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $numero_cta; ?>" id="numero_cta_emp_mod<?php echo $id_empleado; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status_emp_mod<?php echo $id_empleado; ?>">
                        <tr>
                            <td><?php echo $documento; ?></td>
                            <td><?php echo $nombre; ?></td>
                            <td><?php echo $direccion; ?></td>
                            <td><?php echo $correo; ?></td>
                            <td><?php echo $sexo; ?></td>
                            <td><?php echo date('d-m-Y', strtotime($fecha_nacimiento)); ?></td>
                            <td><?php echo $banco; ?></td>
                            <td><?php echo $tipo_cta; ?></td>
                            <td><?php echo $numero_cta; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'empleados')['u'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Editar empleado" onclick="editar_empleado('<?php echo $id_empleado; ?>');" data-toggle="modal" data-target="#modalEmpleados"><i class="glyphicon glyphicon-edit"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'empleados')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar empleado" onclick="eliminar_empleado('<?php echo $id_empleado; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="11"><span class="pull-right">
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

//guardar o editar empleados
if ($action == 'guardar_empleado') {
    $id_empleado = intval($_POST['id_empleado']);
    $tipo_id = $_POST['tipo_id'];
    $documento = strClean($_POST['documento']);
    $nombres = strClean($_POST['nombres']);
    $mail = strClean($_POST['mail']);
    $direccion = strClean($_POST['direccion']);
    $telefono = $_POST['telefono'];
    $sexo = $_POST['sexo'];
    $nacimiento = date('Y-m-d', strtotime($_POST['nacimiento']));
    $status = $_POST['status'];
    $banco = $_POST['banco'];
    $tipo_cta = $_POST['tipo_cta'];
    $cuenta = strClean($_POST['cuenta']);

    if (empty($documento)) {
        echo "<script>
            $.notify('Ingrese identificación del empleado','error');
            </script>";
    } else if (empty($nombres)) {
        echo "<script>
        $.notify('Ingrese nombres y apellidos del empleado','error');
        </script>";
    } else if (empty($id_empresa)) {
        echo "<script>
		$.notify('La sesión ha expirado, reingrese al sistema.','error');
		</script>";
    } else {
        if (empty($id_empleado)) {
            $busca_empleado = mysqli_query($con, "SELECT * FROM empleados WHERE documento = '" . $documento . "' and id_empresa = '" . $id_empresa . "' and status !=0 ");
            $count = mysqli_num_rows($busca_empleado);
            if ($count > 0) {
                echo "<script>
                $.notify('El empleado ya esta registrado','error');
                </script>";
            } else {
                $guarda_empleado = mysqli_query($con, "INSERT INTO empleados (id_empresa,
                                                                        tipo_id,
                                                                        documento,
                                                                        nombres_apellidos,
                                                                        direccion,
                                                                        email,
                                                                        telefono,
                                                                        sexo,
                                                                        fecha_nacimiento,
                                                                        status,
                                                                        id_usuario,
                                                                        id_banco,
                                                                        tipo_cta,
                                                                        numero_cta)
                                                                            VALUES ('" . $id_empresa . "',
                                                                                    '" . $tipo_id . "',
                                                                                    '" . $documento . "',
                                                                                    '" . $nombres . "',
                                                                                    '" . $direccion . "',
                                                                                    '" . $mail . "',
                                                                                    '" . $telefono . "',
                                                                                    '" . $sexo . "',
                                                                                    '" . $nacimiento . "',
                                                                                    '" . $status . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $banco . "',
                                                                                    '" . $tipo_cta . "',
                                                                                    '" . $cuenta . "')");

                if ($guarda_empleado) {
                    echo "<script>
                $.notify('Empleado registrado','success');
                document.querySelector('#formEmpleados').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No se admite caracteres especiales','error');
                </script>";
                }
            }
        } else {
            //modificar el empleado
            $busca_empleado = mysqli_query($con, "SELECT * FROM empleados WHERE (documento = '" . $documento . "' and id != '" . $id_empleado . "' and id_empresa = '" . $id_empresa . "' and status !=0) ");
            $count = mysqli_num_rows($busca_empleado);
            if ($count > 0) {
                echo "<script>
                $.notify('El empleado ya esta registrado','error');
                </script>";
            } else {
                $update_empleado = mysqli_query($con, "UPDATE empleados SET tipo_id='" . $tipo_id . "',
                                                                            documento='" . $documento . "',
                                                                            nombres_apellidos='" . $nombres . "',
                                                                            direccion='" . $direccion . "',
                                                                            email='" . $mail . "',
                                                                            telefono='" . $telefono . "',
                                                                            sexo='" . $sexo . "',
                                                                            fecha_nacimiento='" . $nacimiento . "',
                                                                            status='" . $status . "',
                                                                            id_usuario='" . $id_usuario . "',
                                                                            id_banco='" . $banco . "',
                                                                            tipo_cta='" . $tipo_cta . "',
                                                                            numero_cta='" . $cuenta . "' 
                                                                            WHERE id ='" . $id_empleado . "'");
                if ($update_empleado) {
                    echo "<script>
                    $.notify('Empleado actualizado','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
                } else {
                    echo "<script>
                        $.notify('No se admite caracteres especiales','error');
                        </script>";
                }
            }
        }
    }
}

?>