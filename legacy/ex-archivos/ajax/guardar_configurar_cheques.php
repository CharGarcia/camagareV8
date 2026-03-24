<?php				
		/* Connect To Database*/
		include("../conexiones/conectalogin.php");
		$con = conenta_login();
		session_start();
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$id_usuario = $_SESSION['id_usuario'];
		
	if (isset($_POST['default'])){
		$delete_all = mysqli_query($con, "DELETE FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."'");
	if ($delete_all){
			echo "<script>
				$.notify('Datos reiniciados exitosamente','success');
				setTimeout(function (){location.reload()}, 1000);
				</script>";
		}else {
			echo "<script>
				$.notify('Lo siento algo ha salido mal intenta nuevamente','error')
				</script>";
		}
	}
		
		
		
	if (isset($_POST['guardar_confi'])){	
	$ben_ini_der_izq=mysqli_real_escape_string($con,(strip_tags($_POST["ben_ini_der_izq"],ENT_QUOTES)));
	$ben_ini_arr_aba=mysqli_real_escape_string($con,(strip_tags($_POST["ben_ini_arr_aba"],ENT_QUOTES)));
	$ben_ini_anc_cell=mysqli_real_escape_string($con,(strip_tags($_POST["ben_ini_anc_cell"],ENT_QUOTES)));
	$ben_ini_alt_cell=mysqli_real_escape_string($con,(strip_tags($_POST["ben_ini_alt_cell"],ENT_QUOTES)));

	$canum_ini_der_izq=mysqli_real_escape_string($con,(strip_tags($_POST["canum_ini_der_izq"],ENT_QUOTES)));
	$canum_ini_arr_aba=mysqli_real_escape_string($con,(strip_tags($_POST["canum_ini_arr_aba"],ENT_QUOTES)));
	$canum_ini_anc_cell=mysqli_real_escape_string($con,(strip_tags($_POST["canum_ini_anc_cell"],ENT_QUOTES)));
	$canum_ini_alt_cell=mysqli_real_escape_string($con,(strip_tags($_POST["canum_ini_alt_cell"],ENT_QUOTES)));

	$canle_ini_der_izq=mysqli_real_escape_string($con,(strip_tags($_POST["canle_ini_der_izq"],ENT_QUOTES)));
	$canle_ini_arr_aba=mysqli_real_escape_string($con,(strip_tags($_POST["canle_ini_arr_aba"],ENT_QUOTES)));
	$canle_ini_anc_cell=mysqli_real_escape_string($con,(strip_tags($_POST["canle_ini_anc_cell"],ENT_QUOTES)));
	$canle_ini_alt_cell=mysqli_real_escape_string($con,(strip_tags($_POST["canle_ini_alt_cell"],ENT_QUOTES)));

	$ciufec_ini_der_izq=mysqli_real_escape_string($con,(strip_tags($_POST["ciufec_ini_der_izq"],ENT_QUOTES)));
	$ciufec_ini_arr_aba=mysqli_real_escape_string($con,(strip_tags($_POST["ciufec_ini_arr_aba"],ENT_QUOTES)));
	$ciufec_ini_anc_cell=mysqli_real_escape_string($con,(strip_tags($_POST["ciufec_ini_anc_cell"],ENT_QUOTES)));
	$ciufec_ini_alt_cell=mysqli_real_escape_string($con,(strip_tags($_POST["ciufec_ini_alt_cell"],ENT_QUOTES)));

	
	$delete_all = mysqli_query($con, "DELETE FROM configurar_cheques WHERE ruc_empresa = '".$ruc_empresa."'");

	$query_new_insert=mysqli_query($con, "INSERT INTO configurar_cheques VALUES (null,'".$ruc_empresa."','beneficiario','".$ben_ini_der_izq."','".$ben_ini_arr_aba."','".$ben_ini_anc_cell."','".$ben_ini_alt_cell."','".$id_usuario."')");
	$query_new_insert=mysqli_query($con, "INSERT INTO configurar_cheques VALUES (null,'".$ruc_empresa."','cantidad_numeros','".$canum_ini_der_izq."','".$canum_ini_arr_aba."','".$canum_ini_anc_cell."','".$canum_ini_alt_cell."','".$id_usuario."')");
	$query_new_insert=mysqli_query($con, "INSERT INTO configurar_cheques VALUES (null,'".$ruc_empresa."','cantidad_letras','".$canle_ini_der_izq."','".$canle_ini_arr_aba."','".$canle_ini_anc_cell."','".$canle_ini_alt_cell."','".$id_usuario."')");
	$query_new_insert=mysqli_query($con, "INSERT INTO configurar_cheques VALUES (null,'".$ruc_empresa."','ciudad_fecha','".$ciufec_ini_der_izq."','".$ciufec_ini_arr_aba."','".$ciufec_ini_anc_cell."','".$ciufec_ini_alt_cell."','".$id_usuario."')");
	if ($query_new_insert){
		echo "<script>$.notify('Configuración guardada.','success');
		setTimeout(function (){location.reload()}, 1000);
		</script>";
	} else{
		echo "<script>$.notify('Lo siento algo ha salido mal intenta nuevamente.','error')</script>";
	}
	}
?>