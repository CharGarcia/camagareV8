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
  <title>Conciliación Bancos</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	$con = conenta_login();
	?>
  </head>
  <body>
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Conciliaciones bancarias</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="reporte_conciliacion_bancos" method ="POST" action="../pdf/pdf_conciliacion_bancaria.php" name="conciliacion_bancaria">
				<div class="form-group row">
						<div class="col-xs-4">
						<div class="input-group">
							<span class="input-group-addon"><b>Cuenta</b></span>
							<select class="form-control input-sm" id="cuenta" name="cuenta" required>
									<?php
									$cuentas = mysqli_query($con,"SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='".$ruc_empresa."'");
									while($row = mysqli_fetch_array($cuentas)){
									?>
									<option value="<?php echo $row['id_cuenta']?>" selected><?php echo strtoupper($row['cuenta_bancaria'])?></option>
									<?php
									}
									?>
							</select>
						</div>
						</div>
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_desde" id="fecha_desde" value="<?php echo date("01-m-Y");?>">
						</div>
						</div>
						
						<div class="col-sm-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm text-center" name="fecha_hasta" id="fecha_hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
				<div class="col-md-3">
					<button type="button" class="btn btn-info btn-sm" onclick ='generar_informe();'><span class="glyphicon glyphicon-search" ></span> Ver</button>					
					<button type="submit" class="btn btn-default btn-sm" title="Imprimir pdf">Pdf</button>
					<button type="button" onclick="document.conciliacion_bancaria.action = '../excel/conciliacion_bancaria_excel.php?action=generar_informe_excel'; document.conciliacion_bancaria.submit()" class='btn btn-success btn-sm' title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="16"></button>
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
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
</body>
</html>

<script>
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

function generar_informe(){
			var cuenta = $("#cuenta").val();
			var fecha_desde= $("#fecha_desde").val();
			var fecha_hasta= $("#fecha_hasta").val();
			
			$("#resultados").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/conciliacion_bancaria.php',
         data: 'action=conciliacion_bancaria&cuenta='+cuenta+'&fecha_desde='+fecha_desde+'&fecha_hasta='+fecha_hasta,
		 beforeSend: function(objeto){
			$('#resultados').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Generando informe, espere por favor...</div></div>');					    
		  },
			success: function(datos){
			$("#resultados").html(datos);
			}
			});
}

 </script>