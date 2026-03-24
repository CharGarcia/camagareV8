<?php
// PARA MOSTRAR LA INFO ADICIONAL DE GUIA DE REMISION
function muestra_adicionales_gr($serie_guia, $secuencial_guia, $id_usuario, $con, $id_cliente)
{
?>
	<div class="col-md-8 col-md-offset-2">
		<div class="panel panel-info">
			<div class="panel-heading">Detalle de información adicional</div>
			<td>
				<?php
				// 1) Traer datos del cliente
				$email = $direccion = $telefono = $ruc_empresa = null;

				if ($stmt = mysqli_prepare($con, "SELECT email, direccion, telefono, ruc_empresa FROM clientes WHERE id = ? LIMIT 1")) {
					mysqli_stmt_bind_param($stmt, 'i', $id_cliente);
					mysqli_stmt_execute($stmt);
					mysqli_stmt_bind_result($stmt, $email, $direccion, $telefono, $ruc_empresa);
					mysqli_stmt_fetch($stmt);
					mysqli_stmt_close($stmt);
				}

				// 2) Borrar valores fijos previos SOLO de conceptos predefinidos (para no tocar otros)
				//    Haremos todo en transacción: si algo falla, revertimos.
				mysqli_autocommit($con, false);
				$ok = true;

				// helper para exec de delete por concepto
				$delete_sql = "DELETE FROM adicional_tmp 
                   WHERE id_usuario = ? AND serie_factura = ? AND secuencial_factura = ? AND concepto = ?";
				if ($del = mysqli_prepare($con, $delete_sql)) {
					$conceptos_fijos = array('Agente de Retención', 'Régimen', 'Email', 'Dirección', 'Teléfono');
					foreach ($conceptos_fijos as $c) {
						mysqli_stmt_bind_param($del, 'isss', $id_usuario, $serie_guia, $secuencial_guia, $c);
						if (!mysqli_stmt_execute($del)) {
							$ok = false;
							break;
						}
					}
					mysqli_stmt_close($del);
				} else {
					$ok = false;
				}

				// 3) Insertar solo si los valores NO están vacíos (trim) → email, dirección, teléfono
				//    Si cualquiera de estos inserts falla, se hará ROLLBACK y no quedará nada a medias.
				if ($ok) {
					$insert_sql = "INSERT INTO adicional_tmp (id_usuario, serie_factura, secuencial_factura, concepto, detalle)
                       VALUES (?, ?, ?, ?, ?)";
					if ($ins = mysqli_prepare($con, $insert_sql)) {
						// Lista de pares concepto => valor
						$pares = array(
							'Email'     => is_null($email) ? '' : trim($email),
							'Dirección' => is_null($direccion) ? '' : trim($direccion),
							'Teléfono'  => is_null($telefono) ? '' : trim($telefono),
						);

						foreach ($pares as $concepto => $valor) {
							if ($valor === '') {
								// No insertar si está vacío
								continue;
							}
							mysqli_stmt_bind_param($ins, 'issss', $id_usuario, $serie_guia, $secuencial_guia, $concepto, $valor);
							if (!mysqli_stmt_execute($ins)) {
								$ok = false;
								break;
							}
						}
						mysqli_stmt_close($ins);
					} else {
						$ok = false;
					}
				}

				// 4) Confirmar o deshacer
				if ($ok) {
					mysqli_commit($con);
				} else {
					mysqli_rollback($con);
				}
				mysqli_autocommit($con, true);

				// 5) Pintar la tabla (si hubo error, igual mostramos lo que haya en BD para depurar visualmente)
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
							<td class="text-center">
								<a class="btn btn-info btn-sm" title="Agregar" onclick="agregar_info_adicional_gr()">
									<i class="glyphicon glyphicon-plus"></i>
								</a>
							</td>
						</tr>
						<?php
						// Cargar los adicionales actuales
						if ($res = mysqli_query($con, sprintf(
							"SELECT id_ad_tmp, concepto, detalle, serie_factura, secuencial_factura 
                             FROM adicional_tmp 
                             WHERE id_usuario = %d AND serie_factura = '%s' AND secuencial_factura = '%s'",
							(int)$id_usuario,
							mysqli_real_escape_string($con, $serie_guia),
							mysqli_real_escape_string($con, $secuencial_guia)
						))) {
							while ($row = mysqli_fetch_assoc($res)) {
								$id_info_adicional       = (int)$row['id_ad_tmp'];
								$concepto                = $row['concepto'];
								$detalle                 = $row['detalle'];
								$serie_guia_adicional    = $row['serie_factura'];
								$secuencial_guia_adicional = $row['secuencial_factura'];

								// Sanitizar para HTML
								$concepto_html = htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8');
								$detalle_html  = htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8');
						?>
								<tr>
									<input type="hidden" id="id_cliente_adicional" value="<?php echo (int)$id_cliente; ?>">
									<input type="hidden" id="serie_adicional" value="<?php echo htmlspecialchars($serie_guia_adicional, ENT_QUOTES, 'UTF-8'); ?>">
									<input type="hidden" id="secuencial_adicional" value="<?php echo htmlspecialchars($secuencial_guia_adicional, ENT_QUOTES, 'UTF-8'); ?>">
									<td><?php echo $concepto_html; ?></td>
									<td><?php echo $detalle_html; ?></td>
									<?php
									// Estos conceptos no se eliminan desde la UI
									if (in_array($concepto, array('Email', 'Dirección', 'Teléfono', 'Agente de Retención', 'Régimen'), true)) {
										echo "<td class='text-center'></td>";
									} else {
									?>
										<td class='text-center'>
											<a href="#" class="btn btn-danger btn-sm" title="Eliminar" onclick="eliminar_detalle_info_adicional_gr('<?php echo $id_info_adicional; ?>')">
												<i class="glyphicon glyphicon-remove"></i>
											</a>
										</td>
									<?php
									}
									?>
								</tr>
						<?php
							}
							mysqli_free_result($res);
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