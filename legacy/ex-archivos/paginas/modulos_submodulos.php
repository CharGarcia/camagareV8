<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones | Módulos-Submódulos</title>
</head>

<body>
<?php
session_start();
if($_SESSION['nivel'] >= 3){
include("../conexiones/conectalogin.php");
include("../head.php");
$titulo_info = "Opciones de Módulos y Submómulos";
?>
<?php 
include("../navbar_confi.php");
include("../modal/nuevo_modulo.php");
include("../modal/editar_modulo.php");
include("../modal/nuevo_sub_modulo.php");
include("../modal/editar_sub_modulo.php");

?>


<nav class="navbar navbar" role="navigation">
   <div class="list-group-item list-group-item-info">
    <div class="collapse navbar-collapse">
 		<ul class="nav navbar-nav navbar-center" >
		<li><a href="#" data-toggle="modal" data-target="#nuevoModulo"><span class="glyphicon glyphicon-plus"></span> Nuevo Módulo</a></li>
		<li><a href="#" data-toggle="modal" data-target="#nuevosubModulo"><span class="glyphicon glyphicon-plus"></span> Nuevo Sub Módulo</a></li>
		<li><a href="#" onclick='modulo(1);'><span class="glyphicon glyphicon-list" ></span> Ver Módulos</a></li>
		<li><a href="#" onclick='modulo(2);'><span class="glyphicon glyphicon-list" ></span> Ver Sub Módulos</a></li>
		<input type="hidden" id="tipo_modulo">
		</ul>
    </div>
  </div>
</nav>

<div class="container">  
	<div class="col-md-12">
    <div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Módulos y submódulos</h4>		
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" >
				<div class="form-group row">
					<label for="q" class="col-md-1 control-label">Buscar:</label>
					<div class="col-md-8">
					<div class="input-group">
						<input type="text" class="form-control" id="b" placeholder="Nombre"  onkeyup='load(1);'>
						 <span class="input-group-btn">
							<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
						 </span>
					</div>
					</div>
					<span id="loader"></span>
				</div>
			</form>
			<div id='resultados'></div><!-- Carga los datos ajax -->
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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.4/css/bootstrap-select.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.4/js/bootstrap-select.min.js"></script>

</body>

</html>
<script>
$(document).ready(function(){
	load(1);
	
});

function arreglar_modulos_asignados(){
	$("#resultados").fadeIn('slow');
	$.ajax({
		url:'../paginas/arreglar_modulos_asignados.php',
		 beforeSend: function(objeto){
		 $('#resultados').html('<img src="../image/ajax-loader.gif"> Arreglando modulos y asignaciones...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#resultados').html('');
			
		}
	})
}	

//para guardar un modulo
$("#nuevo_modulo" ).submit(function( event ) {
  $('#guarda_modulo').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/modulos_submodulos.php?action=guardar_modulo",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax").html(datos);
			$('#guarda_modulo').attr("disabled", false);
		  }
	});
 event.preventDefault();
})

//para guardar un sub modulo
$("#nuevo_submodulo" ).submit(function( event ) {
  $('#guarda_submodulo').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/modulos_submodulos.php?action=guardar_submodulo",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_submodulo").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax_submodulo").html(datos);
			$('#guarda_submodulo').attr("disabled", false);
		  }
	});
 event.preventDefault();
})

//para mostrar los modulos
function load(page){
	var modulo=document.getElementById('tipo_modulo').value;
	var b= $("#b").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url: "../ajax/modulos_submodulos.php?action="+modulo+"&b="+b+"&page="+page,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
		}
	});
}
//para saber el tipo de modulo
function modulo(tipo){	
	if (tipo==1){
		var mostrar="mostrar_modulos";
		$("#tipo_modulo").val(mostrar);
	}
	if (tipo==2){
		var mostrar="mostrar_submodulos";
		$("#tipo_modulo").val(mostrar);
	}
	load(1);
}

//editar un modulo
$( "#editar_modulo" ).submit(function( event ) {
  $('#guarda_modulo').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/modulos_submodulos.php?action=editar_modulo",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_modulo").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax_modulo").html(datos);
			$('#guarda_modulo').attr("disabled", false);
		  }
	});
	 event.preventDefault();
})

//editar un modulo
$( "#editar_submodulo" ).submit(function( event ) {
  $('#editar').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/modulos_submodulos.php?action=editar_submodulo",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_submod").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax_submod").html(datos);
			$('#editar').attr("disabled", false);
		  }
	});
	 event.preventDefault();
})

function obtener_datos_modulo(id){
			var id_modulo = $("#id_modulo"+id).val();
			var nombre_modulo = $("#nombre_modulo"+id).val();
			var nombre_icono = $("#nombre_icono"+id).val();
			var id_icono = $("#id_icono"+id).val();

			$("#mod_id_modulo").val(id_modulo);
			$("#mod_nombre_modulo").val(nombre_modulo);
			$("#mod_id_icono").val(id_icono);
	}

function obtener_datos_submodulo(id){
			var id_modulo = $("#id_modulo_sub"+id).val();
			var id_submodulo = $("#id_submodulo"+id).val();
			var nombre_submodulo = $("#nombre_submodulo"+id).val();
			var ruta = $("#ruta"+id).val();
			var id_icono_sub = $("#id_icono_sub"+id).val();

			$("#mod_id_submodulo").val(id_submodulo);
			$("#mod_id_modulo_sub").val(id_modulo);
			$("#mod_nombre_submodulo").val(nombre_submodulo);
			$("#mod_ruta").val(ruta);
			$("#mod_id_icono_sub").val(id_icono_sub);
	}
	
function eliminar_submodulo(id){
		if (confirm("Realmente desea eliminar el sub módulo?")){	
		$.ajax({
		type: "GET",
		url: "../ajax/modulos_submodulos.php",
		data: "action=eliminar_submodulos&id_submodulo="+id,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: eliminando...");
		  },
		success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
		});
	}
}

</script>