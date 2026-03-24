<?php
session_start();
if (isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])) {
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

?>
	<!DOCTYPE html>
	<html lang="es">

	<head>
		<title>Novedades nómina</title>
		<?php include("../paginas/menu_de_empresas.php"); ?>
	</head>
	<body>
		<div class="container-fluid">
				<div class="panel panel-info">
					<div class="panel-heading">
						<div class="btn-group pull-right">
						<?php
							$con = conenta_login();
							if(getPermisos($con, $id_usuario, $ruc_empresa, 'novedades')['w']==1){
							?>
							<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#modalNovedades" onclick="openModal();"><span class="glyphicon glyphicon-plus"></span> Nueva</button>
							<?php
							}
							?>
						</div>
						<h4><i class='glyphicon glyphicon-search'></i> Novedades nómina</h4>
					</div>
					<div class="panel-body">
						<?php
						include("../modal/novedades.php");
						?>
						<form class="form-horizontal" method="POST">
							<div class="form-group row">
								<label for="q" class="col-md-1 control-label">Buscar:</label>
								<div class="col-md-6">
									<input type="hidden" id="ordenado" value="mes_ano">
									<input type="hidden" id="por" value="asc">
									<div class="input-group">
										<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
										<span class="input-group-btn">
											<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search"></span> Buscar</button>
										</span>
									</div>
								</div>
								<span id="loader"></span>
							</div>
						</form>
						<div id="resultados"></div><!-- Carga los datos ajax -->
						<div class="outer_div"></div><!-- Carga los datos ajax -->
					</div>
				</div>
		</div>
	<?php

} else {
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
	?>
	<script src="../js/notify.js"></script>
	</body>

	</html>
	<script>
		$(document).ready(function() {
			window.addEventListener("keypress", function(event) {
				if (event.keyCode == 13) {
					event.preventDefault();
				}
			}, false);
			load(1);
		});

		function openModal() {
			document.querySelector("#titleModalNovedades").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva novedad en nómina";
			document.querySelector("#formNovedades").reset();
			document.querySelector("#idNovedad").value = "";
			document.querySelector("#btnActionFormNovedad").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextNovedad").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormNovedad').title = "Guardar nueva novedad de nómina";
		}

		function load(page) {
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			var por = $("#por").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url: '../ajax/novedades.php?action=buscar_novedades&page=' + page + '&q=' + q + "&ordenado=" + ordenado + "&por=" + por,
				beforeSend: function(objeto) {
					$('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
				},
				success: function(data) {
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
		}

		function ordenar(ordenado) {
			$("#ordenado").val(ordenado);
			var por = $("#por").val();
			var q = $("#q").val();
			var ordenado = $("#ordenado").val();
			$("#loader").fadeIn('slow');
			var value_por = document.getElementById('por').value;
			if (value_por == "asc") {
				$("#por").val("desc");
			}
			if (value_por == "desc") {
				$("#por").val("asc");
			}
			load(1);
		}

		function editar_novedad(id) {
			document.querySelector('#titleModalNovedades').innerHTML = "<i class='glyphicon glyphicon-edit'></i> Actualizar novedad";
			document.querySelector('#btnActionFormNovedad').classList.replace("btn-primary", "btn-info");
			document.querySelector('#btnActionFormNovedad').title = "Actualizar Novedad";
			document.querySelector('#btnTextNovedad').innerHTML = "Actualizar";
			document.querySelector("#formNovedades").reset();
            document.querySelector("#idNovedad").value = id;
			
            let request = (window.XMLHttpRequest) ?
				new XMLHttpRequest() :
				new ActiveXObject('Microsoft.XMLHTTP');
			let ajaxUrl = '../ajax/novedades.php?action=datos_editar_novedad&id_novedad=' + id;
			request.open("GET", ajaxUrl, true);
			request.send();
			request.onreadystatechange = function() {
				if (request.readyState == 4 && request.status == 200) {
					let objData = JSON.parse(request.responseText);
					if (objData.status) {
						let objNovedades = objData.data;
						document.querySelector("#idEmpleado").value = objNovedades.id_empleado;
						document.querySelector("#datalistEmpleados").value = objNovedades.empleado;
						document.querySelector("#datalistNovedad").value = objNovedades.id_novedad;
						document.querySelector("#datalistMotivo").value = objNovedades.motivo_salida;
						document.querySelector("#txtFechaNovedad").value = objNovedades.fecha_novedad;
						document.querySelector("#datalistMes").value = objNovedades.mes_afecta;
						document.querySelector("#datalistAno").value = objNovedades.ano_afecta;
						document.querySelector("#txtValor").value = objNovedades.valor;
						document.querySelector("#datalistAplicaEn").value = objNovedades.aplica_en;
                        document.querySelector("#aportaIess").value = objNovedades.iess;
						document.querySelector("#txtDetalle").value = objNovedades.detalle;
                        let tipo_novedad = objNovedades.id_novedad;

                        if (tipo_novedad == 14){
                        document.getElementById("titulo_motivo").style.display="";
                        document.getElementById("datalistMotivo").style.display="";
                        document.getElementById("txtValor").readOnly = true;
                        document.getElementById("txtDetalle").readOnly = true;
                        document.querySelector("#datalistMotivo").value = objNovedad.id_salida
                        }else{
                        document.getElementById("titulo_motivo").style.display="none";
                        document.getElementById("datalistMotivo").style.display="none";
                        document.getElementById("txtValor").readOnly = false;
                        document.getElementById("txtDetalle").readOnly = false;
                        }
                    
                        if (tipo_novedad == 1 || tipo_novedad == 4 || tipo_novedad == 5 || tipo_novedad == 6 ){
                        document.getElementById("aportaIess").disabled = false;
                        }else{
                            document.getElementById("aportaIess").disabled = true;
                        }

                        if (tipo_novedad == 10){
                        document.getElementById("datalistAplicaEn").value = "R";
                        }
					} else {
						$.notify(objData.msg, "error");
					}
				}
				return false;
			}
		}

		function eliminar_novedad(id) {
			var q = $("#q").val();
			if (confirm("Realmente desea eliminar la novedad?")) {
				$.ajax({
					type: "POST",
					url: "../ajax/novedades.php?action=eliminar_novedad",
					data: "id_novedad=" + id,
					"q": q,
					beforeSend: function(objeto) {
						$("#resultados").html("Mensaje: Cargando...");
					},
					success: function(datos) {
						$("#resultados").html(datos);
						load(1);
					}
				});
			}
		}

	</script>