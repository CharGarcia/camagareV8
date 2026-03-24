<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
		session_start();
		$id_usuario = $_SESSION['id_usuario'];
		$ruc_empresa = $_SESSION['ruc_empresa'];
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';

	if($action == 'buscar_marcas'){		
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('id_marca', 'nombre_marca');//Columnas de busqueda
		 $sTable = "marca";
		$sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." '  " ;
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND ruc_empresa = '".  $ruc_empresa ."' OR ";
			}
			
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by nombre_marca asc ";
		
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 20; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../marcas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
		<div class="table-responsive">
			<div class="panel panel-info">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Id</th>
					<th>Nombre</th>
					<th class='text-right'>Opciones</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_marca=$row['id_marca'];
						$nombre_marca=strtoupper ($row['nombre_marca']);					
					?>					
					<input type="hidden" value="<?php echo $id_marca;?>" id="id_marca<?php echo $id_marca;?>">
					<input type="hidden" value="<?php echo $nombre_marca;?>" id="nombre_marca<?php echo $id_marca;?>">
					<tr>
						<td><?php echo $id_marca; ?></td>
						<td class='col-xs-4'><?php echo $nombre_marca; ?></td>

					<td class='text-right'>
					<a href="#" class='btn btn-info btn-sm' title='Editar marca' onclick="obtener_datos('<?php echo $id_marca;?>');" data-toggle="modal" data-target="#marcas"><i class="glyphicon glyphicon-edit"></i></a> 
					<a href="#" class='btn btn-danger btn-sm' title='Eliminar marca' onclick="eliminar_marca('<?php echo $id_marca;?>');"><i class="glyphicon glyphicon-trash"></i></a> 
					</td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=3 ><span class="pull-right">
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