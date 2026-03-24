<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Subir documentos</title>
<?php include("../head.php");?>
</head>

<body>
<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
	$id_usuario = $_SESSION['id_usuario'];

$titulo_info ="Subir documentos";
include("../navbar_confi.php");	
include("../conexiones/conectalogin.php");
$con = conenta_login();
//para buscar el usuario
$busca_empresas = "SELECT ea.id_empresa as codigo, em.nombre as nombre FROM empresa_asignada as ea, empresas as em WHERE ea.id_empresa = em.id and ea.id_usuario = $id_usuario";
$resultado_de_la_busqueda = $con->query($busca_empresas);
?>

	<div class="container-fluid">
	<div class="col-md-6 col-md-offset-3">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-pencil'></i> Enviar documentos para procesar</h4>
		</div>			
			<div class="panel-body">
			
			<form class="form-horizontal" method="post" id="subir_documentos" name="subir_documentos" enctype="multipart/form-data">
					<div class="form-group">
						<label for="" class="col-sm-3 control-label">Empresa</label>
						<div class="col-sm-8">
						   <select class="form-control" name="empresa" required>
									<option value="">Seleccione empresa</option>
									<?php
									while($empresa = mysqli_fetch_assoc($resultado_de_la_busqueda)){		
									?>
									<option value="<?php echo $empresa['codigo']; ?>"><?php echo $empresa['nombre']; ?> </option>
									<?php
									}
									?>
							</select>
						</div>
					 </div>
					<div class="form-group">
						<label for="" class="col-sm-3 control-label">Documento</label>
						<div class="col-sm-8">
						   <select class="form-control" name="documento" required>
									<option value="">Seleccione tipo de documento</option>
							<?php
							$busca_documentos = "SELECT * FROM tipos_documentos_subir ";
							$resultado_de_la_busqueda = $con->query($busca_documentos);
									while($documentos = mysqli_fetch_assoc($resultado_de_la_busqueda)){		
									?>
									<option value="<?php echo $documentos['cod_documento']; ?>"><?php echo $documentos['detalle_documento']; ?> </option>
									<?php
									}
									?>
							</select>
						</div>
					 </div>
					<div class="form-group">
							<label for="" class="col-sm-3 control-label">Archivo</label>
						  <div class="col-sm-8">
							 <input class='filestyle' data-buttonText=" Documento" type="file" name="archivo">
						  </div>
					</div>
					<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Detalle</label>
						<div class="col-sm-8">
						<textarea class="form-control" rows="3" placeholder ="Ingrese un detalle del documento si lo cree necesario" name="detalle"></textarea>
						</div>
					</div>

					 				<div id="resultados_ajax"></div>
			</div>
					<div class="modal-footer">
					   <button type="submit" class="btn btn-primary" id="guardar_documentos" >Enviar</button>
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
        $("#subir_documentos").on("submit", function(e){
            e.preventDefault();
            var f = $(this);
            var formData = new FormData(document.getElementById("subir_documentos"));
            formData.append("dato", "valor");
            $.ajax({
                url: "../ajax/subir_documentos.php",
                type: "post",
                dataType: "html",
                data: formData,
                cache: false,
                contentType: false,
	     processData: false
            })
                .done(function(res){
					document.subir_documentos.reset(); 
                    $("#resultados_ajax").html(res);
                });
        });
    });

</script>

