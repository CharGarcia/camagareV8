<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones documentos</title>
<?php include("../head.php");?>
</head>

<body>
<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
$id_usuario = $_SESSION['id_usuario'];

$titulo_info ="Opciones de documentos cargados";
include("../navbar_confi.php");	
include("../conexiones/conectalogin.php");
$con = conenta_login();

?>

	<div class="container-fluid">
	<div class="col-md-10 col-md-offset-1">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-screenshot'></i> Opciones de documentos cargados</h4>
		</div>			
			<div class="panel-body">
			
					<form class="form-horizontal" role="form" id="datos_cotizacion">
				
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-5">
								<input type="text" class="form-control" id="q" placeholder="Buscar documentos" onkeyup='load(1);'>
							</div>
				
							<div class="col-md-3">
								<button type="button" class="btn btn-default" onclick='load(1);'>
									<span class="glyphicon glyphicon-search" ></span> Buscar</button>
								<span id="loader"></span>
							</div>
							
						</div>
			</form>
			<div id="resultados_documentos"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			
<?php }else{
header('Location: ../includes/logout.php');
exit;
}
?>
<script type="text/javascript" src="../js/bootstrap-filestyle.js"> </script>
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
				url:'../ajax/buscar_documentos_cargados.php',
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
}
function eliminar_doc(id_documento){
		var q= $("#q").val();
		if (confirm("Realmente desea eliminar el documento?")){	
		$.ajax({
        type: "POST",
        url: '../ajax/subir_documentos.php',
        data: "id_documento="+id_documento,"q":q,
		 beforeSend: function(objeto){
			$("#resultados_documentos").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados_documentos").html(datos);
		load(1);
		}
			});
		}
};
</script>

