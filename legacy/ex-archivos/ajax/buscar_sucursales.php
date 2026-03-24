<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
	if (isset($_GET['id_sucursal'])){
		$id_sucursal=intval($_GET['id_sucursal']);
		
		$busca_periodo = "SELECT * FROM sucursales WHERE ruc_empresa = '$ruc_empresa' ";
		 $result = $con->query($busca_periodo);
		 $count = mysqli_num_rows($result);
				 if ($count == 1) {
			echo "<script>alert('No es posible eliminar, al menos debe tener una sucursal disponible el sistema.')</script>";
			echo "<script>window.close();</script>";
				 exit;
				 }
				 
			if ($delete=mysqli_query($con,"DELETE FROM sucursales WHERE id_sucursal='".$id_sucursal."'")){
			echo "<script>alert('Datos eliminados exitosamente.')</script>";
			echo "<script>window.close();</script>";
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
		 $aColumns = array('serie');//Columnas de busqueda
		 $sTable = "sucursales";
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
		$sWhere.=" order by id_sucursal desc";
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
		$reload = '../sucursales.php';
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
				<td>Número</td>
				<td>Serie</td>
				<td>Nombre</td>
				<td>Dirección</td>
				<td>Teléfonos</td>
				<td class='text-right'>Opciones</td>
				</tr>
<?php
					$n=0;	
					while($p = mysqli_fetch_assoc($query)){
						$n++;
				?>
						<tr>
								<td> <?php echo $n ?> </td>
								<td> <?php echo ($p['serie']) ?> </td>
								<td> <?php echo ($p['nombre_sucursal']) ?> </td>
								<td> <?php echo ($p['direccion_sucursal']) ?> </td>
								<td> <?php echo ($p['telefono_sucursal']) ?> </td>	

								<input type="hidden" value="<?php echo $p['serie'];?>" id="serie_sucursal<?php echo $p['id_sucursal'];?>">
								<input type="hidden" value="<?php echo $p['nombre_sucursal'];?>" id="nombre_sucursal<?php echo $p['id_sucursal'];?>">
								<input type="hidden" value="<?php echo $p['direccion_sucursal'];?>" id="direccion_sucursal<?php echo $p['id_sucursal'];?>">
								<input type="hidden" value="<?php echo $p['telefono_sucursal'];?>" id="telefono_sucursal<?php echo $p['id_sucursal'];?>">
								
						        <td class='col-md-2 text-right'>
								<a href="#" class='btn btn-info' title='Editar sucursal' data-toggle="modal" data-target="#editaSucursal" onclick="obtener_datos('<?php echo $p['id_sucursal']; ?>')"><i class="glyphicon glyphicon-edit"></i> </a></span>								
								<a href="#" class='btn btn-default' title='Eliminar sucursal' onclick="eliminar_sucursal('<?php echo $p['id_sucursal']; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
								</td>
						
						
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
			</div>
			<?php
		}
	}
?>