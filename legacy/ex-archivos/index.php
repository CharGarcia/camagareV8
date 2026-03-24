<!DOCTYPE html>
<html lang="es">

<head>
	<meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
	<title>CaMaGaRe</title>
	<link rel="shortcut icon" type="image/png" href="../sistema/image/logofinal.png" />
	<?php include("head.php");
	?>
	<script src="js/notify.js"></script>

	<style>
		/* Oculta el formulario usando CSS */
		#formUnaEmpresa {
			display: none;
		}

		.login-container {
			display: flex;
			justify-content: center;
			align-items: center;
			height: 100vh;
		}

		.login-box {
			width: 100%;
			max-width: 400px;
			padding: 15px;
			border: 1px solid #ddd;
			border-radius: 4px;
			background-color: #fff;
		}
	</style>
</head>

<body style="background-color: hsla(146, 30%, 61%, 0.67);">
	<?php
	include("includes/login.php");
	session_start();
	if (isset($_POST['login']) && isset($_POST['password']) && isset($_POST['cedula'])) {
		$cedula = trim($_POST['cedula']);
		$passwordPlain = $_POST['password'];
		$user = valida_login($cedula, $passwordPlain);
		if ($user !== false) {
			ini_set('date.timezone', 'America/Guayaquil');
			$_SESSION['nivel'] = $user['nivel'];
			$_SESSION['nombre'] = $user['nombre'];
			$_SESSION['id_usuario'] = $user['id'];
			$empresas_asignadas = contar_empresas_asignadas($_SESSION['id_usuario']);
			//comparar si el usuario tiene una empresa asignada para entrar de una al menu empresas
			if ($empresas_asignadas['numrows'] == 0) {
				echo get_form_sistema();
				echo "<script>$.notify('El usuario " . $_SESSION['nombre'] . " no tiene empresas asignadas','error');</script>";
			} else if ($empresas_asignadas['numrows'] >= 1) {
				$emp = ($empresas_asignadas['numrows'] == 1)
					? array('id_empresa' => $empresas_asignadas['id_empresa'], 'ruc_empresa' => $empresas_asignadas['ruc_empresa'])
					: get_primera_empresa_asignada($_SESSION['id_usuario']);
				if ($emp) {
					$_SESSION['id_empresa'] = $emp['id_empresa'];
					$_SESSION['ruc_empresa'] = $emp['ruc_empresa'];
					header('Location: /sistema/empresa');
					exit;
				}
			}
		} else {
			echo get_form_sistema();
			echo "<script>$.notify('Usuario o contraseña incorrectos, vuelva a intentarlo','error');</script>";
		}
	} else if (isset($_GET['menu']) && isset($_SESSION['nivel']) && isset($_SESSION['id_usuario'])) {
		if (!isset($_SESSION['id_empresa'])) {
			$emp = get_primera_empresa_asignada($_SESSION['id_usuario']);
			if ($emp) {
				$_SESSION['id_empresa'] = $emp['id_empresa'];
				$_SESSION['ruc_empresa'] = $emp['ruc_empresa'];
			}
		}
		if (isset($_SESSION['id_empresa'])) {
			header('Location: /sistema/empresa');
			exit;
		}
	}
	echo get_form_sistema();
	?>
	<?php //include("pie.php"); 
	?>
	<!-- <script src="js/md5.js"></script> -->
</body>
<!-- <script>
	function cifrar() {
		var input_pass = MD5($("#password").val());
		$("#password").val(input_pass);
	}
</script> -->

</html>
<script>
	//buscador de empresas
	$(document).ready(function() {
		load(1);
	});

	function load(page) {
		var q = $("#q").val();
		$("#loader").fadeIn('slow');
		$.ajax({
			url: './ajax/buscar_empresa_asignada.php?action=buscar_empresa_asignada&page=' + page + '&q=' + q,
			beforeSend: function(objeto) {
				$('#loader').html('Cargando...');
			},
			success: function(data) {
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
			}
		})
	}


	/*
	function recuperarPassword() {
		// Pedir el correo electrónico
		const correo = prompt("Por favor, ingrese su correo electrónico:");

		// Validar si el usuario canceló o dejó el campo vacío
		if (correo === null || correo.trim() === "") {
			alert("No ingresaste ningún correo.");
			return;
		}
		// Expresión regular para validar el correo electrónico
		const regexCorreo = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,7}$/;
		// Validar el formato del correo
		if (regexCorreo.test(correo)) {
			$.ajax({
				url: './ajax/recuperarPassword.php?action=recuperar_password&correo=' + encodeURIComponent(correo),
				method: 'GET',
				beforeSend: function() {
					$('#loader_rc').html('Enviando...');
				},
				success: function(response) {
					try {
						const data = JSON.parse(response);
						if (data.status === 'success') {
							enviar_correo_recuperar_clave(data.id_user, data.nombre, encodeURIComponent(correo));
							alert(data.message);
						} else {
							alert("Error: " + data.message);
						}
					} catch (error) {
						alert("Ocurrió un error procesando la respuesta del servidor.");
					}
				},
				error: function(xhr, status, error) {
					alert(`Error en la solicitud: ${status} - ${error}`);
				},
				complete: function() {
					$('#loader_rc').html('');
				}
			});
		} else {
			alert("El correo ingresado no es válido. Por favor, intente nuevamente.");
		}
	}

*/

	async function recuperarPassword() {
		const regexCorreo = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,7}$/;
		let correo;

		// Bucle para pedir correo una y otra vez
		while (true) {
			correo = prompt("Por favor, ingrese su correo electrónico:");
			if (correo === null) {
				alert("Operación cancelada.");
				return;
			}
			correo = correo.trim();
			if (!correo) {
				alert("No ingresaste ningún correo.");
				continue;
			}
			if (!regexCorreo.test(correo)) {
				alert("El correo ingresado no es válido. Por favor, intente nuevamente.");
				continue;
			}
			break; // correo válido
		}

		// Mostrar loader
		$('#loader_rc').text('Enviando...');

		try {
			const response = await $.ajax({
				url: './ajax/recuperarPassword.php',
				method: 'POST', // mejor usar POST
				data: {
					action: 'recuperar_password',
					correo: correo // jQuery se encarga de codificar
				}
			});

			const data = JSON.parse(response);
			if (data.status === 'success') {
				enviar_correo_recuperar_clave(data.id_user, data.nombre, correo);
				alert(data.message);
				return;
			}

			// Status distinto => mostrar error y reiniciar el bucle
			alert("Error: " + data.message);
			return recuperarPassword();

		} catch (err) {
			alert("Ocurrió un error: " + err.message);
			return recuperarPassword();

		} finally {
			$('#loader_rc').text('');
		}
	}


	function enviar_correo_recuperar_clave(id, nombre, correo) {
		$.ajax({
			url: './ajax/recuperarPassword.php',
			method: 'GET',
			data: {
				action: 'enviar_correo_recuperar_clave',
				id_user: id,
				nombre: nombre,
				correo: correo // aquí jQuery hace automáticamente el encodeURIComponent
			},
			beforeSend: function() {
				$('#loader_rc').html('Enviando...');
			},
			success: function(data) {
				$('#loader_rc').html('');
				// (aquí podrías mostrar un mensaje de “correo enviado” si lo deseas)
			},
			error: function(xhr, status, error) {
				$('#loader_rc').html('');
				alert('Error enviando correo: ' + error);
			}
		});
	}
</script>