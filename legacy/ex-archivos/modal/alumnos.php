<?php

session_start();
if($_SESSION['nivel'] >= 1 && isset($_POST['id_usuario']) && isset($_POST['id_empresa'])){
	$id_usuario = $_POST['id_usuario'];
	$id_empresa = $_POST['id_empresa'];
	$ruc_empresa = $_POST['ruc_empresa'];
	
	

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <title>Alumnos</title>
	<?php include("../paginas/menu_de_empresas.php");
		  include("../modal/nuevo_alumno.php");
		  include("../modal/editar_alumno.php");
		  include("../modal/buscar_cliente_alumno.php");
		  include("../modal/detalle_factura_alumno.php");
	?>
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
									<input type="text" class="form-control" id="q" placeholder="Ced/pasaporte, Nombre, apellido" onkeyup='load(1);'>
								</div>
					
								<div class="col-md-3">
									<button type="button" class="btn btn-default" onclick='load(1);'>
										<span class="glyphicon glyphicon-search" ></span> Buscar</button>
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
header('Location: ../includes/logout.php');
exit;
}
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
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
	load(1);//load 1 carga la pagina 1
});
	
function load(page){
			var cliente= $("#cli").val();
			$("#loaderCliente").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_clientes_alumno.php?actiones=ajax&pages='+page+'&cli='+cliente,
				 beforeSend: function(objeto){
				 $('#loaderCliente').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_divcli").html(data).fadeIn('slow');
					$('#loaderCliente').html('');
				}
			});
			

			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_alumnos.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})

}

function eliminar_alumno(id_alumno){
			var q= $("#q").val();
			var serie = $("#serie_factura"+id_factura).val();
			var secuencial = $("#secuencial_factura"+id_factura).val();
		if (confirm("Realmente desea eliminar la factura "+serie+"-"+secuencial+" ?")){	
		$.ajax({
        type: "POST",
        url: "../ajax/buscar_alumnos.php",
        data: "id_factura="+id_factura,"q":q,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
			});
		}
};

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
			var nombres_alumno = $("#nombres_alumno"+id_alumno).val();
			var apellidos_alumno = $("#apellidos_alumno"+id_alumno).val();
			var fecha_nacimiento_alumno = $("#fecha_nacimiento_alumno"+id_alumno).val();
			var fecha_ingreso_alumno = $("#fecha_ingreso_alumno"+id_alumno).val();
			var sexo_alumno = $("#sexo_alumno"+id_alumno).val();
			var horario_alumno = $("#horario_alumno"+id_alumno).val();
			var sucursal_alumno = $("#sucursal_alumno"+id_alumno).val();
			var nivel_alumno = $("#nivel_alumno"+id_alumno).val();
			var estado_alumno = $("#estado_alumno"+id_alumno).val();
		
			$("#mod_id_alumno").val(id_alumno);
			$("#mod_tipo_id").val(tipo_id_alumno);
			$("#mod_cedula_alumno").val(cedula_alumno);
			$("#mod_nombres_alumno").val(nombres_alumno);
			$("#mod_apellidos_alumno").val(apellidos_alumno);
			$("#mod_fecha_nacimiento_alumno").val(fecha_nacimiento_alumno);
			$("#mod_fecha_ingreso_alumno").val(fecha_ingreso_alumno);
			$("#mod_sexo_alumno").val(sexo_alumno);
			$("#mod_horario_alumno").val(horario_alumno);
			$("#mod_sucursal_alumno").val(sucursal_alumno);
			$("#mod_nivel_alumno").val(nivel_alumno);
			$("#mod_estado_alumno").val(estado_alumno);
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

//pasa el id del alumno a ajxx/buscar_clientes_alumno.php
function agrega_datos_facturacion(id_reg){
		var reg_alumno = $("#id_alumno"+id_reg).val();
		$("#id_alumno").val(reg_alumno);	
	};

function actualiza_cliente_alumnos(id_cliente){
			var id_alumno_mod= $("#id_alumno").val();
			var id_cliente_mod= id_cliente;
		
		$.ajax({
        url: '../ajax/actualiza_cliente_alumno.php?id_alumno='+id_alumno_mod+'&id_cliente='+id_cliente_mod,
		 beforeSend: function(objeto){
			$("#resultados_busqueda_cliente").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados_actualizar_cliente").html(datos);
		load(1);
		}
			});

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
						$("#muestra_detalle_factura_alumno").html("Cargando detalle...");
					  },
					success: function(data){
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#muestra_detalle_factura_alumno').html('');
						document.getElementById("id_producto").value = "";
						document.getElementById("precio").value = "";
						document.getElementById("periodo").value = "";
				  }
			});
};

//pasa eliminar cada detalle de factura del alumno
function eliminar_detalle_factura_alumno(id_detalle_pf){
			var id_reg_detalle = $("#id_a_facturar"+id_detalle_pf).val();
			var id_reg_alumno = $("#id_reg_alumno"+id_detalle_pf).val();		
			$("#muestra_detalle_factura_alumno").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_factura_alumno.php?id_reg_detalle="+id_reg_detalle+"&id_reg_alumno="+id_reg_alumno,
					 beforeSend: function(objeto){
						$("#muestra_detalle_factura_alumno").html("Cargando detalle...");
					  },
					success: function(data){
							$(".outer_divdet").html(data).fadeIn('fast');
							$('#muestra_detalle_factura_alumno').html('');
				  }
			});
};

//para que cuando se cierre el modal de enviar sri se reseteen los datos y se limpie
$("#cerrar_edita_alumno").click(function(){
	$("#resultados_ajax_editar_alumnos").empty();
    });
</script>