
<div class="modal fade" id="nuevoPeriodo" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-sm" role="document">
<div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-edit'></i> Nuevo período</h4>
        </div>
        <div class="modal-body">
	<form class="form-horizontal" method="post" id="guardar_periodo" name="guardar_periodo">
		<div id="resultados_ajax"></div>
				<div class="form-group">
					<label for="" class="col-sm-4 control-label"> Mes</label>
					<div class="col-sm-5">
					   <select class="form-control" name="mes_periodo" id="mes_periodo">
							<option value="<?php echo date("m") ?>" selected> <?php echo date("m") ?></option>
							<option value="01" >01</option>
							<option value="02" >02</option>
							<option value="03" >03</option>
							<option value="04" >04</option>
							<option value="05" >05</option>
							<option value="06" >06</option>
							<option value="07" >07</option>
							<option value="08" >08</option>
							<option value="09" >09</option>
							<option value="10" >10</option>
							<option value="11" >11</option>
							<option value="12" >12</option>
							
						</select>
					</div>
					</div>
					<div class="form-group">
					
					<label for="" class="col-sm-4 control-label"> Año</label>
					<div class="col-sm-5">
					   <select class="form-control" name="anio_periodo" id="anio_periodo">
					       <option value="<?php echo date("Y")+1 ?>" selected> <?php echo date("Y") +1 ?></option>
							<option value="<?php echo date("Y") ?>" selected> <?php echo date("Y") ?></option>
							<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-10; $i+= -1) {
							?> 
							<option value="<?php echo $i ?>"> <?php echo $i ?></option>
							<?php }  ?> 
						</select>
					</div>
				 </div>
				      
				</div>
				<div class="modal-footer">
				   <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				   <button type="submit" class="btn btn-primary" id="guardar_datos_periodo" >Guardar</button>
				</div>
            </form>
	</div>
	</div>
 </div>
