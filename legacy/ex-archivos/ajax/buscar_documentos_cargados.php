<?php
include("../conexiones/conectalogin.php");
$con = conenta_login();
session_start();
$id_usuario = $_SESSION['id_usuario'];
?>
<div class="table-responsive">
			  <table class="table">
				<tr  class="info">
					<th>Empresa</th>
					<th>Documento</th>
					<th>Archivo</th>
					<th>Detalle</th>
					<th class='text-right'>Eliminar</th>
					
				</tr>
				<?php
				$busca_documentos = "SELECT * FROM documentos_subidos WHERE id_usuario = $id_usuario and estado = 'PENDIENTE' ";
				$resultado_de_la_busqueda = $con->query($busca_documentos);

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
					<input type="hidden" value="<?php echo $archivo;?>" id="archivo<?php echo $id_documento;?>">
					<tr>
						<td><?php echo $nombre_empresa; ?></td>
						<td><?php echo $nombre_documento; ?></td>
						<?php
						if ($imageFileType=="pdf"){
						?>
						<td><embed src="<?php echo $archivo;?>" type="application/pdf" width="100%" height="100%"></td>
						<?php
						}else{
						?>
						<td><img onclick="javascript:this.width=500;this.height=400" ondblclick="javascript:this.width=100;this.height=80" src="<?php echo $archivo;?>" width="100"/></td>
						<?php
						}
						?>
						<td><?php echo $detalle; ?></td>
					<td><span class="pull-right">
						<a href="#" class='btn btn-danger btn-md' title='Eliminar documento' onclick="eliminar_doc('<?php echo $id_documento;?>');" ><i class="glyphicon glyphicon-erase"></i> </a>
					</span></td>
					
					</tr>
				<?php
				}
				?>
			  </table>
			</div>