<!DOCTYPE html>

<?php
$action = (isset($_REQUEST['codigoqr'])&& $_REQUEST['codigoqr'] !=NULL)?$_REQUEST['codigoqr']:'';

if(!isset($action)){
header('Location: ../includes/logout.php');
exit;	
}else{
	
$codigo_qr=$action;
	include("../conexiones/conectalogin.php");
	$con = conenta_login();

	$busca_encabezado_qr = mysqli_query($con, "SELECT * FROM encabezado_qr WHERE codigo_unico = '".$codigo_qr."' ");
	$contar=mysqli_num_rows($busca_encabezado_qr);

if ($contar==0){
	header('Location: ../includes/logout.php');
exit;
}else{
	$row_encabezado=mysqli_fetch_array($busca_encabezado_qr);
	$titulo_general=$row_encabezado['titulo_general'];

	$busca_detalle_qr = mysqli_query($con, "SELECT * FROM detalle_qr WHERE codigo_unico = '".$codigo_qr."' group by pestana order by id_detalle_qr asc");	//group by pestana order by pestana asc				
?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Información</title>
		<script src="../js/menu_responsive.js"></script>
		<?php include("../head.php");
		?>
</head>

<body style="background-size: 300px 300px; background-repeat: no-repeat ; background-position: center; background-color: hsl(179, 17%, 61%, 0.90);  background-attachment: fixed; padding: 2px;">
	<div class="container-fluid">
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
			<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-list-alt'></i> <?php echo $titulo_general ?></h4>
			</div>			
				<div class="panel-body">
				<div class="panel-group" id="accordion">
						<?php
						while ($row=mysqli_fetch_array($busca_detalle_qr)){
						$id_detalle_qr=$row['id_detalle_qr'];
						$pestana=$row['pestana'];
						$codigo_unico=$row['codigo_unico'];
						?>
							<div class="panel panel-info">
								<a class="list-group-item list-group-item-info" data-toggle="collapse" data-parent="#accordion" href="#<?php echo $id_detalle_qr ?>" ><span class="glyphicon glyphicon-ok-sign"></span> <?php echo str_replace("_", " ", ucfirst($pestana)) ?></a>
								<div id="<?php echo $id_detalle_qr ?>" class="panel-collapse collapse">
									<!--<div class="panel-body">
										<div class="form-group">
											<div class="col-sm-12">
											<div class="panel panel-info">
											<div class="table-responsive">-->
											<?php
											$busca_detalle_interno = mysqli_query($con, "SELECT * FROM detalle_qr WHERE pestana = '".$pestana."' and codigo_unico = '".$codigo_unico."' ");					
											while ($row_interno=mysqli_fetch_array($busca_detalle_interno)){
											//$pestana=ucfirst($row_interno['pestana']);
											$detalle=ucfirst($row_interno['detalle']);
											$imagen=$row_interno['imagen'];
											$posicion_imagen=$row_interno['posicion_imagen'];
											$posicion_texto=$row_interno['posicion_texto'];
											if ($posicion_texto==1){
												$texto="class='text-left'";
											}
											if ($posicion_texto==2){
												$texto="class='text-center'";
											}
											if ($posicion_texto==3){
												$texto="class='text-right'";
											}
											
											$imagen_mostrar = "../qr/imagenes/".$imagen;
											?>
												
								<table class="table">									
								  <?php
								  if ($posicion_imagen==0){
									?>
								  <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								  <?php
								  }
								  if ($posicion_imagen==1){
								  ?>
								  <tr  class="info">
									  <td class="text-center"><img width="250" height="150" src="<?php echo $imagen_mostrar ?>"></td>
								  </tr>
								  <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								 <?php
								  }
								  if ($posicion_imagen==2){
								  ?>
								   <tr  class="info">
									  <td <?php echo $texto ?>><?php echo $detalle ?></td>
								  </tr>
								 <tr  class="info">
									  <td class="text-center"><img width="250" height="150" src="<?php echo $imagen_mostrar ?>"></td>
								  </tr>
								 
								  <?php
								  }
								  ?>
								  
								</table>
																								
											<?php
											}
											?>
											<!--</div>
											</div>
											</div>	
										</div>
									</div>-->
								</div>
							</div>
						<?php
						}
						?>
				</div>
				</div>
		</div>
	</div>
	</div>
<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
</body>

</html>
<?php
}
}
?>