<div class="modal fade bs-example-modal-lg" data-backdrop="static" id="proveedores" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><span class='glyphicon glyphicon-search'></span> Cuentas por pagar proveedores</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="opciones_egreso_CxP_Proveedores">
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" id="dato_a_buscar" placeholder="Buscar" onkeyup="load(1)" autofocus>
								<span class="input-group-btn">
									<button type="button" id="boton_buscar" class="btn btn-default" onclick="load(1)"><span class="glyphicon glyphicon-search"></span> Buscar</button>
								</span>
							</div>
						</div>
						<div id="loader_cxp_proveedores"></div>
					</div>
					<div class="outer_div_cxp_proveedores"></div><!-- Datos ajax Final -->
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade bs-example-modal-lg" data-backdrop="static" id="nomina" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><span class='glyphicon glyphicon-search'></span> Sueldos por pagar</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="opciones_egreso_nomina">
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" id="dato_a_buscar_nomina" placeholder="Buscar" onkeyup="load(1)" autofocus>
								<span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick="load(1)"><span class="glyphicon glyphicon-search"></span> Buscar</button>
								</span>
							</div>
						</div>
						<div id="loader_nomina"></div>
					</div>
					<div class="outer_div_nomina"></div><!-- Datos ajax Final -->
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade bs-example-modal-lg" data-backdrop="static" id="quincena" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><span class='glyphicon glyphicon-search'></span> Quincenas por pagar</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="opciones_egreso_quincena">
					<div class="form-group">
						<div class="col-sm-6">
							<div class="input-group">
								<input type="text" class="form-control" id="dato_a_buscar_quincena" placeholder="Buscar" onkeyup="load(1)" autofocus>
								<span class="input-group-btn">
									<button type="button" class="btn btn-default" onclick="load(1)"><span class="glyphicon glyphicon-search"></span> Buscar</button>
								</span>
							</div>
						</div>
						<div id="loader_quincena"></div>
					</div>
					<div class="outer_div_quincena"></div><!-- Datos ajax Final -->
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<div class="modal fade bs-example-modal-lg" data-backdrop="static" id="formasPagosEgreso" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title"><span class='glyphicon glyphicon-usd'></span> Formas de pago</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="opciones_formasPagosEgreso">
						<div id="loader_formas_pagos_egreso"></div>
					<div class="outer_div_formas_pagos_egreso"></div><!-- Datos ajax Final -->
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>