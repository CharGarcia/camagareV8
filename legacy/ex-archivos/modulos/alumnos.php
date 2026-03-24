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
  <title>Alumnos</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include("../modal/nuevo_alumno.php");
	include("../modal/editar_alumno.php");
	include("../modal/buscar_cliente_alumno.php");
	include("../modal/detalle_factura_alumno.php");
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
					<a href="#" data-toggle="modal" data-target="#nuevoAlumno" class="btn btn-info"><span class="glyphicon glyphicon-plus"></span> Nuevo alumno</a>
				</div>
				<h4><i class='glyphicon glyphicon-search'></i> Buscar alumnos</h4>	
			</div>
			<div class="panel-body">
			<div id="resultados_actualizar_cliente"></div>
				<form class="form-horizontal" role="form" id="datos_cotizacion">
							<div class="form-group row">
								<label for="q" class="col-md-2 control-label">Buscar:</label>
								<div class="col-md-5">
								<input type="hidden" id="ordenado" value="al.nombres_apellidos">
								<input type="hidden" id="por" value="asc">
								
								<div class="input-group">
								<input type="text" class="form-control" id="q" placeholder="Nombres, apellidos, Cliente, cedula, ruc" onkeyup='load(1);'>
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
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
	<script src="../js/ordenado.js" type="text/javascript"></script>

 </body>
</html>
<script>
		
$( function() {
	$("#fecha_nacimiento_alumno").datepicker({
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
    $( "#fecha_nacimiento_alumno" ).datepicker( "option", "maxDate", "+0m +0d" );
	
$("#fecha_ingreso_alumno").datepicker({
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
    $( "#fecha_ingreso_alumno" ).datepicker( "option", "maxDate", "+0m +0d" );

$("#mod_fecha_nacimiento_alumno").datepicker({
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
    $("#mod_fecha_nacimiento_alumno" ).datepicker( "option", "maxDate", "+0m +0d" );	
	
$("#mod_fecha_ingreso_alumno").datepicker({
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
    $("#mod_fecha_ingreso_alumno" ).datepicker( "option", "maxDate", "+0m +0d" );	
} );
	
//esto es para que al momento de abrir el modulo se carguen los datos automaticamente
$(document).ready(function(){
	document.getElementById('q').focus();
	load(1);
});
	
function load(page){
	var por= $("#por").val();
	var ordenado= $("#ordenado").val();
	var q= $("#q").val();	
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/buscar_alumnos.php?action=buscar_alumnos&page='+page+'&q='+q+"&por="+por+"&ordenado="+ordenado,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
		}
	})
}


$( "#guardar_alumno" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/nuevo_alumno.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_alumnos").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_alumnos").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

function obtener_datos_editar_alumnos(id_alumno){
			var tipo_id_alumno = $("#tipo_id_alumno"+id_alumno).val();
			var cedula_alumno = $("#cedula_alumno"+id_alumno).val();
			var nombres_apellidos = $("#nombres_apellidos"+id_alumno).val();
			var fecha_nacimiento_alumno = $("#fecha_nacimiento_alumno"+id_alumno).val();
			var fecha_ingreso_alumno = $("#fecha_ingreso_alumno"+id_alumno).val();
			var sexo_alumno = $("#sexo_alumno"+id_alumno).val();
			var horario_alumno = $("#horario_alumno"+id_alumno).val();
			var sucursal_alumno = $("#sucursal_alumno"+id_alumno).val();
			var nivel_alumno = $("#nivel_alumno"+id_alumno).val();
			var estado_alumno = $("#estado_alumno"+id_alumno).val();
			var serie_facturar = $("#serie_facturar"+id_alumno).val();
		
			$("#mod_id_alumno").val(id_alumno);
			$("#mod_tipo_id").val(tipo_id_alumno);
			$("#mod_cedula_alumno").val(cedula_alumno);
			$("#mod_nombres_alumno").val(nombres_apellidos);
			$("#mod_fecha_nacimiento_alumno").val(fecha_nacimiento_alumno);
			$("#mod_fecha_ingreso_alumno").val(fecha_ingreso_alumno);
			$("#mod_sexo_alumno").val(sexo_alumno);
			$("#mod_horario_alumno").val(horario_alumno);
			$("#mod_sucursal_alumno").val(sucursal_alumno);
			$("#mod_nivel_alumno").val(nivel_alumno);
			$("#mod_estado_alumno").val(estado_alumno);
			$("#mod_serie_facturar").val(serie_facturar);
	}

$( "#editar_alumno" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/editar_alumno.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax_editar_alumnos").html("Mensaje: Cargando...");
					  },
					success: function(datos){
					$("#resultados_ajax_editar_alumnos").html(datos);
					$('#guardar_datos').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

//pasa los datos del cliente a buscar clientes o editar
function agrega_datos_facturacion(id_reg){
		var reg_alumno = $("#id_alumno"+id_reg).val();
		var id_cliente = $("#id_cliente"+id_reg).val();
		var tipo_id = $("#tipo_id"+id_reg).val();
		var ruc_cliente = $("#ruc_cliente"+id_reg).val();
		var nombre_cliente = $("#nombre_cliente"+id_reg).val();
		var telefono_cliente = $("#telefono_cliente"+id_reg).val();
		var direccion_cliente = $("#direccion_cliente"+id_reg).val();
		var plazo_cliente = $("#plazo_cliente"+id_reg).val();
		var email_cliente = $("#email_cliente"+id_reg).val();
		$("#id_alumno").val(reg_alumno);
		$("#id_cliente_alumno").val(id_cliente);
		$("#tipo_id_cliente").val(tipo_id);
		$("#ruc_cliente_alumno").val(ruc_cliente);
		$("#nombre_cliente_alumno").val(nombre_cliente);
		$("#telefono_cliente_alumno").val(telefono_cliente);
		$("#direccion_cliente_alumno").val(direccion_cliente);
		$("#plazo_cliente_alumno").val(plazo_cliente);
		$("#email_cliente_alumno").val(email_cliente);
		//document.getElementById("tipo_id_cliente").disabled = true;
	document.getElementById("ruc_cliente_alumno").readOnly = true;
	};


//para mostrar el precio y la cantidad del producto seleccionado
$(function(){ 
		$('#id_producto').change(function(){
			var producto_va = $("#id_producto").val();
			$.post( '../ajax/combo_detalle_productos.php', {id_producto_pasa: producto_va}).done( function( respuesta ){
			var precio_producto = respuesta;
			$("#precio").val(precio_producto);		
			});
		});
});
//pasa el id del alumno a modal/detalle_factura_alumno.php y carga detalle a facturar
function detalle_factura_alumno(id_reg){
		var id_reg_alumno = $("#id_alumno"+id_reg).val();		
		$("#id_reg_alumno").val(id_reg_alumno);
		var nombres = $("#nombres_apellidos"+id_reg).val();		
		$("#alumno").val(nombres);

		$("#muestra_detalle_factura_alumno").fadeIn('fast');
		$.ajax({
			url:'../ajax/detalle_factura_alumno.php?action=ajax&id_reg_alumno='+id_reg_alumno,
			 beforeSend: function(objeto){
			 $('#muestra_detalle_factura_alumno').html('<img src="../image/ajax-loader.gif"> Cargando... por favor espere a que se cargue la información.');
		  },
			success:function(data){
				$(".outer_divdet").html(data).fadeIn('fast');
				$('#muestra_detalle_factura_alumno').html('');
			}
		})
};
			
//para agregar un producto al alumno por facturar
function agregar_detalle_factura_alumno(){
			var id_alumno = $("#id_reg_alumno").val();
			var id_producto= $("#id_producto").val();
			var cantidad_producto= $("#cantidad").val();
			var precio_producto= $("#precio").val();
			var periodo= $("#periodo").val();
			//Inicia validacion
			if (id_producto ==''){
			alert('Seleccione producto');
			document.getElementById('id_producto').focus();
			return false;
			}
			if (cantidad_producto ==''){
			alert('Ingrese cantidad');
			document.getElementById('cantidad').focus();
			return false;
			}
			if (isNaN(cantidad_producto)){
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad').focus();
			return false;
			}
			if (precio_producto ==''){
			alert('Ingrese precio');
			document.getElementById('precio').focus();
			return false;
			}
			if (isNaN(precio_producto)){
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio').focus();
			return false;
			}
			if (periodo ==''){
			alert('Seleccione período a facturar');
			document.getElementById('periodo').focus();
			return false;
			}
			//Fin validacion
			$("#muestra_detalle_factura_alumno").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_factura_alumno.php?id_reg_alumno="+id_alumno+"&id_producto="+id_producto+"&cantidad_producto="+cantidad_producto+"&precio_producto="+precio_producto+"&periodo="+periodo,
					 beforeSend: function(objeto){
						$('#loader_detalle_factura_alumno').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(data){
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#muestra_detalle_factura_alumno').html('');
						$('#loader_detalle_factura_alumno').html('');
						document.getElementById("id_producto").value = "";
						document.getElementById("precio").value = "";
				  }
			});
};

//para que cuando se cierre el modal de enviar sri se reseteen los datos y se limpie
$("#cerrar_edita_alumno").click(function(){
	$("#resultados_ajax_editar_alumnos").empty();
    });
	
$("#cerrar_detalle_a_facturar").click(function(){
	$("#resultados_generar_factura").empty();
	$("#muestra_detalle_factura_alumno").empty();
    });

$("#cancelar_asigna_cliente").click(function(){
	$("#buscar_cliente_alumno" ).val("");
    });
	
$("#borrar_datos").click(function(){
	$("#id_cliente_alumno" ).val("");
	$("#buscar_cliente_alumno" ).val("");
	$("#tipo_id_cliente" ).val("");
	$("#ruc_cliente_alumno" ).val("");
	$("#nombre_cliente_alumno" ).val("");
	$("#telefono_cliente_alumno" ).val("");
	$("#direccion_cliente_alumno" ).val("");
	$("#plazo_cliente_alumno" ).val("5");
	$("#email_cliente_alumno" ).val("");
	document.getElementById("tipo_id_cliente").disabled = false;
	document.getElementById("ruc_cliente_alumno").readOnly = false;
    });
	
//pasa eliminar cada detalle de factura del alumno
function eliminar_detalle_factura_alumno(id_detalle_pf){
			var id_reg_detalle = $("#id_a_facturar"+id_detalle_pf).val();
			var id_reg_alumno = $("#id_reg_alumno"+id_detalle_pf).val();		
			$("#muestra_detalle_factura_alumno").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_factura_alumno.php?id_reg_detalle="+id_reg_detalle+"&id_reg_alumno="+id_reg_alumno,
					 beforeSend: function(objeto){
						$('#loader_detalle_factura_alumno').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(data){
							$(".outer_divdet").html(data).fadeIn('fast');
							$('#muestra_detalle_factura_alumno').html('');
							$('#loader_detalle_factura_alumno').html('');
				  }
			});
};

//apara aplicar el descuento
function aplica_descuento(id){
			var valor= $("#descuento"+id).val();
			var id_reg_alumno = $("#id_reg_alumno"+id).val();
			var subtotal = $("#subtotal"+id).val();
			
			if (valor > parseFloat(subtotal)){
			alert('El valor del descuento es mayor que el subtotal. '+subtotal);
			document.getElementById('descuento').focus();
			return false;
			}
			
			if (valor <0){
			alert('Ingrese valor mayor a cero');
			document.getElementById('descuento').focus();
			return false;
			}
			$("#muestra_detalle_factura_alumno").fadeIn('fast');
			$.ajax({
				url:'../ajax/detalle_factura_alumno.php?id_descuento='+id+'&valor_descuento='+valor+"&id_reg_alumno="+id_reg_alumno,
				 beforeSend: function(objeto){
				 $('#loader_detalle_factura_alumno').html('<img src="../image/ajax-loader.gif">');
			  },
				success:function(data){
					$(".outer_divdet").html(data).fadeIn('fast');
					$('#muestra_detalle_factura_alumno').html('');
					$('#loader_detalle_factura_alumno').html('');
				}
			});
}

//generar factura
$( "#generar_factura" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/generar_factura_alumno.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#loader_detalle_factura_alumno').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_generar_factura").html(datos);
					$('#loader_detalle_factura_alumno').html('');
					$('#guardar_datos').attr("disabled", false);
					load(1);
				  }
			});
		  event.preventDefault();
})

//para buscar los clientes
function buscar_clientes_alumnos(){
	$("#buscar_cliente_alumno").autocomplete({
			source:'../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cliente_alumno').val(ui.item.id);
				$('#nombre_cliente_alumno').val(ui.item.nombre);
				$('#buscar_cliente_alumno').val(ui.item.nombre);
				$('#ruc_cliente_alumno').val(ui.item.ruc);
				$('#telefono_cliente_alumno').val(ui.item.telefono);
				$('#direccion_cliente_alumno').val(ui.item.direccion);
				$('#plazo_cliente_alumno').val(ui.item.plazo);
				$('#email_cliente_alumno').val(ui.item.email);
				$('#tipo_id_cliente').val(ui.item.tipo_id);
				
			}
		});

		$("#buscar_cliente_alumno" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cliente_alumno" ).val("");
			$("#buscar_cliente_alumno" ).val("");
			$("#nombre_cliente_alumno" ).val("");
			$("#ruc_cliente_alumno" ).val("");
			$("#telefono_cliente_alumno" ).val("");
			$("#direccion_cliente_alumno" ).val("");
			$("#plazo_cliente_alumno" ).val("");
			$("#email_cliente_alumno" ).val("");

		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#id_cliente_alumno" ).val("");
			$("#buscar_cliente_alumno" ).val("");
			$("#nombre_cliente_alumno" ).val("");
			$("#ruc_cliente_alumno" ).val("");
			$("#telefono_cliente_alumno" ).val("");
			$("#direccion_cliente_alumno" ).val("");
			$("#plazo_cliente_alumno" ).val("");
			$("#email_cliente_alumno" ).val("");
		}
		});
}

//para asignar un cliente al alumno y guardar o actualizar un cliente
$( "#guardar_cliente_alumno" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);  
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: "../ajax/actualiza_cliente_alumno.php",
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
</script>