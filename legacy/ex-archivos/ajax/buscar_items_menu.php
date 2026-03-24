<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('etiqueta');//Columnas de busqueda
		 $sTable = "menu";
		 $sWhere = "";
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
		$sWhere.=" order by etiqueta desc";
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
		$reload = '../opciones_de_menu.php';
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
					<th>Etiqueta</th>
					<th>Ruta</th>
					<th>Nivel</th>
					<th>Estado</th>
					<th class="text-right">Modificar</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_menu=$row['id'];
						$etiqueta_menu=$row['etiqueta'];
						$ruta_menu=$row['ruta'];
						$nivel_menu=$row['nivel'];
						$estado=$row['estado'];
						$estado_menu=$row['estado'];
						if ($estado==1){
							$estado="ACTIVO";
						}
						if ($estado==2){
							$estado="PASIVO";
						}
						
					?>
					<input type="hidden" value="<?php echo $id_menu;?>" id="id_menu<?php echo $id_menu;?>">
					<input type="hidden" value="<?php echo $etiqueta_menu;?>" id="etiqueta_menu<?php echo $id_menu;?>">
					<input type="hidden" value="<?php echo $ruta_menu;?>" id="ruta_menu<?php echo $id_menu;?>">
					<input type="hidden" value="<?php echo $nivel_menu;?>" id="nivel_menu<?php echo $id_menu;?>">
					<input type="hidden" value="<?php echo $estado_menu;?>" id="estado_menu<?php echo $id_menu;?>">
					<tr>						
						<td><?php echo $etiqueta_menu; ?></td>
						<td><?php echo $ruta_menu; ?></td>
						<td><?php echo $nivel_menu; ?></td>
						<td><?php echo $estado; ?></td>
					<td ><span class="pull-right">
					<a href="#" class='btn btn-info btn-md' title='Editar' onclick="editar_item_menu('<?php echo $id_menu;?>');" data-toggle="modal" data-target="#editarItemMenu"><i class="glyphicon glyphicon-edit"></i></a> 
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