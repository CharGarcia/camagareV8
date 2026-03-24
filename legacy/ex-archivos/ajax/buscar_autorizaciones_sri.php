<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//Archivo de funciones PHP
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('codigo_documento', 'id_serie');//Columnas de busqueda
		 $sTable = "autorizaciones_sri";
		 $sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ." '", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by id_autorizacion desc";
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
		$reload = '../autorizaciones_sri.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Sucursal</th>
					<th>Documento</th>
					<th>Autorización</th>
					<th>Emisión</th>
					<th>Vencimiento</th>
					<th>Del</th>
					<th>Al</th>
					<th>Datos imprenta</th>
					<th class='text-right'>Editar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_autorizacion=$row['id_autorizacion'];
						$id_serie=$row['id_serie'];
						$codigo_documento=$row['codigo_documento'];
						$autorizacion_sri=$row['autorizacion_sri'];
						$fecha_emision=$row['emision_autorizacion'];
						$fecha_vencimiento=$row['vence_autorizacion'];
						$del=$row['del_autorizacion'];
						$al=$row['al_autorizacion'];
						$imprenta=$row['imprenta'];

					?>					
					<input type="hidden" value="<?php echo $id_serie;?>" id="id_serie<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo $codigo_documento;?>" id="codigo_documento<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo $autorizacion_sri;?>" id="autorizacion_sri<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo date("d/m/Y", strtotime($fecha_emision));?>" id="fecha_emision<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo date("d/m/Y", strtotime($fecha_vencimiento));?>" id="fecha_vencimiento<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo $del;?>" id="del_sri<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo $al;?>" id="al_sri<?php echo $id_autorizacion;?>">
					<input type="hidden" value="<?php echo $imprenta;?>" id="imprenta_sri<?php echo $id_autorizacion;?>">
					<tr>
						
						<?php
						$sql="SELECT * FROM  sucursales where id_sucursal = $id_serie ";
						$queri_sucursal = mysqli_query($con, $sql);
						$fila_tipo=mysqli_fetch_array($queri_sucursal);
						$serie = $fila_tipo['serie'];
						?>
						
						<td><?php echo $serie; ?></td>
						<?php
						$sql="SELECT * FROM  comprobantes_autorizados where codigo_comprobante = $codigo_documento ";
						$queri_documento = mysqli_query($con, $sql);
						$fila_tipo=mysqli_fetch_array($queri_documento);
						$documento = $fila_tipo['comprobante'];
						?>
						
						<td ><?php echo $documento; ?></td>
						<td ><?php echo $autorizacion_sri; ?></td>
						<td ><?php echo date("d/m/Y", strtotime($fecha_emision)); ?></td>
						<td ><?php echo date("d/m/Y", strtotime($fecha_vencimiento)); ?></td>
						<td ><?php echo $del; ?></td>
						<td ><?php echo $al; ?></td>
						<td ><?php echo $imprenta; ?></td>
						
					<td ><span class="pull-right">
					<a href="#" class='btn btn-default' title='Editar autorización' onclick="pasa_datos_autorizacion('<?php echo $id_autorizacion;?>');" data-toggle="modal" data-target="#editautorizacionsri"><i class="glyphicon glyphicon-edit"></i></a> 
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
			<?php
		}
	}
?>