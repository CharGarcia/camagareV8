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
  <title>Marcas</title>
	<?php include("../paginas/menu_de_empresas.php");
	include("../modal/grupo_familiar_producto.php");
	?>
  </head>
  <body>

<div class="container">  
	<div class="col-md-8 col-md-offset-2">
    <div class="panel panel-info">
		<div class="panel-heading">
		<div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#grupo_familiar_producto" onclick="titulo_grupo_familiar_producto('Nuevo grupo familiar');"><span class="glyphicon glyphicon-plus" ></span> Nuevo</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Grupo familiar en productos</h4>		
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" action="">
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
function titulo_grupo_familiar_producto(titulo){
	$("#TituloModalGrupoFamiliarproducto").html(titulo);
	document.getElementById("nombre_grupo_familiar_producto").value="";
	};
	
//para cargar al entrar a la pagina
		$(document).ready(function(){
			window.addEventListener("keypress", function(event){
				if (event.keyCode == 13){
					event.preventDefault();
				}
			}, false);
			load(1);
		});
//para buscar las marcas
function load(page){
		var q= $("#q").val();
		$("#loader").fadeIn('slow');
		$.ajax({
			url:'../ajax/grupo_familiar_producto.php?action=buscar_grupo_familiar_producto&page='+page+'&q='+q,
			 beforeSend: function(objeto){
			 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
		  },
			success:function(data){
				$(".outer_div").html(data).fadeIn('slow');
				$('#loader').html('');
				
			}
		})
	}
		
//para guardar y editar una bodega		
$( "#guarda_grupo_familiar_producto" ).submit(function( event ) {
  $('#guardar_datos_grupo_familiar_producto').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url:'../ajax/grupo_familiar_producto.php?action=guardarYeditar_grupo_familiar_producto',
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_grupo_familiar_producto").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_grupo_familiar_producto").html(datos);
			$('#guardar_datos_grupo_familiar_producto').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
});		

//para eliminar 
function eliminar_grupo_familiar_producto(id){
			var q= $("#q").val();
		if (confirm("Realmente deseas eliminar?")){	
		$.ajax({
        type: "GET",
       url:'../ajax/grupo_familiar_producto.php?action=eliminar_grupo_familiar_producto',
        data: "id_grupo_familiar_producto="+id,"q":q,
		 beforeSend: function(objeto){
			$("#loader").html("Eliminando grupo...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		$("#loader").html('');
		load(1);
		}
			});
		}
}


function obtener_datos(id){
			var id_grupo = $("#id_grupo"+id).val();
			var nombre_grupo = $("#nombre_grupo"+id).val();
			$("#id_grupo_familiar_producto").val(id_grupo);
			$("#nombre_grupo_familiar_producto").val(nombre_grupo);
			$("#TituloModalGrupoFamiliarproducto").html("Editar grupo familiar");
	}

</script>