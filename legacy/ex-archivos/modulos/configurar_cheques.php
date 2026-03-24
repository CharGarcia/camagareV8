<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Configurar cheque</title>
<?php include("../head.php");?>
<script src="../js/jquery.min.js"></script> <!--para que me cargue el select con las ciudades -->
<script type="text/javascript" src="../js/select_ciudad.js"></script>
</head>
<body>
<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

include("../paginas/menu_de_empresas.php"); 	
$con = conenta_login();
//para buscar los datos
$busca_beneficiario = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='beneficiario'");
$row_beneficiario=mysqli_fetch_array($busca_beneficiario);

	$ben_ini_der_izq=$row_beneficiario["ini_der_izq"]==null?"16":$row_beneficiario["ini_der_izq"];
	$ben_ini_arr_aba=$row_beneficiario["ini_arr_aba"]==null?"15":$row_beneficiario["ini_arr_aba"];
	$ben_ini_anc_cell=$row_beneficiario["anc_cel"]==null?"107":$row_beneficiario["anc_cel"];
	$ben_ini_alt_cell=$row_beneficiario["alt_cel"]==null?"10":$row_beneficiario["alt_cel"];

$busca_cantidad_numeros = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='cantidad_numeros'");
$row_cantidad_numeros=mysqli_fetch_array($busca_cantidad_numeros);
	
	$canum_ini_der_izq=$row_cantidad_numeros["ini_der_izq"]==null?"120":$row_cantidad_numeros["ini_der_izq"];
	$canum_ini_arr_aba=$row_cantidad_numeros["ini_arr_aba"]==null?"15":$row_cantidad_numeros["ini_arr_aba"];
	$canum_ini_anc_cell=$row_cantidad_numeros["anc_cel"]==null?"50":$row_cantidad_numeros["anc_cel"];
	$canum_ini_alt_cell=$row_cantidad_numeros["alt_cel"]==null?"10":$row_cantidad_numeros["alt_cel"];

$busca_cantidad_letras = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='cantidad_letras'");
$row_cantidad_letras=mysqli_fetch_array($busca_cantidad_letras);

	$canle_ini_der_izq=$row_cantidad_letras["ini_der_izq"]==null?"16":$row_cantidad_letras["ini_der_izq"];
	$canle_ini_arr_aba=$row_cantidad_letras["ini_arr_aba"]==null?"25":$row_cantidad_letras["ini_arr_aba"];
	$canle_ini_anc_cell=$row_cantidad_letras["anc_cel"]==null?"135":$row_cantidad_letras["anc_cel"];
	$canle_ini_alt_cell=$row_cantidad_letras["alt_cel"]==null?"6":$row_cantidad_letras["alt_cel"];

$busca_ciudad_fecha = mysqli_query($con, "SELECT * FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."' and concepto='ciudad_fecha'");
$row_ciudad_fecha=mysqli_fetch_array($busca_ciudad_fecha);

	$ciufec_ini_der_izq=$row_ciudad_fecha["ini_der_izq"]==null?"12":$row_ciudad_fecha["ini_der_izq"];
	$ciufec_ini_arr_aba=$row_ciudad_fecha["ini_arr_aba"]==null?"35":$row_ciudad_fecha["ini_arr_aba"];
	$ciufec_ini_anc_cell=$row_ciudad_fecha["anc_cel"]==null?"120":$row_ciudad_fecha["anc_cel"];
	$ciufec_ini_alt_cell=$row_ciudad_fecha["alt_cel"]==null?"10":$row_ciudad_fecha["alt_cel"];

?>

<div class="container-fluid">
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-pencil'></i> Configuración de impresión de cheques</h4>
		</div>			
			<form class="form-horizontal" method="POST" id="configurar_cheques" name="configurar_cheques" >
				<div class="panel-body">
				
				<div class="table-responsive">
						<table class="table table-bordered">
							<tr  class="info">
							
									<td style ="padding: 2px;">Descripción</td>
									<td style ="padding: 2px;" align="center">Punto inicial de izquierda a derecha</td>
									<td style ="padding: 2px;" align="center">Punto inicial de arriba hacia abajo</td>
									<td style ="padding: 2px;" align="center">Ancho de celda</td>
									<td style ="padding: 2px;" align="center">Alto de celda</td>
							</tr>
							<tr>
							<input type="hidden" name="guardar_confi" id="guardar_confi" value="guardar_confi">
							<td class='col-xs-3'>Nombre del beneficiario</td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ben_ini_der_izq ?>" style="text-align:right" id="ben_ini_der_izq" name="ben_ini_der_izq" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ben_ini_arr_aba ?>" style="text-align:right" id="ben_ini_arr_aba" name="ben_ini_arr_aba" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ben_ini_anc_cell ?>" style="text-align:right" id="ben_ini_anc_cell" name="ben_ini_anc_cell" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ben_ini_alt_cell ?>" style="text-align:right" id="ben_ini_alt_cell" name="ben_ini_alt_cell" required></td>
							</tr>
							<tr>
							<td class='col-xs-2'>Cantidad en números</td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canum_ini_der_izq ?>" style="text-align:right" id="canum_ini_der_izq" name="canum_ini_der_izq" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canum_ini_arr_aba ?>" style="text-align:right" id="canum_ini_arr_aba" name="canum_ini_arr_aba" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canum_ini_anc_cell ?>" style="text-align:right" id="canum_ini_anc_cell" name="canum_ini_anc_cell" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canum_ini_alt_cell ?>" style="text-align:right" id="canum_ini_alt_cell" name="canum_ini_alt_cell" required></td>
							</tr>
							<tr>
							<td class='col-xs-2'>Cantidad en letras</td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canle_ini_der_izq ?>" style="text-align:right" id="canle_ini_der_izq" name="canle_ini_der_izq" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canle_ini_arr_aba ?>" style="text-align:right" id="canle_ini_arr_aba" name="canle_ini_arr_aba" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canle_ini_anc_cell ?>" style="text-align:right" id="canle_ini_anc_cell" name="canle_ini_anc_cell" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $canle_ini_alt_cell ?>" style="text-align:right" id="canle_ini_alt_cell" name="canle_ini_alt_cell" required></td>
							</tr>
							<tr>
							<td class='col-xs-2'>Ciudad y fecha</td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ciufec_ini_der_izq ?>" style="text-align:right" id="ciufec_ini_der_izq" name="ciufec_ini_der_izq" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ciufec_ini_arr_aba ?>" style="text-align:right" id="ciufec_ini_arr_aba" name="ciufec_ini_arr_aba" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ciufec_ini_anc_cell ?>" style="text-align:right" id="ciufec_ini_anc_cell" name="ciufec_ini_anc_cell" required></td>
							<td class='col-xs-2'><input type="number" class="form-control" value="<?php echo $ciufec_ini_alt_cell ?>" style="text-align:right" id="ciufec_ini_alt_cell" name="ciufec_ini_alt_cell" required></td>
							</tr>
						</table>
				</div>

				 <div id="resultados_ajax"></div>
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-primary" onclick='resetear();' reset>Reset</button>
				   <button type="submit" class="btn btn-primary" id="guardar_configurar_cheques" >Guardar</button>
				</div>
			</form>
		</div>
	</div>
</div>

<?php }else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>

<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
<script src="../js/notify.js"></script>
</body>

</html>
<script>
//para guardar 
	$( "#configurar_cheques" ).submit(function( event ) {
	  $('#guardar_configurar_cheques').attr("disabled", true);
	 var parametros = $(this).serialize();
		 $.ajax({
				type: "POST",
				url: "../ajax/guardar_configurar_cheques.php",
				data: parametros,
				 beforeSend: function(objeto){
					$("#resultados_ajax").html("Guardando...");
				  },
				success: function(datos){
				$("#resultados_ajax").html(datos);
				$('#guardar_configurar_cheques').attr("disabled", false);
			  }
		});
	  event.preventDefault();
	})

	
function resetear(){
	if (confirm("Realmente desea reiniciar los datos?")){	
	$.ajax({
		type: "POST",
		url: "../ajax/guardar_configurar_cheques.php",
		data: "default=default",
		 beforeSend: function(objeto){
			$("#resultados_ajax").html("Reiniciando datos...");
		  },
		success: function(datos){
		$("#resultados_ajax").html(datos);
		$("#resultados_ajax").html("");
		}
		});
		event.preventDefault();
	};

}
</script>

