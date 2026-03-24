		$(document).ready(function(){
			load(1);
		});

		function load(page){
			var q= $("#q").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_usuarios.php?action=ajax&page='+page+'&q='+q,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}	
		
		
$( "#editar_usuario" ).submit(function( event ) {
  $('#actualizar_datos').attr("disabled", true);
  
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_usuario.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax2").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax2").html(datos);
			$('#actualizar_datos').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})
		

		function obtener_datos(id){
			var nombre_usuario = $("#nombre_usuario"+id).val();
			var tipo_usuario = $("#tipo_usuario"+id).val();
			var cedula_usuario = $("#cedula_usuario"+id).val();
			var estado_usuario = $("#estado_usuario"+id).val();
			var mail_usuario = $("#mail_usuario"+id).val();
			
	
			$("#mod_nombre").val(nombre_usuario);
			$("#mod_tipo").val(tipo_usuario);
			$("#mod_cedula").val(cedula_usuario);
			$("#mod_estado").val(estado_usuario);
			$("#mod_mail").val(mail_usuario);
			$("#mod_id").val(id);
		}
	
		
		

