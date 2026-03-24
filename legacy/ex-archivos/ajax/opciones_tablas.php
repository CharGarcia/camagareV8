<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];
	
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	
	if($action == 'eliminar_modulos_faltantes'){
	$id_eliminar=$_GET['id_eliminar'];
	$delete=mysqli_query($con,"DELETE FROM modulos_asignados WHERE id_mod_asignado = '".$id_eliminar."'");
	echo "<script>
	$.notify('Módulo eliminado exitosamente','success')
	</script>";
	}
	
	
	if($action == 'buscar_modulos_faltantes'){
	?>
	<div class="panel panel-info">
		<table class="table table-hover"> 
			<tr class="info">
					<th style ="padding: 2px;">Módulo</th>
					<th style ="padding: 2px;">Usuario</th>
					<th style ="padding: 2px;">Empresa</th>
					<th style ="padding: 2px;" class='text-right'>Eliminar</th>
			</tr>
	<?php
	
	$buscar_registros=mysqli_query($con, "SELECT emp.nombre as empresa, usu.nombre as usuario, sub_menu.nombre_submodulo as nombre_submodulo, mod_asi.id_mod_asignado as id_mod_asignado FROM submodulos_menu as sub_menu RIGHT JOIN modulos_asignados as mod_asi ON sub_menu.id_submodulo=mod_asi.id_submodulo INNER JOIN usuarios as usu ON mod_asi.id_usuario=usu.id INNER JOIN empresas as emp ON mod_asi.id_empresa=emp.id order by sub_menu.nombre_submodulo asc");
	while ($row_registros=mysqli_fetch_array($buscar_registros)){
	$id_mod_asignado=$row_registros['id_mod_asignado'];
	$nombre_submodulo=$row_registros['nombre_submodulo'];
	$nombre_usuario=$row_registros['usuario'];
	$nombre_empresa=$row_registros['empresa'];
				
				?>
			<tr>	
					<td style ="padding: 2px;"><?php echo $nombre_submodulo; ?></td>						
					<td style ="padding: 2px;"><?php echo $nombre_usuario; ?></td>
					<td style ="padding: 2px;"><?php echo $nombre_empresa; ?></td>
					<td style ="padding: 2px;" class='text-right'><a href="#" class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_modulo_faltante('<?php echo $id_mod_asignado; ?>')" ><i class="glyphicon glyphicon-remove"></i></a></td>
			</tr>
				<?php
				}
			?>
		</table>
	</div>
	<?php
	}

	if($action == 'arreglar_compras'){
			$ruc_origen=$_GET['desde'];
			$ruc_destino=$_GET['hasta'];
						
			//$buscar_registros_origen=mysqli_query($con, "SELECT * FROM encabezado_compra as enc INNER JOIN proveedores as pro ON pro.id_proveedor=enc.id_proveedor WHERE enc.ruc_empresa='".$ruc_origen."' ");
			$buscar_registros_origen=mysqli_query($con, "SELECT * FROM encabezado_compra WHERE ruc_empresa='".$ruc_origen."' ");
			while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
			$id_proveedor=$row_registros_origen['id_proveedor'];
			
			
			$consultar_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor='".$id_proveedor."' ");
			$row_registros_proveedores=mysqli_fetch_array($consultar_proveedores);
			$ruc_proveedor=$row_registros_proveedores['ruc_proveedor'];
			$razon_social=$row_registros_proveedores['razon_social'];
			$nombre_comercial=$row_registros_proveedores['nombre_comercial'];
			$tipo_id_proveedor=$row_registros_proveedores['tipo_id_proveedor'];
			$mail_proveedor=$row_registros_proveedores['mail_proveedor'];
			$dir_proveedor=$row_registros_proveedores['dir_proveedor'];
			$telf_proveedor=$row_registros_proveedores['telf_proveedor'];
			$tipo_empresa=$row_registros_proveedores['tipo_empresa'];
			$fecha_agregado=$row_registros_proveedores['fecha_agregado'];
			$plazo=$row_registros_proveedores['plazo'];
			$unidad_tiempo=$row_registros_proveedores['unidad_tiempo'];
			
			
			$consultar_proveedores_existentes = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor='".$ruc_proveedor."' and ruc_empresa='".$ruc_origen."'");
			$num_proveedores=mysqli_num_rows($consultar_proveedores_existentes);
			
			if ($num_proveedores==0){
				//guarda un nuevo proveedores
			$crear = mysqli_query($con, "INSERT INTO proveedores VALUES(null, '".$razon_social."', '".$nombre_comercial."', '".$ruc_origen."', '".$tipo_id_proveedor."', '".$ruc_proveedor."', '".$mail_proveedor."', '".$dir_proveedor."', '".$telf_proveedor."', '".$tipo_empresa."', '".$fecha_agregado."', '".$plazo."', '".$unidad_tiempo."', '1')");	
			}
			
			//$consultar_nuevo_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor='".$ruc_proveedor."' and ruc_empresa='".$ruc_origen."'");
			//$row_nuevo_proveedores=mysqli_fetch_array($consultar_nuevo_proveedores);
			
			//$nombre_proveedor=$row_nuevo_proveedores['razon_social'];
			//echo $id_proveedor."//".$nombre_proveedor."<br>";
			
			
			$consultar_proveedores_final = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor='".$ruc_proveedor."' and ruc_empresa ='".$ruc_origen."'");
			$row_registros_proveedores_final=mysqli_fetch_array($consultar_proveedores_final);
			$id_proveedor_final=$row_registros_proveedores_final['id_proveedor'];
			
			$actualizar = mysqli_query($con, "UPDATE encabezado_compra SET id_proveedor='".$id_proveedor_final."' WHERE ruc_empresa='".$ruc_origen."' and id_proveedor='".$id_proveedor."'");
									
			}
		
			echo "<script>$.notify('Registros actualizados.','success');
			
			</script>";
		}
		
	if($action == 'arreglar_retenciones'){
			$ruc_origen=$_GET['desde'];
			$ruc_destino=$_GET['hasta'];
						
			$buscar_registros_origen=mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE ruc_empresa='".$ruc_origen."' ");
			while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
			$id_proveedor=$row_registros_origen['id_proveedor'];
			
			$consultar_proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE id_proveedor='".$id_proveedor."' ");
			$row_registros_proveedores=mysqli_fetch_array($consultar_proveedores);
			$ruc_proveedor=$row_registros_proveedores['ruc_proveedor'];
			
			$consultar_proveedores_final = mysqli_query($con, "SELECT * FROM proveedores WHERE ruc_proveedor='".$ruc_proveedor."' and ruc_empresa ='".$ruc_origen."'");
			$row_registros_proveedores_final=mysqli_fetch_array($consultar_proveedores_final);
			$id_proveedor_final=$row_registros_proveedores_final['id_proveedor'];
			
			$actualizar = mysqli_query($con, "UPDATE encabezado_retencion SET id_proveedor='".$id_proveedor_final."' WHERE ruc_empresa='".$ruc_origen."' and id_proveedor='".$id_proveedor."'");
									
			}
		
			echo "<script>$.notify('Registros actualizados.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
		}
	
		if($action == 'arreglar_lotes'){
			$ruc_origen=$_GET['desde'];
			$ruc_destino=$_GET['hasta'];
			$serie=substr($_GET['hasta'],0,7);
			$secuencial=substr($_GET['hasta'],8,9);
			
			$buscar_registros_origen=mysqli_query($con, "SELECT * FROM cuerpo_factura WHERE ruc_empresa='".$ruc_origen."' and serie_factura='".$serie."' and secuencial_factura='".$secuencial."'");
			while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
			$lote=$row_registros_origen['lote'];
			$serie_factura=$row_registros_origen['serie_factura'];
			$secuencial_factura=$row_registros_origen['secuencial_factura'];
			$id_producto=$row_registros_origen['id_producto'];
			$id_documento_venta=$serie_factura."-".str_pad($secuencial_factura,9,"000000000",STR_PAD_LEFT);
			$nombre_producto=$row_registros_origen['nombre_producto'];
			$crear = mysqli_query($con, "UPDATE inventarios SET lote='".$lote."' WHERE ruc_empresa='".$ruc_origen."' and cantidad_salida>0 and id_producto='".$id_producto."' and id_documento_venta='".$ruc_destino."'");
									
			}
		
			echo "<script>$.notify('Registros corregidos.','success');
			setTimeout(function (){location.reload()}, 1000);
			</script>";
		}



	if($action == 'tabla_nivel'){
	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];
	
	$buscar_registros_origen=mysqli_query($con, "SELECT * FROM nivel_alumnos WHERE ruc_empresa='".$ruc_origen."'");
	while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
	$nombre_nivel_origen=$row_registros_origen['nombre_nivel'];
	$buscar_registros_destino=mysqli_query($con, "SELECT * FROM nivel_alumnos WHERE nombre_nivel='".$nombre_nivel_origen."' and ruc_empresa='".$ruc_destino."'");
	$registros_destino=mysqli_num_rows($buscar_registros_destino);
		if ($registros_destino==0){	
		$crear = mysqli_query($con, "INSERT INTO nivel_alumnos VALUES(null, '".$ruc_destino."', '".$nombre_nivel_origen."', '".$id_usuario."')");
		}
	}

	echo "<script>$.notify('Registros creados.','success');
	setTimeout(function (){location.reload()}, 1000);
	</script>";
	}
	
	if($action == 'tabla_horarios'){
	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];
	$buscar_registros_origen=mysqli_query($con, "SELECT * FROM horarios_alumnos WHERE ruc_empresa='".$ruc_origen."'");
	while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
	$nombre_origen=$row_registros_origen['nombre_horario'];
	$buscar_registros_destino=mysqli_query($con, "SELECT * FROM horarios_alumnos WHERE nombre_horario='".$nombre_origen."' and ruc_empresa='".$ruc_destino."'");
	$registros_destino=mysqli_num_rows($buscar_registros_destino);
		if ($registros_destino==0){	
		$crear = mysqli_query($con, "INSERT INTO horarios_alumnos VALUES(null, '".$nombre_origen."', '".$ruc_destino."', '".$id_usuario."')");
		}
	}

	echo "<script>$.notify('Registros creados.','success');
	setTimeout(function (){location.reload()}, 1000);
	</script>";
	}
	
	if($action == 'tabla_alumnos'){
	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];
	
	$buscar_registros_origen=mysqli_query($con, "SELECT * FROM alumnos WHERE ruc_empresa='".$ruc_origen."'");
		while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
		$cedula_alumno=$row_registros_origen['cedula_alumno'];
		$nombres_alumno=$row_registros_origen['nombres_alumno'];
		$apellidos_alumno=$row_registros_origen['apellidos_alumno'];
		$fecha_nacimiento_alumno=$row_registros_origen['fecha_nacimiento_alumno'];
		$fecha_ingreso_alumno=$row_registros_origen['fecha_ingreso_alumno'];
		$horario_alumno=$row_registros_origen['horario_alumno'];
		$sexo_alumno=$row_registros_origen['sexo_alumno'];
		$sucursal_alumno=$row_registros_origen['sucursal_alumno'];
		$paralelo_alumno=$row_registros_origen['paralelo_alumno'];
		$fecha_agregado=$row_registros_origen['fecha_agregado'];
		$tipo_id=$row_registros_origen['tipo_id'];
		$estado_alumno=$row_registros_origen['estado_alumno'];
		$id_cliente=$row_registros_origen['id_cliente'];

		$buscar_registros_destino=mysqli_query($con, "SELECT * FROM alumnos WHERE cedula_alumno='".$cedula_alumno."' and ruc_empresa='".$ruc_destino."'");
		$registros_destino=mysqli_num_rows($buscar_registros_destino);
			if ($registros_destino==0){	
			$crear = mysqli_query($con, "INSERT INTO alumnos VALUES(null, '".$cedula_alumno."', '".$nombres_alumno."', '".$apellidos_alumno."', '".$fecha_nacimiento_alumno."', '".$fecha_ingreso_alumno."', '".$horario_alumno."', '".$sexo_alumno."', '".$sucursal_alumno."', '".$paralelo_alumno."', '".$id_usuario."', '".$fecha_agregado."', '".$ruc_destino."', '".$tipo_id."', '".$estado_alumno."', '".$id_cliente."', '001-001')");
			}
		}

		echo "<script>$.notify('Registros creados.','success');
		setTimeout(function (){location.reload()}, 1000);
		</script>";
	}
	
	if($action == 'actualizar_clientes'){
	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];
	
	$buscar_registros_origen=mysqli_query($con, "SELECT * FROM alumnos WHERE ruc_empresa='".$ruc_destino."'");
		while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
		$id_cliente=$row_registros_origen['id_cliente'];
		$id_alumno=$row_registros_origen['id_alumno'];
		
		$buscar_clientes=mysqli_query($con, "SELECT * FROM clientes WHERE id='".$id_cliente."' ");
		$row_clientes=mysqli_fetch_array($buscar_clientes);
		$ruc_cliente=$row_clientes['ruc'];
		
		$datos_clientes=mysqli_query($con, "SELECT * FROM clientes WHERE ruc='".$ruc_cliente."' and ruc_empresa='".$ruc_destino."' ");
		$row_id_clientes=mysqli_fetch_array($datos_clientes);
		$id_nuevo_cliente=$row_id_clientes['id'];

		$actualizar = mysqli_query($con, "UPDATE alumnos SET id_cliente='".$id_nuevo_cliente."' WHERE id_alumno='".$id_alumno."'");

		}

		echo "<script>$.notify('Registros actualizados.','success');
		setTimeout(function (){location.reload()}, 1000);
		</script>";
	}

	
	if($action == 'tabla_clientes'){
	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];

	$ruc_origen=$_GET['desde'];
	$ruc_destino=$_GET['hasta'];
	
	$buscar_registros_origen=mysqli_query($con, "SELECT * FROM clientes WHERE ruc_empresa='".$ruc_origen."'");
	while ($row_registros_origen=mysqli_fetch_array($buscar_registros_origen)){
	$nombre_nivel_origen=$row_registros_origen['ruc'];
	$nombre=$row_registros_origen['nombre'];
	$tipo_id=$row_registros_origen['tipo_id'];
	$telefono=$row_registros_origen['telefono'];
	$email=$row_registros_origen['email'];
	$direccion=$row_registros_origen['direccion'];
	$parte_rel=$row_registros_origen['parte_rel'];
	$fecha_agregado=$row_registros_origen['fecha_agregado'];
	$plazo=$row_registros_origen['plazo'];
	$unidad_tiempo=$row_registros_origen['unidad_tiempo'];
	
	$buscar_registros_destino=mysqli_query($con, "SELECT * FROM clientes WHERE ruc='".$nombre_nivel_origen."' and ruc_empresa='".$ruc_destino."'");
	$registros_destino=mysqli_num_rows($buscar_registros_destino);
		if ($registros_destino==0){	
		$crear = mysqli_query($con, "INSERT INTO clientes VALUES(null, '".$ruc_destino."', '".$nombre."', '".$tipo_id."', '".$nombre_nivel_origen."', '".$telefono."', '".$email."', '".$direccion."', '".$parte_rel."', '".$fecha_agregado."', '".$plazo."', '".$unidad_tiempo."', '".$id_usuario."')");
		}
	}
	
	echo "<script>$.notify('Registros creados.','success');
	//setTimeout(function (){location.reload()}, 1000);
	</script>";
	}
?>