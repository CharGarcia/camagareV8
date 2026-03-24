<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

if ($action == 'mesas_ordenes') {
	// escaping, additionally removing everything that could be (html/javascript-) code
	$q = ""; //mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
	$ordenado = mysqli_real_escape_string($con, (strip_tags($_GET['ordenado'], ENT_QUOTES)));
	$por = mysqli_real_escape_string($con, (strip_tags($_GET['por'], ENT_QUOTES)));
	$aColumns = array('nombre_mesa'); //Columnas de busqueda
	$sTable = "mesas";
	$sWhere = "WHERE ruc_empresa='" . $ruc_empresa . "'";
	if ($q != "") {
		$sWhere = "WHERE (ruc_empresa='" . $ruc_empresa . "' AND ";
		for ($i = 0; $i < count($aColumns); $i++) {
			$sWhere .= $aColumns[$i] . " LIKE '%" . $q . "%' AND ruc_empresa='" . $ruc_empresa . "' OR ";
		}
		$sWhere = substr_replace($sWhere, "AND ruc_empresa='" . $ruc_empresa . "' ", -3);
		$sWhere .= ')';
	}
	$sWhere .= " order by $ordenado $por";

	include("../ajax/pagination.php"); //include pagination file
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
	$reload = '../mesas.php';
	//main query to fetch the data
	$sql = "SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
	$query = mysqli_query($con, $sql);
	//loop through fetched data
	if ($numrows > 0) {
?>
		<div class="panel panel-info" style="background-color: #C9CCCD; margin-top: 2px;">
			<div class="table-responsive">
				<table class="table">
					<?php
					while ($row = mysqli_fetch_array($query)) {
						$id_mesa = $row['id_mesa'];
						$nombre_mesa = $row['nombre_mesa'];
						//buscar si la mesa tiene itens por facturar
						$busca_detalle_mesa = mysqli_query($con, "SELECT * FROM detalle_mesas WHERE id_mesa = '" . $id_mesa . "' and estado = 'PENDIENTE' and ruc_empresa='" . $ruc_empresa . "'");
						$por_facturar = mysqli_num_rows($busca_detalle_mesa);
						if ($por_facturar == 0) {
							$label_class = 'list-group-item list-group-item-success';
							$estado_mesa = " Libre";
						} else {
							$label_class = 'list-group-item list-group-item-danger';
							$estado_mesa = "";
						}
						$sutotal_a_pagar = array();
						$iva = array();
						while ($detalle_a_facturar = mysqli_fetch_array($busca_detalle_mesa)) {
							$id_detalle = $detalle_a_facturar['id_detalle_mesa'];
							$id_producto = $detalle_a_facturar['id_producto'];
							$cantidad = $detalle_a_facturar['cantidad'];
							$precio = $detalle_a_facturar['precio'];
							$descuento = $detalle_a_facturar['descuento'];

							//buscar productos
							$busca_nombre_producto = mysqli_query($con, "SELECT * FROM productos_servicios WHERE id = '" . $id_producto . "' ");
							$row_productos = mysqli_fetch_array($busca_nombre_producto);
							$nombre_producto = $row_productos['nombre_producto'];
							$tarifa_iva = $row_productos['tarifa_iva'];

							//buscar tipos iva
							$busca_tarifa_iva = mysqli_query($con, "SELECT * FROM tarifa_iva WHERE codigo = '" . $tarifa_iva . "' ");
							$row_tarifa = mysqli_fetch_array($busca_tarifa_iva);
							$nombre_tarifa = $row_tarifa['tarifa'];
							$porcentaje_iva = $row_tarifa['porcentaje_iva'];
							$sutotal_a_pagar[] = number_format((($cantidad * $precio) - $descuento), 2, '.', '');
							$iva[] = number_format((($cantidad * $precio) - $descuento) * ($porcentaje_iva / 100), 2, '.', '');
						}
						$total_a_pagar = array_sum($sutotal_a_pagar) + array_sum($iva);
						//trae valor de la propina
						$sql_propina = mysqli_query($con, "select * from propina_restaurante_tmp where id_mesa = '" . $id_mesa . "'");
						$row_propina = mysqli_fetch_array($sql_propina);
						$total_propina = isset($row_propina['propina']) ? $row_propina['propina'] : 0;

						//trae valor de las posiciones
						$sql_posiciones = mysqli_query($con, "select * from posiciones_mesas where id_mesa = '" . $id_mesa . "' and ruc_empresa ='" . $ruc_empresa . "'");
						$row_posiciones = mysqli_fetch_array($sql_posiciones);
						$eje_x = isset($row_posiciones['eje_x']) ? $row_posiciones['eje_x'] . "px" : 0;
						$eje_y = isset($row_posiciones['eje_y']) ? $row_posiciones['eje_y'] . "px" : 0;
						$eje_x_anterior = isset($row_posiciones['eje_x']) ? $row_posiciones['eje_x'] : 0;
						$eje_y_anterior = isset($row_posiciones['eje_y']) ? $row_posiciones['eje_y'] : 0;

						if ($total_a_pagar > 0) {
							$total_a_pagar = "Total: " . number_format($total_a_pagar + $total_propina, 2, '.', '');
						} else {
							$total_a_pagar = "";
						}
					?>
						<input type="hidden" value="<?php echo $nombre_mesa; ?>" id="nombre_mesa<?php echo $id_mesa; ?>">
						<input type="hidden" value="<?php echo $eje_x_anterior; ?>" id="id_x_anterior<?php echo $id_mesa; ?>">
						<input type="hidden" value="<?php echo $eje_y_anterior; ?>" id="id_y_anterior<?php echo $id_mesa; ?>">
						<div id="arrastrar" class="arrastrar" style="position: relative; left: <?php echo $eje_x; ?>; top: <?php echo $eje_y; ?>; width: 100px; word-wrap: break-word;" onmouseover="muestra_id_mesa('<?php echo $id_mesa; ?>')">
							<a id="posicion" style="width: 100px; word-wrap: break-word;" title='Detalles de la mesa' class="<?php echo $label_class; ?>" onclick="obtener_datos_mesas('<?php echo $id_mesa; ?>')" data-toggle="modal" data-target="#detalleOrdenesMesa">
								<span class="glyphicon glyphicon-cutlery"></span> <?php echo ucwords($nombre_mesa); ?> <br><?php echo $estado_mesa; ?> <b><?php echo $total_a_pagar; ?></b>
							</a>
						</div>
					<?php
					}
					?>
				</table>
			</div>
		</div>
	<?php
	}
}


if (isset($errors)) {

	?>
	<div class="alert alert-danger" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>Error!</strong>
		<?php
		foreach ($errors as $error) {
			echo $error;
		}
		?>
	</div>
<?php
}
if (isset($messages)) {

?>
	<div class="alert alert-success" role="alert">
		<button type="button" class="close" data-dismiss="alert">&times;</button>
		<strong>¡Bien hecho!</strong>
		<?php
		foreach ($messages as $message) {
			echo $message;
		}
		?>
	</div>
<?php
}
?>