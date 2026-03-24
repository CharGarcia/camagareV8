<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
<title>Asignar Usuarios</title>
<?php include("../head.php");?>
<script language="JavaScript"> 
function confirmar ( mensaje ) { 
return confirm( "Al quitar este usuario, también se eliminan las empresas asiganadas por este administrador ¿Desea quitar el usuario a este administrador?" ); 
} 
</script>
</head>

	
<body>
<?php
ob_start();
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 2){
$titulo_info ="Agregar y Eliminar Usuarios a Cada Administrador. ";
include("../navbar_confi.php");

?>
<div class="col-md-12">
<div class="container">
<div class="row">
	<div class="col-md-4">
		<div class="panel panel-info" >
<?php
$id = $_SESSION['id_usuario'];
?>
				<div class="panel-heading">
					<h4><i class="glyphicon glyphicon-pushpin"></i> Usuarios administrados por <?php echo $_SESSION['nombre']?> </h4>
				</div>
			<div class="panel-body">
					<div class="table-responsive">
						<table class="table table-condensed" >
								<tr  class="info">
								<td>#</td>
							   <td>Nombre</td>
								<td>Cedula</td>
								<td>Eliminar</td>
								</tr>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT u.nombre as nombre, u.cedula as cedula, ua.id as id FROM usuarios u, usuario_asignado ua where ua.id_adm = $id and u.id = ua.id_usuario ;";
							$res = mysqli_query($conexion,$sql);
							$n=0;
							while($p = mysqli_fetch_assoc($res)){
							$n++;
							?>
							<tr>
							<td><?php echo $n ?></td>
							<td><?php echo $p['nombre'] ?></td>
							<td><?php echo $p['cedula'] ?></td>
							<td><a href="asigna_usuarios.php?op=elimina_usuario&id=<?php echo $p['id'] ?>" onclick="return confirmar()" class="btn btn-default" title="Eliminar módulo" ><i class="glyphicon glyphicon-trash"></i></a></td>
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
echo muestra_todos_los_usuarios();
	}else{
?>
		<div class="alert alert-danger alert-dismissible" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<strong>Hey!</strong> Usted no tiene permisos para acceder a este sitio! 
		</div>
<?php
}
?>

<?php

function muestra_todos_los_usuarios(){
	$id=$_SESSION['id_usuario'];
    $nivel=$_SESSION['nivel'];
?>
	<div class="col-md-6">
		<div class="panel panel-info">
				<div class="panel-heading">
					<h4><i class="glyphicon glyphicon-pushpin"></i> Listado de usuarios disponibles para agregar </h4>
				</div>
			<div class="panel-body">
				<div class="table-responsive">
					<table class="table table-condensed" >
						<tr  class="info">
						<td>#</td>
							<td>Tipo</td>
							<td>Nombre</td>
							<td>cedula</td>
							<td>Agregar</td>
						</tr>
						<?php 
							$conexion = conenta_login();
							$sqlestado = "SELECT * FROM estado_del_registro where nombre = 'Activo';";
							$resestado = mysqli_query($conexion,$sqlestado);
							$estado = mysqli_fetch_assoc($resestado);
							$idestado = $estado['idestado']; 
							if ($nivel>=3) {
								$nivel = 4;
							}
							
							$sql = "SELECT u.nombre as nombre, u.cedula as cedula, u.id as id, u.tipo as tipo FROM usuarios u LEFT JOIN usuario_asignado ua ON u.id = ua.id_usuario and ua.id_adm = $id WHERE ua.id_usuario is null and u.estado = $idestado and u.nivel < $nivel;";
							
							$n=0;
							$res = mysqli_query($conexion,$sql);
							while($p = mysqli_fetch_assoc($res)){
								$n++;
						?>
						<tr>
							<td><?php echo $n ?></td>
							<td><?php echo $p['tipo'] ?></td>
							<td><?php echo $p['nombre']?></td>
							<td><?php echo $p['cedula']?></td>
						<td><a href="../paginas/asigna_usuarios.php?op=add&id_usuario=<?php echo $p['id'] ?>&id_adm=<?php echo $id ?>" class="btn btn-default" title="Agregar módulo" ><i class="glyphicon glyphicon-plus"></i></a></td>
								</tr>
						<?php
							}
						?>
					</table>
				</div>
			</div>
		</div>
	</div>
	
</div>
</div>
</div>
<?php
}


function eliminar_usuario_al_administrador(){
	$conexion = conenta_login();
	$id =$_GET['id'];
	$usu_administrador = $_SESSION['id_usuario'];
	//para saber el id_usuario de los usuarios asignados
	$sql = "SELECT * FROM usuario_asignado where id = $id ;";
	$resp = mysqli_query($conexion,$sql);
	$datos_usuario = mysqli_fetch_assoc($resp);
	$id_usuario = $datos_usuario['id_usuario'];
	
	//para saber el nivel de usuario
	$sql = "SELECT * FROM usuarios where id = $id_usuario ;";
	$respu = mysqli_query($conexion,$sql);
	$niveles = mysqli_fetch_assoc($respu);
	$nivel = $niveles['nivel'];
	
	//para borrar todas las empresas asigandas dependientes del usuario que se borra
	$sql = "DELETE FROM empresa_asignada WHERE id_usuario = $id_usuario and usu_asignador = $usu_administrador;";
	mysqli_query($conexion,$sql);
	
	//para borrar todas las empresas asignadas al usuario que se borra
	if ($nivel > 1){
	$sql = "DELETE FROM empresa_asignada WHERE usu_asignador = $id_usuario ;";
	mysqli_query($conexion,$sql);
	}
	//para borrar todos los usuarios administrados por el usuario que se borra
	$sql = "DELETE FROM usuario_asignado WHERE id_adm=$id_usuario ;";
	mysqli_query($conexion,$sql);
	
	//para borrar el usuario que se selecciona
	$sql = "DELETE FROM usuario_asignado WHERE id=$id;";
	mysqli_query($conexion,$sql);
}

function agregar_usuario_al_administrador(){
	$conexion = conenta_login();
	
	$id_usuario = $_GET['id_usuario'];
	$id_adm = $_GET['id_adm'];

	$sql = "SELECT * FROM usuario_asignado where id_usuario = $id_usuario and id_adm = $id_adm ;";
	$resp = mysqli_query($conexion,$sql);
	$total_usuarios = mysqli_num_rows($resp);

if ($total_usuarios == 0){
	$fecha_agregado=date("Y-m-d H:i:s");
	$sql = "INSERT INTO usuario_asignado VALUES(NULL, $id_usuario, $id_adm, '$fecha_agregado' );";
	mysqli_query($conexion,$sql);
}
}

if (isset($_GET['op']) && $_GET['op'] == "elimina_usuario"){
echo eliminar_usuario_al_administrador();
header("Location: asigna_usuarios.php");
}

if (isset($_GET['op']) && $_GET['op'] == "add"){
echo agregar_usuario_al_administrador();
header("Location: asigna_usuarios.php");
}
//ob_end_flush();
?>							

<?php include("../pie.php");?>
</body>

</html>
