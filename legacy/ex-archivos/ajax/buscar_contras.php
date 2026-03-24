<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
	 if (isset($_GET['id_clave'])){
		$id_misclaves=intval($_GET['id_clave']);
		
			if ($delete1=mysqli_query($con,"DELETE FROM mis_claves WHERE id_misclaves=$id_misclaves")){
			?>
			<div class="alert alert-success alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Aviso!</strong> Registro eliminado exitosamente.
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
		
	if($action == 'ajax'){
		$id_usuario = $_SESSION['id_usuario'];
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $sTable = "empresas, mis_claves";
		 $sWhere = "";
		 $sWhere.=" WHERE empresas.ruc = mis_claves.ruc_empresa and mis_claves.id_usuario = '$id_usuario' ";
		if ( $_GET['q'] != "" )
		{
		$sWhere.= " and  (empresas.nombre like '%".$q."%' or empresas.nombre_comercial like '%".$q."%' or mis_claves.institucion like '%".$q."%' or mis_claves.usuario like '%".$q."%' or mis_claves.detalle like '%".$q."%' )";	
		}
		$sWhere.=" order by empresas.nombre asc";
		
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
					<th>Ruc</th>
					<th>Cedula RL</th>
					<th>Institución</th>
					<th>Usuario</th>
					<th>Clave</th>
					<th>Detalle</th>
					<th class="text-right">Acciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_misclaves=$row['id_misclaves'];
						$nombre_empresa=$row['nombre'];
						$ruc_empresa=$row['ruc'];
						$cedula_rl=$row['ced_rep_legal'];
						$institucion=$row['institucion'];
						$usuario=$row['usuario'];
						$clave=$row['clave'];
						$detalle=$row['detalle'];
					?>
					<input type="hidden" value="<?php echo $id_misclaves;?>" id="id_contra<?php echo $id_misclaves;?>">
					<input type="hidden" value="<?php echo $ruc_empresa;?>" id="ruc_empresa<?php echo $id_misclaves;?>">
					<input type="hidden" value="<?php echo $institucion;?>" id="institucion<?php echo $id_misclaves;?>">
					<input type="hidden" value="<?php echo $usuario;?>" id="usuario<?php echo $id_misclaves;?>">
					<input type="hidden" value="<?php echo $clave;?>" id="clave<?php echo $id_misclaves;?>">
					<input type="hidden" value="<?php echo $detalle;?>" id="detalle<?php echo $id_misclaves;?>">
					<tr>						
						<td><?php echo $nombre_empresa; ?></td>
						<td><input class="form-control" type="text" value="<?php echo $ruc_empresa; ?>"></td>
						<td><input class="form-control" type="text" value="<?php echo $cedula_rl; ?>"></td>
						<td><?php echo strtoupper($institucion);?></td>
						<td><input class="form-control" type="text" value="<?php echo $usuario; ?>"></td>
						<td><input class="form-control" type="text" value="<?php echo $clave; ?>"></td>
						<td><?php echo $detalle;?></td>
					<td ><span class="pull-right">
					<a href="#" class='btn btn-default btn-xs' title='Editar clave' onclick="obtener_datos_claves('<?php echo $id_misclaves;?>');" data-toggle="modal" data-target="#editarRegistroClaves"><i class="glyphicon glyphicon-edit"></i></a>
					<a href="#" class='btn btn-default btn-xs' title='Eliminar clave' onclick="eliminar_clave('<?php echo $id_misclaves;?>');" ><i class="glyphicon glyphicon-trash"></i></a>
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