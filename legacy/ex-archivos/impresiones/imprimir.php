<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
    $id_usuario = $_SESSION['id_usuario'];
    $id_empresa = $_SESSION['id_empresa'];
    $ruc_empresa = $_SESSION['ruc_empresa'];
    include("../conexiones/conectalogin.php");
    ini_set('date.timezone', 'America/Guayaquil');
    $con = conenta_login();

    $action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
    $id_mesa = isset($_GET['id_mesa']) ? $_GET['id_mesa'] : "";
    if (!empty($id_mesa)) {
        $detalle_mesa = mysqli_query($con, "SELECT * FROM mesas WHERE id_mesa = '" . $id_mesa . "' ");
        $row_mesas = mysqli_fetch_array($detalle_mesa);
        $nombre_mesa = "Mesa: " . $row_mesas['nombre_mesa'];
    } else {
        $nombre_mesa = "";
    }

    $busca_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE ruc = '" . $ruc_empresa . "'");

    $datos_mesas_integra = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' ");
    $datos_mesas_precuenta = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' ");
    $datos_mesas_cocina = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto LEFT JOIN marca_producto as mar ON mar.id_producto=ser.id LEFT JOIN opciones_envio_impresion as opc ON opc.id_categoria=mar.id_marca WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' and opc.id_opcion='1' and opc.ruc_empresa='" . $ruc_empresa . "' ");
    $datos_mesas_barra = mysqli_query($con, "SELECT mesa.id_producto as id_producto, mesa.descuento as descuento, mesa.cantidad as cantidad, ser.nombre_producto as detalle, mesa.precio as valor FROM detalle_mesas as mesa INNER JOIN productos_servicios as ser ON ser.id=mesa.id_producto LEFT JOIN marca_producto as mar ON mar.id_producto=ser.id LEFT JOIN opciones_envio_impresion as opc ON opc.id_categoria=mar.id_marca WHERE mesa.id_mesa='" . $id_mesa . "' and mesa.estado='PENDIENTE' and opc.id_opcion='2' and opc.ruc_empresa='" . $ruc_empresa . "'");

    $datos_empresa = mysqli_fetch_assoc($busca_empresa);
    $nombre_empresa = $datos_empresa['nombre_comercial'];
    $direccion_empresa = $datos_empresa['direccion'];

    //para imprimir tickect de la factura
    $id_documento = base64_decode($_GET['id_factura']);
    $encabezado_factura = mysqli_query($con, "SELECT * FROM encabezado_factura as enc INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE enc.id_encabezado_factura = '" . $id_documento . "' ");
    $row_encabezado = mysqli_fetch_array($encabezado_factura);
    $cliente = $row_encabezado['nombre'];
    $fecha_factura = date('d-m-Y', strtotime($row_encabezado['fecha_factura']));
    $ruc_cliente = $row_encabezado['ruc'];
    $dir_cliente = $row_encabezado['direccion'];
    $tel_cliente = $row_encabezado['telefono'];
    $email_cliente = $row_encabezado['email'];
    $total_factura = $row_encabezado['total_factura'];
    $serie_factura = $row_encabezado['serie_factura'];
    $secuencial_factura = $row_encabezado['secuencial_factura'];
    $numero_factura = $serie_factura . "-" . str_pad($secuencial_factura, 9, "000000000", STR_PAD_LEFT);

    //$sql_vendedor = mysqli_query($con, "SELECT * FROM vendedores_ventas as ven_fac INNER JOIN vendedores as ven ON ven.id_vendedor=ven_fac.id_vendedor WHERE ven_fac.id_venta = '".$id_documento."' ");
    //$row_vendedor = mysqli_fetch_array($sql_vendedor);
    //$vendedor =$row_vendedor['nombre'];

    //hasta aqui la factura tickect



?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Impresiones</title>
    </head>

    <body style="width:300px; height: auto; font-size:100%; font-family:Verdana, Arial, Helvetica, sans-serif;">
        <!--Barra-->
        <?php
        if ($action == "barra") {
        ?>
            <div class="container">
                <label><?php echo $nombre_empresa ?></label><br>
                <hr>
                <label>Pedido a barra</label><br>
                <hr>
                <label><?php echo $nombre_mesa ?></label><br>
                <table>
                    <tr>
                        <td>Cant</td>
                        <td>Detalle</td>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_assoc($datos_mesas_barra)) {
                        $cantidad = $row['cantidad'];
                        $detalle = $row['detalle'];
                    ?>
                        <tr>
                            <td><?php echo $cantidad; ?></td>
                            <td><?php echo strtoupper($detalle); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
                <br>
                <br>
                <br>
            </div>
        <?php
        }

        if ($action == "cocina") {
        ?>
            <div class="container">
                <label><?php echo $nombre_empresa ?></label><br>
                <hr>
                <label>Pedido a cocina</label><br>
                <hr>
                <label><?php echo $nombre_mesa ?></label><br>
                <table>
                    <tr>
                        <td>Cant</td>
                        <td>Detalle</td>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_assoc($datos_mesas_cocina)) {
                        $cantidad = $row['cantidad'];
                        $detalle = $row['detalle'];
                    ?>
                        <tr>
                            <td><?php echo $cantidad; ?></td>
                            <td><?php echo strtoupper($detalle); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
                <br>
                <br>
                <br>
            </div>
        <?php
        }


        if ($action == "comanda_integra") {
        ?>
            <div class="container">
                <label><?php echo $nombre_empresa ?></label><br>
                <hr>
                <label>Detalle del pedido</label><br>
                <hr>
                <label><?php echo $nombre_mesa ?></label><br>
                <table>
                    <tr>
                        <td>Cant</td>
                        <td>Detalle</td>
                    </tr>
                    <?php
                    while ($row = mysqli_fetch_assoc($datos_mesas_integra)) {
                        $cantidad = $row['cantidad'];
                        $detalle = $row['detalle'];
                    ?>
                        <tr>
                            <td><?php echo $cantidad; ?></td>
                            <td><?php echo strtoupper($detalle); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </table>
                <br>
                <br>
                <br>
            </div>
        <?php
        }

        if ($action == "precuenta") {
        ?>
            <div class="container">
                <label><?php echo $nombre_empresa ?></label><br>
                <hr>
                <label>Detalle de cuenta</label><br>
                <hr>
                <label><?php echo $nombre_mesa ?></label><br>
                <label>Nombre:</label><br>
                <label>Ced/Ruc:</label><br>
                <label>Telf:</label><br>
                <label>Dir:</label><br>
                <label>Mail:</label><br>
                <table>
                    <tr>
                        <td>Cant
                            <hr />
                        </td>
                        <td>Detalle
                            <hr />
                        </td>
                        <td>Valor
                            <hr />
                        </td>
                    </tr>
                    <?php
                    $sutotal_a_pagar = array();
                    $iva = array();
                    while ($row = mysqli_fetch_assoc($datos_mesas_precuenta)) {
                        $cantidad = $row['cantidad'];
                        $detalle = $row['detalle'];
                        $valor = $row['valor'];

                        $busca_nombre_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $row['id_producto'] . "' ");
                        $row_productos = mysqli_fetch_array($busca_nombre_producto);
                        $tarifa_iva = $row_productos['tarifa_iva'];

                        //buscar tipos iva
                        $busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
                        $row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
                        $nombre_tarifa = $row_tarifa['tarifa'];
                        $porcentaje_iva = $row_tarifa['porcentaje_iva'];

                        $sutotal_a_pagar[] = (($row['cantidad'] * $row['valor']) - $row['descuento']);
                        $iva[] = (($row['cantidad'] * $row['valor']) - $row['descuento']) * ($porcentaje_iva / 100);

                    ?>
                        <tr>
                            <td><?php echo number_format($cantidad, 2, '.', ''); ?></td>
                            <td><?php echo strtoupper($detalle); ?></td>
                            <td aling="right"><?php echo number_format($valor, 2, '.', ''); ?></td>
                        </tr>
                    <?php
                    }
                    $total_a_pagar = array_sum($sutotal_a_pagar) + array_sum($iva);
                    ?>
                    <tr>
                        <td></td>
                        <td>Subtotal:</td>
                        <td><?php echo number_format(array_sum($sutotal_a_pagar), 2, '.', ''); ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>IVA:</td>
                        <td><?php echo number_format(array_sum($iva), 2, '.', ''); ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>Servicio:</td>
                        <td><?php echo number_format($propina, 2, '.', ''); ?></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td>Total:</td>
                        <td><?php echo number_format($total_a_pagar + $propina, 2, '.', ''); ?></td>
                    </tr>
                </table>
                <br>
                <label>Nota-Propina:</label><br>
                <hr>
                <label></label><br>
                <hr>
                <br>
                <br>
                <br>
            </div>
        <?php
        }

        if ($action == "ticket_factura_venta_a2") {
        ?>
            <div class="container">
                <table>
                    <tr ALIGN="center">
                        <td><?php echo $nombre_empresa ?></td>
                    </tr>
                    <tr ALIGN="center">
                        <td>RUC <?php echo substr($ruc_empresa, 0, 10) . "001" ?></td>
                    </tr>
                    <tr ALIGN="center">
                        <td><?php echo $direccion_empresa ?></td>
                    </tr>
                    <tr ALIGN="center">
                        <td>FACTURA <?php echo $numero_factura ?></td>
                    </tr>
                    <tr ALIGN="center">
                        <td>----------------------------------------</td>
                    </tr>
                    <tr ALIGN="left">
                        <td>CLIENTE: <?php echo $cliente ?></td>
                    </tr>
                    <tr ALIGN="left">
                        <td>CED/RUC: <?php echo $ruc_cliente ?></td>
                    </tr>
                    <tr ALIGN="left">
                        <td>FECHA: <?php echo $fecha_factura ?></td>
                    </tr>
                    <tr ALIGN="center">
                        <td>----------------------------------------</td>
                    </tr>
                </table>
                <table>
                    <thead>
                        <tr>
                            <td>Cant</td>
                            <td>Detalle</td>
                            <td ALIGN="right">Subtotal</td>
                        </tr>
                        <tr ALIGN="center">
                            <td colspan="3">----------------------------------------</td>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        $sutotal_a_pagar = array();
                        $iva = array();
                        $detalle_factura = mysqli_query($con, "SELECT * FROM cuerpo_factura WHERE serie_factura = '" . $serie_factura . "' and secuencial_factura='" . $secuencial_factura . "' and ruc_empresa='" . $ruc_empresa . "' ");
                        while ($row_detalle = mysqli_fetch_assoc($detalle_factura)) {
                            $tarifa_iva = $row_detalle['tarifa_iva'];
                            //buscar tipos iva
                            $busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
                            $row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
                            $nombre_tarifa = $row_tarifa['tarifa'];
                            $porcentaje_iva = $row_tarifa['porcentaje_iva'];

                            $sutotal_a_pagar[] = $row_detalle['subtotal_factura'] - $row_detalle['descuento'];
                            $iva[] = ($row_detalle['subtotal_factura'] - $row_detalle['descuento']) * ($porcentaje_iva / 100);
                        ?>
                            <tr>
                                <td><?php echo number_format($row_detalle['cantidad_factura'], 2, '.', ''); ?></td>
                                <td><?php echo mb_strtoupper($row_detalle['nombre_producto'], 'UTF-8'); ?></td>
                                <td aling="right"><?php echo number_format($row_detalle['subtotal_factura'] - $row_detalle['descuento'], 2, '.', ''); ?></td>
                            </tr>
                        <?php
                        }
                        $total_a_pagar = array_sum($sutotal_a_pagar) + array_sum($iva);
                        ?>
                        <tr ALIGN="center">
                            <td colspan="3">----------------------------------------</td>
                        </tr>
                        <tr ALIGN="right">
                            <td></td>
                            <td>Subtotal:</td>
                            <td><?php echo number_format(array_sum($sutotal_a_pagar), 2, '.', ''); ?></td>
                        </tr>
                        <tr ALIGN="right">
                            <td></td>
                            <td>IVA:</td>
                            <td><?php echo number_format(array_sum($iva), 2, '.', ''); ?></td>
                        </tr>
                        <tr ALIGN="right">
                            <td></td>
                            <td>Total:</td>
                            <td><?php echo number_format($total_a_pagar, 2, '.', ''); ?></td>
                        </tr>
                    <tbody>
                </table>
                <br>
            </div>
        <?php
        }

        ?>

        <script src="../js/jquery-1.12.4.js"></script>
        <script src="../js/jquery-ui.js"></script>
        <script>
            $(document).ready(function() {
                print();
            });

            setTimeout(function() {
                window.close();
            }, 3000);
        </script>
    </body>

    </html>

<?php
} else {
    header('Location: ../includes/logout.php');
    exit;
}
?>