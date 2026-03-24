<?php
include __DIR__ . "/../helpers/helpers.php";

class contabilizacion
{

    //para limpiar tablas
    public function limpiarTmp($con, $ruc_empresa)
    {
        $eliminar_asientos = mysqli_query($con, "DELETE FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' ");
        if ($eliminar_asientos) {
            return true;
        } else {
            return false;
        }
    }

    //para eliminar registros que estan en el tmp por contabilizar
    public function eliminaRegistroPorContabilizar($con, $ruc_empresa, $id_registro)
    {
        $eliminar_asientos = mysqli_query($con, "DELETE FROM asientos_automaticos_tmp WHERE ruc_empresa = '" . $ruc_empresa . "' and id_registro='" . $id_registro . "'");
        if ($eliminar_asientos) {
            return true;
        } else {
            return false;
        }
    }

    //para contar las cuentas asignadas en asientos programados
    public function contarCuentasAsignadas($con, $tipo_asiento, $ruc_empresa)
    {
        $sql = mysqli_query($con, "SELECT count(*) as numrows FROM asientos_programados 
        WHERE tipo_asiento = '" . $tipo_asiento . "' and ruc_empresa ='" . $ruc_empresa . "' ");
        $row = mysqli_fetch_array($sql);
        $result = $row['numrows'] > 0 ? 1 : 0;
        return $result;
    }

    //para sacar los id de asiento programado
    public function infoAsientoProgramado($con, $tipo_asiento, $id_asiento_tipo, $ruc_empresa, $idTipoAsiento)
    {
        $sql = mysqli_query($con, "SELECT * FROM asientos_programados 
        WHERE ruc_empresa ='" . $ruc_empresa . "'
        AND tipo_asiento = '" . $tipo_asiento . "' 
        AND id_asiento_tipo = '" . $id_asiento_tipo . "' 
        AND id_pro_cli ='" . $idTipoAsiento . "'");
        return mysqli_fetch_array($sql);
    }

    //para pasar las facturas de ventas pendientes por contabilizar al tmp
    public function documentosVentasFacturas($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        //para cuando estan asignadas cuentas en las marcas
        $ventasmarcasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturasCxC', $ruc_empresa);
        $cuenta_marca = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturas', $ruc_empresa);
        $ventasmarcasfacturasIva = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturasIva', $ruc_empresa);
        $cuentasMarcas = ($ventasmarcasfacturasCxC + $cuenta_marca + $ventasmarcasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las categorias
        $ventascategoriasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturasCxC', $ruc_empresa);
        $cuenta_categoria = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturas', $ruc_empresa);
        $ventascategoriasfacturasIva = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturasIva', $ruc_empresa);
        $cuentasCategorias = ($ventascategoriasfacturasCxC + $cuenta_categoria + $ventascategoriasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los productos
        $productoventafacturaIva = $this->contarCuentasAsignadas($con, 'productoventafacturaIva', $ruc_empresa);
        $productoventafacturaCxC = $this->contarCuentasAsignadas($con, 'productoventafacturaCxC', $ruc_empresa);
        $cuenta_producto = $this->contarCuentasAsignadas($con, 'productoventafactura', $ruc_empresa);
        $cuentasProductos = ($productoventafacturaIva + $productoventafacturaCxC +  $cuenta_producto) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los clientes
        $cuenta_cliente = $this->contarCuentasAsignadas($con, 'cliente', $ruc_empresa);
        $ventas_cliente_iva = $this->contarCuentasAsignadas($con, 'ventas_cliente_iva', $ruc_empresa);
        $ventas_cliente_cxc = $this->contarCuentasAsignadas($con, 'ventas_cliente_cxc', $ruc_empresa);
        $cuentasClientes = ($cuenta_cliente + $ventas_cliente_iva + $ventas_cliente_cxc) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las tarifas de iva
        $tarifa_iva_ventas = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas', $ruc_empresa);
        $tarifa_iva_ventas_iva = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_iva', $ruc_empresa);
        $tarifa_iva_ventas_cxc = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_cxc', $ruc_empresa);
        $cuentaTarifaIvaVentasFacturas = ($tarifa_iva_ventas + $tarifa_iva_ventas_iva + $tarifa_iva_ventas_cxc) > 0 ? 1 : 0;

        $sql = mysqli_query($con, "SELECT ef.id_encabezado_factura as id_documento, 
        ef.id_cliente as id_cliente, concat(ef.serie_factura, '-', LPAD(ef.secuencial_factura,9,'0')) as documento,
         (cf.subtotal_factura-cf.descuento) as subtotal, ef.fecha_factura as fecha_documento, 'VENTAS' as transaccion, 
        concat('FAC', ef.id_encabezado_factura) as id_registro, cli.nombre as cliente, cf.id_producto as id_producto, 
        (select id_marca from marca_producto where id_producto = cf.id_producto) as id_marca, 
        (select id_grupo from grupo_producto_asignado where id_producto = cf.id_producto) as id_categoria, cf.tarifa_iva as tipo_iva
        FROM cuerpo_factura as cf 
        INNER JOIN encabezado_factura as ef 
        ON ef.serie_factura = cf.serie_factura and ef.secuencial_factura = cf.secuencial_factura 
        INNER JOIN clientes as cli 
        ON cli.id=ef.id_cliente
        WHERE cf.ruc_empresa = '" . $ruc_empresa . "' and ef.ruc_empresa = '" . $ruc_empresa . "' 
        and DATE_FORMAT(ef.fecha_factura, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
        and '" . date("Y/m/d", strtotime($hasta)) . "' and ef.id_registro_contable='0' ");

      
        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            //para ver si hay mas de una opcion de contabilizacion, solo puede haber una opcion
            $opciones_contabilizacion =   $cuentasMarcas + $cuentasCategorias + $cuentasProductos + $cuentasClientes +  $cuentaTarifaIvaVentasFacturas;
            if ($opciones_contabilizacion > 1) {
                return "configurarCuentas";
            } else {
                //me separa en un arreglos los documentos que van al debe y otro array los documentos que van al haber
                //datos del documentos para el detalle del asiento
                $arrayDocumentosDebe = array();
                $arrayDocumentosHaber = array();
                $codigo_unico = codigo_aleatorio(20);
                while ($row = mysqli_fetch_array($sql)) {
                    $datos_documentos = ['tipo_iva' => $row['tipo_iva'], 'id_categoria' => $row['id_categoria'], 'id_marca' => $row['id_marca'], 'id_producto' => $row['id_producto'], 'subtotal' => $row['subtotal'], 'id_documento' => $row['id_documento'], 'fecha_documento' => $row['fecha_documento'], 'documento' => $row['documento'], 'cliente' => $row['cliente'], 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_cliente' => $row['id_cliente'], 'transaccion' => $row['transaccion']];
                    if (!$this->documento_existente($arrayDocumentosDebe, $row['id_documento'])) {
                        $arrayDocumentosDebe[] = $datos_documentos;
                    }
                    $arrayDocumentosHaber[] = $datos_documentos;
                }

                //cuentas del debe
                foreach ($arrayDocumentosDebe as $documento) {
                    //para la cuenta por cobrar
                    $total_factura = $this->totales_factura($con, $documento['id_documento'])['total'];
                    $array_datos_por_cobrar = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Factura de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => number_format($total_factura, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                    $id_marca_configurado = $this->infoAsientoProgramado($con, 'ventasmarcasfacturasCxC', '0', $ruc_empresa, $documento['id_marca']);
                    $id_categoria_configurado = $this->infoAsientoProgramado($con, 'ventascategoriasfacturasCxC', '0', $ruc_empresa, $documento['id_categoria']);
                    $id_producto_configurado = $this->infoAsientoProgramado($con, 'productoventafacturaCxC', '0', $ruc_empresa, $documento['id_producto']);
                    $id_cliente_configurado = $this->infoAsientoProgramado($con, 'ventas_cliente_cxc', '0', $ruc_empresa, $documento['id_cliente']);
                    $id_tarifa_iva_configurado = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_cxc', '0', $ruc_empresa, $documento['tipo_iva']);

                    //para las cuentas de ventas de la cuenta por cobrar
                    if ($cuentasMarcas == 1 && isset($id_marca_configurado)) {
                        $id_cuenta = isset($id_marca_configurado) ? $id_marca_configurado['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($id_categoria_configurado)) {
                        $id_cuenta = isset($id_categoria_configurado) ? $id_categoria_configurado['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($id_producto_configurado)) {
                        $id_cuenta = isset($id_producto_configurado) ? $id_producto_configurado['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($id_cliente_configurado)) {
                        $id_cuenta = isset($id_cliente_configurado) ? $id_cliente_configurado['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($id_tarifa_iva_configurado)) {
                        $id_cuenta = isset($id_tarifa_iva_configurado) ? $id_tarifa_iva_configurado['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXCC')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta por cobrar en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                    }

                    //para mostrar las cuentas de iva
                    $total_iva = 0;
                    $sql_iva_factura = $this->iva_factura_venta($con, $documento['id_documento']);
                    while ($row_iva_factura = mysqli_fetch_array($sql_iva_factura)) {
                        $total_iva = $row_iva_factura['total_iva'];
                        $array_datos_iva = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'IVA en ventas en factura de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($total_iva, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                        $id_marca_configurado = $this->infoAsientoProgramado($con, 'ventasmarcasfacturasIva', '0', $ruc_empresa, $documento['id_marca']);
                        $id_categoria_configurado = $this->infoAsientoProgramado($con, 'ventascategoriasfacturasIva', '0', $ruc_empresa, $documento['id_categoria']);
                        $id_producto_configurado = $this->infoAsientoProgramado($con, 'productoventafacturaIva', '0', $ruc_empresa, $documento['id_producto']);
                        $id_cliente_configurado = $this->infoAsientoProgramado($con, 'ventas_cliente_iva', '0', $ruc_empresa, $documento['id_cliente']);
                        $id_tarifa_iva_configurado = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_iva', '0', $ruc_empresa, $documento['tipo_iva']);

                        if ($cuentasMarcas == 1 && isset($id_marca_configurado)) {
                            $id_cuenta = isset($id_marca_configurado) ? $id_marca_configurado['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                        } else if ($cuentasCategorias == 1 && isset($id_categoria_configurado)) {
                            $id_cuenta = isset($id_categoria_configurado) ? $id_categoria_configurado['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                        } else if ($cuentasProductos == 1 && $id_producto_configurado) {
                            $id_cuenta = isset($id_producto_configurado) ? $id_producto_configurado['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                        } else if ($cuentasClientes == 1 && isset($id_cliente_configurado)) {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'ventas_cliente_iva', '0', $ruc_empresa, $documento['id_cliente']);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                        } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($id_tarifa_iva_configurado)) {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_iva', '0', $ruc_empresa, $documento['tipo_iva']);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                        } else {
                            $id_asiento_tipo = $row_iva_factura['codigo'];
                            $id_cuenta = $this->infoAsientoProgramado($con, 'iva_ventas', '0', $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta para el IVA en ventas en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                        }
                    }

                    //PARA LA CUENTA CONTABLE DE OTROS VALORES COMO PROPINA O TASA
                    $propina = $this->totales_factura($con, $documento['id_documento'])['propina'];
                    $tasa_turistica = $this->totales_factura($con, $documento['id_documento'])['tasa_turistica'];
                    if ($propina > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Propina en factura de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($propina, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOV')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta para propina en ventas en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                    }
                    if ($tasa_turistica > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Otros valores en factura de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($tasa_turistica, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOV')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta para otros valores como propina o tasas adicionales en ventas en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                    }
                }

                //cuentas del haber
                foreach ($arrayDocumentosHaber as $documento) {
                    //para los subtotales de la factura de venta
                    $array_datos_subtotal = array(
                        'fecha_documento' => $documento['fecha_documento'],
                        'detalle' => 'Factura de venta N. ' . $documento['documento'] . ' ' .  $documento['cliente'],
                        'valor_debe' => '0',
                        'valor_haber' => number_format($documento['subtotal'], 2, '.', ''),
                        'id_registro' => $documento['id_registro'],
                        'id_relacion' => $documento['id_documento'],
                        'codigo_unico' => $documento['codigo_unico'],
                        'id_cli_pro' => $documento['id_cliente'],
                        'transaccion' => $documento['transaccion']
                    );

                    $id_marca_configurado = $this->infoAsientoProgramado($con, 'ventasmarcasfacturas', '0', $ruc_empresa, $documento['id_marca']);
                    $id_categoria_configurado = $this->infoAsientoProgramado($con, 'ventascategoriasfacturas', '0', $ruc_empresa, $documento['id_categoria']);
                    $id_producto_configurado = $this->infoAsientoProgramado($con, 'productoventafactura', '0', $ruc_empresa, $documento['id_producto']);
                    $id_cliente_configurado = $this->infoAsientoProgramado($con, 'cliente', '0', $ruc_empresa, $documento['id_cliente']);
                    $id_tarifa_iva_configurado = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas', '0', $ruc_empresa, $documento['tipo_iva']);


                    if ($cuentasMarcas == 1 && isset($id_marca_configurado)) {
                        $id_cuenta = isset($id_marca_configurado) ? $id_marca_configurado['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($id_categoria_configurado)) {
                        $id_cuenta = isset($id_categoria_configurado) ? $id_categoria_configurado['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($id_producto_configurado)) {
                        $id_cuenta = isset($id_producto_configurado) ? $id_producto_configurado['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($id_cliente_configurado)) {
                        $id_cuenta = isset($id_cliente_configurado) ? $id_cliente_configurado['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($id_tarifa_iva_configurado)) {
                        $id_cuenta = isset($id_tarifa_iva_configurado) ? $id_tarifa_iva_configurado['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCSV')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Configurar cuentas contables de ventas con facturas, tener en cuenta si se genera los asientos por categorías y marcas, deben tener los productos o servicios agregados categoría o marca.');
                    }
                }
            }
        }
    }

    public function documentosVentasRecibos($con, $ruc_empresa, $desde, $hasta)
    {
        //para cuando estan asignadas cuentas en las tarifas de iva
        $cuenta_tarifa_iva = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_recibos', $ruc_empresa);

        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        //para cuando estan asignadas cuentas en las marcas
        $ventasmarcasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventasmarcasrecibosCxC', $ruc_empresa);
        $cuenta_marca = $this->contarCuentasAsignadas($con, 'ventasmarcasrecibos', $ruc_empresa);
        $ventasmarcasfacturasIva = $this->contarCuentasAsignadas($con, 'ventasmarcasrecibosIva', $ruc_empresa);
        $cuentasMarcas = ($ventasmarcasfacturasCxC + $cuenta_marca + $ventasmarcasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las categorias
        $ventascategoriasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventascategoriasrecibosCxC', $ruc_empresa);
        $cuenta_categoria = $this->contarCuentasAsignadas($con, 'ventascategoriasrecibos', $ruc_empresa);
        $ventascategoriasfacturasIva = $this->contarCuentasAsignadas($con, 'ventascategoriasrecibosIva', $ruc_empresa);
        $cuentasCategorias = ($ventascategoriasfacturasCxC + $cuenta_categoria + $ventascategoriasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los productos
        $productoventafacturaIva = $this->contarCuentasAsignadas($con, 'productoventareciboIva', $ruc_empresa);
        $productoventafacturaCxC = $this->contarCuentasAsignadas($con, 'productoventareciboCxC', $ruc_empresa);
        $cuenta_producto = $this->contarCuentasAsignadas($con, 'productoventarecibo', $ruc_empresa);
        $cuentasProductos = ($productoventafacturaIva + $productoventafacturaCxC +  $cuenta_producto) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los clientes
        $cuenta_cliente = $this->contarCuentasAsignadas($con, 'clienteRecibo', $ruc_empresa);
        $ventas_cliente_iva = $this->contarCuentasAsignadas($con, 'clienteRecibo_iva', $ruc_empresa);
        $ventas_cliente_cxc = $this->contarCuentasAsignadas($con, 'clienteRecibo_cxc', $ruc_empresa);
        $cuentasClientes = ($cuenta_cliente + $ventas_cliente_iva + $ventas_cliente_cxc) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las tarifas de iva
        $tarifa_iva_ventas = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_recibos', $ruc_empresa);
        $tarifa_iva_ventas_iva = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_recibos_iva', $ruc_empresa);
        $tarifa_iva_ventas_cxc = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_recibos_cxc', $ruc_empresa);
        $cuentaTarifaIvaVentasFacturas = ($tarifa_iva_ventas + $tarifa_iva_ventas_iva + $tarifa_iva_ventas_cxc) > 0 ? 1 : 0;

        $sql = mysqli_query($con, "SELECT er.id_encabezado_recibo as id_documento, er.id_cliente as id_cliente, concat(er.serie_recibo,'-', LPAD(er.secuencial_recibo,9,'0')) as documento,
         round((cr.cantidad * cr.valor_unitario) - cr.descuento,2) as subtotal, er.fecha_recibo as fecha_documento, 'RECIBOS' as transaccion, 
        concat('REC',er.id_encabezado_recibo) as id_registro, cli.nombre as cliente, cr.id_producto as id_producto,
         (select id_marca from marca_producto where id_producto = cr.id_producto) as id_marca , 
        (select id_grupo from grupo_producto_asignado where id_producto = cr.id_producto) as id_categoria, cr.tarifa_iva as tipo_iva 
        FROM encabezado_recibo as er INNER JOIN cuerpo_recibo as cr ON er.id_encabezado_recibo = cr.id_encabezado_recibo
        INNER JOIN clientes as cli ON cli.id=er.id_cliente
        WHERE er.ruc_empresa = '" . $ruc_empresa . "' 
        and DATE_FORMAT(er.fecha_recibo, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
        and '" . date("Y/m/d", strtotime($hasta)) . "' and er.id_registro_contable='0' and er.status !='2' ");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $opciones_contabilizacion =   $cuentasMarcas + $cuentasCategorias + $cuentasProductos + $cuentasClientes +  $cuentaTarifaIvaVentasFacturas;
            if ($opciones_contabilizacion > 1) {
                return "configurarCuentas";
            } else {
                //obtengo todos los datos de la consulta y los guardo en un array para luego formar el asiento ordenoa con debe y haber
                $arrayDocumentosDebe = array();
                $arrayDocumentosHaber = array();
                $codigo_unico = codigo_aleatorio(20);
                while ($row = mysqli_fetch_array($sql)) {
                    $datos_documentos = array('tipo_iva' => $row['tipo_iva'], 'id_categoria' => $row['id_categoria'], 'id_marca' => $row['id_marca'], 'id_producto' => $row['id_producto'], 'subtotal' => $row['subtotal'], 'id_documento' => $row['id_documento'], 'fecha_documento' => $row['fecha_documento'], 'documento' => $row['documento'], 'cliente' => $row['cliente'], 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_cliente' => $row['id_cliente'], 'transaccion' => $row['transaccion']);
                    if (!$this->documento_existente($arrayDocumentosDebe, $row['id_documento'])) {
                        $arrayDocumentosDebe[] = $datos_documentos;
                    }
                    $arrayDocumentosHaber[] = $datos_documentos;
                }

                //cuentas del debe
                foreach ($arrayDocumentosDebe as $documento) {
                    //para la cuenta por cobrar
                    $total_recibo = $this->totales_recibo($con, $documento['id_documento'])['total'];
                    $array_datos_por_cobrar = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Recibo de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => number_format($total_recibo, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                    $ventasmarcasrecibosCxC = $this->infoAsientoProgramado($con, 'ventasmarcasrecibosCxC', '0', $ruc_empresa, $documento['id_marca']);
                    $ventascategoriasrecibosCxC = $this->infoAsientoProgramado($con, 'ventascategoriasrecibosCxC', '0', $ruc_empresa, $documento['id_categoria']);
                    $productoventareciboCxC = $this->infoAsientoProgramado($con, 'productoventareciboCxC', '0', $ruc_empresa, $documento['id_producto']);
                    $clienteRecibo_cxc = $this->infoAsientoProgramado($con, 'clienteRecibo_cxc', '0', $ruc_empresa, $documento['id_cliente']);
                    $tarifa_iva_ventas_recibos_cxc = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_recibos_cxc', '0', $ruc_empresa, $documento['tipo_iva']);


                    //para las cuentas de ventas de la cuenta por cobrar
                    if ($cuentasMarcas == 1 && isset($ventasmarcasrecibosCxC)) {
                        $id_cuenta = isset($ventasmarcasrecibosCxC) ? $ventasmarcasrecibosCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($ventascategoriasrecibosCxC)) {
                        $id_cuenta = isset($ventascategoriasrecibosCxC) ? $ventascategoriasrecibosCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($productoventareciboCxC)) {
                        $id_cuenta = isset($productoventareciboCxC) ? $productoventareciboCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($clienteRecibo_cxc)) {
                        $id_cuenta = isset($clienteRecibo_cxc) ? $clienteRecibo_cxc['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas_recibos_cxc)) {
                        $id_cuenta = isset($tarifa_iva_ventas_recibos_cxc) ? $tarifa_iva_ventas_recibos_cxc['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXRC')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'recibos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta por cobrar en: contabilidad /configurar cuentas contables / ventas con recibos, cuentas para asiento general.');
                    }

                    //para mostrar las cuentas de iva que va en el haber
                    $total_iva = 0;
                    $sql_iva_recibo = $this->iva_recibo_venta($con, $documento['id_documento']);
                    while ($row_iva_recibo = mysqli_fetch_array($sql_iva_recibo)) {
                        $total_iva = $row_iva_recibo['total_iva'];
                        $array_datos_iva = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'IVA en ventas en recibo de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($total_iva, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                        $ventasmarcasrecibosIva = $this->infoAsientoProgramado($con, 'ventasmarcasrecibosIva', '0', $ruc_empresa, $documento['id_marca']);
                        $ventascategoriasrecibosIva = $this->infoAsientoProgramado($con, 'ventascategoriasrecibosIva', '0', $ruc_empresa, $documento['id_categoria']);
                        $productoventareciboIva = $this->infoAsientoProgramado($con, 'productoventareciboIva', '0', $ruc_empresa, $documento['id_producto']);
                        $clienteRecibo_iva = $this->infoAsientoProgramado($con, 'clienteRecibo_iva', '0', $ruc_empresa, $documento['id_cliente']);
                        $tarifa_iva_ventas_recibos_iva = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_recibos_iva', '0', $ruc_empresa, $documento['tipo_iva']);


                        if ($cuentasMarcas == 1 && isset($ventasmarcasrecibosIva)) {
                            $id_cuenta = isset($ventasmarcasrecibosIva) ? $ventasmarcasrecibosIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                        } else if ($cuentasCategorias == 1 && isset($ventascategoriasrecibosIva)) {
                            $id_cuenta = isset($ventascategoriasrecibosIva) ? $ventascategoriasrecibosIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                        } else if ($cuentasProductos == 1 && isset($productoventareciboIva)) {
                            $id_cuenta = isset($productoventareciboIva) ? $productoventareciboIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                        } else if ($cuentasClientes == 1 && isset($clienteRecibo_iva)) {
                            $id_cuenta = isset($clienteRecibo_iva) ? $clienteRecibo_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                        } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas_recibos_iva)) {
                            $id_cuenta = isset($tarifa_iva_ventas_recibos_iva) ? $tarifa_iva_ventas_recibos_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                        } else {
                            $id_asiento_tipo = $row_iva_recibo['codigo'];
                            $id_cuenta = $this->infoAsientoProgramado($con, 'iva_ventas_recibos', '0', $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta para el IVA en ventas en: contabilidad /configurar cuentas contables / ventas con recibos, cuentas para asiento general.');
                        }
                    }
                    //PARA LA CUENTA CONTABLE DE OTROS VALORES COMO PROPINA O TASA
                    $propina = $this->totales_recibo($con, $documento['id_documento'])['propina'];
                    $tasa_turistica = $this->totales_recibo($con, $documento['id_documento'])['tasa_turistica'];
                    if ($propina > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Propina en recibo de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($propina, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOVR')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'recibos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta para propina en ventas en: contabilidad /configurar cuentas contables / ventas con recibos');
                    }
                    if ($tasa_turistica > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Otros valores en recibo de venta N. ' . $documento['documento'] . ' ' . $documento['cliente'], 'valor_debe' => '0', 'valor_haber' => number_format($tasa_turistica, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOVR')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'recibos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta para otros valores como propina o tasas adicionales en ventas en: contabilidad /configurar cuentas contables / ventas con recibos');
                    }
                }

                //cuentas del haber
                foreach ($arrayDocumentosHaber as $row) {
                    //para los subtotales de la recibo de venta
                    $array_datos_subtotal = array(
                        'fecha_documento' => $row['fecha_documento'],
                        'detalle' => 'Recibo de venta N. ' . $row['documento'] . ' ' . $row['cliente'],
                        'valor_debe' => '0',
                        'valor_haber' => number_format($row['subtotal'], 2, '.', ''),
                        'id_registro' => $row['id_registro'],
                        'id_relacion' => $row['id_documento'],
                        'codigo_unico' => $row['codigo_unico'],
                        'id_cli_pro' => $row['id_cliente'],
                        'transaccion' => $row['transaccion']
                    );

                    $ventasmarcasrecibos = $this->infoAsientoProgramado($con, 'ventasmarcasrecibos', '0', $ruc_empresa, $documento['id_marca']);
                    $ventascategoriasrecibos = $this->infoAsientoProgramado($con, 'ventascategoriasrecibos', '0', $ruc_empresa, $documento['id_categoria']);
                    $productoventarecibo = $this->infoAsientoProgramado($con, 'productoventarecibo', '0', $ruc_empresa, $documento['id_producto']);
                    $clienteRecibo = $this->infoAsientoProgramado($con, 'clienteRecibo', '0', $ruc_empresa, $documento['id_cliente']);
                    $tarifa_iva_ventas_recibos = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_recibos', '0', $ruc_empresa, $documento['tipo_iva']);


                    if ($cuentasMarcas == 1 && isset($ventasmarcasrecibos)) {
                        $id_cuenta = isset($ventasmarcasrecibos) ? $ventasmarcasrecibos['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($ventascategoriasrecibos)) {
                        $id_cuenta = isset($ventascategoriasrecibos) ? $ventascategoriasrecibos['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($productoventarecibo)) {
                        $id_cuenta = isset($productoventarecibo) ? $productoventarecibo['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($clienteRecibo)) {
                        $id_cuenta = isset($clienteRecibo) ? $clienteRecibo['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas_recibos)) {
                        $id_cuenta = isset($tarifa_iva_ventas_recibos) ? $tarifa_iva_ventas_recibos['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con recibos / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCSVR')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'recibos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta para el subtotal en ventas en: contabilidad /configurar cuentas contables / ventas con recibos, cuentas para asiento general.');
                    }
                }
            }
        }
    }

    //para ver las notas de credito por ventas
    public function documentosNcVentas($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        //para cuando estan asignadas cuentas en las marcas
        $ventasmarcasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturasCxC', $ruc_empresa);
        $cuenta_marca = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturas', $ruc_empresa);
        $ventasmarcasfacturasIva = $this->contarCuentasAsignadas($con, 'ventasmarcasfacturasIva', $ruc_empresa);
        $cuentasMarcas = ($ventasmarcasfacturasCxC + $cuenta_marca + $ventasmarcasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las categorias
        $ventascategoriasfacturasCxC = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturasCxC', $ruc_empresa);
        $cuenta_categoria = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturas', $ruc_empresa);
        $ventascategoriasfacturasIva = $this->contarCuentasAsignadas($con, 'ventascategoriasfacturasIva', $ruc_empresa);
        $cuentasCategorias = ($ventascategoriasfacturasCxC + $cuenta_categoria + $ventascategoriasfacturasIva) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los productos
        $productoventafacturaIva = $this->contarCuentasAsignadas($con, 'productoventafacturaIva', $ruc_empresa);
        $productoventafacturaCxC = $this->contarCuentasAsignadas($con, 'productoventafacturaCxC', $ruc_empresa);
        $cuenta_producto = $this->contarCuentasAsignadas($con, 'productoventafactura', $ruc_empresa);
        $cuentasProductos = ($productoventafacturaIva + $productoventafacturaCxC +  $cuenta_producto) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en los clientes
        $cuenta_cliente = $this->contarCuentasAsignadas($con, 'cliente', $ruc_empresa);
        $ventas_cliente_iva = $this->contarCuentasAsignadas($con, 'ventas_cliente_iva', $ruc_empresa);
        $ventas_cliente_cxc = $this->contarCuentasAsignadas($con, 'ventas_cliente_cxc', $ruc_empresa);
        $cuentasClientes = ($cuenta_cliente + $ventas_cliente_iva + $ventas_cliente_cxc) > 0 ? 1 : 0;
        //para cuando estan asignadas cuentas en las tarifas de iva
        $tarifa_iva_ventas = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas', $ruc_empresa);
        $tarifa_iva_ventas_iva = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_iva', $ruc_empresa);
        $tarifa_iva_ventas_cxc = $this->contarCuentasAsignadas($con, 'tarifa_iva_ventas_cxc', $ruc_empresa);
        $cuentaTarifaIvaVentasFacturas = ($tarifa_iva_ventas + $tarifa_iva_ventas_iva + $tarifa_iva_ventas_cxc) > 0 ? 1 : 0;

        $sql = mysqli_query($con, "SELECT enc.id_encabezado_nc as id_documento, enc.id_cliente as id_cliente, 
        concat(enc.serie_nc, '-', LPAD(enc.secuencial_nc,9,'0')) as documento, round(cnc.subtotal_nc - cnc.descuento,2) as subtotal, 
        enc.fecha_nc as fecha_documento, 'NC_VENTAS' as transaccion, concat('NCV',enc.id_encabezado_nc) as id_registro, 
        cli.nombre as cliente, cnc.id_producto as id_producto, enc.factura_modificada as factura_modificada,
        (select id_marca from marca_producto where id_producto = cnc.id_producto) as id_marca, 
        (select id_grupo from grupo_producto_asignado where id_producto = cnc.id_producto) as id_categoria, cnc.tarifa_iva as tipo_iva 
		FROM cuerpo_nc as cnc INNER JOIN encabezado_nc as enc ON enc.serie_nc = cnc.serie_nc and enc.secuencial_nc = cnc.secuencial_nc 
		INNER JOIN clientes as cli ON cli.id=enc.id_cliente WHERE cnc.ruc_empresa = '" . $ruc_empresa . "' and enc.ruc_empresa = '" . $ruc_empresa . "' 
		and DATE_FORMAT(enc.fecha_nc, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
		and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_registro_contable='0' order by enc.fecha_nc asc");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $opciones_contabilizacion = $cuentasMarcas + $cuentasCategorias + $cuentasProductos + $cuentasClientes + $cuentaTarifaIvaVentasFacturas;
            if ($opciones_contabilizacion > 1) {
                return "configurarCuentas";
            } else {
                $arrayDocumentos = array();
                $codigo_unico = codigo_aleatorio(20);

                //aqui recoge todos los datos de las notas de credito a contabilizar

                while ($documento = mysqli_fetch_array($sql)) {
                    $nombre_cliente = strtoupper($documento['cliente']);
                    $array_datos_subtotal = array(
                        'fecha_documento' => $documento['fecha_documento'],
                        'detalle' => 'Nota de crédito en venta N. ' . $documento['documento'] . ' ' . $nombre_cliente . ' Documento modificado N. ' . $documento['factura_modificada'],
                        'valor_debe' => number_format($documento['subtotal'], 2, '.', ''),
                        'valor_haber' => '0',
                        'id_registro' => $documento['id_registro'],
                        'codigo_unico' => $codigo_unico,
                        'id_relacion' => $documento['id_documento'],
                        'id_cli_pro' => $documento['id_cliente'],
                        'transaccion' => $documento['transaccion']
                    );

                    $ventasmarcasfacturas = $this->infoAsientoProgramado($con, 'ventasmarcasfacturas', '0', $ruc_empresa, $documento['id_marca']);
                    $ventascategoriasfacturas = $this->infoAsientoProgramado($con, 'ventascategoriasfacturas', '0', $ruc_empresa, $documento['id_categoria']);
                    $productoventafactura = $this->infoAsientoProgramado($con, 'productoventafactura', '0', $ruc_empresa, $documento['id_producto']);
                    $cliente = $this->infoAsientoProgramado($con, 'cliente', '0', $ruc_empresa, $documento['id_cliente']);
                    $tarifa_iva_ventas = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas', '0', $ruc_empresa, $documento['tipo_iva']);


                    //para las cuentas de debe
                    if ($cuentasMarcas == 1 && isset($ventasmarcasfacturas)) {
                        $id_cuenta = isset($ventasmarcasfacturas) ? $ventasmarcasfacturas['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($ventascategoriasfacturas)) {
                        $id_cuenta = isset($ventascategoriasfacturas) ? $ventascategoriasfacturas['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($productoventafactura)) {
                        $id_cuenta = isset($productoventafactura) ? $productoventafactura['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($cliente)) {
                        $id_cuenta = isset($cliente) ? $cliente['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas)) {
                        $id_cuenta = isset($tarifa_iva_ventas) ? $tarifa_iva_ventas['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCSV')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta para el subtotal en ventas en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                    }

                    //aqui recoge datos para generar las cuentas del haber y el iva
                    $datos_documentos = array('tipo_iva' => $documento['tipo_iva'], 'id_categoria' => $documento['id_categoria'], 'id_marca' => $documento['id_marca'], 'id_producto' => $documento['id_producto'], 'id_documento' => $documento['id_documento'], 'fecha_documento' => $documento['fecha_documento'], 'documento' => $documento['documento'], 'cliente' => $documento['cliente'], 'id_registro' => $documento['id_registro'], 'codigo_unico' => $codigo_unico, 'id_cliente' => $documento['id_cliente'], 'transaccion' => $documento['transaccion'], 'factura_modificada' => $documento['factura_modificada']);
                    if (!$this->documento_existente($arrayDocumentos, $documento['id_documento'])) {
                        $arrayDocumentos[] = $datos_documentos;
                    }
                }


                //para la cuenta por cobrar, cuenta del haber
                foreach ($arrayDocumentos as $documento) {
                    //para mostrar las cuentas de iva
                    $total_iva = 0;
                    $sql_iva_nc = $this->iva_nc($con, $documento['id_documento']);
                    while ($row_iva_nc = mysqli_fetch_array($sql_iva_nc)) {
                        $total_iva = $row_iva_nc['total_iva'];
                        $array_datos_iva = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'IVA en nota de crédito en ventas N. ' . $documento['documento'] . ' ' . $documento['cliente'] . ' Documento modificado N. ' . $documento['factura_modificada'], 'valor_debe' => number_format($total_iva, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $documento['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                        $ventasmarcasfacturasIva = $this->infoAsientoProgramado($con, 'ventasmarcasfacturasIva', '0', $ruc_empresa, $documento['id_marca']);
                        $ventascategoriasfacturasIva = $this->infoAsientoProgramado($con, 'ventascategoriasfacturasIva', '0', $ruc_empresa, $documento['id_categoria']);
                        $productoventafacturaIva = $this->infoAsientoProgramado($con, 'productoventafacturaIva', '0', $ruc_empresa, $documento['id_producto']);
                        $ventas_cliente_iva = $this->infoAsientoProgramado($con, 'ventas_cliente_iva', '0', $ruc_empresa, $documento['id_cliente']);
                        $tarifa_iva_ventas_iva = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_iva', '0', $ruc_empresa, $documento['tipo_iva']);

                        if ($cuentasMarcas == 1 && isset($ventasmarcasfacturasIva)) {
                            $id_cuenta = isset($ventasmarcasfacturasIva) ? $ventasmarcasfacturasIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                        } else if ($cuentasCategorias == 1 && isset($ventascategoriasfacturasIva)) {
                            $id_cuenta = isset($ventascategoriasfacturasIva) ? $ventascategoriasfacturasIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                        } else if ($cuentasProductos == 1 && isset($productoventafacturaIva)) {
                            $id_cuenta = isset($productoventafacturaIva) ? $productoventafacturaIva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                        } else if ($cuentasClientes == 1 && isset($ventas_cliente_iva)) {
                            $id_cuenta = isset($ventas_cliente_iva) ? $ventas_cliente_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                        } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas_iva)) {
                            $id_cuenta = isset($tarifa_iva_ventas_iva) ? $tarifa_iva_ventas_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                        } else {
                            $id_asiento_tipo = $row_iva_nc['codigo'];
                            $id_cuenta = $this->infoAsientoProgramado($con, 'iva_ventas', '0', $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta para el IVA en ventas en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                        }
                    }


                    $total_nc = $this->total_nc($con, $documento['id_documento']);
                    $array_datos_por_cobrar = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Nota de crédito en venta N. ' . $documento['documento'] . ' ' . $documento['cliente'] . ' Documento modificado N. ' . $documento['factura_modificada'], 'valor_debe' => '0', 'valor_haber' => number_format($total_nc, 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);

                    $ventasmarcasfacturasCxC = $this->infoAsientoProgramado($con, 'ventasmarcasfacturasCxC', '0', $ruc_empresa, $documento['id_marca']);
                    $ventascategoriasfacturasCxC = $this->infoAsientoProgramado($con, 'ventascategoriasfacturasCxC', '0', $ruc_empresa, $documento['id_categoria']);
                    $productoventafacturaCxC = $this->infoAsientoProgramado($con, 'productoventafacturaCxC', '0', $ruc_empresa, $documento['id_producto']);
                    $ventas_cliente_cxc = $this->infoAsientoProgramado($con, 'ventas_cliente_cxc', '0', $ruc_empresa, $documento['id_cliente']);
                    $tarifa_iva_ventas_cxc = $this->infoAsientoProgramado($con, 'tarifa_iva_ventas_cxc', '0', $ruc_empresa, $documento['tipo_iva']);


                    if ($cuentasMarcas == 1 && isset($ventasmarcasfacturasCxC)) {
                        $id_cuenta = isset($ventasmarcasfacturasCxC) ? $ventasmarcasfacturasCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por marcas de productos, en la marca ' . $this->mostrarInfoMarca($con, $documento['id_marca'])['nombre_marca']);
                    } else if ($cuentasCategorias == 1 && isset($ventascategoriasfacturasCxC)) {
                        $id_cuenta = isset($ventascategoriasfacturasCxC) ? $ventascategoriasfacturasCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por categorías de productos, en la categoría ' . $this->mostrarInfoCategoria($con, $documento['id_categoria'])['nombre_grupo']);
                    } else if ($cuentasProductos == 1 && isset($productoventafacturaCxC)) {
                        $id_cuenta = isset($productoventafacturaCxC) ? $productoventafacturaCxC['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por productos, en el producto ' . $this->mostrarInfoProducto($con, $documento['id_producto'])['nombre_producto']);
                    } else if ($cuentasClientes == 1 && isset($ventas_cliente_cxc)) {
                        $id_cuenta = isset($ventas_cliente_cxc) ? $ventas_cliente_cxc['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $documento['id_cliente'])['nombre']);
                    } else if ($cuentaTarifaIvaVentasFacturas == 1 && isset($tarifa_iva_ventas_cxc)) {
                        $id_cuenta = isset($tarifa_iva_ventas_cxc) ? $tarifa_iva_ventas_cxc['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por tarifas de IVA, en la tarifa ' . $this->mostrarInfoTarifasIva($con, $documento['tipo_iva'])['tarifa']);
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXCC')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta por cobrar en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
                    }
                }
            }
        }
    }

    //para ver los documentos de retenciones de ventas
    public function documentosRetencionesVentas($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        $sql = mysqli_query($con, "SELECT erv.id_encabezado_retencion as id_documento, 
        crv.codigo_impuesto as codigo_impuesto, 
        concat(erv.serie_retencion, '-', LPAD(erv.secuencial_retencion, 9, '0')) as numero_retencion, 
        crv.impuesto as impuesto, sum(crv.valor_retenido) as valor_retenido, erv.fecha_emision as fecha_documento, 
        'RETENCIONES_VENTAS' as transaccion, erv.id_cliente as id_cliente,
        concat('RETVEN', erv.id_encabezado_retencion) as id_registro, cli.nombre as cliente, 
        ret_sri.concepto_ret as concepto_ret, crv.numero_documento as documento_retenido
        FROM cuerpo_retencion_venta as crv 
        INNER JOIN encabezado_retencion_venta as erv 
        ON erv.codigo_unico = crv.codigo_unico 
        INNER JOIN clientes as cli 
        ON erv.id_cliente=cli.id
        LEFT JOIN retenciones_sri as ret_sri 
        ON ret_sri.codigo_ret=crv.codigo_impuesto
        WHERE crv.ruc_empresa = '" . $ruc_empresa . "' and erv.ruc_empresa = '" . $ruc_empresa . "' and 
        DATE_FORMAT(erv.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
        and '" . date("Y/m/d", strtotime($hasta)) . "' and erv.id_registro_contable='0' and 
        crv.valor_retenido > 0 group by erv.serie_retencion, erv.secuencial_retencion, 
        crv.impuesto, crv.porcentaje_retencion ");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {

            $arrayDocumentos = array();
            $codigo_unico = codigo_aleatorio(20);
            while ($row = mysqli_fetch_array($sql)) {
                $concepto_retencion = !empty($row['concepto_ret']) ? $row['concepto_ret'] : " Otras retenciones";
                $array_datos_retencion = array(
                    'fecha_documento' => $row['fecha_documento'],
                    'detalle' => 'Retención en ventas N. ' . $row['numero_retencion'] . " " . strtoupper($row['cliente']) . " Documento retenido N. " . $row['documento_retenido'] . " " . $concepto_retencion,
                    'valor_debe' => number_format($row['valor_retenido'], 2, '.', ''),
                    'valor_haber' => '0',
                    'id_registro' => $row['id_registro'],
                    'codigo_unico' => $codigo_unico,
                    'id_relacion' => $row['id_documento'],
                    'id_cli_pro' => $row['id_cliente'],
                    'transaccion' => $row['transaccion']
                );

                $id_cuenta = $this->infoAsientoProgramado($con, 'retenciones_ventas', '0', $ruc_empresa, $row['codigo_impuesto']);
                $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_datos_retencion, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / retenciones en ventas / concepto:' . $concepto_retencion);

                //array para pasar al foreach de la cuenta por cobrar de abajo
                //. " Documento retenido N. " . $row['documento_retenido']
                $datos_documentos = array(
                    'fecha_documento' => $row['fecha_documento'],
                    'detalle' => 'Retención en ventas N. ' . $row['numero_retencion'] . " " . strtoupper($row['cliente']),
                    'valor_haber' => number_format($row['valor_retenido'], 2, '.', ''),
                    'id_registro' => $row['id_registro'],
                    'codigo_unico' => $codigo_unico,
                    'id_documento' => $row['id_documento'],
                    'id_cliente' => $row['id_cliente'],
                    'transaccion' => $row['transaccion']
                );
                $arrayDocumentos[] = $datos_documentos;
                //array_push($arrayDocumentos, $datos_documentos);
            }

            foreach ($arrayDocumentos as $documento) {
                //para la cuenta por cobrar
                $array_datos_por_cobrar = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => $documento['detalle'], 'valor_debe' => '0', 'valor_haber' => number_format($documento['valor_haber'], 2, '.', ''), 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_cliente'], 'transaccion' => $documento['transaccion']);
                $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXCC')['id_asiento_tipo'];
                $id_cuenta = $this->infoAsientoProgramado($con, 'ventas', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta por cobrar en: contabilidad /configurar cuentas contables / ventas con facturas, cuentas para asiento general.');
            }
        }
    }

    //para ver los documentos de retenciones de compras
    public function documentosRetencionesCompras($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        $sql = mysqli_query($con, "SELECT crc.codigo_impuesto as codigo_impuesto, erc.id_encabezado_retencion as id_documento, 
        concat(erc.serie_retencion, '-', LPAD(erc.secuencial_retencion, 9, '0')) as numero_retencion, 
        sum(crc.valor_retenido) as valor_retenido, erc.fecha_emision as fecha_documento, 'RETENCIONES_COMPRAS' as transaccion,
        concat('RETCOM', erc.id_encabezado_retencion) as id_registro, pro.razon_social as proveedor, 
        erc.id_proveedor as id_proveedor, ret_sri.concepto_ret as concepto_ret, erc.numero_comprobante as documento_retenido
        FROM cuerpo_retencion as crc 
        INNER JOIN encabezado_retencion as erc 
        ON erc.serie_retencion = crc.serie_retencion and erc.secuencial_retencion = crc.secuencial_retencion
        INNER JOIN proveedores as pro 
        ON pro.id_proveedor=erc.id_proveedor 
        LEFT JOIN retenciones_sri as ret_sri 
        ON ret_sri.codigo_ret=crc.codigo_impuesto
        WHERE crc.ruc_empresa = '" . $ruc_empresa . "' and erc.ruc_empresa = '" . $ruc_empresa . "' 
            and DATE_FORMAT(erc.fecha_emision, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
            and '" . date("Y/m/d", strtotime($hasta)) . "' and erc.id_registro_contable='0' and erc.estado_sri !='ANULADA'
            and crc.valor_retenido > 0 group by erc.serie_retencion, erc.secuencial_retencion, crc.impuesto, crc.porcentaje_retencion");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $codigo_unico = codigo_aleatorio(20);
            while ($row = mysqli_fetch_array($sql)) {
                $array_datos_por_pagar = array(
                    'fecha_documento' => $row['fecha_documento'],
                    'detalle' => 'Retención en compras N. ' . $row['numero_retencion'] . " " . strtoupper($row['proveedor']) . " " . " Documento retenido N. " . $row['documento_retenido'],
                    'valor_debe' => number_format($row['valor_retenido'], 2, '.', ''),
                    'valor_haber' => '0',
                    'id_registro' => $row['id_registro'],
                    'codigo_unico' => $codigo_unico,
                    'id_relacion' => $row['id_documento'],
                    'id_cli_pro' => $row['id_proveedor'],
                    'transaccion' => $row['transaccion']
                );

                $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXPP')['id_asiento_tipo'];
                $id_cuenta = $this->infoAsientoProgramado($con, 'compras_servicios', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                $cuenta_porcobrar = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_pagar, $id_cuenta, 'Agregar cuenta por cobrar en: contabilidad /configurar cuentas contables / adquisiciones de compras y/o servicios, cuentas para asiento general.');

                $array_datos_retencion = array(
                    'fecha_documento' => $row['fecha_documento'],
                    'detalle' =>
                    'Retención en compras N. ' . $row['numero_retencion'] . " " . strtoupper($row['proveedor']) . " " . " Documento retenido N. " . $row['documento_retenido'] . " " . $row['concepto_ret'],
                    'valor_debe' => '0',
                    'valor_haber' => number_format($row['valor_retenido'], 2, '.', ''),
                    'id_registro' => $row['id_registro'],
                    'codigo_unico' => $codigo_unico,
                    'id_relacion' => $row['id_documento'],
                    'id_cli_pro' => $row['id_proveedor'],
                    'transaccion' => $row['transaccion']
                );
                $id_cuenta = $this->infoAsientoProgramado($con, 'retenciones_compras', '0', $ruc_empresa, $row['codigo_impuesto']);
                $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_datos_retencion, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / retenciones en compras / concepto:' . $row['concepto_ret']);
            }
        }
    }

    //para los ingresos
    public function documentosIngresos($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        $sql = mysqli_query($con, "SELECT ing.id_ing_egr as id_documento, ing.id_cli_pro as id_cliente, 
        ing.numero_ing_egr as numero_ingreso, ing.fecha_ing_egr as fecha_documento, 
        'INGRESOS' as transaccion, ing.codigo_documento as codigo_documento,
        concat('ING', ing.id_ing_egr) as id_registro, ing.nombre_ing_egr as cliente, ing.id_cli_pro as id_cliente 
        FROM ingresos_egresos as ing 
        WHERE ing.ruc_empresa = '" . $ruc_empresa . "' and 
        DATE_FORMAT(ing.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
        and '" . date("Y/m/d", strtotime($hasta)) . "' and
        ing.codigo_contable='0' and ing.tipo_ing_egr='INGRESO' and ing.estado='OK' and ing.valor_ing_egr>0");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $codigo_unico = codigo_aleatorio(20);
            while ($row = mysqli_fetch_array($sql)) {
                $nombre_cliente_proveedor = strtoupper($row['cliente']);
                //detalle de los cobros		
                $busca_pago_ingreso_egreso = $this->buscaPagoIngresoEgreso($con, $row['codigo_documento']);
                while ($row_pago_ingresos_egresos = mysqli_fetch_array($busca_pago_ingreso_egreso)) {
                    $codigo_forma_pago = $row_pago_ingresos_egresos['codigo_forma_pago'];
                    $id_cuenta_bancaria = $row_pago_ingresos_egresos['id_cuenta'];
                    $valor_forma_pago = $row_pago_ingresos_egresos['valor_forma_pago'];

                    //cuendo esta pagado con la cuenta bancaria
                    if ($id_cuenta_bancaria > 0) {
                        $row_cuenta = $this->buscaCuentaBancaria($con, $id_cuenta_bancaria);
                        $cuenta_bancaria = strtoupper($row_cuenta['cuenta_bancaria']);
                        $forma_pago = $row_pago_ingresos_egresos['detalle_pago'];
                        switch ($forma_pago) {
                            case "D":
                                $tipo = 'Depósito';
                                break;
                            case "T":
                                $tipo = 'Transferencia';
                                break;
                        }
                        $detalle_pago = $tipo . " " . $cuenta_bancaria;
                        $array_pago_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de ingreso N. ' . $row['numero_ingreso'] . " recibido de " . $nombre_cliente_proveedor . " cobrado con: " . $detalle_pago, 'valor_debe' => number_format($valor_forma_pago, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_cliente'], 'transaccion' => $row['transaccion']);

                        $id_cuenta = $this->infoAsientoProgramado($con, 'bancos', '0', $ruc_empresa, $id_cuenta_bancaria);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_pago_ingreso = $this->generar_asiento($con, $ruc_empresa, $array_pago_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / cobros y pagos / cuentas banco:' . $cuenta_bancaria);
                    }

                    //cuando esta pagado con otras formas de pagos
                    if ($codigo_forma_pago > 0) {
                        $row_opciones_pagos = $this->buscaOpcionCobroPago($con, $codigo_forma_pago);
                        $forma_pago = strtoupper($row_opciones_pagos['descripcion']);
                        $detalle_pago = $forma_pago;
                        $array_pago_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de ingreso N. ' . $row['numero_ingreso'] . " recibido de " . $nombre_cliente_proveedor . " cobrado con: " . $detalle_pago, 'valor_debe' => number_format($valor_forma_pago, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_cliente'], 'transaccion' => $row['transaccion']);

                        $id_cuenta = $this->infoAsientoProgramado($con, 'opcion_cobro', '0', $ruc_empresa, $codigo_forma_pago);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_pago_ingreso = $this->generar_asiento($con, $ruc_empresa, $array_pago_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / cobros y pagos / opciones de ingresos:' . $detalle_pago);
                    }
                }

                //detalle de los documentos del ingreso
                $busca_detalle_ingreso_egreso = $this->buscaDetalleIngresoEgreso($con, $row['codigo_documento']);
                while ($row_detalle_ingresos_egresos = mysqli_fetch_array($busca_detalle_ingreso_egreso)) {
                    $tipo_ing_egr = $row_detalle_ingresos_egresos['tipo_ing_egr'];
                    $detalle = $row_detalle_ingresos_egresos['detalle_ing_egr'];
                    $valor_ing_egr = $row_detalle_ingresos_egresos['valor_ing_egr'];
                    $codigo_documento_cv = preg_replace('/\D/', '', $row_detalle_ingresos_egresos['codigo_documento_cv']);

                    //para buscar el id del cliente de cada factura o recibo
                    $id_cli_pro = $row['id_cliente'];
                    switch ($tipo_ing_egr) {
                        case "CCXCC": //para cuando son facturas
                            $id_cli_pro = $this->mostrarInfoVentaFactura($con, $codigo_documento_cv)['id_cliente'];
                            $id_cliente_configurado = $this->infoAsientoProgramado($con, 'ventas_cliente_cxc', '0', $ruc_empresa, $id_cli_pro);
                            break;
                        case "CCXRC": //para cuando son recibos
                            $id_cli_pro = $this->mostrarInfoVentaRecibo($con, $codigo_documento_cv)['id_cliente'];
                            $clienteRecibo_cxc = $this->infoAsientoProgramado($con, 'clienteRecibo_cxc', '0', $ruc_empresa, $id_cli_pro);
                            break;
                    }

                    if (!is_numeric($tipo_ing_egr)) {
                        $tipo_asiento = $this->idAsientoTipo($con, $tipo_ing_egr)['tipo_asiento'];
                        $id_asiento_tipo = $this->idAsientoTipo($con, $tipo_ing_egr)['id_asiento_tipo'];
                        $detalle = "Concepto: " . $tipo_asiento . " Detalle: " . $detalle;
                        $array_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de ingreso N. ' . $row['numero_ingreso'] . " " . $detalle, 'valor_debe' => '0', 'valor_haber' => number_format($valor_ing_egr, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $id_cli_pro, 'transaccion' => $row['transaccion']);

                        if ($tipo_ing_egr == 'CCXCC' && isset($id_cliente_configurado)) {
                            $id_cuenta = isset($id_cliente_configurado) ? $id_cliente_configurado['id_cuenta'] : 0;
                            $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con factura por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $id_cli_pro)['nombre']);
                        } else if ($tipo_ing_egr == 'CCXRC' && isset($clienteRecibo_cxc)) {
                            $id_cuenta = isset($clienteRecibo_cxc) ? $clienteRecibo_cxc['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / contabilizacion de venta con recibo por cliente, en el cliente ' . $this->mostrarInfoCliente($con, $id_cli_pro)['nombre']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, $tipo_asiento, $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ventas con facturas / asiento general de ventas');
                        }
                    } else {
                        $row_tipo_pago = $this->buscaOpcionIngresoEgreso($con, $tipo_ing_egr, '1');
                        $transaccion = isset($row_tipo_pago['descripcion']) ? $row_tipo_pago['descripcion'] : "";
                        $id_asiento_tipo = isset($row_tipo_pago['id']) ? $row_tipo_pago['id'] : "";
                        $detalle = "Concepto: " . $transaccion . " Detalle: " . $detalle;
                        $array_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de ingreso N. ' . $row['numero_ingreso'] . " " . $detalle, 'valor_debe' => '0', 'valor_haber' => number_format($valor_ing_egr, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $id_cli_pro, 'transaccion' => $row['transaccion']);

                        $id_cuenta = $this->infoAsientoProgramado($con, 'opcion_ingreso', '0', $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ingresos y egresos / opciones de ingresos: ' . $transaccion);
                    }
                }
            }
        }
    }

    //para los egresos
    public function documentosEgresos($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        $sql = mysqli_query($con, "SELECT ing.id_cli_pro as id_documento, ing.numero_ing_egr as numero_egreso, 
        ing.fecha_ing_egr as fecha_documento, 'EGRESOS' as transaccion, concat('EGR', ing.id_ing_egr) as id_registro, 
        ing.nombre_ing_egr as proveedor, ing.id_cli_pro as id_proveedor, ing.codigo_documento as codigo_documento
        FROM ingresos_egresos as ing WHERE ing.ruc_empresa = '" . $ruc_empresa . "' and 
        DATE_FORMAT(ing.fecha_ing_egr, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' and '" . date("Y/m/d", strtotime($hasta)) . "' and
        ing.codigo_contable='0' and ing.tipo_ing_egr='EGRESO' and ing.estado='OK' and ing.valor_ing_egr>0 ");
        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $codigo_unico = codigo_aleatorio(20);
            while ($row = mysqli_fetch_array($sql)) {
                $nombre_cliente_proveedor = strtoupper($row['proveedor']);
                //detalle del egreso
                $busca_detalle_ingreso_egreso = $this->buscaDetalleIngresoEgreso($con, $row['codigo_documento']);
                while ($row_detalle_ingresos_egresos = mysqli_fetch_array($busca_detalle_ingreso_egreso)) {
                    $tipo_ing_egr = $row_detalle_ingresos_egresos['tipo_ing_egr'];
                    $detalle = $row_detalle_ingresos_egresos['detalle_ing_egr'];
                    $valor_ing_egr = $row_detalle_ingresos_egresos['valor_ing_egr'];
                    $codigo_documento_cv = preg_replace('/\D/', '', $row_detalle_ingresos_egresos['codigo_documento_cv']);
                    //para buscar el id del proveedor de cada compra
                    $id_cli_pro = $row['id_proveedor'];
                    switch ($tipo_ing_egr) {
                        case "CCXPP": //para cuando son compras de proveedores obtengo el id_proveedor
                            //$id_cli_pro = $this->mostrarInfoCompra($con, $codigo_documento_cv)['id_proveedor'];
                            $proveedor_cxp = $this->infoAsientoProgramado($con, 'proveedor_cxp', '0', $ruc_empresa, $id_cli_pro);
                            break;
                        case "CCXRPP": //para cuando son sueldos por pagar del rol de pagos optengo el id_empleado de roles
                            $id_cli_pro = $this->mostrarInfoRoles($con, $codigo_documento_cv)['id_empleado'];
                            $nombre_empleado = $this->mostrarInfoEmpleado($con, $id_cli_pro)['nombres_apellidos'];
                            $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXRPP')['id_asiento_tipo'];
                            $sueldo_empleado_xp = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $id_cli_pro);
                            break;
                        case "CCXQPP": //para cuando son quincenas optengo el id del empleado de la quincena
                            $id_cli_pro = $this->mostrarInfoQuincena($con, $codigo_documento_cv)['id_empleado'];
                            $nombre_empleado = $this->mostrarInfoEmpleado($con, $id_cli_pro)['nombres_apellidos'];
                            $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXQPP')['id_asiento_tipo'];
                            $quincena_empleado_xp = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $id_cli_pro);
                            break;
                        case "CCXS14PP": //para cuando decimo cuarto optengo el id del empleado de la quincena
                            $id_cli_pro = $this->mostrarInfoDecimoCuarto($con, $codigo_documento_cv)['id_empleado'];
                            $nombre_empleado = $this->mostrarInfoEmpleado($con, $id_cli_pro)['nombres_apellidos'];
                            $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXS14PP')['id_asiento_tipo'];
                            $dcuarto_empleado_xp = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $id_cli_pro);
                            break;
                    }

                    if (!is_numeric($tipo_ing_egr)) {
                        $tipo_asiento = $this->idAsientoTipo($con, $tipo_ing_egr)['tipo_asiento'];
                        $detalle = " Concepto: " . $tipo_asiento . " Detalle: " . $detalle;
                        $array_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de egreso N. ' . $row['numero_egreso'] . " " . $detalle, 'valor_debe' => number_format($valor_ing_egr, 2, '.', ''), 'valor_haber' => number_format(0, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $id_cli_pro, 'transaccion' => $row['transaccion']);
                        //para asientos personalizados de proveedores por pagar

                        if ($tipo_ing_egr == 'CCXPP' && isset($proveedor_cxp)) {
                            $id_cuenta = isset($proveedor_cxp) ? $proveedor_cxp['id_cuenta'] : 0;
                            $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta por pagar, proveedor: ' . $this->mostrarInfoProveedor($con, $id_cli_pro)['razon_social']);
                        } else if ($tipo_ing_egr == 'CCXRPP' && isset($sueldo_empleado_xp)) {
                            $id_cuenta = isset($sueldo_empleado_xp) ? $sueldo_empleado_xp['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ cuenta de sueldo por pagar en asiento individual de: ' .  $nombre_empleado);
                        } else if ($tipo_ing_egr == 'CCXQPP' && isset($quincena_empleado_xp)) {
                            $id_cuenta = isset($quincena_empleado_xp) ? $quincena_empleado_xp['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ cuenta de quincena en asiento individual de: ' .  $nombre_empleado);
                        } else if ($tipo_ing_egr == 'CCXS14PP' && isset($dcuarto_empleado_xp)) {
                            $id_cuenta = isset($dcuarto_empleado_xp) ? $dcuarto_empleado_xp['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ cuenta de décimo cuarto por pagar en asiento individual de: ' .  $nombre_empleado);
                        } else {
                            $id_asiento_tipo = $this->idAsientoTipo($con, $tipo_ing_egr)['id_asiento_tipo'];
                            $id_cuenta = $this->infoAsientoProgramado($con, $tipo_asiento, $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / adquisiciones de compras y/o servicios / asiento en general ');
                        }
                    } else {
                        $row_tipo_pago = $this->buscaOpcionIngresoEgreso($con, $tipo_ing_egr, '2');
                        $transaccion = isset($row_tipo_pago['descripcion']) ? $row_tipo_pago['descripcion'] : "";
                        $id_asiento_tipo = isset($row_tipo_pago['id']) ? $row_tipo_pago['id'] : "";
                        $detalle = " Concepto: " . $transaccion . " Detalle: " . $detalle;
                        $array_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de egreso N. ' . $row['numero_egreso'] . " " . $detalle, 'valor_debe' => number_format($valor_ing_egr, 2, '.', ''), 'valor_haber' => number_format(0, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $id_cli_pro, 'transaccion' => $row['transaccion']);
                        $id_cuenta = $this->infoAsientoProgramado($con, 'opcion_egreso', '0', $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal = $this->generar_asiento($con, $ruc_empresa, $array_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / ingresos y egresos / opciones de egresos: ' . $transaccion);
                    }
                }

                //detalle de los pagos		
                $busca_pago_ingreso_egreso = $this->buscaPagoIngresoEgreso($con, $row['codigo_documento']);
                while ($row_pago_ingresos_egresos = mysqli_fetch_array($busca_pago_ingreso_egreso)) {
                    $codigo_forma_pago = $row_pago_ingresos_egresos['codigo_forma_pago'];
                    $id_cuenta_bancaria = $row_pago_ingresos_egresos['id_cuenta'];
                    $valor_forma_pago = $row_pago_ingresos_egresos['valor_forma_pago'];
                    $cheque = $row_pago_ingresos_egresos['cheque'];

                    //cuendo esta pagado con la cuenta bancaria
                    if ($id_cuenta_bancaria > 0) {
                        $row_cuenta = $this->buscaCuentaBancaria($con, $id_cuenta_bancaria);
                        $cuenta_bancaria = strtoupper(' cta: ' . $row_cuenta['cuenta_bancaria']);
                        $forma_pago = $row_pago_ingresos_egresos['detalle_pago'];
                        switch ($forma_pago) {
                            case "C":
                                $tipo = 'Cheque #' . $cheque;
                                break;
                            case "D":
                                $tipo = 'Débito';
                                break;
                            case "T":
                                $tipo = 'Transferencia';
                                break;
                        }
                        $detalle_pago = $tipo . " " . $cuenta_bancaria;
                        $array_pago_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de egreso N. ' . $row['numero_egreso'] . " pagado a " . $nombre_cliente_proveedor . " pagado con: " . $detalle_pago, 'valor_debe' => number_format(0, 2, '.', ''), 'valor_haber' => number_format($valor_forma_pago, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_proveedor'], 'transaccion' => $row['transaccion']);
                        $id_cuenta = $this->infoAsientoProgramado($con, 'bancos', '0', $ruc_empresa, $id_cuenta_bancaria);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_pago_egreso = $this->generar_asiento($con, $ruc_empresa, $array_pago_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / cobros y pagos / cuentas banco:' . $cuenta_bancaria);
                    }

                    //cuando esta pagado con otras formas de pagos
                    if ($codigo_forma_pago > 0) {
                        $row_opciones_pagos = $this->buscaOpcionCobroPago($con, $codigo_forma_pago);
                        $forma_pago = strtoupper($row_opciones_pagos['descripcion']);
                        $detalle_pago = $forma_pago;
                        $array_pago_ingreso_egreso = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Comprobante de egreso N. ' . $row['numero_egreso'] . " pagado a " . $nombre_cliente_proveedor . " pagado con: " . $detalle_pago, 'valor_debe' => number_format(0, 2, '.', ''), 'valor_haber' => number_format($valor_forma_pago, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_proveedor'], 'transaccion' => $row['transaccion']);
                        $id_cuenta = $this->infoAsientoProgramado($con, 'opcion_pago', '0', $ruc_empresa, $codigo_forma_pago);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_pago_egreso = $this->generar_asiento($con, $ruc_empresa, $array_pago_ingreso_egreso, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / cobros y pagos / opciones de egresos:' . $detalle_pago);
                    }
                }
            }
        }
    }

    //para ver las facturas de compras
    public function documentosAdquisiciones($con, $ruc_empresa, $desde, $hasta)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        //para cuando estan asignadas cuentas en los proveedores
        $cuenta_proveedor_gasto = $this->contarCuentasAsignadas($con, 'proveedor', $ruc_empresa);
        $cuenta_proveedor_iva = $this->contarCuentasAsignadas($con, 'proveedor_iva', $ruc_empresa);
        $cuenta_proveedor_xp = $this->contarCuentasAsignadas($con, 'proveedor_cxp', $ruc_empresa);
        $cuentas_proveedor = ($cuenta_proveedor_gasto + $cuenta_proveedor_iva + $cuenta_proveedor_xp) > 0 ? 1 : 0;

        //para cuando estan asignadas cuentas en las tarifas de iva
        $cuenta_proveedor_tarifa_iva_gasto = $this->contarCuentasAsignadas($con, 'tarifa_iva_compras', $ruc_empresa);
        $cuenta_proveedor_tarifa_iva_iva = $this->contarCuentasAsignadas($con, 'tarifa_iva_compras_iva', $ruc_empresa);
        $cuenta_proveedor_tarifa_iva_xp = $this->contarCuentasAsignadas($con, 'tarifa_iva_compras_cxp', $ruc_empresa);
        $cuentas_tarifa_iva = ($cuenta_proveedor_tarifa_iva_gasto + $cuenta_proveedor_tarifa_iva_iva + $cuenta_proveedor_tarifa_iva_xp) > 0 ? 1 : 0;

        if ($cuentas_tarifa_iva == 1) {
            $agrupado_por = "cuc.codigo_documento, cuc.det_impuesto";
        } else {
            $agrupado_por = "cuc.codigo_documento";
        }

        $sql = mysqli_query($con, "SELECT enc.id_encabezado_compra as id_documento, enc.id_proveedor as id_proveedor,
            enc.numero_documento as numero_documento, sum(cuc.subtotal) as subtotal, cuc.det_impuesto as tarifa_iva, 
            sum(cuc.descuento) as descuento, enc.propina as propina, enc.otros_val as otros_val, enc.total_compra as total_compra,
            enc.fecha_compra as fecha_documento, 'COMPRAS_SERVICIOS' as transaccion, concat('COM', enc.id_encabezado_compra) as id_registro,
            pro.razon_social as proveedor, com.comprobante as comprobante, enc.id_comprobante as tipo_documento 
            FROM cuerpo_compra as cuc 
            INNER JOIN encabezado_compra as enc 
            ON enc.codigo_documento = cuc.codigo_documento 
            INNER JOIN proveedores as pro 
            ON pro.id_proveedor=enc.id_proveedor
            INNER JOIN comprobantes_autorizados as com
            ON com.id_comprobante = enc.id_comprobante 
            WHERE cuc.ruc_empresa = '" . $ruc_empresa . "' and enc.ruc_empresa = '" . $ruc_empresa . "' 
           and DATE_FORMAT(enc.fecha_compra, '%Y/%m/%d') between '" . date("Y/m/d", strtotime($desde)) . "' 
           and '" . date("Y/m/d", strtotime($hasta)) . "' and enc.id_registro_contable='0' group by $agrupado_por ");

        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $opciones_contabilizacion = $cuentas_proveedor + $cuentas_tarifa_iva;
            if ($opciones_contabilizacion > 1) {
                return "configurarCuentas";
            } else {
                $arrayDocumentos = array();
                $codigo_unico = codigo_aleatorio(20);
                while ($row = mysqli_fetch_array($sql)) {
                    //para guardar los datos para generar las cuentas del haber
                    $datos_documentos = array(
                        'tipo_documento' => $row['tipo_documento'],
                        'comprobante' => $row['comprobante'],
                        'id_documento' => $row['id_documento'],
                        'fecha_documento' => $row['fecha_documento'],
                        'documento' => $row['numero_documento'],
                        'proveedor' => $row['proveedor'],
                        'id_registro' => $row['id_registro'],
                        'codigo_unico' => $codigo_unico,
                        'id_proveedor' => $row['id_proveedor'],
                        'transaccion' => $row['transaccion'],
                        'tarifa_iva' => $row['tarifa_iva']
                    );
                    if (!$this->documento_existente($arrayDocumentos, $row['id_documento'])) {
                        $arrayDocumentos[] = $datos_documentos;
                    }

                    if ($row['tipo_documento'] != 4) { //para facturas y demas documentos exepto nota de credito
                        $valor_debe_subtotal = number_format($row['subtotal'], 2, '.', '');
                        $valor_haber_subtotal = number_format(0, 2, '.', '');
                    }

                    if ($row['tipo_documento'] == 4) { //4 es nota de credito
                        $valor_debe_subtotal = number_format(0, 2, '.', '');
                        $valor_haber_subtotal = number_format($row['subtotal'], 2, '.', '');
                    }

                    $array_datos_subtotal = array(
                        'fecha_documento' => $row['fecha_documento'],
                        'detalle' => 'Compra/Servicio con ' . $row['comprobante'] . " N. " . $row['numero_documento'] . " Proveedor " . strtoupper($row['proveedor']),
                        'valor_debe' => $valor_debe_subtotal,
                        'valor_haber' => $valor_haber_subtotal,
                        'id_registro' => $row['id_registro'],
                        'codigo_unico' => $codigo_unico,
                        'id_relacion' => $row['id_documento'],
                        'id_cli_pro' => $row['id_proveedor'],
                        'transaccion' => $row['transaccion']
                    );

                    $proveedor = $this->infoAsientoProgramado($con, 'proveedor', '0', $ruc_empresa, $row['id_proveedor']);
                    $tarifa_iva_compras = $this->infoAsientoProgramado($con, 'tarifa_iva_compras', '0', $ruc_empresa, $row['tarifa_iva']);

                    //para el subtotal de compras si esta asignado una cuenta a un proveedor
                    if ($cuentas_proveedor == 1 && isset($proveedor)) {
                        $id_cuenta = isset($proveedor) ? $proveedor['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de costo o gasto, proveedor: ' . strtoupper($row['proveedor']));
                    } else if ($cuentas_tarifa_iva == 1 && isset($tarifa_iva_compras)) {
                        $id_cuenta = isset($tarifa_iva_compras) ? $tarifa_iva_compras['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de costo o gasto, proveedor: ' . strtoupper($row['proveedor']));
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCCGP')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'compras_servicios', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_subtotal, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de costo o gasto en asiento general');
                    }

                    //PARA LA CUENTA CONTABLE DE OTROS VALORES COMO PROPINA O TASA
                    $propina = $this->totales_compra($con, $row['id_documento'])['propina'];
                    $otros_val = $this->totales_compra($con, $row['id_documento'])['otros_val'];
                    if ($propina > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Propina y otros valores en ' . $row['comprobante'] . ' N.' . $row['numero_documento'] . ' Proveedor ' . $row['proveedor'], 'valor_debe' => number_format($propina, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_proveedor'], 'transaccion' => $row['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOP')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'compras_servicios', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ cuenta de costo o gasto en asiento general para otros valores');
                    }
                    if ($otros_val > 0) {
                        $array_subtotal_otros =  array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Propina y otros valores en ' . $row['comprobante'] . ' N ' . $row['numero_documento'] . ' Proveedor ' . $row['proveedor'], 'valor_debe' => number_format($otros_val, 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_proveedor'], 'transaccion' => $row['transaccion']);
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCOP')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'compras_servicios', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_subtotal_otros = $this->generar_asiento($con, $ruc_empresa, $array_subtotal_otros, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ cuenta de costo o gasto en asiento general para otros valores');
                    }
                }

                foreach ($arrayDocumentos as $documento) {
                    //para mostrar las cuentas de iva
                    $total_iva = 0;
                    $sql_iva_compra = $this->iva_factura_compra($con, $documento['id_documento']);
                    while ($row_iva_compra = mysqli_fetch_array($sql_iva_compra)) {
                        $total_iva = $row_iva_compra['total_iva'];

                        if ($documento['tipo_documento'] != 4) { //para facturas y demas documentos exepto nota de credito
                            $total_iva_debe = number_format($total_iva, 2, '.', '');
                            $total_iva_haber = number_format(0, 2, '.', '');
                        }
                        if ($documento['tipo_documento'] == 4) { //4 es nota de credito
                            $total_iva_debe = number_format(0, 2, '.', '');
                            $total_iva_haber = number_format($total_iva, 2, '.', '');
                        }

                        $proveedor_iva = $this->infoAsientoProgramado($con, 'proveedor_iva', '0', $ruc_empresa, $documento['id_proveedor']);
                        $tarifa_iva_compras_iva = $this->infoAsientoProgramado($con, 'tarifa_iva_compras_iva', '0', $ruc_empresa, $documento['tarifa_iva']);

                        $array_datos_iva = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'IVA en Compra/Servicio con ' . $documento['comprobante'] . " N. " . $documento['documento'] . " Proveedor " . strtoupper($documento['proveedor']), 'valor_debe' => $total_iva_debe, 'valor_haber' => $total_iva_haber, 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_proveedor'], 'transaccion' => $documento['transaccion']);
                        if ($cuentas_proveedor == 1 && isset($proveedor_iva)) {
                            $id_cuenta = isset($proveedor_iva) ? $proveedor_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de iva compras, proveedor: ' . strtoupper($documento['proveedor']));
                        } else if ($cuentas_tarifa_iva == 1 && isset($tarifa_iva_compras_iva)) {
                            $id_cuenta = isset($tarifa_iva_compras_iva) ? $tarifa_iva_compras_iva['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de iva compras, proveedor: ' . strtoupper($documento['proveedor']));
                        } else {
                            $id_asiento_tipo = $row_iva_compra['codigo'];
                            $id_cuenta = $this->infoAsientoProgramado($con, 'iva_compras', '0', $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_iva, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta de iva compras en asiento general');
                        }
                    }
                }

                //para los totales de cuentas por pagar
                foreach ($arrayDocumentos as $documento) {
                    $total_compra_debe = $this->total_debe_haber($con, $documento['id_registro'], $ruc_empresa, 'compras_servicios')['debe'];
                    $total_compra_haber = $this->total_debe_haber($con, $documento['id_registro'], $ruc_empresa, 'compras_servicios')['haber'];
                    $array_datos_por_cobrar = array('fecha_documento' => $documento['fecha_documento'], 'detalle' => 'Compra/Servicio con ' . $documento['comprobante'] . " N. " . $documento['documento'] . " Proveedor " . strtoupper($documento['proveedor']), 'valor_debe' => $total_compra_debe, 'valor_haber' => $total_compra_haber, 'id_registro' => $documento['id_registro'], 'codigo_unico' => $documento['codigo_unico'], 'id_relacion' => $documento['id_documento'], 'id_cli_pro' => $documento['id_proveedor'], 'transaccion' => $documento['transaccion']);

                    $proveedor_cxp = $this->infoAsientoProgramado($con, 'proveedor_cxp', '0', $ruc_empresa, $documento['id_proveedor']);
                    $tarifa_iva_compras_cxp = $this->infoAsientoProgramado($con, 'tarifa_iva_compras_cxp', '0', $ruc_empresa, $documento['tarifa_iva']);

                    if ($cuentas_proveedor == 1 && isset($proveedor_cxp)) {
                        $id_cuenta = isset($proveedor_cxp) ? $proveedor_cxp['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta por pagar, proveedor: ' . strtoupper($documento['proveedor']));
                    } else if ($cuentas_tarifa_iva == 1 && isset($tarifa_iva_compras_cxp)) {
                        $id_cuenta = isset($tarifa_iva_compras_cxp) ? $tarifa_iva_compras_cxp['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta por pagar, proveedor: ' . strtoupper($documento['proveedor']));
                    } else {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXPP')['id_asiento_tipo'];
                        $id_cuenta = $this->infoAsientoProgramado($con, 'compras_servicios', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                        $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                        $cuenta_iva = $this->generar_asiento($con, $ruc_empresa, $array_datos_por_cobrar, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / Adquisiciones de compras y/o servicios/ en base a proveedores/ cuenta por pagar en asiento general');
                    }
                }
            }
        }
    }

    //para ROLES de pagos
    public function documentosRolPagos($con, $id_empresa, $desde, $hasta, $ruc_empresa)
    {
        $limpiarTmp = $this->limpiarTmp($con, $ruc_empresa);
        //para las cuentas de empleados
        $cuentas_empleado = $this->contarCuentasAsignadas($con, 'empleado', $ruc_empresa);
        $cuenta_empleado = ($cuentas_empleado) > 0 ? 1 : 0;

        $sql = mysqli_query($con, "SELECT det.id as id_documento, rol.mes_ano as documento, 
            LAST_DAY(STR_TO_DATE(rol.mes_ano, '%m-%Y')) as fecha_documento, 'ROL_PAGOS' as transaccion, 
            concat('ROL_PAGOS', det.id) as id_registro, det.id_empleado as id_empleado,
            det.sueldo as sueldo, 
            det.a_recibir as a_recibir, 
            det.quincena as quincena,
            det.aporte_personal as aporte_personal,
            det.aporte_patronal as aporte_patronal,
            det.prov_tercero as prov_tercero,
            det.tercero as tercero,
            det.prov_cuarto as prov_cuarto,
            det.cuarto as cuarto,
            det.prov_fr as prov_fr,
            det.fondo_reserva as fondo_reserva,
            det.prov_vacacion as prov_vacacion,
            det.prov_desahucio as prov_desahucio, 
            emp.nombres_apellidos as empleado,
            det.id_empleado as id_empleado
            FROM detalle_rolespago as det 
            INNER JOIN rolespago as rol
            ON det.id_rol=rol.id
            INNER JOIN empleados as emp
            ON emp.id=det.id_empleado
            WHERE rol.id_empresa = '" . $id_empresa . "' 
      AND DATE_FORMAT(STR_TO_DATE(rol.mes_ano, '%m-%Y'), '%Y-%m') >= '" . date("Y-m", strtotime($desde)) . "' 
      AND DATE_FORMAT(STR_TO_DATE(rol.mes_ano, '%m-%Y'), '%Y-%m') <= '" . date("Y-m", strtotime($hasta)) . "' 
      AND det.id_registro_contable = '0' 
      AND rol.status = '1' ");
        if ($sql->num_rows == 0) {
            return "noData";
        } else {
            $opciones_contabilizacion = $cuenta_empleado;
            if ($opciones_contabilizacion > 1) {
                return "configurarCuentas";
            } else {
                $codigo_unico = codigo_aleatorio(20);
                while ($row = mysqli_fetch_array($sql)) {
                    //valores del debe
                    //sueldos gasto debe
                    if ($row['sueldo'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXCGS')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Sueldo ' . $row['empleado'], 'valor_debe' => number_format($row['sueldo'], 2, '.', ''), 'valor_haber' => '0', 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //aporte patronal debe
                    if ($row['aporte_patronal'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXAPA')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Aporte patronal ' . $row['empleado'], 'valor_debe' => number_format($row['aporte_patronal'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //decimo cuarto debe
                    if ($row['prov_cuarto'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXS14')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Décimo cuarto sueldo ' . $row['empleado'], 'valor_debe' => number_format($row['prov_cuarto'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //decimo tercero debe
                    if ($row['prov_tercero'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXS13')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Décimo tercer sueldo ' . $row['empleado'], 'valor_debe' => number_format($row['prov_tercero'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //fondos de reserva debe
                    if ($row['prov_fr'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXFR')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Fondos de reserva ' . $row['empleado'], 'valor_debe' => number_format($row['prov_fr'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //vacaciones debe
                    if ($row['prov_vacacion'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXVA')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Vacaciones ' . $row['empleado'], 'valor_debe' => number_format($row['prov_vacacion'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //desahucio debe
                    if ($row['prov_desahucio'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXCGD')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Desahucio ' . $row['empleado'], 'valor_debe' => number_format($row['prov_desahucio'], 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //horas extras debe
                    $valor_extras = $this->novedades($con, $row['documento'], array(4, 5, 6), $row['id_empleado'], $id_empresa); // horas, nocturnas, suple, extras
                    if ($valor_extras > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXHEX')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Horas nocturnas, suplementarias y extraordinarias ' . $row['empleado'], 'valor_debe' => number_format($valor_extras, 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //otros ingresos y ingresos fijos mensuales debe
                    $otros_ingresos = $this->novedades($con, $row['documento'], array(1), $row['id_empleado'], $id_empresa); // 1 es otros ingresos
                    if ($otros_ingresos > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXOI')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Otros ingresos varios y otros ingresos fijos ' . $row['empleado'], 'valor_debe' => number_format($otros_ingresos, 2, '.', ''), 'valor_haber' => 0, 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //valores del haber
                    //sueldo a pagar haber
                    if ($row['a_recibir'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXRPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Sueldos a pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['a_recibir'] - $row['tercero'] - $row['cuarto'] - $row['fondo_reserva'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //quincena haber
                    if ($row['quincena'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXQPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Quincena ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['quincena'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //aporte personal haber
                    if ($row['aporte_personal'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXAPE')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Aporte personal por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['aporte_personal'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //aporte patronal haber
                    if ($row['aporte_patronal'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXAPAPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Aporte patronal por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['aporte_patronal'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //quirografarios haber
                    $prestamos_quirografarios = $this->novedades($con, $row['documento'], array(7), $row['id_empleado'], $id_empresa); //7 prestamos quirografarios
                    if ($prestamos_quirografarios > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXPQUI')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Préstamos quirografarios ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($prestamos_quirografarios, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //hipotecarios haber
                    $prestamos_hipotecarios = $this->novedades($con, $row['documento'], array(8), $row['id_empleado'], $id_empresa); //8 prestamos hipotecarios
                    if ($prestamos_hipotecarios > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXPHIP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Préstamos hipotecarios ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($prestamos_hipotecarios, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //descuentos y otros descuentos fijos mensuales haber
                    $descuentos = $this->novedades($con, $row['documento'], array(2), $row['id_empleado'], $id_empresa); //2 es descuentos
                    if ($descuentos > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXDERP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Descuentos varios y descuentos fijos ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($descuentos, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //anticipos haber
                    $anticipos = $this->novedades($con, $row['documento'], array(3), $row['id_empleado'], $id_empresa); //3 es anticipos
                    if ($anticipos > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXAS')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Anticípos ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($anticipos, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }
                    //prestamo de empresa haber
                    $prestamos_empresa = $this->novedades($con, $row['documento'], array(9), $row['id_empleado'], $id_empresa); //9 prestamos empresa
                    if ($prestamos_empresa > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXPEE')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Préstamos de empresa ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($prestamos_empresa, 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //tercero por pagar haber
                    if ($row['prov_tercero'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXS13PP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Décimo tercero por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['prov_tercero'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //cuarto por pagar haber
                    if ($row['prov_cuarto'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXS14PP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Décimo cuarto por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['prov_cuarto'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //fondos de reserva haber
                    if ($row['prov_fr'] > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXFRPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Fondos de reserva por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['prov_fr'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //vacaciones haber
                    if (($row['prov_vacacion']) > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXVAPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Vacaciones por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['prov_vacacion'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }

                    //desahucio haber
                    if (($row['prov_desahucio']) > 0) {
                        $id_asiento_tipo = $this->idAsientoTipo($con, 'CCXDPP')['id_asiento_tipo'];
                        $array_datos = array('fecha_documento' => $row['fecha_documento'], 'detalle' => 'Rol de pagos: ' . $row['documento'] . ' Desahucio por pagar ' . $row['empleado'], 'valor_debe' => '0', 'valor_haber' =>  number_format($row['prov_desahucio'], 2, '.', ''), 'id_registro' => $row['id_registro'], 'codigo_unico' => $codigo_unico, 'id_relacion' => $row['id_documento'], 'id_cli_pro' => $row['id_empleado'], 'transaccion' => $row['transaccion']);
                        $id_empleado = $this->infoAsientoProgramado($con, 'empleado', $id_asiento_tipo, $ruc_empresa, $row['id_empleado']);
                        if ($cuenta_empleado == 1 && isset($id_empleado)) {
                            $id_cuenta = isset($id_empleado) ? $id_empleado['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento individual para: ' . $row['empleado']);
                        } else {
                            $id_cuenta = $this->infoAsientoProgramado($con, 'rol_pagos', $id_asiento_tipo, $ruc_empresa, $id_asiento_tipo);
                            $id_cuenta = isset($id_cuenta) ? $id_cuenta['id_cuenta'] : 0;
                            $asiento = $this->generar_asiento($con, $ruc_empresa, $array_datos, $id_cuenta, 'Agregar cuenta en: contabilidad /configurar cuentas contables / roles de pago/ asiento general de rol de pagos');
                        }
                    }
                }
            }
        }
    }


    //ciclo para comprobar registros repetidos
    public function documento_existente($arrayDocumentos, $id_documento)
    {
        foreach ($arrayDocumentos as $documento) {
            if ($documento['id_documento'] == $id_documento) {
                return true;
            }
        }
        return false;
    }

    //para total de novedades
    public function novedades($con, $mes_ano, $array_novedades, $id_empleado, $id_empresa)
    {
        if (empty($id_empleado)) {
            $condicion_empleado = "";
        } else {
            $condicion_empleado = " and id_empleado ='" . $id_empleado . "'";
        }
        $total = 0;
        foreach ($array_novedades as $id_novedad) {
            $sql = mysqli_query($con, "SELECT round(sum(valor),2) as total FROM novedades WHERE id_novedad = '" . $id_novedad . "' 
        and mes_ano = '" . $mes_ano . "' and id_novedad = '" . $id_novedad . "' and status ='1'
        and id_empresa = '" . $id_empresa . "' $condicion_empleado group by id_novedad");
            $row_total = mysqli_fetch_array($sql);

            //las novedades 4 5 6 son de horas extras, solo me da el numero de horas
            if ($id_novedad == 4 || $id_novedad == 5 || $id_novedad == 6) {
                $sql_salarios = mysqli_query($con, "SELECT * FROM salarios WHERE ano= '" . substr($mes_ano, -4) . "' and status ='1'");
                $row_salarios = mysqli_fetch_array($sql_salarios);
                $incremento_hora_nocturna = 1 + $row_salarios['hora_nocturna'] / 100;
                $incremento_hora_suplementaria = 1 + $row_salarios['hora_suplementaria'] / 100;
                $incremento_hora_extraordinaria = 1 + $row_salarios['hora_extraordinaria'] / 100;


                $sql_sueldo = mysqli_query($con, "SELECT round(det.sueldo,2) as sueldo FROM detalle_rolespago as det 
                INNER JOIN rolespago as rol ON rol.id=det.id_rol 
                WHERE rol.id_empresa = '" . $id_empresa . "' and rol.mes_ano = '" . $mes_ano . "' 
                and det.id_empleado='" . $id_empleado . "' and rol.status ='1'");
                $row_sueldo = mysqli_fetch_array($sql_sueldo);

                $hora_normal = number_format($row_sueldo['sueldo'] / $row_salarios['hora_normal'], 2, '.', '');

                $calculo_hora_nocturna = number_format($hora_normal * $incremento_hora_nocturna, 2, '.', '');
                $calculo_hora_suplementaria = number_format($hora_normal * $incremento_hora_suplementaria, 2, '.', '');
                $calculo_hora_extraordinaria = number_format($hora_normal * $incremento_hora_extraordinaria, 2, '.', '');


                if ($id_novedad == 4) {
                    $total_hora_nocturna = number_format($calculo_hora_nocturna * $row_total['total'], 2, '.', '');
                    $total += isset($total_hora_nocturna) ? $total_hora_nocturna : 0;
                }
                if ($id_novedad == 5) {
                    $total_hora_suplementaria = number_format($calculo_hora_suplementaria * $row_total['total'], 2, '.', '');
                    $total += isset($total_hora_suplementaria) ? $total_hora_suplementaria : 0;
                }

                if ($id_novedad == 6) {
                    $total_hora_extraordinaria = number_format($calculo_hora_extraordinaria * $row_total['total'], 2, '.', '');
                    $total += isset($total_hora_extraordinaria) ? $total_hora_extraordinaria : 0;
                }
            } else {
                $total += isset($row_total['total']) ? $row_total['total'] : 0;
            }
        }
        return $total;
    }


    //para total nc
    public function total_nc($con, $id_nc)
    {
        $sql_total_nc = mysqli_query($con, "SELECT round(total_nc,2) as total FROM encabezado_nc WHERE id_encabezado_nc = '" . $id_nc . "' ");
        $row_total = mysqli_fetch_array($sql_total_nc);
        $total_nc = $row_total['total'];
        return $total_nc;
    }

    //para el iva en nc
    public function iva_nc($con, $id_nc)
    {
        $sql = mysqli_query($con, "SELECT ti.codigo as codigo, 
        round(sum((cnc.subtotal_nc - cnc.descuento) * ti.porcentaje_iva / 100),2) as total_iva 
        FROM cuerpo_nc as cnc INNER JOIN encabezado_nc as enc ON enc.serie_nc=cnc.serie_nc and enc.secuencial_nc=cnc.secuencial_nc and
        enc.ruc_empresa=cnc.ruc_empresa
        INNER JOIN tarifa_iva as ti ON ti.codigo = cnc.tarifa_iva 
        WHERE enc.id_encabezado_nc='" . $id_nc . "' and ti.porcentaje_iva > 0 group by cnc.tarifa_iva ");
        return $sql;
    }

    //para total factura
    public function totales_factura($con, $id_factura)
    {
        $total_iva = 0;
        $sql_iva_factura = $this->iva_factura_venta($con, $id_factura);
        while ($row_iva_factura = mysqli_fetch_array($sql_iva_factura)) {
            $total_iva += $row_iva_factura['total_iva'];
        }

        //subtotales
        $sql_subtotal = mysqli_query($con, "SELECT round(sum(cf.subtotal_factura - cf.descuento),2) as subtotal 
            FROM cuerpo_factura as cf INNER JOIN encabezado_factura as enc ON enc.serie_factura=cf.serie_factura 
            and enc.secuencial_factura=cf.secuencial_factura and  enc.ruc_empresa=cf.ruc_empresa
            WHERE enc.id_encabezado_factura='" . $id_factura . "' group by enc.id_encabezado_factura ");
        $row_subtotal = mysqli_fetch_array($sql_subtotal);

        //propinas y otros
        $sql_total_factura = mysqli_query($con, "SELECT round(total_factura,2) as total, propina, tasa_turistica FROM encabezado_factura WHERE id_encabezado_factura = '" . $id_factura . "' ");
        $row_total = mysqli_fetch_array($sql_total_factura);
        $total = $row_subtotal['subtotal'] + $total_iva + $row_total['propina'] + $row_total['tasa_turistica'];
        $datos = array('total' => $total, 'propina' => isset($row_total['propina']) ? $row_total['propina'] : 0, 'tasa_turistica' => isset($row_total['tasa_turistica']) ? $row_total['tasa_turistica'] : 0);
        return $datos;
    }

    //para total compra
    public function totales_compra($con, $id_compra)
    {
        $sql_total_compra = mysqli_query($con, "SELECT round(total_compra,2) as total, propina, otros_val FROM encabezado_compra WHERE id_encabezado_compra = '" . $id_compra . "' ");
        $row_total = mysqli_fetch_array($sql_total_compra);
        $datos = array('total' => $row_total['total'], 'propina' => isset($row_total['propina']) ? $row_total['propina'] : 0, 'otros_val' => isset($row_total['otros_val']) ? $row_total['otros_val'] : 0);
        return $datos;
    }

    //para el iva en facturas de compras
    public function iva_factura_compra($con, $id_factura_compra)
    {
        $sql = mysqli_query($con, "SELECT ti.codigo as codigo, 
        round(sum((cf.cantidad * cf.precio - cf.descuento) * (ti.porcentaje_iva /100)),2) as total_iva 
            FROM cuerpo_compra as cf INNER JOIN encabezado_compra as enc ON enc.codigo_documento=cf.codigo_documento 
            INNER JOIN tarifa_iva as ti ON ti.codigo = cf.det_impuesto 
            WHERE enc.id_encabezado_compra='" . $id_factura_compra . "' and ti.porcentaje_iva > 0 group by cf.det_impuesto ");
        return $sql;
    }

    //para el iva en facturas
    public function iva_factura_venta($con, $id_factura_venta)
    {
        $sql = mysqli_query($con, "SELECT ti.codigo as codigo, 
        round((sum(cf.cantidad_factura * cf.valor_unitario_factura - cf.descuento) * (ti.porcentaje_iva /100)),2) as total_iva 
            FROM cuerpo_factura as cf 
            INNER JOIN encabezado_factura as enc ON enc.serie_factura=cf.serie_factura 
            and enc.secuencial_factura=cf.secuencial_factura and
            enc.ruc_empresa=cf.ruc_empresa
            INNER JOIN tarifa_iva as ti ON ti.codigo = cf.tarifa_iva 
            WHERE enc.id_encabezado_factura='" . $id_factura_venta . "' and ti.porcentaje_iva > 0 group by cf.tarifa_iva ");
        return $sql;
    }

    //para el iva en recibos
    public function iva_recibo_venta($con, $id_recibo_venta)
    {
        $sql = mysqli_query($con, "SELECT ti.codigo as codigo, 
        round(sum((cr.cantidad * cr.valor_unitario - cr.descuento) * (ti.porcentaje_iva /100)),2) as total_iva 
        FROM cuerpo_recibo as cr 
        INNER JOIN encabezado_recibo as enc ON enc.id_encabezado_recibo=cr.id_encabezado_recibo 
        INNER JOIN tarifa_iva as ti ON ti.codigo = cr.tarifa_iva 
        WHERE enc.id_encabezado_recibo='" . $id_recibo_venta . "' and ti.porcentaje_iva > 0 group by cr.tarifa_iva ");
        return $sql;
    }

    //para total recibos
    public function totales_recibo($con, $id_recibo)
    {
        //iva de recibos
        $total_iva = 0;
        $sql_iva_recibo = $this->iva_recibo_venta($con, $id_recibo);
        while ($row_iva_factura = mysqli_fetch_array($sql_iva_recibo)) {
            $total_iva += $row_iva_factura['total_iva'];
        }

        //subtotales
        $sql_subtotal = mysqli_query($con, "SELECT round(sum(cr.subtotal),2) as subtotal 
        FROM cuerpo_recibo as cr INNER JOIN encabezado_recibo as enc ON enc.id_encabezado_recibo=cr.id_encabezado_recibo 
        WHERE enc.id_encabezado_recibo='" . $id_recibo . "' group by enc.id_encabezado_recibo ");
        $row_subtotal = mysqli_fetch_array($sql_subtotal);


        $sql_total_recibo = mysqli_query($con, "SELECT round(total_recibo,2) as total, propina, tasa_turistica FROM encabezado_recibo WHERE id_encabezado_recibo = '" . $id_recibo . "' ");
        $row_total = mysqli_fetch_array($sql_total_recibo);
        $total = $row_subtotal['subtotal'] + $total_iva + $row_total['propina'] + $row_total['tasa_turistica'];
        $datos = array('total' => $total, 'propina' => isset($row_total['propina']) ? $row_total['propina'] : 0, 'tasa_turistica' => isset($row_total['tasa_turistica']) ? $row_total['tasa_turistica'] : 0);
        return $datos;
    }



    //para consultar clientes
    public function mostrarInfoCliente($con, $id_cliente)
    {
        $sql = mysqli_query($con, "SELECT * FROM clientes WHERE id = '" . $id_cliente . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar proveedores
    public function mostrarInfoProveedor($con, $id_proveedor)
    {
        $sql = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor = '" . $id_proveedor . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar productos
    public function mostrarInfoProducto($con, $id_producto)
    {
        $sql = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar empleados
    public function mostrarInfoEmpleado($con, $id_empleado)
    {
        $sql = mysqli_query($con, "SELECT * FROM empleados WHERE id = '" . $id_empleado . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar marca
    public function mostrarInfoMarca($con, $id_marca)
    {
        $sql = mysqli_query($con, "SELECT * FROM marca WHERE id_marca = '" . $id_marca . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }
    //para consultar categoria
    public function mostrarInfoCategoria($con, $id_categoria)
    {
        $sql = mysqli_query($con, "SELECT * FROM grupo_familiar_producto WHERE id_grupo = '" . $id_categoria . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para sacar el id del asiento tipo
    public function idAsientoTipo($con, $codigo)
    {
        $sql = mysqli_query($con, "SELECT * FROM asientos_tipo WHERE codigo = '" . $codigo . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar cliente desde facturas
    public function mostrarInfoVentaFactura($con, $id_encabezado_venta)
    {
        $sql = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE id_encabezado_factura = '" . $id_encabezado_venta . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar cliente desde recibos
    public function mostrarInfoVentaRecibo($con, $id_encabezado_venta)
    {
        $sql = mysqli_query($con, "SELECT * FROM encabezado_recibo WHERE id_encabezado_recibo = '" . $id_encabezado_venta . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar proveedor desde compras
    public function mostrarInfoCompra($con, $codigo_documento)
    {
        $sql = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE codigo_documento = '" . $codigo_documento . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar empleado desde sueldos
    public function mostrarInfoRoles($con, $id_detalle_rol)
    {
        $sql = mysqli_query($con, "SELECT * FROM detalle_rolespago WHERE id = '" . str_replace('ROL_PAGOS', '', $id_detalle_rol) . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para ver el id del empleado de quincenas
    public function mostrarInfoQuincena($con, $id_detalle_quincena)
    {
        $sql = mysqli_query($con, "SELECT * FROM detalle_quincena WHERE id = '" . str_replace('QUINCENA', '', $id_detalle_quincena) . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para ver el id del empleado de decimo cuarto
    public function mostrarInfoDecimoCuarto($con, $id_detalle_cuarto)
    {
        $sql = mysqli_query($con, "SELECT * FROM detalle_decimocuarto WHERE id = '" . str_replace('DECIMO_CUARTO', '', $id_detalle_cuarto) . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }


    //para generar el asiento contable tmp
    public function generar_asiento($con, $ruc_empresa, $arreglo_datos, $id_cuenta_programada, $cuenta_faltante)
    {
        //para obtener la cuenta contable de asientos programados 
        $sql_cuenta_asignada = mysqli_query($con, "SELECT * FROM plan_cuentas WHERE id_cuenta ='" . $id_cuenta_programada . "' ");
        $row_asiento_tipo = mysqli_fetch_array($sql_cuenta_asignada);
        $id_cuenta = isset($row_asiento_tipo['id_cuenta']) ? $row_asiento_tipo['id_cuenta'] : 0;
        $codigo_cuenta = isset($row_asiento_tipo['codigo_cuenta']) ? $row_asiento_tipo['codigo_cuenta'] : "";
        $nombre_cuenta = isset($row_asiento_tipo['nombre_cuenta']) ? $row_asiento_tipo['nombre_cuenta'] : "";

        if (empty($nombre_cuenta)) {
            $nombre_cuenta = $cuenta_faltante;
        }

        //compruebo si existe la cuenta y la sumo
        $buscar_asiento_existente = mysqli_query($con, "SELECT count(*) as numrows FROM asientos_automaticos_tmp 
            WHERE ruc_empresa = '" . $ruc_empresa . "' 
            and id_cuenta='" . $id_cuenta . "' 
            and id_registro = '" . $arreglo_datos['id_registro'] . "'
            and detalle = '" . $arreglo_datos['detalle'] . "' ");

        $numrows = mysqli_fetch_array($buscar_asiento_existente);
        if ($numrows['numrows'] > 0) {
            $respuesta = mysqli_query($con, "UPDATE asientos_automaticos_tmp 
         SET debe = round(debe + '" . $arreglo_datos['valor_debe'] . "',2), haber = round(haber + '" . $arreglo_datos['valor_haber'] . "',2)
         WHERE id_cuenta= '" . $id_cuenta . "' and id_registro= '" . $arreglo_datos['id_registro'] . "' ");
            $mensaje = "2";
        } else {
            $respuesta = mysqli_query($con, "INSERT INTO asientos_automaticos_tmp 
         VALUE (null, '" . $ruc_empresa . "', 
         '" . $arreglo_datos['fecha_documento'] . "', 
         '" . $id_cuenta . "', 
         '" . $codigo_cuenta . "', 
         '" . $nombre_cuenta . "', 
         '" . $arreglo_datos['detalle'] . "', 
         '" . $arreglo_datos['valor_debe'] . "', 
         '" . $arreglo_datos['valor_haber'] . "', 
         '" . $arreglo_datos['id_registro'] . "',
         '" . $arreglo_datos['transaccion'] . "',
         '" . $arreglo_datos['id_cli_pro'] . "', 
         '" . $arreglo_datos['codigo_unico'] . "', 
         '" . $arreglo_datos['id_relacion'] . "')");
            $mensaje = "1";
        }
        return $mensaje;
    }

    //para resgitros que tienen problemas con las cuentas contables
    public function contarRegistrosSinCuentaContable($con, $ruc_empresa, $tipo_asiento)
    {
        $sql_registros = mysqli_query($con, "SELECT count(*) as numrows FROM asientos_automaticos_tmp WHERE ruc_empresa ='" . $ruc_empresa . "' and tipo_asiento='" . $tipo_asiento . "' and codigo_cuenta=''");
        $row_registros = mysqli_fetch_array($sql_registros);
        return $row_registros['numrows'];
    }

    //buscar tipos de comprobantes autorizados
    public function buscarComprobantesAutorizados($con, $id_comprobante)
    {
        $sql = mysqli_query($con, "SELECT * FROM comprobantes_autorizados WHERE id_comprobante = '" . $id_comprobante . "' ");
        $row = mysqli_fetch_array($sql);
        $comprobante = isset($row['comprobante']) ? $row['comprobante'] : "";
        return $comprobante;
    }

    //buscar formas de pago en ingreso y egreso
    public function buscaPagoIngresoEgreso($con, $codigo_unico_documento)
    {
        $busca_pago_ingreso_egreso = mysqli_query($con, "SELECT * FROM formas_pagos_ing_egr WHERE codigo_documento = '" . $codigo_unico_documento . "' ");
        return $busca_pago_ingreso_egreso;
    }

    //buscar cuentas bancarias
    public function buscaCuentaBancaria($con, $id_cuenta)
    {
        $cuentas = mysqli_query($con, "SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.id_cuenta ='" . $id_cuenta . "'");
        $row_cuenta = mysqli_fetch_array($cuentas);
        return $row_cuenta;
    }

    //opciones de cobros y pagos en ingresos y egresos
    public function buscaOpcionCobroPago($con, $codigo_forma_pago)
    {
        $sql = mysqli_query($con, "SELECT * FROM opciones_cobros_pagos WHERE id ='" . $codigo_forma_pago . "'");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //buscar detalle de ingreso y egreso
    public function buscaDetalleIngresoEgreso($con, $codigo_unico_documento)
    {
        $sql = mysqli_query($con, "SELECT * FROM detalle_ingresos_egresos WHERE codigo_documento = '" . $codigo_unico_documento . "' ");
        return $sql;
    }

    //opciones de ingresos y egresos
    public function buscaOpcionIngresoEgreso($con, $tipo_ing_egr, $opcion)
    {
        $sql = mysqli_query($con, "SELECT * FROM opciones_ingresos_egresos WHERE id='" . $tipo_ing_egr . "' and tipo_opcion = '" . $opcion . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para consultar nombres de tarifas iva
    public function mostrarInfoTarifasIva($con, $codigo)
    {
        $sql = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $codigo . "' ");
        $row = mysqli_fetch_array($sql);
        return $row;
    }

    //para sumar los debe y haber de los asientos
    public function total_debe_haber($con, $id_registro, $ruc_empresa, $tipo_asiento)
    {
        $sumas = array();
        $sql = mysqli_query($con, "SELECT round(sum(debe),2) as debe, 
                                        round(sum(haber),2) as haber 
                                        FROM asientos_automaticos_tmp 
        WHERE ruc_empresa ='" . $ruc_empresa . "' and id_registro ='" . $id_registro . "' and tipo_asiento ='" . $tipo_asiento . "'
        group by id_registro");
        $row = mysqli_fetch_array($sql);

        if ($row['debe'] > 0) {
            $sumas = array('haber' => $row['debe'], 'debe' => '0');
        }
        if ($row['haber'] > 0) {
            $sumas = array('debe' => $row['haber'], 'haber' => '0');
        }
        return $sumas;
    }

    //para guardar los asientos y registrar en cada documento
    public function guardar_asientos_contables_generados($con, $ruc_empresa, $id_usuario, $tipo_asiento)
    {
        // 1) Verifica partida doble del tipo solicitado
        $sql_pd = "
        SELECT ROUND(SUM(debe - haber), 2) AS partida_doble
        FROM asientos_automaticos_tmp
        WHERE ruc_empresa = '" . mysqli_real_escape_string($con, $ruc_empresa) . "'
          AND tipo_asiento = '" . mysqli_real_escape_string($con, $tipo_asiento) . "'
          AND id_cuenta > 0
        GROUP BY tipo_asiento
    ";
        $rs_pd = mysqli_query($con, $sql_pd);
        if (!$rs_pd) {
            return "error:PDQuery:" . mysqli_error($con);
        }
        $row_pd = mysqli_fetch_assoc($rs_pd);
        $partida_doble = isset($row_pd['partida_doble']) ? $row_pd['partida_doble'] : 1;

        if ($partida_doble != 0) {
            // No está cuadrado, no guardes nada
            return "partidaDoble";
        }

        // 2) Transacción: o todo se guarda o nada
        ini_set('date.timezone', 'America/Guayaquil');
        $fecha_registro = date("Y-m-d H:i:s");

        // Desactiva autocommit y comienza "transacción manual"
        mysqli_autocommit($con, false);

        // IMPORTANTE: si quieres guardar SOLO el tipo de asiento que estás cuadrando, agrega el filtro tipo_asiento en los INSERT
        $ruc = mysqli_real_escape_string($con, $ruc_empresa);
        $usr = (int)$id_usuario;
        $tip = mysqli_real_escape_string($con, $tipo_asiento);
        $fec = mysqli_real_escape_string($con, $fecha_registro);

        // 3) INSERT encabezado (si quieres por tipo, descomenta la línea que filtra)
        $sql_enc = "
        INSERT INTO encabezado_diario (
            id_diario, ruc_empresa, codigo_unico, fecha_asiento, concepto_general,
            estado, id_usuario, fecha_registro, tipo, id_documento, codigo_unico_bloque
        )
        SELECT
            NULL,
            '{$ruc}',
            id_registro,
            fecha,
            detalle,
            'ok',
            '{$usr}',
            '{$fec}',
            tipo_asiento,
            id_registro,
            codigo_unico
        FROM asientos_automaticos_tmp
        WHERE ruc_empresa = '{$ruc}'
        -- AND tipo_asiento = '{$tip}'
        GROUP BY id_registro
    ";
        $ok_enc = mysqli_query($con, $sql_enc);
        if (!$ok_enc) {
            mysqli_rollback($con);
            mysqli_autocommit($con, true);
            return "error:INSERT_ENC:" . mysqli_error($con);
        }

        // 4) INSERT detalle (si quieres por tipo, descomenta el filtro)
        $sql_det = "
        INSERT INTO detalle_diario_contable (
            id_detalle_cuenta, ruc_empresa, codigo_unico, id_cuenta, debe, haber,
            detalle_item, codigo_unico_bloque, id_cli_pro
        )
        SELECT
            NULL,
            '{$ruc}',
            id_registro,
            id_cuenta,
            debe,
            haber,
            detalle,
            codigo_unico,
            id_cli_pro
        FROM asientos_automaticos_tmp
        WHERE ruc_empresa = '{$ruc}'
        -- AND tipo_asiento = '{$tip}'
    ";
        $ok_det = mysqli_query($con, $sql_det);
        if (!$ok_det) {
            mysqli_rollback($con);
            mysqli_autocommit($con, true);
            return "error:INSERT_DET:" . mysqli_error($con);
        }

        // 5) DELETE de la TMP (si quieres por tipo, descomenta el filtro)
        $sql_del = "
        DELETE FROM asientos_automaticos_tmp
        WHERE ruc_empresa = '{$ruc}'
        -- AND tipo_asiento = '{$tip}'
    ";
        $ok_del = mysqli_query($con, $sql_del);
        if (!$ok_del) {
            mysqli_rollback($con);
            mysqli_autocommit($con, true);
            return "error:DELETE_TMP:" . mysqli_error($con);
        }

        // 6) Si todo bien, confirma
        $ok_commit = mysqli_commit($con);
        mysqli_autocommit($con, true);

        if (!$ok_commit) {
            // Si por alguna razón fallara el commit
            mysqli_rollback($con);
            return "error:COMMIT";
        }

        return "ok";
    }
}
