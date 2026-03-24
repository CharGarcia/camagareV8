<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
		
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $id_usuario = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_usuario'], ENT_QUOTES)));
		 $id_empresa = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_empresa'], ENT_QUOTES)));
		 $aColumns = array('nombre_submodulo');//Columnas de busqueda
		 $sTable = "submodulos_menu";
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
		$sWhere.=" order by $ordenado $por";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 6; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../usuarios_y_modulos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			
			?>
			<div class="panel panel-info">
			<div class="table-responsive">
			  <table class="table">
				<tr class="info">
					<td>Módulo</td>
					<td>Menú</td>
					<td>Opción</td>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_submodulo=$row['id_submodulo'];
						$id_modulo=$row['id_modulo'];
						$nombre_submodulo=$row['nombre_submodulo'];
					//para mostrar el nombre del modulo
					$sql_modulo = mysqli_query($con, "SELECT * FROM modulos_menu WHERE id_modulo='".$id_modulo."' ");
					$row_modulo=mysqli_fetch_array($sql_modulo);
					$nombre_modulo=$row_modulo['nombre_modulo'];
					//para saber que modulo esta asignado a ese usuario y empresa
					$sql_modulo_asignado = mysqli_query($con, "SELECT * FROM modulos_asignados WHERE id_usuario='".$id_usuario."' and id_empresa='".$id_empresa."' and id_submodulo='".$id_submodulo."' ");
					$row_modulo_asignado=mysqli_fetch_array($sql_modulo_asignado);
					$id_modulo_asignado=$row_modulo_asignado['id_submodulo'];
					?>
					<input type="hidden" value="<?php echo $id_submodulo;?>" id="id_submodulo<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $id_modulo;?>" id="id_modulo<?php echo $id_submodulo;?>">
					<input type="hidden" value="<?php echo $nombre_submodulo;?>" id="nombre_submodulo<?php echo $id_submodulo;?>">
					<tr>
			
					<td><?php echo $nombre_submodulo; ?></td>
					<td><?php echo $nombre_modulo; ?></td>
					<?php
					if ($id_submodulo == $id_modulo_asignado ){
					?>
					<td><input type="checkbox" class="form-control" checked onclick="agregar_modulo('<?php echo $id_submodulo; ?>')" id="elemento_seleccionado"></td>
					<?php
					}else{
					?>
					<td><input type="checkbox" class="form-control" onclick="agregar_modulo('<?php echo $id_submodulo; ?>')" id="elemento_seleccionado"></td>
					<?php
					}
					?>	
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=5><span class="pull-right">
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