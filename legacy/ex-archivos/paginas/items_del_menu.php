<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones | Menú</title>
<?php include("../head.php");?>

</head>

<body>
<?php
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 3){
$titulo_info ="Opciones del menú";
$conexion = conenta_login();
?>
<?php 
include("../modal/nuevo_item_menu.php");
include("../modal/editar_item_menu.php");
include("../navbar_confi.php");
?>
<div class="container-fluid">
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoItemMenu"><span class="glyphicon glyphicon-plus" ></span> Nuevo item</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Opiones del Menú</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Opciones:</label>
							<div class="col-md-6">
								<input type="text" class="form-control" id="q" placeholder="Detalle" onkeyup='load(1);'>
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
	</div>



<?php
}else{ 
header('Location: ../includes/logout.php');
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
				url:'../ajax/buscar_items_menu.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

};

$(function() {
$( "#guardar_item_menu" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: '../ajax/guardar_item_menu.php',
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados").html("Mensaje: Guardando...");
					  },
					success: function(datos){
					$("#resultados").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
});
});

$( "#editar_item" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_item_menu.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_editar").html("Mensaje: Actualizando...");
			  },
			success: function(datos){
			$("#resultados_ajax_editar").html(datos);
			$('#guardar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})

function editar_item_menu(id){
			var etiqueta_menu = $("#etiqueta_menu"+id).val();
			var ruta_menu = $("#ruta_menu"+id).val();
			var nivel_menu = $("#nivel_menu"+id).val();
			var estado_menu = $("#estado_menu"+id).val();
			
			$("#mod_nombre_item").val(etiqueta_menu);
			$("#mod_ruta_item").val(ruta_menu);
			$("#mod_nivel").val(nivel_menu);
			$("#mod_estado").val(estado_menu);
			$("#mod_id_item").val(id);
		
		}
</script>
