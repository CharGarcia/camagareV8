<!DOCTYPE html>
<html lang="en">
 <head>
<title>Mis claves</title>
<?php include("../head.php");?>
</head>

  <body>	
<?php
session_start();
$titulo_info ="Mis Claves";
include("../navbar_confi.php");	
include("../conexiones/conectalogin.php");
$con = conenta_login();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
$id_usuario = $_SESSION['id_usuario'];


include("../modal/nuevo_registro_claves.php");
include("../modal/editar_registro_claves.php");
?>
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">
	
		    <div class="btn-group pull-right">

							<button type="button" class="btn btn-info btn-md" data-toggle="modal" data-target="#nuevoRegistroClaves" title="Agregar productos a la factura">
							 <span class="glyphicon glyphicon-plus"></span> Agregar nueva</button>
		
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Buscar contraseñas</h4>		
		</div>			
			<div class="panel-body">

			<form class="form-horizontal" role="form" id="claves">
				
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="text" class="form-control" id="q" placeholder="Empresa, institución, detalle" onkeyup='load(1);'>
							</div>
				
							<div class="col-md-3">
								<button type="button" class="btn btn-default" onclick='load(1);'>
									<span class="glyphicon glyphicon-search" ></span> Buscar</button>
								<span id="loader"></span>
							</div>
							
						</div>
			</form>
			<div id="resultados_claves"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
						
			</div>
		</div>

	</div>
	<hr>

 

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
				url:'../ajax/buscar_contras.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
};
$( "#guardar_clave" ).submit(function( event ) {
		  $('#guardar_contra').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/nueva_contra.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax").html(datos);
					$('#guardar_contra').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
		});
		
function eliminar_clave (id_clave){
			var q= $("#q").val();
			var id_contra_clave = $("#id_contra"+id_clave).val();
	if (confirm("Realmente desea eliminar el registro seleccionado?")){
		$.ajax({
        type: "GET",
        url: "../ajax/buscar_contras.php",
        data: "id_clave="+id_contra_clave,"q":q,
		 beforeSend: function(objeto){
		$("#resultados_claves").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados_claves").html(datos);
		load(1);
		}
			});

	}
}

$( "#editar_clave" ).submit(function( event ) {
  $('#actualizar_clave').attr("disabled", true);

 var parametros = $(this).serialize();
   
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_contra.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax2").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax2").html(datos);
			$('#actualizar_clave').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})
function obtener_datos_claves(id){
			var ruc_empresa = $("#ruc_empresa"+id).val();
			var institucion = $("#institucion"+id).val();
			var usuario = $("#usuario"+id).val();
			var clave = $("#clave"+id).val();
			var detalle = $("#detalle"+id).val();
			$("#mod_id").val(id);
			$("#mod_empresa").val(ruc_empresa);
			$("#mod_institucion").val(institucion);
			$("#mod_usuario").val(usuario);
			$("#mod_clave").val(clave);
			$("#mod_detalle").val(detalle);

	}
</script>