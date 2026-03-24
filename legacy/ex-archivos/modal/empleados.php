<div class="modal fade" id="modalEmpleados" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h5 class="modal-title " id="titleModalEmpleado"></h5>  
    </div>
      <div class="modal-body">
        <form id="formEmpleados">
          <input type="hidden" id="idEmpleado" name="idEmpleado" value="">
          <div class="form-group row">
            <div class="col-sm-5">
              <div class="input-group">
                <span class="input-group-addon"><b>Tipo ID *</b></span>
                <select class="form-control form-control-sm" id="listTipoId" name="listTipoId">
                  <option value="1" selected>Cedula</option>
                  <option value="2">Pasaporte</option>
                </select>
              </div>
            </div>
            <div class="col-sm-7">
              <div class="input-group">
                <span class="input-group-addon"><b>Cédula/pasaporte *</b></span>
                <input type="text" id="txtDocumento" name="txtDocumento" tabindex="1" class="form-control focusNext" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Nombres y apellidos *</b></span>
                <input type="text" id="txtNombres" name="txtNombres" tabindex="2" class="form-control focusNext" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Dirección</b></span>
                <input type="text" id="txtDireccion" name="txtDireccion" tabindex="3" class="form-control focusNext" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Émail</b></span>
                <input type="email" id="txtEmail" name="txtEmail" tabindex="4" class="form-control focusNext" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-7">
              <div class="input-group">
                <span class="input-group-addon"><b>Teléfono</b></span>
                <input type="text" id="txtTelefono" name="txtTelefono" tabindex="5" class="form-control focusNext" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
            <div class="col-sm-5">
              <div class="input-group">
                <span class="input-group-addon"><b>Sexo</b></span>
                <select class="form-control form-control-sm" id="listSexo" name="listSexo">
                  <option value="1" selected> Masculino</option>
                  <option value="2"> Femenino</option>
                  <option value="3"> Otro</option>
                </select>
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-7">
              <div class="input-group">
                <span class="input-group-addon"><b>F. Nac * (dd-mm-aaaa)</b></span>
                <input type="text" class="form-control focusNext" id="txtFechaNacimiento" tabindex="6" name="txtFechaNacimiento" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm" title ="Fecha de nacimiento">
              </div>
            </div>
            <div class="col-sm-5">
              <div class="input-group">
                <span class="input-group-addon"><b>Status *</b></span>
                <select class="form-control form-control-sm" id="listStatus" name="listStatus">
                  <option value="1" selected>Activo</option>
                  <option value="2">Inactivo</option>
                </select>
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-12">
              <div class="input-group">
                <span class="input-group-addon"><b>Banco</b></span>
                <select class="form-control form-control-sm" id="listBanco" name="listBanco">
                <option value="0" selected>Ninguno</option>
                <?php
                    $con = conenta_login();
                    $sql = mysqli_query($con,"SELECT id_bancos, nombre_banco FROM bancos_ecuador order by nombre_banco asc");
                    while($row_banco = mysqli_fetch_assoc($sql)){
                    ?>
                <option value="<?php echo $row_banco['id_bancos'];?>" ><?php echo $row_banco['nombre_banco'];?></option>
                <?php 
                    }
                    ?>
                </select>
              </div>
            </div>
            </div>
            <div class="form-group row">
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Tipo cta</b></span>
                <select class="form-control form-control-sm" id="listTipoCta" name="listTipoCta">
                <option value="0" selected>Ninguna</option>  
                <option value="1">Ahorro</option>
                  <option value="2">Corriente</option>
                  <option value="3">Virtual</option>
                </select>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="input-group">
                <span class="input-group-addon"><b>Número cta</b></span>
                <input type="text" class="form-control focusNext" id="txtNumeroCuenta" tabindex="8" name="txtNumeroCuenta" aria-label="Sizing example input" aria-describedby="inputGroup-sizing-sm">
              </div>
            </div>
          </div>
      </div>
      <div class="modal-footer">
      <span id="resultados_modal_empleado"></span>
        <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar modal empleado"> Cerrar</button>
        <button type="submit" tabindex="7" onclick="guarda_empleado();"  id="btnActionFormEmpleado" class="btn btn-primary" title=""><span id="btnTextEmpleado"></span></button>
      </div>
      </form>
    </div>
  </div>
</div>
<link rel="stylesheet" href="../css/jquery-ui.css">
<script src="../js/jquery-ui.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<script src="../js/notify.js"></script>
<script>
         jQuery(function($) {
            $("#txtFechaNacimiento").mask("99-99-9999");
            $("#txtTelefono").mask("999-999-9999");
        });
    function guarda_empleado() {
                $('#btnTextEmpleado').attr("disabled", true);
                var id_empleado = $("#idEmpleado").val();
                var listTipoId = $("#listTipoId").val();
                var txtDocumento = $("#txtDocumento").val();
                var txtNombres = $("#txtNombres").val();
                var txtEmail = $("#txtEmail").val();
                var txtTelefono = $("#txtTelefono").val();
                var listSexo = $("#listSexo").val();
                var txtFechaNacimiento = $("#txtFechaNacimiento").val();
                var listStatus = $("#listStatus").val();
                var listBanco = $("#listBanco").val();
                var listTipoCta = $("#listTipoCta").val();
                var txtNumeroCuenta = $("#txtNumeroCuenta").val();
                var txtDireccion = $("#txtDireccion").val();
                
                $.ajax({
                    type: "POST",
                    url: "../ajax/empleados.php?action=guardar_empleado",
                    data: "id_empleado=" + id_empleado + "&tipo_id=" + listTipoId +
                        "&documento=" + txtDocumento + "&nombres=" + txtNombres + "&mail=" + txtEmail
                        + "&telefono=" + txtTelefono + "&sexo=" + listSexo + "&nacimiento=" + txtFechaNacimiento
                        + "&status=" + listStatus + "&banco=" + listBanco + "&tipo_cta=" + listTipoCta + "&cuenta=" + txtNumeroCuenta
                        + "&direccion="+txtDireccion,
                    beforeSend: function(objeto) {
                        $("#resultados_modal_empleado").html("Guardando...");
                    },
                    success: function(datos) {
                        $("#resultados_modal_empleado").html(datos);
                        $('#btnTextEmpleado').attr("disabled", false);
                    }
                });
                event.preventDefault();
            }
        </script>

