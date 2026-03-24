<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	
//PARA BUSCAR EXEPCIONES DE RETENCIONES
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $e = mysqli_real_escape_string($con,(strip_tags($_REQUEST['e'], ENT_QUOTES)));
		 $retencion = mysqli_real_escape_string($con,(strip_tags($_REQUEST['retencion'], ENT_QUOTES)));
		 $aColumns = array('detalle_info');//Columnas de busqueda
		 $sTable = "info_legal";
		$sWhere = "WHERE aplica_a ='".  $retencion ." ' ";
		if ( $_GET['e'] != "" )
		{
			$sWhere = "WHERE (aplica_a ='".  $retencion ." ' AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$e."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND aplica_a = '".  $retencion ."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by detalle_info asc";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 5; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable  $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../alumnos.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			?>
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr  class="info">
					<th>#</th>
					<th>Exepciones</th>
				</tr>
				<?php
				$n=0;
				while ($row=mysqli_fetch_array($query)){
						$detalle=$row['detalle_info'];
						$n=$n+1;	
					?>
					<tr>
						<td><?php echo ($n); ?></td>
						<td><?php echo ($detalle); ?></td>
					<?php
					}
					?>
					</tr>
				<?php
				}
				?>
					<td colspan=2><span class="pull-right">
					<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?></span></td>
			  </table>
			</div>
			<?php
	}
?>