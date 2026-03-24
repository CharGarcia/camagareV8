<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

	?>
<!DOCTYPE html>
<html lang="es">
  <head>
  <title>Aprobar || Inventario</title>
	<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Aprobaciones cuarentena de productos</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" method ="POST" action="">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<input type="hidden" id="ordenado" value="apro.id">
							<input type="hidden" id="por" value="desc">
							<div class="input-group">
								<input type="text" class="form-control" id="q" placeholder="" onkeyup='load(1);'>
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
									<span id="loader"></span>	  
								</span> 
							</div>
							</div>	
													
						</div>
			</form>
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class="outer_div"></div><!-- Carga los datos ajax -->
			</div>
		</div>

	</div>
<?php

}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<script src="../js/notify.js"></script>
</body>
</html>
<script>
$(document).ready(function(){
	load(1);
});

function load(page){
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/aprobar_inventario.php?action=buscar_aprobaciones&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Buscando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			
		}
	})
}	
function ordenar(ordenado){
	$("#ordenado").val(ordenado);
	var por= $("#por").val();
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	$("#loader").fadeIn('slow');
	var value_por=document.getElementById('por').value;
			if (value_por=="asc"){
			$("#por").val("desc");
			}
			if (value_por=="desc"){
			$("#por").val("asc");
			}
	load(1);
}		
	
function eliminar_aprobacion(id){
			var q= $("#q").val();
		if (confirm("Realmente desea eliminar el documento?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/aprobar_inventario.php?action=eliminar_documento",
        data: "id="+id,"q":q,
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

function aprobar(id){
			var q= $("#q").val();
		if (confirm("Realmente desea aprobar la carga del documento?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/aprobar_inventario.php?action=aprobar_documento",
        data: "id="+id,"q":q,
		 beforeSend: function(objeto){
			$("#loader").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#loader").html(datos);
		load(1);
		}
			});
		}
}
	
</script>