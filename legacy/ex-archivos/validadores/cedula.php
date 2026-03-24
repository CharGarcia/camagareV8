<?php

function validaCedula($cedula_numero){
$validaCedula = $cedula_numero;		
						
						if(is_numeric($validaCedula)){ 
						$total_caracteres=strlen($validaCedula);// se suma el total de caracteres
						if($total_caracteres==10){//compruebo que tenga 10 digitos la cedula
							/*
						$nro_region=substr($validaCedula, 0,2);//extraigo los dos primeros caracteres de izq a der
						if($nro_region>=1 && $nro_region<=24){// compruebo a que region pertenece esta cedula//
						$ult_digito=substr($validaCedula, -1,1);//extraigo el ultimo digito de la cedula
						//extraigo los valores pares//
						$valor2=substr($validaCedula, 1, 1);
						$valor4=substr($validaCedula, 3, 1);
						$valor6=substr($validaCedula, 5, 1);
						$valor8=substr($validaCedula, 7, 1);
						$suma_pares=($valor2 + $valor4 + $valor6 + $valor8);
						//extraigo los valores impares//
						$valor1=substr($validaCedula, 0, 1);
						$valor1=($valor1 * 2);
						if($valor1>9){ $valor1=($valor1 - 9); }else{ }
						$valor3=substr($validaCedula, 2, 1);
						$valor3=($valor3 * 2);
						if($valor3>9){ $valor3=($valor3 - 9); }else{ }
						$valor5=substr($validaCedula, 4, 1);
						$valor5=($valor5 * 2);
						if($valor5>9){ $valor5=($valor5 - 9); }else{ }
						$valor7=substr($validaCedula, 6, 1);
						$valor7=($valor7 * 2);
						if($valor7>9){ $valor7=($valor7 - 9); }else{ }
						$valor9=substr($validaCedula, 8, 1);
						$valor9=($valor9 * 2);
						if($valor9>9){ $valor9=($valor9 - 9); }else{ }

						$suma_impares=($valor1 + $valor3 + $valor5 + $valor7 + $valor9);
						$suma=($suma_pares + $suma_impares);
						$dis=substr($suma, 0,1);//extraigo el primer numero de la suma
						$dis=(($dis + 1)* 10);//luego ese numero lo multiplico x 10, consiguiendo asi la decena inmediata superior
						$digito=($dis - $suma);
						if($digito==10){ $digito='0'; }else{ }//si la suma nos resulta 10, el decimo digito es cero
						if ($digito==$ult_digito){//comparo los digitos final y ultimo
						
						$value="cedula correcta";
						return $value;
						
						}else{
						$value="Cedula Incorrecta.";
						return $value;
						}
						}else{
						$value="Cedula Incorrecta.";
						return $value;
						}
						
						*/
						$value="cedula correcta";
						return $value;
						}else{
						$value="Cedula Incorrecta.";
						return $value;
						}
						}else{
						$value= "Cedula Incorrecta.";
						return $value;
						}
}
?>