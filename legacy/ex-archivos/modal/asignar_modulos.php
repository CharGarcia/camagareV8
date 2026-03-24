<!-- Modal -->
<div class="modal fade bs-example-modal-lg" id="asignar_modulos" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" onclick="load(1);" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Asignar módulos</h4>
      </div>
      <div class="modal-body">
	   <input type="hidden" id="id_empresa_asignada">
	  <div class="form-group row">
	  <div class="col-sm-6">
		  <div class="input-group">
			  <span class="input-group-addon">Usuario</span>
			  <input type="text" class="form-control" id="usuario_asignado_modulos" readonly>
		  </div>
	  </div>
	  <div class="col-sm-6">
		  <div class="input-group">
			  <span class="input-group-addon">Empresa</span>
			  <input type="text" class="form-control" id="empresa_asignada" readonly>
		  </div>
	  </div>
	  </div>
	  	<div class="form-group row">
		<div class="col-sm-6">
		<div class="input-group">
		<span class="input-group-addon ">Buscar</span>
			<input type="text" class="form-control" id="busca_modulo" onkeyup="buscar_modulos_asignados(1);" placeholder="Módulo">
			 <span class="input-group-btn">
				<button type="button" onclick="buscar_modulos_asignados(1);" class="btn btn-info" ><span class="glyphicon glyphicon-search" ></span> Buscar</button>
			  </span>
		</div>
		</div>
			<span id="loader_modulo"></span>					
	</div>
			<div class="outer_divdet_modulos" ></div><!-- Datos ajax Final -->

      </div>
      <div class="modal-footer">
        <button type="button" onclick="load(1);" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
    </div>

  </div>
</div> 
