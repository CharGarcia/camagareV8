<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Opciones | Usuarios</title>
<?php include("../head.php");?>
</head>
<body>

<?php
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 3){
$titulo_info ="Listado de Usuarios";
include("../navbar_confi.php");
?>

<div class="container-fluid">
		<div class="panel panel-info">
				<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-search'></i> Buscar Usuarios</h4>
				</div>	
			<ul class="nav nav-tabs nav-justified">
			<li class="active"><a data-toggle="tab" href="#usuarios">Usuarios</a></li>
			<li><a data-toggle="tab" href="#acciones_usuarios">Acciones de usuarios</a></li>
			</ul>
			<div class="tab-content">
			<div id="usuarios" class="tab-pane fade in active">
			<div class="panel-body">
					<?php
						include("../modal/editar_usuarios.php");
					?>
				<form class="form-horizontal" role="form" id="datos_cotizacion">
					<div class="form-group row">
						<label for="q" class="col-md-2 control-label">Nombres:</label>
						<div class="col-md-5">
							<div class="input-group">
							<input type="text" class="form-control" id="q" placeholder="Nombre, correo, teléfono" onkeyup='load(1);'>
							<span class="input-group-btn">
							<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
							</span>
						</div>
						</div>
					</div>
				</form>
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
			</div>
    
		<div id="acciones_usuarios" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="acciones" placeholder="Usuario, empresa, fecha" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
							<span id="loader_acciones"></span>
						</div>
			</form>
			<div id="resultados_detalles_acciones"></div><!-- Carga los datos ajax -->
			<div class='outer_div_acciones'></div><!-- Carga los datos ajax -->
			</div>
		</div>
	</div>	
			
			
		</div>
	</div>
	<hr>
<?php
}else{
	?>
		  <div class="alert alert-danger alert-dismissible" role="alert">
		  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		  <strong>Hey!</strong> Usted no tiene permisos para acceder a este sitio! </div>
		  <?php
}
?>
	<?php include("../pie.php");?>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
 </body>
 <script>
 $(document).ready(function(){
	 load(1);
	 /*
	$.ajax({
		url:'../ajax/buscar_usuarios.php?action=actualizar_estado',
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
		}
	});	
	*/
});
function load(page){
	var q= $("#q").val();
	var acciones= $("#acciones").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_usuarios.php?action=mostrar_usuarios&page='+page+'&q='+q,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			
		}
	})
	
	//para buscar las acciones de los usuarios
	$.ajax({
		url:'../ajax/opciones_acciones_usuarios.php?action=buscar_acciones&page='+page+'&acciones='+acciones,
		 beforeSend: function(objeto){
		 $('#loader_acciones').html('<img src="../image/ajax-loader.gif"> Buscando...');
	  },
		success:function(data){
			$(".outer_div_acciones").html(data).fadeIn('slow');
			$('#loader_acciones').html('');	
		}
	})
	
}

$( "#editar_usuario" ).submit(function( event ) {
  $('#actualizar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_usuario.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax2").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax2").html(datos);
			$('#actualizar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})
		
function obtener_datos(id){
	var nombre_usuario = $("#nombre_usuario"+id).val();
	var tipo_usuario = $("#tipo_usuario"+id).val();
	var cedula_usuario = $("#cedula_usuario"+id).val();
	var estado_usuario = $("#estado_usuario"+id).val();
	var mail_usuario = $("#mail_usuario"+id).val();
	
	$("#mod_nombre").val(atob(nombre_usuario));
	$("#mod_tipo").val(atob(tipo_usuario));
	$("#mod_cedula").val(atob(cedula_usuario));
	$("#mod_estado").val(atob(estado_usuario));
	$("#mod_mail").val(atob(mail_usuario));
	$("#mod_id").val(id);
}
 </script>
</html>