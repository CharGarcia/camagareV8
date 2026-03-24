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
  <title>Por pagar</title>
<?php 
include("../paginas/menu_de_empresas.php");
date_default_timezone_set('America/Guayaquil');
?>
  </head>
  <body>
 	
    <div class="container">
		<div class="panel panel-info">
			<div class="panel-heading">		
				<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte de cuentas por pagar</h4>
			</div>
			<div class="panel-body">
			<form class="form-horizontal" method ="POST" action="../pdf/pdf_cuentas_por_pagar.php" target="_blank" name="cxp">
			<input type="hidden" name="id_proveedor" id="id_proveedor">
					<div class="form-group">
						<div class="col-sm-4">
						<div class="input-group">
							<span class="input-group-addon"><b>Proveedor</b></span>
							<input type="text" class="form-control input-sm" name="nombre_proveedor" id="nombre_proveedor" onkeyup='buscar_proveedor();' placeholder="Todos" autocomplete="off">
						</div>
						</div>
				
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
						<div class="col-sm-2">
							<button type="button" title="Mostrar resultado" class="btn btn-info btn-sm" onclick="mostrar_informe()"><span class="glyphicon glyphicon-search" ></span></button>				
							<button type="submit" title='Imprimir pdf' class='btn btn-default btn-sm' title='Pdf'>Pdf</button>
							<button type="button" onclick="document.cxp.action = '../excel/cuentas_porpagar_excel.php?action=generar_informe_excel'; document.cxp.submit()" class='btn btn-success btn-sm' title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
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
</body>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</html>
<script>
jQuery(function($){
	 $("#fecha_hasta").mask("99-99-9999");
});

/*
$(document).ready(function(){
	load(1);
});

function load(page){
	var desde = $("#fecha_desde").val();
	var hasta = $("#fecha_hasta").val();
	$("#loader").fadeIn('slow');
	$.ajax({
		url:'../ajax/cuentas_por_pagar.php?action=cuentas_por_pagar&page='+page+'&desde='+desde+'&hasta='+hasta,
		 beforeSend: function(objeto){
		 $('#loader').html('<img src="../image/ajax-loader.gif"> Actualizando saldos, espere por favor...');
	  },
		success:function(data){
			$(".outer_div").html(data).fadeIn('slow');
			$('#loader').html('');
			
		}
	})

}
*/

$( function() {
	$("#fecha_hasta").datepicker({
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
});

//generar informe
function mostrar_informe(){
 var id_proveedor=$("#id_proveedor").val();
 var hasta = $("#fecha_hasta").val();
 
	 $.ajax({
			type: "POST",
			url: "../ajax/cuentas_por_pagar.php",
			data: "action=generar_informe&id_proveedor="+id_proveedor+"&hasta="+hasta,
			 beforeSend: function(objeto){
				$('#loader').html('<img src="../image/ajax-loader.gif">Generando...');
			  },
			success: function(datos){
			$(".outer_div").html(datos);
			$("#loader").html('');
		  }
	});
}

function buscar_proveedor(){
	$("#nombre_proveedor").autocomplete({
			source:'../ajax/proveedores_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_proveedor').val(ui.item.id_proveedor);
				$('#nombre_proveedor').val(ui.item.razon_social);
			}
		});

		$("#nombre_proveedor" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_proveedor" ).val("");
			$("#nombre_proveedor" ).val("");	
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#id_proveedor" ).val("");
			$("#nombre_proveedor" ).val("");
		}
		});
 }
 

</script>