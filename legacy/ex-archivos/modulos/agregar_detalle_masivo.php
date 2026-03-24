<?php
//include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 1 && isset($_POST['id_usuario']) && isset($_POST['id_empresa'])){
	$id_usuario = $_POST['id_usuario'];
	$id_empresa = $_POST['id_empresa'];
	$ruc_empresa = $_POST['ruc_empresa'];
	
	

?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <title>Por facturar</title>
	<?php include("../paginas/menu_de_empresas.php");
		  include("../modal/enviar_documentos_mail.php");
		  include("../modal/enviar_documentos_sri.php");
		  include("../modal/detalle_factura_e.php");
		  include("../modal/editar_factura_e.php");
	?>
  </head>
  <body>	
	
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">
			
			<h4><i class='glyphicon glyphicon-screenshot'></i> Agregar detalles por facturar</h4>		
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" role="form" id="guarda_adicional">
						<div class="form-group row">
						<div class="form-group">
						<label class="col-md-4 control-label">Agregar el valor de:</label>
						<div class="col-md-3">	
						<input type="text" class="form-control" id="valor_masivo" name="valor_masivo" placeholder="valor" >
						</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">por concepto de:</label>
							<div class="col-md-3">						
								<select class="form-control" id="id_producto" name="id_producto" required>
										<option value="" selected >Seleccione producto</option>
										<?php
										$con = conenta_login();
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '$ruc_empresa';";
										$respuesta = mysqli_query($con,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['id']?>"><?php echo $datos_producto['nombre_producto'] ?></option> 
										<?php 
										}
										?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label class="col-md-4 control-label">De forma:</label>
							<div class="col-md-3">						
								<select class="form-control" id="periodo_masivo" name="periodo_masivo" required>
										<option value="" Selected>Seleccione período</option>
										<?php
										$con = conenta_login();
										$sql = "SELECT * FROM periodo_a_facturar";
										$respuesta = mysqli_query($con,$sql);
										while($datos_periodo = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_periodo['codigo_periodo']?>"><?php echo $datos_periodo['detalle_periodo'] ?></option> 
										<?php 
										}
										?>
									</select>
							</div>
						</div>
							
						<div class="form-group">
						<label class="col-md-4 control-label">A todos los alumnos donde:</label>
							<div class="col-md-3">						
								<select class="form-control" name="columna" id="columna" required>
									<?php
									$con = conenta_login();
									$columnas_name = mysqli_query($con, "select COLUMN_NAME from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME = 'alumnos'");
									?> 
									<option value="">Seleccione columna</option>
									<?php
									while($o = mysqli_fetch_assoc($columnas_name)){
									?>
									<option value="<?php echo $o['COLUMN_NAME'] ?>"><?php echo $o['COLUMN_NAME'] ?> </option>
									<?php
									}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
						<label class="col-md-4 control-label">Sea igual a:</label>
							<div class="col-md-3">						
								<input type="text" class="form-control" id="dato" name="dato" placeholder="dato, id" required >
							</div>
						</div>
							<div class="form-group">
							<label class="col-md-4 control-label"></label>
								<div class="col-md-2">
									<button type="submit" name="guardar" class="btn btn-primary" > Guardar</button>
									<span id="loader"></span>
								</div>
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
</html>
<script>


//para guardar
 $( "#guarda_adicional" ).submit(function( event ) {
 $('#guardar').attr("disabled", true);
	var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/guardar_por_facturar_masivo_alumnos.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados").html(	
				'<div class="progress"><div class="progress-bar progress-bar-primary progress-bar-striped active" role="progressbar" style="width:100%;">Guardando...</div></div>');
			  },
			success: function(datos){
			$("#resultados").html(datos);
			$('#guardar').attr("disabled", false);
		  }
	});
  event.preventDefault();
});
</script>