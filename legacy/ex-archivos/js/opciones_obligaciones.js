
$( "#asignar_obligacion" ).submit(function( event ) {
  $('#actualizar_obligacion').attr("disabled", true);
  
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/asigna_obligacion.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax2").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax2").html(datos);
			$('#actualizar_obligacion').attr("disabled", false);
			load(1);
		  }
	});
  event.preventDefault();
})