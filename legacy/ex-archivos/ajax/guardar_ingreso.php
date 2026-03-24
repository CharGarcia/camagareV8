<?php
include("../clases/contabilizacion.php");
$contabilizacion = new contabilizacion();
include("../conexiones/conectalogin.php");
include("../validadores/generador_codigo_unico.php");
include("../clases/asientos_contables.php");

$con = conenta_login();
session_start();

if (empty($_POST['fecha_ingreso'])) {
    $errors[] = "Ingrese fecha para el ingreso.";
} else if (!strtotime($_POST['fecha_ingreso'])) {
    $errors[] = "Ingrese una fecha válida.";
} else if (empty($_POST['cliente_ingreso'])) {
    $errors[] = "Ingrese o seleccione un cliente o el nombre de quien se recibe el ingreso.";
} else if (!empty($_POST['fecha_ingreso']) && !empty($_POST['cliente_ingreso'])) {
    $fecha_ingreso        = date('Y-m-d H:i:s', strtotime($_POST['fecha_ingreso']));
    $nombre_cliente       = mysqli_real_escape_string($con, strip_tags($_POST["cliente_ingreso"], ENT_QUOTES));
    $id_cliente_ingreso   = mysqli_real_escape_string($con, strip_tags($_POST["id_cliente_ingreso"], ENT_QUOTES));
    $observacion_ingreso  = mysqli_real_escape_string($con, strip_tags($_POST["observacion_ingreso"], ENT_QUOTES));
    $fecha_registro       = date("Y-m-d H:i:s");
    $id_usuario           = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 0;
    $ruc_empresa          = isset($_SESSION['ruc_empresa']) ? $_SESSION['ruc_empresa'] : '';
    $codigo_unico         = codigo_unico(20);

    // Verifica que existan detalles en la tabla temporal
    $sql_ingresos_egresos_tmp = mysqli_query($con, "SELECT * FROM ingresos_egresos_tmp WHERE id_usuario = '$id_usuario' AND tipo_documento='INGRESO'");
    $count = mysqli_num_rows($sql_ingresos_egresos_tmp);
    if ($count == 0) {
        $errors[] = "No hay detalles agregados al ingreso.";
    } else {
        // Suma de formas de pago (desde sesión)
        $total_pagos = 0.0;
        if (isset($_SESSION['arrayFormaPagoIngreso']) && is_array($_SESSION['arrayFormaPagoIngreso'])) {
            foreach ($_SESSION['arrayFormaPagoIngreso'] as $detalle) {
                $total_pagos += (float)$detalle['valor'];
            }
        }

        // Suma de conceptos en la tabla temporal
        $busca_ingresos_agregados = mysqli_query($con, "
            SELECT SUM(valor) AS valor
            FROM ingresos_egresos_tmp
            WHERE id_usuario = '$id_usuario' AND tipo_documento = 'INGRESO'
            GROUP BY id_usuario
        ");
        $row_ingresos   = mysqli_fetch_assoc($busca_ingresos_agregados);
        $total_ingresos = isset($row_ingresos['valor']) ? (float)$row_ingresos['valor'] : 0.0;

        // Comparación redondeada a 2 decimales (evita problemas por strings/formatos)
        if (round($total_pagos, 2) != round($total_ingresos, 2)) {
            $errors[] = "El total de ingreso no coincide con el total de formas de pagos.";
        } else {
            // Siguiente número de ingreso
            $busca_siguiente_ingreso = mysqli_query($con, "
                SELECT MAX(numero_ing_egr) AS numero
                FROM ingresos_egresos
                WHERE ruc_empresa = '$ruc_empresa' AND tipo_ing_egr = 'INGRESO'
            ");
            $row_siguiente_ingreso = mysqli_fetch_assoc($busca_siguiente_ingreso);
            $numero_ingreso = (isset($row_siguiente_ingreso['numero']) ? (int)$row_siguiente_ingreso['numero'] : 0) + 1;

            // Totales de asiento contable en tmp
            $sql_diario_temporal = mysqli_query($con, "
                SELECT COUNT(id_cuenta) AS id_cuenta, SUM(debe) AS debe, SUM(haber) AS haber
                FROM detalle_diario_tmp
                WHERE id_usuario = '$id_usuario'
                AND MID(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "'
            ");
            $row_asiento_contable = mysqli_fetch_assoc($sql_diario_temporal);
            $debe          = isset($row_asiento_contable['debe']) ? (float)$row_asiento_contable['debe'] : 0.0;
            $haber         = isset($row_asiento_contable['haber']) ? (float)$row_asiento_contable['haber'] : 0.0;
            $count_asientos = isset($row_asiento_contable['id_cuenta']) ? (int)$row_asiento_contable['id_cuenta'] : 0;

            if ($count_asientos > 0 && (round($debe, 2) != round($haber, 2))) {
                $errors[] = "El asiento contable no cumple con partida doble.";
            } else {

                // ===== Transacción compatible con PHP 5.6 =====
                mysqli_autocommit($con, false);
                try {
                    // Encabezado de ingreso
                    $query_encabezado_ingreso = mysqli_query($con, "
                        INSERT INTO ingresos_egresos
                        VALUES (
                            NULL, '$ruc_empresa', '$fecha_ingreso', '$nombre_cliente', '$numero_ingreso',
                            '$total_ingresos', 'INGRESO', '$id_usuario', '$fecha_registro', '0',
                            '$codigo_unico', '$observacion_ingreso', 'OK', '$id_cliente_ingreso'
                        )
                    ");
                    if (!$query_encabezado_ingreso) {
                        throw new Exception("Error al guardar encabezado del ingreso: " . mysqli_error($con));
                    }

                    // Actualiza saldos temporales
                    $update_ingresos_tmp = mysqli_query($con, "
                        UPDATE saldo_porcobrar_porpagar AS sal_tmp
                        JOIN (
                            SELECT iet.id_documento AS registro, SUM(iet.valor) AS suma_ingreso_tmp
                            FROM ingresos_egresos_tmp AS iet
                            WHERE iet.tipo_documento='INGRESO'
                            GROUP BY iet.id_documento
                        ) AS total_ingreso_tmp
                        ON total_ingreso_tmp.registro=sal_tmp.id_documento
                        SET sal_tmp.total_ing = sal_tmp.total_ing + total_ingreso_tmp.suma_ingreso_tmp,
                            sal_tmp.ing_tmp='0'
                    ");
                    if (!$update_ingresos_tmp) {
                        throw new Exception("Error al actualizar ingresos tmp: " . mysqli_error($con));
                    }

                    // Recupera el ID de ingreso guardado
                    $busca_ultimo_registro = mysqli_query($con, "SELECT * FROM ingresos_egresos WHERE id_ing_egr = LAST_INSERT_ID()");
                    if (!$busca_ultimo_registro || mysqli_num_rows($busca_ultimo_registro) == 0) {
                        throw new Exception("No se pudo recuperar el ingreso guardado.");
                    }
                    $row_ultimo_registro = mysqli_fetch_assoc($busca_ultimo_registro);
                    $id_ing_egr = (int)$row_ultimo_registro['id_ing_egr'];

                    // Asiento contable (si aplica)
                    if ($count_asientos > 0 && round($debe, 2) == round($haber, 2)) {
                        $asiento_contable = new asientos_contables();
                        $glosa = 'INGRESO N.' . $numero_ingreso . " " . $nombre_cliente;
                        $guarda_asiento = $asiento_contable->guarda_asiento($con, $fecha_ingreso, $glosa, 'INGRESOS', $id_ing_egr, $ruc_empresa, $id_usuario, $id_cliente_ingreso);
                        if (!$guarda_asiento) {
                            throw new Exception("Error al guardar asiento contable.");
                        }
                    }

                    // Formas de pago
                    if (isset($_SESSION['arrayFormaPagoIngreso']) && is_array($_SESSION['arrayFormaPagoIngreso'])) {
                        foreach ($_SESSION['arrayFormaPagoIngreso'] as $detalle_fp) {
                            $origen            = isset($detalle_fp['origen']) ? $detalle_fp['origen'] : '1';
                            $codigo_forma_pago = ($origen == '1') ? $detalle_fp['id_forma'] : '0';
                            $id_cuenta_fp      = ($origen == '1') ? '0' : $detalle_fp['id_forma'];
                            $valor_pago        = round((float)$detalle_fp['valor'], 2);
                            $tipo_fp           = isset($detalle_fp['tipo']) ? $detalle_fp['tipo'] : '';

                            $detalle_formas_pago = mysqli_query($con, "
                                INSERT INTO formas_pagos_ing_egr
                                VALUES (
                                    NULL, '$ruc_empresa', 'INGRESO', '$numero_ingreso', '$valor_pago',
                                    '$codigo_forma_pago', '$id_cuenta_fp', '$tipo_fp', '$codigo_unico',
                                    '$fecha_ingreso', '$fecha_ingreso', '$fecha_ingreso', 'PAGADO', '0', 'OK'
                                )
                            ");
                            if (!$detalle_formas_pago) {
                                throw new Exception("Error al guardar forma de pago: " . mysqli_error($con));
                            }
                        }
                    }

                    // Inserta detalles desde la tabla temporal
                    $detalle_ingreso = mysqli_query($con, "
                        INSERT INTO detalle_ingresos_egresos (
                            id_detalle_ing_egr, ruc_empresa, beneficiario_cliente, valor_ing_egr, detalle_ing_egr,
                            numero_ing_egr, tipo_ing_egr, tipo_documento, codigo_documento_cv, estado, codigo_documento
                        )
                        SELECT
                            NULL, '$ruc_empresa', beneficiario_cliente, valor, detalle, '$numero_ingreso',
                            tipo_transaccion, 'INGRESO', id_documento, 'OK', '$codigo_unico'
                        FROM ingresos_egresos_tmp
                        WHERE id_usuario = '$id_usuario' AND tipo_documento = 'INGRESO'
                    ");
                    if (!$detalle_ingreso) {
                        throw new Exception("Error al insertar detalles del ingreso: " . mysqli_error($con));
                    }

                    // ¡IMPORTANTE! Capturamos inmediatamente las filas insertadas
                    $filas_insertadas_detalle = mysqli_affected_rows($con);

                    // Reglas para RV: revisar sumas y actualizar status si coincide con total del recibo
                    $result_rv = mysqli_query($con, "
                        SELECT DISTINCT id_documento
                        FROM ingresos_egresos_tmp
                        WHERE id_usuario = '$id_usuario'
                          AND tipo_documento = 'INGRESO'
                          AND id_documento LIKE 'RV%'
                    ");

                    if ($result_rv) {
                        while ($row = mysqli_fetch_assoc($result_rv)) {
                            $id_documento_rv = $row['id_documento'];              // Ej: RV12345
                            $id_recibo       = preg_replace('/\D/', '', $id_documento_rv); // 12345

                            // Suma de valores aplicados a ese RV en detalle_ingresos_egresos
                            $consulta_suma = mysqli_query($con, "
                                SELECT SUM(valor_ing_egr) AS suma_valores
                                FROM detalle_ingresos_egresos
                                WHERE codigo_documento_cv = '$id_documento_rv'
                            ");
                            $row_suma = $consulta_suma ? mysqli_fetch_assoc($consulta_suma) : null;
                            $suma = $row_suma && isset($row_suma['suma_valores']) ? (float)$row_suma['suma_valores'] : 0.0;

                            // Total del recibo
                            $consulta_total = mysqli_query($con, "
                                SELECT total_recibo
                                FROM encabezado_recibo
                                WHERE id_encabezado_recibo = '$id_recibo'
                                LIMIT 1
                            ");
                            $fila_recibo = $consulta_total ? mysqli_fetch_assoc($consulta_total) : null;
                            $total_recibo = ($fila_recibo && isset($fila_recibo['total_recibo'])) ? (float)$fila_recibo['total_recibo'] : 0.0;

                            // Comparación
                            if (round($suma, 2) == round($total_recibo, 2)) {
                                $update_status = mysqli_query($con, "
                                    UPDATE encabezado_recibo
                                    SET status = '3', id_usuario = '$id_usuario'
                                    WHERE id_encabezado_recibo = '$id_recibo'
                                ");
                                if (!$update_status) {
                                    throw new Exception("Error al actualizar el estado del recibo RV: " . mysqli_error($con));
                                }
                            }
                        }
                    }

                    // Validación final del insert de detalle (usamos el valor guardado)
                    if ($filas_insertadas_detalle <= 0) {
                        throw new Exception("No se guardaron los detalles del ingreso.");
                    }

                    // Contabilización posterior
                    $contabilizacion->documentosIngresos($con, $ruc_empresa, $fecha_ingreso, $fecha_ingreso);
                    $contabilizacion->guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, 'ingresos');

                    // Commit
                    mysqli_commit($con);
                    mysqli_autocommit($con, true);

                    unset($_SESSION['arrayFormaPagoIngreso']);
                    echo "<script>
                        $.notify('Ingreso guardado con éxito','success');
                        setTimeout(function () { location.reload(); }, 200);
                    </script>";
                    exit;

                } catch (Exception $e) {
                    mysqli_rollback($con);
                    mysqli_autocommit($con, true);
                    // Mostrar el error en la pantalla
                    echo '<div class="alert alert-danger" role="alert">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <strong>Error: </strong>' . $e->getMessage() . '
                    </div>';
                }
            }
        }
    }
} else {
    $errors[] = "Error desconocido.";
}

// Muestra de errores acumulados
if (isset($errors) && is_array($errors) && count($errors) > 0) {
    echo '<div class="alert alert-danger" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>Atención! </strong>';
    foreach ($errors as $error) {
        echo $error . '<br>';
    }
    echo '</div>';
}
