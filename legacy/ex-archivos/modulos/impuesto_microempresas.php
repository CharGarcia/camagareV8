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
  <title>Régimen Microempresas</title>
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
			<h4><i class='glyphicon glyphicon-list-alt'></i> IMPUESTO A LA RENTA REGIMEN IMPOSITIVO PARA MICROEMPRESAS</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="reporte_declaracion_microempresas" method ="POST" action="" >
				<div class="form-group row">
						<div class="col-xs-3">
						<div class="input-group">
							<span class="input-group-addon"><b>Semestre</b></span>
							<select class="form-control" name="semestre" id="semestre">
							<option value="1" selected> Enero-Junio</option>
							<option value="2"> Julio-Diciembre</option>
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
					<button type="button" class="btn btn-default" onclick ='resumen_declaracion_microempresas();'><span class="glyphicon glyphicon-search" ></span> Generar</button>
				</div>
				<div class="col-md-1">							
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
function resumen_declaracion_microempresas(){
			var semestre= $("#semestre").val();
			var anio_periodo= $("#anio_periodo").val();
			
			$("#resultados").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/declaracion_microempresas.php',
         data: 'action=declaracion_microempresas&semestre='+semestre+'&anio_periodo='+anio_periodo,
		 beforeSend: function(objeto){
			$('#resultados').html('<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Generando declaración Régimen Microempresas, espere por favor...</div></div>');					    
		  },
			success: function(datos){
			$("#resultados").html(datos);
			}
			});
}

 </script>