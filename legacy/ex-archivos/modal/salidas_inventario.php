<!-- Modal -->
<?php
$con = conenta_login();
?>
<div id="NuevaSalida" data-backdrop="static" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">×</button>
        <h4 class="modal-title"><i class='glyphicon glyphicon-edit'></i> Salidas inventario</h4>
      </div>
      <div class="modal-body">
        <form class="form-horizontal" method="post" name="guardar_salida" id="guardar_salida">
          <div id="resultados_ajax_salidas"></div>
          <input type="hidden" name="id_producto" id="id_producto">
          <input type="hidden" name="stock_salida_tmp" id="stock_salida_tmp">
          <input type="hidden" name="codigo_producto" id="codigo_producto">
          <input type="hidden" name="precio_salida_tmp" id="precio_salida_tmp">

          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Sucursal</b></span>
                <select class="form-control" title="Seleccione serie." name="serie_salida_inventario" id="serie_salida_inventario">
                  <?php
                  $res = mysqli_query($con, "SELECT * FROM sucursales WHERE ruc_empresa ='" . $ruc_empresa . "'");
                  while ($o = mysqli_fetch_array($res)) {
                  ?>
                    <option value="<?php echo $o['serie'] ?>"><?php echo strtoupper($o['serie']) ?></option>
                  <?php
                  }
                  ?>
                </select>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Fecha registro</b></span>
                <input type="text" name="fecha_salida" class="form-control" id="fecha_salida" placeholder="Fecha entrada" value="<?php echo date("d-m-Y"); ?>" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Producto</b></span>
                <input type="text" name="nombre_producto" class="form-control" id="nombre_producto" value="" placeholder="Escribir para buscar un producto" onkeyup='agregar_productos();' autocomplete="off">
              </div>
            </div>
          </div>
          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Bodega</b></span>
                <select class="form-control" name="bodega" id="bodega" required>
                  <?php
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
                <span class="input-group-addon"><b>Saldo</b></span>
                <input type="text" name="saldo_producto" class="form-control" id="saldo_producto" readonly>
              </div>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Lote</b></span>
                <select class="form-control" name="lote_salida" id="lote_salida">
                  <option value="" selected>Seleccione</option>
                </select>
                <span class="input-group-addon">
                  <a href="#" data-toggle="tooltip" data-placement="top" title="Si selecciona un lote se calcula la salida en base a la seleccion, si no lo hace calcula en base a la fecha mas antigua."><span class="glyphicon glyphicon-question-sign"></span></a>
                </span>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Medida</b></span>
                <select class="form-control" name="unidad_medida" id="unidad_medida" required>
                  <option value="">Seleccione</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Cantidad</b></span>
                <input type="text" name="cantidad" class="form-control text-right" id="cantidad" value="" placeholder="Cantidad" required>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Precio</b></span>
                <input type="text" name="precio_producto" class="form-control text-right" id="precio_producto" value="" placeholder="Precio" required>
              </div>
            </div>
          </div>

          <div class="form-group">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Referencia</b></span>
                <input type="text" name="referencia" class="form-control" id="referencia" value="" placeholder="Referencia">
              </div>
            </div>
          </div>
      </div><!--body end-->

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary" id="guardar_datos">Guardar</button>
      </div>
      </form>
    </div>

  </div>
</div>