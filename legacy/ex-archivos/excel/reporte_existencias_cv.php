<?php
include("../ajax/buscar_existencias_consignacion.php");
$con = conenta_login();
//session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'existencia_consignacion_ventas_excel') {
	$tipo_existencia = mysqli_real_escape_string($con, (strip_tags($_POST['tipo_existencia'], ENT_QUOTES)));
	$id_nombre_buscar = mysqli_real_escape_string($con, (strip_tags($_POST['id_nombre_buscar'], ENT_QUOTES)));
	$nombre_buscar = mysqli_real_escape_string($con, (strip_tags($_POST['nombre_buscar'], ENT_QUOTES)));
	$asesor = mysqli_real_escape_string($con, (strip_tags($_POST['asesor'], ENT_QUOTES)));
	$ordenado = $_POST['ordenado'];
	$por = $_POST['por'];

	//para sacar el nombre de la empresa
	$sql_empresa = "SELECT * FROM empresas where ruc= '" . $ruc_empresa . "'";
	$resultado_empresa = mysqli_query($con, $sql_empresa);
	$empresa_info = mysqli_fetch_array($resultado_empresa);
	$tituloEmpresa = $empresa_info['nombre_comercial'];
	$tituloReporte = "Consignaciones en ventas";

	if (PHP_SAPI == 'cli')
		die('Este archivo solo se puede ver desde un navegador web');
	/** Se agrega la libreria PHPExcel */
	require_once 'lib/PHPExcel/PHPExcel.php';
	// Se crea el objeto PHPExcel
	$objPHPExcel = new PHPExcel();
	// Se asignan las propiedades del libro
	$objPHPExcel->getProperties()->setCreator("CaMaGaRe") //Autor
		->setLastModifiedBy("CaMaGaRe") //Ultimo usuario que lo modificó
		->setTitle("Reporte Excel")
		->setSubject("Reporte Excel")
		->setDescription("Consignaciones en ventas")
		->setKeywords("Consignaciones en ventas")
		->setCategory("Consignaciones en ventas");

	switch ($tipo_existencia) {
			//por cliente
		case "1":
			if (empty($id_nombre_buscar)) {
?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Seleccione un cliente.";
					?>
				</div>
			<?php
				exit;
			}

			$por_cliente = por_cliente($con, $ruc_empresa, $id_nombre_buscar, $asesor, 1, $ordenado, $por)['query'];
			if ($por_cliente->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
			<?php
				exit;
			}
			$row_detalle = mysqli_fetch_array($por_cliente);
			$cliente = $row_detalle['cliente'];
			$id_cliente = $row_detalle['id_cliente'];
			$tituloBusqueda = 'Cliente: ' . $nombre_buscar;

			$titulosColumnas = array('NCV', 'Asesor', 'Código', 'Producto', 'Lote', 'NUP', 'Cantidad', 'Facturado', 'No. Factura', 'Devuelto', 'Saldo', 'Fecha');
			$objPHPExcel->setActiveSheetIndex(0)
				->mergeCells('A1:I1')
				->mergeCells('A2:I2')
				->mergeCells('A3:I3');
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda)
				->setCellValue('A4',  $titulosColumnas[0])
				->setCellValue('B4',  $titulosColumnas[1])
				->setCellValue('C4',  $titulosColumnas[2])
				->setCellValue('D4',  $titulosColumnas[3])
				->setCellValue('E4',  $titulosColumnas[4])
				->setCellValue('F4',  $titulosColumnas[5])
				->setCellValue('G4',  $titulosColumnas[6])
				->setCellValue('H4',  $titulosColumnas[7])
				->setCellValue('I4',  $titulosColumnas[8])
				->setCellValue('J4',  $titulosColumnas[9])
				->setCellValue('K4',  $titulosColumnas[10])
				->setCellValue('L4',  $titulosColumnas[11]);

			$saldo_final = 0;
			$saldo_subtotal = 0;
			$cantidad_suma = 0;
			$total_facturado_suma = 0;
			$total_devuelto_suma = 0;
			$i = 5;

			$resultado = detalle_productos_por_cliente($con, $ruc_empresa, $id_cliente, $asesor);
			while ($row_detalle = mysqli_fetch_array($resultado)) {
				$id_producto = $row_detalle['id_producto'];
				$codigo_producto = $row_detalle['codigo_producto'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$fecha_consignacion = $row_detalle['fecha_consignacion'];
				$lote = $row_detalle['lote'];
				$nup = $row_detalle['nup'];
				$asesor = $row_detalle['asesor'];
				$numero_consignacion = $row_detalle['numero_consignacion'];
				$cantidad = $row_detalle['cant_consignacion'];
				$cantidad_suma += $row_detalle['cant_consignacion'];

				$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_facturado = $detalle_facturado['facturado'];
				$total_facturado_suma += $detalle_facturado['facturado'];
				$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

				$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_devuelto = $detalle_devolucion['devuelto'];
				$total_devuelto_suma += $detalle_devolucion['devuelto'];

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $numero_consignacion)
					->setCellValue('B' . $i,  strtoupper($asesor))
					->setCellValue('C' . $i,  "=\"" . $codigo_producto . "\"")
					->setCellValue('D' . $i,  "=\"" . $nombre_producto . "\"")
					->setCellValue('E' . $i,  "=\"" . $lote . "\"")
					->setCellValue('F' . $i,  "=\"" . $nup . "\"")
					->setCellValue('G' . $i,  number_format($cantidad, 2, '.', ''))
					->setCellValue('H' . $i,  number_format($total_facturado, 2, '.', ''))
					->setCellValue('I' . $i,  $todas_facturas)
					->setCellValue('J' . $i,  number_format($total_devuelto, 2, '.', ''))
					->setCellValue('K' . $i,  number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''))
					->setCellValue('L' . $i,  date("d-m-Y", strtotime($fecha_consignacion)), 2, '.', '');
				$i++;
			}
			$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
			break;
			//por numero de sonsignacion
		case "2":
			$consignacion_por_numero = consignacion_por_numero($con, $nombre_buscar, $ruc_empresa);
			if (empty($nombre_buscar)) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Ingrese un número de consignación para buscar.";
					?>
				</div>
			<?php
				exit;
			}

			if ($consignacion_por_numero->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
			<?php
				exit;
			}

			$row = mysqli_fetch_array($consignacion_por_numero);
			$fecha_consignacion = $row['fecha_consignacion'];
			$observaciones = strtoupper($row['observaciones']);
			$nombre_cliente = $row['cliente'];
			$tituloBusqueda = '#CV:' . $nombre_buscar . ' Fecha: ' . $fecha_consignacion . ' Cliente: ' . $nombre_cliente . ' Observaciones: ' . $observaciones;

			$titulosColumnas = array('Asesor', 'Código', 'Producto', 'Lote', 'NUP', 'Cantidad', 'Facturado', 'No. Factura', 'Devuelto', 'Saldo');
			$objPHPExcel->setActiveSheetIndex(0)
				->mergeCells('A1:I1')
				->mergeCells('A2:I2')
				->mergeCells('A3:I3');
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda)
				->setCellValue('A4',  $titulosColumnas[0])
				->setCellValue('B4',  $titulosColumnas[1])
				->setCellValue('C4',  $titulosColumnas[2])
				->setCellValue('D4',  $titulosColumnas[3])
				->setCellValue('E4',  $titulosColumnas[4])
				->setCellValue('F4',  $titulosColumnas[5])
				->setCellValue('G4',  $titulosColumnas[6])
				->setCellValue('H4',  $titulosColumnas[7])
				->setCellValue('I4',  $titulosColumnas[8])
				->setCellValue('J4',  $titulosColumnas[9]);

			$saldo_final = 0;
			$saldo_subtotal = 0;
			$cantidad_suma = 0;
			$total_facturado_suma = 0;
			$total_devuelto_suma = 0;
			$i = 5;
			$resultado = consignacion_por_numero($con, $nombre_buscar, $ruc_empresa);
			while ($row_detalle = mysqli_fetch_array($resultado)) {
				$id_producto = $row_detalle['id_producto'];
				$codigo_producto = $row_detalle['codigo_producto'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$lote = $row_detalle['lote'];
				$nup = $row_detalle['nup'];
				$asesor = $row_detalle['asesor'];
				$cantidad = $row_detalle['cant_consignacion'];
				$cantidad_suma += $row_detalle['cant_consignacion'];

				$detalle_facturado = detalle_facturado($con, $ruc_empresa, $nombre_buscar, $id_producto, $lote, $nup);
				$total_facturado = $detalle_facturado['facturado'];
				$total_facturado_suma += $detalle_facturado['facturado'];
				$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

				$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $nombre_buscar, $id_producto, $lote, $nup);
				$total_devuelto = $detalle_devolucion['devuelto'];
				$total_devuelto_suma += $detalle_devolucion['devuelto'];

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  strtoupper($asesor))
					->setCellValue('B' . $i,  "=\"" . $codigo_producto . "\"")
					->setCellValue('C' . $i,  "=\"" . $nombre_producto . "\"")
					->setCellValue('D' . $i,  "=\"" . $lote . "\"")
					->setCellValue('E' . $i,  "=\"" . $nup . "\"")
					->setCellValue('F' . $i,  number_format($cantidad, 2, '.', ''))
					->setCellValue('G' . $i,  number_format($total_facturado, 2, '.', ''))
					->setCellValue('H' . $i,  $todas_facturas)
					->setCellValue('I' . $i,  number_format($total_devuelto, 2, '.', ''))
					->setCellValue('J' . $i,  number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''));
				$i++;
			}
			$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
			break;

			//reporte por productos	
		case "3":
			$consignacion_por_producto = consignacion_por_producto($con, $id_nombre_buscar, $ruc_empresa, $asesor);
			if (empty($id_nombre_buscar)) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Ingrese un producto para buscar.";
					?>
				</div>
			<?php
				exit;
			}

			if ($consignacion_por_producto->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
			<?php
				exit;
			}
			$tituloBusqueda = 'Producto: ' . $nombre_buscar;
			$titulosColumnas = array('#CV', 'Asesor', 'Fecha', 'Cliente', 'Lote', 'NUP', 'Cantidad', 'Facturado', 'No. Factura', 'Devuelto', 'Saldo');
			$objPHPExcel->setActiveSheetIndex(0)
				->mergeCells('A1:J1')
				->mergeCells('A2:J2')
				->mergeCells('A3:J3');
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda)
				->setCellValue('A4',  $titulosColumnas[0])
				->setCellValue('B4',  $titulosColumnas[1])
				->setCellValue('C4',  $titulosColumnas[2])
				->setCellValue('D4',  $titulosColumnas[3])
				->setCellValue('E4',  $titulosColumnas[4])
				->setCellValue('F4',  $titulosColumnas[5])
				->setCellValue('G4',  $titulosColumnas[6])
				->setCellValue('H4',  $titulosColumnas[7])
				->setCellValue('I4',  $titulosColumnas[8])
				->setCellValue('J4',  $titulosColumnas[9])
				->setCellValue('K4',  $titulosColumnas[10]);

			$saldo_final = 0;
			$cantidad_suma = 0;
			$total_facturado_suma = 0;
			$total_devuelto_suma = 0;
			$i = 5;
			$detalle_consignacion = consignacion_por_producto($con, $id_nombre_buscar, $ruc_empresa, $asesor);
			while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
				$codigo_producto = $row_detalle['codigo_producto'];
				$id_producto = $row_detalle['id_producto'];
				$numero_consignacion = $row_detalle['numero_consignacion'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$lote = $row_detalle['lote'];
				$asesor = $row_detalle['asesor'];
				$nup = $row_detalle['nup'];
				$cliente = $row_detalle['cliente'];
				$cantidad = $row_detalle['cant_consignacion'];
				$cantidad_suma += $row_detalle['cant_consignacion'];
				$fecha_consignacion = $row_detalle['fecha_consignacion'];

				$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_facturado = $detalle_facturado['facturado'];
				$total_facturado_suma += $detalle_facturado['facturado'];
				$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

				$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_devuelto = $detalle_devolucion['devuelto'];
				$total_devuelto_suma += $detalle_devolucion['devuelto'];

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $numero_consignacion)
					->setCellValue('B' . $i,  strtoupper($asesor))
					->setCellValue('C' . $i,  date("d-m-Y", strtotime($fecha_consignacion)))
					->setCellValue('D' . $i,  "=\"" . strtoupper($cliente) . "\"")
					->setCellValue('E' . $i,  "=\"" . $lote . "\"")
					->setCellValue('F' . $i,  "=\"" . $nup . "\"")
					->setCellValue('G' . $i,  number_format($cantidad, 2, '.', ''))
					->setCellValue('H' . $i,  number_format($total_facturado, 2, '.', ''))
					->setCellValue('I' . $i,  $todas_facturas)
					->setCellValue('J' . $i,  number_format($total_devuelto, 2, '.', ''))
					->setCellValue('K' . $i,  number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''));
				$i++;
			}
			$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
			break;
			//por nup
		case "4":
			$consignacion_por_nup = consignacion_por_nup($con, $nombre_buscar, $ruc_empresa, $asesor);
			if (empty($nombre_buscar)) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Ingrese número de nup para buscar.";
					?>
				</div>
			<?php
				exit;
			}

			if ($consignacion_por_nup->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
			<?php
				exit;
			}
			$tituloBusqueda = 'NUP: ' . $nombre_buscar;
			$titulosColumnas = array('#CV', 'Asesor', 'Fecha', 'Cliente', 'Lote', 'Cantidad', 'Facturado', 'No. Factura', 'Devuelto', 'Saldo');
			$objPHPExcel->setActiveSheetIndex(0)
				->mergeCells('A1:I1')
				->mergeCells('A2:I2')
				->mergeCells('A3:I3');
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda)
				->setCellValue('A4',  $titulosColumnas[0])
				->setCellValue('B4',  $titulosColumnas[1])
				->setCellValue('C4',  $titulosColumnas[2])
				->setCellValue('D4',  $titulosColumnas[3])
				->setCellValue('E4',  $titulosColumnas[4])
				->setCellValue('F4',  $titulosColumnas[5])
				->setCellValue('G4',  $titulosColumnas[6])
				->setCellValue('H4',  $titulosColumnas[7])
				->setCellValue('I4',  $titulosColumnas[8])
				->setCellValue('J4',  $titulosColumnas[9]);

			$cantidad_suma = 0;
			$total_facturado_suma = 0;
			$total_devuelto_suma = 0;
			$i = 5;
			$detalle_consignacion = consignacion_por_nup($con, $nombre_buscar, $ruc_empresa, $asesor);
			while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
				$codigo_producto = $row_detalle['codigo_producto'];
				$id_producto = $row_detalle['id_producto'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$numero_consignacion = $row_detalle['numero_consignacion'];
				$lote = $row_detalle['lote'];
				$asesor = $row_detalle['asesor'];
				$cliente = $row_detalle['cliente'];
				$nup = $row_detalle['nup'];
				$cantidad = $row_detalle['cant_consignacion'];
				$cantidad_suma += $row_detalle['cant_consignacion'];
				$fecha_consignacion = $row_detalle['fecha_consignacion'];

				$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_facturado = $detalle_facturado['facturado'];
				$total_facturado_suma += $detalle_facturado['facturado'];
				$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

				$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_devuelto = $detalle_devolucion['devuelto'];
				$total_devuelto_suma += $detalle_devolucion['devuelto'];

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $numero_consignacion)
					->setCellValue('B' . $i,  strtoupper($asesor))
					->setCellValue('C' . $i,  date("d-m-Y", strtotime($fecha_consignacion)))
					->setCellValue('D' . $i,  "=\"" . strtoupper($cliente) . "\"")
					->setCellValue('E' . $i,  "=\"" . $lote . "\"")
					->setCellValue('F' . $i,  number_format($cantidad, 2, '.', ''))
					->setCellValue('G' . $i,  number_format($total_facturado, 2, '.', ''))
					->setCellValue('H' . $i,  $todas_facturas)
					->setCellValue('I' . $i,  number_format($total_devuelto, 2, '.', ''))
					->setCellValue('J' . $i,  number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''));
				$i++;
			}
			$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
			break;
			//por lote
		case "5":
			$consignacion_por_lote = consignacion_por_lote($con, $nombre_buscar, $ruc_empresa, $asesor);
			if (empty($nombre_buscar)) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Ingrese lote para buscar.";
					?>
				</div>
			<?php
				exit;
			}

			if ($consignacion_por_lote->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
			<?php
				exit;
			}
			$tituloBusqueda = 'Lote: ' . $nombre_buscar;
			$titulosColumnas = array('#CV', 'Asesor', 'Fecha', 'Cliente', 'NUP', 'Cantidad', 'Facturado', 'No. Factura', 'Devuelto', 'Saldo');
			$objPHPExcel->setActiveSheetIndex(0)
				->mergeCells('A1:I1')
				->mergeCells('A2:I2')
				->mergeCells('A3:I3');
			// Se agregan los titulos del reporte
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda)
				->setCellValue('A4',  $titulosColumnas[0])
				->setCellValue('B4',  $titulosColumnas[1])
				->setCellValue('C4',  $titulosColumnas[2])
				->setCellValue('D4',  $titulosColumnas[3])
				->setCellValue('E4',  $titulosColumnas[4])
				->setCellValue('F4',  $titulosColumnas[5])
				->setCellValue('G4',  $titulosColumnas[6])
				->setCellValue('H4',  $titulosColumnas[7])
				->setCellValue('I4',  $titulosColumnas[8])
				->setCellValue('J4',  $titulosColumnas[9]);

			$i = 5;
			$cantidad_suma = 0;
			$total_facturado_suma = 0;
			$total_devuelto_suma = 0;
			$detalle_consignacion = consignacion_por_lote($con, $nombre_buscar, $ruc_empresa, $asesor);
			while ($row_detalle = mysqli_fetch_array($detalle_consignacion)) {
				$codigo_producto = $row_detalle['codigo_producto'];
				$id_producto = $row_detalle['id_producto'];
				$nombre_producto = $row_detalle['nombre_producto'];
				$nup = $row_detalle['nup'];
				$lote = $row_detalle['lote'];
				$cliente = $row_detalle['cliente'];
				$asesor = $row_detalle['asesor'];
				$cantidad = $row_detalle['cant_consignacion'];
				$cantidad_suma += $row_detalle['cant_consignacion'];
				$fecha_consignacion = $row_detalle['fecha_consignacion'];
				$numero_consignacion = $row_detalle['numero_consignacion'];

				$detalle_facturado = detalle_facturado($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_facturado = $detalle_facturado['facturado'];
				$total_facturado_suma += $detalle_facturado['facturado'];
				$todas_facturas = isset($detalle_facturado['factura']) ? $detalle_facturado['factura'] : 0;

				$detalle_devolucion = detalle_devolucion($con, $ruc_empresa, $numero_consignacion, $id_producto, $lote, $nup);
				$total_devuelto = $detalle_devolucion['devuelto'];
				$total_devuelto_suma += $detalle_devolucion['devuelto'];

				$objPHPExcel->setActiveSheetIndex(0)
					->setCellValue('A' . $i,  $numero_consignacion)
					->setCellValue('B' . $i,  strtoupper($asesor))
					->setCellValue('C' . $i,  date("d-m-Y", strtotime($fecha_consignacion)))
					->setCellValue('D' . $i,  "=\"" . strtoupper($cliente) . "\"")
					->setCellValue('E' . $i,  "=\"" . $nup . "\"")
					->setCellValue('F' . $i,  number_format($cantidad, 2, '.', ''))
					->setCellValue('G' . $i,  number_format($total_facturado, 2, '.', ''))
					->setCellValue('H' . $i,  $todas_facturas)
					->setCellValue('I' . $i,  number_format($total_devuelto, 2, '.', ''))
					->setCellValue('J' . $i,  number_format($cantidad - $total_facturado - $total_devuelto, 0, '.', ''));
				$i++;
			}
			$saldo_final = $cantidad_suma - $total_facturado_suma - $total_devuelto_suma;
			break;
			//existencia por asesor
		case "6":
			if (empty($asesor)) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "Seleccione un asesor.";
					?>
				</div>
			<?php
				exit;
			}

			$clientes_consignacion_asesor = clientes_consignacion_asesor($con, $ruc_empresa, $asesor, $id_nombre_buscar);
			if ($clientes_consignacion_asesor->num_rows == 0) {
			?>
				<div class="alert alert-danger" role="alert">
					<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Mensaje! </strong>
					<?php
					echo "No hay datos para mostrar.";
					?>
				</div>
<?php
				exit;
			}
			$tituloBusqueda = 'Busqueda por asesor';
			$objPHPExcel->setActiveSheetIndex(0)
				->setCellValue('A1',  $tituloEmpresa)
				->setCellValue('A2',  $tituloReporte)
				->setCellValue('A3',  $tituloBusqueda);
			$i = 4;
			while ($row_detalle = mysqli_fetch_array($clientes_consignacion_asesor)) {
				$cliente = $row_detalle['cliente'];
				$id_cliente = $row_detalle['id_cliente'];
				$total_saldo_cliente = $row_detalle['saldo']; //total_saldo_cliente($con, $ruc_empresa, $id_cliente, $asesor);
				if ($total_saldo_cliente > 0) {
					$titulosColumnas = array('#CV', 'Cliente', 'Fecha', 'Asesor', 'Observaciones', 'Saldo consignado');
					$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('A' . $i,  $titulosColumnas[0])
						->setCellValue('B' . $i,  $titulosColumnas[1])
						->setCellValue('C' . $i,  $titulosColumnas[2])
						->setCellValue('D' . $i,  $titulosColumnas[3])
						->setCellValue('E' . $i,  $titulosColumnas[4])
						->setCellValue('F' . $i,  $titulosColumnas[5]);
					$resultado = por_cliente_excel($con, $ruc_empresa, $id_cliente, $asesor);
					$i = $i + 1;
					$suma_total = 0;
					while ($row_detalle = mysqli_fetch_array($resultado)) {
						$total_saldo_consignacion = $row_detalle['saldo']; //total_saldo_consignacion($con, $ruc_empresa, $numero_consignacion, $asesor);
						$suma_total += $total_saldo_consignacion;
						$numero_consignacion = $row_detalle['numero_consignacion'];
						$fecha_consignacion = $row_detalle['fecha_consignacion'];
						$nombre_asesor = $row_detalle['asesor'];
						$observaciones = $row_detalle['observaciones'];
						if ($total_saldo_consignacion > 0) {
							$objPHPExcel->setActiveSheetIndex(0)
								->setCellValue('A' . $i,  $numero_consignacion)
								->setCellValue('B' . $i,  strtoupper($cliente))
								->setCellValue('C' . $i,  date("d-m-Y", strtotime($fecha_consignacion)))
								->setCellValue('D' . $i,  strtoupper($nombre_asesor))
								->setCellValue('E' . $i,  "=\"" . strtoupper($observaciones) . "\"")
								->setCellValue('F' . $i,  $total_saldo_consignacion);
							$i++;
						}
					}
					$objPHPExcel->setActiveSheetIndex(0)
						->setCellValue('E' . $i,  'Total')
						->setCellValue('F' . $i,  $suma_total);
					$i = $i + 2;
					$saldo_final += $total_saldo_cliente;
				}
			}
			break;
	}

	$t = $i + 1;
	$objPHPExcel->setActiveSheetIndex(0)
		->mergeCells('A' . $t . ':C' . $t)
		->setCellValue('A' . $t,  'Productos en consignación')
		->setCellValue('D' . $t,  number_format($saldo_final, 0, '.', ''));

	/* for ($i = 'A'; $i <= 'K'; $i++) {
		$objPHPExcel->setActiveSheetIndex(0)
			->getColumnDimension($i)->setAutoSize(TRUE);
	} */

	// Se asigna el nombre a la hoja
	$objPHPExcel->getActiveSheet()->setTitle('CV');

	// inmovilizar paneles
	$objPHPExcel->setActiveSheetIndex(0);
	$objPHPExcel->getActiveSheet(0)->freezePaneByColumnAndRow(0, 5);

	// Se manda el archivo al navegador web, con el nombre que se indica (Excel2007)
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="ConsignacionVentas.xlsx"');
	header('Cache-Control: max-age=0');

	$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
	$objWriter->save('php://output');
	exit;
} //final de todo
