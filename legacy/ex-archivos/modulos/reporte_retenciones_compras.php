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
  <title>Reporte Retenciones Compras</title>
	<?php include("../paginas/menu_de_empresas.php");?>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
  </head>
  <body>
 	
    <div class="container-fluid">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Reportes de retenciones por compras</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="reporte_retenciones_compras" method ="POST" action="../excel/reporte_retenciones_compras.php?action=reporte_retenciones_compras" >
				<div class="form-group row">
					<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-01-Y");?>">
						</div>
						</div>
						
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
		
					<div class="col-md-1">
						<button type="button" class="btn btn-default btn-sm" title="Mostrar reporte" onclick ='reporte_retenciones_compras();'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
					</div>
					<div class="col-md-2">							
						<button type="submit" class="btn btn-success btn-sm" title="Descargar a excel" ><img src="../image/excel.ico" width="25" height="20"></button>
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
	
</body>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css"> 
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</html>
<script>

function reporte_retenciones_compras(){
			var fecha_desde= $("#fecha_desde").val();
			var fecha_hasta= $("#fecha_hasta").val();
			$("#resultados").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/reporte_retenciones_compras.php',
         data: 'action=reporte_retenciones_compras&fecha_desde='+fecha_desde+'&fecha_hasta='+fecha_hasta,
		 beforeSend: function(objeto){
			$("#loader").html("Cargando...");
		  },
			success: function(datos){
			$("#resultados").html(datos);
			$("#loader").html('');
			}
			});
}

jQuery(function($){
     $("#fecha_desde").mask("99-99-9999");
	 $("#fecha_hasta").mask("99-99-9999");
});

$( function() {
	$("#fecha_desde").datepicker({
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
 </script>