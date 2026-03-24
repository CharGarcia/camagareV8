<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Perfil usuario</title>
<?php include("../head.php");?>
</head>

<body>
<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
	$id_usuario = $_SESSION['id_usuario'];

$titulo_info ="perfil de usuario";
include("../navbar_confi.php");	
include("../conexiones/conectalogin.php");
$con = conenta_login();
//para buscar el usuario
$busca_usuario = "SELECT * FROM usuarios WHERE id = $id_usuario";
$resultado_de_la_busqueda = $con->query($busca_usuario);
$row=mysqli_fetch_array($resultado_de_la_busqueda);
$nombre = $row['nombre'];
$mail = $row['mail'];
$telefono = $row['telefono'];


?>

	<div class="container-fluid">
	<div class="col-md-6 col-md-offset-3">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-pencil'></i> Perfil de usuario</h4>
		</div>			
			<div class="panel-body">
			
			<form class="form-horizontal" method="post" id="editar_usuario" name="editar_usuario" enctype="multipart/form-data">
					<div class="form-group">
						<label for="" class="col-sm-3 control-label">Nombre</label>
						<div class="col-sm-8">
						   <input type="text" class="form-control" name="usuario" value= "<?php echo $nombre; ?>" required >
						</div>
					 </div>
					<div class="form-group">
							<label for="" class="col-sm-3 control-label">Mail</label>
						  <div class="col-sm-8">
							 <input type="text" class="form-control" name="mail" value= "<?php echo $mail; ?>">
						  </div>
					</div>
					<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Teléfono</label>
						<div class="col-sm-8">
							<input type="text" class="form-control" name="telefono" value= "<?php echo $telefono; ?>" >
						</div>
					</div>

					 				<div id="resultados_ajax"></div>
			</div>
					<div class="modal-footer">
					   <button type="submit" class="btn btn-primary" id="guardar_perfil_usuario" >Guardar</button>
					</div>
            </form>
			
		</div>
	</div>
	</div>
	<hr>



<?php }else{
header('Location: ../includes/logout.php');
exit;
}
?>

<script type="text/javascript" src="../js/bootstrap-filestyle.js"> </script>
</body>

</html>
<script>
//para pasar archivos file debe ser este codigo ajax 
$(function(){
        $("#editar_usuario").on("submit", function(e){
            e.preventDefault();
            var f = $(this);
            var formData = new FormData(document.getElementById("editar_usuario"));
            formData.append("dato", "valor");
            $.ajax({
                url: "../ajax/editar_perfil_usuario.php",
                type: "post",
                dataType: "html",
                data: formData,
                cache: false,
                contentType: false,
	     processData: false
            })
                .done(function(res){
                    $("#resultados_ajax").html(res);
                });
        });
    });

</script>

