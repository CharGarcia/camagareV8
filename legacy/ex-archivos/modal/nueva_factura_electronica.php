<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">
  <head>
  <title>Factura Electrónica</title>
</head>	

<?php

session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];

include("../paginas/menu_de_empresas.php");
include("../modal/nuevo_cliente_factura.php");

ini_set('date.timezone','America/Guayaquil');
	//para borrar los datos de la factura que este en temporal
$con = conenta_login();
if (isset($_SESSION['id_usuario'])){
$delete_factura_tmp = mysqli_query($con, "DELETE FROM factura_tmp WHERE id_usuario = '".$id_usuario."' and ruc_empresa = '".$ruc_empresa."'");
$delete_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."'");
$delete_propina_tasa_tmp = mysqli_query($con, "DELETE FROM propina_tasa_tmp WHERE id_usuario = '".$id_usuario."'");
}
?>

<body>
	<?php 
	include("../modal/aplicar_descuento.php");
	include("../modal/registro_productos.php");	
	?>
	
	<div class="container" id="content">
		<div class="panel panel-info">
		
			<div class="panel-heading">
			<div class="btn-group pull-right">
			<form class="form-group" id="guardar_factura_e" name="guardar_factura_e" method="POST">
			<button id="guardar_datos_factura_e" type="submit" class="btn btn-info btn-md"><span class='glyphicon glyphicon-floppy-disk'></span> Guardar</button>
			</div>
			<h4><i class='glyphicon glyphicon-edit'></i> Nueva Factura Electrónica</h4>
			</div>
			
			<div class="panel-body">		
				<div id="resultados_ajax"></div>
				<div id="resultados_guardar_factura_electronica"></div>
			<div class="well well-sm">
					<div class="form-group row">
								<label class="col-sm-1 control-label">Fecha</label>
								<div class="col-sm-2">		
								<input type="text" class="form-control input-sm" name="fecha_factura_e" id="fecha_factura_e" value="<?php echo date("d-m-Y");?>">
								</div>
								<label class="col-sm-1 control-label">Serie</label>
								<div class="col-sm-2">
									<select class="form-control" name="serie_factura_e" id="serie_factura_e">
									<option value="0" >Seleccione</option>
										<?php
										$conexion = conenta_login();
										$sql = "SELECT * FROM sucursales where ruc_empresa ='$ruc_empresa' order by id_sucursal asc;";
										$res = mysqli_query($conexion,$sql);
										while($serie = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $serie['serie']?>"selected><?php echo $serie['serie']?></option>
										<?php
										}
										?>
									</select>
									</div>
								<div class="col-sm-2">							
								<input type="text" class="form-control input-sm" id="secuencial_factura_e" name="secuencial_factura_e"  placeholder="000000001" readonly>
								</div>
								<label class="col-sm-1 control-label">Guía R</label>
								  <div class="col-sm-3">
									<div class="input-group">
									  <input type="text" class="form-control input-sm" id="guia_factura_e" name="guia_factura_e" placeholder="Guía de remisión"><span class="input-group-btn btn-md"><button class="btn btn-info btn-md" onclick="secuencial_guia_remision()" type="button" title="Agregar guía de remisión"><span class="glyphicon glyphicon-plus"></span></button></span>
									</div>
								  </div>
								<input type="hidden" id="id_cliente_e" name="id_cliente_e">
								<input type="hidden" id="total_factura_e" name="total_factura_e">
								<input type="hidden" id="inventario" name="inventario">
								<input type="hidden" id="propina_final" name="propina_final">
								<input type="hidden" id="tasa_turistica_final" name="tasa_turistica_final">
					 </div>
					
					<div class="form-group row">
								<label class="col-sm-1 control-label">Cliente</label>
									  <div class="col-sm-5">
									  <div class="input-group">
									  <input style="z-index:inherit;" type="text" class="form-control input-sm" id="nombre_cliente_e" name="nombre_cliente_e" placeholder="Agregue un cliente por ruc o nombre" title="Buscar un cliente." onkeyup='buscar_clientes();' autocomplete="off"><span class="input-group-btn btn-md"><button class="btn btn-info btn-md" type="button" title="Nuevo cliente" data-toggle="modal" data-target="#nuevoClienteFactura"><span class="glyphicon glyphicon-pencil"></span></button></span>
									  </div>
									  </div>
								<label class="col-sm-1 control-label">Pago</label>
								<div class="col-sm-5">
									<select class="form-control" name="forma_pago_e" id="forma_pago_e">
									<option value="0" >Seleccione forma de pago</option>
									<option value="20" selected>OTROS CON UTILIZACION DEL SISTEMA FINANCIERO</option>
										<?php
										$conexion = conenta_login();
										$sql = "SELECT * FROM formas_de_pago WHERE aplica_a ='VENTAS' order by nombre_pago asc";
										$res = mysqli_query($conexion,$sql);
										while($o = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $o['codigo_pago']?>"><?php echo $o['nombre_pago']?></option>
										<?php
										}
										?>
									</select>
								</div>							
					</div>
			</div>
			<!-- para agregar los productos a la factura -->
			
				<div class="panel panel-info">
				<div class="table-responsive">
					<table class="table table-bordered" >
						<tr  class="success">
								<th>Producto/Servicio</th>
								<th class="text-center">Bodega</th>
								<th class="text-center">Cantidad</th>
								<th class="text-center">Medida</th>
								<th class="text-center">Precio U.</th>
								<th class="text-center">Existencia</th>
								<th>Agregar</th>
						</tr>
								<td class='col-xs-6'>
								<input type="hidden" name="id_producto_agregar" id="id_producto_agregar" >
								<input type="hidden" name="tipo_producto_agregar" id="tipo_producto_agregar" >
								<input type="hidden" name="precio_tmp" id="precio_tmp" >
								<input type="hidden" name="stock_tmp" id="stock_tmp" >
								<div class="input-group">
								<input style="z-index:inherit;" type="text" class="form-control input-sm" title="Buscar producto o servicio." name="producto_agregar" id="producto_agregar" placeholder="Ingrese un producto" onkeyup='buscar_productos();' autocomplete="off">
								<span class="input-group-btn btn-md">
								<button class="btn btn-info btn-md" type="button" title="Nuevo producto o servicio" data-toggle="modal" data-target="#nuevoProducto"><span class="glyphicon glyphicon-pencil"></span></button>
								</span>
								</div>
								</td>
								<td class='col-xs-1' id="hidden_bodega">
								  <select class="form-control" style="text-align:right; width: auto;" title="Seleccione bodega." name="bodega_agregar" id="bodega_agregar" >
									<option value="0" >Seleccione</option>
										<?php
										$conexion = conenta_login();
										$sql = "SELECT * FROM bodega WHERE ruc_empresa ='".$ruc_empresa."'";
										$res = mysqli_query($conexion,$sql);
										while($o = mysqli_fetch_array($res)){
										?>
										<option value="<?php echo $o['id_bodega']?>" selected><?php echo strtoupper($o['nombre_bodega'])?></option>
										<?php
										}
										?>
								</select>
								</td>
								<td class='col-xs-1'>
								<div class="pull-right">
								  <input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese cantidad" name="cantidad_agregar" id="cantidad_agregar" value="1" placeholder="Cantidad" >
								</div>
								</td>
								<td class='col-xs-1'>
									 <select class="form-control" style="text-align:right; width: auto;" title="Seleccione medida." name="medida_agregar" id="medida_agregar" >
									<option value="0" >Seleccione</option>
									 </select>
								</td>
								<td class='col-xs-2'>
								  <input type="text" class="form-control input-sm" style="text-align:right;" title="Ingrese precio unitario" name="precio_agregar" id="precio_agregar" placeholder="Precio unitario">
								</td>
								<td class="col-xs-2">
								  <input type="text" style="text-align:right;" class="form-control input-sm" id="existencia_producto" name="existencia_producto" placeholder="0" readonly>
								</td>
								
								<td class="col-sm-1">
								<div class="pull-right">
								<button type="button" class="btn btn-info btn-md" title="Agregar productos a la factura" onclick="agregar_un_item()"><span class="glyphicon glyphicon-plus"></span></button>
								</div>
								</td>	
					</table>
				</div>	
				</div>				
			</form>	
			<div id="resultados" ></div><!-- Carga los datos ajax del detalle de la factura -->		
		</div>
		</div>
	</div>
	
<?php
}else{
header('Location: ../includes/logout.php');
}
?>

</body>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="../js/notify.js"></script>
	<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<style>
.fixedHeight {
        padding: 1px;
		max-height: 200px;
		overflow: auto;
    }
</style>
</html>
<script>
jQuery(function($){
     $("#fecha_factura_e").mask("99-99-9999");
	 $("#guia_factura_e").mask("999-999-999999999");
});


$( function() {
	$("#fecha_factura_e").datepicker({
        dateFormat: "dd-mm-yy",
        firstDay: 1,
        dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
        dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
        monthNames: 
            ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
            "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        monthNamesShort: 
            ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
            "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]
});
	$( "#fecha_factura_e" ).datepicker( "option", "minDate", "-1m:+24d" );
    $( "#fecha_factura_e" ).datepicker( "option", "maxDate", "+0m +0d" );

});


//para guardar el nuevo cliente desde la factura
$(function() {
$( "#guardar_cliente_directo" ).submit(function( event ) {
		  $('#guardar_datos').attr("disabled", true);
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: '../ajax/nuevo_cliente_directo.php',
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_nuevo_cliente_directo").html("Mensaje: Guardando...");
					  },
					success: function(datos){
					$("#resultados_nuevo_cliente_directo").html(datos);
					$('#guardar_datos').attr("disabled", false);
				  }  
			});
		  event.preventDefault();
});
});
//para buscar los clientes
function buscar_clientes(){
	$("#nombre_cliente_e").autocomplete({
			source:'../ajax/clientes_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cliente_e').val(ui.item.id);
				$('#nombre_cliente_e').val(ui.item.nombre);
				document.getElementById('forma_pago_e').focus();
			}
		});

		$("#nombre_cliente_e" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cliente_e" ).val("");
			$("#nombre_cliente_e" ).val("");		
		}
		if (event.keyCode==$.ui.keyCode.DELETE){
			$("#nombre_cliente_e" ).val("");
			$("#id_cliente_e" ).val("");
		}
		});
}
 
//para buscar productos
function buscar_productos(){
						$("#producto_agregar").autocomplete({
							source: '../ajax/productos_autocompletar.php',
							minLength: 2,
							select: function(event, ui) {
								event.preventDefault();
								$('#id_producto_agregar').val(ui.item.id);
								$('#producto_agregar').val(ui.item.nombre);
								$('#precio_agregar').val(ui.item.precio);
								$('#precio_tmp').val(ui.item.precio);
								$('#tipo_producto_agregar').val(ui.item.tipo);
										
								document.getElementById('cantidad_agregar').focus();
								var tipo_producto = $("#tipo_producto_agregar").val();
								var configuracion_inventario=document.getElementById('inventario').value;
								
								if (tipo_producto=="02"){
								document.getElementById("bodega_agregar").style.visibility = "hidden";
								document.getElementById("existencia_producto").style.visibility = "hidden";
								document.getElementById("medida_agregar").style.visibility = "hidden";
								document.getElementById("precio_agregar").disabled = false;
								}
								if (tipo_producto=="01" && configuracion_inventario =='NO'){
								document.getElementById("bodega_agregar").style.visibility = "hidden";
								document.getElementById("existencia_producto").style.visibility = "hidden";
								document.getElementById("medida_agregar").style.visibility = "hidden";
								document.getElementById("precio_agregar").disabled = false;
								}
								
								if (tipo_producto=="01" && configuracion_inventario ==''){
								document.getElementById("bodega_agregar").style.visibility = "hidden";
								document.getElementById("existencia_producto").style.visibility = "hidden";
								document.getElementById("medida_agregar").style.visibility = "hidden";
								document.getElementById("precio_agregar").disabled = false;
								}
								if (tipo_producto=="01" && configuracion_inventario =='SI'){
								document.getElementById("bodega_agregar").style.visibility = "";
								document.getElementById("existencia_producto").style.visibility = "";
								document.getElementById("medida_agregar").style.visibility = "";
								document.getElementById("precio_agregar").disabled = true;
								
							
								$("#existencia_producto" ).val("0");
								var bodega = $("#bodega_agregar").val();
								var producto = $("#id_producto_agregar").val();
							
							//cuando trae se busca el producto me trae que tipo de medida tiene
									$.post( '../ajax/select_tipo_medida.php', {id_producto: producto}).done( function( res_tipos_medidas ){
										$("#medida_agregar").html(res_tipos_medidas);
									});	

							//para que se cargue el stock del producto al momento de buscar el producto
									$.post( '../ajax/saldo_producto_inventario.php', {id_bodega: bodega, id_producto: producto}).done( function( respuesta ){
									var saldo_producto = respuesta;
									$("#existencia_producto").val(saldo_producto);
									$('#stock_tmp').val(saldo_producto);
								});
							
								}	
							}
						});
						
						$( "#producto_agregar" ).autocomplete("widget").addClass("fixedHeight");
						
				$("#producto_agregar" ).on( "keydown", function( event ) {
					if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
					{
						$("#id_producto_agregar" ).val("");
						$("#producto_agregar" ).val("");
						$("#precio_agregar" ).val("");
						$("#tipo_producto_agregar" ).val("");
						$("#existencia_producto" ).val("");
						$("#medida_agregar" ).val("");
						$("#stock_tmp" ).val("");						
					}

					if (event.keyCode==$.ui.keyCode.DELETE){
						$("#id_producto_agregar" ).val("");
						$("#producto_agregar" ).val("");
						$("#precio_agregar" ).val("");
						$("#tipo_producto_agregar" ).val("");
						$("#existencia_producto" ).val("");
						$("#medida_agregar" ).val("");
						$("#stock_tmp" ).val("");
					}
			});
}

//para cuando se selecciona una bodega que me cargue el saldo de ese producto
$( function(){
	$('#bodega_agregar').change(function(){
		var bodega = $("#bodega_agregar").val();
		var producto = $("#id_producto_agregar").val();
		var id_medida = $("#medida_agregar").val();
		
			$.post( '../ajax/saldo_producto_inventario.php', {id_bodega: bodega, id_producto: producto}).done( function( respuesta ){
			var saldo_producto = respuesta;
			$("#existencia_producto").val(saldo_producto);
			$('#stock_tmp').val(saldo_producto);			
		});
	});
	
	//para traer el valor de conversion de medidas en el producto
	$('#medida_agregar').change(function(){
		var serie = $("#serie_factura_e").val();
		var id_medida = $("#medida_agregar").val();
		var id_producto = $("#id_producto_agregar").val();
		var precio_venta = $("#precio_tmp").val();//este precio es para que no se me cambie del precio que calculo cada vez que cambio el selec de medida
		var stock_tmp = $("#stock_tmp").val();		
		$.post( '../ajax/saldo_producto_inventario.php', {id_medida_seleccionada: id_medida, id_producto: id_producto, precio_venta: precio_venta, serie: serie, stock_tmp: stock_tmp }).done( function( respuesta_medida ){
		
		$("#existencia_producto").val(respuesta_medida);
		/*
		$.each(respuesta_medida, function(i, item) {
					$("#precio_agregar").val(item.precio);
					$("#existencia_producto").val(item.stock);
				});
				*/
		});
	});
});

//para cargar automaticamente el numero de factura que sigue al momento de cargar la nueva factura
$(document).ready(function(){
		var id_serie = $("#serie_factura_e").val();
			$.post( '../ajax/buscar_ultima_factura.php', {serie_fe: id_serie}).done( function( respuesta )
		{
			var factura_final = respuesta;
			$("#secuencial_factura_e").val(factura_final);		
		});
		//para traer el tipo de configuracion de inventarios, si o no
		$.post( '../ajax/consulta_configuracion_inventario.php', {nueva_factura:'opcion',serie_consultada:id_serie}).done( function(respuesta_consulta)
		{		
			var resultado_inventario = $.trim(respuesta_consulta);
			$('#inventario').val(resultado_inventario);
		});
		document.getElementById('nombre_cliente_e').focus();	
});
//para mostrar la factura que continua segun la serie seleccionada
$( function() {
	$('#serie_factura_e').change(function(){
		var id_serie = $("#serie_factura_e").val();
			$.post( '../ajax/buscar_ultima_factura.php', {serie_fe: id_serie}).done( function( respuesta )
		{
			var factura_final = respuesta;
			$("#secuencial_factura_e").val(factura_final);		
		});
		//para traer el tipo de configuracion de inventarios, si o no
		$.post( '../ajax/consulta_configuracion_inventario.php', {nueva_factura:'opcion',serie_consultada: id_serie}).done( function(respuesta_consulta)
		{
			var resultado_inventario = $.trim(respuesta_consulta);
			$("#inventario").val(resultado_inventario);
		});
	});
});

//eliminar iten de la factura
function eliminar_fila (id){
			var id_cliente = $("#id_cliente_e").val();
			var serie_factura = $("#serie_factura_e").val();
			var secuencial_factura = $("#secuencial_factura_e").val();
			$.ajax({
        type: "POST",
        url: "../ajax/agregar_facturacion.php",
        data: "id="+id+"&id_cliente="+id_cliente+"&serie_factura_e="+serie_factura+"&secuencial_factura="+secuencial_factura,
		 beforeSend: function(objeto){
			$("#resultados").html("Mensaje: Cargando...");
		  },
        success: function(datos){
		$("#resultados").html(datos);
		}
			});
	};

//para guardar la factura electronica
$(function() {
$( "#guardar_factura_e" ).submit(function( event ) {
	$('#guardar_datos_factura_e').attr("disabled", true);
		//para pasar el total de la factura de un text a otro text
		var total_factura = $("#suma_factura").val();
		var propina = $("#propina").val();
		var tasa_turistica = $("#tasa_turistica").val();
		$("#total_factura_e").val(total_factura);
		$("#propina_final").val(propina);
		$("#tasa_turistica_final").val(tasa_turistica);
		//de aqui para abajo para guardar la factura
		 var parametros = $(this).serialize();
			 $.ajax({
					type: "POST",
					url: '../ajax/guardar_factura_electronica.php',
					data: parametros,
					 beforeSend: function(objeto){
						$("#resultados_guardar_factura_electronica").html("Mensaje: Guardando...");
					  },
					success: function(datos){
					$("#resultados_guardar_factura_electronica").html(datos);
					$('#guardar_datos_factura_e').attr("disabled", false);
				  }  
			});
		  event.preventDefault();
});
});

//para pasar el id de descuento al modal de aplicar descuento
function pasa_descuento(id_tmp, subtotal, serie_factura, secuencial_factura, id_cliente){ 
			$("#id_tmp_descuento").val(id_tmp);
			$("#valor_subtotal").val(subtotal);
			$("#serie_factura_descuento").val(serie_factura);
			$("#secuencial_factura_descuento").val(secuencial_factura);
			$("#id_cliente_descuento").val(id_cliente);
};

//REGISTRA EL DESCUENTO
$(function() {
$( "#guardar_descuento" ).submit(function( event ) {//viene del boton aplicar descuento
	 $('#aplicar_descuento').attr("disabled", true);
	 var parametros = $(this).serialize();
	 
			var valor_descuento = $("#valor_descuento").val();
			var valor_subtotal = $("#valor_subtotal").val();
			//Inicia validacion
			if (isNaN(valor_descuento)){
			alert('El dato ingresado, no es un número');
			document.getElementById('valor_descuento').focus();
			$('#aplicar_descuento').attr("disabled", false);
			return false;
			}
			if ((Number(valor_descuento)) > (Number(valor_subtotal))){
			alert('El descuento ingresado, es mayor que el subtotal');
			document.getElementById('valor_descuento').focus();
			$('#aplicar_descuento').attr("disabled", false);
			return false;
			}	
			if ((Number(valor_descuento)) < 0) {
			alert('El descuento ingresado, debe ser mayor o igual a cero');
			document.getElementById('valor_descuento').focus();
			$('#aplicar_descuento').attr("disabled", false);
			return false;
			}				
			//Fin validacion
			$.ajax({
         type: "POST",
         url: "../ajax/agregar_facturacion.php",
         data: parametros,
		 beforeSend: function(objeto){
			$("#resultados").html("Cargando...");
		  },
			success: function(datos){
			$("#resultados").html(datos);
			$('#aplicar_descuento').attr("disabled", false);
				load(1);
			}
			});
		 event.preventDefault();
		 $('#aplicarDescuento').modal('hide');
		});
});

//agrega un item al cuerpo de la factura
function agregar_un_item(){
			var id_cliente_e = $("#id_cliente_e").val();
			var serie_factura_e = $("#serie_factura_e").val();
			var secuencial_factura_e = $("#secuencial_factura_e").val();
			var id_producto_agregar = $("#id_producto_agregar").val();
			var tipo_producto_agregar = $("#tipo_producto_agregar").val();	
			var cantidad=document.getElementById('cantidad_agregar').value;
			var medida=document.getElementById('medida_agregar').value;
			var precio_venta=document.getElementById('precio_agregar').value;
			var bodega_agregar=document.getElementById('bodega_agregar').value;
			var existencia_producto=document.getElementById('existencia_producto').value;
			var configuracion_inventario=document.getElementById('inventario').value;
			
			//Inicia validacion
			if (serie_factura_e=="0"){
			alert('Seleccione una sucursal');
			document.getElementById('serie_factura_e').focus();
			return false;
			}	
			if (id_cliente_e==""){
			alert('Seleccione un cliente');
			document.getElementById('id_cliente_e').focus();
			return false;
			}			
			if (id_producto_agregar==""){
			alert('Seleccione un producto o servicio');
			document.getElementById('id_producto_agregar').focus();
			return false;
			}
			if (isNaN(cantidad)){
			alert('El dato ingresado en cantidad, no es un número');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			if (isNaN(precio_venta)){
			alert('El dato ingresado en precio, no es un número');
			document.getElementById('precio_agregar').focus();
			return false;
			}
			if (configuracion_inventario =='SI' && tipo_producto_agregar=='01' && bodega_agregar=='0'){
			alert('Seleccione una bodega');
			document.getElementById('bodega_agregar').focus();
			return false;
			}

			if (parseFloat(cantidad) > parseFloat(existencia_producto) && configuracion_inventario =='SI' && tipo_producto_agregar=='01'){
			alert('El saldo en inventarios es menor a la cantidad a facturar ');
			document.getElementById('cantidad_agregar').focus();
			return false;
			}
			
			//Fin validacion
			$.ajax({
         type: "POST",
         url: "../ajax/agregar_facturacion.php",
         data: "id_producto="+id_producto_agregar+"&precio_venta="+precio_venta+"&cantidad="+cantidad+"&serie_factura_e="+serie_factura_e+"&bodega_agregar="+bodega_agregar+"&id_cliente="+id_cliente_e+"&secuencial_factura="+secuencial_factura_e+"&medida="+medida,
		 beforeSend: function(objeto){
			$("#resultados").html("Cargando...");
		  },
			success: function(datos){
			$("#resultados").html(datos);
			$("#producto_agregar" ).val("");
			$("#id_producto_agregar" ).val("");
			$("#precio_agregar" ).val("");
			$("#tipo_producto_agregar" ).val("");
			$("#existencia_producto" ).val("0");
			$("#cantidad_agregar" ).val("1");
			document.getElementById('producto_agregar').focus();
			}
			});
		
};


//para agregar informacion adicional a la factura
function agregar_info_adicional(){
			var $elem = $('#content');
			var id_cliente = $("#id_cliente_e").val();
			var serie_factura_e = $("#serie_factura_e").val();
			var secuencial_factura_e = $("#secuencial_factura_e").val();
			var adicional_concepto= $("#adicional_concepto").val();
			var adicional_descripcion= $("#adicional_descripcion").val();

			//Inicia validacion
			if (adicional_concepto ==''){
			alert('Ingrese un concepto');
			document.getElementById('adicional_concepto').focus();
			return false;
			}
			if (adicional_descripcion ==''){
			alert('Ingrese detalle');
			document.getElementById('adicional_descripcion').focus();
			return false;
			}
			
			//Fin validacion
			 $.ajax({
				 type: "POST",
					url: "../ajax/agregar_facturacion.php",
					data: "agregar_adicional=agregar_adicional&serie_factura_e="+serie_factura_e+"&secuencial_factura="+secuencial_factura_e+"&adicional_concepto="+adicional_concepto+"&adicional_descripcion="+adicional_descripcion+"&id_cliente="+id_cliente,
					 beforeSend: function(objeto){
						$("#resultados").html("Cargando...");
					  },
					success: function(datos){
						$("#resultados").html(datos);
						document.getElementById("adicional_concepto").value = "";
						document.getElementById("adicional_descripcion").value = "";
				  }
			});
			$('body').animate({scrollTop: $elem.height()}, 500);
};

//pasa eliminar cada detalle adicional de la factura a guardarse
function eliminar_detalle_info_adicional(id_info_adicional){
			var $elem = $('#content');
			var id_cliente = $("#id_cliente_adicional").val();
			var serie_factura = $("#serie_adicional").val();
			var secuencial_factura = $("#secuencial_adicional").val();

			 $.ajax({
					type: "POST",
					url: "../ajax/agregar_facturacion.php",
					data:"id_adicional="+id_info_adicional+"&serie_factura_e="+serie_factura+"&secuencial_factura="+secuencial_factura+"&id_cliente="+id_cliente,	
					beforeSend: function(objeto){
						$("#resultados").html("Cargando detalle...");
					  },
						success: function(datos){
						$("#resultados").html(datos);
					}
			});
			$('body').animate({scrollTop: $elem.height()}, 500);
};

//para mostrar la guia que continua segun la serie seleccionada
function secuencial_guia_remision(){
		var id_serie = $("#serie_factura_e").val();
		if (id_serie ==0){
			alert('Seleccione una serie para agregar automaticamente la guía de remisión');
			document.getElementById('serie_factura_e').focus();
			return false;
			};
		
			$.post( '../ajax/buscar_ultima_gr.php', {serie_gr: id_serie}).done( function( respuesta )
		{
			var numero = String("000000000"+respuesta).slice(-9);
			var guia_final = id_serie+'-'+numero;
			$("#guia_factura_e").val(guia_final);
			$("#guia_factura_e").mask("999-999-999999999");			
		});

};

//para aplicar propina y tasa
function aplica_propina_tasa(){
			var propina = $("#propina").val();
			var tasa = $("#tasa_turistica").val();
			var id_cliente = $("#id_cliente_e").val();
			var serie_factura_e = $("#serie_factura_e").val();
			var secuencial_factura_e = $("#secuencial_factura_e").val();
			var adicional_concepto= $("#adicional_concepto").val();
			var adicional_descripcion= $("#adicional_descripcion").val();
			var $elem = $('#content');
			//Inicia validacion
			if (propina <0){
			alert('Ingrese valor positivo en propina');
			document.getElementById('propina').focus();
			return false;
			}
			
			if (tasa_turistica <0){
			alert('Ingrese valor positivo en tasa turistica');
			document.getElementById('tasa_turistica').focus();
			return false;
			}
			
			//Fin validacion
			 $.ajax({
				 type: "POST",
					url: "../ajax/agregar_facturacion.php",
					data: "agregar_propina_tasa=agregar_propina_tasa&serie_factura_e="+serie_factura_e+"&secuencial_factura="+secuencial_factura_e+"&adicional_concepto="+adicional_concepto+"&adicional_descripcion="+adicional_descripcion+"&id_cliente="+id_cliente+"&propina="+propina+"&tasa="+tasa,
					 beforeSend: function(objeto){
						$("#resultados").html("Cargando...");
					  },
					success: function(datos){
						$("#resultados").html(datos);
				  }
			});
			$('body').animate({scrollTop: $elem.height()}, 500);
};

//para calcular automaticamente la propina
function calcular_propina(){
			var propina_calculada = $("#propina_calculada").val();
			$("#propina").val(propina_calculada);
			var propina = $("#propina").val();
			var tasa = $("#tasa_turistica").val();
			var id_cliente = $("#id_cliente_e").val();
			var serie_factura_e = $("#serie_factura_e").val();
			var secuencial_factura_e = $("#secuencial_factura_e").val();
			var adicional_concepto= $("#adicional_concepto").val();
			var adicional_descripcion= $("#adicional_descripcion").val();
			var $elem = $('#content');

			 $.ajax({
				 type: "POST",
					url: "../ajax/agregar_facturacion.php",
					data: "agregar_propina_tasa=agregar_propina_tasa&serie_factura_e="+serie_factura_e+"&secuencial_factura="+secuencial_factura_e+"&adicional_concepto="+adicional_concepto+"&adicional_descripcion="+adicional_descripcion+"&id_cliente="+id_cliente+"&propina="+propina+"&tasa="+tasa,
					 beforeSend: function(objeto){
						$("#resultados").html("Cargando...");
					  },
					success: function(datos){
						$("#resultados").html(datos);
				  }
			});
			$('body').animate({scrollTop: $elem.height()}, 500);
};

//para guardar el producto directamente desde la factura
$( "#guardar_producto" ).submit(function( event ) {
  $('#guardar_datos').attr("disabled", true);
  
 var parametros = $(this).serialize();
	 $.ajax({
			type: "POST",
			url: "../ajax/nuevo_producto.php",
			data: parametros,
			 beforeSend: function(objeto){
				$("#resultados_ajax_productos").html("Mensaje: Cargando...");
			  },
			success: function(datos){
			$("#resultados_ajax_productos").html(datos);
			$('#guardar_datos').attr("disabled", false);
		  }
	});
  event.preventDefault();
})

//para que cuando se cierre el modal de nuevo producto
$("#cerrar_nuevo_producto").click(function(){
	$("#resultados_ajax_productos").empty();
	document.getElementById("guardar_producto").reset();
    });
</script>



