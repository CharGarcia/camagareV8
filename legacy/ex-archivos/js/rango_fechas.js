// Obtener las fechas ingresadas por el usuario
//var fechaInicio = new Date(document.getElementById("fechaInicio").value);
//var fechaFin = new Date(document.getElementById("fechaFin").value);

function rango_fecha(fechaEntrada, fechaSalida, dias){
// Calcular la diferencia en milisegundos entre las fechas
var partesFeIni = fechaEntrada.split("-");
var partesFeFin = fechaSalida.split("-");

var anoIni = partesFeIni[2];
var mesIni = partesFeIni[1];
var diaIni = partesFeIni[0];

var anoFin = partesFeFin[2];
var mesFin = partesFeFin[1];
var diaFin = partesFeFin[0];

// Construir la fecha en formato "YYYY/MM/DD"
var inicio = anoIni + "/" + mesIni + "/" + diaIni;
var fin = anoFin + "/" + mesFin + "/" + diaFin;

var fechaIni = new Date(inicio);
var fechaFin = new Date(fin);


var diferenciaEnMilisegundos = fechaFin - fechaIni;
// Calcular la diferencia en días
var diasDeDiferencia = diferenciaEnMilisegundos / (1000 * 60 * 60 * 24);
// Comprobar si la diferencia en días es mayor que 30
    if (diasDeDiferencia > dias) {
        return true;
    } else {
        return false;
    }
    
}
