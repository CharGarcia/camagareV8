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
  <title>Opciones</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include("../modal/detalle_consignaciones.php");
	?>
	<style type="text/css">
		 ul.ui-autocomplete {
			z-index: 1100;
		}
		</style>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#opcion_consignacion_venta" onclick="carga_modal();"><span class="glyphicon glyphicon-plus" ></span> Nueva devolución/Factura</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Opciones de tablas</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" method ="POST" action="">
				<div class="form-group">
				<div class="col-sm-4">
						<div class="input-group">
							<span class="input-group-addon"><b>Copiar información del ruc</b></span>
							<input type="text" class="form-control input-sm text-center" name="ruc_desde" id="ruc_desde" value="">
						</div>
				</div>
				<div class="col-sm-4">
						<div class="input-group">
							<span class="input-group-addon"><b>Copiar información al ruc</b></span>
							<input type="text" class="form-control input-sm text-center" name="ruc_hasta" id="ruc_hasta" value="">
						</div>
				</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="tabla_nivel()"><span class="glyphicon glyphicon-duplicate" ></span> Copiar tabla nivel alumno</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="tabla_horarios()"><span class="glyphicon glyphicon-duplicate" ></span> Copiar tabla horarios</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="tabla_alumnos()"><span class="glyphicon glyphicon-duplicate" ></span> Copiar tabla alumnos</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="tabla_clientes()"><span class="glyphicon glyphicon-duplicate" ></span> Copiar tabla clientes</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="actualizar_clientes()"><span class="glyphicon glyphicon-refresh" ></span> Actualizar clientes</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="buscar_modulos()"><span class="glyphicon glyphicon-refresh" ></span> Buscar módulos faltantes</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="arreglar_lote()"><span class="glyphicon glyphicon-refresh" ></span> Arreglar lotes</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="arreglar_compras()"><span class="glyphicon glyphicon-refresh" ></span> Arreglar compras</button>				
					</div>
				</div>
				<div class="form-group">
					<div class="col-sm-4">
						<button type="button" class="btn btn-info btn-sm" onclick="arreglar_retenciones()"><span class="glyphicon glyphicon-refresh" ></span> Arreglar retenciones compras</button>				
					</div>
				</div>
			<span id="loader"></span>				
			</form>
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
		</div>

	</div>
<?php

}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<link rel="stylesheet" href="../css/jquery-ui.css"> <!--para que se vea con fondo blanco el autocomplete -->
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
</body>
</html>
<script>

function tabla_nivel(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=tabla_nivel&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function tabla_horarios(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=tabla_horarios&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function tabla_alumnos(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=tabla_alumnos&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function tabla_clientes(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=tabla_clientes&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function actualizar_clientes(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=actualizar_clientes&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function buscar_modulos(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();
	
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=buscar_modulos_faltantes",
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
}

function eliminar_modulo_faltante(id){
	if (confirm("Realmente desea eliminar?")){
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=eliminar_modulos_faltantes&id_eliminar="+id,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
	}
}


function arreglar_lote(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();

	if (confirm("Realmente desea arreglar los lotes?")){
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=arreglar_lotes&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
	}
}

function arreglar_compras(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();

	if (confirm("Realmente desea arreglar los proveedores en las compras?")){
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=arreglar_compras&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
	}
}

function arreglar_retenciones(){
	var desde= $("#ruc_desde").val();
	var hasta= $("#ruc_hasta").val();

	if (confirm("Realmente desea arreglar los proveedores en las retenciones?")){
	$.ajax({
		url: "../ajax/opciones_tablas.php?action=arreglar_retenciones&desde="+desde+"&hasta="+hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
		success: function(data){
			$(".outer_div").html(data).fadeIn('fast');
			$('#loader').html('');
	  }
	});
	}
}


</script>