<!DOCTYPE html>
<html lang="es">
<head>
<title>Opciones | Empresas</title>
<script src="../js/jquery.min.js"></script>
</head>
<body>

<?php
session_start();
if($_SESSION['nivel'] >= 3 ){
$titulo_info ="Opciones de Empresas";
include("../head.php");
include("../navbar_confi.php");
include("../conexiones/conectalogin.php");
?>

<div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-search'></i> Buscar Empresas</h4>
				</div>	

	<div class="panel-body">
	<?php
				include("../modal/editar_empresa.php");
			?>
			<form class="form-horizontal" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Nombre:</label>
							<div class="col-md-3">
								<input type="text" class="form-control" id="q" placeholder="Nombre, nombre comercial o ruc" onkeyup='load(1);'>
							</div>
			
							<div class="col-md-4">
								<button type="button" class="btn btn-default" onclick='load(1);'>
									<span class="glyphicon glyphicon-search" ></span> Buscar</button>
									<button type="button" class="btn btn-default" >
									<span class="glyphicon glyphicon-pencil" ></span> Actualizar Rimpe</button>
								<span id="loader"></span>
							</div>
						</div>
			</form>
			<div id="resultados"></div><!-- Carga los datos ajax -->
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
<script type="text/javascript" src="../js/opciones_empresa.js"></script>
</body>
</html>
<script>

function obtener_datos(id){
		var estado_empresa = $("#estado_empresa"+id).val();
		$("#mod_estado_empresa").val(estado_empresa);
		$("#mod_id_empresa").val(id);
}


function actualizar_rimpe(){
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/actualizar_rimpe.php?action=actualizar_rimpe',
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
	}	

</script>