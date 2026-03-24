<?php
include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];

//para saber el tipo de cuenta y el ultimo cheque de esa cuenta seleccionada		
if (isset($_POST['id_cuenta'])){
		$datos_cuenta_corriente = array();
		$id_cuenta=mysqli_real_escape_string($con,(strip_tags($_POST["id_cuenta"],ENT_QUOTES)));
		
			//para buscar tipo de cuenta 
			$busca_tipo_cuenta_bancaria = "SELECT * FROM cuentas_bancarias WHERE id_cuenta='".$id_cuenta."'";
			$result_tmp = $con->query($busca_tipo_cuenta_bancaria);
			$row_tipo_cuenta = mysqli_fetch_array($result_tmp);
			$tipo_cuenta = $row_tipo_cuenta['id_tipo_cuenta'];//para saber que tipo de cuenta es, ahorros o corriente

			//para buscar el ultimo cheque de la cuenta seleccionada siempre que sea cuemta corriente
			if ($tipo_cuenta==2){
			$busca_ultimo_cheque = "SELECT max(cheque) as ultimo_cheque FROM formas_pagos_ing_egr WHERE tipo_documento='EGRESO' and id_cuenta='".$id_cuenta."'";
			$result_ultimo_cheque = $con->query($busca_ultimo_cheque);
			$row_ultimo_cheque = mysqli_fetch_array($result_ultimo_cheque);
			$ultimo_cheque = $row_ultimo_cheque['ultimo_cheque']+1;
			}else{
			$ultimo_cheque =0;
			}
			
			$datos_cuenta_corriente[] = array('tipo_cuenta'=>$tipo_cuenta, 'ultimo_cheque'=>$ultimo_cheque);
			
		header('Content-Type: application/json');
		echo json_encode($datos_cuenta_corriente);			
}
	
	//para mostrar en el select solo la cuenta corriente si es banco y si es transferencia todas las cuentas
	if (isset($_POST['pago_forma'])){
			$forma=$_POST['pago_forma'];
				if ($forma=="02"){
				$cuenta_tipo = "id_tipo_cuenta=2";;
				}else{
				$cuenta_tipo = "id_tipo_cuenta>0";
				}
				
		?>
			<option value="0" selected>Seleccione</option>
				<?php
				$cuentas = mysqli_query($con,"SELECT cue_ban.id_cuenta as id_cuenta, concat(ban_ecu.nombre_banco,' ',cue_ban.numero_cuenta,' ', if(cue_ban.id_tipo_cuenta=1,'Aho','Cte')) as cuenta_bancaria FROM cuentas_bancarias as cue_ban INNER JOIN bancos_ecuador as ban_ecu ON cue_ban.id_banco=ban_ecu.id_bancos WHERE cue_ban.ruc_empresa ='".$ruc_empresa."'");
				while($p = mysqli_fetch_array($cuentas)){
				?>
				<option value="<?php echo $p['id_cuenta']?>"><?php echo strtoupper($p['cuenta_bancaria']) ?></option>
				<?php
				}
				?>

		<?php
	}
?>