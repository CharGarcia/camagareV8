
			<!-- Modal -->
			<div class="modal fade bs-example-modal-lg" id="agregarProducto" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
			  <div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
				  <div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel">Buscar productos y servicios</h4>
				  </div>
				  <div class="modal-body">
					<form class="form-horizontal">
					  <div class="form-group">
						  <div class="col-sm-6">
								<div class="input-group">
									<input type="text" class="form-control" id="p" placeholder="Buscar productos" onkeyup='load(1);'>
									 <span class="input-group-btn">
										<button type="button" class="btn btn-default" onclick='load(1);'><span class="glyphicon glyphicon-search" ></span> Buscar</button>
									  </span>
								</div>
						  </div>
						</div>
					</form>
					<div id="loaderp"></div><!-- Carga gif animado -->
					<div class="outer_divp" ></div><!-- Datos ajax Final -->
				  </div>
				  <div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				  </div>
				</div>
			  </div>
			</div>
