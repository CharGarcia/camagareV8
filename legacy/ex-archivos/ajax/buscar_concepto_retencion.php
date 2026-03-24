<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	
//PARA BUSCAR EXEPCIONES DE RETENCIONES
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $r = mysqli_real_escape_string($con,(strip_tags($_REQUEST['r'], ENT_QUOTES)));
		 $aColumns = array('codigo_ret','concepto_ret','impuesto_ret');//Columnas de busqueda
		 $sTable = "retenciones_sri";
		$sWhere = "";
		if ( $_GET['r'] != "" )
		{
			$sWhere = "WHERE (";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$r."%' OR ";
			}
			$sWhere = substr_replace( $sWhere, "", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by concepto_ret asc";
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
					<th>Código</th>
					<th>Concepto</th>
					<th>Impuesto</th>
					<th>Porcentaje</th>
					<th>Base imponible</th>
					<th>Agregar</th>
				</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_ret=$row['id_ret'];
						$codigo=$row['codigo_ret'];
						$concepto=$row['concepto_ret'];
						$impuesto=$row['impuesto_ret'];
						$porcentaje=$row['porcentaje_ret'];
					?>
					<tr>
						<td><?php echo ($codigo); ?></td>
						<td><?php echo ($concepto); ?></td>
						<td><?php echo ($impuesto); ?></td>
						<td><input type="text" class="form-control" style="text-align:right" id="porcentaje_ret<?php echo $id_ret; ?>" value="<?php echo $porcentaje; ?>"></td>
						<td class='col-xs-2'><div class="pull-right">
						<input type="text" class="form-control" style="text-align:right" id="base_imponible_ret<?php echo $id_ret; ?>"  >
						</div></td>
						<td class='text-center'><a class='btn btn-info'href="#" onclick="agregar_concepto_retencion('<?php echo $id_ret ?>')"><i class="glyphicon glyphicon-plus"></i></a></td>

					<?php
					}
					?>
					</tr>
				<?php
				}
				?>
					<td colspan=6><span class="pull-right">
					<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?></span></td>
			  </table>
			</div>
			<?php
	}
?>