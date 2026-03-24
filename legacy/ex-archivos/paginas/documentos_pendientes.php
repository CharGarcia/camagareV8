<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Pendientes | SRI</title>
<?php include("../head.php");?>

</head>

<body>
<?php
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
$titulo_info ="Documentos pendientes por enviar al SRI";
$conexion = conenta_login();
?>
<?php 
include("../navbar_confi.php");
?>
<div class="container-fluid">
	<div class="col-md-12">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Documentos pendientes por autorizar en el SRI</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-6">
								<input type="text" class="form-control" id="q" placeholder="Empresa" onkeyup='load(1);'>
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
				url:'../ajax/buscar_documentos_pendientes.php?action=pendientes&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif">');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			});

};

</script>
