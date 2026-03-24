<?php
//Para mostrar el detalle de los adicionales de la liquidacion, usa el tmp de factura -->
function muestra_adicionales_liquidacion($serie_liquidacion, $secuencial_liquidacion, $id_usuario, $con, $id_proveedor){
	
		$busca_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."'";
		$query = mysqli_query($con, $busca_adicional_tmp);
		//para ver si ya estan agregados adicionales a la liquidacion que se va hacer o sino se guarda el mail y la direccion como adicional
		$adicionales_encontradas = mysqli_num_rows($query);
			if ($adicionales_encontradas ==0){
			//trae informacion de la sucursal
				$busca_empresa_detalle = "SELECT * FROM proveedores WHERE id_proveedor = '".$id_proveedor."' ";
				$result_detalle = $con->query($busca_empresa_detalle);
				$datos_detalle = mysqli_fetch_array($result_detalle);
				$email=$datos_detalle['mail_proveedor'];
				$telefono=$datos_detalle['telf_proveedor'];
				$ruc_empresa=$datos_detalle['ruc_empresa'];

	$delete_agente_ret = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."' and concepto='Agente de Retención'");
	$delete_regimen_micro = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."' and concepto='Régimen'");
	$delete_mail_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."' and concepto='Email'");
	$delete_telefono_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."' and concepto='Teléfono'");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '".$id_usuario."', '".$serie_liquidacion."', '".$secuencial_liquidacion."', 'Email','".$email."')");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '".$id_usuario."', '".$serie_liquidacion."', '".$secuencial_liquidacion."', 'Teléfono','".$telefono."')");
	}
?>
						<div class="table-responsive">
						<table class="table table-bordered">

									<tr class="info">
										<td class='col-xs-3'>
										 <input type="text" class="form-control input-sm" id="adicional_concepto" name="adicional_concepto" placeholder="Concepto">
										</td>
										<td class="col-xs-7">
										<input type="text" class="form-control input-sm" id="adicional_descripcion" name="adicional_descripcion" placeholder="Descripción del detalle">
										</td>
										<td class="text-center"><a class='btn btn-info btn-sm' title='Agregar' onclick="agregar_info_adicional()"><i class="glyphicon glyphicon-plus"></i></a></td>
									</tr>
						<?php
							$muestra_adicional_tmp = "SELECT * FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_liquidacion."' and secuencial_factura = '".$secuencial_liquidacion."'";
							$query = mysqli_query($con, $muestra_adicional_tmp);
							while ($detalle_info_adicional = mysqli_fetch_array($query)){
								$id_info_adicional=$detalle_info_adicional['id_ad_tmp'];
								$concepto=$detalle_info_adicional['concepto'];
								$detalle=$detalle_info_adicional['detalle'];
						?>				
									<tr>
									<input type="hidden" id="id_proveedor_adicional" value="<?php echo $id_proveedor;?>">
									<input type="hidden" id="serie_adicional" value="<?php echo $serie_liquidacion;?>">
									<input type="hidden" id="secuencial_adicional" value="<?php echo $secuencial_liquidacion;?>">									
									<td><?php echo $concepto; ?></td>
									<td><?php echo $detalle; ?></td>
									<?php
									if ($concepto=="Email" || $concepto=="Teléfono" || $concepto=="Agente de Retención" || $concepto=="Régimen"){
									?>
									<td class='text-center'></td>
									<?php
									}else{
									?>
									<td class='text-center'><a href="#" class='btn btn-danger btn-sm' title='Eliminar' onclick="eliminar_detalle_info_adicional('<?php echo $id_info_adicional;?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
									<?php
									}
									?>		
									</tr>
								
							<?php
							
							}
						?>
						</table>
						</div>
<?php
}

?>