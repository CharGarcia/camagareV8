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
  <title>Producto más vendido</title>
  
	<?php include("../paginas/menu_de_empresas.php");
	ini_set('date.timezone','America/Guayaquil'); 
	?>
  </head>
  <body>

<div class="container-fluid">
	<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Producto o servicio más vendido</h4>	
		</div>
		<div class="panel-body">
			
		<form class="form-horizontal" method ="POST" action="../excel/productos_mas_vendidos.php">
		<input type="hidden" name="id_cliente" id="id_cliente" >
		<input type="hidden" name="id_producto" id="id_producto" >
			<div class="form-group">
					<div class="col-sm-12">
					  <div class="input-group">
						  <span class="input-group-addon"><b>Cliente</b></span>
							<input type="text" class="form-control input-sm" name="cliente" id="cliente" onkeyup='buscar_clientes();' placeholder='Todos'>  
					  
						  <span class="input-group-addon"><b>Producto</b></span>
							<input type="text" class="form-control input-sm" name="producto" id="producto" onkeyup='buscar_productos();' placeholder='Todos'>  

						<span class="input-group-addon"><b>Del</b></span>
							<input type="text" name="desde" id="desde" class="form-control input-sm text-center datepicker" value="<?php echo date("01"."-m-Y") ?>" autocomplete="off">
						
						<span class="input-group-addon"><b>Al</b></span>
						<input type="text" name="hasta" id="hasta" class="form-control input-sm text-center datepicker" value="<?php echo date("d-m-Y") ?>" autocomplete="off">

						<span class="input-group-addon"><b>Los mejores</b></span>
						<input type="text" name="cantidad" id="cantidad" class="form-control input-sm" value="5" >
						
						<span class="input-group-addon"></span>
						<button type="button" class="btn btn-info form-control input-sm" title="Mostrar resultado" onclick='buscar_mas_vendido();'><span class="glyphicon glyphicon-search" ></span></button>				
						<span class="input-group-addon"></span>
						<button type="submit" class="btn btn-info form-control input-sm" title="Descargar a excel" ><img src="../image/excel.ico" width="15" height="20"><span id="loader"></span></button>					
						
					  </div>
					</div>					
			</div>
		</form>	
			
			<div id='resultados'></div><!-- Carga los datos ajax -->
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
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/notify.js"></script>
 </body>
</html>	

<script>
jQuery(function($){
     $("#desde").mask("99-99-9999");
	 $("#hasta").mask("99-99-9999");
});
  $( function() {
    $( "#desde" ).datepicker({ dateFormat: 'dd-mm-yy' });
    $( "#hasta" ).datepicker({ dateFormat: 'dd-mm-yy' });
  } );
 
 
function buscar_clientes(){
	$("#cliente").autocomplete({
			source:'../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#cliente').val(ui.item.nombre);
				$('#id_cliente').val(ui.item.id);
			}
		});
 }
 

$("#cliente" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#cliente" ).val("");
			$("#id_cliente" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#cliente" ).val("");
			$("#id_cliente" ).val("");
		}
});



function buscar_productos(){
	$("#producto").autocomplete({
			source:'../ajax/productos_autocompletar.php',
			minLength: 2,
			select: function(event, ui) {
				event.preventDefault();
				$('#producto').val(ui.item.nombre);
				$('#id_producto').val(ui.item.id);
			}
		});
 }
 

$("#producto" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#producto" ).val("");
			$("#id_producto" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#producto" ).val("");
			$("#id_producto" ).val("");
		}
});

function buscar_mas_vendido(){
			var desde= $("#desde").val();
			var hasta= $("#hasta").val();
			var cantidad= $("#cantidad").val();
			var id_cliente= $("#id_cliente").val();
			var id_producto= $("#id_producto").val();
			$("#resultados").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_producto_mas_vendido.php?action=producto_mas_vendido&desde='+desde+'&hasta='+hasta+'&cantidad='+cantidad+'&id_cliente='+id_cliente+'&id_producto='+id_producto,
				 beforeSend: function(objeto){
				 $('#resultados').html('Generando...');
				 //$.notify('Generando...','warning');
				 //<img src="../image/ajax-loader.gif">
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#resultados').html('');
					
				}
			});
			
};


</script>