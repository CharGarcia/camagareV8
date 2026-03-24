<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Configura Inventarios</title>
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

//para buscar los logos de cada sucursal
$busca_info_sucursales=mysqli_query($con, "SELECT * FROM configuracion_inventarios WHERE ruc_empresa = '".$ruc_empresa."'" );

?>
	<div class="container-fluid">
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-pencil'></i> Configuración de inventarios</h4>
		</div>			
			<div class="panel-body">
			<form class="form-horizontal" id="configura_inventarios" method="POST" >
			<div class="panel-body">
				<label for="" class="col-sm-1 control-label"> Sucursal</label>
				<div class="col-sm-3">
						<select class="form-control" name="serie_sucursal" id="serie_sucursal">
							<option value="0" >Seleccione serie</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='$ruc_empresa' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</div> 
				<label for="" class="col-sm-3 control-label"> Depende de inventario?</label>
				<div class="col-sm-3">
						<select class="form-control" name="controla_inventario" id="controla_inventario">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
			
			<div class="col-sm-1">
						<button type="submit" class="btn btn-primary" name="guardar_datos" >Guardar</button>
			</div>
			</div>
			</form>
			<div id="resultados_ajax"></div><!-- Carga los datos ajax -->

			</div>
		</div>
	</div>
	</div>

	
<?php }else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
</body>

</html>
<script>
	//para guardar datos de si trabaja en base al inventario o no
$( "#configura_inventarios" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../ajax/guarda_configuracion_inventarios.php",
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_ajax").html("Mensaje: Guardando...");
					  },
					success: function(datos){
					$("#resultados_ajax").html(datos);
					$('#guardar_datos').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//para si esta o no aplicando el inventario de la sucursal cuando se seleccione la serie
$(function(){
	$('#serie_sucursal').change(function(){
		 var serie_sucursal = $(this).val();
		$.post( '../ajax/consulta_configuracion_inventario.php', {configura: 'opcion', serie_consultada: serie_sucursal}).done( function( respuesta ){
			$( '#resultados_ajax' ).html( respuesta );
			var opcion = $("#opcion_inventario").val();
			$("#controla_inventario").val(opcion);
		}); 
	});
			
});
</script>

