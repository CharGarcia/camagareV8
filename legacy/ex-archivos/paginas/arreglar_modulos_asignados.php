<?php
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
//eliminar las tablas modulos_menu y submodulo_menu
$borra_modulos_menu=mysqli_query($con,"DELETE FROM modulos_menu");
$borra_submodulos_menu=mysqli_query($con,"DELETE FROM submodulos_menu");
$borra_modulos_asignados=mysqli_query($con,"DELETE FROM modulos_asignados");

$resultado=array();
if ($borra_modulos_menu && $borra_submodulos_menu && $borra_modulos_asignados){
	$resultado[]= "Datos eliminados";
	}else{
	$resultado[]= "Error al eliminar datos";
}

//copiar a modulos_menu los registros de la tabla modulos que tengan usuario 0
$sql_modulos_menu =mysqli_query($con, "INSERT INTO modulos_menu(id_modulo, nombre_modulo, id_icono) SELECT id,nombre,'9' FROM modulos WHERE modulos.id_usuario='0'");
//copiar a submodulos_menu los registros de la tabla submodulos que tengan usuario 0
$sql_submodulos_menu = mysqli_query($con,"INSERT INTO submodulos_menu(id_submodulo,nombre_submodulo,id_icono,id_modulo,ruta) SELECT null, sub_modulos.nombre,'9', sub_modulos.id_modulo, sub_modulos.ruta FROM sub_modulos WHERE sub_modulos.id_usuario = '0' AND sub_modulos.id_empresa = '0'");
//copiar a la tabla modulos_asignados los registros de la tabla submodulos que usuario sean diferente de 0
$sql_modulos_asignados = mysqli_query($con,"INSERT INTO modulos_asignados(id_mod_asignado,id_usuario,id_empresa,id_modulo,id_submodulo) SELECT null, sub_modulos.id_usuario,sub_modulos.id_empresa,sub_modulos.id_modulo,sub_modulos.id FROM sub_modulos WHERE sub_modulos.id_usuario != '0' AND sub_modulos.id_empresa != '0'");

if ($sql_modulos_menu && $sql_submodulos_menu && $sql_modulos_asignados){
	$resultado[]= "Datos copiados a nuevas tablas";
	}else{
	$resultado[]= "Error al copiar datos a nuevas tablas";
}

//consultar de la tabla sub_modulos el nombre en base al id_submodulo de la tabla modulos asignados
		$busca_id_submodulo = "SELECT * FROM modulos_asignados ";
		$result_submodulo = $con->query($busca_id_submodulo);
		while ($row_id_submodulo = mysqli_fetch_array($result_submodulo)){
				$id_modulo_asignado = $row_id_submodulo['id_mod_asignado'];
				$id_submodulo = $row_id_submodulo['id_submodulo'];
				
				$busca_nombre_submodulo = "SELECT * FROM sub_modulos WHERE id= '".$id_submodulo."'";
				$result_nombre = $con->query($busca_nombre_submodulo);
				$row_nombre_submodulo = mysqli_fetch_array($result_nombre);
				$nombre_submodulo=$row_nombre_submodulo['nombre'];
				
				$busca_id_submodulo_cero = "SELECT * FROM submodulos_menu WHERE nombre_submodulo= '".$nombre_submodulo."'";
				$result_nombre_cero = $con->query($busca_id_submodulo_cero);
				$row_nombre_cero = mysqli_fetch_array($result_nombre_cero);
				$id_cero=$row_nombre_cero['id_submodulo'];
				
				//actualiza el id en modulos_asignados
		$update_idsubmodulo=mysqli_query($con,"UPDATE modulos_asignados SET id_submodulo='".$id_cero."' WHERE id_mod_asignado = '".$id_modulo_asignado."' ");
		}
		
if ($update_idsubmodulo){
	$resultado[]= "submodulos actualizados";
	}else{
	$resultado[]= "Error al actualizar el id submodulo";
}

//para ver el nombre del icono en los modulos y agregar a los nuevos modulos_menu
		$busca_icono_modulo = "SELECT * FROM modulos where id_usuario='0' and id_empresa='0'";
		$result_icono_modulo = $con->query($busca_icono_modulo);
		while ($row_icono_modulo = mysqli_fetch_array($result_icono_modulo)){
				$nombre_icono_modulos = $row_icono_modulo['icono'];
				$nombre_modulos = $row_icono_modulo['nombre'];
				
				$busca_id_icono = "SELECT * FROM iconos_fontawesome WHERE nombre_icono= '".$nombre_icono_modulos."'";
				$result_id_icono = $con->query($busca_id_icono);
				$row_nombre_submodulo = mysqli_fetch_array($result_id_icono);
				$id_icono_modulo=$row_nombre_submodulo['id_icono'];
				
				$busca_nombre_modulo = "SELECT * FROM modulos_menu WHERE nombre_modulo= '".$nombre_modulos."'";
				$result_nombre_modulo = $con->query($busca_nombre_modulo);
				$row_nombre_modulo = mysqli_fetch_array($result_nombre_modulo);
				$id_modulo_menu=$row_nombre_modulo['id_modulo'];
				
				//actualiza el icono de modulos
		$update_icono_modulos=mysqli_query($con,"UPDATE modulos_menu SET id_icono='".$id_icono_modulo."' WHERE id_modulo= '".$id_modulo_menu."' ");
		}
		
if ($update_icono_modulos){
	$resultado[]= "iconos de modulos, actualizados";
	}else{
	$resultado[]= "Error al actualizar el icono de modulos";
}

//para ver el nombre del icono en los sub modulos y agregar a los nuevos submodulos_menu
		$busca_icono_submodulo = "SELECT * FROM sub_modulos where id_usuario='0' and id_empresa='0'";
		$result_icono_submodulo = $con->query($busca_icono_submodulo);
		while ($row_icono_submodulo = mysqli_fetch_array($result_icono_submodulo)){
				$nombre_icono_submodulos = $row_icono_submodulo['icono'];
				$nombre_submodulos = $row_icono_submodulo['nombre'];
				
				$busca_id_icono = "SELECT * FROM iconos_fontawesome WHERE nombre_icono= '".$nombre_icono_submodulos."'";
				$result_id_icono = $con->query($busca_id_icono);
				$row_nombre_submodulo = mysqli_fetch_array($result_id_icono);
				$id_icono_submodulo=$row_nombre_submodulo['id_icono'];
				
				$busca_nombre_submodulo = "SELECT * FROM submodulos_menu WHERE nombre_submodulo= '".$nombre_submodulos."'";
				$result_nombre_submodulo = $con->query($busca_nombre_submodulo);
				$row_nombre_submodulo = mysqli_fetch_array($result_nombre_submodulo);
				$id_submodulo_menu=$row_nombre_submodulo['id_submodulo'];
				
				//actualiza el icono de modulos
		$update_icono_submodulos=mysqli_query($con,"UPDATE submodulos_menu SET id_icono='".$id_icono_submodulo."' WHERE id_submodulo= '".$id_submodulo_menu."' ");
		}
		
if ($update_icono_submodulos){
	$resultado[]= "iconos de submodulos, actualizados";
	}else{
	$resultado[]= "Error al actualizar el icono de submodulos";
}

print_r ($resultado);
?>