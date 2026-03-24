<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];

	//PARA ELIMINAR horarios
if (isset($_POST['id_horario'])){
		$id_horario=intval($_POST['id_horario']);

		// hay que comprbar si no esta usandose en alumnos
		$sql_horario = "SELECT * FROM alumnos WHERE horario_alumno = $id_horario";
                $query_check_horario = mysqli_query($con,$sql_horario);
				$count_horario=mysqli_num_rows($query_check_horario);
		if($count_horario>0){
			?>
			<div class="alert alert-danger alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Error!</strong> Lo siento no se puede eliminar el horario, existen registros en uso.
			</div>
			<?php
			exit;
		}
		
		//eliminar el nivel
		if ($delete=mysqli_query($con,"DELETE FROM horarios_alumnos WHERE id_horario = $id_horario")){
			?>
			<div class="alert alert-success alert-dismissible" role="alert">
			  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			  <strong>Bien hecho!</strong> Se eliminó correctamente el detalle de horario.
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
	
	
//PARA BUSCAR LOS MIVELES O PARALELOS
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombre_horario');//Columnas de busqueda
		 $sTable = "horarios_alumnos";
		 $sWhere = "WHERE ruc_empresa ='".  $ruc_empresa ." ' ";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (ruc_empresa ='".  $ruc_empresa ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND ruc_empresa = '".  $ruc_empresa ."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by nombre_horario asc";
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
		$reload = '../horarios_alumnos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>Código</th>
					<th>Detalle</th>
					<th class='text-right'>Opciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_horario=$row['id_horario'];
						$nombre_horario=$row['nombre_horario'];
										
							
					?>
					<input type="hidden" value="<?php echo $id_horario;?>" id="id_horario<?php echo $id_horario;?>">
					<input type="hidden" value="<?php echo $nombre_horario;?>" id="nombre_horario<?php echo $id_horario;?>">
					<tr>
						<td><?php echo $id_horario; ?></td>
						<td><?php echo strtoupper($nombre_horario); ?></td>
					<td><span class="pull-right">
					<a href="#" class='btn btn-info btn-xs' title='Editar' onclick="pasa_datos_editar_horario('<?php echo $id_horario; ?>')" data-toggle="modal" data-target="#editarHorario"><i class="glyphicon glyphicon-edit"></i> </a>
					<a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_horario('<?php echo $id_horario; ?>')"><i class="glyphicon glyphicon-erase"></i> </a>
					</span></td>
					
					</tr>
				<?php
				}
				?>
				
				<tr>
					<td colspan=9 ><span class="pull-right"><?php echo paginate($reload, $page, $total_pages, $adjacents);?></span></td>
				</tr>
			  </table>
			</div>
			<?php
		}
	}
?>