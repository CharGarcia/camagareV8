<?php
//PARA MOSTRAR LA INFO ADICIONAL DE GUIA DE REMISION
function muestra_adicionales_gr($serie_guia, $secuencial_guia, $id_usuario, $con, $id_cliente){
	?>
	<div class="col-md-8 col-md-offset-2">
						<div class="panel panel-info">
						<div class="panel-heading">Detalle de información adicional</div>
						<td>
	<?php
if (isset($_POST["serie_guia"]) && isset($_POST["secuencial_guia"]) && isset($_POST["id_cliente"]) ){
$serie_guia=mysqli_real_escape_string($con,(strip_tags($_POST["serie_guia"],ENT_QUOTES)));
$secuencial_guia=mysqli_real_escape_string($con,(strip_tags($_POST["secuencial_guia"],ENT_QUOTES)));
$id_cliente=mysqli_real_escape_string($con,(strip_tags($_POST["id_cliente"],ENT_QUOTES)));
}

	
	$busca_empresa_detalle = mysqli_query($con, "SELECT * FROM clientes WHERE id = '".$id_cliente."' ");
	$datos_detalle = mysqli_fetch_array($busca_empresa_detalle);
	$email=$datos_detalle['email'];
	$direccion=$datos_detalle['direccion'];
	$telefono=$datos_detalle['telefono'];
	$ruc_empresa=$datos_detalle['ruc_empresa'];
	
	$delete_agente_ret = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_guia."' and secuencial_factura = '".$secuencial_guia."' and concepto='Agente de Retención'");
	$delete_regimen_micro = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_guia."' and secuencial_factura = '".$secuencial_guia."' and concepto='Régimen'");
	$delete_mail_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_guia."' and secuencial_factura = '".$secuencial_guia."' and concepto='Email'");
	$delete_direccion_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_guia."' and secuencial_factura = '".$secuencial_guia."' and concepto='Dirección'");
	$delete_telefono_adicional_tmp = mysqli_query($con, "DELETE FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura = '".$serie_guia."' and secuencial_factura = '".$secuencial_guia."' and concepto='Teléfono'");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '".$id_usuario."', '".$serie_guia."', '".$secuencial_guia."', 'Email','".$email."')");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '".$id_usuario."', '".$serie_guia."', '".$secuencial_guia."', 'Dirección','".$direccion."')");
	$detalle_adicional_uno = mysqli_query($con, "INSERT INTO adicional_tmp VALUES (null, '".$id_usuario."', '".$serie_guia."', '".$secuencial_guia."', 'Teléfono','".$telefono."')");
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
										<td class="text-center"><a class='btn btn-info btn-sm' title='Agregar' onclick="agregar_info_adicional_gr()"><i class="glyphicon glyphicon-plus"></i></a></td>
									</tr>
						<?php

							$muestra_adicional_tmp = mysqli_query($con,"SELECT * FROM adicional_tmp WHERE id_usuario = '".$id_usuario."' and serie_factura= '".$serie_guia."' and secuencial_factura= '".$secuencial_guia."' ");
							while ($detalle_info_adicional = mysqli_fetch_array($muestra_adicional_tmp)){
								$id_info_adicional=$detalle_info_adicional['id_ad_tmp'];
								$concepto=$detalle_info_adicional['concepto'];
								$detalle=$detalle_info_adicional['detalle'];
								$serie_guia_adicional=$detalle_info_adicional['serie_factura'];
								$secuencial_guia_adicional=$detalle_info_adicional['secuencial_factura'];
						?>				
									<tr>
									<input type="hidden" id="id_cliente_adicional" value="<?php echo $id_cliente;?>">
									<input type="hidden" id="serie_adicional" value="<?php echo $serie_guia_adicional;?>">
									<input type="hidden" id="secuencial_adicional" value="<?php echo $secuencial_guia_adicional;?>">									
									<td><?php echo $concepto; ?></td>
									<td><?php echo $detalle; ?></td>
									<?php
									if ($concepto=="Email" || $concepto=="Dirección" || $concepto=="Teléfono" || $concepto=="Agente de Retención" || $concepto=="Régimen"){
									?>
									<td class='text-center'></td>
									<?php
									}else{
									?>
									<td class='text-center'><a href="#" class='btn btn-danger btn-sm' title='Eliminar' onclick="eliminar_detalle_info_adicional_gr('<?php echo $id_info_adicional;?>')"><i class="glyphicon glyphicon-remove"></i></a></td>
									<?php
									}
									?>								
									</tr>
							<?php
							
							}
						?>
						</table>
						</div>
					</td>							
					</div>
	</div>
<?php
}
?>