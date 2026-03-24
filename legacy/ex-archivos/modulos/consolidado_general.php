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
  <title>Consolidado General</title>
	<?php 
	include("../paginas/menu_de_empresas.php");
	$con = conenta_login();
	$buscar_sucursales=mysqli_query($con, "select DISTINCT ruc, nombre_comercial from empresas where mid(ruc,1,12) = '".substr($ruc_empresa,0,12)."' order by nombre_comercial asc");
	?>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>  
  </head>
  <body>
    <div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">		
			<h4><i class='glyphicon glyphicon-list-alt'></i> Reporte General de Ventas, NC, Compras, Retenciones</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="reporte_consolidado" method ="POST" action="../excel/consolidado_general_tc.php" target="_blank">
				<div class="form-group row">
				<div class="col-xs-5">
						<div class="input-group">
							<span class="input-group-addon"><b>Sucursal</b></span>
								<select class="form-control" name="sucursal" id="sucursal">
								<option value="" selected>Todas</option>
								<?php
								while($row_sucursales = mysqli_fetch_array($buscar_sucursales)){
								?>
								<option value="<?php echo $row_sucursales['ruc'];?>"><?php echo $row_sucursales['nombre_comercial'];?></option>
								<?php
								}
								?>
								</select>
						</div>
						</div>
						<div class="col-xs-3">
						<div class="input-group">
							<span class="input-group-addon"><b>Mes</b></span>
							<select class="form-control" name="mes" id="mes">
							<option value="01" selected>Enero</option>
							<option value="02" >Febrero</option>
							<option value="03" >Marzo</option>
							<option value="04" >Abril</option>
							<option value="05" >Mayo</option>
							<option value="06" >Junio</option>
							<option value="07" >Julio</option>
							<option value="08" >Agosto</option>
							<option value="09" >Septiembre</option>
							<option value="10" >Octubre</option>
							<option value="11" >Noviembre</option>
							<option value="12" >Diciembre</option>
							</select>
						</div>
						</div>
						<div class="col-xs-2">
						<div class="input-group">
							<span class="input-group-addon"><b>Año</b></span>
							<select class="form-control" name="anio_periodo" id="anio_periodo">
								<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
								<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-3; $i+= -1) {
								?> 
								<option value="<?php echo $i ?>"> <?php echo $i ?></option>
								<?php }  ?> 
							</select>
						</div>
						</div>
				<div class="col-md-2">
					<button type="submit" class='btn btn-success btn-sm' title="Descargar excel" target="_blank"><img src="../image/excel.ico" width="20" height="20"></button>
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
	<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
	<script src="../js/notify.js"></script>
</body>
</html>
<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>