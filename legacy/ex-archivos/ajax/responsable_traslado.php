<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_empresa = $_SESSION['id_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';


if ($action == 'eliminar_responsable_traslado') {
    $id = intval($_POST['id']);
    if ($deleteuno = mysqli_query($con, "UPDATE responsable_traslado SET status='0' WHERE id='" . $id . "'")) {
        echo "<script>$.notify('Eliminada.','success')</script>";
    } else {
        echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
    }
}

if ($action == 'buscar_responsable_traslado') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $aColumns = array('nombre', 'correo'); //Columnas de busqueda
    $sTable = "responsable_traslado";
    $sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' OR ";
        }
        $sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by nombre asc ";
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
    $sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table table-hover">
                    <tr class="info">
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Status</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id = $row['id'];
                        $nombre = $row['nombre'];
                        $status = $row['status'];
                        $correo = $row['correo'];
                    ?>
                        <input type="hidden" value="<?php echo $nombre; ?>" id="nombre<?php echo $id; ?>">
                        <input type="hidden" value="<?php echo $correo; ?>" id="correo<?php echo $id; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status<?php echo $id; ?>">
                        <tr>
                            <td class='col-xs-4'><?php echo $nombre; ?></td>
                            <td class='col-xs-4'><?php echo $correo; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td class='text-right'>
                                <?php
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'responsable_traslado')['u'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-info btn-sm' title='Editar' onclick="editar_responsable_traslado('<?php echo $id; ?>');" data-toggle="modal" data-target="#responsable_traslado"><i class="glyphicon glyphicon-edit"></i></a>
                                <?php
                                }
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'responsable_traslado')['d'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-danger btn-sm' title='Eliminar' onclick="eliminar_responsable_traslado('<?php echo $id; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                <?php
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="4"><span class="pull-right">
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

//inicio guardar y editar 
if ($action == 'guardar_responsable_traslado') {
    $id = intval($_POST['id']);
    $nombre = $_POST['nombre'];
    $correo = $_POST['correo'];
    $codigo = md5($_POST['codigo']);
    $status = $_POST['status'];

    if (empty($nombre)) {
        echo "<script>
				$.notify('Ingrese nombre','error');
				</script>";
    } else if (empty($correo)) {
        echo "<script>
                $.notify('Ingrese correo','error');
                </script>";
    } else if (empty($codigo)) {
        echo "<script>
                $.notify('Ingrese código','error');
                </script>";
    } else {
        if (empty($id)) {
            $busca_responsable = mysqli_query($con, "SELECT * FROM responsable_traslado 
            WHERE (nombre = '" . $nombre . "' || codigo = '" . $codigo . "' || correo = '" . $correo . "') and ruc_empresa = '" . $ruc_empresa . "' and status !=0 ");
            $count = mysqli_num_rows($busca_responsable);
            if ($count > 0) {
                echo "<script>
					$.notify('Ya existe un registro con el mismo nombre, correo o código','error');
					</script>";
            } else {
                $guarda_key = mysqli_query($con, "INSERT INTO responsable_traslado (ruc_empresa,
																			codigo,
                                                                            nombre,
                                                                            correo)
																				VALUES ('" . $ruc_empresa . "',
																						'" . $codigo . "',
                                                                                        '" . $nombre . "',
                                                                                        '" . $correo . "')");

                if ($guarda_key) {
                    echo "<script>
					$.notify('Responsable registrado','success');
					document.querySelector('#formresponsable_traslado').reset();
					load(1);
					</script>";
                } else {
                    echo "<script>
					$.notify('Intente de nuevo','error');
					</script>";
                }
            }
        } else {
            //modificar la key
            $busca_responsable = mysqli_query($con, "SELECT * FROM responsable_traslado WHERE ((nombre = '" . $nombre . "' || codigo = '" . $codigo . "' || correo = '" . $correo . "') and id != '" . $id . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0) ");
            $count = mysqli_num_rows($busca_responsable);
            if ($count > 0) {
                echo "<script>
					$.notify('Existe un registro con el mismo nombre, código o correo','error');
					</script>";
            } else {
                $update_key = mysqli_query($con, "UPDATE responsable_traslado SET nombre='" . $nombre . "', correo='" . $correo . "', codigo='" . $codigo . "',	status='" . $status . "' WHERE id = '" . $id . "'");
                if ($update_key) {
                    echo "<script>
						$.notify('Responsable actualizado','success');
						setTimeout(function () {location.reload()}, 1000);
							</script>";
                } else {
                    echo "<script>
							$.notify('Intente de nuevo','error');
							</script>";
                }
            }
        }
    }
}

?>