<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//Archivo de funciones PHP
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
//para eliminar el cliente programado y los detalles de la factura
	if (isset($_GET['id_fp'])){
		$id_fp=$_GET['id_fp'];
		$id_cliente="CLIENTE".$_GET['id_fp'];
			if ($delete1=mysqli_query($con,"DELETE FROM clientes_facturas_programadas WHERE id_fp = $id_fp ") and $delete2=mysqli_query($con,"DELETE FROM detalle_por_facturar WHERE id_referencia = '$id_cliente' ") ){
			?>
			<div class="alert alert-success alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Aviso!</strong> Cliente y detalle de factura eliminados.
			</div>
			<?php 
		}else {
			?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> Lo siento algo ha salido mal intente nuevamente.
			</div>
			<?php
			
		}
	}	
	
	if($action == 'ajax'){
		$q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		$aColumns = array('nombre', 'direccion');//Columnas de busqueda
		$sTable = "clientes cl, clientes_facturas_programadas cfp";
		$sWhere = "WHERE cfp.ruc_empresa ='".  $ruc_empresa ."' and cfp.id_cliente = cl.id ";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (cfp.ruc_empresa ='".  $ruc_empresa ." ' and cfp.id_cliente = cl.id AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' and cfp.ruc_empresa ='".  $ruc_empresa ." ' and cfp.id_cliente = cl.id OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND cfp.ruc_empresa = '".  $ruc_empresa ." ' and cfp.id_cliente = cl.id " , -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by cfp.fecha_agregado desc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../facturas_programadas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Cliente</th>
					<th>Fecha Agregado</th>
					<th class='text-right'>Opciones</th>
					
				</tr>
				<?php
	while ($row=mysqli_fetch_array($query)){
			$id_fp=$row['id_fp'];//id dela factura programada, lo cual me permite borrar la fila de la tabla clientes_facturas_programadas
			$cliente=$row['nombre'];
			$fecha_agregado=$row['fecha_agregado'];
		?>					
		<input type="hidden" value="<?php echo $id_fp;?>" id="codigo_cliente<?php echo $id_fp; ?>">
		<tr>
								
			<td><?php echo $cliente; ?></td>
     		<td ><?php echo date("d/m/Y", strtotime($fecha_agregado)); ?></td>
		<td><span class="pull-right">
		<a href="#" class='btn btn-info' title='Detalle' onclick="detalle_factura_programada('<?php echo $id_fp; ?>')" data-toggle="modal" data-target="#DetalleFacturaProgramada"><i class="glyphicon glyphicon-th-list"></i> </a>
		<a href="#" class='btn btn-danger' title='Eliminar' onclick="eliminar_factura_programada('<?php echo $id_fp; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
			
		</tr>
		<?php
	}
				?>
				<tr>
					<td colspan=9 ><span class="pull-right">
					<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?></span></td>
				</tr>
			  </table>
			</div>
			</div>
			<?php
		}
	}
?>