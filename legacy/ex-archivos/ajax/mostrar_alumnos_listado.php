<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'listado'){
		if (empty($_POST['sucursal_alumno_listado'])) {
           $errors[] = "Seleccione campus.";
		}else if (empty($_POST['paralelo_alumno_listado'])) {
           $errors[] = "Seleccione nivel";
        } else if (!empty($_POST['sucursal_alumno_listado']) && !empty($_POST['paralelo_alumno_listado']))
		{
			$sucursal_listado=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno_listado"],ENT_QUOTES)));
			$paralelo_alumno_listado=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno_listado"],ENT_QUOTES)));
			if ($_POST['paralelo_alumno_listado']=="TODOS"){
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='$ruc_empresa' and al.sucursal_alumno = $sucursal_listado and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}else{
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='$ruc_empresa' and al.sucursal_alumno = $sucursal_listado and paralelo_alumno = $paralelo_alumno_listado and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}
?>
<div class="panel panel-info">
   <div class="table-responsive">
   <table class="table table-bordered">
  <tr class="info">
	<th>No</th>
	<th >ALUMNO</th>
	<th >HORARIO</th>
	<th >A QUIEN FACTURAR</th>
	<th colspan=10>VALORES</th>
	</tr>
	<?php
// PARA MOSTRAR LOS ESTUDIANTES 
	$sql_alumnos=mysqli_query($con, "SELECT ho.nombre_horario as horario, al.id_cliente as id_cliente, al.id_alumno as id_alumno, cl.nombre as clientes, al.nombres_alumno as nombres, al.apellidos_alumno as apellidos FROM horarios_alumnos ho, alumnos al, clientes cl WHERE $condiciones ");
	$numero=0;
	//$suma_valores=0;
	while ($row=mysqli_fetch_array($sql_alumnos)){
	$id_alumno=$row["id_alumno"];
	$nombres_alumnos= $row['apellidos'] ." ". $row['nombres'];
	$id_cliente=$row['id_cliente'];
	$clientes=$row['clientes'];
	$horario=$row['horario'];
	$numero = $numero + 1;

	//para mostrar los valores
		?>
		<tr>
			<td><?php echo ($numero);?></td>
			<td><?php echo strtoupper($nombres_alumnos);?></td>
			<td><?php echo strtoupper($horario);?></td>
			<td><?php echo strtoupper($clientes);?></td>
			<td class='col-xs-4'>
			<table>
			
			<?php
			$total_servicios=0;
			$sql_servicios=mysqli_query($con, "SELECT ps.nombre_producto as producto, dpf.precio_producto as precio, dpf.cant_producto as cantidad FROM detalle_por_facturar dpf, productos_servicios ps WHERE dpf.id_referencia= $id_alumno and dpf.ruc_empresa='$ruc_empresa' and dpf.id_producto=ps.id ");
			while ($row_servicios=mysqli_fetch_array($sql_servicios)){
	        $nombre_producto = $row_servicios['producto'];
			$precio_producto = $row_servicios['precio'] * $row_servicios['cantidad'];
			$total_servicios+=$precio_producto;
			?>
			<tr>			
			<td><?php echo strtoupper($nombre_producto);?></td>
			<td><?php echo " $ ".number_format($precio_producto,2,'.','');?></td>
			</tr>
			<?php
			}
			?>
			<td>TOTAL A COBRAR:</td>
			<td><?php echo " $ ".number_format(($total_servicios),2,'.','');?></td>
			</table>
			</td>
		</tr>		

		<?php
}
?>
</table>
</div>
</div>

<?php
		}else {
			$errors []= "Error desconocido.";
		}
}
//para mostrar el por cobrar

if($action == 'listado_pc'){
		if (empty($_POST['sucursal_alumno_pc'])) {
           $errors[] = "Seleccione campus.";
		}else if (empty($_POST['paralelo_alumno_pc'])) {
           $errors[] = "Seleccione nivel";
		}else if (empty($_POST['periodo_alumno_pc'])) {
           $errors[] = "Seleccione periodo";
        } else if (!empty($_POST['sucursal_alumno_pc']) && !empty($_POST['paralelo_alumno_pc']) && !empty($_POST['periodo_alumno_pc']))
		{
			$sucursal_listado=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno_pc"],ENT_QUOTES)));
			$paralelo_alumno_listado=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno_pc"],ENT_QUOTES)));
			$periodo_alumno_pc=mysqli_real_escape_string($con,(strip_tags($_POST["periodo_alumno_pc"],ENT_QUOTES)));
			
			
			if ($_POST['paralelo_alumno_pc']=="TODOS"){
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='".$ruc_empresa."' and al.sucursal_alumno = '".$sucursal_listado."' and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}else{
			$condiciones = "ho.id_horario = al.horario_alumno and al.ruc_empresa='".$ruc_empresa."' and al.sucursal_alumno = '".$sucursal_listado."' and paralelo_alumno = '".$paralelo_alumno_listado."' and al.estado_alumno='1' and al.id_cliente>0 and al.id_cliente= cl.id order by al.apellidos_alumno asc";
			}
?>
<div class="panel panel-info">
   <div class="table-responsive">
   <table class="table table-bordered">
  <tr class="info">
	<th>No</th>
	<th >ALUMNO</th>
	<th >HORARIO</th>
	<th >DATOS FACTURA</th>
	<th colspan=10>DETALLE DE VALORES</th>
	</tr>
	<?php
// PARA MOSTRAR LOS ESTUDIANTES 
	$sql_alumnos=mysqli_query($con, "SELECT ho.nombre_horario as horario, al.id_cliente as id_cliente, al.id_alumno as id_alumno, cl.nombre as clientes, al.nombres_alumno as nombres, al.apellidos_alumno as apellidos FROM horarios_alumnos ho, alumnos al, clientes cl WHERE $condiciones");
	$numero=0;
	//$suma_valores=0;
	while ($row=mysqli_fetch_array($sql_alumnos)){
	$id_alumno=$row["id_alumno"];
	$nombres_alumnos= $row['apellidos'] ." ". $row['nombres'];
	$id_cliente=$row['id_cliente'];
	$clientes=$row['clientes'];
	$horario=$row['horario'];
	$numero = $numero + 1;

	//para mostrar los valores
		?>
		<tr>
			<td><?php echo ($numero);?></td>
			<td><?php echo strtoupper($nombres_alumnos);?></td>
			<td><?php echo strtoupper($horario);?></td>
			<td><?php echo strtoupper($clientes);?></td>
			<td class='col-xs-4'>
			<table>
			
			<?php
			$total_servicios=0;
			$sql_servicios=mysqli_query($con, "SELECT ps.nombre_producto as producto, dpf.precio_producto as precio, dpf.cant_producto as cantidad FROM detalle_por_facturar dpf, productos_servicios ps WHERE dpf.id_referencia= '".$id_alumno."' and dpf.ruc_empresa='".$ruc_empresa."' and dpf.id_producto=ps.id and cuando_facturar='".$periodo_alumno_pc."' ");
			while ($row_servicios=mysqli_fetch_array($sql_servicios)){
	        $nombre_producto = $row_servicios['producto'];
			$precio_producto = $row_servicios['precio'] * $row_servicios['cantidad'];
			$total_servicios+=$precio_producto;
			?>
			<tr>			
			<td><?php echo strtoupper($nombre_producto);?></td>
			<td><?php echo " $ ".number_format($precio_producto,2,'.','');?></td>
			</tr>
			<?php
			}
			?>
			<td>TOTAL A COBRAR:</td>
			<td><?php echo " $ ".number_format(($total_servicios),2,'.','');?></td>
			</table>
			</td>
		</tr>		

		<?php
}
?>
</table>
</div>
</div>

<?php
		}else {
			$errors []= "Error desconocido.";
		}
}



if (isset($errors)){
			
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
			if (isset($messages)){
				
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