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
  <title>Orden Servicio</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include("../modal/nueva_orden_mecanica.php");
	include("../modal/detalle_orden_mecanica.php");
	include("../modal/detalle_factura_mecanica.php");
	include("../modal/buscar_agregar_editar_cliente.php");
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
					<a href="#" data-toggle="modal" data-target="#nuevaOrdenMecanica" class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nueva orden</a>
				</div>
				<h4><i class='glyphicon glyphicon-search'></i> Buscar Ordenes</h4>	
			</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form" id="datos_cotizacion">
							<div class="form-group row">
								<label for="q" class="col-md-2 control-label">Buscar:</label>
								<div class="col-md-5">
								<input type="hidden" id="ordenado" value="id_enc_mecanica">
								<input type="hidden" id="por" value="desc">
								
								<div class="input-group">
								<input type="text" class="form-control" id="q" placeholder="Cliente, usuario, placa..." onkeyup='load(1);'>
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								  </span>
								</div>
								</div>
					
								<div class="col-md-3">
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
	<link rel="stylesheet" href="../css/jquery-ui.css">
	<script src="../js/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>
 </body>
</html>
<script>

$( function() {
$("#fecha_entrada_vehiculo").datepicker({
        dateFormat: "dd-mm-yy",
        firstDay: 1,
        dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
        dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
        monthNames: 
            ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
            "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        monthNamesShort: 
            ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
            "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]
});


jQuery(function($){
     $("#fecha_entrada_vehiculo").mask("99-99-9999");
	 $("#hora_entrada").mask("99:99");
	 $("#mod_fecha_entrada").mask("99-99-9999");
	 $("#mod_hora_entrada").mask("99:99");
	 $("#mod_fecha_entrega").mask("99-99-9999");
	 $("#mod_hora_entrega").mask("99:99");
	 $("#placa").mask("***-****");
	 $("#proxima_cita").mask("99-99-9999");
	 $("#fecha_mecanica").mask("99-99-9999");
	 
});
})
	
//esto es para que al momento de abrir el modulo se carguen los datos automaticamente
$(document).ready(function(){
	//document.getElementById('q').focus();
	load(1);
});
	
function load(page){
	var por= $("#por").val();
	var ordenado= $("#ordenado").val();
	var q= $("#q").val();	
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_orden_mecanica.php?action=ordenes_mecanica&page='+page+'&q='+q+"&por="+por+"&ordenado="+ordenado,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
		}
	})
}


$( "#guardar_orden_mecanica" ).submit(function( event ) {
	 $('#guardar_datos').attr("disabled", true);
	 var parametros = $(this).serialize();
		 $.ajax({
				type: "POST",
				url: "../ajax/guardar_orden_mecanica.php",
				data: parametros,
				 beforeSend: function(objeto){
					$("#resultados_ajax_mecanica").html("Mensaje: Cargando...");
				  },
				success: function(datos){
				$("#resultados_ajax_mecanica").html(datos);
				$('#guardar_datos').attr("disabled", false);
				load(1);
			  }
		});
	  event.preventDefault();
})

//pasa los datos del cliente a buscar clientes o editar
function agrega_datos_facturacion(id_reg){
		var id_cliente = $("#id_cliente"+id_reg).val();
		var tipo_id = $("#tipo_id"+id_reg).val();
		var ruc_cliente = $("#ruc_cliente"+id_reg).val();
		var nombre_cliente = $("#nombre_cliente"+id_reg).val();
		var telefono_cliente = $("#telefono_cliente"+id_reg).val();
		var direccion_cliente = $("#direccion_cliente"+id_reg).val();
		var plazo_cliente = $("#plazo_cliente"+id_reg).val();
		var email_cliente = $("#mail_cliente"+id_reg).val();
		$("#id_registro").val(id_reg);
		$("#id_cliente").val(id_cliente);
		$("#tipo_id_cliente").val(tipo_id);
		$("#ruc_cliente").val(ruc_cliente);
		$("#nombre_cliente").val(nombre_cliente);
		$("#telefono_cliente").val(telefono_cliente);
		$("#direccion_cliente").val(direccion_cliente);
		$("#plazo_cliente").val(plazo_cliente);
		$("#email_cliente").val(email_cliente);
	document.getElementById("ruc_cliente").readOnly = true;
}
	
$("#borrar_datos").click(function(){
	$("#id_cliente" ).val("");
	$("#buscar_cliente" ).val("");
	$("#tipo_id_cliente" ).val("");
	$("#ruc_cliente" ).val("");
	$("#nombre_cliente" ).val("");
	$("#telefono_cliente" ).val("");
	$("#direccion_cliente" ).val("");
	$("#plazo_cliente" ).val("5");
	$("#email_cliente" ).val("");
	document.getElementById("tipo_id_cliente").disabled = false;
	document.getElementById("ruc_cliente").readOnly = false;
    });

	//para buscar los clientes
function buscar_clientes(){
	$("#buscar_cliente").autocomplete({
			source:'../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cliente').val(ui.item.id);
				$('#nombre_cliente').val(ui.item.nombre);
				$('#buscar_cliente').val(ui.item.nombre);
				$('#ruc_cliente').val(ui.item.ruc);
				$('#telefono_cliente').val(ui.item.telefono);
				$('#direccion_cliente').val(ui.item.direccion);
				$('#plazo_cliente').val(ui.item.plazo);
				$('#email_cliente').val(ui.item.email);
				$('#tipo_id_cliente').val(ui.item.tipo_id);
				
			}
		});

		$("#buscar_cliente" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cliente" ).val("");
			$("#buscar_cliente" ).val("");
			$("#nombre_cliente" ).val("");
			$("#ruc_cliente" ).val("");
			$("#telefono_cliente" ).val("");
			$("#direccion_cliente" ).val("");
			$("#plazo_cliente" ).val("");
			$("#email_cliente" ).val("");

		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#id_cliente" ).val("");
			$("#buscar_cliente" ).val("");
			$("#nombre_cliente" ).val("");
			$("#ruc_cliente" ).val("");
			$("#telefono_cliente" ).val("");
			$("#direccion_cliente" ).val("");
			$("#plazo_cliente" ).val("");
			$("#email_cliente" ).val("");
		}
		});
}

//para asignar un cliente a
$( "#guardar_cliente" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/actualiza_cliente_mecanica.php?action=agrega_cliente",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_busqueda_cliente").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_busqueda_cliente").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

function detalle_orden(id){
		var fecha_recepcion = $("#fecha_recepcion"+id).val();
		var hora_recepcion = $("#hora_recepcion"+id).val();
		var fecha_entrega = $("#fecha_entrega"+id).val();
		var hora_entrega = $("#hora_entrega"+id).val();
		var placa = $("#placa"+id).val();
		var marca = $("#marca"+id).val();
		var anio = $("#anio"+id).val();
		var chasis = $("#chasis"+id).val();
		var propietario = $("#propietario"+id).val();
		var nombre_usuario = $("#nombre_usuario"+id).val();
		var contacto_usuario = $("#contacto_usuario"+id).val();
		var correo_usuario = $("#correo_usuario"+id).val();
		var codigo_unico = $("#codigo_unico"+id).val();
		var proximo_chequeo = $("#proximo_chequeo"+id).val();
		var obs_proximo_chequeo = $("#obs_proximo_chequeo"+id).val();
		var estado = $("#estado"+id).val();
		$("#mod_fecha_entrada").val(fecha_recepcion);
		$("#mod_hora_entrada").val(hora_recepcion);
		$("#mod_fecha_entrega").val(fecha_entrega);
		$("#mod_hora_entrega").val(hora_entrega);
		$("#mod_placa").val(placa);
		$("#mod_marca").val(marca);
		$("#mod_anio").val(anio);
		$("#mod_chasis").val(chasis);
		$("#mod_propietario").val(propietario);
		$("#mod_usuario").val(nombre_usuario);
		$("#mod_telefono").val(contacto_usuario);
		$("#mod_correo_usuario").val(correo_usuario);
		$("#mod_codigo_unico").val(codigo_unico);
		$("#proxima_cita").val(proximo_chequeo);
		$("#obs_proxima_cita").val(obs_proximo_chequeo);
		$("#mod_estado").val(estado);
		detalle_observaciones_mecanica(id);
}

function detalle_observaciones_mecanica(id){
		var codigo_unico = $("#codigo_unico"+id).val();		
		$("#muestra_detalle_observaciones_mecanica").fadeIn('fast');
		$.ajax({
			url:'../ajax/detalle_observaciones_mecanica.php?action=detalle_observaciones&codigo_unico='+codigo_unico,
			 beforeSend: function(objeto){
			 $('#muestra_detalle_observaciones_mecanica').html('<img src="../image/ajax-loader.gif"> Cargando... por favor espere a que se cargue la información.');
		  },
			success:function(data){
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_observaciones_mecanica').html('');
			}
		})
}

function agregar_detalle_observaciones(){
			var codigo_unico = $("#mod_codigo_unico").val();
			var concepto= $("#concepto").val();
			var detalle= $("#detalle").val();
			//Inicia validacion
			if (concepto ==''){
			alert('Ingrese concepto de observaciones');
			document.getElementById('concepto').focus();
			return false;
			}
			if (detalle ==''){
			alert('Ingrese detalle de la observación');
			document.getElementById('detalle').focus();
			return false;
			}
			
			//Fin validacion
			$("#muestra_detalle_observaciones_mecanica").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_observaciones_mecanica.php?action=agregar_observaciones&codigo_unico="+codigo_unico+"&concepto="+concepto+"&detalle="+detalle,
					 beforeSend: function(objeto){
						$("#muestra_detalle_observaciones_mecanica").html("Cargando detalle...");
					  },
					success: function(data){
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#muestra_detalle_observaciones_mecanica').html('');
						document.getElementById("concepto").value = "";
						document.getElementById("detalle").value = "";
				  }
			});
}

function agregar_item_factura_mecanica(){
			var codigo_unico = $("#codigo_unico_factura").val();
			var id_producto_mecanica= $("#id_producto_mecanica").val();
			var cantidad_agregar= $("#cantidad_agregar").val();
			var precio_agregar= $("#precio_agregar").val();
			var fecha_emision= $("#fecha_mecanica").val();
			var bodega_agregar= $("#bodega_mecanica").val();
			var medida_agregar= $("#medida_agregar").val();
			var tipo_producto_mecanica= $("#tipo_producto_mecanica").val();
			var serie_mecanica= $("#serie_mecanica").val();
			var configuracion_inventario="SI";//document.getElementById('inventario').value;
			var tipo_producto_agregar = $("#tipo_producto_mecanica").val();
			var inventario = "SI";//$("#inventario").val();
			//Inicia validacion
			if (id_producto_mecanica ==''){
			alert('Ingrese producto o servicio');
			document.getElementById('nombre_producto_servicio').focus();
			return false;
			}
			if (cantidad_agregar ==''){
			alert('Ingrese cantidad');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			
			if (precio_agregar ==''){
			alert('Ingrese precio');
			document.getElementById('precio_agregar').focus();
			return false;
			}
			if (isNaN(cantidad_agregar)){
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			if (isNaN(precio_agregar)){
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio_agregar').focus();
			return false;
			}
			//Fin validacion
			$("#muestra_detalle_factura_mecanica").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_observaciones_mecanica.php?action=agregar_detalle_factura&codigo_unico="+codigo_unico+"&id_producto_mecanica="+id_producto_mecanica+"&cantidad_agregar="+cantidad_agregar+"&precio_agregar="+precio_agregar+"&fecha_emision="+fecha_emision+"&bodega_agregar="+bodega_agregar+"&tipo_producto_mecanica="+tipo_producto_mecanica+"&serie_mecanica="+serie_mecanica+"&medida_agregar="+medida_agregar+"&inventario="+inventario+"&tipo_producto_agregar="+tipo_producto_agregar,
					 beforeSend: function(objeto){
						$("#muestra_detalle_factura_mecanica").html("Cargando detalle...");
					  },
					success: function(data){
						$(".outer_divdet_mecanica").html(data).fadeIn('fast');
						$('#muestra_detalle_factura_mecanica').html('');
						document.getElementById("nombre_producto_servicio").value = "";
						document.getElementById("cantidad_agregar").value = "";
						document.getElementById("precio_agregar").value = "";
						document.getElementById("precio_agregar_iva").value = "";
						document.getElementById("medida_agregar").value = "";
						document.getElementById("porcentaje_iva").value = "";
						document.getElementById("existencia_producto").value = "";
						document.getElementById("id_producto_mecanica").value = "";
						document.getElementById('nombre_producto_servicio').focus();
				  }
			});
}

function eliminar_detalle_observaciones(id){	
var codigo_unico = $("#mod_codigo_unico").val();
if (confirm("Realmente desea eliminar el registro?")){
	$("#muestra_detalle_observaciones_mecanica").fadeIn('fast');
	 $.ajax({
			url: "../ajax/detalle_observaciones_mecanica.php?action=eliminar_observaciones&id_registro="+id+"&codigo_unico="+codigo_unico,
			 beforeSend: function(objeto){
				$("#muestra_detalle_observaciones_mecanica").html("Cargando detalle...");
			  },
			success: function(data){
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_observaciones_mecanica').html('');
		  }
	});
}
}

function eliminar_detalle_factura(id){	
var codigo_unico = $("#codigo_unico_factura").val();
if (confirm("Realmente desea eliminar el registro?")){
	$("#muestra_detalle_mecanica").fadeIn('fast');
	 $.ajax({
			url: "../ajax/detalle_observaciones_mecanica.php?action=eliminar_detalle_factura&id_registro="+id+"&codigo_unico="+codigo_unico,
			 beforeSend: function(objeto){
				$("#muestra_detalle_mecanica").html("Cargando detalle...");
			  },
			success: function(data){
					$(".outer_divdet_mecanica").html(data).fadeIn('fast');
					$('#muestra_detalle_mecanica').html('');
		  }
	});
}
}

function actualizar_fechas(){	
var codigo_unico = $("#mod_codigo_unico").val();
var fecha_entrada = $("#mod_fecha_entrada").val();
var hora_entrada = $("#mod_hora_entrada").val();
var fecha_salida = $("#mod_fecha_entrega").val();
var hora_salida = $("#mod_hora_entrega").val();
var estado = $("#mod_estado").val();

			if (fecha_entrada ==''){
			alert('Ingrese fecha de ingreso al taller.');
			document.getElementById('mod_fecha_entrada').focus();
			return false;
			}
			if (hora_entrada ==''){
			alert('Ingrese hora de entrada al taller.');
			document.getElementById('mod_hora_entrada').focus();
			return false;
			}
			if (fecha_salida ==''){
			alert('Ingrese hora de salida del taller.');
			document.getElementById('mod_fecha_entrega').focus();
			return false;
			}
			if (hora_salida ==''){
			alert('Ingrese hora de salida del taller.');
			document.getElementById('mod_hora_entrega').focus();
			return false;
			}
	  $.ajax({
				type: "POST",
				url: "../ajax/detalle_observaciones_mecanica.php?action=actualizar_fechas",
				data: "codigo_unico="+codigo_unico+"&fecha_entrada="+fecha_entrada+"&hora_entrada="+hora_entrada+"&fecha_salida="+fecha_salida+"&hora_salida="+hora_salida+"&estado="+estado,
				 beforeSend: function(objeto){
					$("#resultados_fechas_mecanica").html("Actualizando...");
				  },
				success: function(datos){
				$("#resultados_fechas_mecanica").html(datos);
				load(1);
			  }
			});
}

function actualizar_vehiculo(){	
var codigo_unico = $("#mod_codigo_unico").val();
var mod_placa = $("#mod_placa").val();
var mod_marca = $("#mod_marca").val();
var mod_anio = $("#mod_anio").val();
var mod_propietario = $("#mod_propietario").val();
var mod_chasis = $("#mod_chasis").val();

			if (mod_placa ==''){
			alert('Ingrese placa.');
			document.getElementById('mod_placa').focus();
			return false;
			}
			if (mod_marca ==''){
			alert('Ingrese marca.');
			document.getElementById('mod_marca').focus();
			return false;
			}
			if (mod_anio ==''){
			alert('Ingrese año.');
			document.getElementById('mod_anio').focus();
			return false;
			}
			if (mod_propietario ==''){
			alert('Ingrese nombre del propietario.');
			document.getElementById('mod_propietario').focus();
			return false;
			}
			if (mod_chasis ==''){
			alert('Ingrese chasis.');
			document.getElementById('mod_chasis').focus();
			return false;
			}
	  $.ajax({
				type: "POST",
				url: "../ajax/detalle_observaciones_mecanica.php?action=actualizar_vehiculo",
				data: "codigo_unico="+codigo_unico+"&mod_placa="+mod_placa+"&mod_marca="+mod_marca+"&mod_anio="+mod_anio+"&mod_propietario="+mod_propietario+"&mod_chasis="+mod_chasis,
				 beforeSend: function(objeto){
					$("#resultados_vehiculo_mecanica").html("Actualizando...");
				  },
				success: function(datos){
				$("#resultados_vehiculo_mecanica").html(datos);
				load(1);
			  }
			});
}

function actualizar_usuario(){	
var codigo_unico = $("#mod_codigo_unico").val();
var mod_usuario = $("#mod_usuario").val();
var mod_telefono = $("#mod_telefono").val();
var mod_correo_usuario = $("#mod_correo_usuario").val();

			if (mod_usuario ==''){
			alert('Ingrese nombre de usuario.');
			document.getElementById('mod_usuario').focus();
			return false;
			}

	  $.ajax({
				type: "POST",
				url: "../ajax/detalle_observaciones_mecanica.php?action=actualizar_usuario",
				data: "codigo_unico="+codigo_unico+"&mod_usuario="+mod_usuario+"&mod_telefono="+mod_telefono+"&mod_correo_usuario="+mod_correo_usuario,
				 beforeSend: function(objeto){
					$("#resultados_usuario_mecanica").html("Actualizando...");
				  },
				success: function(datos){
				$("#resultados_usuario_mecanica").html(datos);
				load(1);
			  }
			});
}

function actualizar_proxima_cita(){	
var codigo_unico = $("#mod_codigo_unico").val();
var proxima_cita = $("#proxima_cita").val();
var obs_proxima_cita = $("#obs_proxima_cita").val();

			if (proxima_cita ==''){
			alert('Ingrese fecha.');
			document.getElementById('proxima_cita').focus();
			return false;
			}
			
	  $.ajax({
				type: "POST",
				url: "../ajax/detalle_observaciones_mecanica.php?action=actualizar_proxima_cita",
				data: "codigo_unico="+codigo_unico+"&proxima_cita="+proxima_cita+"&obs_proxima_cita="+obs_proxima_cita,
				 beforeSend: function(objeto){
					$("#resultados_proxima_cita_mecanica").html("Actualizando...");
				  },
				success: function(datos){
				$("#resultados_proxima_cita_mecanica").html(datos);
				load(1);
			  }
			});
}

function eliminar_orden_total(id){	
if (confirm("Realmente desea eliminar toda la orden?")){
	$("#resultados").fadeIn('fast');
	 $.ajax({
			url: "../ajax/detalle_observaciones_mecanica.php?action=eliminar_orden_total&id_registro="+id,
			 beforeSend: function(objeto){
				$("#resultados").html("Cargando detalle...");
			  },
			success: function(data){
					$(".outer_div").html(data).fadeIn('fast');
					$('#resultados').html('');
		  }
	});
}
}

function detalle_factura_mecanica(id){
	var cliente = $("#nombre_cliente"+id).val();
	var id_cliente = $("#id_cliente"+id).val();
	var codigo_unico = $("#codigo_unico"+id).val();
	
		$("#cliente_mecanica").val(cliente);
		$("#id_cliente_mecanica").val(id_cliente);
		$("#codigo_unico_factura").val(codigo_unico);
		opciones_facturacion();
		var codigo_unico = $("#codigo_unico"+id).val();		
		$("#muestra_detalle_mecanica").fadeIn('fast');
		$.ajax({
			url:'../ajax/detalle_observaciones_mecanica.php?action=detalle_factura_mecanica&codigo_unico='+codigo_unico,
			 beforeSend: function(objeto){
			 $('#muestra_detalle_mecanica').html('<img src="../image/ajax-loader.gif"> Cargando... por favor espere a que se cargue la información.');
		  },
			success:function(data){
				$(".outer_divdet_mecanica").html(data).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');
			}
		})
}


function buscar_productos(){
        //para usar el lector de barras
        var keycode = event.keyCode;
		var codigo_producto = $("#nombre_producto_servicio").val();
		var bodega = $("#bodega_mecanica").val();
	
        if (keycode == '13') {
			let request = (window.XMLHttpRequest) ? 
                            new XMLHttpRequest() : 
                            new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = '../ajax/buscar_orden_mecanica.php?action=bar_code&codigo_producto='+codigo_producto; 
            request.open("GET",ajaxUrl,true);
            request.send();
            request.onreadystatechange = function(){
                if(request.readyState == 4 && request.status == 200){
                    let objData = JSON.parse(request.responseText);
                    if(objData.status){
						let objProducto = objData;

						$.post( '../ajax/saldo_producto_inventario.php', {id_bodega_mecanica: bodega, id_producto_mecanica: objProducto.id_producto}).done( function( respuesta ){
								var saldo_producto = respuesta;
								$("#existencia_producto").val(saldo_producto);
								$('#stock_tmp').val(saldo_producto);
								});

			              document.querySelector("#id_producto_mecanica").value = objProducto.id_producto;
						  document.querySelector("#nombre_producto_servicio").value = objProducto.nombre_producto;
						  document.querySelector("#precio_agregar").value = objProducto.precio_producto;
						  document.querySelector("#precio_agregar_iva").value = objProducto.precio_iva;
						  document.querySelector("#porcentaje_iva").value = objProducto.porcentaje_iva;
						  document.querySelector("#medida_agregar").value = objProducto.id_medida;
						  document.querySelector("#tipo_producto_mecanica").value = objProducto.tipo_produccion;
						  document.querySelector("#cantidad_agregar").value =1;
						  document.getElementById('cantidad_agregar').focus();
                     }else{
                        $.notify(objData.msg, "error");
                    }
                }
                  return false;
            }
		}
		

				$("#nombre_producto_servicio").autocomplete({
					source: '../ajax/productos_autocompletar.php',
					minLength: 2,
					select: function(event, ui) {
						event.preventDefault();
						$('#id_producto_mecanica').val(ui.item.id);
						$('#nombre_producto_servicio').val(ui.item.nombre);
						$('#precio_agregar').val(ui.item.precio);
						$('#precio_tmp').val(ui.item.precio);
						$('#tipo_producto_mecanica').val(ui.item.tipo);
						$('#medida_agregar').val(ui.item.medida);
						$('#porcentaje_iva').val(ui.item.porcentaje_iva);
						$('#precio_agregar_iva').val(ui.item.precio_iva);

						var configuracion_inventario="SI";//document.getElementById('inventario').value;
						//var configuracion_bodega=document.getElementById('muestra_bodega').value;
						var tipo_producto = $("#tipo_producto_mecanica").val();
															
						//aqui controla cuando se selecciona producto y trabaja con inventario
						
							if (tipo_producto=="01" && configuracion_inventario =='SI'){
								//if(configuracion_bodega=='SI'){
								//	document.getElementById("titulo_bodega").style.display="";
									//document.getElementById("lista_bodega").style.display="";
								//}
								document.getElementById("precio_agregar").disabled = false;
								//document.getElementById("existencia_producto").disabled = true;
																	
								$("#existencia_producto" ).val("0");
								//var bodega = $("#bodega_mecanica").val();
								var producto = $("#id_producto_mecanica").val();
							
							//para que se cargue el stock del producto al momento de buscar el producto dependiendo de la bodega que esta seleeccionada por default
							
							$.post( '../ajax/saldo_producto_inventario.php', {id_bodega_mecanica: bodega, id_producto_mecanica: producto}).done( function( respuesta ){
								var saldo_producto = respuesta;
								$("#existencia_producto").val(saldo_producto);
								$('#stock_tmp').val(saldo_producto);
								});
									
							}
							
						//hasta aqui me controla si trabaja con inventario
						document.getElementById('cantidad_agregar').focus();
					}
					
				});
				
		//$( "#nombre_producto_servicio" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar		
		$("#nombre_producto_servicio" ).on( "keydown", function( event ) {
			if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
			{
				$("#id_producto_mecanica" ).val("");
				$("#nombre_producto_servicio" ).val("");
				$("#precio_agregar" ).val("");
				$("#tipo_producto_mecanica" ).val("");
				$("#existencia_producto" ).val("");
				$("#medida_agregar" ).val("");
				$("#cantidad_agregar" ).val("");
				$("#stock_tmp" ).val("");						
			}
		});
}


//para saber si tiene opcion de trabajar con inventarios
function opciones_facturacion(){
		document.getElementById("titulo_bodega").style.display="none";
		//document.getElementById("titulo_existencia").style.display="none";
		//document.getElementById("lista_bodega").style.display="none";
		//document.getElementById("lista_existencia").style.display="none";
		
		var id_serie = $("#serie_mecanica").val();
		//para traer el tipo de configuracion de inventarios, si o no
		$.post( '../ajax/consulta_configuracion_facturacion.php', {opcion_mostrar:'inventario',serie_consultada:id_serie}).done( function(respuesta_inventario)
		{		
			var resultado_inventario = $.trim(respuesta_inventario);
			$('#inventario').val(resultado_inventario);
		});
					
		document.getElementById('nombre_producto_servicio').focus();	
}

	
$( function(){
	//para cuando se cambia el select de bodega que me cargue el saldo de ese producto
	$('#bodega_mecanica').change(function(){
		var bodega = $("#bodega_mecanica").val();
		var producto = $("#id_producto_mecanica").val();
		var id_medida = $("#medida_agregar").val();
		
			//reinicia la medida
			$.post( '../ajax/select_tipo_medida.php', {id_producto: producto}).done( function( res_id_medidas ){
				$("#medida_agregar").html(res_id_medidas);
			});
			
			//trae la existencia en base a la bodega
			$.post( '../ajax/saldo_producto_inventario.php', {id_bodega: bodega, id_producto: producto}).done( function( respuesta ){
			var saldo_producto = respuesta;
			$("#existencia_producto").val(saldo_producto);
			$('#stock_tmp').val(saldo_producto);			
			});
				
	});

});
	
function generar_factura(){
	var orden = false;
	var codigo_unico_factura = document.getElementById('codigo_unico_factura').value;
	let request = (window.XMLHttpRequest) ? 
                            new XMLHttpRequest() : 
                            new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = '../ajax/buscar_orden_mecanica.php?action=buscar_orden&codigo='+codigo_unico_factura; 
            request.open("GET",ajaxUrl,true);
            request.send();
            request.onreadystatechange = function(){
                if(request.readyState == 4 && request.status == 200){
                    let objData = JSON.parse(request.responseText);
                    if(objData.status){
						
						if (confirm("La orden ya tiene "+ objData.msg +", desea volver a generar?")){
							guardar_factura();
						}
							             
                     }else{
						if (confirm("Al generar la factura la orden se cierra y no es posible agregar ningun detalle adicional, desea generar?")){
                        guardar_factura();
						}
                    }
                }
                  return false;
            }
}

function guardar_factura(){
	var codigo_unico_factura = document.getElementById('codigo_unico_factura').value;
	var id_cliente_mecanica = document.getElementById('id_cliente_mecanica').value;
	var fecha_mecanica = document.getElementById('fecha_mecanica').value;
	var serie_mecanica = document.getElementById('serie_mecanica').value;
	var total_factura = document.getElementById('total_factura').value;
	
			$('#guardar_datos_factura').attr("disabled", true); 	  
			//var parametros = $(this).serialize();
				$.ajax({
						type: "POST",
						url: "../ajax/generar_factura_mecanica.php",
						data: "codigo_unico_factura="+codigo_unico_factura+"&id_cliente_mecanica="+id_cliente_mecanica
						+"&fecha_mecanica="+fecha_mecanica+"&serie_mecanica="+serie_mecanica+"&total_factura="+total_factura,
						beforeSend: function(objeto){
							$("#mensajes_ordenes_mecanica").html("Mensaje: Guardando...");
						},
						success: function(datos){
						$("#mensajes_ordenes_mecanica").html(datos);
						$('#guardar_datos_factura').attr("disabled", false);
						load(1);
					}
				});
				event.stopPropagation();
		  		event.preventDefault();
}


function generar_recibo(){
	var orden = false;
	var codigo_unico_factura = document.getElementById('codigo_unico_factura').value;
	let request = (window.XMLHttpRequest) ? 
                            new XMLHttpRequest() : 
                            new ActiveXObject('Microsoft.XMLHTTP');
            let ajaxUrl = '../ajax/buscar_orden_mecanica.php?action=buscar_orden&codigo='+codigo_unico_factura; 
            request.open("GET",ajaxUrl,true);
            request.send();
            request.onreadystatechange = function(){
                if(request.readyState == 4 && request.status == 200){
                    let objData = JSON.parse(request.responseText);
                    if(objData.status){
						
						if (confirm("La orden ya tiene "+ objData.msg +", desea volver a generar?")){
							guardar_recibo();
						}
							             
                     }else{
						if (confirm("Al generar el recibo de venta la orden se cierra y no es posible agregar ningun detalle adicional, desea generar?")){
							guardar_recibo();
						}
                    }
                }
                  return false;
            }
}

function guardar_recibo(){
	var codigo_unico_factura = document.getElementById('codigo_unico_factura').value;
	var id_cliente_mecanica = document.getElementById('id_cliente_mecanica').value;
	var fecha_mecanica = document.getElementById('fecha_mecanica').value;
	var serie_mecanica = document.getElementById('serie_mecanica').value;
	var total_factura = document.getElementById('total_factura').value;
	
	//if (confirm("Al generar el recibo de venta la orden se cierra y no es posible agregar ningun detalle adicional, desea generar?")){
		  $('#guardar_datos_recibo').attr("disabled", true); 	  
		 //var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/generar_recibo_mecanica.php",
					data: "codigo_unico_factura="+codigo_unico_factura+"&id_cliente_mecanica="+id_cliente_mecanica
					+"&fecha_mecanica="+fecha_mecanica+"&serie_mecanica="+serie_mecanica+"&total_factura="+total_factura,
					 beforeSend: function(objeto){
						$("#mensajes_ordenes_mecanica").html("Mensaje: Guardando...");
					  },
					success: function(datos){
					$("#mensajes_ordenes_mecanica").html(datos);
					$('#guardar_datos_recibo').attr("disabled", false);
					load(1);
				  }
			});
			event.stopPropagation();
		  	event.preventDefault();
	//}
}


    //calcular el precio sin iva al ingresar un producto
    function precio_sin_iva() {
        var porcentaje_item = document.getElementById('porcentaje_iva').value;
        var precio_sin_iva = document.getElementById('precio_agregar').value;
 
        if (isNaN(precio_sin_iva)) {
            alert('El precio ingresado, no es un número');
            document.getElementById('precio_agregar').focus();
            return false;
        }

        if ((precio_sin_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            document.getElementById('precio_agregar').focus();
            return false;
        }

        var precio_con_iva = (parseFloat(precio_sin_iva) + (parseFloat(precio_sin_iva) * parseFloat(porcentaje_item)));
        $("#precio_agregar_iva").val(precio_con_iva.toFixed(4));

    }

    //calcular el precio con iva al momento de ingresar un producto
    function precio_con_iva() {
        var porcentaje_item = document.getElementById('porcentaje_iva').value;
        var precio_con_iva = document.getElementById('precio_agregar_iva').value;

        if (isNaN(precio_con_iva)) {
            alert('El precio ingresado, no es un número');
            document.getElementById('precio_agregar_iva').focus();
            return false;
        }

        if ((precio_con_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            document.getElementById('precio_agregar_iva').focus();
            return false;
        }

        var precio_sin_iva = (parseFloat(precio_con_iva) / (parseFloat(1) + parseFloat(porcentaje_item)));
        $("#precio_agregar").val(precio_sin_iva.toFixed(4));

    }




    //para buscar los clientes
    function buscar_clientes() {
		var codigo_unico = document.getElementById('codigo_unico_factura').value;
        $("#cliente_mecanica").autocomplete({
            appendTo: "#detalleFacturaMecanica",
            source: '../ajax/clientes_autocompletar.php',
            minLength: 2,
            select: function(event, ui) {
                event.preventDefault();
                $('#id_cliente_mecanica').val(ui.item.id);
                $('#cliente_mecanica').val(ui.item.nombre);
				var cliente = ui.item.id;
					$.ajax({
							type: "POST",
							url: "../ajax/actualiza_cliente_mecanica.php?action=actualiza_cliente",
							data: "codigo_unico="+codigo_unico+"&id_cliente="+cliente,
							beforeSend: function(objeto){
								$("#mensajes_ordenes_mecanica").html("Actualizando cliente...");
							},
							success: function(datos){
							$("#mensajes_ordenes_mecanica").html(datos);
							load(1);
						}
					});
				event.preventDefault();
            }
        });

        $("#cliente_mecanica").on("keydown", function(event) {
            if (event.keyCode == $.ui.keyCode.UP || event.keyCode == $.ui.keyCode.DOWN || event.keyCode == $.ui.keyCode.DELETE) {
                $("#id_cliente_mecanica").val("");
                $("#cliente_mecanica").val("");
            }
            if (event.keyCode == $.ui.keyCode.DELETE) {
                $("#cliente_mecanica").val("");
                $("#id_cliente_mecanica").val("");
            }
        });
    }


	 //calcular el precio de un item sin iva
	 function precio_item_sin_iva(id) {
        var porcentaje_item = document.getElementById('porcentaje_item' + id).value;
        var precio_sin_iva = document.getElementById('precio_item_sin_iva' + id).value;
        var precio_sin_iva_inicial = document.getElementById('precio_sin_iva_inicial' + id).value;
		
        if (isNaN(precio_sin_iva)) {
            alert('El precio ingresado, no es un número');
            $("#precio_item_sin_iva" + id).val(precio_sin_iva_inicial);
            document.getElementById('precio_item_sin_iva' + id).focus();
            return false;
        }

        if ((precio_sin_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            $("#precio_item_sin_iva" + id).val(precio_sin_iva_inicial);
            document.getElementById('precio_item_sin_iva' + id).focus();
            return false;
        }

        var precio_con_iva = (parseFloat(precio_sin_iva) + (parseFloat(precio_sin_iva) * parseFloat(porcentaje_item)));
        $("#precio_item_con_iva" + id).val(precio_con_iva.toFixed(4));

        $.ajax({
            type: "POST",
            url: "../ajax/detalle_observaciones_mecanica.php?action=calculo_precio_item",
            data: "id=" + id + "&precio=" + precio_sin_iva,
            beforeSend: function(objeto) {
                $("#muestra_detalle_mecanica").html("Actualizando...");
            },
            success: function(datos) {
				$(".outer_divdet_mecanica").html(datos).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');
                muestra_detalle_factura();
            }
        });
    }

    //calcular el precio de un item con iva
    function precio_item_con_iva(id) {
        var porcentaje_item = document.getElementById('porcentaje_item' + id).value;
        var precio_con_iva = document.getElementById('precio_item_con_iva' + id).value;
        var precio_con_iva_inicial = document.getElementById('precio_con_iva_inicial' + id).value;
		
        if (isNaN(precio_con_iva)) {
            alert('El precio ingresado, no es un número');
            $("#precio_item_con_iva" + id).val(precio_con_iva_inicial);
            document.getElementById('precio_item_con_iva' + id).focus();
            return false;
        }

        if ((precio_con_iva < 0)) {
            alert('El precio, debe ser mayor a cero');
            $("#precio_item_con_iva" + id).val(precio_con_iva_inicial);
            document.getElementById('precio_item_con_iva' + id).focus();
            return false;
        }

        var precio_sin_iva = (parseFloat(precio_con_iva) / (parseFloat(1) + parseFloat(porcentaje_item)));
        $("#precio_item_sin_iva" + id).val(precio_sin_iva.toFixed(4));

        $.ajax({
            type: "POST",
            url: "../ajax/detalle_observaciones_mecanica.php?action=calculo_precio_item",
            data: "id=" + id + "&precio=" + precio_sin_iva,
            beforeSend: function(objeto) {
                $("#muestra_detalle_mecanica").html("Actualizando...");
            },
            success: function(datos) {
                $(".outer_divdet_mecanica").html(datos).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');
                muestra_detalle_factura();
            }
        });
    }

    //calcular el precio de un item con iva
    function actualiza_cantidad(id) {
        var cantidad_producto = document.getElementById('cantidad_producto' + id).value;
        var cantidad_inicial = document.getElementById('cantidad_inicial' + id).value;

        if (isNaN(cantidad_producto)) {
            alert('La cantidad ingresada, no es un número');
            $("#cantidad_producto" + id).val(cantidad_inicial);
            document.getElementById('cantidad_producto' + id).focus();
            return false;
        }

        if ((cantidad_producto < 0)) {
            alert('La cantidad, debe ser mayor a cero');
            $("#cantidad_producto" + id).val(cantidad_inicial);
            document.getElementById('cantidad_producto' + id).focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/detalle_observaciones_mecanica.php?action=actualiza_cantidad",
            data: "id=" + id + "&cantidad_producto=" + cantidad_producto,
            beforeSend: function(objeto) {
                $("#muestra_detalle_mecanica").html("Cargando...");
            },
            success: function(datos) {
				$(".outer_divdet_mecanica").html(datos).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');
                muestra_detalle_factura();
            }
        });
    }


    //descuento en item individual
    function descuento_item(id) {
        var descuento_item = document.getElementById('descuento_item' + id).value;
        var descuento_inicial = document.getElementById('descuento_inicial' + id).value;
 
        if (isNaN(descuento_item)) {
            alert('El valor ingresado, no es un número');
            $("#descuento_item" + id).val(descuento_inicial);
            document.getElementById('descuento_item' + id).focus();
            return false;
        }

        if ((descuento_item < 0)) {
            alert('El valor ingresado debe ser mayor a cero');
            $("#descuento_item" + id).val(descuento_inicial);
            document.getElementById('descuento_item' + id).focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/detalle_observaciones_mecanica.php?action=actualiza_descuento_item",
            data: "id=" + id + "&descuento_item=" + descuento_item,
            beforeSend: function(objeto) {
                $("#muestra_detalle_mecanica").html("Cargando...");
            },
            success: function(datos) {
                $(".outer_divdet_mecanica").html(datos).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');
                muestra_detalle_factura();
            }
        });
    }


	function muestra_detalle_factura() {
		var codigo_unico = $("#codigo_unico_factura").val();

		$.ajax({
			url: "../ajax/detalle_observaciones_mecanica.php?action=muestra_detalle_factura&codigo_unico="+codigo_unico,
				beforeSend: function(objeto){
				$("#muestra_detalle_mecanica").html("Cargando detalle...");
				},
			success: function(data){
				$(".outer_divdet_mecanica").html(data).fadeIn('fast');
				$('#muestra_detalle_mecanica').html('');

				  }
			});

	}
</script>