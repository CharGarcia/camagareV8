<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones | Módulos-Submódulos</title>
<?php include("../head.php");?>
<script language="JavaScript"> 
function confirmar ( mensaje ) { 
return confirm( "¿Desea eliminar el módulo o sub módulo?" ); 
} 
</script>
</head>

<body>
<?php
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 3){
$titulo_info ="Opciones de Módulos y Submómulos";
$conexion = conenta_login();
?>
<?php 
include("../modal/nuevo_modulo.php");
include("../modal/nuevo_sub_modulo.php");
include("../navbar_confi.php");
?>


<nav class="navbar navbar" role="navigation">
    <div class="container">
    <div class="collapse navbar-collapse" >
 		<ul class="nav navbar-nav navbar-center" >
		<li><a href="#" data-toggle="modal" data-target="#nuevoModulo"><span class="glyphicon glyphicon-pencil"></span> Nuevo Módulo</a></li>
		<li><a href="#" data-toggle="modal" data-target="#nuevosubModulo"><span class="glyphicon glyphicon-pencil"></span> Nuevo Sub Módulo</a></li>
		<li><a href="opciones_de_modulos.php?op=vermo" ><span class="glyphicon glyphicon-list" aria-hidden="true"></span> Ver Módulos</a></li>
		<li><a href="opciones_de_modulos.php?op=versubmod" ><span class="glyphicon glyphicon-list" aria-hidden="true"></span> Ver Sub Módulos</a></li>

		</ul>
    </div>
  </div>
</nav>


<?php
function muestra_modulos(){
	$conexion = conenta_login();
	$sql = "SELECT * FROM modulos where id_usuario = 0 and id_empresa = 0 order by nombre asc ;";
	$res = mysqli_query($conexion,$sql);
	
	$html = '
	<div class="container-fluid">
	<div class="col-md-4 col-md-offset-4">
		<div class="panel panel-info">
				<div class="panel-heading">
				<h4><i class="glyphicon glyphicon-search"></i> Listado de módulos</h4>
				</div>
				<div class="panel-body">			
	<div class="table-responsive">
			  <table class="table table-condensed">
				<tr  class="info">
	<td>Nombre</td>
	<td>Icono</td>
	<td>Eliminar</td>
	<td>Modificar</td>
';
	$n=1;	
	while($p = mysqli_fetch_assoc($res)){
		$n++;
		$html .= '<tr>
				<td>' . ($p['nombre']) . '</td>
				<td><i class="' . ($p['icono']) . '"></i></td>
				<td align="center"><a href="opciones_de_modulos.php?op=delmod&id=' . $p['id'] . '&nom=' . $p['nombre'] . '"onclick="return confirmar()"class="btn btn-default" title="Eliminar módulo" ><i class="glyphicon glyphicon-trash"></i></a></td>
				<td align="center"><a href="opciones_de_modulos.php?op=editmod&id=' . $p['id'] . '&nom=' . $p['nombre'] . '" class="btn btn-default" title="Editar módulo" ><i class="glyphicon glyphicon-edit"></i></a></td>
		</tr>
		<tbody>';
	}
	$html .= '
	</table>
	</div>
	</div>
</div>
</div>
	
	';
	echo $html;
}

function muestra_sub_modulos(){
	$conexion = conenta_login();
	$sql = "SELECT sm.id as idsm, mo.nombre as nomod, sm.nombre as nosubmod, sm.icono as icono, sm.ruta as ruta FROM modulos mo, sub_modulos sm  where sm.id_modulo > 0 and sm.id_usuario = 0 and sm.id_empresa = 0 and sm.id_modulo = mo.id order by sm.nombre asc ;";
	$res = mysqli_query($conexion,$sql);
	
	$html = '
	<div class="container-fluid">
	<div class="col-md-9 col-md-offset-1">
		<div class="panel panel-info">
				<div class="panel-heading">
				<h4><i class="glyphicon glyphicon-search"></i> Listado de sub módulos</h4>
				</div>
				<div class="panel-body">
				
	<div class="table-responsive">
			  <table class="table table-condensed">
				<tr  class="info">
	
	<td>Sub Módulo</td>
	<td>Icono</td>
	<td>Dentro del Módulo</td>
	<td>Ubicación</td>
	<td>Eliminar</td>
	<td>Modificar</td>
	</tr>';
	$n=1;	
	while($p = mysqli_fetch_assoc($res)){
		$n++;
		$html .= '<tr>
				<td>' . ($p['nosubmod']) . '</td>
				<td><i class="' . ($p['icono']) . '"></i></td>
				<td>' . ($p['nomod']) . '</td>
				<td>'. ($p['ruta']) .'</td>
				<td align="center"><a href="opciones_de_modulos.php?op=delsubmod&id=' . $p['idsm'] . '&nom=' . $p['nosubmod'] . '"onclick="return confirmar()"class="btn btn-default" title="Eliminar módulo" ><i class="glyphicon glyphicon-trash"></i></a></td>
				<td align="center"><a href="opciones_de_modulos.php?op=modifica_ruta&id='. $p['idsm'] .'&nom=' . $p['nosubmod'] . '"class="btn btn-default" title="Editar submódulo" ><i class="glyphicon glyphicon-edit"></a></td>
		</tr>
		<tbody>';
	}
	$html .= '
	</table>
	</div>
	</div>
</div>
</div>';
	echo $html;
}


function eliminar_modulo($id){
	$conexion = conenta_login();
	$nombre = $_GET['nom'];
	
	$sql = "DELETE FROM modulos WHERE nombre='$nombre';";
if(mysqli_query($conexion,$sql)){
	?>
	
		  <div class="alert alert-success alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Bien hecho!</strong> Se eliminó correctamente! </div>
		  <?php
	}else{
		?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Algo pasa!</strong> Error al eliminar el registro! </div>
		  <?php
	}
}

function eliminar_sub_modulo($id){
	$conexion = conenta_login();
	$nombre = $_GET['nom'];
	$sql = "DELETE FROM sub_modulos WHERE nombre='$nombre';";
if(mysqli_query($conexion,$sql)){
?>
		  <div class="alert alert-success alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Bien hecho!</strong> Se eliminó correctamente! </div>
		  <?php
	}else{
	?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Algo pasa!</strong> Error al eliminar el registro! </div>
		  <?php
	}
}


function editar_sub_modulo(){
	$id = $_GET['id'];
	$nombre_actual = $_GET['nom'];
	$conexion = conenta_login();
		$sql = "SELECT * FROM sub_modulos WHERE id=$id;";
		$res = mysqli_query($conexion,$sql);
		$sub_modulo = mysqli_fetch_assoc($res);
		$ruta = $sub_modulo['ruta'];
		$nombre = $sub_modulo['nombre'];
		$icono = $sub_modulo['icono'];
		
?>
<div class="col-md-6 col-md-offset-3">
<div class="container-center">
		<div class="panel panel-info" >
				<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-pencil'></i> Modificar información del submódulo</h4>
				</div>	

	<div class="panel-body">
		
	<form class="form-horizontal" method="POST" name="edita_submodulo">
			  
			  <div class="form-group">
				<label class="col-sm-3 control-label control-label">Nombre</label>
				<div class="col-sm-6">	
				<input type="hidden" name="nombre_actual" value = "<?php echo $nombre_actual ?>">
				<td><input class="form-control input-sm" type="text" name="nombre" value = "<?php echo $nombre ?>" required></td>
					</div>
			  </div>
			  <div class="form-group">
				<label class="col-sm-3 control-label control-label">Ruta</label>
				<div class="col-sm-6">	
				<td><input class="form-control input-sm" type="text" name="ruta" value = "<?php echo $ruta ?>" required></td>
					</div>
			  </div>  
			  <div class="form-group">
				<label for="estado" class="col-sm-3 control-label">Icono</label>
				<div class="col-sm-6">
				<td><input class="form-control input-sm"type="text" name="icono" value = "<?php echo $icono ?>" ></td>
			</div>
			  </div>
			  <div class="form-group">
				<label for="estado" class="col-sm-3 control-label">Forma</label>
				<div class="col-sm-6">
				<button class="btn btn-default" disabled="true"><i class="<?php echo $icono ?>"></i></button>
				</div>
			  </div>
			
							 <div class="modal-footer">
							 <a href="opciones_de_modulos.php?op=versubmod"><button type="button" class="btn btn-default" data-dismiss="modal">Ver listado</button></a>
							 <button type="submit" class="btn btn-primary" name="Guardarsubmod" value="Guardar">Actualizar</button>
							</div>
					</form>
<?php
}


function actualiza_sub_modulo(){
	$conexion = conenta_login();
	$ruta = $_POST['ruta'];
	$icono = $_POST['icono'];
	$nombre_actual = $_POST['nombre_actual'];
    $nombre_nuevo = $_POST['nombre'];
    
	$sql = "UPDATE sub_modulos SET nombre = '$nombre_nuevo', ruta='$ruta', icono='$icono' WHERE nombre='$nombre_actual';";

	if(mysqli_query($conexion,$sql)){
		?>
		  <div class="alert alert-success alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Bien hecho!</strong> Se actualizó correctamente! </div>
		  <?php
	}else{
	?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Algo pasa!</strong> Error al actualizar el registro! </div>
		  </div>
		  </div>
		  </div>
		  </div>
		  <?php
	}

}

function editar_modulo(){
	$id = $_GET['id'];
	$nombre_actual = $_GET['nom'];
	$conexion = conenta_login();
		$sql = "SELECT * FROM modulos WHERE id=$id;";
		$res = mysqli_query($conexion,$sql);
		$modulo = mysqli_fetch_assoc($res);
		$nombre = $modulo['nombre'];
		$icono = $modulo['icono'];
		
?>
<div class="col-md-6 col-md-offset-3">
<div class="container-center">
		<div class="panel panel-info" >
				<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-pencil'></i> Modificar información del módulo</h4>
				</div>	

	<div class="panel-body">
		
	<form class="form-horizontal" method="POST" name="edita_modulo">
			  
			  <div class="form-group">
				<label class="col-sm-3 control-label control-label">Nombre</label>
				<div class="col-sm-6">	
				<input type="hidden" name="nombre_actual" value = "<?php echo $nombre_actual ?>">
				<td><input class="form-control input-sm" type="text" name="nombre" value = "<?php echo $nombre ?>" required></td>
					</div>
			  </div>
			  <div class="form-group">
				<label for="estado" class="col-sm-3 control-label">Icono</label>
				<div class="col-sm-6">
				<td><input class="form-control input-sm"type="text" name="icono" value = "<?php echo $icono ?>" ></td>
			</div>
			  </div>
			  <div class="form-group">
				<label for="estado" class="col-sm-3 control-label">Forma</label>
				<div class="col-sm-6">
				<button class="btn btn-default" disabled="true"><i class="<?php echo $icono ?>"></i></button>
				</div>
			  </div>
			
							 <div class="modal-footer">
							 <a href="opciones_de_modulos.php?op=vermo"><button type="button" class="btn btn-default" data-dismiss="modal">Ver listado</button></a>
							 <button type="submit" class="btn btn-primary" name="Guardarmod" value="Guardar">Actualizar</button>
							</div>
					</form>
<?php
}

function actualizar_modulo(){
	$conexion = conenta_login();
	$nombre_actual = $_POST['nombre_actual'];
	$nombre = $_POST['nombre'];
	$icono = $_POST['icono'];
	$sql = "UPDATE modulos SET nombre='$nombre', icono = '$icono' WHERE nombre='$nombre_actual';";
	if(mysqli_query($conexion,$sql)){
	?>
		  <div class="alert alert-success alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Bien hecho!</strong> Se actualizó correctamente! </div>
		  <?php
	}else{
	?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Algo pasa!</strong> Error al actualizar el registro! </div>
		  </div>
		  </div>
		  </div>
		  </div>
		  <?php
	}

}

if(isset($_POST['Guardarmod'])){
echo actualizar_modulo();
}

if (isset($_GET['op']) && $_GET['op'] == "editmod"){
echo editar_modulo();
}

if (isset($_GET['op']) && $_GET['op'] == "vermo"){
echo muestra_modulos();
}

if(isset($_POST['Guardarsubmod'])){
echo actualiza_sub_modulo();
}

if (isset($_GET['op']) && $_GET['op'] == "modifica_ruta"){
echo editar_sub_modulo();
}

if (isset($_GET['op']) && $_GET['op'] == "versubmod"){
echo muestra_sub_modulos();
}

if (isset($_GET['op']) && $_GET['op'] == "delmod"){
echo eliminar_modulo($_GET['id']);
echo muestra_modulos();
}

if (isset($_GET['op']) && $_GET['op'] == "delsubmod"){
echo eliminar_sub_modulo($_GET['id']);
echo muestra_sub_modulos();
}


}else{ 
header('Location: ../includes/logout.php');
exit;
}
?>
<?php //include("../pie.php");?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>
