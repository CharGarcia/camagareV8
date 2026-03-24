<?php
require_once("../conexiones/conectalogin.php");
require_once("../ajax/pagination.php");
include("../clases/contabilizacion.php");
include("../clases/anular_registros.php");
$contabilizacion = new contabilizacion();
$anular_asiento_contable = new anular_registros();
$con = conenta_login();
session_start();

$id_empresa = $_SESSION['id_empresa'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
setlocale(LC_ALL, "es_ES");
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'actualizar_rolespago') {
    $id_rol = intval($_POST['id_rol']);
    $mes = $_POST['mes'];
    $ano = $_POST['ano'];
    $mes_ano = $mes . "-" . $ano;
    $fecha_inicial = date("Y-m-d", strtotime("$ano-$mes-01"));
    $fecha_final = date("Y-m-t", strtotime($fecha_inicial));

    //para eliminar el asiento contable
    $busca_registro_asiento = mysqli_query($con, "SELECT * FROM detalle_rolespago WHERE id_rol = '" . $id_rol . "' and abonos='0'");
    while ($datos_asiento_rol = mysqli_fetch_array($busca_registro_asiento)) {
        $id_registro_contable = $datos_asiento_rol['id_registro_contable'];
        if ($id_registro_contable > 0) {
            $resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
        }
    }
    $res = actualizar_rolespago($con, $id_usuario, $id_rol, $id_empresa, $mes_ano);
    //para guardar asiento contable
    $contabilizacion->documentosRolPagos($con, $id_empresa, $fecha_inicial, $fecha_final, $ruc_empresa);
    $guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'rol_pagos');
    return $res;
}


if ($action == 'eliminar_rolespago') {
    $id_rol = intval($_POST['id_rol']);

    // Verificar si existen abonos en el detalle del rol
    $sql_rol = mysqli_query($con, "
        SELECT ROUND(SUM(abonos), 2) AS abonos
        FROM detalle_rolespago
        WHERE id_rol = '" . $id_rol . "'
        GROUP BY id_rol
    ");

    $row_rol = mysqli_fetch_array($sql_rol);
    $abonos = isset($row_rol['abonos']) ? $row_rol['abonos'] : 0;

    if ($abonos > 0) {
        echo "<script>$.notify('No se puede eliminar. El rol tiene registros pagados.','error')</script>";
    } else {
        // Buscar asientos contables asociados y anularlos
        $busca_registro_asiento = mysqli_query($con, "
            SELECT *
            FROM detalle_rolespago
            WHERE id_rol = '" . $id_rol . "'
        ");

        while ($datos_asiento_rol = mysqli_fetch_array($busca_registro_asiento)) {
            $id_registro_contable = $datos_asiento_rol['id_registro_contable'];
            if ($id_registro_contable > 0) {
                $resultado_anular_documento = $anular_asiento_contable->anular_asiento_contable($con, $id_registro_contable);
            }
        }

        // Eliminar registros detalle_rolespago donde abonos = 0
        $delete_detalle = mysqli_query($con, "
            DELETE FROM detalle_rolespago
            WHERE id_rol = '" . $id_rol . "'
            AND ABS(abonos) < 0.000001
        ");

        if (!$delete_detalle) {
            die("Error eliminando detalle_rolespago: " . mysqli_error($con));
        }

        // Verificar si queda algún registro en detalle_rolespago
        $check_restantes = mysqli_query($con, "
            SELECT COUNT(*) AS restantes
            FROM detalle_rolespago
            WHERE id_rol = '" . $id_rol . "'
        ");

        $row_restantes = mysqli_fetch_array($check_restantes);
        $registros_restantes = $row_restantes['restantes'];

        if ($registros_restantes == 0) {
            // Si ya no quedan registros, actualizar el rol como inactivo
            $update_rol = mysqli_query($con, "
                UPDATE rolespago
                SET status = '0'
                WHERE id = '" . $id_rol . "'
            ");

            if ($update_rol) {
                echo "<script>$.notify('Rol eliminado.','success')</script>";
            } else {
                echo "<script>$.notify('Lo siento, algo ha salido mal intenta nuevamente.','error')</script>";
            }
        } else {
            echo "<script>$.notify('No se pudo eliminar completamente. Aún existen registros en detalle_rolespago.','error')</script>";
        }
    }
}




if ($action == 'detalle_rolespago') {
    $id_rol = intval($_GET['id_rol']);
    $rol = mysqli_real_escape_string($con, (strip_tags($_REQUEST['rol'], ENT_QUOTES)));
    $aColumns = array('emp.nombres_apellidos'); //Columnas de busqueda
    $sTable = "detalle_rolespago as rol INNER JOIN empleados as emp ON emp.id=rol.id_empleado";
    $sWhere = "WHERE rol.id_rol='" . $id_rol . "'";

    $text_buscar = explode(' ', $rol);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['rol'] != "") {
        $sWhere = "WHERE (rol.id_rol='" . $id_rol . "' AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND rol.id_rol='" . $id_rol . "' OR ";
        }
        $sWhere = substr_replace($sWhere, " AND rol.id_rol='" . $id_rol . "' ", -3);
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
    $sql = "SELECT rol.id as id, round((rol.sueldo + rol.ingresos_gravados + rol.ingresos_excentos),2) as ingresos, 
round(rol.total_egresos,2) as egresos, round((rol.tercero + rol.cuarto + rol.fondo_reserva),2) as beneficios,
round(rol.a_recibir,2) as arecibir, round(rol.abonos,2) as abonos, emp.nombres_apellidos as empleado, emp.email as correo_empleado 
FROM $sTable $sWhere LIMIT $offset,$per_page";

    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Empleado</th>
                        <th>Ingresos</th>
                        <th>Egresos</th>
                        <th>Beneficios</th>
                        <th>A_pagar</th>
                        <th>Saldo</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row_rol = mysqli_fetch_array($query)) {
                        $empleado = strtoupper($row_rol['empleado']);
                        $id_registro = $row_rol['id'];
                        $ingresos = $row_rol['ingresos'];
                        $egresos = $row_rol['egresos'];
                        $beneficios = $row_rol['beneficios'];
                        $arecibir = $row_rol['arecibir'];
                        $abonos = $row_rol['abonos'];
                        $correo_empleado = $row_rol['correo_empleado'];
                        $id_encrypt = encrypt_decrypt("encrypt", $id_registro);
                        $path = encrypt_decrypt("encrypt", ""); //para ver la carpeta donde se va a descargar el pdf 
                        $tipoDescarga = encrypt_decrypt("encrypt", "D");
                    ?>
                        <tr>
                            <td><?php echo $empleado; ?></td>
                            <td class="text-right"><?php echo $ingresos; ?></td>
                            <td class="text-right"><?php echo $egresos; ?></td>
                            <td class="text-right"><?php echo $beneficios; ?></td>
                            <td class="text-right"><?php echo $arecibir; ?></td>
                            <td class="text-right"><?php echo number_format($arecibir - $abonos, 2, '.', ''); ?></td>
                            <td><span class="pull-right">
                                    <button type="button" class="btn btn-info btn-xs" onclick="enviar_rolpago_mail('<?php echo $id_registro; ?>', '<?php echo $correo_empleado; ?>');" title="Enviar a: <?php echo $correo_empleado; ?>" data-toggle="modal" data-target="#EnviarDocumentosMail"><span class="glyphicon glyphicon-envelope"></span></button>
                                    <a title="Imprimir rol en pdf" href="../ajax/imprime_documento.php?action=pdf_rol_individual&path=<?php echo $path; ?>&tipoDescarga=<?php echo $tipoDescarga; ?>&id_rol=<?php echo $id_encrypt; ?>" class='btn btn-default btn-xs' title='Pdf' target="_blank">Pdf</a>
                                </span></td>
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

//buscar roles
if ($action == 'buscar_roles_pago') {
    $q = mysqli_real_escape_string($con, (strip_tags($_REQUEST['q'], ENT_QUOTES)));
    $ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
    $por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
    $aColumns = array('rol.datecreated', 'rol.mes_ano'); //Columnas de busqueda
    $sTable = "rolespago as rol"; //INNER JOIN detalle_rol as det ON det.id_rol=qui.id
    $sWhere = "WHERE rol.id_empresa='" . $id_empresa . "' and rol.status !=0 ";

    $text_buscar = explode(' ', $q);
    $like = "";
    for ($i = 0; $i < count($text_buscar); $i++) {
        $like .= "%" . $text_buscar[$i];
    }

    if ($_GET['q'] != "") {
        $sWhere = "WHERE (rol.id_empresa='" . $id_empresa . "' and rol.status !=0 AND ";
        for ($i = 0; $i < count($aColumns); $i++) {
            $sWhere .= $aColumns[$i] . " LIKE '" . $like . "%' AND rol.id_empresa='" . $id_empresa . "' and rol.status !=0 OR ";
        }
        $sWhere = substr_replace($sWhere, " AND rol.id_empresa='" . $id_empresa . "' and rol.status !=0 ", -3);
        $sWhere .= ')';
    }
    $sWhere .= " order by mid(rol.mes_ano,4,4) desc, mid(rol.mes_ano,1,2) desc";


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
    $reload = '';
    //main query to fetch the data
    $sql = "SELECT rol.id as id, rol.datecreated as datecreated, rol.mes_ano as mes_ano
    , (select round(sum(a_recibir),2) FROM detalle_rolespago where id_rol =rol.id group by id_rol) as total_rol FROM $sTable $sWhere LIMIT $offset,$per_page"; //round(sum(det.arecibir),2) as total_rol
    $query = mysqli_query($con, $sql);
    //loop through fetched data
    if ($numrows > 0) {
    ?>
        <div class="panel panel-info">
            <div class="table-responsive">
                <table class="table">
                    <tr class="info">
                        <th>Fecha</th>
                        <th class="text-center">Mes-Año</th>
                        <th class="text-right">Total</th>
                        <th class='text-right'>Opciones</th>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_array($query)) {
                        $id_registro = $row['id'];
                        $mes_ano = $row['mes_ano'];
                        $datecreated = $row['datecreated'];
                        $total_rol = $row['total_rol'];

                    ?>
                        <tr>
                            <td><?php echo date('d-m-Y', strtotime($datecreated)); ?></td>
                            <td class="text-center"><?php echo $mes_ano; ?></td>
                            <td class="text-right"><?php echo $total_rol; ?></td>
                            <td><span class="pull-right">
                                    <?php
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'rolespago')['r'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-info btn-xs" title="Detalle Roles" onclick="detalle_rolespago('<?php echo $id_registro; ?>', '<?php echo $mes_ano; ?>');" data-toggle="modal" data-target="#modalViewRolPagos"><i class="glyphicon glyphicon-list"></i></a>
                                    <?php
                                    }
                                    if (getPermisos($con, $id_usuario, $ruc_empresa, 'rolespago')['d'] == 1) {
                                    ?>
                                        <a href="#" class="btn btn-danger btn-xs" title="Eliminar Roles" onclick="eliminar_rolespago('<?php echo $id_registro; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
                                    <?php
                                    }
                                    ?>
                                </span></td>
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

//guardar o editar rols
if ($action == 'guardar_rolpago') {
    $id_rol = intval($_POST['id_rol_pago']);
    $mes = $_POST['mes'];
    $ano = $_POST['ano'];
    $mes_ano = $mes . "-" . $ano;
    $fecha_inicial = date("Y-m-d", strtotime("$ano-$mes-01"));
    $fecha_final = date("Y-m-t", strtotime($fecha_inicial));

    if (empty($mes)) {
        echo "<script>
            $.notify('Seleccione mes','error');
            </script>";
    } else if (empty($ano)) {
        echo "<script>
        $.notify('Seleccione año','error');
        </script>";
    } else {
        if (empty($id_rol)) {
            $busca_sueldos = mysqli_query($con, "SELECT * FROM sueldos WHERE id_empresa = '" . $id_empresa . "' and status ='1' ");
            $count_sueldos = mysqli_num_rows($busca_sueldos);
            if ($count_sueldos == 0) {
                echo "<script>
                $.notify('Se debe configurar los sueldos de los empleados en: Nómina/sueldos.','error');
                </script>";
            } else {
            $busca_rol = mysqli_query($con, "SELECT * FROM rolespago WHERE id_empresa = '" . $id_empresa . "' and status !=0 and mes_ano='" . $mes_ano . "' ");
            $count = mysqli_num_rows($busca_rol);
            if ($count > 0) {
                echo "<script>
                $.notify('Ya existe un rol de pagos con este período generado','error');
                </script>";
            } else {
                $guarda_rol = mysqli_query($con, "INSERT INTO rolespago (id_empresa, id_usuario, mes_ano) VALUES('" . $id_empresa . "','" . $id_usuario . "','" . $mes_ano . "')");
                $lastid = mysqli_insert_id($con);
                if ($guarda_rol) {
                    detalle_rol($con, $id_empresa, $lastid, $mes_ano);

                    //para guardar asiento contable
                    $contabilizacion->documentosRolPagos($con, $id_empresa, $fecha_inicial, $fecha_final, $ruc_empresa);
                    $guardar_asientos_contables_generados = $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'rol_pagos');

                    echo "<script>
                $.notify('Rol registrado','success');
                document.querySelector('#formRolPago').reset();
                load(1);
                </script>";
                } else {
                    echo "<script>
                $.notify('No es posible registrar el rol, intente de nuevo','error');
                </script>";
                }
            }
        }
        } else {
            echo "<script>
                $.notify('Rol no registrado, intente de nuevo','error');
                </script>";
        }
    }
}

function actualizar_rolespago($con, $id_usuario, $id_rol, $id_empresa, $mes_ano)
{
    $update_rol = mysqli_query($con, "UPDATE rolespago SET id_usuario='" . $id_usuario . "' WHERE id='" . $id_rol . "'");
    detalle_rol($con, $id_empresa, $id_rol, $mes_ano);
    return $update_rol;
}



function detalle_rol($con, $id_empresa, $id_rol, $mes_ano)
{

    mysqli_query($con, "
        DELETE FROM detalle_rolespago 
        WHERE id_rol = '" . $id_rol . "' 
          AND (abonos IS NULL OR abonos = 0)
    ");

    $primer_dia_mes = date("Y-m-d", strtotime("01-" . $mes_ano));
    $ultimo_dia_mes = date("Y-m-t", strtotime("01-" . $mes_ano));

    $request_sueldos = mysqli_query($con, "
    SELECT 
        sue.id as id,
        sue.fecha_salida as fecha_salida,
        sue.fecha_ingreso as fecha_ingreso,
        sue.ap_personal as ap_personal,
        sue.ap_patronal as ap_patronal,
        sue.aporta_al_iess as aporta_al_iess,
        sue.id_empleado as id_empleado,
        ROUND(sue.sueldo, 2) as sueldo,
        sue.decimo_tercero_mensual as decimo_tercero_mensual,
        sue.decimo_cuarto_mensual as decimo_cuarto_mensual,
        sue.fondo_reserva as fondo_reserva,
        sue.id_usuario as id_usuario
    FROM sueldos as sue
    INNER JOIN empleados as emp 
        ON emp.id = sue.id_empleado
    WHERE
        sue.id_empresa = '" . $id_empresa . "'
        AND sue.fecha_ingreso <= '" . $ultimo_dia_mes . "'
        AND (
            emp.status = 1
            OR (
                emp.status != 1
                AND sue.fecha_salida IS NOT NULL
                AND sue.fecha_salida BETWEEN '" . $primer_dia_mes . "' AND '" . $ultimo_dia_mes . "'
            )
        )
        AND sue.sueldo > 0
        AND sue.status = 1
        AND sue.id_empleado NOT IN (
            SELECT det.id_empleado 
            FROM detalle_rolespago as det 
            WHERE det.id_rol = '" . $id_rol . "'
        )
");


    $calculos_salarios = calculos_rol_pagos(substr($mes_ano, -4), $con);
    $incremento_hora_nocturna = 1 + $calculos_salarios['hora_nocturna'] / 100;
    $incremento_hora_suplementaria = 1 + $calculos_salarios['hora_suplementaria'] / 100;
    $incremento_hora_extraordinaria = 1 + $calculos_salarios['hora_extraordinaria'] / 100;
    $salario_basico = $calculos_salarios['sbu'];
    $porcentaje_fondo_reserva = $calculos_salarios['fondo_reserva'] / 100;

    foreach ($request_sueldos as $detalle) {

        $fecha_ingreso = !empty($detalle['fecha_ingreso']) ? strtotime($detalle['fecha_ingreso']) : null;
        $fecha_salida  = !empty($detalle['fecha_salida'])  ? strtotime($detalle['fecha_salida'])  : null;

        $descuento_dias_ingreso = 0;
        if ($fecha_ingreso) {
            $mes_ingreso = date("m-Y", $fecha_ingreso);
            if ($mes_ingreso === $mes_ano) {
                $dia_ingreso = intval(date("d", $fecha_ingreso));
                $descuento_dias_ingreso = max($dia_ingreso - 1, 0);
            }
        }

        $descuento_dias_salida = 0;
        if ($fecha_salida) {
            $mes_salida = date("m-Y", $fecha_salida);
            if ($mes_salida === $mes_ano) {
                $dia_salida = intval(date("d", $fecha_salida));
                $dia_salida = ($dia_salida == 31) ? 30 : $dia_salida;
                $descuento_dias_salida = max(30 - $dia_salida, 0);
            }
        }

        $sql_dias_no_laborados = mysqli_query($con, "
    SELECT SUM(valor) as dias_no_laborados
    FROM novedades
    WHERE id_empresa = '" . $id_empresa . "'
      AND id_novedad = 10
      AND status = 1
      AND id_empleado = '" . $detalle['id_empleado'] . "'
      AND aplica_en = 'R'
      AND mes_ano = '" . $mes_ano . "'
    GROUP BY id_novedad
");

        $row_dias_no_laborados = mysqli_fetch_array($sql_dias_no_laborados) ?: [];
        $dias_no_laborados = isset($row_dias_no_laborados['dias_no_laborados'])
            ? intval($row_dias_no_laborados['dias_no_laborados'])
            : 0;

        $dias_laborados = 30 - $descuento_dias_ingreso - $descuento_dias_salida - $dias_no_laborados;
        $dias_laborados = max($dias_laborados, 0);

        $sueldo = round(($dias_laborados * $detalle['sueldo']) / 30, 2);

        $hora_normal = number_format($sueldo / $calculos_salarios['hora_normal'], 2, '.', '');
        $calculo_hora_nocturna = number_format($hora_normal * $incremento_hora_nocturna, 2, '.', '');
        $calculo_hora_suplementaria = number_format($hora_normal * $incremento_hora_suplementaria, 2, '.', '');
        $calculo_hora_extraordinaria = number_format($hora_normal * $incremento_hora_extraordinaria, 2, '.', '');
        $calculo_aporte_personal = $detalle['ap_personal'] / 100;
        $calculo_aporte_patronal = $detalle['ap_patronal'] / 100;

        //eliminar ingresos y descuentos fijos de novedades
        mysqli_query($con, "
            DELETE FROM novedades
            WHERE 
                id_empleado = '" . $detalle['id_empleado'] . "'
                AND id_empresa = '" . $id_empresa . "'
                AND aplica_en = 'R'
                AND mes_ano = '" . $mes_ano . "'
                AND (
                    (id_novedad = '1' AND (
                        detalle LIKE 'INGRESO_FIJO_MENSUAL_GRAVABLE%' OR
                        detalle LIKE 'INGRESO_FIJO_MENSUAL_EXCENTO%'
                    ))
                    OR
                    (id_novedad = '2' AND detalle LIKE 'DESCUENTO_FIJO_MENSUAL%')
                )
        ");


        //registrar ingresos y descuentos fijos en novedades
        //ingresos fijos gravables
        $query_ingresos_fijos_gravables = mysqli_query($con, "INSERT INTO novedades (id_empleado, id_novedad, id_empresa, id_usuario, fecha_novedad, mes_ano, valor, detalle, iess, aplica_en)
        SELECT '" . $detalle['id_empleado'] . "', '1', '" . $id_empresa . "', '" . $detalle['id_usuario'] . "', concat(SUBSTRING('" . $mes_ano . "',4,4),'-',SUBSTRING('" . $mes_ano . "',1,2),'-01'),'" . $mes_ano . "', round(sum(valor),2) as valor, concat('INGRESO_FIJO_MENSUAL_GRAVABLE',' ',detalle),'1','R' FROM detalle_sueldos WHERE tipo=1 and aporta_al_iess='true' and id_sueldo = '" . $detalle['id'] . "' group by tipo, detalle ");

        $query_ingresos_fijos_excentos = mysqli_query($con, "INSERT INTO novedades (id_empleado, id_novedad, id_empresa, id_usuario, fecha_novedad, mes_ano, valor, detalle, iess, aplica_en)
        SELECT '" . $detalle['id_empleado'] . "', '1', '" . $id_empresa . "', '" . $detalle['id_usuario'] . "', concat(SUBSTRING('" . $mes_ano . "',4,4),'-',SUBSTRING('" . $mes_ano . "',1,2),'-01'),'" . $mes_ano . "', round(sum(valor),2) as valor, concat('INGRESO_FIJO_MENSUAL_EXCENTO',' ',detalle),'0','R' FROM detalle_sueldos WHERE tipo=1 and aporta_al_iess='false' and id_sueldo = '" . $detalle['id'] . "' group by tipo, detalle ");

        //descuentos fijos
        $query_descuentos_fijos_excentos = mysqli_query($con, "INSERT INTO novedades (id_empleado, id_novedad, id_empresa, id_usuario, fecha_novedad, mes_ano, valor, detalle, iess, aplica_en)
        SELECT '" . $detalle['id_empleado'] . "', '2', '" . $id_empresa . "', '" . $detalle['id_usuario'] . "', concat(SUBSTRING('" . $mes_ano . "',4,4),'-',SUBSTRING('" . $mes_ano . "',1,2),'-01'),'" . $mes_ano . "', round(sum(valor),2) as valor, concat('DESCUENTO_FIJO_MENSUAL',' ',detalle),'0','R' FROM detalle_sueldos WHERE tipo=2 and id_sueldo = '" . $detalle['id'] . "' group by tipo, detalle ");


        //INGRESOS gravados los que se calcula con el iess tanto de novedades como de ingresos fijos guardados en sueldos
        $sql_otros_ingresos_gravables_rol = mysqli_query($con, "SELECT sum(valor) as valor FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=1 and iess=1 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_otros_ingresos_gravables_rol = mysqli_fetch_array($sql_otros_ingresos_gravables_rol);
        $otros_ingresos_gravables_rol = isset($request_otros_ingresos_gravables_rol['valor']) ? number_format($request_otros_ingresos_gravables_rol['valor'], 2, '.', '') : 0;

        //otros ingresos de quincenas
        $sql_otros_ingresos_gravables_quincena = mysqli_query($con, "SELECT sum(valor) as valor FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=1 and iess=1 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_otros_ingresos_gravables_quincena = mysqli_fetch_array($sql_otros_ingresos_gravables_quincena);
        $otros_ingresos_gravables_quincena = isset($request_otros_ingresos_gravables_quincena['valor']) ? number_format($request_otros_ingresos_gravables_quincena['valor'], 2, '.', '') : 0;

        //horas extras gravables de rol
        $sql_horas_nocturnas_gravables = mysqli_query($con, "SELECT sum(valor) as horas_nocturnas FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=4 and iess=1 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_nocturnas_gravables = mysqli_fetch_array($sql_horas_nocturnas_gravables);
        $horas_nocturnas_gravables = isset($request_horas_nocturnas_gravables['horas_nocturnas']) ? number_format($calculo_hora_nocturna * $request_horas_nocturnas_gravables['horas_nocturnas'], 2, '.', '') : 0;

        $sql_horas_suplementarias_gravables = mysqli_query($con, "SELECT sum(valor) as horas_suplementarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=5 and iess=1 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_suplementaria_gravables = mysqli_fetch_array($sql_horas_suplementarias_gravables);
        $horas_suplementarias_gravables = isset($request_horas_suplementaria_gravables['horas_suplementarias']) ? number_format($calculo_hora_suplementaria * $request_horas_suplementaria_gravables['horas_suplementarias'], 2, '.', '') : 0;

        $sql_horas_extraordinarias_gravables = mysqli_query($con, "SELECT sum(valor) as horas_extraordinarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=6 and iess=1 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_extraordinaria_gravables = mysqli_fetch_array($sql_horas_extraordinarias_gravables);
        $horas_extraordinarias_gravables = isset($request_horas_extraordinaria_gravables['horas_extraordinarias']) ? number_format($calculo_hora_extraordinaria * $request_horas_extraordinaria_gravables['horas_extraordinarias'], 2, '.', '') : 0;

        //horas extras  gravables de quincena
        $sql_horas_nocturnas_gravables_quincena = mysqli_query($con, "SELECT sum(valor) as horas_nocturnas FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=4 and iess=1 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_nocturnas_gravables_quincena = mysqli_fetch_array($sql_horas_nocturnas_gravables_quincena);
        $horas_nocturnas_gravables_quincena = isset($request_horas_nocturnas_gravables_quincena['horas_nocturnas']) ? number_format($calculo_hora_nocturna * $request_horas_nocturnas_gravables_quincena['horas_nocturnas'], 2, '.', '') : 0;

        $sql_horas_suplementarias_gravables_quincena = mysqli_query($con, "SELECT sum(valor) as horas_suplementarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=5 and iess=1 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_suplementaria_gravables_quincena = mysqli_fetch_array($sql_horas_suplementarias_gravables_quincena);
        $horas_suplementarias_gravables_quincena = isset($request_horas_suplementaria_gravables_quincena['horas_suplementarias']) ? number_format($calculo_hora_suplementaria * $request_horas_suplementaria_gravables_quincena['horas_suplementarias'], 2, '.', '') : 0;

        $sql_horas_extraordinarias_gravables_quincena = mysqli_query($con, "SELECT sum(valor) as horas_extraordinarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=6 and iess=1 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_extraordinaria_gravables_quincena = mysqli_fetch_array($sql_horas_extraordinarias_gravables_quincena);
        $horas_extraordinarias_gravables_quincena = isset($request_horas_extraordinaria_gravables_quincena['horas_extraordinarias']) ? number_format($calculo_hora_extraordinaria * $request_horas_extraordinaria_gravables_quincena['horas_extraordinarias'], 2, '.', '') : 0;

        $total_ingresos_gravables = number_format($otros_ingresos_gravables_quincena +
            $otros_ingresos_gravables_rol +
            $horas_nocturnas_gravables +
            $horas_suplementarias_gravables +
            $horas_extraordinarias_gravables +
            $horas_nocturnas_gravables_quincena +
            $horas_suplementarias_gravables_quincena +
            $horas_extraordinarias_gravables_quincena, 2, '.', '');

        //otros ingresos no gravados
        $sql_otros_ingresos_nogravables_rol = mysqli_query($con, "SELECT sum(valor) as valor FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=1 and iess=0 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_otros_ingresos_gravables_rol = mysqli_fetch_array($sql_otros_ingresos_nogravables_rol);
        $otros_ingresos_nogravables_rol = isset($request_otros_ingresos_gravables_rol['valor']) ? number_format($request_otros_ingresos_gravables_rol['valor'], 2, '.', '') : 0;

        $sql_otros_ingresos_nogravables_quincena = mysqli_query($con, "SELECT sum(valor) as valor FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=1 and iess=0 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_otros_ingresos_gravables_quincena = mysqli_fetch_array($sql_otros_ingresos_nogravables_quincena);
        $otros_ingresos_nogravables_quincena = isset($request_otros_ingresos_gravables_quincena['valor']) ? number_format($request_otros_ingresos_gravables_quincena['valor'], 2, '.', '') : 0;

        //INGRESOS no gravables ROL
        $sql_horas_nocturnas_excentos = mysqli_query($con, "SELECT sum(valor) as horas_nocturnas FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=4 and iess=0 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_nocturnas_excentos = mysqli_fetch_array($sql_horas_nocturnas_excentos);
        $horas_nocturnas_excentos = isset($request_horas_nocturnas_excentos['horas_nocturnas']) ? number_format($calculo_hora_nocturna * $request_horas_nocturnas_excentos['horas_nocturnas'], 2, '.', '') : 0;

        $sql_horas_suplementarias_excentos = mysqli_query($con, "SELECT sum(valor) as horas_suplementarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=5 and iess=0 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_suplementaria_excentos = mysqli_fetch_array($sql_horas_suplementarias_excentos);
        $horas_suplementarias_excentos = isset($request_horas_suplementaria_excentos['horas_suplementarias']) ? number_format($calculo_hora_suplementaria * $request_horas_suplementaria_excentos['horas_suplementarias'], 2, '.', '') : 0;

        $sql_horas_extraordinarias_excentos = mysqli_query($con, "SELECT sum(valor) as horas_extraordinarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=6 and iess=0 and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_extraordinaria_excentos = mysqli_fetch_array($sql_horas_extraordinarias_excentos);
        $horas_extraordinarias_excentos = isset($request_horas_extraordinaria_excentos['horas_extraordinarias']) ? number_format($calculo_hora_extraordinaria * $request_horas_extraordinaria_excentos['horas_extraordinarias'], 2, '.', '') : 0;

        //INGRESOS no gravables QUINCENA
        $sql_horas_nocturnas_excentos_quincena = mysqli_query($con, "SELECT sum(valor) as horas_nocturnas FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=4 and iess=0 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_nocturnas_excentos_quincena = mysqli_fetch_array($sql_horas_nocturnas_excentos_quincena);
        $horas_nocturnas_excentos_quincena = isset($request_horas_nocturnas_excentos_quincena['horas_nocturnas']) ? number_format($calculo_hora_nocturna * $request_horas_nocturnas_excentos_quincena['horas_nocturnas'], 2, '.', '') : 0;

        $sql_horas_suplementarias_excentos_quincena = mysqli_query($con, "SELECT sum(valor) as horas_suplementarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=5 and iess=0 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_suplementaria_excentos_quincena = mysqli_fetch_array($sql_horas_suplementarias_excentos_quincena);
        $horas_suplementarias_excentos_quincena = isset($request_horas_suplementaria_excentos_quincena['horas_suplementarias']) ? number_format($calculo_hora_suplementaria * $request_horas_suplementaria_excentos_quincena['horas_suplementarias'], 2, '.', '') : 0;

        $sql_horas_extraordinarias_excentos_quincena = mysqli_query($con, "SELECT sum(valor) as horas_extraordinarias FROM novedades WHERE id_empresa = '" . $id_empresa . "' and id_novedad=6 and iess=0 and status = 2 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad ");
        $request_horas_extraordinaria_excentos_quincena = mysqli_fetch_array($sql_horas_extraordinarias_excentos_quincena);
        $horas_extraordinarias_excentos_quincena = isset($request_horas_extraordinaria_excentos_quincena['horas_extraordinarias']) ? number_format($calculo_hora_extraordinaria * $request_horas_extraordinaria_excentos_quincena['horas_extraordinarias'], 2, '.', '') : 0;

        $total_ingresos_excentos = number_format($otros_ingresos_nogravables_quincena +
            $otros_ingresos_nogravables_rol +
            $horas_nocturnas_excentos +
            $horas_suplementarias_excentos +
            $horas_extraordinarias_excentos +
            $horas_nocturnas_excentos_quincena +
            $horas_suplementarias_excentos_quincena +
            $horas_extraordinarias_excentos_quincena, 2, '.', '');

        if ($detalle['aporta_al_iess'] == 1) {
            $aporte_personal = number_format(($sueldo + $total_ingresos_gravables) * $calculo_aporte_personal, 2, '.', '');
            $aporte_patronal = number_format(($sueldo + $total_ingresos_gravables) * $calculo_aporte_patronal, 2, '.', '');
        } else {
            $aporte_personal = number_format(0, 2, '.', '');
            $aporte_patronal = number_format(0, 2, '.', '');
        }

        //descuentos rol
        $codigos_descuentos = array(2, 3); //codigos de helpers funcion novedades_sueldos()
        $suma_descuentos_rol = 0;
        $suma_descuentos_quincena = 0;
        foreach ($codigos_descuentos as $des) {
            $sql_descuentos_rol = mysqli_query($con, "SELECT round(sum(valor),2) as suma_descuentos FROM novedades WHERE id_novedad='" . $des . "' and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad");
            $row_descuentos_rol = mysqli_fetch_array($sql_descuentos_rol);
            $suma_descuentos_rol += isset($row_descuentos_rol['suma_descuentos']) ? $row_descuentos_rol['suma_descuentos'] : 0;
            $sql_descuentos_qui = mysqli_query($con, "SELECT round(sum(valor),2) as suma_descuentos FROM novedades WHERE id_novedad='" . $des . "' and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
            $row_descuentos_qui = mysqli_fetch_array($sql_descuentos_qui);
            $suma_descuentos_quincena += isset($row_descuentos_qui['suma_descuentos']) ? $row_descuentos_qui['suma_descuentos'] : 0;
        }


        //prestamos rol
        $codigos_prestamos = array(7, 8, 9); //codigos de helpers funcion novedades_sueldos()
        $suma_prestamos_rol = 0;
        $suma_prestamos_quincena = 0;
        foreach ($codigos_prestamos as $pres) {
            $sql_prestamos_rol = mysqli_query($con, "SELECT round(sum(valor),2) as suma_descuentos FROM novedades WHERE id_novedad='" . $pres . "' and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='R' and mes_ano='" . $mes_ano . "' group by id_novedad");
            $row_prestamos_rol = mysqli_fetch_array($sql_prestamos_rol);
            $suma_prestamos_rol += isset($row_prestamos_rol['suma_descuentos']) ? $row_prestamos_rol['suma_descuentos'] : 0;
            $sql_prestamos_qui = mysqli_query($con, "SELECT round(sum(valor),2) as suma_descuentos FROM novedades WHERE id_novedad='" . $pres . "' and status = 1 and id_empleado = '" . $detalle['id_empleado'] . "' and aplica_en ='Q' and mes_ano='" . $mes_ano . "' group by id_novedad");
            $row_prestamos_qui = mysqli_fetch_array($sql_prestamos_qui);
            $suma_prestamos_quincena += isset($row_prestamos_qui['suma_descuentos']) ? $row_prestamos_qui['suma_descuentos'] : 0;
        }

        $sql_quincena = mysqli_query($con, "SELECT sum(det.quincena) as quincena FROM detalle_quincena as det INNER JOIN quincenas as qui ON qui.id=det.id_quincena WHERE qui.mes_ano='" . $mes_ano . "' and det.id_empleado = '" . $detalle['id_empleado'] . "' and qui.status !=0 group by det.id_empleado, det.id_quincena ");
        $request_quincena = mysqli_fetch_array($sql_quincena);
        $quincena = isset($request_quincena['quincena']) ? number_format($request_quincena['quincena'], 2, '.', '') : 0;

        $total_egresos = number_format($aporte_personal +
            $suma_descuentos_rol +
            $suma_descuentos_quincena +
            $suma_prestamos_rol +
            $suma_prestamos_quincena +
            $quincena, 2, '.', '');

        //para el decimo tercero
        switch ($detalle['decimo_tercero_mensual']) {
            case "1": //Se mensualiza
                $tercero = number_format(($sueldo + $total_ingresos_gravables) / 12, 2, '.', '');
                $prov_tercero = $tercero;
                break;
            case "0": //Se paga acumulado
                $tercero = 0;
                $prov_tercero = number_format(($sueldo + $total_ingresos_gravables) / 12, 2, '.', '');
                break;
        }

        //para el decimo cuarto
        switch ($detalle['decimo_cuarto_mensual']) {
            case "1": //Se mensualiza
                $cuarto = number_format((($dias_laborados * $salario_basico) / 30) / 12, 2, '.', '');
                $prov_cuarto = $cuarto;
                break;
            case "0": //Se paga acumulado
                $cuarto = 0;
                $prov_cuarto = number_format((($dias_laborados * $salario_basico) / 30) / 12, 2, '.', '');
                break;
        }


        //para los fondos de reserva
        switch ($detalle['fondo_reserva']) {
            case "1": //Se paga mediante rol mensual
                $fondo_reserva = number_format(($sueldo + $total_ingresos_gravables) * $porcentaje_fondo_reserva, 2, '.', '');
                $prov_fr = $fondo_reserva;
                break;
            case "2": //Se paga mediante planilla IESS
                $fondo_reserva = 0;
                $prov_fr = number_format(($sueldo + $total_ingresos_gravables) * $porcentaje_fondo_reserva, 2, '.', '');
                break;
            case "4": //No se paga
                $fondo_reserva = 0;
                $prov_fr = 0;
                break;
        }

        $prov_vacacion = number_format(($sueldo + $total_ingresos_gravables) / 24, 2, '.', '');
        $prov_desahucio = number_format((($sueldo * 0.25) / 12), 2, '.', '');

        $a_recibir = number_format($sueldo +
            $total_ingresos_gravables +
            $total_ingresos_excentos -
            $total_egresos +
            $tercero +
            $cuarto +
            $fondo_reserva, 2, '.', '');

        $query_insert_detalle_roles  = mysqli_query($con, "INSERT INTO detalle_rolespago(id_rol, 
                                                                                id_empleado, 
                                                                                dias_laborados,
                                                                                sueldo,
                                                                                ingresos_gravados,
                                                                                ingresos_excentos,
                                                                                aporte_personal,
                                                                                aporte_patronal,
                                                                                prestamos,
                                                                                descuentos,
                                                                                quincena,
                                                                                total_egresos,
                                                                                tercero,
                                                                                cuarto,
                                                                                fondo_reserva,
                                                                                a_recibir,
                                                                                prov_tercero,
                                                                                prov_cuarto,
                                                                                prov_fr,
                                                                                prov_vacacion,
                                                                                prov_desahucio) 
                                                        VALUES('" . $id_rol . "',
                                                        '" . $detalle['id_empleado'] . "',
                                                        '" . $dias_laborados . "',
                                                        '" . $sueldo . "',
                                                        '" . $total_ingresos_gravables . "',
                                                        '" . $total_ingresos_excentos . "',
                                                        '" . $aporte_personal . "',
                                                        '" . $aporte_patronal . "',
                                                        '" . number_format($suma_prestamos_rol + $suma_prestamos_quincena, 2, '.', '') . "',
                                                        '" . number_format($suma_descuentos_rol + $suma_descuentos_quincena, 2, '.', '') . "',
                                                        '" . $quincena . "',
                                                        '" . $total_egresos . "',
                                                        '" . $tercero . "',
                                                        '" . $cuarto . "',
                                                        '" . $fondo_reserva . "',
                                                        '" . $a_recibir . "',
                                                        '" . $prov_tercero . "',
                                                        '" . $prov_cuarto . "',
                                                        '" . $prov_fr . "',
                                                        '" . $prov_vacacion . "',
                                                        '" . $prov_desahucio . "')");
    }
}
?>