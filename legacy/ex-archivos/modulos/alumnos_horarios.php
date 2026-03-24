<?php

session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <title>Alumnos</title>
	<?php include("../paginas/menu_de_empresas.php");
		  include("../modal/nuevo_horario.php");
		  include("../modal/editar_horario.php");
	?>
  </head>
  <body>	
	
    <div class="container">
		<div class="panel panel-info">
			
			<div class="panel-heading">
					
				<div class="btn-group pull-right">
					<a href="#" data-toggle="modal" data-target="#nuevoHorario" class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nuevo horario</a>
				</div>
				<h4><i class='glyphicon glyphicon-search'></i> Buscar horarios</h4>	
			</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form" id="datos_cotizacion">
							<div class="form-group row">
								<label for="q" class="col-md-2 control-label">Buscar:</label>
								<div class="col-md-5">
									<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
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
 </body>
</html>
<script>
		$(document).ready(function(){
			load(1);
		});

function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_horarios.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
};

function eliminar_horario(id_horario){
			var id_horario = $("#id_horario"+id_horario).val();
		if (confirm("Realmente desea eliminar el horario?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/buscar_horarios.php",
        data: "id_horario="+id_horario,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
			});
		}
};

$( "#guardar_nuevo_horario" ).submit(function( event ) {
		  $('#guardar_datos_horario').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/nuevo_horario.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_horario").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_horario").html(datos);
					$('#guardar_datos_horario').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

$( "#editar_horario" ).submit(function( event ) {
		  $('#guardar_datos_horario').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/editar_horario.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_editar_horario").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_editar_horario").html(datos);
					$('#guardar_datos_horario').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

function pasa_datos_editar_horario(id_horario){
		var id_horario = $("#id_horario"+id_horario).val();
		var nombre_horario = $("#nombre_horario"+id_horario).val();
		$("#mod_id_horario").val(id_horario);
		$("#mod_nombre_horario").val(nombre_horario);
};
</script>