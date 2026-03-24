<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="es" lang="es">

<head>
<title>Obligaciones</title>
<?php include("../head.php");?>
<script language="JavaScript"> 
function confirmar ( mensaje ) { 
return confirm( "Desea eliminar la obligación?" ); 
} 
</script>
</head>

	
<body>
<?php
ob_start();
include("../conexiones/conectalogin.php");
session_start();
if($_SESSION['nivel'] >= 2){
$titulo_info ="Obligaciones por cumplir de las empresas. ";
include("../navbar_confi.php");

?>
<div class="container">
<div class="row">
	<div class="col-md-8">
		<div class="panel panel-info" >
<?php
$id = $_SESSION['id_usuario'];
?>
				<div class="panel-heading">
				<div class="btn-group pull-right">
			<button type='submit' class="btn btn-info" data-toggle="modal" data-target="#nuevaObligacion"><span class="glyphicon glyphicon-plus" ></span> Nueva Obligación</button>
			</div>
					<h4><i class="glyphicon glyphicon-pushpin"></i> Listado de obligaciones </h4>
				</div>
			<div class="panel-body">
			<div id="resultados_ajax2"></div>
					<div class="table-responsive">
						<table class="table table-condensed" >
								<tr  class="info">
								<td>#</td>
							   <td>Detalle</td>
							   <td>Aplica a</td>
							   <td>Fecha a cumplir</td>
							   <td><span class="pull-right">Opciones</td>
								</tr>
							<?php
							$conexion = conenta_login();
							$sql = "SELECT * FROM obligaciones_empresas ;";
							$res = mysqli_query($conexion,$sql);
							$n=0;
							while($p = mysqli_fetch_assoc($res)){
							$n++;
							?>
							<tr>
							<td><?php echo $n ?></td>
							<td><?php echo ucfirst(strtolower($p['nombre'])) ?></td>
							<td><?php echo ucfirst(strtolower($p['tipo_empresa'])) ?></td>
							<td><?php echo ucfirst(strtolower($p['mes_cumplir'])) ?></td>
							<td><span class="pull-right">
							<a href="" onclick="return confirmar()" class="btn btn-default" title="Eliminar obligación" ><i class="glyphicon glyphicon-trash"></i></a>
							<a href="" onclick="" class="btn btn-default" title="Editar obligación" ><i class="glyphicon glyphicon-edit"></i></a>
							
							<a href="?op=asignar&id_obligacion= <?php echo ($p['id'])?>" onclick="" class="btn btn-default" title="Asignar obligación" ><i class="glyphicon glyphicon-thumbs-up"></i></a>
							</td>
							</tr>
						
						   <?php 
							}
							?>
						
							
						</table>
					</div>
			</div>
		</div>
	</div>
	</div>
	</div>
<?php
	}else{
header('Location: ../includes/logout.php');
exit;
}
?>
	<script type="text/javascript" src="../js/opciones_obligaciones.js"></script>
</body>

</html>

<?php
$con = conenta_login();
if (isset($_GET['op']) && isset($_GET['id_obligacion']) && ($_GET['op']=="asignar")){
	$id_obligacion = $_GET['id_obligacion'];

	//PARA SABER QUE A QUE TIPO DE EMPRESAS SE LE VA A APLICAR LA OBLIGACION
	$resultado_uno   = mysqli_query($con, "select * from obligaciones_empresas where id = $id_obligacion");
	$respuesta = mysqli_fetch_array($resultado_uno);
	$tipo_empresa = $respuesta['tipo_empresa'];
	$aplica_con_ruc = $respuesta['aplica_con_ruc'];
	$mes_a_cumplir = $respuesta['mes_cumplir'];
	
	if ($tipo_empresa=="TODOS"  ){
		
		
	}
	
	
	while ($row_uno = mysqli_fetch_array($resultado_uno)) {
	$id=$row0['id'];
	$campo1=$row0['campo1'];
	$campo2=$row0['campo2'];
	$campo5=$row0['campo5'];
	$campo6=$row0['campo6'];

	$insercion="insert into Tabla_Destino (id, campo1, campo2, campo5) values ($id, $campo1, $campo2, $campo5, $campo6)";

if($ejecucion=mysqli_query($insercion)){
        
echo 'Se insertaron los campos correspondientes al ID ',$id;

        }else{

echo  mysql_error(); 

    }
   
          }
	
	
	
}

?>