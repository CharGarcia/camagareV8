
<!DOCTYPE html>
<html lang="es">

<head>
<title>Asignar | Empresas</title>
<?php include("../head.php");?>
<script language="JavaScript"> 
function confirmar ( mensaje ) { 
return confirm( "¿Desea eliminar la empresa a este usuario?" ); 
} 
</script>
</head>
	
<body>
<?php
ob_start();

include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 2){
$titulo_info ="Asigna Empresas a Usuario";
include("../navbar_confi.php");

?>
<div class="col-md-12">
<div class="panel-body">
<div class="container">
<div class="row">
<div class="col-md-4">
<div class="panel panel-info" >
				
<?php
    $nivel = $_SESSION['nivel'];
    $id = $_SESSION['id_usuario'];
?>
	<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-pushpin'></i> Listado de usuarios de <?php echo $_SESSION['nombre'] ?> </h4>
				</div>
	<div class="panel-body">
<div class="table-responsive">
	<table class="table table-hover">
		<thead>
			<tr  class="info">
				<td>#</td>
				<td>Nombre</td>
				<td>Cedula</td>
				<td>Mostrar</td>
			</tr>
		</thead>
		
<?php			
	$conexion = conenta_login();
    $sqlestado = "SELECT * FROM estado_del_registro where nombre = 'Activo';";
	$resestado = mysqli_query($conexion,$sqlestado);
	$estado = mysqli_fetch_assoc($resestado);
    $idestado = $estado['idestado'];
    if ($nivel>=3) {
        $nivel = 4;
    }
    
    $sql = "SELECT u.id as id_usuario, u.nombre as nombre, u.cedula as cedula, ua.id as id FROM usuarios u, usuario_asignado ua where ua.id_adm = $id and u.id = ua.id_usuario and u.estado='1' ;";
	$res = mysqli_query($conexion,$sql);
	$n=0;
	while($p = mysqli_fetch_assoc($res)){
		$n++;
	
?>	
<tr>
				<td><?php echo  $n ?></td>
				<td><?php echo ($p['nombre']) ?></td>
				<td><?php echo ($p['cedula']) ?></td>
				<td><a href="asigna_empresas.php?op=ver&id_usuario=<?php echo $p['id_usuario'] ?> &nom=<?php echo $p['nombre'] ?>" class="btn btn-default" title="Mostrar Empresas" ><i class="glyphicon glyphicon-zoom-in"></i></a></td> 
<?php
	}
?>
</table>
</div>
</div>
</div>
</div>


<div class="col-md-4">
<div class="panel panel-info" >
<div class="panel-heading">
  <?php
function muestra_empresas_de_usuario(){
	$conexion = conenta_login();
    $nombre = $_GET['nom'];
	$id_usuario = $_GET['id_usuario'];
	
?>
<h4><i class='glyphicon glyphicon-pushpin'></i> Listado de empresas asignadas a <?php echo $nombre ?> </h4>
				</div>
	<div class="panel-body">
<div class="table-responsive">
	<table class="table table-hover">
		<tr  class="info">
		<td>#</td>
		<td>Empresa</td>
		<td>Eliminar</td>
		</tr>
<?php	
		
	$sql = "SELECT e.nombre_comercial as nombre, ea.id as id FROM empresas e, empresa_asignada ea WHERE e.id = ea.id_empresa and ea.id_usuario = $id_usuario  ;";
	$res = mysqli_query($conexion,$sql);
	$n=0;	
	while($p = mysqli_fetch_assoc($res)){
		$n++;
		?>
		<tr>
				<td><?php echo ($n) ?></td>
				<td><?php echo ($p['nombre']) ?></td>
				<td><a href="asigna_empresas.php?op=eliminar_empresa_asignada&id_registro=<?php echo $p['id'] ?>&id_usuario=<?php echo $id_usuario ?>&nom=<?php echo $nombre ?>" onclick="return confirmar()" class="btn btn-default" title="Eliminar Empresa" ><i class="glyphicon glyphicon-trash"></i></a></td>
		</tr>
		<?php
	}
?>
	</table>
	</div>
	</div>
	</div>
</div>
<?php
}

function muestra_todas_las_empresas(){
    $nivel =$_SESSION['nivel'];
    $id = $_SESSION['id_usuario'];
    $id_usuario = $_GET['id_usuario'];
	$nombre = $_GET['nom'];
?>

<div class="col-md-4">
<div class="panel panel-info" >
<div class="panel-heading">
<h4><i class='glyphicon glyphicon-pushpin'></i> Listado de empresas disponibles </h4>
</div>
	<div class="panel-body" >
<div class="table-responsive">
	<table class="table table-hover">
		<tr  class="info">
			<td>#</td>
			<td>Empresa</td>
			<td>Agregar</td>
</tr>

<?php
	$conexion = conenta_login();
	
	if ($nivel>=3) {
    $sql = "SELECT em.nombre_comercial as nombre, em.id as id FROM empresas em LEFT JOIN empresa_asignada ea ON ea.id_usuario = $id_usuario and em.id = ea.id_empresa WHERE ea.id_empresa is null and ea.id_usuario is null and em.estado = 1;";
    }else{
	$sql = "SELECT em.nombre_comercial as nombre, em.id as id FROM empresas em, empresa_asignada ea where em.id=ea.id_empresa and ea.id_usuario = $id and em.estado = 1 ;";
	}

    $res = mysqli_query($conexion,$sql);
	$n=0;	
	while($p = mysqli_fetch_assoc($res)){
		$n++;
	?>	
		<tr>
				<td><?php echo $n ?></td>
				<td><?php echo ($p['nombre']) ?></td>
<td><a href="asigna_empresas.php?op=add&id=<?php echo $p['id'] ?>&id_usuario=<?php echo $id_usuario ?>&nom=<?php echo $nombre ?>&usu_asignador= <?php echo $id ?>" class="btn btn-default" title="Agregar Empresa" ><i class="glyphicon glyphicon-plus"></i></a></td>
		</tr>
		<?php
	}
?>
	</table>
<?php
}

function eliminar_empresa_al_usuario($id_registro){
	$conexion = conenta_login();
	$usu_administrador = $_SESSION['id_usuario'];
	//para saber el id_usuario de las empresas asignadas
	$sql = "SELECT * FROM empresa_asignada where id = $id_registro ;";
	$resp = mysqli_query($conexion,$sql);
	$empresas = mysqli_fetch_assoc($resp);
	$id_usuario = $empresas['id_usuario'];
	$id_empresa = $empresas['id_empresa'];
	
	//para saber el nivel de usuario
	$sql = "SELECT * FROM usuarios where id = $id_usuario ;";
	$respu = mysqli_query($conexion,$sql);
	$niveles = mysqli_fetch_assoc($respu);
	$nivel = $niveles['nivel'];
	
	//para borrar todas las empresas asignadas, si el usuario es mayor que usuario normal
	if ($nivel > 1){
	$sql = "DELETE FROM empresa_asignada WHERE usu_asignador = $id_usuario and id_empresa = $id_empresa ;";
	mysqli_query($conexion,$sql);
	}
	
	//para borrar la empresa que se selecciona
	$sql = "DELETE FROM empresa_asignada WHERE id=$id_registro;";
	mysqli_query($conexion,$sql);
}

function agregar_empresa_al_usuario($id){
	$conexion = conenta_login();
    $nivel =$_SESSION['nivel'];
	$conexion = conenta_login();
	//$cedula = $_GET['ced'];
	$nombre = $_GET['nom'];
	$id_empresa = $_GET['id'];
	$id_usuario = $_GET['id_usuario'];
	$usu_asignador = $_GET['usu_asignador'];
   
	$sql = "SELECT * FROM empresa_asignada where id_usuario=$id_usuario and id_empresa= $id_empresa ;";
	$resp = mysqli_query($conexion,$sql);
	$total_empresas = mysqli_num_rows($resp);

if ($total_empresas == 0){
	$fecha_agregado=date("Y-m-d H:i:s");
	$sql = "INSERT INTO empresa_asignada VALUES(NULL,$id_empresa, $id_usuario, $usu_asignador, '$fecha_agregado' );";
	mysqli_query($conexion,$sql);
}else{
?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Atención!</strong> La empresa que seleccionó ya esta agregada! </div>
		  <?php
}
}

if (isset($_GET['op']) && $_GET['op'] == "eliminar_empresa_asignada"){
echo eliminar_empresa_al_usuario($_GET['id_registro']);
echo muestra_empresas_de_usuario($_GET['id_registro']);
echo muestra_todas_las_empresas();
}

if (isset($_GET['op']) && $_GET['op'] == "ver"){
echo muestra_empresas_de_usuario($_GET['id_usuario']);
echo muestra_todas_las_empresas();

}

if (isset($_GET['op']) && $_GET['op'] == "add"){
echo agregar_empresa_al_usuario($_GET['id']);
echo muestra_empresas_de_usuario($_GET['id']);
echo muestra_todas_las_empresas();

}
ob_end_flush();
?>
</div>
	</div>
	</div>
	</div>
</div>
</div>
</div>
</div>
<?php
}else{
header('Location: ../includes/logout.php');
exit;
}
?>
<?php include("../pie.php");?>
</body>

</html>
