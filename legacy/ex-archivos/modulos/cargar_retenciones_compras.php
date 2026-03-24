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
  
  <meta charset="utf-8">
  <title>Cargar Compras</title>
	<?php include("../paginas/menu_de_empresas.php");
	?>
  </head>
  <body>

<div class="container">  
    <div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Cargar Retenciones</h4>		
		</div>
			<div class="panel-body">
			<div class="row">
			<div class="col-md-5">
				<div class="panel panel-info">
					<div class="table table-bordered">
					  <table class="table">
						<tr  class="info">
							<th colspan="2">Cargar retenciones de compras desde excel</th>
						</tr>
						<tr>
							<form method="post" action="" id="cargar_archivo" name="cargar_archivo" enctype="multipart/form-data">
								<div class="form-group row">
								<td class="col-xs-10">
									<input  class="filestyle" data-buttonText=" Archivo" type="file" id="archivo" name="archivo" data-buttonText="Archivo excel" multiple />
								</td>
								<td class="col-xs-2">
									<button type="submit" class="btn btn-info" name="subir" ><span class="glyphicon glyphicon-upload" ></span> Cargar</button>
								</td>
								</div>
							</form>
						<span id="loader_archivo_carga"></span>	
						</tr>				
					  </table>
					</div>
				</div>
				
			</div>
			<div class="col-md-7">
				<div class="panel panel-info">
					<div class="table table-bordered">
					  <table class="table">
						<tr  class="info">
							<th colspan="2">Opciones de cargas</th>
						</tr>
						<tr>
						<div class="form-group row">
						<td class="col-xs-1">
						<a href="../descargas/cargar_retenciones_compras.xlsx" class="list-group-item list-group-item-warning" target="_blank"><span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span> Descargar archivo ejemplo </a>	
						</td>
						<td class="col-xs-1">
							<div id="loader_carga"></div><!-- Carga los datos ajax -->
							<div class='outer_div_carga'></div><!-- Carga los datos ajax -->
						</td>
						</div>
						</tr>					
					  </table>
					</div>
				</div>
			</div>
			</div>
				<div id="resultados_cargar"></div><!-- Carga los datos ajax -->
				<div class='outer_div_cargar'></div><!-- Carga los datos ajax -->
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
<script src="../js/notify.js"></script>
 </body>
</html>
<script>
$(document).ready(function(){
	//buscar_cargas();
});

$(function(){
        $("#cargar_archivo").on("submit", function(e){
            e.preventDefault();
            var formData = new FormData(document.getElementById("cargar_archivo"));
			formData.append("dato", "valor");
			
            $.ajax({			
                url: "../ajax/cargar_retenciones_compras.php?action=cargar_retenciones_compras",
                type: "post",
                dataType: "html",
                data: formData,
				beforeSend: function(objeto){					
				$('#loader_archivo_carga').html('<div class="progress"><div class="progress-bar progress-bar-success progress-bar-striped active" role="progressbar" style="width:100%;">Procesando archivo excel, espere por favor...</div></div>');					    
					},
                cache: false,
                contentType: false,
				processData: false
            })
                .done(function(res){			
                    $("#resultados_cargar").html(res);
					$("#loader_archivo_carga").html('');
					//load(1);
                });

        });	
});

</script>
