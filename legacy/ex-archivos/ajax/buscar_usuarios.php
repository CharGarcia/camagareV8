<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	 //if (isset($_GET['id'])){
	//	$id_usuario=intval($_GET['id']);
	//	} 
	if($action == 'mostrar_usuarios'){
		//actualizar estado de session de los usuarios
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $aColumns = array('nombre','cedula','mail','telefono');//Columnas de busqueda
		 $sTable = "usuarios";
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
		$sWhere.=" order by nombre asc";
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
		$reload = '../opciones_de_usuarios.php';
		//main query to fetch the data
		$sql="SELECT * FROM  $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){ 
		?>
<div class="panel panel-info">
<div class="table-responsive">
	<table class="table">
	<tr class="info">
		<td>Tipo</td>
		<td>Nombre</td>
		<td>Cedula</td>
		<td>Estado</td>
		<td>Modo</td>
		<td>Entrada</td>
		<td>Mail</td>
		<td>Modificar</td>
	</tr>
	<?php
			while ($row=mysqli_fetch_array($query)){
					$id_usuario=$row['id'];
					$tipo_usuario=$row['tipo'];
					$nombre_usuario=$row['nombre'];
					$cedula_usuario=$row['cedula'];
					$est_usuario=$row['estado'];
					$mail_usuario=$row['mail'];
					$respu = mysqli_query($con, "SELECT * FROM estado_del_registro where idestado = '".$est_usuario."'");      
					$e = mysqli_fetch_assoc($respu);
					$estado_usuario = $e['nombre'];
					
					$modo_usuario = mysqli_query($con, "SELECT * FROM control_usuarios where id_usuario = '".$id_usuario."' and estado='ONLINE'");      
					$total_online = mysqli_num_rows($modo_usuario);
					$row_online = mysqli_fetch_array($modo_usuario);
					$fecha_entrada=$row_online['fecha_entrada'];
											
					if ($total_online>0){
					$label_class_modo='label-success';
					$modo="On";	
					}else{
					$label_class_modo='label-danger';
					$modo="Off";
					}
				?>
	<input type="hidden" value="<?php echo base64_encode($tipo_usuario);?>" id="tipo_usuario<?php echo $id_usuario;?>">
	<input type="hidden" value="<?php echo base64_encode($nombre_usuario);?>" id="nombre_usuario<?php echo $id_usuario;?>"> 
	<input type="hidden" value="<?php echo base64_encode($cedula_usuario);?>" id="cedula_usuario<?php echo $id_usuario;?>"> 
	<input type="hidden" value="<?php echo base64_encode($est_usuario);?>" id="estado_usuario<?php echo $id_usuario;?>"> 
	<input type="hidden" value="<?php echo base64_encode($mail_usuario);?>" id="mail_usuario<?php echo $id_usuario;?>">
	<tr>
		<td><?php echo $tipo_usuario; ?></td>
		<td><?php echo $nombre_usuario; ?></td>
		<td><?php echo $cedula_usuario; ?></td>
		<td><?php echo $estado_usuario; ?></td>
		<td><span class="label <?php echo $label_class_modo;?>"><?php echo $modo; ?></span></td>
		<td><?php echo $fecha_entrada; ?></td>
		<td><?php echo $mail_usuario; ?></td>
		<td><span class="pull-center"><a href="#" class='btn btn-default' title='Editar usuario' onclick="obtener_datos('<?php echo $id_usuario;?>');" data-toggle="modal" data-target="#myModal2"><i class="glyphicon glyphicon-edit"></i></a></span></td>
		</tr>
		<?php
				}
				?>
		<tr>
			<td colspan="8">
				<span class="pull-right">
				<?php
					 echo paginate($reload, $page, $total_pages, $adjacents);
					?>
				</span>
			</td>
		</tr>
		</table>
	</div>
	</div>
	<?php
		}
	}
	
if($action == 'actualizar_estado'){
/*
$actualizar_estado=mysqli_query($con, "SELECT TIMESTAMPDIFF(hour, fecha_entrada, now()) as tiempo from control_usuarios WHERE estado='ONLINE' ");
$row=mysqli_fetch_array($actualizar_estado);
$tiempo=$row['tiempo'];
echo $tiempo;
*/
$actualizar_estado=mysqli_query($con, "UPDATE control_usuarios SET estado='OFFLINE' WHERE estado='ONLINE' and TIMESTAMPDIFF(hour, fecha_entrada, now()) > 6 ");
}
	
?>