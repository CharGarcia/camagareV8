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
		  include("../modal/nuevo_campus.php");
		  include("../modal/editar_campus.php");
	?>
  </head>
  <body>	
	
    <div class="container">
		<div class="panel panel-info">
			
			<div class="panel-heading">
					
				<div class="btn-group pull-right">
					<a href="#" data-toggle="modal" data-target="#nuevoCampus" class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nuevo campus</a>
				</div>
				<h4><i class='glyphicon glyphicon-search'></i> Buscar sucursales o campus</h4>	
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
				url:'../ajax/buscar_campus.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
};

function eliminar_campus(id_campus){
			var id_campus = $("#id_campus"+id_campus).val();
		if (confirm("Realmente desea eliminar el campus?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/buscar_campus.php",
        data: "id_campus="+id_campus,
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

$( "#guardar_nuevo_campus" ).submit(function( event ) {
		  $('#guardar_datos_campus').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/nuevo_campus.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_campus").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_campus").html(datos);
					$('#guardar_datos_campus').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

$( "#editar_campus" ).submit(function( event ) {
		  $('#guardar_datos_campus').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/editar_campus.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_editar_campus").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_editar_campus").html(datos);
					$('#guardar_datos_campus').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

function pasa_datos_editar_campus(id_campus){
		var id_campus = $("#id_campus"+id_campus).val();
		var nombre_campus = $("#nombre_campus"+id_campus).val();
		$("#mod_id_campus").val(id_campus);
		$("#mod_nombre_campus").val(nombre_campus);
};
</script>