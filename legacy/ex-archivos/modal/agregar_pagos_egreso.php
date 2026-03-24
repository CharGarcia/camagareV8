<div class="modal fade bs-example-modal-lg" id="agregarPagos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
		  <div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title" id="myModalLabel"><span class='glyphicon glyphicon-search'></span> Datos egreso</h4>
		  </div>
		  <div class="modal-body">
		  <input type="hidden" name="id_tipo" id="id_tipo">
			<form class="form-horizontal">
			<div class="form-group">

			<div class="col-sm-6">
				<div class="input-group">
					<input type="text" class="form-control" id="dato_a_buscar" placeholder="Buscar" onkeyup="load(1)" autofocus>
					 <span class="input-group-btn">
						<button type="button" id="boton_buscar" class="btn btn-default" onclick="load(1)" ><span class="glyphicon glyphicon-search" ></span> Buscar</button>
					  </span>
				</div>
			</div>
			<div id="loader" ></div>
			</div>
			<div class="outer_div" ></div><!-- Datos ajax Final -->			  
			</form>
		  </div>
		  <div class="modal-footer">
			<button type="button" id="cierra_pagos" class="btn btn-default" data-dismiss="modal">Cerrar</button>
		  </div>
		</div>
	</div>
 </div>
