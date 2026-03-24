<?php
function validar_mails($mails){
		$extrae_mail = explode(", ",$mails);
			foreach ($extrae_mail as $validar){
				if (!filter_var($validar, FILTER_VALIDATE_EMAIL)){
				return 'error';
				}	
			}
	}
?>