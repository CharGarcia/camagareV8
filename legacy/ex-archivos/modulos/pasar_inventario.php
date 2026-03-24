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
  <title>Pasar a inventario</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include("../modal/detalle_documento.php");	
	?>
  </head>
  <body>

<div class="container-fluid">  
    <div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Pasar a inventario</h4>		
		</div>

			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="p" placeholder="Productos, documento, proveedor, ruc" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
							<span id="loader_pasar_inventario"></span>
						</div>
			</form>
			<div id="resultados_pasar_inventario"></div><!-- Carga los datos ajax -->
			<div class='outer_div_pasar_inventario'></div><!-- Carga los datos ajax -->
			</div>

	</div>
  </div>

 

<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>

 </body>
 <style type="text/css">
  ul.ui-autocomplete {
    z-index: 1100;
}
</style>
</html>

<script>
$(document).ready(function(){
			load(1);
});

function load(page){
			var p= $("#p").val();
			$("#loader_pasar_inventario").fadeIn('slow');
			$.ajax({
				url:'../ajax/pasar_inventario.php?action=pasar_inventario&page='+page+'&p='+p,
				 beforeSend: function(objeto){
				 $('#loader_pasar_inventario').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_pasar_inventario").html(data).fadeIn('slow');
					$('#loader_pasar_inventario').html('');
				}
			})	

}

//para buscar productos en pasar a inventario
function buscar_productos(id){
	$("#mi_producto"+id).autocomplete({
			source:'../ajax/productos_autocompletar_inventario.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_producto'+id).val(ui.item.id);
				$('#mi_producto'+id).val(ui.item.nombre);
				$('#codigo_producto'+id).val(ui.item.codigo);
			var producto = $("#id_producto"+id).val();	
			//cuando trae se busca el producto me trae que tipo de medida tiene
				$.post( '../ajax/select_tipo_medida.php', {id_producto: producto}).done( function( res_tipos_medidas ){
					$("#unidad_medida"+id).html(res_tipos_medidas);
				});	
			}
		});
		$( "#mi_producto" ).autocomplete("widget").addClass("fixedHeight");
				
		$("#mi_producto"+id).on( "keydown", function( event ) {
			if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
			{
				$("#id_producto"+id).val("");
				$("#mi_producto"+id).val("");
				$("#unidad_medida"+id).val("");
				$("#codigo_producto"+id).val("");
			}

			if (event.keyCode==$.ui.keyCode.DELETE){
				$("#id_producto"+id).val("");
				$("#mi_producto"+id).val("");
				$("#unidad_medida"+id).val("");
				$("#codigo_producto"+id).val("");
			}
			
			if (event.keyCode==$.ui.keyCode.BACKSPACE){
				$("#id_producto"+id).val("");
				$("#unidad_medida"+id).val("");
				$("#codigo_producto"+id).val("");
			}
			
			
	});		
}

</script>
