<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones | Sucursales</title>
<?php include("../head.php");?>
</head>

<body>
<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

include("../paginas/menu_de_empresas.php"); 	
?>
<?php 
include("../modal/nueva_sucursal.php");
include("../modal/editar_sucursal.php");


?>

	<div class="container-fluid">
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevaSucursal"><span class="glyphicon glyphicon-plus" ></span> Nueva sucursal</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Sucursales</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" >
			
			<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Sucursal:</label>
							<div class="col-md-6">
							<div class="input-group">
								<input type="text" class="form-control" id="q" placeholder="serie 001-001" onkeyup='load(1);'>
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

<?php }else{
	header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
	exit;
}
?>

<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>

<script>
		$(document).ready(function(){
			load(1);
		});

		function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_sucursales.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}		
$( "#guardar_sucursal" ).submit(function( event ) {
		  $('#guardar_datos_sucursal').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/guardar_sucursal.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax").html(datos);
					$('#guardar_datos_sucursal').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
		})
	
function eliminar_sucursal(id_sucursal){
			var q= $("#q").val();
		if (confirm("Realmente desea eliminar la sucursal?")){	
		$.ajax({
        type: "GET",
        url: "../ajax/buscar_sucursales.php",
        data: "id_sucursal="+id_sucursal,"q":q,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
			});
		}
}

$( "#editar_sucursal" ).submit(function( event ) {
  $('#guardar_datos_sucursal').attr("disabled", true);
  
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_sucursal.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_sucursal").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax_sucursal").html(datos);
			$('#guardar_datos_sucursal').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})

function obtener_datos(id){
			var serie_sucursal = $("#serie_sucursal"+id).val();
			var nombre_sucursal = $("#nombre_sucursal"+id).val();
			var direccion_sucursal = $("#direccion_sucursal"+id).val();
			var telefono_sucursal = $("#telefono_sucursal"+id).val();

			$("#mod_direccion_sucursal").val(direccion_sucursal);
			$("#mod_nombre_sucursal").val(nombre_sucursal);
			$("#mod_serie").val(serie_sucursal);
			$("#mod_telefono_sucursal").val(telefono_sucursal);
			$("#mod_id_sucursal").val(id);
		}

	</script>
</body>

</html>
