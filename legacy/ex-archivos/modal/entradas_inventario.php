<!-- Modal -->
<div id="NuevaEntrada" data-backdrop="static" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Entradas inventario </h4>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" method="post" name="guardar_entrada" id="guardar_entrada">
          <div id="resultados_ajax_entradas"></div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Producto</b></span>
                <input type="hidden" name="id_producto" id="id_producto">
                <input type="hidden" name="codigo_producto" id="codigo_producto">
                <input type="text" name="nombre_producto" class="form-control" id="nombre_producto" value="" placeholder="Escribir para buscar un producto" onkeyup='agregar_productos();' autocomplete="off">
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Fecha registro</b></span>
                <input type="text" name="fecha_entrada" class="form-control" id="fecha_entrada" value="<?php echo date("d-m-Y"); ?>" placeholder="Fecha entrada" required>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Caducidad</b></span>
                <input type="text" title="Fecha de caducidad del producto" name="fecha_caducidad" class="form-control" id="fecha_caducidad" value="<?php echo date("d-m-Y"); ?>" placeholder="Fecha caducidad" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Cantidad</b></span>
                <input type="text" title="Cantidad total de la entrada" name="cantidad" class="form-control text-right" id="cantidad" required>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Medida</b></span>
                <input type="text" name="unidad_medida" class="form-control" id="unidad_medida" readonly>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Bodega</b></span>
                <select class="form-control" title="Bodega a guardarse el producto" name="bodega" id="bodega" required>
                  <?php
                  $con = conenta_login();
                  $sql_bodega = mysqli_query($con, "SELECT bod.id_bodega as id_bodega, bod.nombre_bodega as nombre_bodega FROM bodega as bod LEFT JOIN bodega_restringida as res ON res.id_bodega = bod.id_bodega and res.id_usuario='" . $id_usuario . "' WHERE res.id_usuario is null and bod.ruc_empresa ='" . $ruc_empresa . "' and bod.status ='1' order by bod.nombre_bodega asc");
                  while ($o = mysqli_fetch_assoc($sql_bodega)) {
                  ?>
                    <option value="<?php echo $o['id_bodega'] ?>"><?php echo strtoupper($o['nombre_bodega']) ?> </option>
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Costo</b></span>
                <input type="text" title="Costo unitario del producto" name="costo_producto" class="form-control text-right" id="costo_producto" placeholder="Costo Unitario">
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Lote</b></span>
                <input type="text" title="Lote" name="lote" class="form-control" id="lote" placeholder="Lote">
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Referencia</b></span>
                <input type="text" title="Referencia de la entrada" name="referencia" class="form-control" id="referencia" placeholder="Referencia-factura-proveedor">
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