<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">
<head>
<title>Configurar facturación</title>
</head>

<body>
<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

include("../paginas/menu_de_empresas.php"); 	
$con = conenta_login();

?>
	<div class="container-fluid">
	<div class="col-md-10 col-md-offset-1">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-pencil'></i> Configuraciones varias en facturación</h4>
		</div>	
<!--Para preguntar si trabaja con inventarios las facturas-->		
		<div class="panel-body">
			<div class="well well-sm">
			<div class="panel-heading">Trabajar con inventario en las facturas.</div>
			<form class="form-horizontal" method="post" id="guarda_trabajar_con_inventario" name="guarda_trabajar_con_inventario" >
			<input type="hidden" id="id_conf_aplica_inventario" name="id_conf_aplica_inventario">
			<input type="hidden" name="guarda_aplica_inventario" value="guarda_aplica_inventario">
				<div class="col-sm-3">
					<div class="input-group">
					<span class="input-group-addon"><b>Sucursal</b></span>
							<select class="form-control" name="serie_sucursal_trabaja_con_inventario" id="serie_sucursal_trabaja_con_inventario">
								<option value="0" >Seleccione</option>
								<?php
								$conexion = conenta_login();
								$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
								$res = mysqli_query($conexion,$sql);
								while($o = mysqli_fetch_assoc($res)){
								?>
								<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
								<?php
								}
								?>
							</select>
					</div>
				</div>				
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Inventario</b></span>
						<select class="form-control" name="opcion_trabaja_inventario" id="opcion_trabaja_inventario">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
				</div>
				<div class="col-sm-1">
					<button type="submit" class="btn btn-primary" id="guardar_datos_inventario" >Guardar</button>
				</div>
			</form>
			<div class="col-sm-1" id="resultados_trabaja_inventario"></div>
			<div class="panel-heading"></div>
			</div>
					
		<!--Para preguntar si tiene propina tasa-->
			<div class="well well-sm">
			<div class="panel-heading">Habilitar campos de propina y tasa turistica en la factura.</div>
			<form class="form-horizontal" id="configura_facturacion_propina_tasa" name="configura_facturacion_propina_tasa" method="POST" >
		<input type="hidden" name="id_conf_propina_tasa" id="id_conf_propina_tasa">
		<input type="hidden" name="guarda_propina_tasa" value="guarda_propina_tasa">
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Sucursal</b></span>
						<select class="form-control" name="serie_sucursal_propina_tasa" id="serie_sucursal_propina_tasa">
							<option value="0" >Seleccione</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</div>
				</div>
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Propina</b></span>
						<select class="form-control" name="aplica_propina" id="aplica_propina">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
				</div>
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Tasa</b></span>
						<select class="form-control" name="aplica_tasa" id="aplica_tasa">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
				</div>
			
			<div class="col-sm-1">
				<button type="submit" class="btn btn-primary" id="guardar_datos_propina_tasa" >Guardar</button>
			</div>
			</form>
			<div class="col-sm-1" id="resultados_propina_tasa"></div>
			<div class="panel-heading"></div>
			</div>
		
			
			<!--Para preguntar si quiere que se junten todos los clientes y productos-->
			<div class="well well-sm">
			<div class="panel-heading">Compartir clientes, productos y servicios entre sucursales de la misma empresa.</div>
			<form class="form-horizontal" id="compartir_clientes_productos" method="POST" >
				<input type="hidden" name="id_conf_facturacion_productos_clientes" id="id_conf_facturacion_productos_clientes">
				<input type="hidden" name="guarda_productos_clientes" value="guarda_productos_clientes">
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Sucursal</b></span>
						<select class="form-control" name="serie_sucursal_productos_clientes" id="serie_sucursal_productos_clientes">
							<option value="0" >Seleccione</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</div>
				</div>
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Clientes</b></span>
						<select class="form-control" name="compartir_clientes" id="compartir_clientes">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
				</div>
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Productos</b></span>
						<select class="form-control" name="compartir_productos" id="compartir_productos">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</div>
				</div>
			
			<div class="col-sm-1">
				<button type="submit" class="btn btn-primary" id="guardar_datos_productos_clientes" >Guardar</button>
			</div>
			</form>
			<div class="col-sm-1" id="resultados_clientes_productos"></div>
			<div class="panel-heading"></div>
			</div>
			
			
			<!--Para preguntar si quiere que aparezca lote, bodega, medida y fecha de caducidad al momento de seleccionar un producto-->
			<div class="well well-sm">
			<div class="panel-heading">Mostrar listados al momento de agregar un producto a la factura.</div>
			<form class="form-horizontal" id="opciones_mostrar_listados_inventario" method="POST" >
			<input type="hidden" name="id_conf_mostrar_opciones_inventario" id="id_conf_mostrar_opciones_inventario">
			<input type="hidden" name="guarda_lista_opciones_inventarios" value="guarda_lista_opciones_inventarios">
			<div class="table-responsive">
			<table class="table table-bordered" >
						<tr  class="default">
								<td class="text-center">Sucursal</td>
								<td class="text-center">Medida</td>
								<td class="text-center">Lote</td>
								<td class="text-center">Bodega</td>
								<td class="text-center">Vencimiento</td>
								<td class="text-center">Opción</td>
						</tr>
						
				<td class='col-xs-2'>
						<select class="form-control" name="serie_sucursal_opciones_inventario" id="serie_sucursal_opciones_inventario">
							<option value="0" >Seleccione</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</td> 
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_medida" id="mostrar_medida">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_lote" id="mostrar_lote">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_bodega" id="mostrar_bodega">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_caducidad" id="mostrar_caducidad">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
			
			<td class="col-sm-1" class="text-center">
				<button type="submit" class="btn btn-primary" id="guardar_datos_opciones_inventarios" > Guardar</button>
			</td>
			</table>
			</div>
			</form>
			<div class="col-sm-1" id="resultados_opciones_inventario"></div>
			<div class="panel-heading"></div>
			</div>
			
			<!--Para preguntar si quiere que aparezca lote, bodega, fecha de caducidad y medida en la factura cuando se imprima la factura-->
			<div class="well well-sm">
			<div class="panel-heading">Mostrar etiquetas en el pdf de la factura impresa.</div>
			<form class="form-horizontal" id="imprime_etiquetas" method="POST" >
				<input type="hidden" name="id_conf_imprime_etiquetas" id="id_conf_imprime_etiquetas">
				<input type="hidden" name="guarda_imprime_etiquetas" value="guarda_imprime_etiquetas">
			<div class="table-responsive">
			<table class="table table-bordered" >
						<tr  class="default">
								<td class="text-center">Sucursal</td>
								<td class="text-center">Medida</td>
								<td class="text-center">Lote</td>
								<td class="text-center">Bodega</td>
								<td class="text-center">Vencimiento</td>
								<td class="text-center">Opción</td>
						</tr>
						
				<td class='col-xs-2'>
						<select class="form-control" name="serie_sucursal_mostrar_impresion" id="serie_sucursal_mostrar_impresion">
							<option value="0" >Seleccione</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</td> 
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_medida_impresion" id="mostrar_medida_impresion">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_lote_impresion" id="mostrar_lote_impresion">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>				
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_bodega_impresion" id="mostrar_bodega_impresion">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
				<td class="col-sm-2">
						<select class="form-control" name="mostrar_caducidad_impresion" id="mostrar_caducidad_impresion">
							<option value="0">Seleccione</option>
							<option value="SI">SI</option>
							<option value="NO">NO</option>
						</select>
				</td>
			
			<td class="col-sm-1" class="text-center">
				<button type="submit" class="btn btn-primary" id="guardar_datos_impresion" > Guardar</button>
			</td>
			</table>
			</div>
			</form>
			<div class="col-sm-1" id="resultados_impresion_etiquetas"></div>
			<div class="panel-heading"></div>
			</div>
			

		<!--Para preguntar como calcula la salida del inventario-->
		
			<div class="well well-sm">
			<div class="panel-heading">Calculo para registrar las salidas del inventario.(El sistema hace el calculo de las existencia en base a la selección escogida)</div>
			<form class="form-horizontal" id="configura_salidas_inventario" method="POST" >
		<input type="hidden" name="id_conf_salidas_inventario" id="id_conf_salidas_inventario">
		<input type="hidden" name="guarda_salidas_inventario" value="guarda_salidas_inventario">
				<div class="col-sm-3">
				<div class="input-group">
					<span class="input-group-addon"><b>Sucursal</b></span>
						<select class="form-control" name="serie_sucursal_salidas_inventario" id="serie_sucursal_salidas_inventario">
							<option value="0" >Seleccione</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='".$ruc_empresa."' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?>"><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
				</div>
				</div>				
				<div class="col-sm-4">
					<div class="input-group">
						<span class="input-group-addon"><b>Calculo kardex</b></span>
							<select class="form-control" name="tipo_salida_inventario" id="tipo_salida_inventario">
								<option value="0" >Seleccione</option>
								<option value="Lote">Lote</option>
								<option value="Caducidad">Caducidad</option>
								<option value="Fifo">Fifo</option>
							</select>
					</div>
				</div>				
			<div class="col-sm-1">
				<button type="submit" class="btn btn-primary" id="guardar_salida_inventario" >Guardar</button>
			</div>
			</form>
			<div class="col-sm-1" id="resultados_salida_inventario"></div>
			<div class="panel-heading"></div>
			</div>
			
		</div>
		</div>
	</div>
	</div>

	
<?php }else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<script src="../js/notify.js"></script>
</body>

</html>
<script>

//para guardar datos de si trabaja en base al inventario o no
$( "#guarda_trabajar_con_inventario" ).submit(function( event ) {
		$('#guardar_datos_inventario').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: '../clases/guardar_bd.php',
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_trabaja_inventario').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_trabaja_inventario").html(datos);
					$('#guardar_datos_inventario').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//para guardar datos si quiere que se muestre tasa y propina
$( "#configura_facturacion_propina_tasa" ).submit(function( event ) {
		  $('#guardar_datos_propina_tasa').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../clases/guardar_bd.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_propina_tasa').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_propina_tasa").html(datos);
					$('#guardar_datos_propina_tasa').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//compartir clientes y productos
$( "#compartir_clientes_productos" ).submit(function( event ) {
		  $('#guardar_datos_productos_clientes').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../clases/guardar_bd.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_clientes_productos').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_clientes_productos").html(datos);
					$('#guardar_datos_productos_clientes').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//para ver si trabaja con lote, bodega, vencimiento, medida
$( "#opciones_mostrar_listados_inventario" ).submit(function( event ) {
		  $('#guardar_datos_opciones_inventarios').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../clases/guardar_bd.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_opciones_inventario').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_opciones_inventario").html(datos);
					$('#guardar_datos_opciones_inventarios').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//para ver si imprime las etiquetas en la factura, lote, bodega, vencimiento, medida
$( "#imprime_etiquetas" ).submit(function( event ) {
		  $('#guardar_datos_impresion').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../clases/guardar_bd.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_impresion_etiquetas').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_impresion_etiquetas").html(datos);
					$('#guardar_datos_impresion').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})

//para configurar en base a que se calcula la salida del inevntario
$( "#configura_salidas_inventario" ).submit(function( event ) {
		  $('#guardar_salida_inventario').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					 url: "../clases/guardar_bd.php",
					data: parametros,
					 beforeSend: function(objeto){
						$('#resultados_salida_inventario').html('<img src="../image/ajax-loader.gif">');
					  },
					success: function(datos){
					$("#resultados_salida_inventario").html(datos);
					$('#guardar_salida_inventario').attr("disabled", false);
				  }
			});
		  event.preventDefault();
})


//desde aqui las opciones para mostrar ------------- para ver opciones cuando se seleccione una sucursal de cualquier fila
$(function(){
	
	//para ver si aplica inventarios
	$('#serie_sucursal_trabaja_con_inventario').change(function(){
		 var serie_sucursal = $("#serie_sucursal_trabaja_con_inventario").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'aplica_inventario', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_trabaja_inventario' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var inventario = $("#inventario").val();
			$("#id_conf_aplica_inventario").val(id_conf);
			$("#opcion_trabaja_inventario").val(inventario);
		});	
	});
		
	//para ver si aplica inventarios
	$('#serie_sucursal_propina_tasa').change(function(){
		 var serie_sucursal = $("#serie_sucursal_propina_tasa").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'propina_tasa', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_propina_tasa' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var propina = $("#propina").val();
			var tasa = $("#tasa").val();
			$("#id_conf_propina_tasa").val(id_conf);
			$("#aplica_propina").val(propina);
			$("#aplica_tasa").val(tasa);
		});	
	});
	
	//para ver si comparte productos y clientes
	$('#serie_sucursal_productos_clientes').change(function(){
		 var serie_sucursal = $("#serie_sucursal_productos_clientes").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'productos_clientes', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_clientes_productos' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var clientes = $("#clientes").val();
			var productos = $("#productos").val();
			$("#id_conf_facturacion_productos_clientes").val(id_conf);
			$("#compartir_clientes").val(clientes);
			$("#compartir_productos").val(productos);
		});	
	});
	
	//para ver si se muestra bodega. lote, vencimiento y medida
	$('#serie_sucursal_opciones_inventario').change(function(){
		 var serie_sucursal = $("#serie_sucursal_opciones_inventario").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'opciones_inventario', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_clientes_productos' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var medida = $("#medida").val();
			var lote = $("#lote").val();
			var vencimiento = $("#vencimiento").val();
			var bodega = $("#bodega").val();
			$("#id_conf_mostrar_opciones_inventario").val(id_conf);
			$("#mostrar_medida").val(medida);
			$("#mostrar_lote").val(lote);
			$("#mostrar_caducidad").val(vencimiento);
			$("#mostrar_bodega").val(bodega);
		});	
	});
	
	//para ver se imprimen bodega. lote, vencimiento y medida
	$('#serie_sucursal_mostrar_impresion').change(function(){
		 var serie_sucursal = $("#serie_sucursal_mostrar_impresion").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'impresion_etiquetas', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_impresion_etiquetas' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var medida = $("#imprime_medida").val();
			var lote = $("#imprime_lote").val();
			var vencimiento = $("#imprime_vencimiento").val();
			var bodega = $("#imprime_bodega").val();
			$("#id_conf_imprime_etiquetas").val(id_conf);
			$("#mostrar_medida_impresion").val(medida);
			$("#mostrar_lote_impresion").val(lote);
			$("#mostrar_caducidad_impresion").val(vencimiento);
			$("#mostrar_bodega_impresion").val(bodega);
		});	
	});
	
	//para ver en que se basa el calculo para la salida del inventario
	$('#serie_sucursal_salidas_inventario').change(function(){
		 var serie_sucursal = $("#serie_sucursal_salidas_inventario").val();
		$.post( '../clases/guardar_bd.php', {opcion_consulta: 'calculo_salida_inventario', serie_sucursal: serie_sucursal}).done( function( respuesta){
			$( '#resultados_salida_inventario' ).html( respuesta);
			var id_conf = $("#id_configuracion").val();
			var calculo_salida = $("#calculo_salida").val();
			$("#id_conf_salidas_inventario").val(id_conf);
			$("#tipo_salida_inventario").val(calculo_salida);
		});	
	});
	
			
});

</script>

