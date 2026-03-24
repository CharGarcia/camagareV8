<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Frases</title>
<?php
session_start();
include("../head.php");
?>
</head>
<body>
<meta charset="utf-8">
<?php
include("../conexiones/conectalogin.php");
if($_SESSION['nivel'] >= 3){
$titulo_info ="Frases de motivación";
$conexion = conenta_login();
?>
<?php 
include("../modal/nueva_frase.php");
include("../navbar_confi.php");
?>
 
<div class="container-fluid">
	<div class="col-md-10 col-md-offset-1">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoFrase"><span class="glyphicon glyphicon-plus" ></span> Nueva frase</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Frases</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
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
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
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
				url:'../ajax/buscar_frases.php?action=ajax&page='+page+'&q='+q,
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
$( "#guardar_frase" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: '../ajax/guardar_frase.php',
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

function eliminar_frase(id_frase){
			var frase = $("#id_frase"+id_frase).val();

if (confirm("Realmente desea eliminar la frase?")){	
$.ajax({
        type: "POST",
        url: "../ajax/buscar_frases.php",
        data: "id_frase="+frase,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
		});
};

};

</script>