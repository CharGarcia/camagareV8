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


if ($action == 'eliminar_key') {
    $id_key = intval($_POST['id_key']);
    if ($deleteuno = mysqli_query($con, "UPDATE localizacion SET status='0' WHERE id='" . $id_key . "'")) {
        echo "<script>$.notify('Key eliminada.','success')</script>";
    } else {
        echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
    }
}

if ($action == 'buscar_key') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $aColumns = array('key_google'); //Columnas de busqueda
    $sTable = "localizacion";
    $sWhere = "WHERE ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' ";
    if ($_GET['q'] != "") {
        $sWhere = "WHERE (ruc_empresa ='" .  $ruc_empresa . " ' and status != '0' AND ";

        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' OR ";
        }

        $sWhere = substr_replace($sWhere, "AND ruc_empresa = '" .  $ruc_empresa . "' and status != '0' ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by key_google asc ";
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
                        <th>Key google maps</th>
                        <th>Status</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_key = $row['id'];
                        $status = $row['status'];
                        $key_google = $row['key_google'];
                    ?>
                        <input type="hidden" value="<?php echo $key_google; ?>" id="key_google<?php echo $id_key; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status<?php echo $id_key; ?>">
                        <tr>
                            <td class='col-xs-8'><?php echo $key_google; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td class='text-right'>
                                <?php
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'localizacion')['u'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-info btn-sm' title='Editar' onclick="editar_key('<?php echo $id_key; ?>');" data-toggle="modal" data-target="#localizacion"><i class="glyphicon glyphicon-edit"></i></a>
                                <?php
                                }
                                if (getPermisos($con, $id_usuario, $ruc_empresa, 'localizacion')['d'] == 1) {
                                ?>
                                    <a href="#" class='btn btn-danger btn-sm' title='Eliminar' onclick="eliminar_key('<?php echo $id_key; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                <?php
                                }
                                ?>
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

//inicio guardar y editar 
if ($action == 'guardar_key') {
    $id_key = intval($_POST['id_key']);
    $key_google = $_POST['key_google'];
    $status = $_POST['status'];

    if (empty($key_google)) {
        echo "<script>
				$.notify('Ingrese key google maps','error');
				</script>";
    } else {
        if (empty($id_key)) {
            $busca_key = mysqli_query($con, "SELECT * FROM localizacion WHERE key_google = '" . $key_google . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0 ");
            $count = mysqli_num_rows($busca_key);
            if ($count > 0) {
                echo "<script>
					$.notify('La llave ya esta registrada','error');
					</script>";
            } else {
                $guarda_key = mysqli_query($con, "INSERT INTO localizacion (key_google,
																			ruc_empresa)
																				VALUES ('" . $key_google . "',
																						'" . $ruc_empresa . "')");

                if ($guarda_key) {
                    echo "<script>
					$.notify('Key registrada','success');
					document.querySelector('#formLocalizacion').reset();
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
            $busca_key = mysqli_query($con, "SELECT * FROM localizacion WHERE (key_google = '" . $key_google . "' and id != '" . $id_key . "' and ruc_empresa = '" . $ruc_empresa . "' and status !=0) ");
            $count = mysqli_num_rows($busca_key);
            if ($count > 0) {
                echo "<script>
					$.notify('La key ya esta registrada','error');
					</script>";
            } else {
                $update_key = mysqli_query($con, "UPDATE localizacion SET key_google='" . $key_google . "',	status='" . $status . "' 	WHERE id = '" . $id_key . "'");
                if ($update_key) {
                    echo "<script>
						$.notify('Key actualizada','success');
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