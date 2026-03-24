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
  <title>Mail masivo</title>
  
	<?php include("../paginas/menu_de_empresas.php");
	ini_set('date.timezone','America/Guayaquil'); 
	?>
  </head>
  <body>

<div class="container">
	<div class="panel panel-info">
		<div class="panel-heading">

			<h4><i class='glyphicon glyphicon-search'></i> Enviar mails masivos</h4>	
		</div>
		<div class="panel-body">
			
				<form class="form" role="form">
				<div class="form-group row">
					<label for="desde" class="col-md-1 control-label">Desde</label>
					<div class="col-md-2">
						<input type="text" name="desde" id="desde" class="form-control datepicker" value="<?php echo date("d-m-Y") ?>" autocomplete="off">
					</div>
					
					<label for="hasta" class="col-md-1 control-label">Hasta</label>
					<div class="col-md-2">
						<input type="text" name="hasta" id="hasta" class="form-control datepicker" value="<?php echo date("d-m-Y") ?>" autocomplete="off">
					</div>
					<label for="documento" class="col-md-1 control-label">Documento</label>
					<div class="col-md-2">
						<select class="form-control" name="documento" id="documento" required>
						<option value="1">Facturas</option>
						<option value="2">Retenciones</option>
						<option value="3">Notas de crédito</option>
						<option value="4">Guías de remisión</option>
						<option value="5">Notas de débito</option>
						</select>
					</div>

					<div class="col-md-1">
						<button type="button" class="btn btn-default" onclick='search();'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
					</div>

					<div class="col-md-2">
						<button type="button" class="btn btn-success" onclick='procesar_comp();'><span class="glyphicon glyphicon-share-alt" ></span> Enviar  <span class="badge" id="count"></span></button>
						<input type="hidden" name="id_comp[]" id="id_comp" value="">
					</div>
				</div>
				</form>
				<div class="form-group row">
				<div class="col-md-12">
				
				<div id="loader"></div>
				</div>
				</div>
				<div id='resultados'></div><!-- Carga los datos ajax -->
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
  $( function() {
    $( "#desde" ).datepicker({ dateFormat: 'dd-mm-yy' });
    $( "#hasta" ).datepicker({ dateFormat: 'dd-mm-yy' });
  } );
 
  function search(){
		$("#loader").fadeIn('slow');
		var desde = $( "#desde" ).val();
		var hasta = $( "#hasta" ).val();
		var documento = $( "#documento" ).val();
		var count = 0;
		$.ajax({
			url:'../ajax/buscar_mails_pendientes.php?action=buscar_comp&desde='+desde+'&hasta='+hasta+'&documento='+documento,
			 beforeSend: function(objeto){
			 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
		  },

		  
			success:function(data){
				$("#resultados").empty();
				var content = '';
				var id_comp = [];
				content += '<div class="panel panel-info">';
				content += '<div class="table-responsive">';
				content += '<table class="table table-hover">';
				content += '<tbody>';
				content += '<tr class="info">';
					content += '<th>Fecha</th>';
					content += '<th>Cliente/proveedor</th>';
					content += '<th>Número</th>';
					content += '<th>Documento</th>';
					content += '<th>Mail</th>';
					content += '<th>Opción</th>';
					content += '</tr>';
					
					$.each(data, function(i, item) {
					    content  += '<tr>';
					    content  += '<td>' + item.fecha + '</td>';
					    content  += '<td>' + item.cliente + '</td>';
					    content  += '<td>' + item.num_doc + '</td>';
						content  += '<td>' + item.documento + '</td>';
					    content  += '<td>' + item.mail + '</td>';
					    content  += '<td><input type="checkbox" class="form-control" id="enviar_'+ item.id +'" name="enviar_'+item.id+'" onclick="contar('+item.id+','+count+')"></td>';
					    content  += '</tr>';
					});
					
					content  += '</tbody>';
					content  += '</table>';
					content  += '</div>';
				content  += '</div>';
				$("#id_comp").val(id_comp);
				$("#count").html( count );
                $("#resultados").append( content );
				$('#loader').html('');
				
			}
			
			
		})
	};

	function contar(id,pos){
		var id_comp_actuales = $('#id_comp').val();
		var id_comp = [];
	    id_comp = id_comp_actuales.split(',');
	    id_comp = JSON.parse("[" + id_comp + "]");
		
		var count_actual = parseInt($("#count").text());
		var new_count = 0;
		if ($("#enviar_"+id).is(':checked')) {
			new_count = count_actual + parseInt(1);
			id_comp.push(id);
		}else{
			new_count = count_actual - parseInt(1);
			var pos = id_comp.indexOf(id);
			id_comp.splice(pos, 1);
		}
		$("#id_comp").val(id_comp);
		$("#count").html(new_count);

   }
   
   //enviar comprobantes al sri
   function procesar_comp() {
	var mails_select = $('#id_comp').val();
	var tipo_documento = $("#documento").val();
	
	if (mails_select =='') {
		$.notify('No hay comprobantes seleccionados para enviar correos.','error');
	}else{	
   	var r = confirm("Esta seguro que desea enviar los correos seleccionados?");
   	if (r == true) {
   		
   		if (mails_select!='') {
				
			var progreso=0;
			var total_documentos =parseInt(100/$("#count").text());
			
			$.ajax({
			  type: "POST",
			  dataType: "html",
			  url: '../ajax/buscar_mails_pendientes.php?action=procesar_comp',
			  data: {mails_select:mails_select, documento:tipo_documento},
				 
			 beforeSend: function(){			
				$('#loader').html(	'<div class="progress"><div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" style="width:100%;">Enviando correos, espere por favor...</div></div>');
			 }, 
				 
			  success:function(datos){
				  $("#resultados").html(datos);
			  	  $('#loader').html('');
			  }
		})
			
   		}else{
			$.notify('No hay comprobantes seleccionados para enviar','error');
   		}
   	}
	}
   }

</script>