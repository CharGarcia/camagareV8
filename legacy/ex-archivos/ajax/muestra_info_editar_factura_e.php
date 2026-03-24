<?php
		$serie =$_GET['serie_factura'];
		$secuencial =$_GET['secuencial_factura'];
		$sql_forma_pago="SELECT fpv.id_fp as id_fp, fp.nombre_pago as nombre FROM formas_pago_ventas fpv, formas_de_pago fp WHERE fpv.ruc_empresa = '$ruc_empresa' and fpv.serie_factura = '$serie' and fpv.secuencial_factura = $secuencial and fpv.id_forma_pago = fp.codigo_pago and fp.aplica_a ='VENTAS'";
		$query = mysqli_query($con, $sql_forma_pago);

		$sql_adicionales="SELECT * FROM detalle_adicional_factura WHERE ruc_empresa = '$ruc_empresa' and serie_factura = '$serie' and secuencial_factura = $secuencial and adicional_concepto !='' and adicional_descripcion !='' ";
		$query_adicionales = mysqli_query($con, $sql_adicionales);
		
?>
	<div class="row">
		<div class="col-md-5">
			<div class="panel-body">
					<table class="table table-bordered"> 
						<tr class="info">
								<th>Forma de pago</th>
								<th>Agregar</th>
						</tr>
							<td class="col-xs-8">
							<select class="form-control" name="forma_pago_e" id="forma_pago_e">
										<option value="0" >Seleccione forma de pago</option>
											<?php
											$conexion = conenta_login();
											$sql = "SELECT * FROM formas_de_pago WHERE aplica_a ='VENTAS' order by nombre_pago asc";
											$res = mysqli_query($conexion,$sql);
											while($o = mysqli_fetch_assoc($res)){
											?>
											<option value="<?php echo $o['codigo_pago']?>"><?php echo $o['nombre_pago'] ?> </option>
											<?php
											}
											?>
										</select>
							</td>
							<td class="col-sm-2">
								<a href="#" onclick="agregar_forma_pago_fe();" title='Agregar forma de pago' class="btn btn-info btn-md"><span class="glyphicon glyphicon-plus"></span></a>
							</td>
					</table>
			</div>
				
				<div class="panel panel-info">
				<div class="panel-heading">Formas de pago</div>
				<div class="panel-body">

					<div class="panel panel-info">
								<table class="table table-bordered"> 
									<tr class="info">
										<th>Forma de pago</th>
										<th class='text-right'>Eliminar</th>
									</tr>

									<?php
									while ($row=mysqli_fetch_array($query)){
											$id_forma_pago=$row['id_fp'];
											$nombre_forma_pago=$row['nombre'];
									?>
									<input type="hidden" value="<?php echo $id_forma_pago;?>" id="forma_pago<?php echo $id_forma_pago;?>">
									<tr>
											<td><?php echo $nombre_forma_pago; ?></td>
											<td class='text-right'><a href="#" class='btn btn-danger btn-md' title='Eliminar' onclick="eliminar_forma_pago('<?php echo $id_forma_pago; ?>')" ><i class="glyphicon glyphicon-remove"></i></a></td>
									</tr>
									<?php
									}
									?>
								</table>
						</div>
				</div>
			</div>
		</div>
		
		<div class="col-md-7">
		<div class="panel-body">
			<table class="table table-bordered"> 
				<tr class="info">
						<th>Concepto</th>
						<th>Detalle</th>
						<th>Agregar</th>
				</tr>
						<td class="col-xs-4">
						<input type="text" class="form-control" id="concepto_adicional" name="concepto_adicional" >
						</td>
						<td class="col-xs-6">
						  <input type="text" class="form-control" id="detalle_adicional" name="detalle_adicional" >
						</td>
						<td class="col-xs-1">
						<a href="#" onclick="agregar_info_adicional();" title='Agregar adicionales' class="btn btn-info btn-md"><span class="glyphicon glyphicon-plus"></span></a>
						</td>
			</table>	
		</div>				
						
				<div class="panel panel-info">
				<div class="panel-heading">Detalle adicionales</div>
					<div class="panel-body">	
							<div class="panel panel-info">
									<table class="table table-bordered"> 
										<tr class="info">
											<th>Concepto</th>
											<th>Detalle</th>
											<th class='text-right'>Eliminar</th>
										</tr>

										<?php
										while ($row_adicional=mysqli_fetch_array($query_adicionales)){
												$id_adicional_factura=$row_adicional['id_detalle'];
												$adicional_concepto_factura=$row_adicional['adicional_concepto'];
												$adicional_descripcion_factura=$row_adicional['adicional_descripcion'];
										?>
										<input type="hidden" value="<?php echo $id_adicional_factura;?>" id="id_adicional<?php echo $id_adicional_factura;?>">
										<tr>
												<td><?php echo $adicional_concepto_factura; ?></td>
												<td><?php echo $adicional_descripcion_factura; ?></td>
												<td class='text-right'><a href="#" class='btn btn-danger btn-md' title='Eliminar' onclick="eliminar_detalle_adicional('<?php echo $id_adicional_factura; ?>')" ><i class="glyphicon glyphicon-remove"></i></a></td>
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
