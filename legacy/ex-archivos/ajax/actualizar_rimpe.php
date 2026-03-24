<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'actualizar_rimpe'){
		echo actualizar_rimpe($con);
	}

function actualizar_rimpe($con){
$sql="SELECT * FROM empresas ";
$result = $con->query($sql);

$carpeta_rimpe = file_get_contents('../agente_micro_especial/rimpe.txt');
$carpeta_negocio_popular = file_get_contents('../agente_micro_especial/negocio_popular.txt');

while ($row=mysqli_fetch_array($result)){
$ruc =$row['ruc'];
$sql_micro="UPDATE config_electronicos SET regimen_micro = 'NO' WHERE ruc_empresa='".$ruc."'";
$query_update_micro = mysqli_query($con,$sql_micro);
	

$rimpe = strpos($carpeta_rimpe, $ruc);
$negocio_popular = strpos($carpeta_negocio_popular, $ruc);
if($rimpe){
	$resultado_rimpe="SI";
}else{
	$resultado_rimpe="NO";
}

if($negocio_popular){
	$resultado_negocio_popular="SI";
}else{
	$resultado_negocio_popular="NO";
}

	$sql_rimpe="UPDATE config_electronicos SET negocio_popular = '".$resultado_negocio_popular."', regimen_rimpe = '".$resultado_rimpe."' WHERE ruc_empresa='".$ruc."'";
	$query_update_rimpe = mysqli_query($con,$sql_rimpe);
	if ($query_update_rimpe){
		$messages[] = "actualizado satisfactoriamente.";
	} else{
		$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
	}

}
	if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}

}

?>