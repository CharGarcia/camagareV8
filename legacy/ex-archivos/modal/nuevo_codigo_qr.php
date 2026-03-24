<div class="modal fade" id="nuevoCodigoQr" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
<div class="modal-dialog modal-lg" role="document">
			<div class="modal-content">
				<div class="modal-header">
				  <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				  <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Crear QR</h4>
				</div>

				<div id="resultados_qr"></div>
				<div id="loader_resultados_qr"></div>
				
			<div class="modal-body">
		<form class="form-horizontal" method="POST" id="codigogr" name="codigogr" enctype="multipart/form-data">
				<div class="form-group">
					<div class="col-sm-12">	
					 <div class="input-group">
						  <span class="input-group-addon"><b>Título Principal o nombre general del QR</b></span>
							<input type="text" class="form-control" name="titulo_general" id="titulo_general" placeholder="Ejemplo: Menú">  
					  </div>
					</div>
					</div>
			
				<div class="form-group">
					<div class="col-sm-12">	
					 <div class="input-group">
						  <span class="input-group-addon"><b>Título Pestaña o nombre individual de cada categoría</b></span>
							<input type="text" class="form-control" name="titulo_pestana" id="titulo_pestana" placeholder="Ejemplo: Entradas, bebidas, etc.">  
					  </div>
					</div>
				</div>	
				<div class="form-group">
				<div class="col-sm-8">	
					 <div class="input-group">
						  <span class="input-group-addon"><b>Detalle o descripción</b></span>
							<input type="text" class="form-control" name="detalle" id="detalle" placeholder="Ejemplo: Coca cola $1.50">  
					  </div>
				</div>
				<div class="col-sm-4">
					<div class="input-group">
					<span class="input-group-addon"><b> Posición del texto</b></span>
					<select class="form-control" name="posicion_texto" id="posicion_texto" >
					<option value="1"Selected>Izquierda</option>
					<option value="2">Centro</option>
					<option value="3">Derecha</option>
					</select>
					</div>
				</div>
				</div>
				
				<div class="form-group">
				<div class="col-sm-6">
					<div class="input-group">
					<span class="input-group-addon"><b> Imagen o foto adicional</b></span>
					<input class='filestyle' data-buttonText=" Imagen" type="file" name="imagen" id="imagen">
					</div>
				</div>
				<div class="col-sm-4">
					<div class="input-group">
					<span class="input-group-addon"><b> Posición de Imagen</b></span>
					<select class="form-control" name="posicion" id="posicion" >
					<option value="1">Arriba del texto</option>
					<option value="2"Selected>Debajo del texto</option>
					</select>
					</div>
				</div>
				<div class="col-sm-2">	
					<button class='btn btn-info' type="submit"><i class="glyphicon glyphicon-plus"></i> Agregar</button>
				</div>  
				</div>
		</form>

				<div id="muestra_detalle_qr"></div><!-- Carga gif animado -->
				<div class="outer_divdet" ></div><!-- Datos ajax Final -->
			</div>
				<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal" reset>Cerrar</button>
						<button type="button" class="btn btn-info" onclick="guardar_qr()">Guardar</button>
				</div>
	</div>
</div>
</div>

