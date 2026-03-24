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

if ($action == 'eliminar_cliente') {
    $id_cliente = intval($_POST['id_cliente']);
    //CONTAR CUANTOS CLIENTES HAY PARA ELIMINAR
    $query_clientes = mysqli_query($con, "select ruc from clientes where id='" . $id_cliente . "'");
    $resultado_clientes = mysqli_fetch_array($query_clientes);
    $ruc_cliente = $resultado_clientes['ruc'];

    $query_contar_clientes = mysqli_query($con, "select * from clientes where ruc='" . $ruc_cliente . "'");
    $count_ruc = mysqli_num_rows($query_contar_clientes);

    $query_facturas_emitidas = mysqli_query($con, "select * from encabezado_factura where id_cliente='" . $id_cliente . "'");
    $count_facturas_emitidas = mysqli_num_rows($query_facturas_emitidas);

    $query_recibos_emitidas = mysqli_query($con, "select * from encabezado_recibo where id_cliente='" . $id_cliente . "'");
    $count_recibos_emitidas = mysqli_num_rows($query_recibos_emitidas);

    if ($count_facturas_emitidas > 0 || $count_recibos_emitidas > 0) {
        echo "<script>$.notify('No se puede eliminar. Existen registros realizados con este cliente.','error')</script>";
    } else {
        if ($deleteuno = mysqli_query($con, "DELETE FROM clientes WHERE id='" . $id_cliente . "'")) {
            echo "<script>$.notify('Cliente eliminado.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar clientes
if ($action == 'buscar_clientes') {
    $condicion_ruc_empresa = compartirClientesProductos($con, $ruc_empresa)['clientes'];
    // escaping, additionally removing everything that could be (html/javascript-) code
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('cli.nombre', 'cli.ruc', 'cli.email', 'cli.direccion', 'cli.telefono'); //Columnas de busqueda
    $sTable = "clientes as cli LEFT JOIN vendedores as ven ON ven.id_vendedor = cli.id_vendedor";
    $sWhere = "WHERE $condicion_ruc_empresa";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE ($condicion_ruc_empresa AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND $condicion_ruc_empresa OR ";
        }
        $sWhere = substr_replace($sWhere, "AND $condicion_ruc_empresa ", -3);
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
    $reload = '../clientes.php';
    //main query to fetch the data
    $sql = "SELECT cli.id_vendedor as id_vendedor, cli.ciudad as ciudad, cli.provincia as provincia, cli.tipo_id as tipo_id, cli.id as id, cli.nombre as nombre, cli.ruc as ruc, cli.telefono as telefono,
     cli.email as email, cli.direccion as direccion, cli.plazo as plazo, cli.status as status, ven.nombre as vendedor FROM  $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {

?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.nombre");'>Nombre</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.ruc");'>Ruc/Cedula</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.telefono");'>Teléfono</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.email");'>Email</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("ven.nombre");'>Asesor</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.direccion");'>Dirección</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.plazo");'>Crédito</button></th>
                        <th style="padding: 0px;"><button style="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cli.status");'>Status</button></th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_cliente = $row['id'];
                        $nombre_cliente = strtoupper($row['nombre']);
                        $ruc_cliente = $row['ruc'];
                        $telefono_cliente = $row['telefono'];
                        $email_cliente = strtolower($row['email']);
                        $direccion_cliente = strtoupper($row['direccion']);
                        $tipo_id = $row['tipo_id'];
                        $plazo = $row['plazo'];
                        $provincia = $row['provincia'];
                        $ciudad = $row['ciudad'];
                        $status = $row['status'];
                        $id_vendedor = $row['id_vendedor'];
                        $vendedor = strtoupper($row['vendedor']);
                    ?>
                        <input type="hidden" value="<?php echo $nombre_cliente; ?>" id="nombre_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $ruc_cliente; ?>" id="ruc_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $telefono_cliente; ?>" id="telefono_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $email_cliente; ?>" id="email_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $direccion_cliente; ?>" id="direccion_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $tipo_id; ?>" id="tipo_id_cliente<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $plazo; ?>" id="plazo_pago<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $provincia; ?>" id="provincia<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $ciudad; ?>" id="ciudad<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $status; ?>" id="status<?php echo $id_cliente; ?>">
                        <input type="hidden" value="<?php echo $id_vendedor; ?>" id="id_vendedor<?php echo $id_cliente; ?>">
                        <tr>
                            <td><?php echo $nombre_cliente; ?></td>
                            <td><?php echo $ruc_cliente; ?></td>
                            <td><?php echo $telefono_cliente; ?></td>
                            <td><?php echo $email_cliente; ?></td>
                            <td><?php echo $vendedor; ?></td>
                            <td><?php echo $direccion_cliente; ?></td>
                            <td><?php echo $plazo . " Días"; ?></td>
                            <td><?php echo $status == 1 ? "<span class='label label-success'>Activo</span>" : "<span class='label label-danger'>Inactivo</span>"; ?></td>
                            <td><span class="pull-right">
                                    <a href="#" class="btn btn-info btn-xs" title="Editar cliente" onclick="editar_cliente('<?php echo $id_cliente; ?>');" data-toggle="modal" data-target="#nuevoCliente"><i class="glyphicon glyphicon-edit"></i></a>
                                    <a href="#" class="btn btn-danger btn-xs" title="Eliminar cliente" onclick="eliminar_cliente('<?php echo $id_cliente; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <td colspan="9"><span class="pull-right">
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

//guardar o editar clientes
if ($action == 'guardar_cliente') {
    $id_cliente = intval($_POST['id_cliente']);
    $tipo_id = $_POST['tipo_id'];
    if ($tipo_id == "07") {
        $ruc_cliente = "9999999999999";
        $nombre_cliente = "CONSUMIDOR FINAL";
    } else {
        $ruc_cliente = strClean($_POST['ruc']);
        $nombre_cliente = strClean($_POST['nombre']);
    }

    if ($ruc_cliente == "9999999999999") {
        $tipo_id = "07";
    }

    $email_cliente = strClean($_POST['email']);
    $direccion_cliente = strClean($_POST['direccion']);
    $telefono_cliente = strClean($_POST['telefono']);
    $plazo_cliente = intval($_POST['plazo']);
    $provincia = $_POST['provincia'];
    $ciudad = $_POST['ciudad'];
    $status = $_POST['status'];
    $id_vendedor = $_POST['id_vendedor'];

    if (empty($ruc_cliente)) {
        echo "<script>
            $.notify('Ingrese número de identificación','error');
            </script>";
    } else if ($tipo_id == "05" && !validador_cedula($ruc_cliente)) {
        echo "<script>
        $.notify('Cedula Incorrecta','error');
        </script>";
    } else if ($tipo_id == "04" && !validador_ruc($ruc_cliente)) {
        echo "<script>
        $.notify('Ruc Incorrecto','error');
        </script>";
    } else if (empty($nombre_cliente)) {
        echo "<script>
        $.notify('Ingrese nombre del cliente','error');
        </script>";
    } else if (empty($email_cliente)) {
        echo "<script>
        $.notify('Ingrese émail del cliente','error');
        </script>";
    } else if (!validarCorreo($email_cliente)) {
        echo "<script>
        $.notify('Error en mail, puede ingresar varios correos separados por coma y espacio','error');
        </script>";
    } else if ($plazo_cliente < 0) {
        echo "<script>
        $.notify('Ingrese días de plazo','error');
        </script>";
    } else if (empty($ruc_empresa)) {
        echo "<script>
		$.notify('La sesión ha expirado, reingrese al sistema.','error');
		</script>";
    } else if (empty($id_usuario)) {
        echo "<script>
		$.notify('La sesión ha expirado, reingrese al sistema.','error');
		</script>";
    } else {
        if (empty($id_cliente)) {
            $busca_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE ruc = '" . $ruc_cliente . "' and ruc_empresa = '" . $ruc_empresa . "'");
            $count = mysqli_num_rows($busca_cliente);
            if ($count > 0) {
                echo "<script>
                $.notify('El cliente ya esta registrado','error');
                </script>";
            } else {
                $guarda_cliente = mysqli_query($con, "INSERT INTO clientes (ruc_empresa,
                                                                        nombre,
                                                                        tipo_id,
                                                                        ruc,
                                                                        telefono,
                                                                        email,
                                                                        direccion,
                                                                        fecha_agregado,
                                                                        plazo,
                                                                        id_usuario,
                                                                        provincia,
                                                                        ciudad,
                                                                        status,
                                                                        id_vendedor)
                                                                            VALUES ('" . $ruc_empresa . "',
                                                                                    '" . $nombre_cliente . "',
                                                                                    '" . $tipo_id . "',
                                                                                    '" . $ruc_cliente . "',
                                                                                    '" . $telefono_cliente . "',
                                                                                    '" . $email_cliente . "',
                                                                                    '" . $direccion_cliente . "',
                                                                                    '" . date("Y-m-d H:i:s") . "',
                                                                                    '" . $plazo_cliente . "',
                                                                                    '" . $id_usuario . "',
                                                                                    '" . $provincia . "',
                                                                                    '" . $ciudad . "',
                                                                                    '" . $status . "',
                                                                                    '" . $id_vendedor . "')");

                if ($guarda_cliente) {
                    echo "<script>
                $.notify('Cliente registrado','success');
                document.querySelector('#guardar_cliente').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No se admite caracteres especiales','error');
                </script>";
                }
            }
        } else {
            //modificar el cliente
            $busca_cliente = mysqli_query($con, "SELECT * FROM clientes WHERE id != '" . $id_cliente . "' and ruc = '" . $ruc_cliente . "' and ruc_empresa = '" . $ruc_empresa . "'");
            $count = mysqli_num_rows($busca_cliente);
            if ($count > 0) {
                echo "<script>
                $.notify('El cliente ya esta registrado','error');
                </script>";
            } else {
                $update_cliente = mysqli_query($con, "UPDATE clientes SET nombre='" . $nombre_cliente . "',
                                                                        tipo_id='" . $tipo_id . "',
                                                                        ruc='" . $ruc_cliente . "',
                                                                        telefono='" . $telefono_cliente . "',
                                                                        email='" . $email_cliente . "',
                                                                        direccion='" . $direccion_cliente . "',
                                                                        fecha_agregado='" . date("Y-m-d H:i:s") . "',
                                                                    plazo='" . $plazo_cliente . "',
                                                                    id_usuario='" . $id_usuario . "',
                                                                    provincia='" . $provincia . "',
                                                                    ciudad='" . $ciudad . "',
                                                                    status='" . $status . "',
                                                                    id_vendedor='" . $id_vendedor . "'
                                                                    WHERE id='" . $id_cliente . "'");
                if ($update_cliente) {
                    echo "<script>
                    $.notify('Cliente actualizado','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
                } else {
                    echo "<script>
                        $.notify('No se admite caracteres especiales','error');
                        </script>";
                }
                //setTimeout(function (){location.reload()}, 1000);
            }
        }
    }
}

/* if ($action == 'guardar_cliente') {

    $ruc_empresa = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
    $id_usuario  = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 0;

    $id_cliente = isset($_POST['id_cliente']) ? intval($_POST['id_cliente']) : 0;
    $tipo_id    = isset($_POST['tipo_id']) ? $_POST['tipo_id'] : '';

    if ($tipo_id == "07") {
        $ruc_cliente    = "9999999999999";
        $nombre_cliente = "CONSUMIDOR FINAL";
    } else {
        $ruc_cliente    = strClean(isset($_POST['ruc']) ? $_POST['ruc'] : '');
        $nombre_cliente = strClean(isset($_POST['nombre']) ? $_POST['nombre'] : '');
    }

    if ($ruc_cliente == "9999999999999") {
        $tipo_id = "07";
    }

    $email_cliente     = strClean(isset($_POST['email']) ? $_POST['email'] : '');
    $direccion_cliente = strClean(isset($_POST['direccion']) ? $_POST['direccion'] : '');
    $telefono_cliente  = strClean(isset($_POST['telefono']) ? $_POST['telefono'] : '');
    $plazo_cliente     = isset($_POST['plazo']) ? intval($_POST['plazo']) : 0;

    $provincia   = strClean(isset($_POST['provincia']) ? $_POST['provincia'] : '');
    $ciudad      = strClean(isset($_POST['ciudad']) ? $_POST['ciudad'] : '');
    $status      = strClean(isset($_POST['status']) ? $_POST['status'] : '');
    $id_vendedor = isset($_POST['id_vendedor']) ? intval($_POST['id_vendedor']) : 0;

    // Validaciones
    if (empty($ruc_cliente)) {
        notify('Ingrese número de identificación', 'error');
        exit;
    }

    if ($tipo_id == "05" && !validador_cedula($ruc_cliente)) {
        notify('Cédula incorrecta', 'error');
        exit;
    }

    if ($tipo_id == "04" && !validador_ruc($ruc_cliente)) {
        notify('RUC incorrecto', 'error');
        exit;
    }

    if (empty($nombre_cliente)) {
        notify('Ingrese nombre del cliente', 'error');
        exit;
    }

    if (empty($email_cliente)) {
        notify('Ingrese email del cliente', 'error');
        exit;
    }

    if (!validarCorreo($email_cliente)) {
        notify('Error en mail, puede ingresar varios correos separados por coma y espacio', 'error');
        exit;
    }

    if ($plazo_cliente < 0) {
        notify('Ingrese días de plazo', 'error');
        exit;
    }

    if (empty($ruc_empresa) || empty($id_usuario)) {
        notify('La sesión ha expirado, reingrese al sistema.', 'error');
        exit;
    }

    $fecha = date("Y-m-d H:i:s");

    // ==========================
    // INSERT
    // ==========================
    if ($id_cliente == 0) {

        $stmt = mysqli_prepare($con, "SELECT 1 FROM clientes WHERE ruc = ? AND ruc_empresa = ? LIMIT 1");
        if (!$stmt) {
            notify('Error interno (prepare select)', 'error');
            exit;
        }
        mysqli_stmt_bind_param($stmt, "ss", $ruc_cliente, $ruc_empresa);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            notify('El cliente ya está registrado', 'error');
            exit;
        }
        mysqli_stmt_close($stmt);

        $sql = "INSERT INTO clientes
                (ruc_empresa, nombre, tipo_id, ruc, telefono, email, direccion, fecha_agregado, plazo, id_usuario, provincia, ciudad, status, id_vendedor)
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            notify('Error interno (prepare insert)', 'error');
            exit;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssiisssi",
            $ruc_empresa,
            $nombre_cliente,
            $tipo_id,
            $ruc_cliente,
            $telefono_cliente,
            $email_cliente,
            $direccion_cliente,
            $fecha,
            $plazo_cliente,
            $id_usuario,
            $provincia,
            $ciudad,
            $status,
            $id_vendedor
        );

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            echo "<script>
                $.notify('Cliente registrado','success');
                document.querySelector('#guardar_cliente').reset();
                load(1);
            </script>";
            exit;
        } else {
            mysqli_stmt_close($stmt);
            notify('No se pudo guardar el cliente. Revise caracteres y datos.', 'error');
            exit;
        }
    }

    // ==========================
    // UPDATE
    // ==========================
    $stmt = mysqli_prepare($con, "SELECT 1 FROM clientes WHERE id != ? AND ruc = ? AND ruc_empresa = ? LIMIT 1");
    if (!$stmt) {
        notify('Error interno (prepare select update)', 'error');
        exit;
    }

    mysqli_stmt_bind_param($stmt, "iss", $id_cliente, $ruc_cliente, $ruc_empresa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        notify('El cliente ya está registrado', 'error');
        exit;
    }
    mysqli_stmt_close($stmt);

    // Nota: NO actualizo fecha_agregado para no perder histórico.
    $sql = "UPDATE clientes SET
                nombre = ?,
                tipo_id = ?,
                ruc = ?,
                telefono = ?,
                email = ?,
                direccion = ?,
                plazo = ?,
                id_usuario = ?,
                provincia = ?,
                ciudad = ?,
                status = ?,
                id_vendedor = ?
            WHERE id = ? AND ruc_empresa = ?";

    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        notify('Error interno (prepare update)', 'error');
        exit;
    }

    // tipos:
    // nombre(s) tipo_id(s) ruc(s) telefono(s) email(s) direccion(s) plazo(i) id_usuario(i) provincia(s) ciudad(s) status(s) id_vendedor(i) id(i) ruc_empresa(s)
    mysqli_stmt_bind_param(
        $stmt,
        "ssssssiisssiiis",
        $nombre_cliente,
        $tipo_id,
        $ruc_cliente,
        $telefono_cliente,
        $email_cliente,
        $direccion_cliente,
        $plazo_cliente,
        $id_usuario,
        $provincia,
        $ciudad,
        $status,
        $id_vendedor,
        $id_cliente,
        $ruc_empresa
    );

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo "<script>
            $.notify('Cliente actualizado','success');
            setTimeout(function () {location.reload()}, 1000);
        </script>";
        exit;
    } else {
        mysqli_stmt_close($stmt);
        notify('No se pudo actualizar el cliente. Revise caracteres y datos.', 'error');
        exit;
    }
} */


?>