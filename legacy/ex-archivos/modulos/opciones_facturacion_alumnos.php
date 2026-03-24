<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">
  <head>
  <title>Opciones</title>
</head>	

<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	include("../paginas/menu_de_empresas.php");
$con = conenta_login();
?>
	
<body>

	<div class="container">
		<div class="panel panel-info">
		<div class="panel-heading">
			<h4><i class='glyphicon glyphicon-edit'></i> Opciones de facturación</h4>
		</div>
			
		<ul class="nav nav-tabs">
		  <li class="active"><a data-toggle="tab" href="#generar_facturas">Generar Facturas</a></li>
		  <li><a data-toggle="tab" href="#descuentos">Descuentos</a></li>
		</ul>

		<div class="tab-content">
		  <div id="generar_facturas" class="tab-pane fade in active">
			<div class="panel-body">		
			<div class="well well-sm">
			<div class="form-group row">
			<div class="form-group">
			<label class="col-md-3 control-label"></label>
					<label for="" class="col-sm-1 control-label">Sucursal</label>
					<div class="col-sm-2">
					   <select class="form-control" name="sucursal_facturar" id="sucursal_facturar">
						<option value="0" >Seleccione serie</option>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM sucursales where ruc_empresa ='$ruc_empresa' order by id_sucursal asc;";
							$res = mysqli_query($conexion,$sql);
							while($o = mysqli_fetch_assoc($res)){
							?>
							<option value="<?php echo $o['serie'] ?> " ><?php echo $o['serie'] ?> </option>
							<?php
							}
							?>
						</select>
					</div>
					</div>
			</div>
			<div class="form-group row">
			<div class="form-group">
			<label class="col-md-3 control-label"></label>
					<label for="" class="col-sm-1 control-label"> Mes</label>
					<div class="col-sm-2">
					   <select class="form-control" name="mes_facturar" id="mes_facturar">
							<option value="<?php echo date("m") ?>" selected> <?php echo date("m") ?></option>
							<option value="01" >01</option>
							<option value="02" >02</option>
							<option value="03" >03</option>
							<option value="04" >04</option>
							<option value="05" >05</option>
							<option value="06" >06</option>
							<option value="07" >07</option>
							<option value="08" >08</option>
							<option value="09" >09</option>
							<option value="10" >10</option>
							<option value="11" >11</option>
							<option value="12" >12</option>
							
						</select>
					</div>
					</div>
			</div>
				<div class="form-group row">
					<div class="form-group">
					<label class="col-md-3 control-label"></label>
					<label for="" class="col-sm-1 control-label"> Año</label>
					<div class="col-sm-2">
					   <select class="form-control" name="anio_facturar" id="anio_facturar">
					       <option value="<?php echo date("Y")+1 ?>" selected> <?php echo date("Y") +1 ?></option>
							<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
							<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-10; $i+= -1) {
							?> 
							<option value="<?php echo $i ?>"> <?php echo $i ?></option>
							<?php }  ?> 
						</select>
					</div>
				 </div>
				</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Campus</label>
								<div class="col-md-4">
									 <select class="form-control" name="sucursal_alumno_facturar" id="sucursal_alumno_facturar" required>
										<?php
										$sql = "SELECT * FROM campus_alumnos where ruc_empresa = '$ruc_empresa' ;";
										$res = mysqli_query($con,$sql);
										?> 
										<option value="">Seleccione campus</option>
										<?php
										while($o = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $o['id_campus'] ?>"><?php echo $o['nombre_campus'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>	
					</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Nivel</label>
								<div class="col-md-4">
									<select class="form-control" name="paralelo_alumno_facturar" id="paralelo_alumno_facturar" required>
										<?php
										$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '$ruc_empresa' ;";
										$res = mysqli_query($con,$sql);
										?> 
										<option value="">Seleccione nivel</option>
										<?php
										while($o = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $o['id_nivel'] ?>"><?php echo $o['nombre_nivel'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>	
					</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Las del periodo</label>
								<div class="col-md-3">
									<select class="form-control" id="periodo_facturar" name="periodo_facturar" required>
										<option value="" Selected>Seleccione período</option>
										<?php
										$sql = "SELECT * FROM periodo_a_facturar";
										$respuesta = mysqli_query($con,$sql);
										while($datos_periodo = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_periodo['codigo_periodo']?>"><?php echo $datos_periodo['detalle_periodo'] ?></option> 
										<?php 
										}
										?>
									</select>
								</div>	
					</div>

					<div class="form-group row">
						<label class="col-md-5 control-label"></label>
						<td class="col-md-2"><a  class="btn btn-primary" href="#" onclick="mostrar_alumnos_facturar('')">Mostrar alumnos</a></td>
					</div>
			</div>	
			<div id="alumnos_facturar" ></div><!-- Carga los datos ajax del detalle de la factura -->	
			<div class='outer_div_facturar'></div><!-- Carga los datos ajax -->
		</div> <!-- HASATA AQUI EL body -->
		  </div>
		  
		  
		  <!-- de aqui para abajo el modulo de descuentos -->
		  <div id="descuentos" class="tab-pane fade">		
			<div class="panel-body">		
			<div class="well well-sm">
			<div class="form-group row">
			<div class="form-group">
			<label class="col-md-3 control-label"></label>
					<label for="" class="col-sm-1 control-label"> Mes</label>
					<div class="col-sm-2">
					   <select class="form-control" name="mes_descuento" id="mes_descuento">
							<option value="<?php echo date("m") ?>" selected> <?php echo date("m") ?></option>
							<option value="01" >01</option>
							<option value="02" >02</option>
							<option value="03" >03</option>
							<option value="04" >04</option>
							<option value="05" >05</option>
							<option value="06" >06</option>
							<option value="07" >07</option>
							<option value="08" >08</option>
							<option value="09" >09</option>
							<option value="10" >10</option>
							<option value="11" >11</option>
							<option value="12" >12</option>
							
						</select>
					</div>
					</div>
			</div>
				<div class="form-group row">
					<div class="form-group">
					<label class="col-md-3 control-label"></label>
					<label for="" class="col-sm-1 control-label"> Año</label>
					<div class="col-sm-2">
					   <select class="form-control" name="anio_descuento" id="anio_descuento">
					       <option value="<?php echo date("Y")+1 ?>" selected> <?php echo date("Y") +1 ?></option>
							<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
							<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-10; $i+= -1) {
							?> 
							<option value="<?php echo $i ?>"> <?php echo $i ?></option>
							<?php }  ?> 
						</select>
					</div>
				 </div>
				</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Campus</label>
								<div class="col-md-4">
									 <select class="form-control" name="sucursal_alumno_descuento" id="sucursal_alumno_descuento" required>
										<?php
										$sql = "SELECT * FROM campus_alumnos where ruc_empresa = '$ruc_empresa' ;";
										$res = mysqli_query($con,$sql);
										?> 
										<option value="">Seleccione campus</option>
										<?php
										while($o = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $o['id_campus'] ?>"><?php echo $o['nombre_campus'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>	
					</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Nivel</label>
								<div class="col-md-4">
									<select class="form-control" name="paralelo_alumno_descuento" id="paralelo_alumno_descuento" required>
										<?php
										$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '$ruc_empresa' ;";
										$res = mysqli_query($con,$sql);
										?> 
										<option value="">Seleccione nivel</option>
										<?php
										while($o = mysqli_fetch_assoc($res)){
										?>
										<option value="<?php echo $o['id_nivel'] ?>"><?php echo $o['nombre_nivel'] ?> </option>
										<?php
										}
										?>
									</select>
								</div>	
					</div>
					<div class="form-group row">
						<label class="col-md-3 control-label"></label>
							<label class="col-md-1 control-label">Producto</label>
								<div class="col-md-4">
									<select class="form-control" id="id_producto_descuento" name="id_producto_descuento" required>
										<option value="" selected >Seleccione producto</option>
										<?php
										$sql = "SELECT * FROM productos_servicios WHERE ruc_empresa = '$ruc_empresa';";
										$respuesta = mysqli_query($con,$sql);
										while($datos_producto = mysqli_fetch_assoc($respuesta)){
										?>	
										<option value="<?php echo $datos_producto['id']?>"><?php echo $datos_producto['nombre_producto'] ?></option> 
										<?php 
										}
										?>
									</select>
								</div>
					</div>
					<div class="form-group row">
						<label class="col-md-5 control-label"></label>
						<td class="col-md-2"><a  class="btn btn-primary" href="#" onclick="mostrar_alumnos_descuento('')">Mostrar alumnos</a></td>
					</div>
			</div>	
			<div id="alumnos_descuento" ></div><!-- Carga los datos ajax del detalle de la factura -->	
			<div class='outer_div_descuentos'></div><!-- Carga los datos ajax -->
		</div> <!-- HASATA AQUI EL body -->
		</div>
		
		
		</div> <!-- HASATA AQUI EL DIV tab contents -->
		</div> <!-- HASATA AQUI EL DIV DEL BODY -->
	</div>
	
<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
?>

</body>
	
</html>
<script>
		
function mostrar_alumnos_descuento(){
			var mes_descuento= $("#mes_descuento").val();
			var anio_descuento= $("#anio_descuento").val();
			var sucursal_alumno_descuento= $("#sucursal_alumno_descuento").val();
			var paralelo_alumno_descuento= $("#paralelo_alumno_descuento").val();
			var id_producto_descuento= $("#id_producto_descuento").val();
			
			$("#alumnos_descuento").fadeIn('slow');
			
			$.ajax({
         type: "POST",
         url:'../ajax/mostrar_alumnos_descuento.php',
         data: 'action=descuento&mes_descuento='+mes_descuento+'&anio_descuento='+anio_descuento+"&sucursal_alumno_descuento="+sucursal_alumno_descuento+"&paralelo_alumno_descuento="+paralelo_alumno_descuento+"&id_producto_descuento="+id_producto_descuento,
		 beforeSend: function(objeto){
			$("#alumnos_descuento").html("Mensaje: Cargando...");
		  },
			success: function(datos){
			$("#alumnos_descuento").html(datos);
			}
			});
}

function mostrar_alumnos_facturar(){
			var sucursal_facturar= $("#sucursal_facturar").val();
			var mes_facturar= $("#mes_facturar").val();
			var anio_facturar= $("#anio_facturar").val();
			var sucursal_alumno_facturar= $("#sucursal_alumno_facturar").val();
			var paralelo_alumno_facturar= $("#paralelo_alumno_facturar").val();
			var periodo_facturar= $("#periodo_facturar").val();
			
			$("#alumnos_facturar").fadeIn('slow');
			
			$.ajax({
         type: "POST",
         url:'../ajax/mostrar_alumnos_facturar.php',
         data: 'action=facturar&sucursal_facturar='+sucursal_facturar+'&mes_facturar='+mes_facturar+"&anio_facturar="+anio_facturar+"&sucursal_alumno_facturar="+sucursal_alumno_facturar+"&paralelo_alumno_facturar="+paralelo_alumno_facturar+"&periodo_facturar="+periodo_facturar,
		 beforeSend: function(objeto){
			$("#alumnos_facturar").html("Mensaje: Cargando...");
		  },
			success: function(datos){
			$("#alumnos_facturar").html(datos);
			}
			});
}

</script>



