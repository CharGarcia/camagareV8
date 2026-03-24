<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es" >

<head>
<title>Generador QR</title>
<?php include("../head.php");?>
</head>
<body>

<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
if($_SESSION['nivel'] >= 1 && isset($_SESSION['id_usuario'])){
$titulo_info ="Crear QR";
include("../navbar_confi.php");
include("../modal/nuevo_codigo_qr.php");

if (isset($_SESSION['id_usuario'])){
$delete_nuevoqr_tmp = mysqli_query($con, "DELETE FROM existencias_inventario_tmp WHERE id_usuario = '".$_SESSION['id_usuario']."'");
$files = glob('temp/*.png'); //obtenemos todos los nombres de los ficheros
foreach($files as $file){
    if(is_file($file))
    unlink($file); //elimino el fichero
}
}

?>
<div class="container-fluid">  
    <div class="panel panel-info">
		<div class="panel-heading">
		<div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevoCodigoQr"><span class="glyphicon glyphicon-plus" ></span> Nuevo</button>
		</div>
			<h4><i class='glyphicon glyphicon-search'></i> Códigos QR</h4>		
		</div>
		<div class="panel-body">
			<form class="form-horizontal" role="form" method ="POST" action="" >
						<div class="form-group row">
						<label for="q" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
							
								<input type="text" class="form-control" id="q" placeholder="Buscar" onkeyup='load(1);'>
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								 </span>
							</div>
							
							</div>
							<span id="loader"></span>						
						</div>
						
			</form>	
			<div id="resultados"></div><!-- Carga los datos ajax -->
			<div class='outer_div'></div><!-- Carga los datos ajax -->
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
<script type="text/javascript" src="../js/style_bootstrap.js"> </script>
<script src="../js/notify.js"></script>
<script>
$(document).ready(function(){
			load(1);
		});

function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_qr.php?action=buscar_qr&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}
		
	//para agregar un detalle	
$(function(){
        $("#codigogr").on("submit", function(e){
			var titulo_pestana = $("#titulo_pestana").val();
			if (titulo_pestana ==''){
			alert('Ingrese un título para la pestaña');
			document.getElementById('titulo_pestana').focus();
			return false;
			}
			
            e.preventDefault();
            var formData = new FormData(document.getElementById("codigogr"));
			formData.append("dato", "valor");
            $.ajax({
                url: "../ajax/detalle_agregar_qr.php?action=agregar_detalle",
                type: "post",
                dataType: "html",
                data: formData,
				beforeSend: function(objeto){
						$("#muestra_detalle_qr").html("Cargando...");
					  },
                cache: false,
                contentType: false,
				processData: false
            })
                .done(function(res){
						$(".outer_divdet").html(res).fadeIn('fast');
						$('#muestra_detalle_qr').html('');
						document.getElementById("titulo_pestana").value = "";
						document.getElementById("detalle").value = "";
						document.getElementById("imagen").value = "";					
                });
        });
    });		
		
	/*	
function agregar_detalle_qr(){
			var titulo_pestana = $("#titulo_pestana").val();
			var detalle= $("#detalle").val();
			//var imagen= $("#imagen").val();
			if (titulo_pestana ==''){
			alert('Ingrese un título para la pestaña');
			document.getElementById('titulo_pestana').focus();
			return false;
			}
							
			
			//Inicia validacion
			
			
			if (concepto ==''){
			alert('Ingrese concepto, ejemplo: nombre');
			document.getElementById('concepto').focus();
			return false;
			}
			if (detalle ==''){
			alert('Ingrese detalle, ejemplo: Juan Perez');
			document.getElementById('detalle').focus();
			return false;
			}
			$("#muestra_detalle_qr").fadeIn('fast');
			 $.ajax({
					url: "../ajax/detalle_agregar_qr.php?action=agregar_detalle&titulo_pestana="+titulo_pestana+"&detalle="+detalle+"&imagen="+imagen,
					 beforeSend: function(objeto){
						$("#muestra_detalle_qr").html("Cargando detalle...");
					  },
					success: function(data){
						$(".outer_divdet").html(data).fadeIn('fast');
						$('#muestra_detalle_qr').html('');
						document.getElementById("detalle").value = "";
						document.getElementById("concepto").value = "";
				  }
			});
			
}
*/

function eliminar_detalle_qr(id){
	$("#muestra_detalle_qr").fadeIn('fast');	
	$.ajax({
		type: "GET",
		url: "../ajax/detalle_agregar_qr.php",
		data: "action=eliminar_detalle&id_registro="+id,
		 beforeSend: function(objeto){
			$("#muestra_detalle_qr").html("Mensaje: Cargando...");
		  },
		success: function(datos){
		$(".outer_divdet").html(datos).fadeIn('fast');
		$("#muestra_detalle_qr").html('');
		}
	});

}

function eliminar_qr(codigo_unico){
	$("#loader").fadeIn('fast');	
	if (confirm("Realmente desea eliminar el registro?")){
	$.ajax({
		type: "GET",
		url: "../ajax/detalle_agregar_qr.php",
		data: "action=eliminar_qr&codigo_unico="+codigo_unico,
		 beforeSend: function(objeto){
			$("#loader").html("Mensaje: Cargando...");
		  },
		success: function(datos){
		$(".outer_div").html(datos).fadeIn('fast');
		$("#loader").html('');
		load(1);
		}
		
	});
	}
}


function guardar_qr(){
	$("#loader_resultados_qr").fadeIn('fast');	
	var titulo_general = $("#titulo_general").val();
		
	$.ajax({
		type: "POST",
		url: "../ajax/guardar_qr.php",
		data: "titulo_general="+titulo_general,
		 beforeSend: function(objeto){
			$("#loader_resultados_qr").html("Guardando...");
		  },
		success: function(datos){
		$(".resultados_qr").html(datos).fadeIn('fast');
		$("#loader_resultados_qr").html('');
		load(1);
		setTimeout(function () {location.reload()}, 60 * 20);
		}
	});
}


</script>