<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
?>
<!DOCTYPE html>
<html lang="en">
  <head>
  <meta charset="utf-8">
  <title>Offline</title>
	<?php include("../paginas/menu_de_empresas.php");
		  include("../modal/enviar_documentos_sri.php");
	?>
  </head>
  <body>

<div class="container">  
    <div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-search'></i> Generar pdf-xml offline</h4>	
		</div>
				<div class="btn-group pull-center">
					<div id='outer_div_resultados'></div><!-- Carga los datos ajax -->		
				</div>
		<ul class="nav nav-tabs nav-justified">
			<li class="active"><a data-toggle="tab" href="#factura">Facturas</a></li>
			<li><a data-toggle="tab" href="#retencion">Retenciones</a></li>
			<li><a data-toggle="tab" href="#guia_remision">Guías de remisión</a></li>
			<li><a data-toggle="tab" href="#nota_credito">Notas de crédito</a></li>
			<li><a data-toggle="tab" href="#nota_debito">Notas de débito</a></li>
			<li><a data-toggle="tab" href="#liquidacion">Liquidaciones</a></li>
		</ul>
	 
	<div class="tab-content">
    <div id="factura" class="tab-pane fade in active">
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="fa" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<input type="hidden" id="ordenado" value="id_encabezado_factura">
							<input type="hidden" id="por" value="desc">
							<div class="input-group">
								<input type="text" class="form-control" id="fa" placeholder="Cliente, serie, factura, fecha, ruc, estado" onkeyup='load(1);'>
								 <span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								  </span>
							</div>
							</div>
						</div>
			</form>
			<div class='outer_div_fa'></div><!-- Carga los datos ajax -->
			</div>
		</div>
    
 <div id="retencion" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="re" placeholder="" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
							<span id="loader_re"></span>
						</div>
			</form>
			<div class='outer_div_re'></div><!-- Carga los datos ajax -->
			</div>
	</div>
	<div id="guia_remision" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="gr" placeholder="" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
								<span id="loader_gr"></span>
						</div>
			</form>
			<div class='outer_div_gr'></div><!-- Carga los datos ajax -->
			</div>
	</div>
	<div id="nota_credito" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="nc" placeholder="" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
								<span id="loader_nc"></span>
						</div>
			</form>
			<div class='outer_div_nc'></div><!-- Carga los datos ajax -->
			</div>
	</div>
	<div id="nota_debito" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="nd" placeholder="" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
								<span id="loader_nd"></span>
						</div>
			</form>
			<div class='outer_div_nd'></div><!-- Carga los datos ajax -->
			</div>
	</div>
	<div id="liquidacion" class="tab-pane fade">		
			<div class="panel-body">
			<form class="form-horizontal" role="form" >
						<div class="form-group row">
							<label for="d" class="col-md-1 control-label">Buscar:</label>
							<div class="col-md-5">
							<div class="input-group">
								<input type="text" class="form-control" id="lc" placeholder="" onkeyup='load(1);'>
								<span class="input-group-btn">
								<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
								</span>
							</div>
							</div>
								<span id="loader_lc"></span>
						</div>
			</form>
			<div class='outer_div_lc'></div><!-- Carga los datos ajax -->
			</div>
	</div>
	</div>
	
	</div>
  </div>

 

<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
<script src="../js/notify.js"></script>
 </body>
</html>
<script>
$(document).ready(function(){
			load(1);
});

function load(page){
			var por= $("#por").val();
			var ordenado= $("#ordenado").val();
			var fa= $("#fa").val();
			var re= $("#re").val();
			var gr= $("#gr").val();
			var nc= $("#nc").val();
			var nd= $("#nd").val();
			var lc= $("#lc").val();
			$("#loader_fa").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_pdf_xml.php?action=fa&page='+page+'&fa='+fa+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader_fa').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_fa").html(data).fadeIn('slow');
					$('#loader_fa').html('');
				}
			});
			
			$("#loader_re").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_pdf_xml.php?action=re&page='+page+'&re='+re+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader_re').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_re").html(data).fadeIn('slow');
					$('#loader_re').html('');
				}
			});
			
			$("#loader_nc").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_pdf_xml.php?action=nc&page='+page+'&nc='+nc+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader_nc').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_nc").html(data).fadeIn('slow');
					$('#loader_nc').html('');
				}
			});
			
			$("#loader_gr").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_pdf_xml.php?action=gr&page='+page+'&gr='+gr+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader_gr').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_gr").html(data).fadeIn('slow');
					$('#loader_gr').html('');
				}
			});
			
			$("#loader_lc").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_pdf_xml.php?action=lc&page='+page+'&lc='+lc+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader_gr').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div_lc").html(data).fadeIn('slow');
					$('#loader_lc').html('');
				}
			});
			
			
};

function ordenar(ordenado){
	$("#ordenado").val(ordenado);
	var ordenado= $("#ordenado").val();
	var por= $("#por").val();
	var fa= $("#fa").val();
	var re= $("#re").val();
	var gr= $("#gr").val();
	var nc= $("#nc").val();
	var nd= $("#nd").val();
	var lc= $("#lc").val();
	$("#loader").fadeIn('slow');
	var value_por=document.getElementById('por').value;
			if (value_por=="asc"){
			$("#por").val("desc");
			}
			if (value_por=="desc"){
			$("#por").val("asc");
			}
	load(1);
}

function generar_pdf_xml(id){
			var id_documento_sri =  $("#id_documento"+id).val();
			var tipo_documento_sri =  $("#documento"+id).val();
			var pagina = $("#pagina").val();
				 $.ajax({
					type: 'POST',
					url: '../facturacion_electronica/enviarComprobantesSri.php',
					data:'tipo_documento_sri='+tipo_documento_sri+'&id_documento_sri='+id_documento_sri+'&modo_envio=offline',
					 beforeSend: function(objeto){
						 $.notify('Generando pdf y xml espere por favor...','warning');
					  },
					success: function(datos){
					$("#outer_div_resultados").html(datos);
					load(pagina);
					window.location.href = '../ajax/imprime_documento.php?id_documento='+btoa(id)+'&tipo_documento='+tipo_documento_sri+'&tipo_archivo=pdf';
				  }
			});
		  event.preventDefault();
};

</script>