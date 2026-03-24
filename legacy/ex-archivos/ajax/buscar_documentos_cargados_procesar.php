<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'ajax'){
		$id_usuario = $_SESSION['id_usuario'];
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $sTable = "empresas em, documentos_subidos ds";
		 $sWhere = "";
		 $sWhere.=" WHERE ds.estado = 'PENDIENTE' AND em.id = ds.id_empresa ";
		if ( $_GET['q'] != "" )
		{
		$sWhere.= " and  (em.nombre like '%".$q."%' or em.nombre_comercial like '%".$q."%' )";	
		}
		$sWhere.=" order by em.nombre asc";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../clientes.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Empresa</th>
					<th>Documentos</th>
					<th>Detalle</th>
					<th class='text-right'>Procesar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_documento=$row['id_documento'];
						$id_empresa=$row['id_empresa'];
						$cod_documento=$row['cod_documento'];
						$archivo=$row['archivo'];
						$detalle=$row['detalle'];
						$imageFileType = pathinfo(strtolower($archivo),PATHINFO_EXTENSION);
						
						//buscar el nombre de la empresa
						$busca_empresa = "SELECT * FROM empresas WHERE id = $id_empresa ";
						$resultado_de_la_busqueda_empresa = $con->query($busca_empresa);
						$empresa=mysqli_fetch_array($resultado_de_la_busqueda_empresa);
						$nombre_empresa=$empresa['nombre'];
						//buscar el nombre del documento
						$busca_documento = "SELECT * FROM tipos_documentos_subir WHERE cod_documento = '$cod_documento' ";
						$resultado_de_la_busqueda_documento = $con->query($busca_documento);
						$documento=mysqli_fetch_array($resultado_de_la_busqueda_documento);
						$nombre_documento=$documento['detalle_documento'];					
					?>	
						<input type="hidden" value="<?php echo $id_documento;?>" id="codigo_documento<?php echo $id_documento;?>">
						<input type="hidden" value="<?php echo $nombre_empresa;?>" id="empresa_documento<?php echo $id_documento;?>">
						<input type="hidden" value="<?php echo $nombre_documento;?>" id="nombre_documento<?php echo $id_documento;?>">
						<input type="hidden" value="<?php echo $detalle;?>" id="detalle_documento<?php echo $id_documento;?>">
					<tr>
					
						<td><?php echo $nombre_empresa; ?></td>
						<td><?php echo $nombre_documento; ?></td>
						<td><?php echo $detalle; ?></td>
					<td><span class="pull-right">
						<a href="#" class='btn btn-info btn-md' title='Procesar documento' onclick="procesar_doc('<?php echo $id_documento;?>');" data-toggle="modal" data-target="#procesarDocumentos" ><i class="glyphicon glyphicon-upload"></i> </a>
					</span></td>
					
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=9><span class="pull-right">
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
