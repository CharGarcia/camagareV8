<?php
	/* Connect To Database*/
	include("../conexiones/conectalogin.php");
	$con = conenta_login();
	session_start();
	$ruc_empresa = $_SESSION['ruc_empresa'];
	$id_usuario = $_SESSION['id_usuario'];

//para actualizar la fecha de la factura
if(isset($_POST['id_factura_electronica'])){

if (empty($_POST['id_factura_electronica'])) {
           $errors[] = "Seleccione una factura para editar";
        }else if (empty($_POST['edita_fecha_f'])) {
           $errors[] = "Seleccione una fecha";
		}else if (!date($_POST['edita_fecha_f'])) {
           $errors[] = "No es una fecha valida";
        }  else if (
			!empty($_POST['id_factura_electronica']) && !empty($_POST['edita_fecha_f'])){
		// escaping, additionally removing everything that could be (html/javascript-) code
		$id_factura_electronica=mysqli_real_escape_string($con,(strip_tags($_POST["id_factura_electronica"],ENT_QUOTES)));
		$fecha_factura=date('Y-m-d H:i:s', strtotime($_POST['edita_fecha_f']));

		$sql="UPDATE encabezado_factura SET fecha_factura='$fecha_factura', id_usuario=$id_usuario WHERE id_encabezado_factura=$id_factura_electronica";
		$query_update = mysqli_query($con,$sql);
			if ($query_update){
				$messages[] = "La factura ha sido actualizada satisfactoriamente.";
			} else{
				$errors []= "Lo siento algo ha salido mal intenta nuevamente.".mysqli_error($con);
			}
}
		
		if (isset($errors)){
			
			?>
			<div class="alert alert-danger" role="alert">
				<button type="button" class="close" data-dismiss="alert">&times;</button>
					<strong>Error!</strong> 
					<?php
						foreach ($errors as $error) {
								echo $error;
							}
						?>
			</div>
			<?php
			}
			if (isset($messages)){
				
				?>
				<div class="alert alert-success" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Bien hecho!</strong>
						<?php
							foreach ($messages as $message) {
									echo $message;
								}
							?>
				</div>
				<?php
			}
}


//para agregar una nueva forma de pago a la factura
	if(isset($_GET['codigo_forma_pago'])){
		$serie =$_GET['serie_factura'];
		$secuencial =$_GET['secuencial_factura'];
		$codigo_forma_pago =$_GET['codigo_forma_pago'];
		// hay que comprobar que esa forma de pago no este ya registrada para esa factura
		$busca_formas_pago_factura = "SELECT * FROM formas_pago_ventas WHERE ruc_empresa='$ruc_empresa' and serie_factura ='$serie' and secuencial_factura = '$secuencial' and id_forma_pago = '$codigo_forma_pago' ";
		$result = $con->query($busca_formas_pago_factura);
		$formas_pago_encontradas = mysqli_num_rows($result);
		if ($formas_pago_encontradas >=1){
		echo "<script>alert('La forma de pago seleccionada, ya está registrada.')</script>";
		echo "<script>window.close();</script>";
		include("../ajax/muestra_info_editar_factura_e.php");
		}else{
			//luego hay que guardar esa forma de pago
		$guarda_forma_pago=mysqli_query($con,"INSERT INTO formas_pago_ventas VALUES (null, '$ruc_empresa','$serie','$secuencial','$codigo_forma_pago', '0')");
		//hay que actualizar la pantalla
		include("../ajax/muestra_info_editar_factura_e.php");
		}		
	}
	
//para agregar info adicional a la factura
	if(isset($_GET['concepto']) && isset($_GET['detalle'])){
		$serie =$_GET['serie_factura'];
		$secuencial =$_GET['secuencial_factura'];
		$concepto_adicional =$_GET['concepto'];
		$detalle_adicional=$_GET['detalle'];
			//luego hay que guardar esa forma de pago
		$guarda_info_adicional=mysqli_query($con,"INSERT INTO detalle_adicional_factura VALUES (null, '$ruc_empresa','$serie','$secuencial','$concepto_adicional','$detalle_adicional')");
		//hay que actualizar la pantalla
		include("../ajax/muestra_info_editar_factura_e.php");	
	}
	
//para eliminar una nueva forma de pago a la factura
	if(isset($_GET['id_forma_pago'])){
		$serie =$_GET['serie_factura'];
		$secuencial =$_GET['secuencial_factura'];
		$id_forma_pago =$_GET['id_forma_pago'];
		// hay que comprobar que haya al menos un registro de formas de pago, ya que no se puede eliminar todas
		$busca_formas_pago_factura = "SELECT * FROM formas_pago_ventas WHERE ruc_empresa='$ruc_empresa' and serie_factura ='$serie' and secuencial_factura = '$secuencial' ";
		$result = $con->query($busca_formas_pago_factura);
		$formas_pago_encontradas = mysqli_num_rows($result);
		if ($formas_pago_encontradas ==1){
		echo "<script>alert('No es posible eliminar todas las formas de pago, al menos debe haber una.')</script>";
		echo "<script>window.close();</script>";
		include("../ajax/muestra_info_editar_factura_e.php");
		}else{
			//luego hay eliminar
		$elimina_forma_pago=mysqli_query($con,"DELETE FROM formas_pago_ventas WHERE id_fp = $id_forma_pago");
		//hay que actualizar la pantalla
		include("../ajax/muestra_info_editar_factura_e.php");
		}		
	}
	
//para eliminar detalle adicional a la factura
	if(isset($_GET['id_info_adi'])){
		$id_detalle =$_GET['id_info_adi'];
		$elimina_detalle_adicional=mysqli_query($con,"DELETE FROM detalle_adicional_factura WHERE id_detalle = $id_detalle");
		//hay que actualizar la pantalla
		include("../ajax/muestra_info_editar_factura_e.php");		
	}

//para mostrar los datos de adicionales y formas de pago en la ventana modal a modificar datos
	$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
	if($action == 'ajax'){
		include("../ajax/muestra_info_editar_factura_e.php");
	}	
?>