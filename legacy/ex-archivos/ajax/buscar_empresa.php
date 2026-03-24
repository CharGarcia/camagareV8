<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombre','nombre_comercial','ruc');//Columnas de busqueda
		 $sTable = "empresas";
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
		$sWhere.="order by nombre";
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
		$reload = '../paginas/opciones_de_empresas.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){
			
			?>
			<div class="table-responsive">
			  <table class="table table-striped table-bordered" style="font-size:12px">
				<tr  class="info">		
			<td>Nombre</td>
			<td>Nombre comercial</td>
			<td>Ruc</td>
			<td>Dirección</td>
			<td>Teléfonos</td>
            <td>Representante Legal</td>
            <td>Mail</td>
			<td>Estado</td>
			<td>Logo</td>
			<td>Editar estado</td>
</tr>
				<?php
				while ($row=mysqli_fetch_array($query)){
						$id_empresa=$row['id'];
						$nombre_empresa=$row['nombre'];
						$nombre_comercial=$row['nombre_comercial'];
						$ruc_empresa=$row['ruc'];
						$direccion_empresa=strtolower($row['direccion']);		
						$telefono_empresa=$row['telefono'];
						$tipo_empresa=$row['tipo'];
						$nom_rep_legal=$row['nom_rep_legal'];
						$ced_rep_legal=$row['ced_rep_legal'];
						$mail_empresa=strtolower($row['mail']);
						$logo_empresa=$row['logo'];
						$provincia=$row['cod_prov'];
						$ciudad=$row['cod_ciudad'];
						$estado=$row['estado'];
					?>
					<input type="hidden" value="<?php echo $estado;?>" id="estado_empresa<?php echo $id_empresa;?>">
					<tr>						
						<td><?php echo $nombre_empresa; ?></td>
						<td><?php echo $nombre_comercial; ?></td>
						<td><?php echo $ruc_empresa; ?></td>
						<td><?php echo $direccion_empresa; ?></td>
						<td><?php echo $telefono_empresa; ?></td>
						<td><?php echo $nom_rep_legal;?></td>
						<td><?php echo $mail_empresa;?></td> 
						<?php
						
        $sql = "SELECT * FROM estado_del_registro where idestado = $estado ;";      
  		$restado = mysqli_query($con,$sql);
        $es = mysqli_fetch_assoc($restado);
        $tipo_estado = $es['nombre'];
        
						?>
						<td><?php echo $tipo_estado;?></td>
						<td><img border="0" src="<?php echo $logo_empresa;?>" width="50" height="50"></td>
					
					<td ><span class="pull-right">
					<a href="#" class='btn btn-default' title='Editar empresa' onclick="obtener_datos('<?php echo $id_empresa;?>');" data-toggle="modal" data-target="#edita_empresa"><i class="glyphicon glyphicon-edit"></i></a> </td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan=11><span class="pull-right">
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