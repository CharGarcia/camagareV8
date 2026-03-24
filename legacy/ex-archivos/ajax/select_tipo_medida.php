<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];

//para buscar medida y cargar combos de nuevo producto
if (isset($_POST['tipo_medida']) && isset($_POST['id_unidad_medida'])){
		$tipo_medida=mysqli_real_escape_string($con,(strip_tags($_POST["tipo_medida"],ENT_QUOTES)));
		$id_unidad_medida=mysqli_real_escape_string($con,(strip_tags($_POST["id_unidad_medida"],ENT_QUOTES)));
		
		$busca_unidad_medida = "SELECT * FROM unidad_medida where id_tipo_medida='".$tipo_medida."' ";
		$resultado_unidad_medida = $con->query($busca_unidad_medida);
						
		?>							
		<option value="0">Seleccione</option>
		<?php
		while ($row_unidad = mysqli_fetch_array($resultado_unidad_medida)){
			if ($row_unidad['id_medida'] == $id_unidad_medida){
			?>
			<option value="<?php echo $id_unidad_medida;?>"selected><?php echo $row_unidad['nombre_medida'];?></option>
			<?php
			}else{
			?>
			<option value="<?php echo $row_unidad['id_medida'];?>"><?php echo $row_unidad['nombre_medida'];?></option>
			<?php	
			}
		}
}

//para buscar medidas 
if (isset($_POST['id_producto'])){
		$id_producto=mysqli_real_escape_string($con,(strip_tags($_POST["id_producto"],ENT_QUOTES)));

		$busca_id_medida = mysqli_query($con,"SELECT * FROM productos_servicios WHERE id='".$id_producto."' ");
		$row_id_medida = mysqli_fetch_array($busca_id_medida);
		$id_unidad_medida=$row_id_medida['id_unidad_medida'];
		
		//para saber el tipo de unidad de medida
		$busca_tipo_medida = "SELECT * FROM unidad_medida WHERE id_medida='".$id_unidad_medida."' ";
		$resultado_tipo_medida = $con->query($busca_tipo_medida);
		$row_tipo_medida = mysqli_fetch_array($resultado_tipo_medida);
		$id_tipo_medida=$row_tipo_medida['id_tipo_medida'];
		
		$busca_unidad_medida = "SELECT * FROM unidad_medida WHERE id_tipo_medida= '".$id_tipo_medida."'";
		$resultado_unidad_medida = $con->query($busca_unidad_medida);
		
		while ($row_unidad_medida = mysqli_fetch_array($resultado_unidad_medida)){
			if ($row_unidad_medida['id_medida'] == $id_unidad_medida){
			?>
			<option value="<?php echo $id_unidad_medida;?>"selected><?php echo $row_unidad_medida['nombre_medida'];?></option>
			<?php
			}else{
			?>
			<option value="<?php echo $row_unidad_medida['id_medida'];?>"><?php echo $row_unidad_medida['nombre_medida'];?></option>
			<?php	
			}			
		}
		
}

?>