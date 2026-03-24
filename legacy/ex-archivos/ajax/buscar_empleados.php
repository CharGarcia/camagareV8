<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	//Archivo de funciones PHP
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombres_empleado', 'apellidos_empleado');//Columnas de busqueda
		 $sTable = "empleados";
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
		$sWhere.=" order by id_empleado desc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../empleados.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Cedula</th>
					<th>Apellidos</th>
					<th>Nombres</th>
					<th>Cargo</th>
					<th>Estado</th>
					<th class='text-right'>Acciones</th>
					
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_empleado=$row['id_empleado'];
						$cedula_empleado=$row['cedula_empleado'];
						$nombres_empleado=$row['nombres_empleado'];
						$apellidos_empleado=$row['apellidos_empleado'];
						$cargo_empleado=$row['cargo_empleado'];
						$estado_empleado=$row['estado_empleado'];

					?>					
					
					<tr>
						
						<td><?php echo $cedula_empleado; ?></td>
						<td><?php echo strtoupper ($nombres_empleado); ?></td>
						<td><?php echo strtoupper ($apellidos_empleado); ?></td>
						
						
						<?php
						$conexion = conenta_login();
						$sql = "SELECT * FROM cargo_iess WHERE id_rama = $cargo_empleado ;";
						$res = mysqli_query($conexion,$sql);
						$cargo = mysqli_fetch_assoc($res);
						?>					
						<td><?php echo $cargo['cargo_rama']; ?></td>
						<td><?php echo $estado_empleado; ?></td>

						<td ><span class="pull-right">
					<a href="#" class='btn btn-default' title='Sueldo empleado' onclick="obtener_datos('<?php echo $id_empleado;?>');" data-toggle="modal" data-target="#sueldoEmpleado"><i class="glyphicon glyphicon-usd"></i></a> 
					<a href="#" class='btn btn-default' title='Editar empleado' onclick="obtener_datos('<?php echo $id_empleado;?>');" data-toggle="modal" data-target="#nuevoEmpleado"><i class="glyphicon glyphicon-edit"></i></a> 


					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=8 ><span class="pull-right">
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