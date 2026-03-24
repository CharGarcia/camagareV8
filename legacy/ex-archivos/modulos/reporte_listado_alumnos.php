<html lang="es">
<meta name="viewport" content="width=device-width, initial-scale=1">
  <head>
  <title>Listados</title>
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
				<h4><i class='glyphicon glyphicon-align-justify'></i> Listado de alumnos</h4>
				</div>
		
			<ul class="nav nav-tabs">
			  <li class="active"><a data-toggle="tab" href="#listados_generales">Listados generales</a></li>
			  <li><a data-toggle="tab" href="#servicios_por_cobrar">Servicios por cobrar</a></li>
			  
			</ul>

			<div class="tab-content">
			  <div id="listados_generales" class="tab-pane fade in active">
						<div class="panel-body">		
						<div class="well well-sm">


								<div class="form-group row">
										<label class="col-md-1 control-label">Campus</label>
											<div class="col-md-3">
												 <select class="form-control" name="sucursal_alumno_listado" id="sucursal_alumno_listado" required>
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


										<label class="col-md-1 control-label">Nivel</label>
											<div class="col-md-3">
												<select class="form-control" name="paralelo_alumno_listado" id="paralelo_alumno_listado" required>
													<?php
													$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '".$ruc_empresa."' ;";
													$res = mysqli_query($con,$sql);
													?> 
													<option value="TODOS">TODOS</option>
													<?php
													while($o = mysqli_fetch_assoc($res)){
													?>
													<option value="<?php echo $o['id_nivel'] ?>"><?php echo $o['nombre_nivel'] ?> </option>
													<?php
													}
													?>
												</select>
											</div>	
									<td class="col-md-2"><a  class="btn btn-primary" href="#" onclick="mostrar_listado_alumnos('')">Mostrar listado</a></td>
									<td class="col-md-1">
										<button type="submit" class="btn btn-success" ><img alt="Brand" src="../image/excel.ico" width="25" height="20"></button>
									</td>
								</div>
						</div>	
						<div id="alumnos_listado" ></div><!-- Carga los datos ajax del detalle de la factura -->	
						<div class='outer_div_listado'></div><!-- Carga los datos ajax -->
					</div> <!-- HASATA AQUI EL body -->
			  </div>
			  <div id="servicios_por_cobrar" class="tab-pane fade">
						<div class="panel-body">
						<form class="form-horizontal" role="form" method ="POST" action="../excel/reporte_pc_alumnos.php">						
						<div class="well well-sm">
								<div class="form-group row">
										<label class="col-md-1 control-label">Campus</label>
											<div class="col-md-2">
												 <select class="form-control" name="sucursal_alumno_pc" id="sucursal_alumno_pc" required>
													<?php
													$sql = "SELECT * FROM campus_alumnos where ruc_empresa = '".$ruc_empresa."' ;";
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


										<label class="col-md-1 control-label">Nivel</label>
											<div class="col-md-2">
												<select class="form-control" name="paralelo_alumno_pc" id="paralelo_alumno_pc" required>
													<?php
													$sql = "SELECT * FROM nivel_alumnos where ruc_empresa = '".$ruc_empresa."' ;";
													$res = mysqli_query($con,$sql);
													?> 
													<option value="TODOS">TODOS</option>
													<?php
													while($o = mysqli_fetch_assoc($res)){
													?>
													<option value="<?php echo $o['id_nivel'] ?>"><?php echo $o['nombre_nivel'] ?> </option>
													<?php
													}
													?>
												</select>
											</div>
										<label class="col-md-1 control-label">Período</label>
											<div class="col-md-2">
												<select class="form-control" name="periodo_alumno_pc" id="periodo_alumno_pc" required>
													<?php
													$sql_periodo = "SELECT * FROM periodo_a_facturar ";
													$res_periodo = mysqli_query($con,$sql_periodo);
													while($row_periodo = mysqli_fetch_assoc($res_periodo)){
													?>
													<option value="<?php echo $row_periodo['codigo_periodo'] ?>"><?php echo $row_periodo['detalle_periodo'] ?> </option>
													<?php
													}
													?>
												</select>
											</div>	
										
									<td class="col-md-2"><a  class="btn btn-primary" href="#" onclick="mostrar_por_cobrar('')">Buscar</a></td>
									<td class="col-md-1">
										<button type="submit" class="btn btn-success" ><img alt="Brand" src="../image/excel.ico" width="25" height="20"></button>
									</td>
								</div>
						</div>
						</form>
						<div id="alumnos_listado_pc" ></div><!-- Carga los datos ajax del detalle de la factura -->	
						<div class='outer_div_listado_pc'></div><!-- Carga los datos ajax -->
					</div> <!-- HASATA AQUI EL body -->
			  </div>
			  
			  
			
			</div> <!-- HASATA AQUI EL DIV tab contents -->
		</div> 
	</div>
	
<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
}
?>

</body>
	
</html>
<script>
		
function mostrar_listado_alumnos(){
			var sucursal_alumno_listado= $("#sucursal_alumno_listado").val();
			var paralelo_alumno_listado= $("#paralelo_alumno_listado").val();

			$("#alumnos_listado").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/mostrar_alumnos_listado.php',
         data: 'action=listado&sucursal_alumno_listado='+sucursal_alumno_listado+'&paralelo_alumno_listado='+paralelo_alumno_listado,
		 beforeSend: function(objeto){
			$("#alumnos_listado").html("Mensaje: Cargando...");
		  },
			success: function(datos){
			$("#alumnos_listado").html(datos);
			}
			});
}

function mostrar_por_cobrar(){
			var sucursal_alumno_pc= $("#sucursal_alumno_pc").val();
			var paralelo_alumno_pc= $("#paralelo_alumno_pc").val();
			var periodo_alumno_pc= $("#periodo_alumno_pc").val();

			$("#alumnos_listado_pc").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../ajax/mostrar_alumnos_listado.php',
         data: 'action=listado_pc&sucursal_alumno_pc='+sucursal_alumno_pc+'&paralelo_alumno_pc='+paralelo_alumno_pc+'&periodo_alumno_pc='+periodo_alumno_pc,
		 beforeSend: function(objeto){
			$("#alumnos_listado_pc").html("Mensaje: Cargando...");
		  },
			success: function(datos){
			$("#alumnos_listado_pc").html(datos);
			}
			});
}



//para el pdf
function mostrar_listado_alumnos_pdf(){
			var sucursal_alumno_listado= $("#sucursal_alumno_listado").val();
			var paralelo_alumno_listado= $("#paralelo_alumno_listado").val();

			$("#alumnos_listado").fadeIn('slow');
			$.ajax({
         type: "POST",
         url:'../pdf/listado_alumnos.php',
         data: 'action=listado_pdf&sucursal_alumno_listado='+sucursal_alumno_listado+'&paralelo_alumno_listado='+paralelo_alumno_listado,
		 beforeSend: function(objeto){
			$("#alumnos_listado").html("Mensaje: Cargando...");
		  },
			success: function(datos){
			$("#alumnos_listado").html(datos);
			}
			});
}

</script>



