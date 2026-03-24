$( document ).ready(function() {
	   var movimiento_raton= true;
      var tiempo = 1000 * 60 * 40;

      $(document).mousemove(function(event){
         movimiento_raton = true;
        });
   //para cuando el raton no se ha movido por mas de 20 min y el sistema esta abierto
    setInterval (function() {
           if (!movimiento_raton) {      
            setTimeout(function (){
                location.href ='../includes/logout.php';
            }, tiempo);//60 minutos
         } else {
            movimiento_raton=false;
         }
      }, 3000); //5000 corresponde cada 5 segundos, esto quiere decir que cada 5 segundos compruba si el mause no se ha movido durante x segundos  
   })
   