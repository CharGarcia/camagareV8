$(document).ready(function(){
	inicio();
	});
	
function inicio(){
	$("#financiero").hide();
	$("#control").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion:'inicial'}).done( function(respuesta)
		{		
			$("#codigo_nivel_uno").val(parseInt(respuesta));
			$("#codigo_cuenta").val(parseInt(respuesta));
			$("#nivel_cuenta").val('1');
		});
		load(1);
		document.getElementById("guardar_cuenta").reset();
}

//cada vez que cambie el select nivel uno
$('#clase_cuenta').change(function(){
	var id_cuenta = document.getElementById("clase_cuenta").value;
	$("#control").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	
	if (id_cuenta =="0"){
		inicio();
	$("#nivel_cuenta").val('1');
	$("#financiero").hide();
	$("#control").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	}else{
		
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'codigo_cuenta_seleccionada', id_cuenta: id_cuenta, nivel:'1'}).done( function( respuesta ){
		$("#codigo_nivel_uno").val(respuesta);
		$("#codigo_cuenta").val(respuesta);
		});

		$("#nivel_cuenta").val('2');
		$("#financiero").show();
		//carga la info en el select dos
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'cargar_select_dos', id_cuenta: id_cuenta}).done( function( respuesta_select_dos ){
		$("#financiero_cuenta").html(respuesta_select_dos);
		});
		
		//para traer el codigo de cuenta de la siguiente cuenta
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'siguiente_codigo_cuenta', id_cuenta: id_cuenta, nivel:'2'}).done( function( respuesta_siguiente_codigo){
		$("#codigo_nivel_dos").val(respuesta_siguiente_codigo)
		var codigo_nivel_uno = document.getElementById("codigo_nivel_uno").value;
		$("#codigo_cuenta").val(parseInt(codigo_nivel_uno)+"."+parseInt(respuesta_siguiente_codigo));
		});
	}
});


//cada vez que cambie el select nivel dos
$('#financiero_cuenta').change(function(){
	var id_cuenta = document.getElementById("financiero_cuenta").value;
	
	$("#detalle").hide();
	$("#movimiento").hide();
	if (id_cuenta =="0"){
	inicio();
	$("#clase_cuenta").val("0");
	$("#nivel_cuenta").val('2');
	$("#financiero").hide();
	$("#control").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	}else{
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'codigo_cuenta_seleccionada', id_cuenta: id_cuenta, nivel:'2'}).done( function( respuesta ){
		$("#codigo_nivel_dos").val(respuesta);
		$("#codigo_cuenta").val(respuesta);
		});
	$("#nivel_cuenta").val('3');
	$("#control").show();
		//carga la info del select tres
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'cargar_select_tres', id_cuenta: id_cuenta}).done( function( respuesta_select_tres ){
		$("#control_cuenta").html(respuesta_select_tres);
		});
		
		//siguiente codigo de cuenta en nivel tres
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'siguiente_codigo_cuenta', id_cuenta: id_cuenta, nivel:'3'}).done( function( respuesta_siguiente_codigo){
		$("#codigo_nivel_tres").val(respuesta_siguiente_codigo);
		var codigo_nivel_uno = document.getElementById("codigo_nivel_uno").value;
		var codigo_nivel_dos = document.getElementById("codigo_nivel_dos").value;
		var codigo_nivel_tres = String("00" + parseInt(respuesta_siguiente_codigo)).slice(-2);
		$("#codigo_cuenta").val(parseInt(codigo_nivel_uno)+"."+parseInt(codigo_nivel_dos)+"."+codigo_nivel_tres);
		});
	}
});


//cada vez que cambie el select nivel tres
$('#control_cuenta').change(function(){
	var id_cuenta = document.getElementById("control_cuenta").value;
	$("#movimiento").hide();
	if (id_cuenta =="0"){
	inicio();
	$("#clase_cuenta").val("0");
	$("#nivel_cuenta").val('3');
	$("#financiero").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	}else{
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'codigo_cuenta_seleccionada', id_cuenta: id_cuenta, nivel:'3'}).done( function( respuesta ){
		$("#codigo_nivel_tres").val(respuesta);
		$("#codigo_cuenta").val(respuesta);
		});
		
	$("#nivel_cuenta").val('4');
	$("#detalle").show();
		
		//carga la info del select cuatro
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'cargar_select_cuatro', id_cuenta: id_cuenta}).done( function( respuesta_select_tres ){
		$("#detalle_cuenta").html(respuesta_select_tres);
		});
		
		//siguiente codigo de cuenta en nivel tres
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'siguiente_codigo_cuenta', id_cuenta: id_cuenta, nivel:'4'}).done( function( respuesta_siguiente_codigo){
		$("#codigo_nivel_cuatro").val(respuesta_siguiente_codigo);
		var codigo_nivel_uno = document.getElementById("codigo_nivel_uno").value;
		var codigo_nivel_dos = document.getElementById("codigo_nivel_dos").value;
		var codigo_nivel_tres = String("00" + parseInt(document.getElementById("codigo_nivel_tres").value)).slice(-2);
		var codigo_nivel_cuatro = String("00" + parseInt(respuesta_siguiente_codigo)).slice(-2)
		$("#codigo_cuenta").val(parseInt(codigo_nivel_uno)+"."+parseInt(codigo_nivel_dos)+"."+codigo_nivel_tres+"."+codigo_nivel_cuatro);
		});

	}
});

//cada vez que cambie el select nivel cuatro
$('#detalle_cuenta').change(function(){
	var id_cuenta = document.getElementById("detalle_cuenta").value;
	if (id_cuenta =="0"){
	inicio();
	$("#clase_cuenta").val("0");
	$("#nivel_cuenta").val('4');
	$("#financiero").hide();
	$("#detalle").hide();
	$("#movimiento").hide();
	}else{
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'codigo_cuenta_seleccionada', id_cuenta: id_cuenta, nivel:'4'}).done( function( respuesta ){
		$("#codigo_nivel_cuatro").val(respuesta);
		$("#codigo_cuenta").val(respuesta);
		});
	$("#nivel_cuenta").val('5');
	$("#movimiento").show();
		
		//carga la info del select cuatro
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'cargar_select_cinco', id_cuenta: id_cuenta}).done( function( respuesta_select_tres ){
		$("#movimiento_cuenta").html(respuesta_select_tres);
		});
		
		//siguiente codigo de cuenta en nivel cuatro
		$.post( '../ajax/consulta_datos_plan_cuentas.php', {opcion: 'siguiente_codigo_cuenta', id_cuenta: id_cuenta, nivel:'5'}).done( function( respuesta_siguiente_codigo){
		$("#codigo_nivel_cinco").val(respuesta_siguiente_codigo);
		var codigo_nivel_uno = document.getElementById("codigo_nivel_uno").value;
		var codigo_nivel_dos = document.getElementById("codigo_nivel_dos").value;
		var codigo_nivel_tres = String("00" + parseInt(document.getElementById("codigo_nivel_tres").value)).slice(-2);
		var codigo_nivel_cuatro = String("00" + parseInt(document.getElementById("codigo_nivel_cuatro").value)).slice(-2);
		var codigo_nivel_cinco = String("000" + parseInt(document.getElementById("codigo_nivel_cinco").value)).slice(-3);
		$("#codigo_cuenta").val(parseInt(codigo_nivel_uno)+"."+parseInt(codigo_nivel_dos)+"."+codigo_nivel_tres+"."+codigo_nivel_cuatro+"."+codigo_nivel_cinco);
		});

	}
});


function load(page){
			var q= $("#q").val();
			var por= $("#por").val();
			var ordenado= $("#ordenado").val();
			$("#loader").fadeIn('slow');
			$.ajax({
				url:'../ajax/buscar_cuentas_contables.php?action=cuentas_contables&page='+page+'&q='+q+"&por="+por+"&ordenado="+ordenado,
				 beforeSend: function(objeto){
				 $('#loader').html('<img src="../image/ajax-loader.gif"> Cargando...');
			  },
				success:function(data){
					$(".outer_div").html(data).fadeIn('slow');
					$('#loader').html('');
					
				}
			})
		}
		

function ordenar(ordenado){
	$("#ordenado").val(ordenado);
	var por= $("#por").val();
	var q= $("#q").val();
	var ordenado= $("#ordenado").val();
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

$( "#guardar_cuenta" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/guardar_cuenta_contable.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_cuentas").html("Mensaje: Guardando...");
			  },
			success: function(datos){
			$("#resultados_ajax_cuentas").html(datos);
			$('#guardar_datos').attr("disabled", false);
			load(1);
			inicio();
		  }
	});
  event.preventDefault();
})

$( "#editar_cuenta" ).submit(function( event ) {
  $('#actualizar_datos').attr("disabled", true);
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/editar_cuenta_contable.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_editar_cuentas").html("Mensaje: Actualizando...");
			  },
			success: function(datos){
			$("#resultados_ajax_editar_cuentas").html(datos);
			$('#actualizar_datos').attr("disabled", false);
			document.getElementById("editar_cuenta").reset();
			load(1);
			
		  }
	});
	event.preventDefault();
})	

function eliminar_cuenta_contable(id){
			var q= $("#q").val();
		if (confirm("Realmente desea eliminar la cuenta contable?")){	
		$.ajax({
        type: "GET",
        url: "../ajax/buscar_cuentas_contables.php",
        data: "action=eliminar_cuentas_contables&id_cuenta="+id,"q":q,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		load(1);
		}
			});
		}
}

function obtener_datos(id){
		var nombre_cuenta = $("#nombre_cuenta"+id).val();
		var codigo_sri = $("#codigo_sri"+id).val();
		var codigo_supercias = $("#codigo_supercias"+id).val();
		var nivel_cuenta = $("#nivel_cuenta"+id).val();
		var codigo_cuenta = $("#codigo_cuenta"+id).val();

		$("#mod_nombre_cuenta").val(nombre_cuenta);
		$("#mod_codigo_sri").val(codigo_sri);
		$("#mod_codigo_supercias").val(codigo_supercias);
		$("#mod_nivel_cuenta").val(nivel_cuenta);
		$("#mod_codigo_cuenta").val(codigo_cuenta);
		$("#mod_id_cuenta").val(id);
		}
		