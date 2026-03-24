<div class="modal fade" id="modalPresupuestos" data-backdrop="static" data-keyboard="false" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h5 class="modal-title" id="titleModalPresupuestos"></h5>
            </div>
            <div class="modal-body">
                <form id="formPresupuestos">
                    <input type="hidden" id="idPresupuesto" value="">
                    <div class="form-group row">
                        <div class="col-sm-9">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Proyecto</b></span>
                                <input type="text" id="proyecto" class="form-control input-sm" placeholder="Nombre, proyecto" onkeyup='agregar_empleado();' autocomplete="off">
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Status</b></span>
                                <select class="form-control input-sm" id="status">
                                    <option value="1" selected>En ejecución</option>
                                    <option value="2">Ejecutado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                         <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Fecha inicio</b></span>
                                <input type="text" id="desde" class="form-control input-sm text-center">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Fecha final</b></span>
                                <input type="text" id="hasta" class="form-control input-sm text-center">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="input-group">
                                <span class="input-group-addon"><b>Presupuesto</b></span>
                                <input type="text" id="total" value="0" class="form-control input-sm text-right" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h5><i class='glyphicon glyphicon-list'></i> Detalle de cuentas</h5>
                            <div class="form-group row">
                                <div class="col-sm-8">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Cuenta</b></span>
                                        <input type="hidden" id="id_cuenta" value="">
                                        <input type="hidden" id="codigo" value="">
                                        <input type="text" id="cuenta" onkeyup='buscar_cuentas();' autocomplete="off" class="form-control input-sm text-left">
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="input-group">
                                        <span class="input-group-addon"><b>Valor</b></span>
                                        <input type="number" id="valor" class="form-control input-sm text-right">
                                    </div>
                                </div>
                                <div class="col-sm-1">
                                    <div class="input-group input-group-sm">
                                        <button type="button" onClick="agrega_cuenta()" class="btn btn-info btn-sm" title="Agregar"><i class='glyphicon glyphicon-plus'></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="resultados_presupuestos"></div><!-- Carga los datos ajax -->
                        <div class="outer_div_presupuestos"></div><!-- Carga los datos ajax -->
                    </div>
            </div>
            <div class="modal-footer">
                <span id="resultados_modal_presupuestos"></span>
                <button type="button" class="btn btn-default" data-dismiss="modal" title="Cerrar">Cerrar</button>
                <button type="button" onclick="guarda_presupuesto();" id="btnActionFormPresupuestos" class="btn btn-primary" title=""><span id="btnTextPresupuestos"></span></button>
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
        $("#desde").mask("99-99-9999");
        $("#hasta").mask("99-99-9999");
    });

	function buscar_cuentas(){
	$("#cuenta").autocomplete({
			source:'../ajax/cuentas_autocompletar.php',
			minLength: 2,
			select: function(event, ui){
				event.preventDefault();
				$('#id_cuenta').val(ui.item.id_cuenta);
				$('#cuenta').val(ui.item.nombre_cuenta);
                $('#codigo').val(ui.item.codigo_cuenta);
				document.getElementById('valor').focus();
			}
		});
		//$("#cuenta" ).autocomplete("widget").addClass("fixedHeight");//para que aparezca la barra de desplazamiento en el buscar
		$("#cuenta" ).on( "keydown", function( event ) {
		if (event.keyCode== $.ui.keyCode.UP || event.keyCode== $.ui.keyCode.DOWN || event.keyCode== $.ui.keyCode.DELETE )
		{
			$("#id_cuenta" ).val("");
			$("#cuenta" ).val("");
            $("#codigo" ).val("");
		}
		if (event.keyCode== $.ui.keyCode.DELETE )
			{
			$("#id_cuenta" ).val("");
			$("#cuenta" ).val("");
            $("#codigo" ).val("");
			}
		});
}

    function guarda_presupuesto() {
        $('#btnTextPresupuestos').attr("disabled", true);
        var id_presupuesto = $("#idPresupuesto").val();
        var proyecto = $("#proyecto").val();
        var desde = $("#desde").val();
        var hasta = $("#hasta").val();
        var status = $("#status").val();
        var total = $("#total").val();

        if (proyecto == "") {
            alert('Ingrese un nombre de un proyecto o presupuesto');
            document.getElementById('proyecto').focus();
            return false;
        }

        if (desde == "") {
            alert('Ingrese fecha inicial');
            document.getElementById('desde').focus();
            return false;
        }

        if (hasta == "") {
            alert('Ingrese fecha final');
            document.getElementById('hasta').focus();
            return false;
        }

        $.ajax({
            type: "POST",
            url: "../ajax/presupuestos.php?action=guardar_presupuesto",
            data: "id_presupuesto=" + id_presupuesto + "&proyecto=" + proyecto +
                "&desde=" + desde + "&hasta=" + hasta + "&status=" + status + "&total="+total,
            beforeSend: function(objeto) {
                $("#resultados_presupuestos").html("Guardando...");
            },
            success: function(datos) {
                $("#resultados_presupuestos").html(datos);
                $('#btnTextPresupuestos').attr("disabled", false);
            }
        });
        event.preventDefault();
    }


    //agregar cuenta
    function agrega_cuenta() {
        var id_cuenta = $("#id_cuenta").val();
        var valor = $("#valor").val();
        var codigo = $("#codigo").val();
        var cuenta = $("#cuenta").val();
        var total = $("#total").val();

        if (id_cuenta == "") {
            alert('Seleccione una cuenta');
            document.getElementById('cuenta').focus();
            return false;
        }
        
        if (valor == "") {
            alert('Ingrese valor');
            document.getElementById('valor').focus();
            return false;
        }

        if (isNaN(valor)) {
            alert('El dato ingresado en valor, no es un número');
            document.getElementById('valor').focus();
            return false;
        }

        var valor_total = (parseFloat(total) + (parseFloat(valor)));
        $.ajax({
            type: "POST",
            url: "../ajax/presupuestos.php?action=agrega_cuenta",
            data: "id_cuenta=" + id_cuenta + "&valor=" + valor + "&codigo="+codigo+"&cuenta="+cuenta,
            beforeSend: function(objeto) {
                $("#resultados_presupuestos").html("Cargando...");
            },
            success: function(datos) {
                $("#resultados_presupuestos").html('');
                $(".outer_div_presupuestos").html(datos).fadeIn('slow');
                $("#id_cuenta").val('');
                $("#cuenta").val('');
                $("#codigo").val('');
                $("#valor").val('');
                $("#total").val(valor_total.toFixed(2));
                document.getElementById('cuenta').focus();
            }
        });
    }
    //para una fila de info adicional
    function eliminar_cuenta(id, valor) {
        var total = $("#total").val();
        var valor_total = (parseFloat(total) - (parseFloat(valor)));
        if (confirm("Realmente desea eliminar?")) {
            $.ajax({
                type: "POST",
                url: "../ajax/presupuestos.php?action=eliminar_cuenta",
                data: "id=" + id,
                beforeSend: function(objeto) {
                    $("#resultados_presupuestos").html("Eliminando...");
                },
                success: function(datos) {
                    $("#total").val(valor_total.toFixed(2));
                    $("#resultados_presupuestos").html('');
                    $(".outer_div_presupuestos").html(datos).fadeIn('slow');
                }
            });
        }
    }
</script>