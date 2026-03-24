<?php
function validaRuc($ruc)
{
	$total_caracteres = strlen($ruc); // se suma el total de caracteres				
	if (!is_numeric($ruc)) {
		$value = "Ruc incorrecto, ingrese números.";
	} else if ($total_caracteres != 13) {
		$value = "El ruc debe tener 13 dígitos.";
		//}else if(substr($ruc,12,1) == 0){
		//$value="El último dígito debe ser mayor a cero.";
	} else {

		/*
						//extraigo cada digito uno a uno
						$valor0=substr($validaRuc, 0, 1);
						$valor1=substr($validaRuc, 1, 1);
						$valor2=substr($validaRuc, 2, 1);
						$valor3=substr($validaRuc, 3, 1);
						$valor4=substr($validaRuc, 4, 1);
						$valor5=substr($validaRuc, 5, 1);
						$valor6=substr($validaRuc, 6, 1);
						$valor7=substr($validaRuc, 7, 1);
						$valor8=substr($validaRuc, 8, 1);
						$valor9=substr($validaRuc, 9, 1);
						$valor10=substr($validaRuc, 10, 1);
						$valor11=substr($validaRuc, 11, 1);
						$valor12=substr($validaRuc, 12, 1);

						//para calcular el ruc de una persona natural
						if ($valor2 < 6 ){
						$resultado1 = ($valor0 * 2)>9 ? ($valor0 * 2)-9 : ($valor0 * 2);
						$resultado2 = ($valor1 * 1)>9 ? ($valor1 * 1)-9 : ($valor1 * 1);
						$resultado3 = ($valor2 * 2)>9 ? ($valor2 * 2)-9 : ($valor2 * 2);
						$resultado4 = ($valor3 * 1)>9 ? ($valor3 * 1)-9 : ($valor3 * 1);
						$resultado5 = ($valor4 * 2)>9 ? ($valor4 * 2)-9 : ($valor4 * 2);
						$resultado6 = ($valor5 * 1)>9 ? ($valor5 * 1)-9 : ($valor5 * 1);
						$resultado7 = ($valor6 * 2)>9 ? ($valor6 * 2)-9 : ($valor6 * 2);
						$resultado8 = ($valor7 * 1)>9 ? ($valor7 * 1)-9 : ($valor7 * 1);
						$resultado9 = ($valor8 * 2)>9 ? ($valor8 * 2)-9 : ($valor8 * 2);				
						$suma = ($resultado1 + $resultado2 + $resultado3 + $resultado4 + $resultado5 + $resultado6 + $resultado7 + $resultado8 + $resultado9) ;
						$verificador = substr($suma, 1, 1);
						$digito_verificador = (10 - $verificador) == 10 ? 0 : (10 - $verificador); 
						$value = ($valor9 == $digito_verificador)? 'correcto' : 'Ruc persona natural incorrecto' ;					
						return $value;
						
						}
						//para calcular el ruc de una persona juridica
						if ($valor2 == 9) {
						$resultado1 = ($valor0 * 4);
						$resultado2 = ($valor1 * 3);
						$resultado3 = ($valor2 * 2);
						$resultado4 = ($valor3 * 7);
						$resultado5 = ($valor4 * 6);
						$resultado6 = ($valor5 * 5);
						$resultado7 = ($valor6 * 4);
						$resultado8 = ($valor7 * 3);
						$resultado9 = ($valor8 * 2);				
						$suma = ($resultado1 + $resultado2 + $resultado3 + $resultado4 + $resultado5 + $resultado6 + $resultado7 + $resultado8 + $resultado9) ;
						$total = ($suma/11);	//11 es el modulo para calcular persona juridica
						$decimales = explode('.',$total);
						$entero = $decimales[0];
						$residuo = ($suma - 11 * $entero) ;
						$digito_verificador = ($residuo == 0) ? 0 : (11 - $residuo); 
						$value = ($valor9 == $digito_verificador)? 'correcto' : 'Ruc persona jurídica incorrecto' ;					
						return $value;
						}
						//para calcular el ruc de una persona juridica publica
						if ($valor2 == 6) {
						$resultado1 = ($valor0 * 3);
						$resultado2 = ($valor1 * 2);
						$resultado3 = ($valor2 * 7);
						$resultado4 = ($valor3 * 6);
						$resultado5 = ($valor4 * 5);
						$resultado6 = ($valor5 * 4);
						$resultado7 = ($valor6 * 3);
						$resultado8 = ($valor7 * 2);
						$resultado9 = ($valor8 * 0);				
						$suma = ($resultado1 + $resultado2 + $resultado3 + $resultado4 + $resultado5 + $resultado6 + $resultado7 + $resultado8 + $resultado9) ;
						$total = ($suma/11);	//11 es el modulo para calcular persona juridica
						$decimales = explode('.',$total);
						$entero = $decimales[0];
						$residuo = ($suma - 11 * $entero) ;
						$digito_verificador = ($residuo == 0) ? 0 : (11 - $residuo); 
						$value = ($valor8 == $digito_verificador)? 'correcto' : 'Ruc persona jurídica pública incorrecto' ;					
						return $value;
						}
						
						}else{
						$value="Ruc incorrecto ingrese 13 números.";
						return $value;
						}
						
						

						*/
		return 'correcto';
	}
}
