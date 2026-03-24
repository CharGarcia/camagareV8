<?php
include("../conexiones/conectalogin.php");
ini_set('date.timezone','America/Guayaquil');
$con = conenta_login();
$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
		session_start();
		$id_usuario = $_SESSION['id_usuario'];

//eliminar modulos asignados		
if($action == 'eliminar_usuario_asignado'){
	$id_registro = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_registro'], ENT_QUOTES)));
	$query_new_delete=mysqli_query($con, "DELETE FROM usuario_asignado WHERE id='".$id_registro."'");
	
	if ($query_new_delete){
		echo "<script>
		$.notify('Usuario eliminado.','success');
		</script>";		
	} else{
		echo "<script>
		$.notify('Intente de nuevo','error');
		</script>";	
	}
}
//eliminar empresa asignada		
if($action == 'eliminar_empresa_asignada'){
	$id_registro = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_registro'], ENT_QUOTES)));
	
	$sql_id_empresa_asignada=mysqli_query($con, "SELECT * FROM empresa_asignada WHERE id='".$id_registro."' ");
	$row_id_empresa_asignada=mysqli_fetch_array($sql_id_empresa_asignada);
	$usu_asignador=$row_id_empresa_asignada['usu_asignador'];
	$id_empresa=$row_id_empresa_asignada['id_empresa'];
	$id_usuario_asignado=$row_id_empresa_asignada['id_usuario'];
	
	if ($usu_asignador != $id_usuario ){
		echo "<script>$.notify('No es posible eliminar, ya que fue asignada por otro administrador.','error')</script>";
	}else{
	$query_new_delete=mysqli_query($con, "DELETE FROM empresa_asignada WHERE id='".$id_registro."'");
	$elimina_modulo=mysqli_query($con,"DELETE FROM modulos_asignados WHERE id_usuario='".$id_usuario_asignado."' and id_empresa='".$id_empresa."'");

	if ($query_new_delete && $elimina_modulo){
			echo "<script>
			$.notify('Empresa y módulos removidos.','success');
			</script>";		
		} else{
			echo "<script>
			$.notify('Intente de nuevo','error');
			</script>";	
		}
	}
}


//busca modulos asignados		
if($action == 'buscar_modulos_asignados'){
         $busca_modulos = mysqli_real_escape_string($con,(strip_tags($_REQUEST['busca_modulos'], ENT_QUOTES)));
		 $id_usuario_seleccionado = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_usuario_seleccionado'], ENT_QUOTES)));
		 $id_empresa = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_empresa_asignada'], ENT_QUOTES)));
		 
		 //buscar el nivel de ususario
		 $query_nivel_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id='".$id_usuario."'");
		 $row_nivel_usuario = mysqli_fetch_array($query_nivel_usuario);
		 $nivel_usuario = $row_nivel_usuario['nivel'];
		 
		 if ($nivel_usuario==2){
			 $aColumns = array('nombre_submodulo','nombre_modulo');//Columnas de busqueda
			 $sTable = "submodulos_menu as smen INNER JOIN modulos_asignados as masi ON smen.id_submodulo=masi.id_submodulo INNER JOIN modulos_menu modme ON modme.id_modulo=masi.id_modulo and masi.id_usuario='".$id_usuario."'"; 
			 $sWhere = "";
			 if ( $_GET['busca_modulos'] != "" )
			{
				$sWhere = "WHERE ( ";
				for ( $i=0 ; $i<count($aColumns) ; $i++ )
				{
					$sWhere .= $aColumns[$i]." LIKE '%".$busca_modulos."%' OR ";
				}
				$sWhere = substr_replace( $sWhere, "", -3 );
				$sWhere .= ')';
			}
			$sWhere.="";
			$query = mysqli_query($con, "SELECT distinct smen.nombre_submodulo as nombre_submodulo, masi.id_submodulo as id_submodulo, masi.id_modulo as id_modulo FROM  $sTable $sWhere order by smen.nombre_submodulo asc");
			}
		
				if ($nivel_usuario==3){
				 $aColumns = array('nombre_submodulo','nombre_modulo');//Columnas de busqueda
				 $sTable = "submodulos_menu as smen INNER JOIN modulos_menu modme ON modme.id_modulo=smen.id_modulo "; 
				 $sWhere = "";
				 if ( $_GET['busca_modulos'] != "" )
				{
					$sWhere = "WHERE ( ";
					for ( $i=0 ; $i<count($aColumns) ; $i++ )
					{
						$sWhere .= $aColumns[$i]." LIKE '%".$busca_modulos."%' OR ";
					}
					$sWhere = substr_replace( $sWhere, "", -3 );
					$sWhere .= ')';
				}
				$sWhere.="";
				$query = mysqli_query($con, "SELECT smen.nombre_submodulo as nombre_submodulo, smen.id_submodulo as id_submodulo, smen.id_modulo as id_modulo FROM  $sTable $sWhere order by smen.nombre_submodulo asc");
				}
			?>
			<div class="panel panel-info" style="overflow-y: scroll; height: 250px;">
			<div class="table-responsive">
			  <table class="table table-hover">
				<tr class="info">
					<td>Módulo</td>
					<td>Menú</td>
					<td>Opción</td>
				</tr>
				<?php
				
				while ($row=mysqli_fetch_array($query)){
						$id_submodulo=$row['id_submodulo'];
						$id_modulo=$row['id_modulo'];
						$nombre_submodulo=$row['nombre_submodulo'];
					//para mostrar el nombre del modulo
					$sql_modulo = mysqli_query($con, "SELECT * FROM modulos_menu WHERE id_modulo='".$id_modulo."' ");
					$row_modulo=mysqli_fetch_array($sql_modulo);
					$nombre_modulo=$row_modulo['nombre_modulo'];
					//para saber que modulo esta asignado a ese usuario y empresa
					$sql_modulo_asignado = mysqli_query($con, "SELECT * FROM modulos_asignados WHERE id_usuario='".$id_usuario_seleccionado."' and id_empresa='".$id_empresa."' and id_submodulo='".$id_submodulo."' ");
					$row_modulo_asignado=mysqli_fetch_array($sql_modulo_asignado);
					$id_modulo_asignado=$row_modulo_asignado['id_submodulo'];
					?>
					<tr>
					<td><?php echo $nombre_submodulo; ?></td>
					<td><?php echo $nombre_modulo; ?></td>
					<?php
					if ($id_submodulo == $id_modulo_asignado ){
					?>
					<td><input style="height:20px;" type="checkbox" class="form-control" checked onclick="agregar_modulo('<?php echo $id_usuario_seleccionado ?>','<?php echo $id_empresa ?>','<?php echo $id_modulo ?>','<?php echo $id_submodulo ?>')" id="elemento_seleccionado"></td>
					<?php
					}else{
					?>
					<td><input style="height:20px;" type="checkbox" class="form-control" onclick="agregar_modulo('<?php echo $id_usuario_seleccionado ?>','<?php echo $id_empresa ?>','<?php echo $id_modulo ?>','<?php echo $id_submodulo ?>')" id="elemento_seleccionado"></td>
					<?php
					}
					?>	
					</tr>
					<?php
				}
				?>
			  </table>
			</div>
			</div>
			<?php
	}
	
	
//buscar empresas asignadas	
if($action == 'buscar_empresas_asignadas'){
		$id_usuario_seleccionado =mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_usuario_asignado'], ENT_QUOTES)));
		 $busca_empresa = mysqli_real_escape_string($con,(strip_tags($_REQUEST['busca_empresa'], ENT_QUOTES)));
		 $aColumns = array('nombre','nombre_comercial','ruc');//Columnas de busqueda
		 $sTable = "empresas emp INNER JOIN empresa_asignada emp_asi ON emp.id=emp_asi.id_empresa";
		 $sWhere = "WHERE emp_asi.id_usuario = '".$id_usuario_seleccionado."' ";
		if ( $_GET['busca_empresa'] != "" )
		{
			$sWhere = "WHERE ( emp_asi.id_usuario ='".$id_usuario_seleccionado."' and ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$busca_empresa."%' and emp_asi.id_usuario = '".$id_usuario_seleccionado."' OR ";
			}
			$sWhere = substr_replace( $sWhere, " and emp_asi.id_usuario ='".$id_usuario_seleccionado."' ", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by emp.nombre asc";
		$sql="SELECT emp.id as id_empresa, emp_asi.id as id_empresa_asignada, emp.nombre_comercial as nombre_comercial FROM $sTable $sWhere ";
		$query = mysqli_query($con, $sql);
		?>
		<div class="panel panel-info" style="overflow-y: scroll; height: 250px;">
		<div class="table-responsive">
		<table class="table table-hover">
		<tr class="info">
			<td>Empresas asignadas</button></td>
			<td><span class="text-center">Módulos asignados</span></td>
			<td class="text-right">Eliminar empresa</td>
		</tr>
		<?php
			while ($row=mysqli_fetch_array($query)){
					$id_empresa_asignada=$row['id_empresa_asignada'];
					$nombre_empresa=$row['nombre_comercial'];
					$id_empresa=$row['id_empresa'];
					$sql_total_modulos=mysqli_query($con, "SELECT id_submodulo FROM modulos_asignados WHERE id_usuario='".$id_usuario_seleccionado."' and id_empresa='".$id_empresa."'");
					$total_modulos_asignados = mysqli_num_rows($sql_total_modulos);
				?>
			<tr>
				<td><?php echo strtoupper ($nombre_empresa); ?></td>
				<td><span class="text-center">
				<a href="#" class='btn btn-success btn-sm' title='Agregar módulos' onclick="buscar_modulo_asignado('<?php echo $id_empresa ?>', '<?php echo $nombre_empresa?>' ,'<?php echo $id_usuario_seleccionado?>', '1');" data-toggle="modal" data-target="#asignar_modulos"><i class="glyphicon glyphicon-saved"></i> Módulos asignados <span class="badge"><?php echo $total_modulos_asignados ?></span></a></span></td>
				<td><span class="pull-right">
				<a href="#" class='btn btn-danger btn-xs' title='Quitar empresa asignada' onclick="quitar_empresa_asignada('<?php echo $id_empresa_asignada;?>');" data-toggle="modal" data-target="#myModal2"><i class="glyphicon glyphicon-trash"></i></a></span></td>
			</tr>
			<?php
				}
				?>
			</table>
		</div>
		</div>
	<?php
		
	}

//para asignar empresaas para administrarlos
if($action == 'asignar_empresa'){
$id_empresa_agregar = mysqli_real_escape_string($con,(strip_tags($_POST['id_empresa_agregar'], ENT_QUOTES)));
$id_usuario_agregar = mysqli_real_escape_string($con,(strip_tags($_POST['id_usuario_agregar'], ENT_QUOTES)));

$sql_empresa_asignada=mysqli_query($con, "SELECT * FROM empresa_asignada WHERE id_empresa='".$id_empresa_agregar."' and id_usuario='".$id_usuario_agregar."'");
$cuenta_asignaciones=mysqli_num_rows($sql_empresa_asignada);
		
if ($cuenta_asignaciones >0 ){
	echo "<script>$.notify('La empresa ya esta asignada.','error')</script>";
}else{
	$query_new_insert=mysqli_query($con, "INSERT INTO empresa_asignada VALUES (NULL, '".$id_empresa_agregar."', '".$id_usuario_agregar."','".$id_usuario."', '".date('Y-m-d h:i:sa')."')");
		if ($query_new_insert){
			echo "<script>
			$.notify('Empresa asignada.','success');
			</script>";		
		} else{
			echo "<script>
			$.notify('Intente de nuevo','error');
			</script>";	
		}
	}
}

//para asignar usuarios para administrarlos
if($action == 'asignar_usuario'){
$id_usuario_agregar = mysqli_real_escape_string($con,(strip_tags($_POST['id_usuario_agregar'], ENT_QUOTES)));
$query_new_insert=mysqli_query($con, "INSERT INTO usuario_asignado VALUES (NULL, '".$id_usuario_agregar."', '".$id_usuario."', '".date('Y-m-d h:i:sa')."')");
	if ($query_new_insert){
		echo "<script>
		$.notify('Usuario asignado.','success');
		</script>";		
	} else{
		echo "<script>
		$.notify('Intente de nuevo','error');
		</script>";	
	}
}


//Para buscar usuarios ya asiganados	
	if($action == 'usuarios_asignados'){
		// escaping, additionally removing everything that could be (html/javascript-) code
         $q = mysqli_real_escape_string($con,(strip_tags($_REQUEST['q'], ENT_QUOTES)));
		 $ordenado = mysqli_real_escape_string($con,(strip_tags($_GET['ordenado'], ENT_QUOTES)));
		 $por = mysqli_real_escape_string($con,(strip_tags($_GET['por'], ENT_QUOTES)));
		 $aColumns = array('nombre','cedula');//Columnas de busqueda
		 $sTable = "usuario_asignado usu_asi INNER JOIN usuarios usu ON usu_asi.id_usuario=usu.id";
		 //usu.id=usu_asi.id
		 $sWhere = "WHERE usu_asi.id_adm='".$id_usuario."' ";
		if ( $_GET['q'] != "" )
		{
			$sWhere = "WHERE ( usu_asi.id_adm='".$id_usuario."' and ";
			for ( $i=0 ; $i<count($aColumns) ; $i++ )
			{
				$sWhere .= $aColumns[$i]." LIKE '%".$q."%' and usu_asi.id_adm='".$id_usuario."' OR ";
			}
			$sWhere = substr_replace( $sWhere, " and usu_asi.id_adm='".$id_usuario."'", -3 );
			$sWhere .= ')';
		}
		$sWhere.=" order by $ordenado $por";
		include ("../ajax/pagination.php"); //include pagination file
		//pagination variables
		$page = (isset($_REQUEST['page']) && !empty($_REQUEST['page']))?$_REQUEST['page']:1;
		$per_page = 10; //how much records you want to show
		$adjacents  = 4; //gap between pages after number of adjacents
		$offset = ($page - 1) * $per_page;
		//Count the total number of row in your table*/
		$count_query   = mysqli_query($con, "SELECT count(*) AS numrows FROM $sTable $sWhere");
		$row= mysqli_fetch_array($count_query);
		$numrows = $row['numrows'];
		$total_pages = ceil($numrows/$per_page);
		$reload = '../configura_usuarios.php';
		//main query to fetch the data
		$sql="SELECT usu_asi.id as id_registro, usu_asi.id_usuario as id_usuario_asignado, usu.nombre as nombre, usu.cedula as cedula, usu.estado as estado FROM $sTable $sWhere LIMIT $offset,$per_page";
		$query = mysqli_query($con, $sql);
		//loop through fetched data
		if ($numrows>0){ 
		?>
<div class="panel panel-info">
<div class="table-responsive">
	<table class="table table-hover">
	<tr class="info">
		<td style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("nombre");'>Nombre de usuario</button></td>
		<td style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("cedula");'>Cédula</button></td>
		<td style ="padding: 0px;"><button style ="border-radius: 0px; border:0;" class="list-group-item list-group-item-info" onclick='ordenar("estado");'>Estado</button></td>
		<td><span class="text-center">Empresas asignadas</span></td>
		<td class="text-right">Eliminar usuario</td>
	</tr>
	<?php
				while ($row=mysqli_fetch_array($query)){
						$id_registro=$row['id_registro'];
						$id_usuario_asignado=$row['id_usuario_asignado'];
						$nombre_usuario=$row['nombre'];
						$cedula_usuario=$row['cedula'];
						$estado_usuario=$row['estado']==1?"Activo":"Pasivo";
						$sql_total_empresas=mysqli_query($con, "SELECT * FROM empresa_asignada WHERE id_usuario='".$id_usuario_asignado."'");
						$total_empresas_asignadas = mysqli_num_rows($sql_total_empresas);
						
					?>
	<tr>
		<td><?php echo strtoupper ($nombre_usuario); ?></td>
		<td><?php echo $cedula_usuario; ?></td>
		<td><?php echo $estado_usuario; ?></td>
		<td><span class="pull-center">
		<a href="#" class='btn btn-info btn-sm' title='Agregar empresas' onclick="buscar_empresa_asignada('<?php echo $id_usuario_asignado; ?>','<?php echo $nombre_usuario; ?>','1')" data-toggle="modal" data-target="#asignar_empresas"><i class="glyphicon glyphicon-briefcase"></i> Empresas asignadas <span class="badge"><?php echo $total_empresas_asignadas ?></span></a></span></td>
		<td><span class="pull-right">
		<a href="#" class='btn btn-danger btn-xs' title='Quitar usuario asignado' onclick="eliminar_usuario_asignado('<?php echo $id_registro;?>');"><i class="glyphicon glyphicon-trash"></i></a></span></td>
		</tr>
		<?php
				}
				?>
		<tr>
			<td colspan="5">
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
?>