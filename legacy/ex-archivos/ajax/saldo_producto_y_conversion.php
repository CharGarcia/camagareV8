<?php
//clase para buscar el saldo del producto y las conversiones		
class saldo_producto_y_conversion
{

	public $ruc_empresa;
	public $id_usuario;

	//existencia de productos en base a una bodega
	public function existencias_productos($id_bodega, $id_producto, $con)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if (!isset($_SESSION['ruc_empresa'])) {
			session_start();
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$id_usuario = $_SESSION['id_usuario'];
		}
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];

		$busca_medida = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
		$row_medida_producto = mysqli_fetch_array($busca_medida);
		$id_medida_producto = $row_medida_producto["id_unidad_medida"]; //la medida de entrada es la medida del producto en este caso

		//saldo del temporal factura
		$saldo_cantidad_tmp = array();
		$busca_saldo_tmp = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and id_bodega='" . $id_bodega . "'");
		while ($saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp)) {
			$id_medida_entrada = $saldo_producto_tmp['id_medida'];
			$cantidad_tmp = $saldo_producto_tmp['cantidad_tmp'];
			$saldo_cantidad_tmp[] = $this->conversion($id_medida_entrada, $id_medida_producto, $id_producto, '0', $cantidad_tmp, $con, 'saldo');
		}
		$saldo_tmp = array_sum($saldo_cantidad_tmp);
		$total_cantidad_entradas = array();
		$total_entradas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE id_bodega= '" . $id_bodega . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_entradas = mysqli_fetch_array($total_entradas_inventario)) {
			$id_medida_entrada_inv = $row_entradas['id_medida'];
			$cantidad_entrada = $row_entradas['cantidad_entrada'];
			$total_cantidad_entradas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_entrada, $con, 'saldo');
		}
		$total_entradas = array_sum($total_cantidad_entradas);

		//total salidas
		$total_cantidad_salidas = array();
		$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE id_bodega= '" . $id_bodega . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_salidas = mysqli_fetch_array($total_salidas_inventario)) {
			$id_medida_entrada_inv = $row_salidas['id_medida'];
			$cantidad_salida = $row_salidas['cantidad_salida'];
			$total_cantidad_salidas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_salida, $con, 'saldo');
		}
		$total_salidas = array_sum($total_cantidad_salidas);
		$saldo_final = number_format($total_entradas - $total_salidas - $saldo_tmp, 4, '.', '');
		return ($saldo_final);
	}

	//existencia producto por lote
	public function existencias_productos_lote($lote, $id_producto, $con)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if (!isset($_SESSION['ruc_empresa'])) {
			session_start();
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$id_usuario = $_SESSION['id_usuario'];
		}
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];

		$busca_medida = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
		$row_medida_producto = mysqli_fetch_array($busca_medida);
		$id_medida_producto = $row_medida_producto["id_unidad_medida"]; //la medida de entrada es la medida del producto en este caso

		//saldo del temporal factura
		$saldo_cantidad_tmp = array();
		$busca_saldo_tmp = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and lote='" . $lote . "'");
		while ($saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp)) {
			$id_medida_entrada = $saldo_producto_tmp['id_medida'];
			$cantidad_tmp = $saldo_producto_tmp['cantidad_tmp'];
			$saldo_cantidad_tmp[] = $this->conversion($id_medida_entrada, $id_medida_producto, $id_producto, '0', $cantidad_tmp, $con, 'saldo');
		}
		$saldo_tmp = array_sum($saldo_cantidad_tmp);
		$total_cantidad_entradas = array();
		$total_entradas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE lote= '" . $lote . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_entradas = mysqli_fetch_array($total_entradas_inventario)) {
			$id_medida_entrada_inv = $row_entradas['id_medida'];
			$cantidad_entrada = $row_entradas['cantidad_entrada'];
			$total_cantidad_entradas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_entrada, $con, 'saldo');
		}
		$total_entradas = array_sum($total_cantidad_entradas);

		//total salidas
		$total_cantidad_salidas = array();
		$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE lote= '" . $lote . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_salidas = mysqli_fetch_array($total_salidas_inventario)) {
			$id_medida_entrada_inv = $row_salidas['id_medida'];
			$cantidad_salida = $row_salidas['cantidad_salida'];
			$total_cantidad_salidas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_salida, $con, 'saldo');
		}
		$total_salidas = array_sum($total_cantidad_salidas);
		$saldo_final = number_format($total_entradas - $total_salidas - $saldo_tmp, 4, '.', '');
		return ($saldo_final);
	}

	//existencia producto por caducidad
	public function existencias_productos_caducidad($caducidad, $id_producto, $con)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if (!isset($_SESSION['ruc_empresa'])) {
			session_start();
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$id_usuario = $_SESSION['id_usuario'];
		}
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];

		$busca_medida = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
		$row_medida_producto = mysqli_fetch_array($busca_medida);
		$id_medida_producto = $row_medida_producto["id_unidad_medida"]; //la medida de entrada es la medida del producto en este caso

		//saldo del temporal factura
		$saldo_cantidad_tmp = array();
		$busca_saldo_tmp = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and vencimiento='" . $caducidad . "'");
		while ($saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp)) {
			$id_medida_entrada = $saldo_producto_tmp['id_medida'];
			$cantidad_tmp = $saldo_producto_tmp['cantidad_tmp'];
			$saldo_cantidad_tmp[] = $this->conversion($id_medida_entrada, $id_medida_producto, $id_producto, '0', $cantidad_tmp, $con, 'saldo');
		}
		$saldo_tmp = array_sum($saldo_cantidad_tmp);
		$total_cantidad_entradas = array();
		$total_entradas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE fecha_vencimiento= '" . $caducidad . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_entradas = mysqli_fetch_array($total_entradas_inventario)) {
			$id_medida_entrada_inv = $row_entradas['id_medida'];
			$cantidad_entrada = $row_entradas['cantidad_entrada'];
			$total_cantidad_entradas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_entrada, $con, 'saldo');
		}
		$total_entradas = array_sum($total_cantidad_entradas);

		//total salidas
		$total_cantidad_salidas = array();
		$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE fecha_vencimiento= '" . $caducidad . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_salidas = mysqli_fetch_array($total_salidas_inventario)) {
			$id_medida_entrada_inv = $row_salidas['id_medida'];
			$cantidad_salida = $row_salidas['cantidad_salida'];
			$total_cantidad_salidas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_salida, $con, 'saldo');
		}
		$total_salidas = array_sum($total_cantidad_salidas);
		$saldo_final = number_format($total_entradas - $total_salidas - $saldo_tmp, 4, '.', '');
		return ($saldo_final);
	}

	//saldo del producto para inventario de materias primas
	public function existencias_productos_mp($id_bodega, $id_producto, $con)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if (!isset($_SESSION['ruc_empresa'])) {
			session_start();
			$ruc_empresa = $_SESSION['ruc_empresa'];
			$id_usuario = $_SESSION['id_usuario'];
		}
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];

		$busca_medida = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
		$row_medida_producto = mysqli_fetch_array($busca_medida);
		$id_medida_producto = $row_medida_producto["id_unidad_medida"]; //la medida de entrada es la medida del producto en este caso

		//saldo del temporal factura
		$saldo_cantidad_tmp = array();
		$busca_saldo_tmp = mysqli_query($con, "SELECT * FROM factura_tmp WHERE id_usuario = '" . $id_usuario . "' and ruc_empresa = '" . $ruc_empresa . "' and id_producto = '" . $id_producto . "' and id_bodega='" . $id_bodega . "'");
		while ($saldo_producto_tmp = mysqli_fetch_array($busca_saldo_tmp)) {
			$id_medida_entrada = $saldo_producto_tmp['id_medida'];
			$cantidad_tmp = $saldo_producto_tmp['cantidad_tmp'];
			$saldo_cantidad_tmp[] = $this->conversion($id_medida_entrada, $id_medida_producto, $id_producto, '0', $cantidad_tmp, $con, 'saldo');
		}
		$saldo_tmp = array_sum($saldo_cantidad_tmp);
		$total_cantidad_entradas = array();
		$total_entradas_inventario = mysqli_query($con, "SELECT * FROM inventarios_mp WHERE id_bodega= '" . $id_bodega . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_entradas = mysqli_fetch_array($total_entradas_inventario)) {
			$id_medida_entrada_inv = $row_entradas['id_medida'];
			$cantidad_entrada = $row_entradas['cantidad_entrada'];
			$total_cantidad_entradas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_entrada, $con, 'saldo');
		}
		$total_entradas = array_sum($total_cantidad_entradas);

		//total salidas
		$total_cantidad_salidas = array();
		$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios_mp WHERE id_bodega= '" . $id_bodega . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "'");
		while ($row_salidas = mysqli_fetch_array($total_salidas_inventario)) {
			$id_medida_entrada_inv = $row_salidas['id_medida'];
			$cantidad_salida = $row_salidas['cantidad_salida'];
			$total_cantidad_salidas[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_salida, $con, 'saldo');
		}
		$total_salidas = array_sum($total_cantidad_salidas);
		$saldo_final = number_format($total_entradas - $total_salidas - $saldo_tmp, 4, '.', '');
		return ($saldo_final);
	}


	//funcion para ver el saldo de salidas de inventario y descontar cuando se esta editando una factura
	public function salidas_editar_factura($id_bodega, $id_producto, $con, $referencia_editar)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if (!isset($_SESSION['ruc_empresa'])) {
			session_start();
			$ruc_empresa = $_SESSION['ruc_empresa'];
		}
		$ruc_empresa = $_SESSION['ruc_empresa'];

		$busca_medida = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id='" . $id_producto . "'");
		$row_medida_producto = mysqli_fetch_array($busca_medida);
		$id_medida_producto = $row_medida_producto["id_unidad_medida"]; //la medida de entrada es la medida del producto en este caso

		//total salidas
		$total_cantidad_salida = array();
		$total_salidas_inventario = mysqli_query($con, "SELECT * FROM inventarios WHERE id_bodega= '" . $id_bodega . "' and id_producto = '" . $id_producto . "' and ruc_empresa='" . $ruc_empresa . "' and referencia='" . $referencia_editar . "' and operacion='SALIDA'");
		while ($row_salida = mysqli_fetch_array($total_salidas_inventario)) {
			$id_medida_entrada_inv = $row_salida['id_medida'];
			$cantidad_salida = $row_salida['cantidad_salida'];
			$total_cantidad_salida[] = $this->conversion($id_medida_entrada_inv, $id_medida_producto, $id_producto, '0', $cantidad_salida, $con, 'saldo');
		}
		$salidas = array_sum($total_cantidad_salida);

		$saldo_salidas = number_format($salidas, 4, '.', '');
		return ($saldo_salidas);
	}

	//para conseguir la conversion de las medidas	
	public function conversion($id_medida_entrada, $id_medida_salida, $id_producto, $precio_venta, $stock_actual, $con, $dato_obtener)
	{
		if ($con == null) {
			include_once("../conexiones/conectalogin.php");
			$con = conenta_login();
		}

		if ($id_medida_entrada == 0) {
			$busca_medida_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "'");
			$row_medida_producto = mysqli_fetch_array($busca_medida_producto);
			$id_medida_entrada = $row_medida_producto['id_unidad_medida'];
		} else {
			$id_medida_entrada = $id_medida_entrada;
		}
		//para todas las unidades cuando son iguales las dos condiciones
		if ($id_medida_entrada == $id_medida_salida) {
			$calculo_precio = number_format($precio_venta * 1, 4, '.', '');
			$calculo_stock = $stock_actual * 1;

			if ($dato_obtener == "saldo") {
				return $calculo_stock;
			}
			if ($dato_obtener == "precio") {
				return $calculo_precio;
			}
		} else {
			$busca_medida_conversion = mysqli_query($con, "SELECT * FROM conversion_medidas WHERE id_medida_entrada = '" . $id_medida_entrada . "' and id_medida_salida= '" . $id_medida_salida . "' ");
			$row_medida_conversion = mysqli_fetch_array($busca_medida_conversion);
			$factor_conversion = $row_medida_conversion['factor_conversion'];
			$operacion = $row_medida_conversion['operacion'];

			if (isset($factor_conversion) && isset($operacion)) {
				$factor_conversion = $factor_conversion;
				$operacion = $operacion;
			} else {
				$factor_conversion = 1;
				$operacion = "multiplicar";
			}

			if ($operacion == "dividir") {
				$calculo_precio = number_format($precio_venta * $factor_conversion, 4, '.', '');
				$calculo_stock = $stock_actual / $factor_conversion;
			}
			if ($operacion == "multiplicar") {
				$calculo_precio = number_format($precio_venta / $factor_conversion, 4, '.', '');
				$calculo_stock = $stock_actual * $factor_conversion;
			}

			if ($dato_obtener == "saldo") {
				return number_format($calculo_stock, 4, '.', '');
			}
			if ($dato_obtener == "precio") {
				return $calculo_precio;
			}
		}
	}
}
