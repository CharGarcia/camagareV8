<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
?>
<html lang="es">
<head>
<title>Ventas horas pico</title>
<?php include("../paginas/menu_de_empresas.php");?>
</head>
<body>
		<div class="row">
			<div class="col-sm-2">
			<div class="panel panel-default">
				 <div class="panel-heading">  
				<form class="form-horizontal" >
					<input type="hidden" id="mes">
					<input type="hidden" id="suma">
					<div class="form-group">
						<div class="col-sm-12">
						<div class="input-group">
						<span class="input-group-addon"><b>Tipo</b></span>
						<select class="form-control" name="tipo" id="tipo">
							<option value="1" selected> Diario</option>
							<option value="2" > Mensual</option>
							<option value="3"> Períodos</option>
						</select>
						</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
						<div class="input-group">
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm" name="dia_desde" id="dia_desde" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-12">
						<div class="input-group">
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm" name="dia_hasta" id="dia_hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
					</div>
					<div class="form-group">
					<div class="col-sm-12">
					<div class="input-group">
						<span class="input-group-addon"><b>Gráfico</b></span>
						<select class="form-control" name="grafico" id="grafico">
							<option value="line" selected> Lineal</option>
							<option value="column" > Columnas</option>
							<option value="bar"> Barras</option>
							<option value="area" > Area</option>
							<option value="spline" > Invertido</option>
						</select>
					</div>
					</div>
					</div>
					<div class="form-group">
					<label class="col-sm-2 control-label"></label>
						<div class="col-sm-10">
						<button type="button" class="btn btn-info" onclick='mostrar_char();'><span class="glyphicon glyphicon-search" ></span> Mostrar </button>
						<span id="loader"></span>
						</div>
					</div>
					</form>
				</div>
				
					
			</div>
						
			</div>
			<div id="resultados"></div>
			<div class="col-xs-9">
				<div id="container" style="min-width: 300px; max-width: 1200px; height: 500px; margin: 1 auto"></div>
			</div>
			
		</div>
	
</body>
</html>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="../js/jquery.maskedinput.js" type="text/javascript"></script>
<?php
}else{
header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/sistema') . '/empresa');
exit;
}
?>
<script >
jQuery(function($){
     $("#dia_desde").mask("99-99-9999");
	 $("#dia_hasta").mask("99-99-9999");
});


$( function() {
	$("#dia_desde").datepicker({
        dateFormat: "dd-mm-yy",
        firstDay: 1,
        dayNamesMin: ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"],
        dayNamesShort: ["Dom", "Lun", "Mar", "Mie", "Jue", "Vie", "Sab"],
        monthNames: 
            ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio",
            "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        monthNamesShort: 
            ["Ene", "Feb", "Mar", "Abr", "May", "Jun",
            "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"]
});
});

//para cuando se seleecione el anio
function mostrar_char(){
		var desde = $("#dia_desde").val();
		var hasta = $("#dia_hasta").val();
		var tipo = $("#tipo").val();
		var tipo_char = $("#grafico").val();
		$.ajax({
			url:'../ajax/horas_pico.php?action=horas_pico&desde='+desde+'&hasta='+hasta+'&tipo='+tipo,
			 beforeSend: function(objeto){
			 $('#loader').html('<img src="../image/ajax-loader.gif">');
		  },
			success:function(data){
					$.each(data, function(i, item) {
						grafico(item.horas, desde, hasta, tipo, tipo_char, item.sumas);
						$('#loader').html('');
					});
			}
		})
}

function grafico(horas, desde, hasta, tipo, tipo_char, sumas){

if (tipo=='1'){
	tipo='Diario';
	mensaje=desde;
}
if (tipo=='2'){
	tipo='Mensual';
	var mes = desde.substr(3, 2);
	mensaje = 'Mes: '+mes;
}
if (tipo=='3'){
	tipo='Períodos';
	mensaje='Desde: '+desde+' hasta: '+hasta;
}

Highcharts.chart('container', {
  chart: {
    type: tipo_char//'line' column, bar
  },
  title: {
    text: 'Detalle de facturación horas pico'
  },
  subtitle: {
    text: tipo
  },
  xAxis: {
    categories: horas,
    title: {
      text: 'Horas del día'
    }
  },
  yAxis: {
    min: 0,
    title: {
      text: 'Total USD',
      align: 'high'
    },
    labels: {
      overflow: 'justify'
    }
  },
  tooltip: {
    valueSuffix: ' Dólares'
  },
  plotOptions: {
    bar: {
      dataLabels: {
        enabled: true
      }
    }
  },
  legend: {
    layout: 'vertical',
    align: 'right',
    verticalAlign: 'top',
    x: -80,
    y: 10,
    floating: true,
    borderWidth: 2,
    backgroundColor: ((Highcharts.theme && Highcharts.theme.legendBackgroundColor) || '#FFFFFF'),
    shadow: true
  },
  credits: {
    enabled: false
  },
 series: [{
    name: [mensaje],
    data: sumas
  },  
]
});
}
</script>
