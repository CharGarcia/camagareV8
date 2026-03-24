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
  <title>Declaración IVA</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	include ("../clases/empresas.php");
	?>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>  
  </head>
  <body>
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Resumen para declaración de IVA</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="reporte_declaracion_iva" method ="POST" action="" >
				<div class="form-group row">
				<div class="col-xs-2">
				<input type="hidden" id="dato_declaracion">
					<div class="form-check">
					  <input type="radio" class="custom-control-input" id="mensual" name="periodo_reporte" value="mensual" checked>
					  <label class="custom-control-label" >Mensual</label>
					</div>
					<?php
					//$info_empresa= new empresas();
					//$tipo_empresa = $info_empresa->datos_empresas($ruc_empresa)['tipo'];
						//if ($tipo_empresa == '01'){
						?>
					<div class="form-check">
					  <input type="radio" class="custom-control-input" id="semestral" name="periodo_reporte" value="semestral">
					  <label class="custom-control-label" >Semestral</label>
					</div>
					<?php
						//}
					?>
				</div>
						<div class="col-xs-3">
						<div class="input-group">
							<span class="input-group-addon"><b>Mes/Semestre</b></span>
							<select class="form-control" name="mes_semestre" id="mes_semestre">
							</select>
						</div>
						</div>
						<div class="col-xs-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Año</b></span>
							<select class="form-control" name="anio_periodo" id="anio_periodo">
								<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
								<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-5; $i+= -1) {
								?> 
								<option value="<?php echo $i ?>"> <?php echo $i ?></option>
								<?php }  ?> 
							</select>
						</div>
						</div>
				<div class="col-md-2">
					<button type="button" class="btn btn-default" onclick ='resumen_declaracion_iva();'><span class="glyphicon glyphicon-search" ></span> Generar</button>
				</div>
				<div class="col-md-1">							
					<!-- <button type="submit" class="btn btn-default" ><img src="../image/xml.ico" width="25" height="20" title="xml"></button>-->
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
<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
</body>
</html>

<script>
$(document).ready(function() {
	
		$.post( '../ajax/select_tipo_declaracion.php', {declaracion:'iva', tipo_periodo: 'mensual'}).done( function( res_tipo_periodo ){
				$("#mes_semestre").html(res_tipo_periodo);
		});
		$("#dato_declaracion").val('mensual');
	
    $('input:radio[name=periodo_reporte]').change(function() {
        if (this.value == 'mensual') {
        var tipo_periodo="mensual";
		$("#dato_declaracion").val('mensual');
        }
        else if (this.value == 'semestral') {
        var tipo_periodo="semestral";
		$("#dato_declaracion").val('semestral');
        }
		
		$.post( '../ajax/select_tipo_declaracion.php', {declaracion:'iva', tipo_periodo: tipo_periodo}).done( function( res_tipo_periodo ){
				$("#mes_semestre").html(res_tipo_periodo);
		});
		
    });
});


function resumen_declaracion_iva(){
			var dato_declaracion = $("#dato_declaracion").val();
			var mes_semestre= $("#mes_semestre").val();
			var anio_periodo= $("#anio_periodo").val();
			
			$("#resultados").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/declaracion_iva.php',
         data: 'action=declaracion_iva&dato_declaracion='+dato_declaracion+'&mes_semestre='+mes_semestre+'&anio_periodo='+anio_periodo,
		 beforeSend: function(objeto){
			$('#resultados').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Generando declaración de IVA, espere por favor...</div></div>');					    
		  },
			success: function(datos){
			$("#resultados").html(datos);
			}
			});
}

jQuery(function($){
     $("#parametro").mask("99/9999");
});
 </script>