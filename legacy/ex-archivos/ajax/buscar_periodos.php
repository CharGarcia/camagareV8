<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
	if (isset($_GET['id_periodo'])){
		$id_periodo=intval($_GET['id_periodo']);
		
		$busca_periodo = "SELECT * FROM periodo_contable WHERE ruc_empresa = '$ruc_empresa' ";
		 $result = $con->query($busca_periodo);
		 $count = mysqli_num_rows($result);
				 if ($count == 1) {
			echo "<script>alert('No es posible eliminar, al menos debe tener un período disponible el sistema.')</script>";
			echo "<script>window.close();</script>";
				 exit;
				 }
				 
			if ($delete=mysqli_query($con,"DELETE FROM periodo_contable WHERE id_periodo='".$id_periodo."'")){
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
		 $aColumns = array('mes_periodo', 'anio_periodo');//Columnas de busqueda
		 $sTable = "periodo_contable";
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
		$sWhere.=" order by anio_periodo, mes_periodo desc";
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
		$reload = '../periodos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
				<td>No.</td>
				<td>Mes</td>
				<td>Año</td>
				<td class='text-right'>Eliminar</td>
					
				</tr>
				<?php
					$n=0;	
					while($p = mysqli_fetch_assoc($query)){
						$n++;
				?>
						<tr>
								<td> <?php echo $n ?> </td>
								<td> <?php echo sprintf("%'.02d\n",($p['mes_periodo'])) ?> </td>
								<td> <?php echo ($p['anio_periodo']) ?> </td>
								<td class='text-right'>
								<a href="#" class='btn btn-default' title='Eliminar periodo' onclick="eliminar_periodo('<?php echo $p['id_periodo']; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
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
			<?php
		}
	}
?>