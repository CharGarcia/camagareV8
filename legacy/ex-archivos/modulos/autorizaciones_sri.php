<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa = $_SESSION['id_empresa'];

	?>
<!DOCTYPE html>
<html lang="es">
  <head>
  <title>Autorizaciones SRI</title>
	<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
 	
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevautorizacionsri"><span class="glyphicon glyphicon-plus" ></span> Nueva autorización</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Buscar autorizaciones SRI</h4>
		</div>			
			<div class="panel-body">
			<?php
				include("../modal/nueva_autorizacion_sri.php");
				include("../modal/edita_autorizacion_sri.php");
			?>
			<form class="form-horizontal" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="text" class="form-control" id="q" placeholder="Documentos, autorizaciones" onkeyup='load(1);'>
							</div>
				
							<div class="col-md-3">
								<button type="button" class="btn btn-default" onclick='load(1);'>
									<span class="glyphicon glyphicon-search" ></span> Buscar</button>
								<span id="loader"></span>
							</div> 						
						</div>
			</form>
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
		</div>

	</div>
<?php

}else{
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
 
	<script>
$('.modal').on('hidden.bs.modal', function(){ 
		$(this).find('form')[0].reset(); //para borrar todos los datos que tenga los input, textareas, select.
		$("label.error").remove();  //lo utilice para borrar la etiqueta de error del jquery validate
	});
	
	
$(document).ready(function(){
			load(1);
		});

		function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_autorizaciones_sri.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}

	
	
$( "#guardar_autorizacion_sri" ).submit(function( event ) {
		  $('#guardar_datos_autorizaciones_sri').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/guardar_autorizaciones_sri.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax").html(datos);
					$('#guardar_datos_autorizaciones_sri').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
		})
		
$( "#editar_autorizacion_sri" ).submit(function( event ) {
		  $('#editar_datos_autorizaciones_sri').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/edita_autorizaciones_sri.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_edita_autorizacion").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_edita_autorizacion").html(datos);
					$('#editar_datos_autorizaciones_sri').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
		})
		
function pasa_datos_autorizacion(id){
			var id_serie = $("#id_serie"+id).val();
			var codigo_documento = $("#codigo_documento"+id).val();
			var autorizacion_sri = $("#autorizacion_sri"+id).val();
			var fecha_emision = $("#fecha_emision"+id).val();
			var fecha_vencimiento = $("#fecha_vencimiento"+id).val();
			var del_sri = $("#del_sri"+id).val();
			var al_sri = $("#al_sri"+id).val();
			var imprenta_sri = $("#imprenta_sri"+id).val();

			$("#id_autorizacion_mod").val(id);
			$("#serie_sri_mod").val(id_serie);
			$("#documento_sri_mod").val(codigo_documento);
			$("#autorizacion_sri_mod").val(autorizacion_sri);
			$("#fecha_emision_sri_mod").val(fecha_emision);
			$("#fecha_vence_sri_mod").val(fecha_vencimiento);
			$("#del_sri_mod").val(del_sri);
			$("#al_sri_mod").val(al_sri);
			$("#imprenta_mod").val(imprenta_sri);
	}
	</script>

</body>
</html>