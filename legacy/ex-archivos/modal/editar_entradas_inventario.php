<!-- Modal -->
<div id="EditarEntrada" data-backdrop="static" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Editar entradas inventario</h4>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" method="post" name="editar_entrada" id="editar_entrada">
          <div id="resultados_ajax_editar_entradas"></div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Producto</b></span>
                <input type="hidden" name="mod_id_inventario" id="mod_id_inventario">
                <input type="text" name="mod_nombre_producto" class="form-control" id="mod_nombre_producto" value="" placeholder="Escribir para buscar un producto" readonly>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Fecha registro</b></span>
                <input type="text" name="mod_fecha_entrada" class="form-control" id="mod_fecha_entrada" placeholder="Fecha entrada" required>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Caducidad</b></span>
                <input type="text" name="mod_fecha_caducidad" class="form-control" id="mod_fecha_caducidad" placeholder="Fecha caducidad" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Cantidad</b></span>
                <input type="text" name="mod_cantidad" class="form-control text-right" id="mod_cantidad" placeholder="Cantidad" required>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Medida</b></span>
                <input type="text" name="mod_unidad_medida" class="form-control" id="mod_unidad_medida" readonly>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Costo</b></span>
                <input type="text" name="mod_costo_producto" class="form-control text-right" id="mod_costo_producto" placeholder="Costo unitario" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Bodega</b></span>
                <?php
                $conexion = conenta_login();
                ?>
                <select class="form-control" name="mod_bodega" id="mod_bodega" required>
                  <?php
                  $sql = "SELECT * FROM bodega where ruc_empresa='" . $ruc_empresa . "';";
                  $res = mysqli_query($conexion, $sql);
                  ?> <option value="">Seleccione</option>
                  <?php
                  while ($o = mysqli_fetch_assoc($res)) {
                  ?>
                    <option value="<?php echo $o['id_bodega'] ?>"><?php echo strtoupper($o['nombre_bodega']) ?> </option>
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Lote</b></span>
                <input type="text" name="mod_lote" class="form-control" id="mod_lote" value="" placeholder="Lote">
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Referencia</b></span>
                <input type="text" name="mod_referencia" class="form-control" id="mod_referencia" value="" placeholder="Referencia-lote-factura-proveedor">
              </div>
            </div>
          </div>


      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
      </div>
      </form>
    </div>

  </div>
</div>