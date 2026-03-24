<?php
include_once(__DIR__ . "/../conexiones/conectalogin.php");

function recuperar_password()
{
?>
	<div class="modal fade" id="modalRecuperarPass" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-md" role="document">
			<div class="modal-content">
				<div class="modal-header bg-info text-white">
					<button type="button" class="close" data-dismiss="modal" title="Cerrar modal Recuperar Password"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="titleModal"></h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" id="formRecuperarPass" name="formRecuperarPass">
						<div class="panel-heading">
							<div class="form-group">
								<div class="col-sm-12">
									<div class="input-group">
										<span class="input-group-addon"><b>Email *</b></span>
										<input type="text" class="form-control" id="txtEmailReset" name="txtEmailReset">
									</div>
								</div>
							</div>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-secundary" data-dismiss="modal" title="Cerrar modal recuperar password"><i class="fa fa-window-close"></i> Cerrar</button>
							<button type="submit" id="btnActionForm" class="btn btn-primary" title=""><span id="btnText"></span></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
<?php
}



function mostrar_menu_empresa($id_usuario, $id_empresa, $ruc_empresa)
{
?>
	<form id="formUnaEmpresa" method="POST" action="/sistema/set_empresa.php">
		<input type="text" name="id_usuario" value=<?php echo $id_usuario; ?>>
		<input type="text" name="id_empresa" value=<?php echo $id_empresa; ?>>
		<input type="text" name="ruc_empresa" value=<?php echo $ruc_empresa; ?>>
	</form>
<?php
	echo "<script>
		const formulario = document.getElementById('formUnaEmpresa');
			formulario.submit();
	</script>";
}

function contar_empresas_asignadas($id_usuario)
{
	$conexion = conenta_login();
	$datos_usuario = mysqli_query($conexion, "SELECT emp.id as id_empresa, emp.ruc as ruc_empresa, count(*) AS numrows  
	FROM empresa_asignada as emp_asi INNER JOIN empresas as emp ON emp.id=emp_asi.id_empresa INNER JOIN usuarios as usu ON usu.id=emp_asi.id_usuario
	 WHERE emp_asi.id_usuario='" . $id_usuario . "' AND emp.estado='1' AND usu.estado='1'");
	$row = mysqli_fetch_array($datos_usuario);
	if (isset($row['numrows'])) {
		$datos = array('id_empresa' => $row['id_empresa'], 'ruc_empresa' => $row['ruc_empresa'], 'numrows' => $row['numrows']);
	} else {
		$datos = array('numrows' => 0);
	}
	return $datos;
	mysqli_close($conexion);
}

function get_primera_empresa_asignada($id_usuario)
{
	$conexion = conenta_login();
	$res = mysqli_query($conexion, "SELECT emp.id as id_empresa, emp.ruc as ruc_empresa 
		FROM empresa_asignada emp_asi INNER JOIN empresas emp ON emp.id=emp_asi.id_empresa INNER JOIN usuarios u ON u.id=emp_asi.id_usuario 
		WHERE emp_asi.id_usuario='" . (int)$id_usuario . "' AND emp.estado='1' AND u.estado='1' 
		ORDER BY emp.nombre_comercial ASC LIMIT 1");
	$row = mysqli_fetch_assoc($res);
	return $row ? array('id_empresa' => $row['id_empresa'], 'ruc_empresa' => $row['ruc_empresa']) : null;
}

function get_form_sistema()
{
?>
	<div class="container-fluid login-container">
		<div class="login-box">
			<!-- 			<h3 class="text-center">Inicio de Sesión</h3> -->
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="panel-title">
						<h4><a href="https://www.camagare.com.ec">CaMaGaRe.com.ec</a></h4>
					</div>
				</div>
			</div>
			<form method="POST" autocomplete="off">
				<div class="mb-3">
					<label for="cedula" class="form-label">Cédula</label>
					<input type="text" class="form-control" name="cedula" placeholder="Ingresa tu cédula">
				</div>
				<div class="mb-3">
					<label for="password" class="form-label">Contraseña</label>
					<input type="password" class="form-control" name="password" placeholder="Ingresa tu contraseña">
				</div>
				<div class="mb-3">
					<button type="submit" name="login" class="btn btn-primary w-100">iniciar sesión</button>
				</div>
				<div class="mb-2">
					<button class="btn btn-default input-sm w-100" id="loader_rc" onclick="recuperarPassword();">Recuperar contraseña</button>
				</div>
				<div id="resultados_recuperar_clave"></div>
			</form>
		</div>
	</div>
<?php
}

function get_form_consignaciones()
{
?>
	<div class="container-fluid login-container">
		<div class="login-box">
			<h2 class="text-center">Consignaciones</h2>
			<div class="panel panel-info">
				<div class="panel-heading">
					<div class="panel-title text-center">
						<h4><a href="https://www.camagare.com">CaMaGaRe.com.ec</a></h4>
					</div>
				</div>
			</div>
			<form method="POST">
				<div class="mb-3">
					<label for="password" class="form-label">Código</label>
					<input type="password" class="form-control" name="codigo" placeholder="Ingresa tu código">
				</div>
				<button type="submit" name="loginConsignaciones" class="btn btn-primary w-100">Ingresar</button>
			</form>
		</div>
	</div>
<?php
}
//para validar el inicio de sesion
// Soporta contraseña en MD5 (cambiar_clave) o bcrypt (password_hash)
function valida_login($cedula, $passwordPlain)
{
	$conexion = conenta_login();
	$cedula = mysqli_real_escape_string($conexion, trim($cedula));
	$datos_usuario = mysqli_query($conexion, "SELECT * FROM usuarios WHERE cedula = '" . $cedula . "' AND estado = '1'");
	if ($user = mysqli_fetch_array($datos_usuario)) {
		$stored = $user['password'] ?? '';
		$passMd5 = md5($passwordPlain);
		// MD5 (32 caracteres hex) - formato usado por cambiar_clave
		if ($passMd5 === $stored) {
			return $user;
		}
		// bcrypt (password_hash)
		if (function_exists('password_verify') && password_verify($passwordPlain, $stored)) {
			return $user;
		}
	}
	return false;
}

//para validar los logisticos
function valida_logistico($codigo)
{
	$conexion = conenta_login();
	$datos_usuario = mysqli_query($conexion, "SELECT * FROM responsable_traslado WHERE codigo = '" . $codigo . "' ");
	if ($user = mysqli_fetch_array($datos_usuario)) {
		return $user;
	} else {
		return false;
	}
	mysqli_close($conexion);
}

//para desplegar el menu
function display_menu($nivel)
{
	$conexion = conenta_login();
	$nivel = mysqli_real_escape_string($conexion, $nivel);
	$sql = "SELECT * FROM menu WHERE nivel BETWEEN 0 AND '" . $nivel . "' and estado=1 order by etiqueta asc;";
	$datos_opciones_menu = @$conexion->query($sql);
	if ($datos_opciones_menu === false) {
		// Tabla menu no existe: redirigir a empresa
		header('Location: /sistema/empresa.php');
		exit;
	}

	// de aqui para abajo es para hacer el encabezado del menu donde estan las empresas y demas opciones de cada usuario
?>
	<div class="col-md-12">
		<div class="panel panel-primary">
			<div class="panel-heading">
				<div class="btn-group pull-left">
					<h5><span class="glyphicon glyphicon-user"></span> <?php echo ucwords(strtolower($_SESSION['nombre'])) ?> </h5>
				</div>
				<h4 class="text-center"><?php echo actual_date(); ?></h4>
			</div>

			<div class="panel-body">
				<div class="container-fluid">
					<div class="row">
						<div class="col-md-4">
							<?php include(__DIR__ . "/../app/Views/partials/avisos_tareas_actividades.php"); ?>
						</div>

						<!--para mostrar el buscador de empresas asignadas -->

						<?php
						$con = conenta_login();
						$id_usuario = $_SESSION['id_usuario'];
						$datos_asignadas = mysqli_query($con, "SELECT * FROM empresa_asignada emp_asi INNER JOIN empresas emp ON emp_asi.id_empresa=emp.id WHERE emp_asi.id_usuario = '" . $id_usuario . "' and emp.estado='1'");
						$count = mysqli_num_rows($datos_asignadas);
						if ($count > 10) {
						?>
							<div class="col-md-4">
								<div class="panel panel-info">
									<div class="panel-heading">
										<h4><i class='glyphicon glyphicon-briefcase'></i> Empresas <span class="pull-right"><span id="loader"></span></span></h4>
									</div>
									<ul class="list-group">
										<div class="col-md-12">
											<form class="form-horizontal" role="form">
												<input type="text" class="form-control" id="q" placeholder="Buscar empresas" onkeyup='load(1);'>
											</form>
										</div>
										<div id="resultados"></div><!-- Carga los datos ajax  de ajax/buscar_empresa_asignada.php-->
										<div class='outer_div'></div><!-- Carga los datos ajax -->
									</ul>
								</div>
							</div>
						<?php
						} else {
						?>
							<div class="col-md-4">
								<div class="panel panel-info">
									<div class="panel-heading">
										<h4><i class='glyphicon glyphicon-briefcase'></i> Empresas <span class="pull-right"><span id="loader"></span></span></h4>
									</div>
									<ul class="list-group">
										<form class="form-horizontal" role="form">
											<input type="hidden" class="form-control" id="q" placeholder="Empresas" onkeyup='load(1);'>
										</form>
										<div id="resultados"></div><!-- Carga los datos ajax  de ajax/buscar_empresa_asignada.php-->
										<div class='outer_div'></div><!-- Carga los datos ajax -->
									</ul>
								</div>
							</div>
							<br>
						<?php
						}
						mysqli_close($con);
						?>
						<!-- opciones de cada usuario en el menu principal-->
						<div class="col-md-4">
							<div class="panel panel-success">
								<div class="panel-heading">
									<h4><i class='glyphicon glyphicon-wrench'></i> Opciones</h4>
								</div>
								<ul class="list-group">
									<?php
									while ($item = mysqli_fetch_array($datos_opciones_menu)) {
									?>
										<a href="<?php echo $item['ruta']; ?> " class="list-group-item list-group-item-success"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> <?php echo $item['etiqueta']; ?> </a>
									<?php
									}
									?>
									<a href="includes/logout.php" class="list-group-item list-group-item-danger"><span class="glyphicon glyphicon-off"></span> Cerrar sesión </a>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
}

function actual_date()
{
	ini_set('date.timezone', 'America/Guayaquil');
	$week_days = array("Domingo", "Lunes", "Martes", "Miercoles", "Jueves", "Viernes", "Sabado");
	$months = array("", "Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
	$year_now = date("Y");
	$month_now = date("n");
	$day_now = date("j");
	$week_day_now = date("w");
	$hora = date("H:i:s", time());
	$date = $week_days[$week_day_now] . ", " . $day_now . " de " . $months[$month_now] . " del " . $year_now . " Hora: " . $hora;
	return $date;
}


?>