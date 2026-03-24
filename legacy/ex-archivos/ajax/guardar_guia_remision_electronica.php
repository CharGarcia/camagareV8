<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();

/* ====================== HELPERS (compatibles PHP 5.6) ====================== */

// Longitud segura con mbstring si existe
function mb_len($s) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($s, 'UTF-8');
    }
    return strlen($s); // fallback (cuenta bytes)
}

// Normaliza texto: quita NBSP, tabs, saltos, colapsa espacios, recorta
function normalizar_texto($s) {
    $s = (string)$s;
    // NBSP (\xC2\xA0), tabs \t, \r -> espacio
    $s = str_replace(array("\xC2\xA0", "\t", "\r"), " ", $s);
    // Colapsar múltiples espacios y saltos
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

// Valida un par concepto/detalle con límites SRI típicos (concepto<=30, detalle<=300)
function validar_par_adicional(&$concepto, &$detalle, &$errorOut) {
    $concepto = normalizar_texto($concepto);
    $detalle  = normalizar_texto($detalle);

    if (mb_len($concepto) === 0) {
        $errorOut = "Información adicional: el nombre (concepto) no puede estar vacío.";
        return false;
    }
    if (mb_len($concepto) > 30) {
        $errorOut = "Información adicional: el nombre (\"".$concepto."\") excede 30 caracteres.";
        return false;
    }
    if (mb_len($detalle) === 0) {
        $errorOut = "Información adicional: el detalle (valor) no puede estar vacío para \"".$concepto."\".";
        return false;
    }
    if (mb_len($detalle) > 300) {
        $errorOut = "Información adicional: el detalle para \"".$concepto."\" excede 300 caracteres.";
        return false;
    }
    return true;
}

// Valida fecha a partir de string (espera algo parseable por strtotime)
function validar_fecha_post($valor) {
    if (!isset($valor) || $valor === '') {
        return false;
    }
    $ts = @strtotime($valor);
    return $ts !== false;
}

/* ====================== VALIDACIONES BÁSICAS DE POST ====================== */

$errors = array();
$messages = array();

if (empty($_POST['id_transportista_guia'])) {
    $errors[] = "Seleccione un transportista.";
} else if (empty($_POST['id_cliente_guia'])) {
    $errors[] = "Seleccione un cliente";
} else if (empty($_POST['placa_guia'])) {
    $errors[] = "Ingrese placa del vehículo.";
} else if (empty($_POST['partida_guia'])) {
    $errors[] = "Ingrese el punto de partida para el traslado.";
} else if (empty($_POST['destino_guia'])) {
    $errors[] = "Ingrese el destino del traslado.";
} else if (empty($_POST['motivo_guia'])) {
    $errors[] = "Ingrese el motivo de traslado.";
} else if (empty($_POST['ruta_guia'])) {
    $errors[] = "Ingrese ruta por donde se va a trasladar el producto a su destino.";
} else if (!validar_fecha_post($_POST['fecha_salida_guia'])) {
    $errors[] = "Ingrese fecha de salida válida.";
} else if (!validar_fecha_post($_POST['fecha_llegada_guia'])) {
    $errors[] = "Ingrese fecha de llegada válida.";
} else if (!validar_fecha_post($_POST['fecha_guia'])) {
    $errors[] = "Ingrese fecha para la guía de remisión válida.";
} else if (empty($_POST['serie_guia'])) {
    $errors[] = "Seleccione serie.";
} else if (empty($_POST['secuencial_guia'])) {
    $errors[] = "Seleccione serie para obtener el número de guía de remisión.";
}

/* ====================== SI PASAN BÁSICAS, CONTINÚA ====================== */

if (empty($errors)) {

    session_start();
    if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['ruc_empresa'])) {
        $errors[] = "Sesión expirada. Ingrese nuevamente.";
    } else {

        $id_usuario  = $_SESSION['id_usuario'];
        $ruc_empresa = $_SESSION['ruc_empresa'];

        $id_transportista_guia = mysqli_real_escape_string($con, strip_tags($_POST["id_transportista_guia"], ENT_QUOTES));
        $id_cliente_guia       = mysqli_real_escape_string($con, strip_tags($_POST["id_cliente_guia"], ENT_QUOTES));
        $placa_guia            = mysqli_real_escape_string($con, strip_tags($_POST["placa_guia"], ENT_QUOTES));
        $factura_guia          = mysqli_real_escape_string($con, strip_tags(isset($_POST["factura_guia"]) ? $_POST["factura_guia"] : "", ENT_QUOTES));
        $origen_guia           = mysqli_real_escape_string($con, strip_tags($_POST["partida_guia"], ENT_QUOTES));
        $destino_guia          = mysqli_real_escape_string($con, strip_tags($_POST["destino_guia"], ENT_QUOTES));
        $motivo_guia           = mysqli_real_escape_string($con, strip_tags($_POST["motivo_guia"], ENT_QUOTES));
        $ruta_guia             = mysqli_real_escape_string($con, strip_tags($_POST["ruta_guia"], ENT_QUOTES));

        $fecha_salida_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_salida_guia']));
        $fecha_llegada_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_llegada_guia']));
        $fecha_guia = date('Y-m-d H:i:s', strtotime($_POST['fecha_guia']));
        $serie_guia = mysqli_real_escape_string($con, strip_tags($_POST["serie_guia"], ENT_QUOTES));
        $secuencial_guia = mysqli_real_escape_string($con, strip_tags($_POST["secuencial_guia"], ENT_QUOTES));

        $aduanero_guia = mysqli_real_escape_string($con, strip_tags(isset($_POST["aduanero_guia"]) ? $_POST["aduanero_guia"] : "", ENT_QUOTES));
        $codigo_destino_guia = mysqli_real_escape_string($con, strip_tags(isset($_POST["codigo_destino_guia"]) ? $_POST["codigo_destino_guia"] : "", ENT_QUOTES));
        $fecha_registro = date("Y-m-d H:i:s");

        // Validación de formato de factura: 001-001-000000001
        if ($factura_guia !== "") {
            if (substr($factura_guia, 0, 3) == "000" || substr($factura_guia, 4, 3) == "000" || substr($factura_guia, 8, 9) == "000000000") {
                $errors[] = 'Ingrese un número de factura correcto. ej: 001-001-000000001 ' . mysqli_error($con);
            }
        }

        if (empty($errors)) {

            // Verifica GR duplicada
            $busca_empresa = "SELECT 1 FROM encabezado_gr WHERE ruc_empresa = '$ruc_empresa' AND serie_gr = '$serie_guia' AND secuencial_gr = '$secuencial_guia' AND tipo_gr = 'ELECTRÓNICA' LIMIT 1";
            $result = mysqli_query($con, $busca_empresa);
            $count = $result ? mysqli_num_rows($result) : 0;

            if ($count > 0) {
                $errors[] = "El número de guía de remisión que intenta guardar ya se encuentra registrado en el sistema." . mysqli_error($con);
            } else {
                // Detalle temporal de productos
                $sql_guia_temporal = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '".$id_usuario."' AND ruc_empresa = '".$ruc_empresa."'");
                $count_tmp = $sql_guia_temporal ? mysqli_num_rows($sql_guia_temporal) : 0;

                if ($count_tmp == 0) {
                    $errors[] = "No hay detalle agregados a la guía de remisión." . mysqli_error($con);
                } else {
                    /*
                     * ===================== VALIDACIÓN PREVIA DE infoAdicional =====================
                     * - Trae adicional_tmp si hay
                     * - Si no hay, usa Correo/Dirección del cliente
                     * - Normaliza/valida, evita duplicados, aplica límite de 15
                     */
                    $campos_adicionales = array();
                    $errores_validacion  = array();

                    // Traer adicionales temporales
                    $busca_adicional_tmp = "SELECT concepto, detalle FROM adicional_tmp WHERE id_usuario='".$id_usuario."' AND serie_factura = '".$serie_guia."' AND secuencial_factura = '".$secuencial_guia."' ";
                    $query_gr_tmp = mysqli_query($con, $busca_adicional_tmp);
                    $contar_registros = $query_gr_tmp ? mysqli_num_rows($query_gr_tmp) : 0;

                    if ($contar_registros == 0) {
                        // Si no hay adicionales, usar Cliente (correo/dirección)
                        $busca_cliente = "SELECT email, direccion FROM clientes WHERE id = '".$id_cliente_guia."' LIMIT 1";
                        $query_cliente = mysqli_query($con, $busca_cliente);
                        $row_cliente   = $query_cliente ? mysqli_fetch_array($query_cliente) : null;

                        $correo_cliente    = $row_cliente ? $row_cliente['email'] : '';
                        $direccion_cliente = $row_cliente ? $row_cliente['direccion'] : '';

                        $campos_adicionales[] = array('concepto' => 'Correo',    'detalle' => $correo_cliente);
                        $campos_adicionales[] = array('concepto' => 'Dirección', 'detalle' => $direccion_cliente);
                    } else {
                        while ($row_detalle_adicional = mysqli_fetch_array($query_gr_tmp)) {
                            $campos_adicionales[] = array(
                                'concepto' => $row_detalle_adicional['concepto'],
                                'detalle'  => $row_detalle_adicional['detalle']
                            );
                        }
                    }

                    // Normalizar, validar y evitar duplicados por concepto (case-insensitive)
                    $vistos = array();
                    for ($i = 0; $i < count($campos_adicionales); $i++) {
                        $concepto = $campos_adicionales[$i]['concepto'];
                        $detalle  = $campos_adicionales[$i]['detalle'];

                        $msgErr = '';
                        if (!validar_par_adicional($concepto, $detalle, $msgErr)) {
                            $errores_validacion[] = $msgErr;
                            continue;
                        }

                        $key = function_exists('mb_strtolower') ? mb_strtolower($concepto, 'UTF-8') : strtolower($concepto);
                        if (isset($vistos[$key])) {
                            $errores_validacion[] = "Información adicional: el nombre \"".$concepto."\" está duplicado.";
                            continue;
                        }
                        $vistos[$key] = true;

                        // Guardar normalizados
                        $campos_adicionales[$i]['concepto'] = $concepto;
                        $campos_adicionales[$i]['detalle']  = $detalle;
                    }

                    // Límite máximo 15 campos
                    if (count($campos_adicionales) > 15) {
                        $errores_validacion[] = "Información adicional: máximo permitido 15 campos. Actualmente: ".count($campos_adicionales).".";
                    }

                    if (!empty($errores_validacion)) {
                        foreach ($errores_validacion as $e) { $errors[] = $e; }
                    } else {
                        /*
                         * ===================== TRANSACCIÓN ATÓMICA (PHP 5.6) =====================
                         */
                        mysqli_autocommit($con, false);
                        $todo_ok = true;

                        // Encabezado GR
                        $guarda_encabezado_guia = "INSERT INTO encabezado_gr VALUES (null, '$ruc_empresa','$fecha_guia','$fecha_salida_guia','$fecha_llegada_guia','$serie_guia','$secuencial_guia','$factura_guia','$origen_guia','$destino_guia','$aduanero_guia','$codigo_destino_guia','$id_transportista_guia','$id_cliente_guia','$placa_guia','$fecha_registro','ELECTRÓNICA','PENDIENTE',$id_usuario,'0','','','$motivo_guia','$ruta_guia','PENDIENTE')";
                        $ok1 = mysqli_query($con, $guarda_encabezado_guia);
                        if (!$ok1) {
                            $errors[] = "Error guardando encabezado: " . mysqli_error($con);
                            $todo_ok = false;
                        }

                        // Detalle adicional validado
                        if ($todo_ok) {
                            for ($i = 0; $i < count($campos_adicionales); $i++) {
                                $concepto_ins = mysqli_real_escape_string($con, $campos_adicionales[$i]['concepto']);
                                $detalle_ins  = mysqli_real_escape_string($con, $campos_adicionales[$i]['detalle']);
                                $sqlAdi = "INSERT INTO detalle_adicional_gr VALUES (null, '$ruc_empresa','$serie_guia','$secuencial_guia','$concepto_ins','$detalle_ins')";
                                $okAdi = mysqli_query($con, $sqlAdi);
                                if (!$okAdi) {
                                    $errors[] = "Error guardando información adicional (".$concepto_ins."): " . mysqli_error($con);
                                    $todo_ok = false;
                                    break;
                                }
                            }
                        }

                        // Detalle productos
                        if ($todo_ok) {
                            while ($row_detalle = mysqli_fetch_array($sql_guia_temporal)) {
                                $cantidad_guia = str_replace(",", ".", $row_detalle["cantidad_tmp"]);
                                $id_producto   = $row_detalle['id_producto'];

                                // Buscar datos de producto
                                $busca_nombre_producto = "SELECT codigo_producto, nombre_producto FROM productos_servicios WHERE ruc_empresa='$ruc_empresa' AND id=".$id_producto." LIMIT 1";
                                $result_nombre_producto = mysqli_query($con, $busca_nombre_producto);
                                $datos_nombre_producto  = $result_nombre_producto ? mysqli_fetch_array($result_nombre_producto) : null;

                                $codigo_producto = strtoupper(isset($datos_nombre_producto['codigo_producto']) ? $datos_nombre_producto['codigo_producto'] : '');
                                $nombre_producto = strtoupper(isset($datos_nombre_producto['nombre_producto']) ? $datos_nombre_producto['nombre_producto'] : '');

                                $sqlDet = "INSERT INTO cuerpo_gr VALUES (null, '$ruc_empresa','$serie_guia',$secuencial_guia,$id_producto,'$cantidad_guia','$codigo_producto','$nombre_producto')";
                                $okDet = mysqli_query($con, $sqlDet);
                                if (!$okDet) {
                                    $errors[] = "Error guardando detalle de producto: " . mysqli_error($con);
                                    $todo_ok = false;
                                    break;
                                }
                            }
                        }

                        // Commit o rollback
                        if ($todo_ok) {
                            mysqli_commit($con);
                            mysqli_autocommit($con, true);
                            echo "<script>
                                    $.notify('Guía de remisión guardada con éxito','success');
                                    setTimeout(function (){location.href ='../modulos/guias_remision.php'}, 1000); 
                                  </script>";
                            // Terminar aquí correctamente
                            exit;
                        } else {
                            mysqli_rollback($con);
                            mysqli_autocommit($con, true);
                        }
                        /* ===================== FIN TRANSACCIÓN ===================== */
                    }
                }
            }
        }
    }
}

/* ====================== RENDER DE MENSAJES ====================== */

if (!empty($errors)) {
?>
    <div class="alert alert-danger" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>Atención! </strong>
        <?php
        foreach ($errors as $error) {
            echo "<br/>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        }
        ?>
    </div>
<?php
}
if (!empty($messages)) {
?>
    <div class="alert alert-success" role="alert">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong>¡Bien hecho! </strong>
        <?php
        foreach ($messages as $message) {
            echo $message;
        }
        ?>
    </div>
<?php
}
?>
