<?php
session_start();
if(isset($_SESSION['id_usuario']) && isset($_SESSION['id_empresa']) && isset($_SESSION['ruc_empresa'])){
	$id_usuario = $_SESSION['id_usuario'];
	$id_empresa =$_SESSION['id_empresa'];
	$ruc_empresa = $_SESSION['ruc_empresa'];
	
?>
<html lang="es">
<head>
<title>Gráficos ventas</title>
<?php include("../paginas/menu_de_empresas.php");?>
</head>
<body>
		<div class="row">
			<div class="col-xs-2">
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
							<option value="1" selected> Anual</option>
							<option value="2" > Mensual</option>
							<option value="3"> Diario</option>
							<option value="4"> Períodos</option>
						</select>
						</div>
						</div>
					</div>
					
					<div class="form-group" id="ficha_anio">
					<div class="col-sm-12">
						<div class="input-group" >
						<span class="input-group-addon"><b>Año</b></span>
							<select class="form-control" name="anio_periodo" id="anio_periodo">
								<option value="<?php echo date("Y") ?>"> <?php echo date("Y") ?></option>
								<?php for ($i = $anio2=date("Y")-1; $i > $anio1=date("Y")-5; $i+= -1) {
								?> 
								<option value="<?php echo $i ?>"> <?php echo $i ?></option>
								<?php }  ?> 
							</select>
						</div>
					</div>
					</div>
					
					<div class="form-group" id="ficha_mes">
					<div class="col-sm-12" >
						<div class="input-group" >
						<span class="input-group-addon" ><b>Mes</b></span>
							<select class="form-control" name="mes_periodo" id="mes_periodo">
								<option value="01"> Enero</option>
								<option value="02"> Febrero</option>
								<option value="03"> Marzo</option>
								<option value="04"> Abril</option>
								<option value="05"> Mayo</option>
								<option value="06"> Junio</option>
								<option value="07"> Julio</option>
								<option value="08"> Agosto</option>
								<option value="09"> Septiembre</option>
								<option value="10"> Octubre</option>
								<option value="11"> Noviembre</option>
								<option value="12"> Diciembre</option>
							</select>
						</div>
					</div>
					</div>
					<div class="form-group" id="ficha_dia">
						<div class="col-sm-12">
						<div class="input-group" >
							<span class="input-group-addon"><b>Día</b></span>
							<input type="text" class="form-control input-sm" name="dia" id="dia" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
					</div>
					<div class="form-group" id="ficha_desde">
						<div class="col-sm-12">
						<div class="input-group" >
							<span class="input-group-addon"><b>Desde</b></span>
							<input type="text" class="form-control input-sm" name="desde" id="desde" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
					</div>
					<div class="form-group" id="ficha_hasta">
						<div class="col-sm-12">
						<div class="input-group" >
							<span class="input-group-addon"><b>Hasta</b></span>
							<input type="text" class="form-control input-sm" name="hasta" id="hasta" value="<?php echo date("d-m-Y");?>">
						</div>
						</div>
					</div>
					<div class="form-group">
					<div class="col-sm-12">
					<div class="input-group">
						<span class="input-group-addon"><b>Gráfico</b></span>
						<select class="form-control" name="grafico" id="grafico">
							<option value="line"> Lineal</option>
							<option value="column" > Columnas</option>
							<option value="bar" selected> Barras</option>
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
     $("#dia").mask("99-99-9999");
	  $("#desde").mask("99-99-9999");
	   $("#hasta").mask("99-99-9999");
});

$( function() {
	$("#dia").datepicker({
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

$(document).ready(function(){
		document.getElementById("ficha_mes").style.display="none";
		document.getElementById("ficha_desde").style.display="none";
		document.getElementById("ficha_hasta").style.display="none";
		document.getElementById("ficha_dia").style.display="none";
		
});
$('#tipo').change(function(){
	var tipo = $("#tipo").val();
	if (tipo=='1'){
		document.getElementById("ficha_mes").style.display="none";
		document.getElementById("ficha_desde").style.display="none";
		document.getElementById("ficha_hasta").style.display="none";
		document.getElementById("ficha_dia").style.display="none";
		document.getElementById("ficha_anio").style.display="";
	}
	if (tipo=='2'){
	document.getElementById("ficha_mes").style.display="";
	document.getElementById("ficha_dia").style.display="none";
	document.getElementById("ficha_desde").style.display="none";
	document.getElementById("ficha_hasta").style.display="none";
	}
	if (tipo=='3'){
	document.getElementById("ficha_dia").style.display="";
	document.getElementById("ficha_mes").style.display="none";
	document.getElementById("ficha_desde").style.display="none";
	document.getElementById("ficha_hasta").style.display="none";
	document.getElementById("ficha_anio").style.display="none";
	}
	if (tipo=='4'){
	document.getElementById("ficha_dia").style.display="none";
	document.getElementById("ficha_mes").style.display="none";
	document.getElementById("ficha_desde").style.display="";
	document.getElementById("ficha_hasta").style.display="";
	document.getElementById("ficha_anio").style.display="none";
	}
	
});
//para cuando se seleecione el anio
function mostrar_char(){
		var anio = $("#anio_periodo").val();
		var mes = $("#mes_periodo").val();
		var dia = $("#dia").val();
		var tipo = $("#tipo").val();
		var desde = $("#desde").val();
		var hasta = $("#hasta").val();
		var tipo_char = $("#grafico").val();
		
		$.ajax({
			url:'../ajax/analisis_ventas.php?action=analisis_ventas&anio='+anio+'&mes='+mes+'&tipo='+tipo+'&desde='+desde+'&hasta='+hasta+'&dia='+dia,
			 beforeSend: function(objeto){
			 $('#loader').html('<img src="../image/ajax-loader.gif">');
		  },
			success:function(data){
					$.each(data, function(i, item) {
						grafico(item.meses, anio, mes, dia, desde,hasta, tipo, tipo_char, item.sumas);
						$('#loader').html('');
					});
			}
		})
}

function grafico(meses, anio, mes, dia, desde, hasta, tipo, tipo_char, sumas){
if (tipo=='1'){
	tipo='Detalle del año por cada mes';
	mensaje='Meses';
	etiqueta=anio;
}
if (tipo=='2'){
	tipo='Detalle del mes por cada día';
	mensaje='Días';
	etiqueta=mes+'-'+anio;
}
if (tipo=='3'){
	tipo='Detalle del día por cada hora';
	mensaje='Horas';
	etiqueta=dia;
}
if (tipo=='4'){
	tipo='Detalle del día por cada hora';
	mensaje='Horas';
	etiqueta='Desde: '+desde +' Hasta: '+hasta;
}

Highcharts.chart('container', {
  chart: {
    type: tipo_char//'line' column, bar
  },
  title: {
    text: 'Detalle de ventas'
  },
  subtitle: {
    text: tipo
  },
  xAxis: {
    categories: meses,
    title: {
      text: mensaje
    }
  },
  yAxis: {
    min: 0,
    title: {
      text: 'Cantidad en Dólares',
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
    name: [etiqueta],
    data: sumas
  },  
]
});
}
</script>
