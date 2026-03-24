<?PHP
	include("../conexiones/conectalogin.php");
	session_start();
	$con = conenta_login();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
		
//para agregar un producto por facturarse nuevo
if (isset($_GET['id_producto'])){
   $id_registro = $_GET['id_reg_alumno'];
	$fecha_agregado=date("Y-m-d H:i:s");
	$id_alumno=mysqli_real_escape_string($con,(strip_tags($_GET["id_reg_alumno"],ENT_QUOTES)));
	$id_producto=mysqli_real_escape_string($con,(strip_tags($_GET["id_producto"],ENT_QUOTES)));
	$cantidad_producto=mysqli_real_escape_string($con,(strip_tags($_GET["cantidad_producto"],ENT_QUOTES)));
	$precio_producto=mysqli_real_escape_string($con,(strip_tags($_GET["precio_producto"],ENT_QUOTES)));
	$periodo=mysqli_real_escape_string($con,(strip_tags($_GET["periodo"],ENT_QUOTES)));
	$detalle_por_facturarse = mysqli_query($con, "INSERT INTO detalle_por_facturar VALUES (null, '$ruc_empresa', '$id_alumno', $id_producto, $cantidad_producto, $precio_producto, '$periodo', '$fecha_agregado', $id_usuario,'0')");
	muestra_detalle_por_facturar_alumno();
}
//para actualizar un descuento
if (isset($_GET['id_descuento'])) {
$id_registro=intval($_GET["id_descuento"]);
$valor_descuento=mysqli_real_escape_string($con,(strip_tags($_GET["valor_descuento"],ENT_QUOTES)));
$actualiza_descuento=mysqli_query($con, "UPDATE detalle_por_facturar SET descuento='".$valor_descuento."' WHERE id_detalle_pf='".$id_registro."'");
muestra_detalle_por_facturar_alumno();
}

//para eliminar un producto por facturarse
if (isset($_GET['id_reg_detalle'])){
	$id_registro = $_GET['id_reg_detalle'];
	$elimina_detalle_por_facturarse = mysqli_query($con, "DELETE FROM detalle_por_facturar WHERE id_detalle_pf='".$id_registro."'");
muestra_detalle_por_facturar_alumno();	
}

//para mostrar los datos de adicionales y formas de pago en la ventana modal a modificar datos
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'ajax'){
	muestra_detalle_por_facturar_alumno();
}

	
function muestra_detalle_por_facturar_alumno(){
	$con = conenta_login();
	if (isset($_GET['id_reg_alumno'])){
	$id_registro=$_GET['id_reg_alumno'];
	$busca_detalle_facturar = "SELECT dpf.id_detalle_pf as id_detalle_pf, ps.nombre_producto as producto, dpf.cant_producto as cant_producto, dpf.precio_producto as precio_producto, dpf.cuando_facturar as cuando_facturar, dpf.descuento as descuento FROM detalle_por_facturar dpf, productos_servicios ps WHERE dpf.id_referencia = '".$id_registro."' and dpf.id_producto = ps.id order by dpf.cuando_facturar asc ";
	$query = mysqli_query($con, $busca_detalle_facturar);
	
	//para sacar los subtotales
	$busca_subtotales_mensual = mysqli_query($con,"SELECT sum(cant_producto * precio_producto - descuento) as subtotal FROM detalle_por_facturar WHERE cuando_facturar='02' and id_referencia = '".$id_registro."'");
	$mensual = mysqli_fetch_array($busca_subtotales_mensual)['subtotal'];
	
	$busca_subtotales_anual = mysqli_query($con,"SELECT sum(cant_producto * precio_producto - descuento) as subtotal FROM detalle_por_facturar WHERE cuando_facturar='06' and id_referencia = '".$id_registro."'");
	$anual = mysqli_fetch_array($busca_subtotales_anual)['subtotal'];
	
	$busca_subtotales_unavez = mysqli_query($con,"SELECT sum(cant_producto * precio_producto - descuento) as subtotal FROM detalle_por_facturar WHERE cuando_facturar='03' and id_referencia = '".$id_registro."'");
	$una_vez = mysqli_fetch_array($busca_subtotales_unavez)['subtotal'];
	
	?>
				<div class="panel panel-info" style="margin-bottom: -15px; margin-top: -10px;">
				<div class="table-responsive">
					<table  class="table table-hover"> 
						<tr class="info">
								<th style ="padding: 2px;">Producto</th>
								<th style ="padding: 2px;">Cantidad</th>
								<th style ="padding: 2px;">Precio</th>
								<th style ="padding: 2px;">Descuento</th>
								<th style ="padding: 2px;">Cuando facturar</th>
								<th style ="padding: 2px;" class='text-right'>Eliminar</th>
						</tr>
						<?php
							while ($detalle_a_facturar = mysqli_fetch_array($query)){
								$id_detalle_pf=$detalle_a_facturar['id_detalle_pf'];
								$producto=$detalle_a_facturar['producto'];
								$cant_producto=$detalle_a_facturar['cant_producto'];
								$precio_producto=$detalle_a_facturar['precio_producto'];
								$cuando_facturar=$detalle_a_facturar['cuando_facturar'];
								$descuento=$detalle_a_facturar['descuento'];
								
								//buscar datos de cuando facturar
								$busca_cuando_facturar = "SELECT * FROM periodo_a_facturar WHERE codigo_periodo = '".$cuando_facturar."' ";
								$result = $con->query($busca_cuando_facturar);
								$cuando_se_facturar = mysqli_fetch_array($result);
								$a_facturar =$cuando_se_facturar['detalle_periodo'];
							?>
							<input type="hidden" value="<?php echo $id_detalle_pf;?>" id="id_a_facturar<?php echo $id_detalle_pf;?>">
							<input type="hidden" value="<?php echo $id_registro;?>" id="id_reg_alumno<?php echo $id_detalle_pf;?>">
							<input type="hidden" value="<?php echo $cant_producto*$precio_producto;?>" id="subtotal<?php echo $id_detalle_pf;?>">
						<tr>
								<td style ="padding: 2px;"><?php echo $producto; ?></td>
								<td style ="padding: 2px;"><?php echo $cant_producto; ?></td>
								<td style ="padding: 2px;"><?php echo $precio_producto; ?></td>
								<td style ="padding: 2px;" class="col-xs-1"><input class="form-control input-sm" type="text" value="<?php echo $descuento;?>" id="descuento<?php echo $id_detalle_pf;?>" onchange="aplica_descuento('<?php echo $id_detalle_pf;?>');" title="Para guardar el descuento ingrese el valor y presione enter"></td>
								<td style ="padding: 2px;" class='text-center'><?php echo $a_facturar; ?></td>
								<td style ="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_detalle_factura_alumno('<?php echo $id_detalle_pf; ?>')" ><i class="glyphicon glyphicon-remove"></i></a></td>
						</tr>
							<?php
							}
						?>
						<tr class="info">
								<td colspan="7">Subtotales => Mensual: <?php echo $mensual ?> // Anual: <?php echo $anual ?> // Una sola vez: <?php echo $una_vez ?></td>
						</tr>
					</table>
				</div>
				</div>
<?php
}
		
}
?>