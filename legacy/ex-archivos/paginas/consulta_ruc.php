<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es" >

<head>
<title>Ruc</title>
<?php include("../head.php");?>
<script src="../js/jquery.min.js"></script>
<script type="text/javascript" src="../js/select_ciudad.js"></script>
</head>
<body>

<?php
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario'])){
$titulo_info ="RUC";
include("../navbar_confi.php");

?>
<div class="col-md-12">
<div class="row">
	<div class="col-md-12 col-md-offset-0">
		<div class="panel panel-info" >
				<div class="panel-heading">
				<h4><i class='glyphicon glyphicon-search'></i> Consultar información del Ruc</h4>
				</div>	
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="q" class="col-md-2 control-label">Buscar:</label>
							<div class="col-md-4">
							<div class="input-group">
								<input type="text" class="form-control" id="numero" placeholder="Ruc, Nombre..." >
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='lee_ruc();'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								 </span> 
							</div>
							</div>
							<div class="col-md-3">
							<span id="loader"></span>
							</div>
							
						</div>
			</form>
			<div class='outer_div'></div><!-- Carga los datos ajax -->
			</div>
	</div>
</div>
</div>
</div>
<?php
}else{
header('Location: ../includes/logout.php');
exit;
}  
?>
</body>

</html>
<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
<script>
	
function lee_ruc(){
		var numero= $("#numero").val();
		$("#loader").fadeIn('slow');
		$.ajax({
			url: "../clases/leer_info_ruc.php?action=leer_ruc&numero="+numero,
			 beforeSend: function(objeto){
			 $("#loader").html('<img src="../image/ajax-loader.gif">');
		  },
			success:function(data){
				$(".outer_div").html(data).fadeIn('slow');
				$("#loader").html('');
			}
		});		
};

</script>