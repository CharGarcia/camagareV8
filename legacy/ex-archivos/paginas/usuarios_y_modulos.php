
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Usuarios y módulos</title>
<?php include("../head.php");?>
<script src="../js/jquery.min.js"></script>
<script src="../js/select_usuarios_empresas.js"></script>

</head>
<body>
<?php
session_start();
if($_SESSION['nivel'] >= 2){
include("../conexiones/conectalogin.php");
$titulo_info ="Agregar Módulos a Usuarios ";
include("../navbar_confi.php");
$conexion = conenta_login();
$id = $_SESSION['id_usuario'];
//para mostrar los usuarios
$sql = "SELECT u.id as id_usuario, u.nombre as nombre, u.cedula as cedula FROM usuarios u, usuario_asignado ua where u.id = ua.id_usuario  and ua.id_adm = $id ;";
$res = mysqli_query($conexion,$sql);
?>
<div class="row">
<div class="col-md-10 col-md-offset-1">
<div class="panel panel-info" >
	<div class="panel-body">
		<form class="form-horizontal">
			<div class="form-group row">
				<label class="col-md-1 control-label">Usuarios:</label>
					<div class="col-md-4">
					<select class="selectpicker" data-live-search="true" name="id_usuario" id="usuarios">
					 <option value="0" selected >Seleccione un usuario</option>
					<?php while($p = mysqli_fetch_assoc($res)){
					?>
					<option value="<?php echo $p['id_usuario'] ?>"> <?php echo $p['nombre'] ?> C: <?php echo $p['cedula'] ?> </option> 
					<?php
					}
					?>
					</select>
					</div>
				<label class="col-md-1 control-label">Empresas:</label>
					<div class="col-md-4" >
					<select class="form-control" name="id_empresa" id="empresas">
					<option value="0">Seleccione una empresa</option>
					</select>		
					</div>
					<div class="col-md-2" >
					<button type="button" class="btn btn-default" onclick="mostrar_modulos();"><i class='glyphicon glyphicon-zoom-in'></i> Mostrar</button>
				   </div>
			</div>
		</form>		
	</div>
</div>
</div>
</div>
							
<div class="col-md-12">
<div class="panel-body">
<div class="container-fluid">
<div class="row">
<div class="col-md-8 col-md-offset-2">
<div class="panel panel-info" >
	<div class="panel-heading">
		<h4><i class='glyphicon glyphicon-list-alt'></i> Listado de módulos asignados y disponibles</b></h4>
	</div>

<div class="table-responsive">
<div class="panel-body">

	<div class="panel-body">
		<form class="form-horizontal" >
					<div class="form-group row">
						<label for="q" class="col-md-1 control-label">Buscar:</label>
						<div class="col-md-7">
						<input type="hidden" id="ordenado" value="id_submodulo">
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
		<div id="resultados"></div>
		<div class='outer_div'></div><!-- Carga los datos ajax -->
	</div>

</div>
	</div>
	
	
	</div>
	</div>
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.4/css/bootstrap-select.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.12.4/js/bootstrap-select.min.js"></script>

	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
</html>
<script>
$(document).ready(function(){
	load(1);
});

function load(page){
	var id_usuario= $("#usuarios").val();
	var id_empresa= $("#empresas").val();
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../paginas/buscar_modulos.php?action=ajax&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por+"&id_usuario="+id_usuario+"&id_empresa="+id_empresa,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			
		}
	})
}

function mostrar_modulos(){
	var page=1;
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	var id_usuario= $("#usuarios").val();
	var id_empresa= $("#empresas").val();
	$("#resultados").fadeIn('slow');
	$.ajax({
		url:'../paginas/buscar_modulos.php?action=ajax&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por+"&id_usuario="+id_usuario+"&id_empresa="+id_empresa,
		 beforeSend: function(objeto){
		 $('#resultados').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#resultados').html('');
			
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

function agregar_modulo(id_sub){
	var id_submodulo = $("#id_submodulo"+id_sub).val();
	var id_modulo = $("#id_modulo"+id_sub).val();
	var id_usuario= $("#usuarios").val();
	var id_empresa= $("#empresas").val();
	var nombre_submodulo = $("#nombre_submodulo"+id_sub).val();
	var seleccionado = $("#elemento_seleccionado").val();

	if (id_usuario==0) {
	alert('Seleccione usuario para asignar módulos');
	return false;
	}
	if (id_empresa==0) {
	alert('Seleccione empresa para asignar módulos');
	return false;
	}

		$.ajax({
        type: "POST",
        url: '../ajax/agregar_modulos.php',
        data: 'id_sub_modulo='+id_submodulo+'&id_sub_usuario='+id_usuario+'&id_sub_empresa='+id_empresa+'&id_modulo='+id_modulo+'&nombre_submodulo='+nombre_submodulo,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		}
			});
	//event.preventDefault();
};


</script>