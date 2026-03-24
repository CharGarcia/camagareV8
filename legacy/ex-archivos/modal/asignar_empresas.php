<!-- Modal -->
<div class="modal fade bs-example-modal-lg" id="asignar_empresas" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog modal-lg">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" onclick="load(1);" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel"><i class='glyphicon glyphicon-list-alt'></i> Asignar empresas</h4>
      </div>
      <div class="modal-body">
	  <input type="hidden" id="id_usuario_asignado">
	  <div class="form-group row">
	  <div class="col-sm-12">
		  <div class="input-group">
			  <span class="input-group-addon">Usuario</span>
			  <input type="text" class="form-control" id="usuario_asignado" readonly>
		  </div>
	  </div>
	  </div>
	    
	  <div class="form-group row">
		<div class="col-sm-5">
		<div class="input-group">
			<span class="input-group-addon">Buscar</span>
			<input type="text" class="form-control" id="busca_empresa" onkeyup="buscar_empresas_asignadas(1);" placeholder="Empresas">
			 <span class="input-group-btn">
				<button type="button" class="btn btn-info" onclick="buscar_empresas_asignadas(1);"><span class="glyphicon glyphicon-search" ></span> Buscar</button>
			  </span>
		</div>
		</div>
		<div class="col-sm-5">
		<input type="hidden" id="id_empresa_agregar" >
		<div class="input-group">
		<span class="input-group-addon ">Agregar</span>
			<input type="text" class="form-control" name="empresa_agregar" id="empresa_agregar" placeholder="Empresa" onkeyup='buscar_empresas();'>
			 <span class="input-group-btn">
				<button type="button" title="Agregar nueva empresa" class="btn btn-info" onclick='agregar_empresas();'><span class="glyphicon glyphicon-plus" ></span></button>
			  </span>
		</div>
		</div>
			<span id="loader_empresa"></span>					
	</div>
			<div class="outer_divdet_empresa" ></div><!-- Datos ajax Final -->

      </div>
      <div class="modal-footer">
        <button type="button" onclick="load(1);" class="btn btn-default" data-dismiss="modal">Cerrar</button>
      </div>
    </div>

  </div>
</div> 

