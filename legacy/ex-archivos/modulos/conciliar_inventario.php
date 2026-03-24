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
  <title>Conciliar</title>
	<?php include("../paginas/menu_de_empresas.php");?>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Conciliar inventario</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" >
						<div class="form-group row">
							<label class="col-md-1 control-label">Producto:</label>
							<div class="col-md-2">
								<input type="hidden" name="id_producto_arreglar" id="id_producto_arreglar" >
								<input type="text" class="form-control input-sm" title="Buscar producto o servicio." name="producto_arreglar" id="producto_arreglar" placeholder="Ingrese un producto" onkeyup='buscar_productos();' autocomplete="off">
							</div>
							<div class="col-md-2">
									<button type="button" class="btn btn-default" onclick='actualizar();'><span class="glyphicon glyphicon-screenshot" ></span> Actualizar</button>
							</div>
							<!--
							<div class="col-md-2">
									<button type="button" class="btn btn-default" onclick='eliminar();'><span class="glyphicon glyphicon-screenshot" ></span> Eliminar salidas</button>
							</div>
							<div class="col-md-2">
									<button type="button" class="btn btn-default" onclick='arreglar();'><span class="glyphicon glyphicon-screenshot" ></span> Conciliar salidas</button>
							</div>
							<div class="col-md-2">
									<button type="button" class="btn btn-default" onclick='corregir();'><span class="glyphicon glyphicon-screenshot" ></span> Corregir registros</button>
							</div>
							-->
							
							<span id="loader"></span>
						</div>
													
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
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script> 
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</body>
</html>
<script>

function actualizar(){
	var id_producto = $("#id_producto_arreglar").val();
			
			if (id_producto==""){
			alert('Seleccione un producto');
			document.getElementById('producto_arreglar').focus();
			return false;
			}	
	
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/arreglo_salidas_inventario.php?action=actualizar&id_producto='+id_producto,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Espere...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
}

function arreglar(){
	var id_producto = $("#id_producto_arreglar").val();
			
			if (id_producto==""){
			alert('Seleccione un producto');
			document.getElementById('producto_arreglar').focus();
			return false;
			}	
	
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/arreglo_salidas_inventario.php?action=arreglar&id_producto='+id_producto,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Espere...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
}

function corregir(){
	var id_producto = $("#id_producto_arreglar").val();
			
			if (id_producto==""){
			alert('Seleccione un producto');
			document.getElementById('producto_arreglar').focus();
			return false;
			}	
	
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/arreglo_salidas_inventario.php?action=corregir&id_producto='+id_producto,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Espere...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
}

function eliminar(){
	var id_producto = $("#id_producto_arreglar").val();
			
			if (id_producto==""){
			alert('Seleccione un producto');
			document.getElementById('producto_arreglar').focus();
			return false;
			}	
	
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/arreglo_salidas_inventario.php?action=eliminar&id_producto='+id_producto,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Espere...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
}

//para buscar productos
function buscar_productos(){
	$("#producto_arreglar").autocomplete({
		source: '../ajax/productos_autocompletar.php',
		minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#id_producto_arreglar').val(ui.item.id);
				$('#producto_arreglar').val(ui.item.nombre);
			}
			//document.getElementById('cantidad_agregar').focus();
	});
						
	$( "#producto_arreglar" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
				
	$("#producto_arreglar" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_producto_arreglar" ).val("");
			$("#producto_arreglar" ).val("");
		
		}
});
			
}
		


</script>