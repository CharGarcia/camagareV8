
<div class="modal fade" id="editautorizacionsri" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id=""><i class='glyphicon glyphicon-edit'> Editar autorizaciones SRI</i></h4>
		</div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="editar_autorizacion_sri" name="editar_autorizacion_sri">
		<div id="resultados_ajax_edita_autorizacion"></div>
				<div class="form-group">
				<input type="hidden" id="id_autorizacion_mod" name="id_autorizacion_mod" >
					<label for="" class="col-sm-3 control-label"> Documento</label>
					<div class="col-sm-8">
					<select class="form-control" name="documento_sri_mod" id="documento_sri_mod">
									<?php
									$conexion = conenta_login();
									$sql = "SELECT * FROM comprobantes_autorizados;";
									$res = mysqli_query($conexion,$sql);
									while($o = mysqli_fetch_assoc($res)){
									?>
									<option value="<?php echo $o['codigo_comprobante'] ?> " ><?php echo $o['comprobante'] ?> </option>
									<?php
									}
									?>
					</select>
					</div>
				 </div>
				<div class="form-group">
						<label for="" class="col-sm-3 control-label"> Serie</label>
					  <div class="col-sm-6">
					<select class="form-control" name="serie_sri_mod" id="serie_sri_mod">
									<?php
									$conexion = conenta_login();
									$sql = "SELECT * FROM sucursales  where ruc_empresa ='$ruc_empresa' order by id_sucursal asc;";
									$res = mysqli_query($conexion,$sql);
									while($o = mysqli_fetch_assoc($res)){
									?>
									<option value="<?php echo $o['id_sucursal'] ?> " ><?php echo $o['serie'] ?> </option>
									<?php
									}
									?>
					</select>	 
					</div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-3 control-label"> Autorización</label>
					<div class="col-sm-6">
						<input type="text" class="form-control" id="autorizacion_sri_mod" name="autorizacion_sri_mod" placeholder="Número de autorización" required>
					</div>
				</div>
				<div class="form-group">
				<label for="" class="col-sm-3 control-label"> Fecha emisión</label>
					<div class="col-sm-4">
						<input type="date" class="form-control" id="fecha_emision_sri_mod" name="fecha_emision_sri_mod" placeholder="Fecha de emisión" required >
					</div>
				</div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Fecha vence</label>
					<div class="col-sm-4">
						<input type="date" class="form-control" id="fecha_vence_sri_mod" name="fecha_vence_sri_mod" placeholder="Fecha de vencimiento" required >
					</div> 
                 </div>
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Del</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" id="del_sri_mod" name="del_sri_mod" placeholder="Número desde" required>
					</div> 
                 </div> 
				<div class="form-group">
					<label for="" class="col-sm-3 control-label"> Al</label>
					<div class="col-sm-4">
						<input type="text" class="form-control" id="al_sri_mod" name="al_sri_mod" placeholder="Número hasta" required>
					</div> 
                 </div>
				 <div class="form-group">
					<label for="" class="col-sm-3 control-label"> Datos imprenta</label>
					<div class="col-sm-8">
						<input type="text" class="form-control" id="imprenta_mod" name="imprenta_mod" placeholder="Datos del pie de imprenta" required>
					</div> 
                 </div> 
				 
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="editar_datos_autorizaciones_sri" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
