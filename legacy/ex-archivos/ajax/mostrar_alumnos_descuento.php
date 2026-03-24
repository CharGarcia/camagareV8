<?php
/* Connect To Database*/
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
$ruc_empresa = $_SESSION['ruc_empresa'];

$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
if($action == 'descuento'){

		if (empty($_POST['mes_descuento'])) {
           $errors[] = "Seleccione mes.";
		}else if (empty($_POST['anio_descuento'])) {
           $errors[] = "Seleccione año";
		}else if (empty($_POST['sucursal_alumno_descuento'])) {
           $errors[] = "Seleccione campus";
		}else if (empty($_POST['paralelo_alumno_descuento'])) {
           $errors[] = "Ingrese nivel.";
		}else if (empty($_POST['id_producto_descuento'])) {
           $errors[] = "Seleccione un producto al cual quiere aplicar el descuento.";
        } else if (!empty($_POST['mes_descuento']) && !empty($_POST['anio_descuento'])  && !empty($_POST['sucursal_alumno_descuento']) 
		&& !empty($_POST['paralelo_alumno_descuento']) && !empty($_POST['id_producto_descuento']))
		{

			$mes_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["mes_descuento"],ENT_QUOTES)));
			$anio_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["anio_descuento"],ENT_QUOTES)));
			$sucursal_alumno_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["sucursal_alumno_descuento"],ENT_QUOTES)));
			$paralelo_alumno_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["paralelo_alumno_descuento"],ENT_QUOTES)));
			$id_producto_descuento=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto_descuento"],ENT_QUOTES)));
		
?>
 <form class="form-horizontal" method="POST" id="guardar_descuentos" name="guardar_descuentos">
<div class="panel panel-info">
   <div class="table-responsive">
   <table class="table">
  <tr class="info">
	<th>N</th>
	<th>NOMBRES</th>
	<th>APELLIDOS</th>
	<th>PRODUCTO</th>
	<th>VALOR</th>
	<th>DESCUENTO</th>
	<th>APLICAR</th>
</tr>
<?php

// PARA MOSTRAR LOS ESTUDIANTES A QUIENES SE LES VA A APLICAR LOS DESCUENTOS
	$sql_alumnos=mysqli_query($con, "SELECT * FROM alumnos WHERE ruc_empresa='$ruc_empresa' and sucursal_alumno = $sucursal_alumno_descuento and paralelo_alumno = $paralelo_alumno_descuento and estado_alumno='1' ");
	$numero=0;
	while ($row=mysqli_fetch_array($sql_alumnos)){
	$id_alumno=$row["id_alumno"];
	$nombres=$row['nombres_alumno'];
	$apellidos=$row['apellidos_alumno'];
	$numero = $numero + 1;
	//para mostrar los productos
	$sql_productos=mysqli_query($con, "SELECT * FROM productos_servicios WHERE id= $id_producto_descuento  ");
	$row_producto=mysqli_fetch_array($sql_productos);
	$nombre_producto=$row_producto["nombre_producto"];
	//para mostrar los valores
	$sql_valores=mysqli_query($con, "SELECT sum(precio_producto) as valores FROM detalle_por_facturar WHERE id_referencia= $id_alumno and id_producto = $id_producto_descuento ");
	$row_valores=mysqli_fetch_array($sql_valores);
	$valor_producto=$row_valores['valores'];
	//para mostrar los descuentos
	$sql_descuentos=mysqli_query($con, "SELECT sum(valor_descuento) as valdes FROM descuentos_programados WHERE id_referencia= $id_alumno and id_producto = $id_producto_descuento and mes_descuento = '$mes_descuento' and anio_descuento = '$anio_descuento'");
	$row_descuentos=mysqli_fetch_array($sql_descuentos);
	$valor_descontado=$row_descuentos['valdes'];
		?>
		
		<input type="hidden" name="mes_descuento" value="<?php echo $mes_descuento;?>">
		<input type="hidden" name="anio_descuento" value="<?php echo $anio_descuento;?>">
		<input type="hidden" name="id_producto" value="<?php echo $id_producto_descuento;?>">
		<input type="hidden" name="id_alumno" value="<?php echo $id_alumno;?>">

		<input type="hidden" value="<?php echo $id_alumno;?>" id="id_alumno<?php echo $id_alumno;?>">
		<input type="hidden" value="<?php echo $mes_descuento;?>" id="mes_descuento<?php echo $id_alumno;?>">
		<input type="hidden" value="<?php echo $anio_descuento;?>" id="anio_descuento<?php echo $id_alumno;?>">
		<input type="hidden" value="<?php echo $id_producto_descuento;?>" id="id_producto<?php echo $id_alumno;?>">

		<tr>
			<td><?php echo ($numero);?></td>
			<td><?php echo strtoupper($nombres);?></td>
			<td><?php echo strtoupper($apellidos);?></td>
			<td><?php echo strtoupper($nombre_producto);?></td>
			<td><?php echo number_format($valor_producto,2,'.','');?></td>
			<td><a href="#" class='btn btn-danger btn-xs' title='Eliminar descuento' onclick="eliminar_descuento_alumno('<?php echo $id_alumno; ?>')"><?php echo number_format($valor_descontado,2,'.','');?> <i class="glyphicon glyphicon-erase"></i></a></td>
			<?php
			if ($valor_producto>0){
			?>
			<td><input type="checkbox" class="form-control" name="aplica_descuento[]"   value="<?php echo $id_alumno;?>"></td>
			<?php
			}else{
			?>
			<td><input type="checkbox" class="form-control" name="aplica_descuento[]"   value="<?php echo $id_alumno;?>" disabled></td>
		    <?php
			}
			?>
		</tr>		

		<?php
}
?>
</table>
</div>
</div>

<!-- <div class="panel panel-info">
<div class="panel panel-body">
-->

<div class="form-group">
<label for="" class="col-sm-2 control-label"> Valor descuento</label>
<div class="col-sm-2">
<input type="text" class="form-control" name="valor_descuento" id="valor_descuento" value="15">
</div>
<div class="col-sm-2">

<input type="submit" class="btn btn-primary" name="guardar" value="Guardar descuentos" onclick="reload()">

</div>
</div>
</form>
<div id="resultados_guardar" ></div><!-- Carga los datos ajax del detalle de la factura -->	
<!--</div>
</div> -->



<?php
}else {
			$errors []= "Error desconocido.";
		}
}
		if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}
?>
<script>
	//para guardar los descuentos
$(function() {
$( "#guardar_descuentos" ).submit(function( event ) {
	 $('#guardar').attr("disabled", true);
	 var parametros = $(this).serialize();
			$.ajax({
         type: "POST",
         url: "../ajax/guardar_descuentos_alumnos.php?action=guardar_descuento",
         data: parametros,
		 beforeSend: function(objeto){
			$("#resultados_guardar").html("Mensaje: Guardando...");
		  },
			success: function(datos){
			$("#resultados_guardar").html(datos);
			$('#guardar').attr("disabled", false);
			
			 for (i=0;i<document.guardar_descuentos.elements.length;i++) 
			  if(document.guardar_descuentos.elements[i].type == "checkbox")	
				 document.guardar_descuentos.elements[i].checked=0 
			}
			});
			event.preventDefault();
		});
		
});
//para eliminar los descuentos
function eliminar_descuento_alumno(id_alumno){
			var id_alumno = $("#id_alumno"+id_alumno).val();
			var mes = $("#mes_descuento"+id_alumno).val();
			var anio = $("#anio_descuento"+id_alumno).val();
			var id_producto = $("#id_producto"+id_alumno).val();
			if (confirm("Realmente desea eliminar el descuento?")){	

			$.ajax({
         type: "POST",
         url: "../ajax/guardar_descuentos_alumnos.php?action=eliminar_descuento",
		 data:"id_alumno="+id_alumno+"&mes="+mes+"&anio="+anio+"&id_producto="+id_producto,
		 beforeSend: function(objeto){
			$("#resultados_guardar").html("Mensaje: Eliminando...");
		  },
			success: function(datos){
			$("#resultados_guardar").html(datos);
			$('#guardar').attr("disabled", false);
			}
			});
			event.preventDefault();
			}
	};


</script>