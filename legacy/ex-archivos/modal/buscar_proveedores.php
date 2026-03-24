<?php
include("../conexiones/conectalogin.php");
include_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	 
	if($action == 'eliminar_proveedor'){
	
	$id_proveedor=intval($_GET['id_proveedor']);
	
	$buscar_proveedor_compras=mysqli_query($con,"SELECT * FROM encabezado_compra WHERE id_proveedor='".$id_proveedor."'");
	$total_proveedores_compras=mysqli_num_rows($buscar_proveedor_compras);
	
	$buscar_proveedor_liquidacion=mysqli_query($con,"SELECT * FROM encabezado_liquidacion WHERE id_proveedor='".$id_proveedor."'");
	$total_proveedores_liquidacion=mysqli_num_rows($buscar_proveedor_liquidacion);
	
	$buscar_proveedor_retenciones=mysqli_query($con,"SELECT * FROM encabezado_retencion WHERE id_proveedor='".$id_proveedor."'");
	$total_proveedores_retenciones=mysqli_num_rows($buscar_proveedor_retenciones);
	
	if (($total_proveedores_retenciones + $total_proveedores_compras + $total_proveedores_liquidacion)==0){
	
		if ($delete_proveedor=mysqli_query($con,"DELETE FROM proveedores WHERE id_proveedor='".$id_proveedor."'")){
		echo "<script>$.notify('Proveedor eliminado.','success')</script>";
		}else {
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
		}			
	}else{
		echo "<script>$.notify('No es posible eliminar porque esta asignado a una transacción.','error')</script>";	
	}
	 
}
	
		
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('razon_social','nombre_comercial','dir_proveedor','ruc_proveedor','mail_proveedor','telf_proveedor');//Columnas de busqueda ,'nombre_comercial','ruc_proveedor','dir_proveedor'
		 $sTable = "proveedores";
		$sWhere = "WHERE ruc_empresa = '".$ruc_empresa."' ";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE ruc_empresa = '".$ruc_empresa."' and ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' and ruc_empresa = '".$ruc_empresa."' OR ";
			}
			$sWhere = substr_replace( $sWhere, " and ruc_empresa = '".$ruc_empresa."'", -3 );
			$sWhere .= '';
		}
		$sWhere.=" order by razon_social asc";

		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../proveedores.php';
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
					<th>Razón social</th>
					<th>Nombre comercial</th>
					<th>Ruc/Cedula</th>
					<th>Teléfono</th>
					<th>Email</th>
					<th>Dirección</th>
					<th>Tipo</th>
					<th>Plazo</th>
					<th class="text-right">Acciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_proveedor=$row['id_proveedor'];
						$razon_social=$row['razon_social'];
						$nombre_comercial=$row['nombre_comercial'];
						$ruc_proveedor=$row['ruc_proveedor'];
						$telf_proveedor=$row['telf_proveedor'];
						$dir_proveedor=$row['dir_proveedor'];
						$mail_proveedor=$row['mail_proveedor'];
						$tipo=$row['tipo_empresa'];
						$tipo_id=$row['tipo_id_proveedor'];
						$plazo=$row['plazo'];
						$unidad_tiempo=$row['unidad_tiempo'];
						$relacionado=$row['relacionado'];
						
						//buscar TIPO DE EMPRESA
						$busca_tipo_empresa = "SELECT * FROM tipo_empresa WHERE codigo= '$tipo'";
						$result = $con->query($busca_tipo_empresa);
						$datos_tipo = mysqli_fetch_array($result);
						$tipo_empresa =$datos_tipo['nombre'];
						//buscar PARTE RELACIONADA
						if ($relacionado==1){
							$relacionado="NO";
						}else{
							$relacionado="SI";
						}
					?>
					<input type="hidden" value="<?php echo $razon_social;?>" id="razon_social<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $nombre_comercial;?>" id="nombre_comercial<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $ruc_proveedor;?>" id="ruc_proveedor<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $telf_proveedor;?>" id="telf_proveedor<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $dir_proveedor;?>" id="dir_proveedor<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $tipo;?>" id="tipo<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $tipo_id;?>" id="tipo_id<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $mail_proveedor;?>" id="mail_proveedor<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $plazo;?>" id="plazo<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $unidad_tiempo;?>" id="unidad_tiempo<?php echo $id_proveedor;?>">
					<input type="hidden" value="<?php echo $relacionado;?>" id="relacionado<?php echo $id_proveedor;?>">
					<tr>						
						<td><?php echo strtoupper ($razon_social); ?></td>
						<td><?php echo strtoupper ($nombre_comercial); ?></td>
						<td><?php echo $ruc_proveedor; ?></td>
						<td><?php echo $telf_proveedor;?></td>
						<td><?php echo strtolower($mail_proveedor);?></td>
						<td><?php echo strtoupper ($dir_proveedor);?></td>
						<td><?php echo $tipo_empresa;?></td>
						<td><?php echo $plazo ." ". $unidad_tiempo;?></td>
					<td ><span class="pull-right">
					<?php
						if(getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['u']==1){
							?>
							<a href="#" class='btn btn-info btn-xs' title='Editar proveedor' onclick="obtener_datos('<?php echo $id_proveedor;?>');" data-toggle="modal" data-target="#editarProveedor"><i class="glyphicon glyphicon-edit"></i></a> 									
							<?php
					}
						if(getPermisos($con, $id_usuario, $ruc_empresa, 'proveedores')['d']==1){
							?>
							<a href="#" class='btn btn-danger btn-xs' title='Eliminar proveedor' onclick="eliminar_proveedor('<?php echo $id_proveedor;?>');"><i class="glyphicon glyphicon-trash"></i></a>
							<?php
					}
					?>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="10"><span class="pull-right">
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