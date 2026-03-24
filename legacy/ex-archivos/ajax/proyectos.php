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


if ($action == 'eliminar_proyecto') {
    $id_proyecto = intval($_POST['id_proyecto']);

    //$query_bodegas = mysqli_query($con, "SELECT * from inventarios WHERE id_bodega='" . $id_bodega . "' and ruc_empresa = '" . $ruc_empresa . "'");
    //$count = mysqli_num_rows($query_bodegas);

    if ($id_proyecto) {
        echo "<script>$.notify('No se puede eliminar. Este proyecto tiene registros.','error')</script>";
    } else {
        if ($deleteuno = mysqli_query($con, "UPDATE proyectos SET status='0' WHERE id='" . $id_proyecto . "'")) {
            echo "<script>$.notify('Proyecto eliminado.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

if ($action == 'buscar_proyectos') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $aColumns = array('nombre'); //Columnas de busqueda
    $sTable = "proyectos";
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
    $reload = '../bodegas.php';
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
                        <th>Status</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_proyecto = $row['id'];
                        $status = $row['status'];
                        $nombre = strtoupper($row['nombre']);
                    ?>
                        <input type="hidden" value="<?php echo $nombre; ?>" id="nombre_proyecto<?php echo $id_proyecto; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status_proyecto<?php echo $id_proyecto; ?>">
                        <tr>
                            <td class='col-xs-4'><?php echo $nombre; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td class='text-right'>
                                <?php
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'proyectos')['u'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-info btn-sm' title='Editar proyecto' onclick="editar_proyecto('<?php echo $id_proyecto; ?>');" data-toggle="modal" data-target="#proyectos"><i class="glyphicon glyphicon-edit"></i></a>
                                <?php
                                }
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'proyectos')['d'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-danger btn-sm' title='Eliminar proyecto' onclick="eliminar_proyecto('<?php echo $id_proyecto; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                <?php
                                }
                                ?>
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="5"><span class="pull-right">
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
if ($action == 'guardar_proyecto') {
    $id_proyecto = intval($_POST['id_proyecto']);
    $nombre_proyecto = strClean($_POST['nombre_proyecto']);
    $status = $_POST['status'];

    if (empty($nombre_proyecto)) {
        echo "<script>
				$.notify('Ingrese nombre de proyecto','error');
				</script>";
    } else {
        if (empty($id_proyecto)) {
            $busca_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE nombre = '" . $nombre_proyecto . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0 ");
            $count = mysqli_num_rows($busca_proyecto);
            if ($count > 0) {
                echo "<script>
					$.notify('El proyecto ya esta registrado','error');
					</script>";
            } else {
                $guarda_proyecto = mysqli_query($con, "INSERT INTO proyectos (nombre,
																			ruc_empresa)
																				VALUES ('" . $nombre_proyecto . "',
																						'" . $ruc_empresa . "')");

                if ($guarda_proyecto) {
                    echo "<script>
					$.notify('Proyecto registrado','success');
					document.querySelector('#formProyecto').reset();
					load(1);
					</script>";
                } else {
                    echo "<script>
					$.notify('Intente de nuevo','error');
					</script>";
                }
            }
        } else {
            //modificar la proyecto
            $busca_proyecto = mysqli_query($con, "SELECT * FROM proyectos WHERE (nombre = '" . $nombre_proyecto . "' and id != '" . $id_proyecto . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0) ");
            $count = mysqli_num_rows($busca_proyecto);
            if ($count > 0) {
                echo "<script>
					$.notify('El proyecto ya esta registrado','error');
					</script>";
            } else {
                $update_proyecto = mysqli_query($con, "UPDATE proyectos SET nombre='" . $nombre_proyecto . "',	status='" . $status . "' 	WHERE id = '" . $id_proyecto . "'");
                if ($update_proyecto) {
                    echo "<script>
						$.notify('Proyecto actualizado','success');
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