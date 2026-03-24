<?php
//PARA ELIMINAR frases

include("../conexiones/conectalogin.php");
$con = conenta_login();
if (isset($_POST['id_aviso'])){
		$id_aviso=intval($_POST['id_aviso']);
	
		//eliminar la frase
		if ($delete=mysqli_query($con,"DELETE FROM avisos_camagare WHERE id_aviso = '".$id_aviso."'")){
				echo "<script>
				$.notify('Aviso eliminado exitosamente','success')
				</script>";
		}else {
			?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> Lo siento algo ha salido mal intenta nuevamente.
			</div>
			<?php
			
		}
	}

	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));

		$aColumns = array('ruc_empresa', 'nombre', 'nombre_comercial', 'detalle_aviso');//Columnas de busqueda
		$sTable = "avisos_camagare as avi LEFT JOIN empresas as emp ON avi.ruc_empresa=emp.ruc";
		 $sWhere = "";
		 /*
		 $aColumns = array('ruc_empresa', 'nombre', 'nombre_comercial', 'detalle_aviso');//Columnas de busqueda
		$sTable = "avisos_camagare as avi, empresas as emp";
		 $sWhere = "WHERE avi.ruc_empresa=emp.ruc";
		
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (avi.ruc_empresa=emp.ruc AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND avi.ruc_empresa=emp.ruc OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND avi.ruc_empresa=emp.ruc ", -3 );
			$sWhere .= ')';
		}
		*/
		
		
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		
		
		$sWhere.=" order by id_aviso desc";
		
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
		$reload = '../frases_motivacion.php';
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
					<th>Ruc</th>
					<th>Empresa</th>
					<th>Aviso</th>
					<th class="text-right">Opciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_aviso=$row['id_aviso'];
						$ruc_compania=$row['ruc'];
						$ruc_empresa=$row['ruc_empresa'];
						if ($ruc_empresa=="9999999999999"){
						$nombre_comercial="APLICA PARA TODOS LOS USUARIOS";
						$ruc_compania="Todos";
						}else{
						$nombre_comercial=$row['nombre_comercial'];	
						}
						$detalle_aviso=$row['detalle_aviso'];
					?>
					<input type="hidden" value="<?php echo $id_aviso;?>" id="id_aviso<?php echo $id_aviso;?>">
					<tr>						
						<td><?php echo $ruc_compania; ?></td>
						<td><?php echo $nombre_comercial; ?></td>
						<td><?php echo $detalle_aviso; ?></td>
					<td ><span class="pull-right">
					<a href="#" class='btn btn-danger btn-md' title='Eliminar' onclick="eliminar_aviso('<?php echo $id_aviso;?>');" ><i class="glyphicon glyphicon-trash"></i></a> 
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=8><span class="pull-right">
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