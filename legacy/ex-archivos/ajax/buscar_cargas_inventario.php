<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	ini_set('date.timezone','America/Guayaquil');

	//PARA ELIMINAR LAS CARGAS
if (isset($_POST['codigo'])){
	$codigo=$_POST['codigo'];
	$query_carga= mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa ='".  $ruc_empresa ." ' and id_documento_venta = '".$codigo."' ");
	$registros_salida=array();
	while ($row_carga=mysqli_fetch_array($query_carga)){
		$codigo_carga=$row_carga['codigo_producto'];
		$lote_carga=$row_carga['lote'];
		$query_salida= mysqli_query($con,"SELECT * FROM inventarios WHERE ruc_empresa ='".  $ruc_empresa ." ' and codigo_producto = '".$codigo_carga."' and lote='".$lote_carga."' and operacion='SALIDA'");
		$registros_salida[]=mysqli_num_rows($query_salida);
	}
	
	$suma_registros=array_sum($registros_salida);
	if ($suma_registros>0){
	?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> No es posible eliminar, existen salidas de productos registradas.
			</div>
			<?php
	}else{
	
	$delete = mysqli_query($con, "DELETE FROM inventarios WHERE id_documento_venta = '".$codigo."' ");
	echo (mysqli_error($con));
		if ($delete){
			?>
			<div class="alert alert-success alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Bien hecho!</strong> Se eliminó correctamente la carga.
			</div>
			<?php
		}else {
			?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> Lo siento algo ha salido mal intenta nuevamente.
			</div>
			<?php
			
		}
	}
	}
	
	
//PARA BUSCAR LAS CARGAS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'cargas_inventario'){

		$query= mysqli_query($con,"SELECT DISTINCT(fecha_registro), id_documento_venta FROM inventarios WHERE ruc_empresa ='".  $ruc_empresa ." ' and tipo_registro='C' order by fecha_registro desc  LIMIT 10 ");
		$numrows = mysqli_num_rows($query);
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Fecha y hora de carga</th>
					<th class='text-right'>Opciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$codigo_unico=$row['id_documento_venta'];
						$fecha_registro=$row['fecha_registro'];
					?>
					<input type="hidden" value="<?php echo $codigo_unico;?>" id="codigo_unico<?php echo $codigo_unico;?>">
						<tr>
						<td><?php echo date('d-m-Y h:i:s A', strtotime($fecha_registro)); ?></td>
					<td><span class="pull-right">
					<a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_carga('<?php echo $codigo_unico; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
					</span></td>
					
					</tr>
				<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php
		}
	}
?>