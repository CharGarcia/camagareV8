<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
setlocale(LC_ALL, "es_ES");
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'actualizar_decimocuarto') {
    $id_dc = intval($_GET['id_dc']);
    $anio = $_GET['anio'];
    $region = $_GET['region'];
    $res = actualizar_decimocuarto($con, $id_usuario, $id_dc, $id_empresa, $anio, $region);
    return $res;
}

if ($action == 'detalle_decimocuarto') {
    $id_dc = intval($_GET['id_dc']);
    $dc = mysqli_real_escape_string($con, (strip_tags($_REQUEST['dc'], ENT_QUOTES)));
    $aColumns = array('emp.nombres_apellidos'); //Columnas de busqueda
    $sTable = "detalle_decimocuarto as ddc INNER JOIN empleados as emp ON emp.id=ddc.id_empleado";
    $sWhere = "WHERE ddc.id_dc='" . $id_dc . "'";

    $text_buscar = explode(' ', $dc);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['dc'] != "") {
        $sWhere = "WHERE (ddc.id_dc='" . $id_dc . "' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND ddc.id_dc='" . $id_dc . "' OR ";
        }
        $sWhere = substr_replace($sWhere, " AND ddc.id_dc='" . $id_dc . "' ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by emp.nombres_apellidos asc";


    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 10; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT ddc.id as id, round(ddc.decimo,2) as decimo, round(ddc.anticipos,2) as anticipos, 
    emp.nombres_apellidos as empleado, emp.email as correo_empleado, 
    ddc.dias as dias, ddc.abonos as abonos, ddc.arecibir as arecibir FROM $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Nombres</th>
                        <th>Días</th>
                        <th>Décimo</th>
                        <th>Mensuales</th>
                        <th>Abonos</th>
                        <th>A pagar</th>
                        <!-- <th class='text-right'>Opciones</th> -->
                    </tr>
                    <?php
                    while ($row_decimo = mysqli_fetch_array($query)) {
                        $empleado = strtoupper($row_decimo['empleado']);
                        $id_registro = $row_decimo['id'];
                        $dias = $row_decimo['dias'];
                        $decimo = $row_decimo['decimo'];
                        $abonos = $row_decimo['abonos'];
                        $anticipos = $row_decimo['anticipos'];
                        $arecibir = $row_decimo['arecibir'];
                        $correo_empleado = $row_decimo['correo_empleado'];
                        //$id_encrypt = encrypt_decrypt("encrypt", $id_registro);
                        //$path = encrypt_decrypt("encrypt", ""); //para ver la carpeta donde se va a descargar el pdf 
                        //$tipoDescarga = encrypt_decrypt("encrypt", "D");
                    ?>
                        <tr>
                            <td><?php echo $empleado; ?></td>
                            <td class="text-right"><?php echo $dias; ?></td>
                            <td class="text-right"><?php echo number_format($decimo, 2, '.', ''); ?></td>
                            <td class="text-right"><?php echo number_format($anticipos, 2, '.', ''); ?></td>
                            <td class="text-right"><?php echo number_format($abonos, 2, '.', ''); ?></td>
                            <td class="text-right"><?php echo number_format($arecibir, 2, '.', ''); ?></td>
                            <!-- <td><span class="pull-right">
                                    <button type="button" class="btn btn-info btn-xs" onclick="enviar_decimocuarto_mail('<?php echo $id_registro; ?>', '<?php echo $correo_empleado; ?>');" title="Enviar a: <?php echo $correo_empleado; ?>" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope"></span></button>
                                    <a title="Imprimir en pdf" href="../ajax/imprime_documento.php?action=pdf_decimocuarto_individual&path=<?php echo $path; ?>&tipoDescarga=<?php echo $tipoDescarga; ?>&id_dc=<?php echo $id_encrypt; ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
                                </span></td> -->
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


if ($action == 'eliminar_decimocuarto') {
    $id = intval($_POST['id_dc']);

    $sql_dc = mysqli_query($con, "SELECT round(sum(abonos),2) as abonos FROM detalle_decimocuarto WHERE id_dc='" . $id . "' group by id_dc");
    $row_decimo = mysqli_fetch_array($sql_dc);
    $abonos = isset($row_decimo['abonos']) ? $row_decimo['abonos'] : 0;


    if ($abonos > 0) {
        echo "<script>$.notify('No se puede eliminar. El décimo tiene registros pagados.','error')</script>";
    } else {
        if ($deleteuno = mysqli_query($con, "UPDATE decimocuarto SET status ='0' WHERE id='" . $id . "'")) {
            echo "<script>$.notify('Décimo cuarto eliminado.','success')</script>";
        } else {
            echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
        }
    }
}

//buscar 
if ($action == 'buscar_decimocuarto') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('dc.anio'); //Columnas de busqueda
    $sTable = "decimocuarto as dc"; //INNER JOIN detalle_quincena as det ON det.id_quincena=qui.id
    $sWhere = "WHERE dc.id_empresa='" . $id_empresa . "' and dc.status !=0 ";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (dc.id_empresa='" . $id_empresa . "' and dc.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND dc.id_empresa='" . $id_empresa . "' and dc.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, " AND dc.id_empresa='" . $id_empresa . "' and dc.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by dc.anio desc";


    //pagination variables
    $page = (isset($_REQUEST['page']) && !empty($_REQUEST['page'])) ? $_REQUEST['page'] : 1;
    $per_page = 12; //how much records you want to show
    $adjacents  = 4; //gap between pages after number of adjacents
    $offset = ($page - 1) * $per_page;
    //Count the total number of row in your table*/
    $count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
    $row = mysqli_fetch_array($count_query);
    $numrows = $row['numrows'];
    $total_pages = ceil($numrows / $per_page);
    $reload = '../decimocuarto.php';
    //main query to fetch the data
    $sql = "SELECT dc.id as id, dc.datecreated as datecreated, dc.anio as anio, 
    (select round(sum(arecibir),2) from detalle_decimocuarto where id_dc=dc.id group by id_dc) as total_decimo, 
    dc.region as region FROM $sTable $sWhere LIMIT $offset,$per_page";
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th class="text-center">Año</th>
                        <th class="text-center">Región</th>
                        <th class="text-center">Período</th>
                        <th class="text-right">Total</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $anio = $row['anio'];
                        $año_anterior = intval($anio) - 1;
                        $region = $row['region'];
                        $datecreated = $row['datecreated'];
                        $total_decimo = $row['total_decimo'];
                        if ($region == '1') {
                            $periodo = "Desde 01-08-" . $año_anterior . " hasta 31-07-" . $anio;
                            $region = "Sierra-Oriente";
                        }
                        if ($region == '2') {
                            $periodo = "Desde 01-03-" . $año_anterior . " hasta 28/29-02-" . $anio;
                            $region = "Costa";
                        }
                    ?>
                        <tr>
                            <td class="text-center"><?php echo $anio; ?></td>
                            <td class="text-center"><?php echo $region; ?></td>
                            <td class="text-center"><?php echo $periodo; ?></td>
                            <td class="text-right"><?php echo $total_decimo; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'decimocuarto')['r'] == 1) {
                                    ?>
                                        <a class="btn btn-info btn-xs" title="Detalle" onclick="detalle_decimocuarto('<?php echo $id_registro; ?>', '<?php echo $anio; ?>', '<?php echo $row['region']; ?>');" data-toggle="modal" data-target="#modalViewDecimoCuarto"><i class="glyphicon glyphicon-list"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'decimocuarto')['d'] == 1) {
                                    ?>
                                        <a class="btn btn-danger btn-xs" title="Eliminar" onclick="eliminar_decimocuarto('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
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

//guardar o editar
if ($action == 'guardar_decimocuarto') {
    $id_dc = intval($_POST['id_dc']);
    $anio = $_POST['anio'];
    $region = $_POST['region'];

    if (empty($anio)) {
        echo "<script>
            $.notify('Seleccione año','error');
            </script>";
    } else {
        if (empty($id_dc)) {
            $busca_dc = mysqli_query($con, "SELECT * FROM decimocuarto WHERE id_empresa = '" . $id_empresa . "' and status !=0 and anio='" . $anio . "' and region='" . $region . "' ");
            $count = mysqli_num_rows($busca_dc);
            if ($count > 0) {
                echo "<script>
                $.notify('Existe un registro con este período que puede editar','error');
                </script>";
            } else {
                $guarda_decimo = mysqli_query($con, "INSERT INTO decimocuarto (id_empresa, anio, region, id_usuario) VALUES('" . $id_empresa . "','" . $anio . "','" . $region . "','" . $id_usuario . "')");
                $lastid = mysqli_insert_id($con);
                if ($guarda_decimo) {
                    detalle_decimocuarto($con, $id_empresa, $lastid, $anio, $region);
                    echo "<script>
                $.notify('Décimo cuarto registrado','success');
                document.querySelector('#formDecimoCuarto').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No es posible guardar, intente de nuevo','error');
                </script>";
                }
            }
        } else {
            $update_decimo = actualizar_decimocuarto($con, $id_usuario, $id_dc, $id_empresa, $anio, $region);
            if ($update_decimo) {
                echo "<script>
                    $.notify('Décimo cuarto actualizado','success');
                    setTimeout(function () {location.reload()}, 1000);
                        </script>";
            } else {
                echo "<script>
                        $.notify('No es posible actualizar, intente de nuevo','error');
                        </script>";
            }
        }
    }
}

function actualizar_decimocuarto($con, $id_usuario, $id_dc, $id_empresa, $anio, $region)
{
    $update_decimo = mysqli_query($con, "UPDATE decimocuarto SET id_usuario='" . $id_usuario . "' WHERE id='" . $id_dc . "'");
    detalle_decimocuarto($con, $id_empresa, $id_dc, $anio, $region);

    //crea el archivo para mt
    // Datos que se desean incluir en el CSV
    $sql_data = mysqli_query($con, "SELECT emp.documento as cedula, 
    CONCAT(SUBSTRING_INDEX(emp.nombres_apellidos, ' ', 1),' ', 
    SUBSTRING_INDEX(SUBSTRING_INDEX(emp.nombres_apellidos, ' ', 2), ' ', -1)) AS nombres,
    CONCAT(SUBSTRING_INDEX(SUBSTRING_INDEX(emp.nombres_apellidos, ' ', -2), ' ', 1),' ',
    SUBSTRING_INDEX(emp.nombres_apellidos, ' ', -1)) AS apellidos, 
    if(emp.sexo=2,'F','M') as genero, sue.cargo_iess as ocupacion, det.dias as dias_laborados, 'P' as tipo_pago, '' as contrato_permanente_parcial,
    '' as horas_mes, '' as discapacidad, '' as fecha_juvilacion, '' as valor_retencion,
    if(sue.decimo_cuarto_mensual=1,'X','0') as mensualiza_decimo 
    FROM detalle_decimocuarto as det 
    INNER JOIN empleados as emp 
    ON emp.id=det.id_empleado
    INNER JOIN decimocuarto as de
    ON de.id=det.id_dc
    INNER JOIN sueldos as sue
    ON sue.id_empleado=det.id_empleado
    WHERE de.id='" . $id_dc . "' order by emp.nombres_apellidos asc");

    // Nombre del archivo CSV
    $filename = "../xml/decimocuarto.csv";
    // Abrir o crear el archivo CSV en modo escritura
    $file = fopen($filename, "w");
    // Configurar la delimitación por punto y coma (;)
    $delimiter = ";";
    // Obtener los encabezados de las columnas (opcional)
    $header = mysqli_fetch_fields($sql_data);
    $header_names = [];
    foreach ($header as $col) {
        $header_names[] = $col->name;
    }
    fputcsv($file, $header_names, $delimiter);
    // Escribir cada fila de datos en el archivo CSV
    while ($row = mysqli_fetch_assoc($sql_data)) {
        foreach ($row as $key => $value) {
            $row[$key] = clear_cadena($value);
        }
        fwrite($file, implode($delimiter, $row) . "\n");
    }
    // Cerrar el archivo
    fclose($file);
    //echo "Archivo CSV creado con éxito.";
    return $update_decimo;
}


function detalle_decimocuarto($con, $id_empresa, $id_dc, $anio, $region)
{
    $sql_delete_no_pagados = mysqli_query($con, "DELETE FROM detalle_decimocuarto WHERE id_dc = '" . $id_dc . "' and abonos = 0");
    $request_empleados = mysqli_query($con, "SELECT emp.id as id_empleado 
     FROM empleados as emp WHERE emp.id_empresa= '" . $id_empresa . "' and emp.status = '1' 
     and emp.id NOT IN (SELECT det.id_empleado FROM detalle_decimocuarto as det WHERE det.id_dc='" . $id_dc . "')");
    $calculos_salarios = calculos_rol_pagos($anio, $con);

    foreach ($request_empleados as $detalle) {
        $sueldo_basico = number_format($calculos_salarios['sbu'], 2, '.', '');
        //para traer la fecha entrada del empleado
        $sql_entrada = mysqli_query($con, "SELECT year(fecha_ingreso) as anio_entrada, month(fecha_ingreso) as mes_entrada FROM sueldos WHERE id_empleado='" . $detalle['id_empleado'] . "' and status = '1' ");
        $row_entrada = mysqli_fetch_array($sql_entrada);
        $anio_entrada = $row_entrada['anio_entrada'];
        $mes_entrada = $row_entrada['mes_entrada'];

        $meses = [];
        //sierra oriente
        if ($region == 1) {
            // Para los meses de 8 a 12, utilizamos el año anterior
            $ano_anterior = $anio - 1;
            if ($ano_anterior == $anio_entrada && intval($mes_entrada) > 8 && intval($mes_entrada) <= 12) {
                $mes_periodo_inicial = $mes_entrada;
            } else {
                $mes_periodo_inicial = 8;
            }

            for ($m = $mes_periodo_inicial; $m <= 12; $m++) {
                $meses[] = sprintf("%02d-%d", $m, $ano_anterior);
            }
            // Para los meses de 1 a 7, utilizamos el año actual
            if ($anio == $anio_entrada && intval($mes_entrada) > 1 && intval($mes_entrada) <= 7) {
                $mes_periodo_siguiente = $mes_entrada;
            } else {
                $mes_periodo_siguiente = 1;
            }
            for ($m = $mes_periodo_siguiente; $m <= 7; $m++) {
                $meses[] = sprintf("%02d-%d", $m, $anio);
            }
        }

        //costa
        if ($region == 2) {
            // Para los meses de 8 a 12, utilizamos el año anterior
            $ano_anterior = $anio - 1;
            if ($ano_anterior == $anio_entrada && intval($mes_entrada) > 3 && intval($mes_entrada) <= 12) {
                $mes_periodo_inicial = $mes_entrada;
            } else {
                $mes_periodo_inicial = 3;
            }

            for ($m = $mes_periodo_inicial; $m <= 12; $m++) {
                $meses[] = sprintf("%02d-%d", $m, $ano_anterior);
            }
            // Para los meses de 1 a 7, utilizamos el año actual
            if ($anio == $anio_entrada && intval($mes_entrada) > 1 && intval($mes_entrada) <= 2) {
                $mes_periodo_siguiente = $mes_entrada;
            } else {
                $mes_periodo_siguiente = 1;
            }
            for ($m = $mes_periodo_siguiente; $m <= 2; $m++) {
                $meses[] = sprintf("%02d-%d", $m, $anio);
            }
        }

        $dias = 0;
        $anticipos = 0;
        foreach ($meses as $mes_ano) {
            //para traerme de los roles de pagos los dias laborados y los anticipos dados mensualmente por decimo cuarto
            $sql_anticipos_dias = mysqli_query($con, "SELECT 
            det_rol.dias_laborados as dias, round(det_rol.cuarto,2) as anticipos 
                FROM rolespago as rol 
                INNER JOIN detalle_rolespago as det_rol 
                ON det_rol.id_rol=rol.id 
                WHERE rol.mes_ano='" . $mes_ano . "' and rol.status = '1' and det_rol.id_empleado= '" . $detalle['id_empleado'] . "'");
            $request_anticipos_dias = mysqli_fetch_array($sql_anticipos_dias);
            $dias += isset($request_anticipos_dias['dias']) ? $request_anticipos_dias['dias'] : 0;
            $anticipos += isset($request_anticipos_dias['anticipos']) ? $request_anticipos_dias['anticipos'] : 0;
        }

        $decimo = number_format($dias * $sueldo_basico / 360, 2, '.', '');
        $arecibir = number_format($decimo - $anticipos, 2, '.', '');

        if($dias>0){
        $query_insert  = mysqli_query($con, "INSERT INTO detalle_decimocuarto(id_dc, 
                                                                                id_empleado, 
                                                                                dias,
                                                                                decimo,
                                                                                anticipos,
                                                                                arecibir) 
            VALUES('" . $id_dc . "','" . $detalle['id_empleado'] . "','" . $dias . "','" .  $decimo . "','" . $anticipos . "','" . $arecibir . "')");
        }    
}
}

?>