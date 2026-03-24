<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php"); //include pagination file
require_once("../helpers/helpers.php"); //include pagination file
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

//quitar tarea
if ($action == 'quitar_tarea') {
    $id_tarea = intval($_GET['id_tarea']);
    $id_usuario = intval($_GET['id_usuario']);
    $query_quitar = mysqli_query($con, "DELETE FROM usuarios_tareas WHERE id_tarea='" . $id_tarea . "' and id_usuario='" . $id_usuario . "'");
    if ($query_quitar) {
        echo "<script>$.notify('Tarea eliminada.','success')</script>";
    } else {
        echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
    }
}

//agregar tarea
if ($action == 'asignar_tarea') {
    $id_tarea = intval($_GET['id_tarea']);
    $id_usuario = intval($_GET['id_usuario']);
    $guarda = mysqli_query($con, "INSERT INTO usuarios_tareas (id_tarea, id_usuario) 
							VALUES ('" . $id_tarea . "', '" . $id_usuario . "')");
    if ($guarda) {
        echo "<script>$.notify('Tarea asignada.','success')</script>";
    } else {
        echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
    }
}

if ($action == 'asignaciones') {
    $id_empresa = intval($_GET['id_empresa']);
    $id_tarea = intval($_GET['id_tarea']);
    $query_usuarios_asignados = mysqli_query($con, "SELECT empre.nombre as empresa, emp.id_empresa as id_empresa, usu.id as id_usu, usu.nombre as usuario_empresa 
		FROM empresa_asignada as emp INNER JOIN usuarios as usu ON usu.id=emp.id_usuario
        INNER JOIN empresas as empre On empre.id=emp.id_empresa
		WHERE emp.id_empresa='" . $id_empresa . "' and usu.estado='1' order by usu.nombre asc");
?>
    <div class="table-responsive">
        <div class="panel panel-info" style="max-height:300px;overflow-y: auto;">
            <table class="table table-hover">
                <tr class="info">
                    <th>Usuarios</th>
                    <th>Estado</th>
                </tr>
                <?php
                while ($row_usuarios = mysqli_fetch_array($query_usuarios_asignados)) {
                    $usuario_empresa = strtoupper($row_usuarios['usuario_empresa']);
                    $id_usu_empresa = $row_usuarios['id_usu'];
                    $id_empresa = $row_usuarios['id_empresa'];
                    $empresa = $row_usuarios['empresa'];

                    $query_tarea_asignada = mysqli_query($con, "SELECT * FROM usuarios_tareas WHERE id_tarea='" . $id_tarea . "' and id_usuario='" . $id_usu_empresa . "' ");
                    $row_asignados = mysqli_fetch_array($query_tarea_asignada);
                    $id_usuario_asignado = isset($row_asignados['id']) ? $row_asignados['id'] : 0;
                    $id_usu_res = isset($row_asignados['id_usuario']) ? "1" : 0;

                ?>
                    <tr>
                        <td><?php echo $usuario_empresa; ?></td>
                        <?php
                        //cuando suma 2 quiere decir que existe un registro lo cual da a entender que ese usuario no tiene acceso a esa tarea
                        if ($id_usu_res == '1') {
                        ?>
                            <td><a class='btn btn-success btn-sm' title='Quitar Tarea' onclick="quitar_tarea('<?php echo $id_tarea; ?>', '<?php echo $id_usu_empresa; ?>', '<?php echo $id_empresa; ?>', '<?php echo $empresa; ?>');"><i class="glyphicon glyphicon-ok"></i></a></td>

                        <?php
                        } else {
                        ?>
                            <td><a class='btn btn-danger btn-sm' title='Asignar tarea' onclick="asignar_tarea('<?php echo $id_tarea; ?>', '<?php echo $id_usu_empresa; ?>', '<?php echo $id_empresa; ?>', '<?php echo $empresa; ?>');"><i class="glyphicon glyphicon-remove"></i></a></td>
                        <?php
                        }
                        ?>
                    </tr>
                <?php
                }
                ?>
            </table>
        </div>
    </div>
    <?php
}

if ($action == 'eliminar_tarea') {
    $id_tarea = intval($_POST['id_tarea']);
    $estado = intval($_POST['estado']);

    if ($estado == '2') {
        echo "<script>$.notify('No se puede eliminar su estado es realizada.','error')</script>";
    } else {

        $deleteTarea = mysqli_query($con, "DELETE FROM tareas_por_hacer WHERE id='" . $id_tarea . "'");
        $deleteAsignados = mysqli_query($con, "DELETE FROM usuarios_tareas WHERE id_tarea='" . $id_tarea . "'");

        if ($deleteTarea && $deleteAsignados) {
            echo "<script>$.notify('Tarea eliminada.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar tareas
if ($action == 'buscar_tareas') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $estado = mysqli_real_escape_string($con, (strip_tags($_GET['estado'], ENT_QUOTES)));
    $anio_tarea = mysqli_real_escape_string($con, (strip_tags($_GET['anio_tarea'], ENT_QUOTES)));
    $mes_tarea = mysqli_real_escape_string($con, (strip_tags($_GET['mes_tarea'], ENT_QUOTES)));
    $tarea = mysqli_real_escape_string($con, (strip_tags($_GET['tarea'], ENT_QUOTES)));
    $empresa = mysqli_real_escape_string($con, (strip_tags($_GET['empresa'], ENT_QUOTES)));

    if (empty($anio_tarea)) {
        $opciones_anio_tarea = "";
    } else {
        $opciones_anio_tarea = " and year(tar.fecha_a_realizar) = '" . $anio_tarea . "' ";
    }
    if (empty($mes_tarea)) {
        $opciones_mes_tarea = "";
    } else {
        $opciones_mes_tarea = " and month(tar.fecha_a_realizar) = '" . $mes_tarea . "' ";
    }
    if (empty($tarea)) {
        $opciones_tarea = "";
    } else {
        $opciones_tarea = " and tar.id_obligacion = '" . $tarea . "' ";
    }
    if (empty($empresa)) {
        $opciones_empresa = "";
    } else {
        $opciones_empresa = " and tar.id_empresa = '" . $empresa . "' ";
    }

    switch ($estado) {
        case '1':
            $opciones_options = " and tar.status = '1'";
            break;
        case '2':
            $opciones_options = " and tar.status = '2'";
            break;
        case '3':
            $opciones_options = " and tar.fecha_a_realizar <= CURDATE() and tar.status = '1'";
            break;
        case '4':
            $opciones_options = " and tar.fecha_a_realizar BETWEEN CURDATE() and DATE_ADD(CURDATE(), INTERVAL 10 DAY) and tar.status = '1'";
            break;
        case '5':
            $opciones_options = " and tar.fecha_a_realizar BETWEEN CURDATE() and DATE_ADD(CURDATE(), INTERVAL 5 DAY) and tar.status = '1'";
            break;
        default:
            $opciones_options = "";
            break;
    }


    $aColumns = array('obli.descripcion', 'emp.nombre', 'emp.nombre_comercial', 'emp.ruc', 'tar.detalle'); //Columnas de busqueda
    $sTable = "tareas_por_hacer as tar INNER JOIN obligaciones_empresas as obli ON obli.id=tar.id_obligacion 
    INNER JOIN empresas as emp ON emp.id=tar.id_empresa INNER JOIN usuarios_tareas as usu_tar ON usu_tar.id_tarea=tar.id";
    $sWhere = "WHERE usu_tar.id_usuario='" . $id_usuario . "' $opciones_anio_tarea $opciones_mes_tarea $opciones_tarea $opciones_options $opciones_empresa";
    $fecha_actual = date("Y/m/d");
    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (usu_tar.id_usuario='" . $id_usuario . "' $opciones_anio_tarea $opciones_mes_tarea $opciones_tarea $opciones_options $opciones_empresa AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND usu_tar.id_usuario='" . $id_usuario . "' $opciones_anio_tarea $opciones_mes_tarea $opciones_tarea $opciones_options $opciones_empresa OR ";
        }
        $sWhere = substr_replace($sWhere, "AND usu_tar.id_usuario='" . $id_usuario . "' $opciones_anio_tarea $opciones_mes_tarea $opciones_tarea $opciones_options $opciones_empresa ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by tar.status asc, tar.fecha_a_realizar desc, emp.nombre asc";

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
    $sql = "SELECT tar.id as id, tar.fecha_a_realizar as fecha, emp.id as id_empresa,
    emp.nombre as empresa, obli.descripcion as tarea, tar.detalle as detalle,
    tar.repetir as repetir, tar.status as status, obli.id as id_obligacion FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Fecha</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Tarea</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Empresa</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Detalle</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Estado</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info">Repetir</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_tarea = $row['id'];
                        $id_obligacion = $row['id_obligacion'];
                        $id_empresa = $row['id_empresa'];
                        $tarea = strtoupper($row['tarea']);
                        $fecha = $row['fecha'];
                        $empresa = strtoupper($row['empresa']);
                        $detalle = strtoupper($row['detalle']);
                        $repetir = $row['repetir'];
                        if ($repetir == 0) {
                            $repetir_final = "No";
                        } elseif ($repetir == 1) {
                            $repetir_final = "Mensual";
                        } else {
                            $repetir_final = "Anual";
                        }

                    ?>
                        <input type="hidden" value="<?php echo date('Y-m-d', strtotime($fecha)); ?>" id="fecha<?php echo $id_tarea; ?>">
                        <input type="hidden" value="<?php echo $id_empresa; ?>" id="id_empresa<?php echo $id_tarea; ?>">
                        <input type="hidden" value="<?php echo $id_obligacion; ?>" id="id_obligacion<?php echo $id_tarea; ?>">
                        <input type="hidden" value="<?php echo $detalle; ?>" id="detalle<?php echo $id_tarea; ?>">
                        <input type="hidden" value="<?php echo $repetir; ?>" id="repetir<?php echo $id_tarea; ?>">
                        <input type="hidden" value="<?php echo $row['status']; ?>" id="status<?php echo $id_tarea; ?>">
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($fecha)); ?></td>
                            <td><?php echo $tarea; ?></td>
                            <td><?php echo $empresa; ?></td>
                            <td><?php echo $detalle; ?></td>
                            <td>
                                <?php
                                if ($row['status'] == 1 && date('Y/m/d', strtotime($fecha)) < $fecha_actual) {
                                ?>
                                    <a class="btn btn-danger btn-sm" title="Actualizar estado" onclick="cambiar_estado('<?php echo $id_tarea; ?>','<?php echo $id_empresa; ?>','<?php echo $id_obligacion; ?>','<?php echo date('Y-m-d', strtotime($fecha)); ?>','<?php echo $repetir; ?>','<?php echo $detalle; ?>', '2');">Vencida</a>
                                <?php
                                } elseif ($row['status'] == 2) {
                                ?>
                                    <a class="btn btn-success btn-sm" title="Actualizar estado" onclick="cambiar_estado('<?php echo $id_tarea; ?>','<?php echo $id_empresa; ?>','<?php echo $id_obligacion; ?>','<?php echo date('Y-m-d', strtotime($fecha)); ?>','<?php echo $repetir; ?>','<?php echo $detalle; ?>', '1');">Realizada</a>
                                <?php
                                } else {
                                ?>
                                    <a class="btn btn-warning btn-sm" title="Actualizar estado" onclick="cambiar_estado('<?php echo $id_tarea; ?>','<?php echo $id_empresa; ?>','<?php echo $id_obligacion; ?>','<?php echo date('Y-m-d', strtotime($fecha)); ?>','<?php echo $repetir; ?>','<?php echo $detalle; ?>', '2');">Por realizar</a>
                                <?php
                                }
                                ?>
                            </td>
                            <td><?php echo strtoupper($repetir_final); ?></td>
                            <?php
                            if ($row['status'] == 1) {
                            ?>
                                <td class='col-md-2'><span class="pull-right">
                                        <a class="btn btn-info btn-sm" title="Editar tarea" onclick="editar_tarea('<?php echo $id_tarea; ?>');" data-toggle="modal" data-target="#modalTareas"><i class="glyphicon glyphicon-edit"></i></a>
                                        <a class="btn btn-info btn-sm" title="Agregar tarea a otro usuario" onclick="agregar_usuario('<?php echo $id_tarea; ?>', '<?php echo $id_empresa; ?>', '<?php echo $empresa; ?>');" data-toggle="modal" data-target="#tarea_asignadas"><i class="glyphicon glyphicon-paste"></i></a>
                                        <a class="btn btn-danger btn-sm" title="Eliminar tarea" onclick="eliminar_tarea('<?php echo $id_tarea; ?>', '<?php echo $row['status']; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    </span></td>
                            <?php
                            } else {
                            ?>
                                <td></td>
                            <?php
                            }
                            ?>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="7"><span class="pull-right">
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
if ($action == 'guardar_tarea') {
    $idTarea = intval($_POST['idTarea']);
    $id_empresa = $_POST['id_empresa'];
    $id_obligacion = $_POST['id_obligacion'];
    $fecha_realizar = $_POST['fecha_realizar'];
    $fecha_actual = date("d/m/Y");
    $repetir = $_POST['repetir'];
    $detalle = strClean($_POST['observacion']);
    $status = $_POST['status'];

    if (empty($id_empresa)) {
        echo "<script>
            $.notify('Seleccione una empresa','error');
            </script>";
    } else if (empty($id_obligacion)) {
        echo "<script>
        $.notify('Seleccione una tarea','error');
        </script>";
    } else if (empty($fecha_realizar)) {
        echo "<script>
        $.notify('Ingrese una fecha','error');
        </script>";
    } else if (!date($fecha_realizar)) {
        echo "<script>
        $.notify('Ingrese fecha correcta.','error');
                    </script>";
    } else if (DateTime::createFromFormat('d-m-Y', $fecha_realizar) < DateTime::createFromFormat('d-m-Y', $fecha_actual)) {
        echo "<script>
                    $.notify('La fecha a realizar la tarea, no puede ser menor a la fecha actual.','error');
                    </script>";
    } else {
        if (empty($idTarea)) {
            /*    $busca_tareas = mysqli_query($con, "SELECT * FROM tareas_por_hacer 
            WHERE id_empresa = '" . $id_empresa . "' and id_obligacion = '" . $id_obligacion . "' and month(fecha_a_realizar) = '" . date('m', strtotime($fecha_realizar)) . "' and year(fecha_a_realizar) = '" . date('Y', strtotime($fecha_realizar)) . "' and status='1'");
            $count = mysqli_num_rows($busca_tareas);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe una tarea igual registrada y pendiente por realizar','error');
                </script>";
            } else { */
            $guarda_tarea = mysqli_query($con, "INSERT INTO tareas_por_hacer (id_obligacion,
                                                                        id_empresa,
                                                                        detalle,
                                                                        repetir,
                                                                        id_usuario,
                                                                        fecha_a_realizar)
                                                                            VALUES ('" . $id_obligacion . "',
                                                                                    '" . $id_empresa . "',
                                                                                    '" . $detalle . "',
                                                                                    '" . $repetir . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $fecha_realizar . "')");
            $id_tarea = mysqli_insert_id($con);
            $guarda_tarea_asiganada = mysqli_query($con, "INSERT INTO usuarios_tareas (id_tarea, id_usuario)
                                                                            VALUES ('" . $id_tarea . "',
                                                                                    '" . $id_usuario . "')");
            if ($guarda_tarea) {
                echo "<script>
                $.notify('Tarea registrada','success');
                document.querySelector('#guardar_tarea').reset();
                setTimeout(function () {location.reload()}, 1000);
                </script>";
            } else {
                echo "<script>
                $.notify('No se ha registrado la tarea, intente de nuevo','error');
                </script>";
            }
            //}
        } else {
            //modificar la tarea
            $busca_tarea = mysqli_query($con, "SELECT * FROM tareas_por_hacer WHERE id != '" . $idTarea . "' and id_obligacion = '" . $id_obligacion . "' and id_empresa = '" . $id_empresa . "' and fecha_a_realizar ='" . $fecha_realizar . "' and status = '2' ");
            $count = mysqli_num_rows($busca_tarea);
            if ($count > 0) {
                echo "<script>
                $.notify('La tarea ya esta registrada','error');
                </script>";
            } else {
                //si estatus es realizada y repetir es mensual y anual
                $fecha_realizar = date('d/m/Y', strtotime($_POST['fecha_realizar']));
                $fecha_realizar_obj = DateTime::createFromFormat('d/m/Y', $fecha_realizar);
                //cuando se debe repetir el prox mes
                if ($status == '2' &&  $repetir == 1) {
                    $fecha_realizar_obj->modify('+1 month');
                    $fecha_incrementada = $fecha_realizar_obj->format('Y/m/d');
                    repetir_tarea($con, $idTarea, $id_empresa, $id_obligacion, $fecha_incrementada, $detalle, $repetir, $id_usuario);
                }

                //cuando se debe repetir el prox año
                if ($status == '2' &&  $repetir == 2) {
                    $fecha_realizar_obj->modify('+1 year');
                    $fecha_incrementada = $fecha_realizar_obj->format('Y/m/d');
                    repetir_tarea($con, $idTarea, $id_empresa, $id_obligacion, $fecha_incrementada, $detalle, $repetir, $id_usuario);
                }

                //actualizar tarea
                $update_tarea = mysqli_query($con, "UPDATE tareas_por_hacer SET id_obligacion='" . $id_obligacion . "',
                                                                                id_empresa='" . $id_empresa . "',
                                                                                detalle='" . $detalle . "',
                                                                                repetir='" . $repetir . "',
                                                                                fecha_a_realizar='" . date('Y/m/d', strtotime($_POST['fecha_realizar'])) . "',
                                                                                status='" . $status . "' WHERE id='" . $idTarea . "'");
                if ($update_tarea) {
                    echo "<script>
                    $.notify('Tarea actualizada','success');
                        </script>";
                } else {
                    echo "<script>
                    $.notify('No se ha registrado la tarea, intente de nuevo','error');
                        </script>";
                }
            }
        }
    }
}

function repetir_tarea($con, $idTarea, $id_empresa, $id_obligacion, $fecha_incrementada, $detalle, $repetir, $id_usuario)
{
    //para verificar si existe la tarea ya registrada
    $busca_tarea_sig_mes = mysqli_query($con, "SELECT * FROM tareas_por_hacer WHERE id != '" . $idTarea . "' and id_empresa = '" . $id_empresa . "' and id_obligacion = '" . $id_obligacion . "' and fecha_a_realizar ='" . $fecha_incrementada . "'");
    $count_sig_mes = mysqli_num_rows($busca_tarea_sig_mes);
    if ($count_sig_mes == 0) {
        //guarda nuevo registro
        $guarda_tarea_sig_mes = mysqli_query($con, "INSERT INTO tareas_por_hacer (id_obligacion,
                                                                                                    id_empresa,
                                                                                                    detalle,
                                                                                                    repetir,
                                                                                                    id_usuario,
                                                                                                    fecha_a_realizar)
                                                                                            VALUES ('" . $id_obligacion . "',
                                                                                                    '" . $id_empresa . "',
                                                                                                    '" . $detalle . "',
                                                                                                    '" . $repetir . "',
                                                                                                    '" . $id_usuario . "',
                                                                                                    '" . $fecha_incrementada . "')");
        $id_tarea_sig_mes = mysqli_insert_id($con);
        $busca_usuarios_tarea_anterior = mysqli_query($con, "SELECT * FROM usuarios_tareas WHERE id_tarea = '" . $idTarea . "'");
        while ($row_usuario_tarea_mes_sig = mysqli_fetch_array($busca_usuarios_tarea_anterior)) {
            $guarda_usuarios_sig_mes = mysqli_query($con, "INSERT INTO usuarios_tareas (id_tarea, id_usuario)
                                                                                    VALUES ('" . $id_tarea_sig_mes . "',
                                                                                            '" . $row_usuario_tarea_mes_sig['id_usuario'] . "')");
        }
    }
}

?>