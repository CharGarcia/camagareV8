<div class="modal fade bs-example-modal-lg" id="NuevaFacturaProgramada" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><span class='glyphicon glyphicon-search'></span> Buscar clientes</h4>
		  </div>
		  <div class="modal-body">
		  <input type="hidden" name="id_alumno" id="id_alumno">
			<form class="form-horizontal">
			  <div class="form-group">
				<div class="col-sm-6">
				 <input type="text" class="form-control" id="cli" placeholder="Buscar cliente" onkeyup="load(1)">
				</div>
				<button type="button" class="btn btn-default" onclick="load(1)"><span class='glyphicon glyphicon-search'></span> Buscar</button>
				<span id="loaderCliente"></span>
			  </div>
			</form>
			  <div class="outer_divcli" ></div><!-- Datos ajax Final -->
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		  </div>
		</div>
	</div>
 </div>

 <div class="modal fade bs-example-modal-lg" id="NuevoReciboProgramado" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><span class='glyphicon glyphicon-search'></span> Buscar clientes</h4>
		  </div>
		  <div class="modal-body">
		  <input type="hidden" name="id_cliente_recibo" id="id_cliente_recibo">
			<form class="form-horizontal">
			  <div class="form-group">
				<div class="col-sm-6">
				 <input type="text" class="form-control" id="cli_recibo" placeholder="Buscar cliente" onkeyup="load(1)">
				</div>
				<button type="button" class="btn btn-default" onclick="load(1)"><span class='glyphicon glyphicon-search'></span> Buscar</button>
				<span id="loaderCliente"></span>
			  </div>
			</form>
			  <div class="outer_divcli" ></div><!-- Datos ajax Final -->
		  </div>
		  <div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		  </div>
		</div>
	</div>
 </div>
