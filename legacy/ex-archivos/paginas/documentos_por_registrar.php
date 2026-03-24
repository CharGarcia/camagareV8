<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Registrar documentos</title>
<?php include("../head.php");?>
</head>

<body>
<?php
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario']) ){
$id_usuario = $_SESSION['id_usuario'];

$titulo_info ="Registrar documentos cargados";
include("../navbar_confi.php");	
include("../conexiones/conectalogin.php");
include("../modal/registrar_documentos.php");
$con = conenta_login();

?>

	<div class="container-fluid">
	<div class="col-md-10 col-md-offset-1">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-inbox'></i> Registro de documentos cargados</h4>
		</div>
			<div class="panel-body">
				<form class="form-horizontal" role="form" >
					<div class="form-group row">
						<label for="q" class="col-md-2 control-label">Buscar:</label>
						<div class="col-md-5">
							<input type="text" class="form-control" id="q" placeholder="Buscar documentos por empresa" onkeyup='load(1);'>
						</div>
						<div class="col-md-3">
							<button type="button" class="btn btn-default" onclick='load(1);'>
								<span class="glyphicon glyphicon-search" ></span> Buscar</button>
							<span id="loader"></span>
						</div>
					</div>
				</form>
			<div id="resultados_documentos"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
		</div>
	</div>
	</div>
			
<?php }else{
header('Location: ../includes/logout.php');
exit;
}

?>
<script type="text/javascript" src="../js/bootstrap-filestyle.js"> </script>
</body>

</html>
<script>
$(document).ready(function(){
			load(1);
		});
function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_documentos_cargados_procesar.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
}

function procesar_doc(id_doc){
	
			var nombre_empresa_viene = $("#empresa_documento"+id_doc).val();
			var nombre_documento_viene = $("#nombre_documento"+id_doc).val();
			var detalle_documento_viene = $("#detalle_documento"+id_doc).val();
			$("#detalle_transaccion").val('Empresa:'+ nombre_empresa_viene +' //Documento:'+ nombre_documento_viene + ' //Detalle:'+ detalle_documento_viene);
			$("#id_documento").val(id_doc);
	
			var id_documento= $("#codigo_documento"+id_doc).val();
			$("#loaderdoc").fadeIn('slow');
			$.ajax({
				url:'../ajax/muestra_documento_cargado.php?action=ajax&id_documento='+id_documento,
				 beforeSend: function(objeto){
				 $('#loaderdoc').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_divdoc").html(data).fadeIn('slow');
					$('#loaderdoc').html('');
				}
			})
	}

$(document).ready(function(){
    $("#cerrar").click(function(){
    $("#procesarDocumentos").modal("hide");
    });
    $("#procesarDocumentos").on('hidden.bs.modal', function () {
		 $.ajax({
				url:'../ajax/muestra_documento_cargado.php?action=cerrar',
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".loader").html(data).fadeIn('slow');
					$('#loader').html('');
				}
			})
    });
});
</script>

