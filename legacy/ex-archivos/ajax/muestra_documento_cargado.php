<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
?>
<div class="table-responsive">
			 
		<?php
		$action = (isset($_REQUEST['action'])&& $_REQUEST['action'] !=NULL)?$_REQUEST['action']:'';
		//si cierra la ventana modal se pone pendiente en el registro para que se pueda volver a modificar el registro
		if($action == 'cerrar'){
				$id_documento = $_SESSION['id_documento'];
				
				//ACTUALIZAR LA TABLA DE DOCUMENTOS DE  REGISTRANDO  a PENDIENTE
				$sql="UPDATE documentos_subidos SET estado='PENDIENTE' WHERE id_documento = $id_documento ";
				$query_update = mysqli_query($con,$sql);
				
				if ($query_update){
			?>
			<div class="alert alert-warning" role="alert">
						<button type="button" class="close" data-dismiss="alert">&times;</button>
						<strong>¡Atención!</strong> No se registró el documento
				</div>
	
			<?php
			}

		}
		
		if($action == 'ajax'){
				$id_documento = mysqli_real_escape_string($con,(strip_tags($_REQUEST['id_documento'], ENT_QUOTES)));
				$busca_documentos = "SELECT * FROM documentos_subidos WHERE id_documento = $id_documento AND estado = 'PENDIENTE' ";
				$resultado_de_la_busqueda = $con->query($busca_documentos);
				//ACTUALIZAR LA TABLA DE DOCUMENTOS DE PENDIENTE A REGISTRANDO
				$sql="UPDATE documentos_subidos SET estado='PROCESANDO' WHERE id_documento = $id_documento AND estado = 'PENDIENTE'";
				$query_update = mysqli_query($con,$sql);
				$_SESSION['id_documento'] = $id_documento; //elevar id documento a sesion para recoger en otro lado

				while ($row=mysqli_fetch_array($resultado_de_la_busqueda)){
						$id_documento=$row['id_documento'];
						$id_empresa=$row['id_empresa'];
						$cod_documento=$row['cod_documento'];
						$archivo=$row['archivo'];
						$detalle=$row['detalle'];
						$imageFileType = pathinfo(strtolower($archivo),PATHINFO_EXTENSION);
						
						//buscar el nombre de la empresa
						$busca_empresa = "SELECT * FROM empresas WHERE id = $id_empresa ";
						$resultado_de_la_busqueda_empresa = $con->query($busca_empresa);
						$empresa=mysqli_fetch_array($resultado_de_la_busqueda_empresa);
						$nombre_empresa=$empresa['nombre'];
						//buscar el nombre del documento
						$busca_documento = "SELECT * FROM tipos_documentos_subir WHERE cod_documento = '$cod_documento' ";
						$resultado_de_la_busqueda_documento = $con->query($busca_documento);
						$documento=mysqli_fetch_array($resultado_de_la_busqueda_documento);
						$nombre_documento=$documento['detalle_documento'];
					?>	
						
						<?php
						if ($imageFileType=="pdf"){
						?>
						<td><embed src="<?php echo $archivo;?>" type="application/pdf" width="100%" height="65%"></td>
						<?php
						}else{
						?>
						<td><img onclick="javascript:this.width=500;this.height=800" ondblclick="javascript:this.width=200;this.height=200" src="<?php echo $archivo;?>" width="200"/></td>
						<?php
						}
						?>
					
				<?php
				}
		}
				?>
			 
</div>