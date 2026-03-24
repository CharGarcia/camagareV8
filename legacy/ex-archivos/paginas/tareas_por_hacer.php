<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
	<title>Tareas</title>
	<?php
	session_start();
	include("../head.php");
	ini_set('date.timezone', 'America/Guayaquil');
	?>
</head>

<body>
	<meta charset="utf-8">
	<?php
	include("../conexiones/conectalogin.php");
	if ($_SESSION['nivel'] >= 1) {
		$titulo_info = "Tareas por hacer";
		$conexion = conenta_login();
		$id_usuario = $_SESSION['id_usuario'];
	?>
		<?php
		include("../modal/tareas_por_hacer.php");
		include("../navbar_confi.php");
		?>
		<div class="container-fluid">
			<div class="panel panel-info">
				<div class="panel-heading">

					<div class="btn-group pull-right">
						<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#modalTareas" onclick="crear_tarea();"><span class="glyphicon glyphicon-plus"></span> Nueva Tarea</button>
					</div>
					<h4><i class='glyphicon glyphicon-search'></i> Tareas por hacer <span id="loader"></span></h4>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
						<div class="form-group row">
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Estado</b></span>
									<select class="form-control input-sm" id="estado" name="estado" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<option value="1">Por realizar (En general)</option>
										<option value="3">Por realizar (Hasta hoy)</option>
										<option value="5">Por realizar (Dentro de 5 días)</option>
										<option value="4">Por realizar (Dentro de 10 días)</option>
										<option value="2">Realizadas</option>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Año</b></span>
									<select class="form-control input-sm" id="anio_tarea" name="anio_tarea" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(year(tar.fecha_a_realizar)) as anio FROM tareas_por_hacer as tar INNER JOIN obligaciones_empresas as obli ON obli.id=tar.id_obligacion 
											INNER JOIN empresas as emp ON emp.id=tar.id_empresa INNER JOIN usuarios_tareas as usu_tar ON usu_tar.id_tarea=tar.id WHERE usu_tar.id_usuario='" . $id_usuario . "' order by year(tar.fecha_a_realizar) desc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['anio'] ?>"><?php echo strtoupper($row['anio']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-2">
								<div class="input-group">
									<span class="input-group-addon"><b>Mes</b></span>
									<select class="form-control input-sm" id="mes_tarea" name="mes_tarea" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct(DATE_FORMAT(tar.fecha_a_realizar, '%m')) as mes FROM tareas_por_hacer as tar INNER JOIN obligaciones_empresas as obli ON obli.id=tar.id_obligacion 
											INNER JOIN empresas as emp ON emp.id=tar.id_empresa INNER JOIN usuarios_tareas as usu_tar ON usu_tar.id_tarea=tar.id WHERE usu_tar.id_usuario='" . $id_usuario . "' order by month(tar.fecha_a_realizar) desc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['mes'] ?>"><?php echo strtoupper($row['mes']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-4">
								<div class="input-group">
									<span class="input-group-addon"><b>Tarea</b></span>
									<select class="form-control input-sm" id="tarea" name="tarea" onchange='load(1);'>
										<option value="" selected>Todos</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct tar.id_obligacion as id,obli.descripcion as descripcion FROM tareas_por_hacer as tar inner join obligaciones_empresas
										as obli ON obli.id=tar.id_obligacion where tar.id_usuario ='" . $id_usuario . "' order by obli.descripcion asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['id'] ?>"><?php echo strtoupper($row['descripcion']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Empresa</b></span>
									<select class="form-control input-sm" id="empresa" name="empresa" onchange='load(1);'>
										<option value="" selected>Todas</option>
										<?php
										$tipo = mysqli_query($con, "SELECT distinct tar.id_empresa as id_empresa, emp.nombre as empresa 
										FROM tareas_por_hacer as tar INNER JOIN empresas as emp 
										ON emp.id=tar.id_empresa WHERE tar.id_usuario='" . $id_usuario . "' order by emp.nombre asc");
										while ($row = mysqli_fetch_array($tipo)) {
										?>
											<option value="<?php echo $row['id_empresa'] ?>"><?php echo strtoupper($row['empresa']) ?></option>
										<?php
										}
										?>
									</select>
								</div>
							</div>
							<div class="col-md-5">
								<div class="input-group">
									<span class="input-group-addon"><b>Buscar:</b></span>
									<input type="text" class="form-control input-sm" id="q" placeholder="Empresa, obligación, detalle" onkeyup='load(1);'>
									<span class="input-group-btn">
										<button type="button" class="btn btn-default input-sm" onclick='load(1);'><span class="glyphicon glyphicon-search"></span></button>
									</span>
								</div>
							</div>
						</div>

					</form>
					<div id="resultados"></div><!-- Carga los datos ajax -->
					<div class='outer_div'></div><!-- Carga los datos ajax -->
				</div>
			</div>
		</div>
	<?php
	} else {
		header('Location: ../includes/logout.php');
		exit;
	}
	?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
</body>

</html>
<script>
	$(document).ready(function() {
		load(1);
	});

	function crear_tarea() {
		document.querySelector("#titleModalTareas").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva Tarea";
		document.querySelector("#guardar_tarea").reset();
		document.querySelector("#idTarea").value = "";
		document.querySelector("#btnActionFormTarea").classList.replace("btn-info", "btn-primary");
		document.querySelector("#btnTextTarea").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
		document.querySelector('#btnActionFormTarea').title = "Guardar tarea";
	}


	function editar_tarea(id) {
		document.querySelector('#titleModalTareas').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar tarea";
		document.querySelector("#guardar_tarea").reset();
		document.querySelector("#idTarea").value = id;
		document.querySelector('#btnActionFormTarea').classList.replace("btn-primary", "btn-info");
		document.querySelector("#btnTextTarea").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";

		var id_empresa = $("#id_empresa" + id).val();
		var id_obligacion = $("#id_obligacion" + id).val();
		var fecha = $("#fecha" + id).val();
		var detalle = $("#detalle" + id).val();
		var repetir = $("#repetir" + id).val();
		var status = $("#status" + id).val();

		$("#id_empresa").val(id_empresa);
		$("#id_obligacion").val(id_obligacion);
		$("#fecha_realizar").val(fecha);
		$("#observacion").val(detalle);
		$("#repetir").val(repetir);
		$("#status").val(status);
	}

	function load(page) {
		var q = $("#q").val();
		var estado = $("#estado").val();
		var anio_tarea = $("#anio_tarea").val();
		var mes_tarea = $("#mes_tarea").val();
		var tarea = $("#tarea").val();
		var empresa = $("#empresa").val();

		$("#loader").fadeIn('slow');
		$.ajax({
			url: '../ajax/tareas_por_hacer.php?action=buscar_tareas&page=' + page +
				'&q=' + encodeURIComponent(q) + '&estado=' + encodeURIComponent(estado) +
				'&anio_tarea=' + encodeURIComponent(anio_tarea) + '&mes_tarea=' + encodeURIComponent(mes_tarea) +
				'&tarea=' + encodeURIComponent(tarea) + '&empresa=' + encodeURIComponent(empresa),
			beforeSend: function(objeto) {
				$('#loader').html('Cargando...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		});

	};

	function cambiar_estado(idTarea, id_empresa, id_obligacion, fecha_realizar, repetir, observacion, estado) {
		if (confirm("Desea cambiar el estado de la tarea?")) {
			$.ajax({
				type: "POST",
				url: "../ajax/tareas_por_hacer.php?action=guardar_tarea",
				data: "idTarea=" + idTarea + "&id_empresa=" + id_empresa +
					"&id_obligacion=" + id_obligacion + "&fecha_realizar=" + fecha_realizar +
					"&repetir=" + repetir + "&observacion=" + observacion + "&status=" + estado,
				beforeSend: function(objeto) {
					$("#loader").html("Actualizando...");
				},
				success: function(datos) {
					$("#loader").html(datos);
					load(1);
				}
			});
		};
	}


	function eliminar_tarea(id_tarea, estado) {
		if (confirm("Realmente desea eliminar la tarea?")) {
			$.ajax({
				type: "POST",
				url: "../ajax/tareas_por_hacer.php?action=eliminar_tarea",
				data: "id_tarea=" + id_tarea + "&estado=" + estado,
				beforeSend: function(objeto) {
					$("#loader").html("Mensaje: Cargando...");
				},
				success: function(datos) {
					$("#loader").html(datos);
					load(1);
				}
			});
		};

	};

	//para mostrar el modal de los usuarios que estan asignados esa tarea
	function agregar_usuario(id_tarea, id_empresa, empresa) {
		document.querySelector("#formEmpresaAsignada").reset();
		$("#idEmpresaAsignada").val(id_empresa);
		$("#nombre_empresa_asignada").val(empresa);

		$.ajax({
			type: "GET",
			url: '../ajax/tareas_por_hacer.php?action=asignaciones',
			data: "id_empresa=" + id_empresa + "&id_tarea=" + id_tarea,
			beforeSend: function(objeto) {
				$("#resultados_asignaciones").html("Cargardo...");
			},
			success: function(datos) {
				$("#resultados_asignaciones").html('');
				$(".outer_div_asiganciones").html(datos).fadeIn('slow');
			}
		});

	}

	function asignar_tarea(id_tarea, id_usuario, id_empresa, empresa) {
		if (confirm("Desea asignar la tarea a este usuario?")) {
			$.ajax({
				type: "GET",
				url: '../ajax/tareas_por_hacer.php?action=asignar_tarea',
				data: "id_tarea=" + id_tarea + "&id_usuario=" + id_usuario,
				beforeSend: function(objeto) {
					$("#resultados_modal_tarea_asignada").html("Agregando...");
				},
				success: function(datos) {
					$("#resultados_modal_tarea_asignada").html(datos);
					agregar_usuario(id_tarea, id_empresa, empresa);
				}
			});
		}
	}

	function quitar_tarea(id_tarea, id_usuario, id_empresa, empresa) {
		if (confirm("Desea quitar la tarea a este usuario?")) {
			$.ajax({
				type: "GET",
				url: '../ajax/tareas_por_hacer.php?action=quitar_tarea',
				data: "id_tarea=" + id_tarea + "&id_usuario=" + id_usuario,
				beforeSend: function(objeto) {
					$("#resultados_modal_tarea_asignada").html("Eliminando...");
				},
				success: function(datos) {
					$("#resultados_modal_tarea_asignada").html(datos);
					agregar_usuario(id_tarea, id_empresa, empresa);
				}
			});
		}
	}
</script>