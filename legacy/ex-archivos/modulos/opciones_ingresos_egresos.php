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
  <title>Opciones/Ingresos/Egresos</title>
	<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevaOpcionIngresoEgreso" onclick="nuevaOpcionIngresoEgreso();"><span class="glyphicon glyphicon-plus" ></span> Nueva</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Opciones de ingresos y egresos</h4>
		</div>			
			<div class="panel-body">
			<?php
				include("../modal/opciones_ingresos_egresos.php");
				//include("../modal/editar_clientes.php");
			?>
			<form class="form-horizontal" method ="POST" action="../excel/clientes.php">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<input type="hidden" id="ordenado" value="opc.descripcion">
							<input type="hidden" id="por" value="asc">
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</body>
</html>
<script>
$(document).ready(function(){
	load(1);
});

function nuevaOpcionIngresoEgreso() {
			document.querySelector("#titleModalOpcionIngresoEgreso").innerHTML = "<i class='glyphicon glyphicon-ok'></i> Nueva opción";
			document.querySelector("#guardar_OpcionIngresoEgreso").reset();
			document.querySelector("#id_OpcionIngresoEgreso").value = "";
			document.querySelector("#btnActionFormOpcionIngresoEgreso").classList.replace("btn-info", "btn-primary");
			document.querySelector("#btnTextOpcionIngresoEgreso").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Guardar";
			document.querySelector('#btnActionFormOpcionIngresoEgreso').title = "Guardar";
		}

function load(page){
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/opciones_ingresos_egresos.php?action=buscar_opciones_ingresos_egresos&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por,
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
	
	function editar_opcion_ingresos_egresos(id){
		document.querySelector('#titleModalOpcionIngresoEgreso').innerHTML ="<i class='glyphicon glyphicon-edit'></i> Actualizar opción";
		document.querySelector("#guardar_OpcionIngresoEgreso").reset();
		document.querySelector("#id_OpcionIngresoEgreso").value = id;
		document.querySelector('#btnActionFormOpcionIngresoEgreso').classList.replace("btn-primary", "btn-info");
		document.querySelector("#btnTextOpcionIngresoEgreso").innerHTML = "<i class='glyphicon glyphicon-floppy-disk'></i> Actualizar";

		var descripcion_opcion = $("#descripcion_act"+id).val();
		var tipo_opcion = $("#tipo_opcion_act"+id).val();
		var status = $("#status_act"+id).val();

		$("#descripcion_opcion").val(descripcion_opcion);
		$("#tipo_opcion").val(tipo_opcion);
		$("#status").val(status);
		$("#id_OpcionIngresoEgreso").val(id);
	}

function eliminar_opcion_ingresos_egresos(id){
			var q= $("#q").val();
		if (confirm("Realmente desea eliminar el registro?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/opciones_ingresos_egresos.php?action=eliminar_opciones_ingresos_egresos",
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
	
</script>