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
  <title>Devolución/Factura</title>
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
		<div class="panel panel-warning">
		<div class="panel-heading">
		    <div class="btn-group pull-right">
			<button type='submit' class="btn btn-warning" data-toggle="modal" data-target="#opcion_consignacion_venta" onclick="carga_modal();"><span class="glyphicon glyphicon-plus" ></span> Nueva devolución/Factura</button>
			</div>
			<h4><i class='glyphicon glyphicon-search'></i> Opciones de consignaciones en ventas</h4>
		</div>			
			<div class="panel-body">
			<?php
				include("../modal/opcion_consignacion_venta.php");
			?>
			<form class="form-horizontal" method ="POST">
						<div class="form-group row">
							<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<input type="hidden" id="ordenado" value="numero_consignacion">
							<input type="hidden" id="por" value="desc">
							<div class="input-group">
								<input type="text" class="form-control" id="q" placeholder="Cliente, Número, Observaciones" onkeyup='load(1);'>
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								  </span>
							</div>
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
$(document).ready(function(){
	load(1);
});

jQuery(function($){
     $("#fecha_opcion_consignacion_salida").mask("99-99-9999"); 
});


function load(page){
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_opcion_consignacion_ventas.php?action=opcion_consignacion_ventas&page='+page+'&q='+q+"&ordenado="+ordenado+"&por="+por,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			
		}
	})
}	


//para buscar los clientes
function buscar_clientes(){
	$("#cliente_opcion_consignacion_venta").autocomplete({
			source:'../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cliente_opcion_consignacion_venta').val(ui.item.id);
				$('#cliente_opcion_consignacion_venta').val(ui.item.nombre);		
				document.getElementById('observacion_opcion_consignacion_venta').focus();
			}
		});

		$("#cliente_opcion_consignacion_venta" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cliente_opcion_consignacion_venta" ).val("");
			$("#cliente_opcion_consignacion_venta" ).val("");
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#cliente_opcion_consignacion_venta" ).val("");
			$("#id_cliente_opcion_consignacion_venta" ).val("");
		}
		});
}

//para cargar el modal
function carga_modal(){
	resetea_datos();
	document.getElementById("titulo_lote").style.display="none";
	document.getElementById("titulo_caducidad").style.display="none";
	document.getElementById("titulo_medida").style.display="none";
	document.getElementById("titulo_existencia").style.display="none";
	document.getElementById("lista_lote").style.display="none";
	document.getElementById("lista_caducidad").style.display="none";
	document.getElementById("lista_medida").style.display="none";
	document.getElementById("lista_existencia").style.display="none";
		//para traer el tipo de configuracion de inventarios, si o no
		var serie_consignacion = $("#serie_opcion_consignacion").val();
		
		$.post( '../ajax/consulta_configuracion_facturacion.php', {opcion_mostrar:'inventario', serie_consultada:serie_consignacion}).done( function(respuesta_inventario)
		{		
			var resultado_inventario = $.trim(respuesta_inventario);
			$('#inventario').val(resultado_inventario);
		});

		//para traer y ver si trabaja con medida
		$.post( '../ajax/consulta_configuracion_facturacion.php', {opcion_mostrar:'medida',serie_consultada:serie_consignacion}).done( function(respuesta_medida)
		{		
			var resultado_medida = $.trim(respuesta_medida);
			$('#muestra_medida').val(resultado_medida);
		});
		
		//para traer y ver si trabaja con lote
		$.post( '../ajax/consulta_configuracion_facturacion.php', {opcion_mostrar:'lote',serie_consultada:serie_consignacion}).done( function(respuesta_lote)
		{		
			var resultado_lote = $.trim(respuesta_lote);
			$('#muestra_lote').val(resultado_lote);
		});
			
		//para traer y ver si trabaja con vencimiento
		$.post( '../ajax/consulta_configuracion_facturacion.php', {opcion_mostrar:'vencimiento',serie_consultada:serie_consignacion}).done( function(respuesta_vencimiento)
		{		
			var resultado_vencimiento = $.trim(respuesta_vencimiento);
			$('#muestra_vencimiento').val(resultado_vencimiento);
		});
}

function eliminar_opcion_detalle_consignacion(id){
	$.ajax({
			url: "../ajax/detalle_consignaciones.php?action=eliminar_item_opcion_consignacion&id_registro="+id,
			 beforeSend: function(objeto){
				$("#muestra_detalle_opciones_consignacion").html("Eliminando...");
			  },
			success: function(data){
				$(".outer_divdet_opciones_consignacion").html(data).fadeIn('fast');
				$('#muestra_detalle_opciones_consignacion').html('');
		  }
	});
}

function resetea_datos(){
	$.ajax({
			url: "../ajax/detalle_consignaciones.php?action=limpiar_info_entrada",
			 beforeSend: function(objeto){
				$("#mensajes_opciones_consignacion_venta").html("Iniciando...");
			  },
			success: function(data){
				$(".outer_divdet_opciones_consignacion").html(data).fadeIn('fast');
				$('#mensajes_opciones_consignacion_venta').html('');
		  }
	});
}


//para buscar productos
function buscar_productos(){
						$("#nombre_producto").autocomplete({
							source: '../ajax/productos_autocompletar_inventario.php',
							minLength: 2,
							select: function(event, ui) {
								event.preventDefault();
								$('#id_producto').val(ui.item.id);
								$('#nombre_producto').val(ui.item.nombre);

								var configuracion_inventario=document.getElementById('inventario').value;
								var configuracion_medida=document.getElementById('muestra_medida').value;
								var configuracion_lote=document.getElementById('muestra_lote').value;
								var configuracion_vencimiento=document.getElementById('muestra_vencimiento').value;
								var producto = $("#id_producto").val();
								var numero_consignacion = $("#numero_consignacion").val();
																
								if ((configuracion_inventario =='NO' || configuracion_inventario =='')){
								document.getElementById("titulo_lote").style.display="none";
								document.getElementById("titulo_caducidad").style.display="none";
								document.getElementById("titulo_medida").style.display="";
								document.getElementById("lista_lote").style.display="none";
								document.getElementById("lista_caducidad").style.display="none";
								document.getElementById("lista_medida").style.display="";							
								var producto = $("#id_producto").val();
							
								//cuando trae se busca el producto me trae que tipo de medida tiene
									$.post( '../ajax/select_tipo_medida.php', {id_producto: producto}).done( function( res_tipos_medidas ){
										$("#medida_agregar").html(res_tipos_medidas);
									});
								}
								
								//aqui controla cuando se selecciona producto y trabaja con inventario
									if (configuracion_inventario =='SI'){

										if(configuracion_medida == "SI"){
											document.getElementById("titulo_medida").style.display="";
											document.getElementById("lista_medida").style.display="";
										}
										if(configuracion_lote=='SI'){
											document.getElementById("titulo_lote").style.display="";
											document.getElementById("lista_lote").style.display="";
										}
										
										if(configuracion_vencimiento=='SI'){
											document.getElementById("titulo_caducidad").style.display="";
											document.getElementById("lista_caducidad").style.display="";
										}
														
										document.getElementById("existencia_consignacion").disabled = true;
																			
										$("#existencia_consignacion" ).val("0");
										var producto = $("#id_producto").val();
									
										//cuando trae se busca el producto me trae que tipo de medida tiene
											$.post( '../ajax/select_tipo_medida.php', {id_producto: producto}).done( function( res_id_medidas ){
												$("#medida_agregar").html(res_id_medidas);
											});	
									
									//de aqui para abajo buscar saldos en las consignaciones entregadas
									//para que se cargue el stock del producto al momento de buscar el producto dependiendo de la bodega que esta seleeccionada por default
										$.post( '../ajax/saldo_producto_consignaciones.php', {action:'saldo_consignacion_venta', numero_consignacion: numero_consignacion, id_producto: producto}).done( function( respuesta ){
										var saldo_producto = respuesta;
										$("#existencia_consignacion").val(saldo_producto);
										$('#stock_tmp').val(saldo_producto);
										});
										
									//para traer todos los lotes en base a una orden al momento de buscar un producto
									$.post( '../ajax/saldo_producto_consignaciones.php', {action:'consignacion_venta_lotes', numero_consignacion: numero_consignacion, id_producto: producto}).done( function( res_opciones_lote ){
										$("#lote_agregar").html(res_opciones_lote);
									});

									//para traer todos las caducidades en base a una orden al momento de buscar un producto
									$.post( '../ajax/saldo_producto_consignaciones.php', {action:'consignacion_venta_caducidades', numero_consignacion: numero_consignacion, id_producto: producto}).done( function( res_opciones_caducidad ){
											$("#caducidad_agregar").html(res_opciones_caducidad);
										});											
																					
										document.getElementById("titulo_existencia").style.display="";
										document.getElementById("lista_existencia").style.display="";
																				
									}
								//hasta aqui me controla si trabaja con inventario
								document.getElementById('cantidad_agregar').focus();
							}
						});
						
				$( "#nombre_producto" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
						
				$("#nombre_producto" ).on( "keydown", function( event ) {
					if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
					{
						$("#id_producto" ).val("");
						$("#nombre_producto" ).val("");
						$("#existencia_consignacion" ).val("");
						$("#medida_agregar" ).val("");
						$("#stock_tmp" ).val("");						
					}
			});
			
}	

	
//agrega un item
function agregar_item(){
			var numero_consignacion= $("#numero_consignacion").val();
			var id_producto= $("#id_producto").val();
			var lote_agregar= $("#lote_agregar").val();
			var cantidad_agregar= $("#cantidad_agregar").val();
			var caducidad_agregar= $("#caducidad_agregar").val();
			var medida_agregar= $("#medida_agregar").val();
			var existencia_producto=document.getElementById('existencia_consignacion').value;
			var configuracion_inventario = $("#inventario").val();
			var control_lote=document.getElementById('muestra_lote').value;
			var control_caducidad=document.getElementById('muestra_vencimiento').value;
			var control_medida=document.getElementById('muestra_medida').value;
			//Inicia validacion
			if (numero_consignacion ==''){
			alert('Ingrese número de consignación');
			document.getElementById('numero_consignacion').focus();
			return false;
			}
			if (isNaN(numero_consignacion)){
			alert('El dato ingresado en número de consignación, no es un número');
			document.getElementById('numero_consignacion').focus();
			return false;
			}
			if (id_producto ==''){
			alert('Ingrese producto');
			document.getElementById('nombre_producto').focus();
			return false;
			}
			if (cantidad_agregar ==''){
			alert('Ingrese cantidad');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			
			if (isNaN(cantidad_agregar)){
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			
			if (configuracion_inventario =='SI' && control_lote=='SI' && lote_agregar=='0' ){
			alert('Seleccione un lote');
			document.getElementById('lote_agregar').focus();
			return false;
			}
			
			if (configuracion_inventario =='SI' && control_caducidad=='SI' && caducidad_agregar=='0' ){
			alert('Seleccione fecha de vencimiento');
			document.getElementById('caducidad_agregar').focus();
			return false;
			}
			
			if (parseFloat(cantidad_agregar) > parseFloat(existencia_producto) && configuracion_inventario =='SI'){
			alert('El saldo en consignación es menor a la cantidad ingresada');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}

			//Fin validacion
			$("#muestra_detalle_opciones_consignacion").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_consignaciones.php?action=agregar_detalle_opcion_consignacion_venta&id_producto="+id_producto+"&cantidad_agregar="+cantidad_agregar+"&numero_consignacion="+numero_consignacion+"&medida_agregar="+medida_agregar+"&lote_agregar="+lote_agregar+"&caducidad_agregar="+caducidad_agregar+"&inventario="+inventario,
					 beforeSend: function(objeto){
						$("#muestra_detalle_opciones_consignacion").html("Cargando detalle...");
					  },
					success: function(data){
						$(".outer_divdet_opciones_consignacion").html(data).fadeIn('fast');
						$('#muestra_detalle_opciones_consignacion').html('');
						document.getElementById("nombre_producto").value = "";
						document.getElementById("cantidad_agregar").value = "";
						document.getElementById("medida_agregar").value = "";
						document.getElementById("lote_agregar").value = "";
						document.getElementById("existencia_consignacion").value = "";
						document.getElementById("numero_consignacion").value = "";
						document.getElementById("id_producto").value = "";
						document.getElementById('numero_consignacion').focus();
				  }
			});
}

//para guardar la consignacion de opciones de ventas, devolucion o factura

$( "#guardar_opcion_consignacion_venta" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/guardar_opcion_consignacion_ventas.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#mensajes_opciones_consignacion_venta").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#mensajes_opciones_consignacion_venta").html(datos);
			$('#guardar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
});


//eliminar opcion consignacion_ventas
function eliminar_opcion_consignacion_ventas(codigo){
			var q= $("#q").val();
		if (confirm("Realmente desea anular el registro?")){	
			$.ajax({
			type: "GET",
			url: "../ajax/detalle_consignaciones.php",
			data: "action=eliminar_opcion_consignacion_ventas&codigo_unico="+codigo,"q":q,
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


//DETALLE opciones consignacion_ventas
function mostrar_detalle_opcion_consignacion(codigo){
	$.ajax({
		url: "../ajax/detalle_consignaciones.php?action=mostrar_detalle_opcion_consignacion&codigo_unico="+codigo,
		 beforeSend: function(objeto){
			$("#loaderdet").html("Iniciando...");
		  },
		success: function(data){
			$(".outer_divdet").html(data).fadeIn('fast');
			$('#loaderdet').html('');
	  }
	});
}


</script>