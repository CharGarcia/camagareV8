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
  <meta charset="utf-8">
  <title>Unidad de medida</title>
	<?php include("../paginas/menu_de_empresas.php");
	include("../modal/unidad_medida.php");
	?>
  </head>
  <body>

<div class="container"> 
<div class="col-md-8 col-md-offset-2">
    <div class="panel panel-info">
		<div class="panel-heading">
		<div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#UnidadMedida" onclick="titulo_medida('Nueva unidad de medida');"><span class="glyphicon glyphicon-plus" ></span> Nueva medida</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Unidades de medida</h4>		
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" >
				<div class="form-group row">
					<label for="q" class="col-md-1 control-label">Buscar:</label>
					<div class="col-md-8">
					<div class="input-group">
						<input type="text" class="form-control" id="q" placeholder="Nombre" onkeyup='load(1);'>
						 <span class="input-group-btn">
							<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
						 </span>
					</div>
					</div>
					<span id="loader"></span>
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
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
 </body>
</html>
<script>

//para poner el nombre en la ventana modal
function titulo_medida(titulo){
	$("#TituloModal").html(titulo);
	document.getElementById("nombre_medida").value="";
	};
	
//para cargar al entrar a la pagina
		$(document).ready(function(){
			load(1);
		});
//para buscar las unidades de medida al cargar
		function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/unidad_medida.php?action=buscar_medida&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}
		
//para guardar y editar una unidad de medida		
$( "#guarda_medida" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url:'../ajax/unidad_medida.php?action=guardarYeditar_medida',
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_medida").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_medida").html(datos);
			$('#guardar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
});		

//para eliminar una unidad de medida

function eliminar_medida(id){
			var q= $("#q").val();
		if (confirm("Realmente deseas eliminar la unidad de medida?")){	
		$.ajax({
        type: "GET",
       url:'../ajax/unidad_medida.php?action=eliminar_medida',
        data: "id_medida="+id,"q":q,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Eliminando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
			});
		}
}


function obtener_datos(id){
			var id_medida = $("#id_medida"+id).val();
			var nombre_medida = $("#nombre_medida"+id).val();
			$("#id_medida").val(id_medida);
			$("#nombre_medida").val(nombre_medida);
			$("#TituloModal").html("Editar unidad de medida");
	}

</script>