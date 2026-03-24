<?php

function numero_aleatorio(){
	//$a = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","O","P","Q","R","S","T","U","V","W","X","Y","Z","a","b","c","d","e","f","g","h","i","j","k","l","m","n","o","p","q","r","s","t","u","v","w","x","y","z","1","2","3","4","5","6","7","8","9","0");
	$a = array("1","2","3","4","5","6","7","8","9","0");
	$name = NULL;
	$e = count($a) - 1; //cuenta el número de elementos del arreglo y le resta 1
	for($i=1;$i<=20;$i++){
		$m = rand(0,$e); //devuelve un número randómico entre 0 y el número de elementos
		$name .= $a[$m];
	}
	return $name;					
}
?>