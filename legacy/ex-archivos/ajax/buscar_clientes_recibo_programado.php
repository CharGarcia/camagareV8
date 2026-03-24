<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];

	$action = (isset($_REQUEST['actiones'])&& $_REQUEST['actiones'] !=NULL)?$_REQUEST['actiones']:'';
	if($action == 'ajax'){
		$condicion_ruc_empresa=	"ruc_empresa = '". $ruc_empresa ."'";
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['cli'], ENT_QUOTES)));
		 //$ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 //$por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('nombre','ruc','email','direccion','telefono');//Columnas de busqueda
		 $sTable = "clientes";
		 $sWhere = "WHERE $condicion_ruc_empresa";
		if ( $_GET['cli'] != "" )
		{
			$sWhere = "WHERE ($condicion_ruc_empresa AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' AND $condicion_ruc_empresa OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND $condicion_ruc_empresa ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by nombre asc";
			
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['pages']) && !empty($_REQUEST['pages']))?$_REQUEST['pages']:1;
		$per_page = 4; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
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
			<div class="table-responsive">
			<div class="panel panel-info">
			  <table class="table">
				<tr  class="warning">
					<th>Nombre</th>
					<th>Dirección</th>
					<th>RUC/CI</span></th>
					<th class='text-center' style="width: 36px;">Agregar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
					$id_cliente=$row['id'];
					$nombre_cliente_alumno=$row['nombre'];
					$direccion_cliente_alumno=$row['direccion'];
					$ruc_cliente_alumno=$row["ruc"];
					?>
					<tr>
						<td><?php echo $nombre_cliente_alumno; ?></td>
						<td><?php echo $direccion_cliente_alumno; ?></td>
						<td><?php echo $ruc_cliente_alumno; ?></td>
						<td data-dismiss="modal"><button type="submit"  onclick="agrega_cliente_recibo_programado('<?php echo $id_cliente; ?>')" class='btn btn-info'><i class="glyphicon glyphicon-plus"></i></button></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="4"><span class="pull-right">
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

