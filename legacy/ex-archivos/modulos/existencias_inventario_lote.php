<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <meta charset="utf-8">
  <title>Existencias inventario</title>
	<?php include("../paginas/menu_de_empresas.php");
		 include("../modal/editar_minimos.php");
	?>
  </head>
  <body>
<div class="container-fluid">  
    <div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Existencias de inventarios por lote</h4>		
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" method ="POST" action="../excel/reporte_inventario_lote.php" >
				<div class="form-group row">
					<label for="q" class="col-md-1 control-label">Buscar:</label>
					<div class="col-md-4">
					<input type="hidden" id="ordenado" value="nombre_producto">
					<input type="hidden" id="por" value="asc">
					<div class="input-group">
						<input type="text" class="form-control" id="q" placeholder="Producto" onkeyup='load(1);'>				
					<span class="input-group-btn">
							<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
						 </span>
					</div>
					</div>
					<div class="col-md-1">
						<button type="submit" title="Descargar a excel" class="btn btn-success" ><img alt="Brand" src="../image/excel.ico" width="25" height="20"></button>
					</div>
					<span id="loader"></span>
				</div>
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

<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script src="../js/notify.js"></script>

 </body>
</html>
<script>
$(document).ready(function(){
	$.ajax({
		url:'../ajax/existencias_inventarios_tmp.php?action=lote',
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Actualizando existencias, espere por favor...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			load(1);
		}
		
	})
	
});

function load(page){
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_existencias_inventarios.php?action=lote&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
		}
	})
	 //event.preventDefault();
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


function obtener_datos(id){
			var id_minimo = $("#id_minimo"+id).val();
			var ruc_empresa = $("#ruc_empresa"+id).val();
			var id_producto = $("#id_producto_item"+id).val();
			var id_bodega = $("#id_bodega"+id).val();
			var valor_minimo = $("#valor_minimo"+id).val();
			$("#mod_id_minimo").val(id_minimo);
			$("#mod_ruc_empresa").val(ruc_empresa);
			$("#mod_id_producto").val(id_producto);
			$("#mod_id_bodega").val(id_bodega);
			$("#mod_valor_minimo").val(valor_minimo);
	}

//editar minimos
$( "#editar_minimo" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_minimos_inventario.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_editar_minimos").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_editar_minimos").html(datos);
			$('#guardar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
});
</script>