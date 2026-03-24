<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
//PARA agregar MODULOS A USUARIOS
if (isset($_POST['id_sub_modulo']) && isset($_POST['id_sub_usuario']) && isset($_POST['id_sub_empresa']) ){
		$id_sub_modulo=$_POST['id_sub_modulo'];
		$id_usuario_asignado = $_POST['id_sub_usuario'];
		$id_empresa = $_POST['id_sub_empresa'];
		$id_modulo = $_POST['id_modulo'];

		$busca_modulo_asignado = mysqli_query($con,"SELECT * FROM modulos_asignados WHERE id_usuario='".$id_usuario_asignado."' and id_empresa='".$id_empresa."' and id_modulo='".$id_modulo."' and id_submodulo='".$id_sub_modulo."'");
		$cuenta_asignados = mysqli_num_rows($busca_modulo_asignado);
			
		//si hay registros de este modulo elimina ese registro
		if ($cuenta_asignados==0){
		$asignar_modulo=mysqli_query($con,"INSERT INTO modulos_asignados VALUES (null, '".$id_usuario_asignado."', '".$id_empresa."', '".$id_modulo."', '".$id_sub_modulo."')");
			if ($asignar_modulo){
				echo "<script>
					$.notify('Módulo asignado.','success');
					</script>";	
			}else{
				echo "<script>
					$.notify('No se pudo asignar el módulo, intente de nuevo','warning');
					</script>";
			}
		}else{
		$elimina_modulo=mysqli_query($con,"DELETE FROM modulos_asignados WHERE id_usuario='".$id_usuario_asignado."' and id_empresa='".$id_empresa."' and id_modulo='".$id_modulo."' and id_submodulo='".$id_sub_modulo."'");
			if ($elimina_modulo){
				echo "<script>
					$.notify('Módulo eliminado.','error');
					</script>";	
			}else{
				echo "<script>
					$.notify('No se pudo eliminar el módulo asignado, intente de nuevo','warning');
					</script>";
			}
		}

}
?>