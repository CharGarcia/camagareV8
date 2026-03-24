<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
	if (isset($_GET['id_cuenta'])){
		$id_cuenta=intval($_GET['id_cuenta']);				 
			if ($delete=mysqli_query($con,"DELETE FROM cuentas_bancarias WHERE id_cuenta=$id_cuenta")){
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
		 $aColumns = array('be.nombre_banco','cb.numero_cuenta');//Columnas de busqueda
		 $sTable = "bancos_ecuador be, cuentas_bancarias cb";
		 $sWhere = "WHERE cb.ruc_empresa ='".  $ruc_empresa ." ' and cb.id_banco=be.id_bancos";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE (cb.ruc_empresa ='".  $ruc_empresa ." ' and cb.id_banco=be.id_bancos AND ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' and cb.id_banco=be.id_bancos OR ";
			}
			$sWhere = substr_replace( $sWhere, "AND cb.ruc_empresa = '".  $ruc_empresa ." ' and cb.id_banco=be.id_bancos", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by be.nombre_banco desc";
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
				<td>Banco</td>
				<td>Tipo cuenta</td>
				<td>Número</td>
				<td class='text-right'>Opciones</td>
				</tr>
<?php
					$n=0;	
					while($fila = mysqli_fetch_assoc($query)){						
						$n++;
						switch ($fila['id_tipo_cuenta']) {
					case "1":
						$tipo_cuenta='AHORROS';
						break;
					case "2":
						$tipo_cuenta='CORRIENTE';
						break;
					case "3":
						$tipo_cuenta='VIRTUAL';
						break;
					case "4":
						$tipo_cuenta='TARJETA';
						break;
						}
				?>
						<tr>
								<td> <?php echo $n ?> </td>
								<td> <?php echo ($fila['nombre_banco']) ?> </td>
								<td> <?php echo ($tipo_cuenta) ?> </td>
								<td> <?php echo ($fila['numero_cuenta']) ?> </td>								
						        <td class='text-right'>
								<a href="#" class='btn btn-default' title='Eliminar cuenta' onclick="eliminar_cuenta_bancaria('<?php echo $fila['id_cuenta']; ?>')"><i class="glyphicon glyphicon-trash"></i> </a></span></td>
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