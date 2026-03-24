<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	include ("../clases/empresas.php");
	include_once("../helpers/helpers.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	$fecha_registro=date("Y-m-d H:i:s");
	ini_set('date.timezone','America/Guayaquil');
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	

	//para buscar detalles de compras
		if($action == 'detalle_compras'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['d'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('codigo_producto','detalle_producto');//Columnas de busqueda
		 $sTable = "cuerpo_compra as cc LEFT JOIN encabezado_compra as ec ON cc.codigo_documento=ec.codigo_documento 
		 INNER JOIN proveedores as pro ON pro.id_proveedor=ec.id_proveedor INNER JOIN comprobantes_autorizados com_aut ON com_aut.id_comprobante=ec.id_comprobante";
		 $sWhere = "WHERE cc.ruc_empresa ='". $ruc_empresa ." ' " ;
		
		 $text_buscar = explode(' ',$q);
		 $like="";
		 for ( $i=0 ; $i<count($text_buscar) ; $i++ )
		 {
			 $like .= "%".$text_buscar[$i];
		 }
		if ( $_GET['d'] != "" )
		{
			$sWhere = "WHERE (cc.ruc_empresa ='". $ruc_empresa ." ' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$like."%' AND cc.ruc_empresa ='". $ruc_empresa ." ' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, " AND cc.ruc_empresa ='". $ruc_empresa ." ' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by $ordenado $por";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 10; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("codigo_producto");'>Código</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("detalle_producto");'>Descripción</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cantidad");'>Cantidad</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("precio");'>Precio</button></th>								
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("tipo_documento");'>Documento</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("numero_documento");'>Número</button></th>
				<th style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" >Proveedor</button></th>
				<th class='text-right'>Opciones</th>
				<input type="hidden" value="<?php echo $page;?>" id="pagina">
				</tr>
				<?php

				while ($row=mysqli_fetch_array($query)){
						//$id_encabezado_compra=$row['id_encabezado_compra'];
						$codigo=$row['codigo_producto'];
						$detalle=$row['detalle_producto'];
						$cantidad=$row['cantidad'];
						$precio=$row['precio'];
						$codigo_documento=$row['codigo_documento'];
						$numero_documento=$row['numero_documento'];
						$nombre_proveedor = $row['razon_social'];
						$id_comprobante = $row['id_comprobante'];
						$nombre_comprobante = $row['comprobante'];

					?>
					<tr>
						<td><?php echo $codigo; ?></td>
						<td><?php echo strtoupper ($detalle); ?></td>
						<td><?php echo $cantidad; ?></td>
						<td><?php echo $precio; ?></td>
						<td><?php echo strtoupper ($nombre_comprobante); ?></td>
						<td><?php echo $numero_documento; ?></td>
						<td><?php echo strtoupper ($nombre_proveedor); ?></td>
						<td class='col-md-2'><span class="pull-right">
						<?php
							if(getPermisos($con, $id_usuario, $ruc_empresa, 'compras')['u']==1){
							?>
							<a href="#" class="btn btn-info btn-xs" onclick="detalle_factura_compra('<?php echo $codigo_documento; ?>')" title="Detalle documento" data-toggle="modal" data-target="#detalleDocumento"><i class="glyphicon glyphicon-list-alt"></i></a>
						<?php
							}
						?>
						</td>
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