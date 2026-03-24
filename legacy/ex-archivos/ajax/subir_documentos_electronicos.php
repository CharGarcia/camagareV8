<?php
include("../conexiones/conectalogin.php");
include("../clases/lee_xml.php");
require_once("../helpers/helpers.php");
session_start();
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
$con = conenta_login();
$rides_sri = new rides_sri();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

/* if ($action == 'consulta_documentos_descargados') {
	$ruc_empresa_consulta = $_GET['ruc_empresa'];
	$documento = $_GET['documento'];
	$anio = $_GET['anio'];
	$mes = $_GET['mes'];
	$dia = $_GET['dia'];
	$clave = encrypt_decrypt('decrypt', $_GET['password']);
	

	$url_almacenados = "http://137.184.159.242:3000/api/search-sri-doc-recibidos"; //emitidos recibidos
	$data_almacenados = array_map('strval', array(
		"ruc" => $ruc_empresa_consulta,
		"anio" => $anio,
		"mes" => $mes,
		"dia" => $dia,
		"tipoComprobante" => $documento
	));

	//documentos descargados en la carpeta temporal
	$ch = curl_init($url_almacenados);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_almacenados));
	$response = curl_exec($ch);

	if (curl_errno($ch)) {
		echo 'Error en la petición: ' . curl_error($ch);
	} else {
		// Decodificar la respuesta JSON
		$responseData = json_decode($response, true);
		// Comprobar si la decodificación fue exitosa
		$documentos_descargados = array();
		if (json_last_error() === JSON_ERROR_NONE) {
			// Verificar si hay registros
			if (is_array($responseData['data'])) {
				if (!empty($responseData['data'])) {
					// Iterar sobre los registros y mostrarlos
					foreach ($responseData['data'] as $registro) {
						$documentos_descargados[] = ['claveAcceso' => $registro['claveAcceso'], 'tipoComprobante' => $registro['tipoComprobante'], 'xmlUrl' => $registro['xmlUrl']];
					}
?>
					<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
						Nota: <?php echo count(array_column($documentos_descargados, 'claveAcceso')); ?> archivos obtenidos desde los servidores del SRI a nuestros servidores, este dato nos ayuda para comparar con los registros que constan en la plataforma del SRI.
					</div>
					<?php
					$clavesAccesoRegistrar = clavesParaRegistrar($con, $documentos_descargados);

					if (empty($clavesAccesoRegistrar)) {

						echo "<script>
						$.notify('Todos los documentos ya han sido cargados con anterioridad.','success');
							</script>";
					} else {
						$documento_registrado = descarga_xml_guardado($con, $clavesAccesoRegistrar, $ruc_empresa, $id_usuario);
						foreach ($documento_registrado as $message) {
							switch ($message['estado']) {
								case "0": ?>
									<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
										<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
									</div>
								<?php
									break;
								case "1":
								?>
									<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
										<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
									</div>
								<?php
									break;
								case "2":
								?>
									<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-warning" role="alert">
										<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
									</div>
								<?php
									break;
								case "3":
								?>
									<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-info" role="alert">
										<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
									</div><?php
											break;
									}
								}
							}
						} else { //cuando esta vacio el array data
							echo "<script>
                    $.notify('No se encontraron documentos para este período.','error');
                        </script>";
						}
					} else { //cuando no es arreglo
						echo "<script>
                    $.notify('El tipo de archivo no es compatible para cargar.','error');
                        </script>";
					}
				} else {
					echo "<script>
                    $.notify('Error al decodificar la consulta de documentos.','error');
                        </script>";
				}
			}
			curl_close($ch);
		} */

/* 		function clavesParaRegistrar($con, $documentos_descargados)
		{
			$xml_urls = array();
			foreach ($documentos_descargados as $documento) {
				switch ($documento['tipoComprobante']) {
					case "1": //factura
						$query_compras = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE aut_sri = '" . $documento['claveAcceso'] . "' ");
						if (mysqli_num_rows($query_compras) == 0) {
							$xml_urls[] = $documento['xmlUrl'];
						}
						break;
					case "2": //liquidacion
						$query_compras = mysqli_query($con, "SELECT * FROM encabezado_liquidacion WHERE aut_sri = '" . $documento['claveAcceso'] . "' ");
						if (mysqli_num_rows($query_compras) == 0) {
							$xml_urls[] = $documento['xmlUrl'];
						}
						break;
					case "3": //notas de credito
						$query_compras = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE aut_sri = '" . $documento['claveAcceso'] . "' ");
						if (mysqli_num_rows($query_compras) == 0) {
							$xml_urls[] = $documento['xmlUrl'];
						}
						break;
					case "4": //notas de debito
						$query_compras = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE aut_sri = '" . $documento['claveAcceso'] . "' ");
						if (mysqli_num_rows($query_compras) == 0) {
							$xml_urls[] = $documento['xmlUrl'];
						}
						break;
					case "6": // retencion
						$query_compras = mysqli_query($con, "SELECT * FROM encabezado_retencion_venta WHERE aut_sri = '" . $documento['claveAcceso'] . "' ");
						if (mysqli_num_rows($query_compras) == 0) {
							$xml_urls[] = $documento['xmlUrl'];
						}
						break;
				}
			}
			return $xml_urls;
		} */


/* 	function descarga_xml_guardado($con, $xml_urls, $ruc_empresa, $id_usuario)
		{
			$rides_sri = new rides_sri();
			$detalle_registrados = array();
			foreach ($xml_urls as $xml) {
				$url = "http://137.184.159.242:3000" . $xml;
				$object_xml = $rides_sri->lee_xml($url);
				$detalle_registrados[] = $rides_sri->lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con);
			}
			return $detalle_registrados;
		} */

if ($action == 'archivo_xml') {
	$carpetaTemporalDestino = '../docs_temp/carpetaXml';
	if (isset($_FILES['carpetaXml']) && !empty($_FILES['carpetaXml']['tmp_name'])) {
		// Iterar sobre cada archivo
		foreach ($_FILES['carpetaXml']['tmp_name'] as $index => $rutaArchivoTemporal) {
			$nombreArchivo = $_FILES['carpetaXml']['name'][$index];
			$rutaDestino = $carpetaTemporalDestino . '/' . $nombreArchivo;
			// Mover el archivo a la carpeta temporal de destino
			move_uploaded_file($rutaArchivoTemporal, $rutaDestino);
		}

		$archivos = scandir($carpetaTemporalDestino);
		$contador = 1;

		foreach ($archivos as $archivo) {
			// Ignorar los archivos . y ..
			if ($archivo === '.' || $archivo === '..') {
				continue;
			}
			// Ruta completa del archivo original
			$rutaArchivoOriginal = $carpetaTemporalDestino . DIRECTORY_SEPARATOR . $archivo;
			// Asegurarse de que sea un archivo válido y no un directorio
			if (!is_file($rutaArchivoOriginal)) {
				continue;
			}
			// Obtener la extensión del archivo
			$extension = pathinfo($archivo, PATHINFO_EXTENSION);
			// Construir el nuevo nombre con número secuencial
			do {
				$nuevoNombreArchivo = $carpetaTemporalDestino . DIRECTORY_SEPARATOR . $contador . '.' . $extension;
				$contador++;
			} while (file_exists($nuevoNombreArchivo)); // Evitar sobrescribir archivos existentes

			// Renombrar el archivo
			if (!rename($rutaArchivoOriginal, $nuevoNombreArchivo)) {
				echo "Error al renombrar el archivo: $rutaArchivoOriginal\n";
			}
		}
		// Obtener los nuevos archivos en la carpeta temporal
		$archivos_nuevos = array_values(array_diff(scandir($carpetaTemporalDestino), array('.', '..')));


		foreach ($archivos_nuevos as $archivo_nuevo) {
			$rutaArchivo = $carpetaTemporalDestino . '/' . $archivo_nuevo;
			$FileType = pathinfo($rutaArchivo, PATHINFO_EXTENSION);

			if ($FileType == "xml") {
				$object_xml = $rides_sri->lee_xml($rutaArchivo);
				$clave_acceso = $object_xml->infoTributaria->claveAcceso;
				$tipo_documento = $object_xml->infoTributaria->codDoc;
				$ruc_emisor = substr($object_xml->infoTributaria->ruc, 0, 10) . "001";

				switch ($tipo_documento) {
					case "01":
						$ruc_comprador = substr($object_xml->infoFactura->identificacionComprador, 0, 10) . "001";
						break;
					case "03":
						$ruc_comprador = $object_xml->infoLiquidacionCompra->identificacionProveedor;
						break;
					case "04":
						$ruc_comprador = substr($object_xml->infoNotaCredito->identificacionComprador, 0, 10) . "001";
						break;
					case "05":
						$ruc_comprador = substr($object_xml->infoNotaDebito->identificacionComprador, 0, 10) . "001";
						break;
					case "07":
						$ruc_comprador = substr($object_xml->infoCompRetencion->identificacionSujetoRetenido, 0, 10) . "001";
						break;
				}
				$array_datos[] = array('tipo' => 'archivo_xml', 'ruta_archivo' => $rutaArchivo, 'clave_acceso' => $clave_acceso, 'ruc_emisor' => $ruc_emisor, 'ruc_receptor' => $ruc_comprador);
			}
		}
		$claves_a_consultar = claves_por_consultar($con, $ruc_empresa, $array_datos);
		$registrar_documentos = registrar_documentos($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con);
		echo $registrar_documentos;

		$archivos = scandir($carpetaTemporalDestino);
		// Iterar sobre cada archivo
		foreach ($archivos as $archivo) {
			// Ignorar los archivos . y ..
			if ($archivo == '.' || $archivo == '..') {
				continue;
			}

			// Ruta completa del archivo
			$rutaArchivo = $carpetaTemporalDestino . '/' . $archivo;

			// Eliminar el archivo
			if (is_file($rutaArchivo)) {
				unlink($rutaArchivo);
			}
		}
	} else {
?>
		<div class="alert alert-danger" role="alert">
			<b>No se han seleccionado archivos xml. <br></b>
		</div>
		<?php
	}
}


//boton de cargar archivo con claves de accesso para varias documentos
if ($action == 'archivo_documentos_electronicos') {
	foreach ($_FILES["archivo"]['tmp_name'] as $key => $tmp_name) {
		if ($_FILES["archivo"]["name"][$key]) {
			$filename = $_FILES["archivo"]["name"][$key]; //Obtenemos el nombre original del archivo
			$source = $_FILES["archivo"]["tmp_name"][$key]; //Obtenemos un nombre temporal del archivo
			$directorio = '../docs_temp/'; //Declaramos un  variable con la ruta donde guardaremos los archivos
			//Validamos si la ruta de destino existe, en caso de no existir la creamos
			if (!file_exists($directorio)) {
				mkdir($directorio, 0777) or die("No se puede crear el directorio de extracci&oacute;n");
			}

			//para obtener el tipo de archivo
			$target_dir = "../docs_temp/";
			$archivo_name = time() . "_" . basename($_FILES["archivo"]["name"][$key]);
			$target_file = $target_dir . $archivo_name;
			$imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);

			if ($imageFileType == "txt") {
				$dir = opendir($directorio); //Abrimos el directorio de destino
				$target_path = $directorio . '/documentos.txt'; //Indicamos la ruta de destino, así como el nombre del archivo
				//Movemos y validamos que el archivo se haya cargado correctamente
				//El primer campo es el origen y el segundo el destino
				if (move_uploaded_file($source, $target_path)) {
					$dir_documento = "../docs_temp/documentos.txt";
					$lineas = file($dir_documento);
					// Seleccionar todas las líneas del array desde la segunda
					$lineas = array_slice($lineas, 1);

					// Recorrer el array de líneas y mostrar su contenido
					$array_datos = array();
					foreach ($lineas as $linea) {
						$columna = explode("\t", $linea);
						$array_datos[] = array('tipo' => 'varias_claves', 'clave_acceso' => $columna[4], 'ruc_emisor' => $columna[0], 'ruc_receptor' => $columna[7]);
					}
					$claves_a_consultar = claves_por_consultar($con, $ruc_empresa, $array_datos);
					$registrar_documentos = registrar_documentos($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con);
					echo $registrar_documentos;
				} else {
		?>
					<div class="alert alert-danger" role="alert">
						<b>Ha ocurrido un error, por favor inténtelo de nuevo. <br></b>
					</div>
				<?php
				}
				closedir($dir); //Cerramos el directorio de destino
			} else {
				?>
				<div class="alert alert-danger" role="alert">
					<b>El archivo <?php echo $filename; ?> no es txt, por lo tanto no se procesó. <br></b>
				</div>
			<?php
			}
		} else {
			?>
			<div class="alert alert-danger" role="alert">
				<b>Seleccione el archivo txt descargado del SRI para cargar los documentos.<br></b>
			</div>
		<?php
		}
	}
}

//boton de cargar una clave de accesso 
if ($action == 'clave_documento_individual') {
	if (empty($_GET['clave_acceso'])) {
		?>
		<div class="alert alert-danger" role="alert">
			<b>Ingrese una clave de acceso. <br></b>
		</div>
		<?php
	} else if (!empty($_GET['clave_acceso'])) {
		// escaping, additionally removing everything that could be (html/javascript-) code
		$clave_acceso = mysqli_real_escape_string($con, (strip_tags($_GET["clave_acceso"], ENT_QUOTES)));
		//$rides_sri = new rides_sri();
		$object_xml = $rides_sri->lee_ride($clave_acceso);
		if ($object_xml) {
			$tipo_documento = $object_xml->infoTributaria->codDoc;
			$ruc_emisor = substr($object_xml->infoTributaria->ruc, 0, 10) . "001";

			switch ($tipo_documento) {
				case "01":
					$ruc_comprador = substr($object_xml->infoFactura->identificacionComprador, 0, 10) . "001";
					break;
				case "03":
					$ruc_comprador = $object_xml->infoLiquidacionCompra->identificacionProveedor;
					break;
				case "04":
					$ruc_comprador = substr($object_xml->infoNotaCredito->identificacionComprador, 0, 10) . "001";
					break;
				case "05":
					$ruc_comprador = substr($object_xml->infoNotaDebito->identificacionComprador, 0, 10) . "001";
					break;
				case "07":
					$ruc_comprador = substr($object_xml->infoCompRetencion->identificacionSujetoRetenido, 0, 10) . "001";
					break;
			}

			$array_datos[] = array('tipo' => 'clave_indivudual', 'clave_acceso' => $clave_acceso, 'ruc_emisor' => $ruc_emisor, 'ruc_receptor' => $ruc_comprador);
			$claves_a_consultar = claves_por_consultar($con, $ruc_empresa, $array_datos);
			$registrar_documentos = registrar_documentos($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con);
			echo $registrar_documentos;
		} else {
		?>
			<div class="alert alert-danger" role="alert">
				<b>No hay respuesta del SRI para el documento solicitado, intente de nuevo.</b>
			</div>
		<?php
		}
	}
}

function claves_por_consultar($con, $ruc_empresa, $array_datos)
{
	$claves_a_consultar = array();
	foreach ($array_datos as $value) {
		$busca_clave_acceso_compras = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and aut_sri='" . $value['clave_acceso'] . "'");
		$contador_clave_acceso_compra = mysqli_num_rows($busca_clave_acceso_compras);

		if ($contador_clave_acceso_compra == 0) {
			$busca_clave_acceso_rv = mysqli_query($con, "SELECT * FROM encabezado_retencion_venta WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and aut_sri='" . $value['clave_acceso'] . "'");
			$contador_clave_acceso_rv = mysqli_num_rows($busca_clave_acceso_rv);

			if ($contador_clave_acceso_rv == 0) {
				$busca_clave_acceso_venta = mysqli_query($con, "SELECT * FROM encabezado_factura WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and aut_sri='" . $value['clave_acceso'] . "'");
				$contador_clave_acceso_venta = mysqli_num_rows($busca_clave_acceso_venta);
				//esto es para cuando mi empresa emite una factura de venta y la quiero registrar como compra de mi misma empresa
				if ($contador_clave_acceso_venta > 0 && ($value['ruc_emisor'] == $value['ruc_receptor']) && (substr($ruc_empresa, 0, 10) == substr($value['ruc_receptor'], 0, 10))) {
					$contador_clave_acceso_venta = 0;
				}
				if ($contador_clave_acceso_venta == 0) {
					$busca_clave_acceso_rc = mysqli_query($con, "SELECT * FROM encabezado_retencion WHERE mid(ruc_empresa,1,10) = '" . substr($ruc_empresa, 0, 10) . "' and aut_sri='" . $value['clave_acceso'] . "'");
					$contador_clave_acceso_rc = mysqli_num_rows($busca_clave_acceso_rc);
					if ($contador_clave_acceso_rc == 0) {
						$claves_a_consultar[] = $value['clave_acceso'];
					}
				}
			}
		}
	}
	return $claves_a_consultar;
}

function registrar_documentos($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con)
{

	if (empty($claves_a_consultar)) {
		?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
			<b>Los documentos que intenta guardar, ya se encuentran registrados en el sistema.</b>
		</div>
	<?php
	} else {
		$rides_sri = new rides_sri();
		//para contar las filas del archivo
		if (is_array($array_datos)) {
			$total_claves = count($array_datos);
		}

		$claves_seleccionadas = count($claves_a_consultar);

		if ($claves_seleccionadas > 100) {
			$claves_a_consultar = array_slice($claves_a_consultar, 0, 100);
		} else {
			$claves_a_consultar = $claves_a_consultar;
		}

		$procesados_ahora = 0;
		$documento_resgistrado = array();
		$documento_no_resgistrado = array();
		if ($array_datos['0']['tipo'] == "archivo_xml") { //para cuando se sube en archivos xml desde una carpeta
			foreach ($array_datos as $archivo_xml) {
				$object_xml = $rides_sri->lee_xml($archivo_xml['ruta_archivo']);
				if (!empty($object_xml)) {
					$documento_resgistrado[] = $rides_sri->lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con);
				} else {
					$documento_no_resgistrado[] = $archivo_xml['ruta_archivo'];
				}
			}
		} else {
			foreach ($claves_a_consultar as $clave) {
				$object_xml = $rides_sri->lee_ride($clave);
				if (!empty($object_xml)) {
					$documento_resgistrado[] = $rides_sri->lee_archivo_xml($object_xml, $ruc_empresa, $id_usuario, $con);
				} else {
					$documento_no_resgistrado[] = $clave;
				}
			}
		}

		//para contar los documentos que se registraron
		foreach ($documento_resgistrado as $clave) {
			if ($clave['estado'] == '1') {
				$procesados_ahora += 1;
			} else {
				$procesados_ahora = 0;
			}
		}

	?>
		<div style="margin-bottom: -5px;" class="alert alert-info" role="alert">
			<b> Documentos en el archivo o carpeta: <?php echo $total_claves; ?></b>
			<br>
			<b> Documentos registrados ahora: <?php echo $procesados_ahora; ?></b>
			<br>
			<b> Documentos ya registrados anteriormente: <?php echo ($total_claves - $claves_seleccionadas); ?></b>
		</div>
		<br>
		<?php
		if (($total_claves - $claves_seleccionadas - $procesados_ahora) > 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<b>Vuelva a cargar este archivo, quedan pendientes <?php echo $claves_seleccionadas - $procesados_ahora; ?> documentos por registrar</b>
			</div>
		<?php
		}
		?>
		<br>
		<?php
		foreach ($documento_resgistrado as $message) {
			if ($message['estado'] == '0') {
		?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
			if ($message['estado'] == '1') {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
			if ($message['estado'] == '2') {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-warning" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
		}

		if (count($documento_no_resgistrado) > 0) {
			foreach ($documento_no_resgistrado as $message) {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -5px;" class="alert alert-danger" role="alert">
					<b><?php echo "No hay respuesta del SRI para el documento " . $message; ?><br></b>
				</div>
		<?php
			}
		}
	}
}

//para guardar las claves de acceso para no descargar
if ($action == 'clave_acceso_no_descargar') {
	if (empty($_GET['clave_acceso'])) {
		?>
		<div class="alert alert-danger" role="alert">
			<b>Ingrese una clave de acceso. <br></b>
		</div>
	<?php
	} else {
		$clave_acceso = mysqli_real_escape_string($con, (strip_tags($_GET["clave_acceso"], ENT_QUOTES)));
		$busca_clave = mysqli_query($con, "SELECT * FROM claves_sri_no_descargar WHERE clave_acceso = '" . $clave_acceso . "' ");
		$count = mysqli_num_rows($busca_clave);
		if ($count > 0) {
			echo "<script>
	$.notify('Clave de acceso ya registrada','error');
	</script>";
		} else {
			$query_guarda = mysqli_query($con, "INSERT INTO claves_sri_no_descargar (ruc_empresa, clave_acceso) VALUES ('" . $ruc_empresa . "', '" . $clave_acceso . "')");
		}
	}
}

//mostrar claves para no descargar
if ($action == 'mostrar_clave_acceso_no_descargar') {
	$query_muestra = mysqli_query($con, "SELECT * FROM claves_sri_no_descargar WHERE ruc_empresa= '" . $ruc_empresa . "'");

	?>
	<div class="table-responsive">
		<div class="panel panel-info">
			<table class="table table-hover">
				<tr class="info">
					<th style="padding: 0px;">Fecha</th>
					<th style="padding: 0px;">Ruc Proveedor/Cliente</th>
					<th style="padding: 0px;">Clave de acceso</th>
					<th class='text-right'>Eliminar</th>
				</tr>
				<?php

				while ($row = mysqli_fetch_array($query_muestra)) {
					$id_clave = $row['id'];
					$cliente = substr($row['clave_acceso'], 10, 13);
					$fecha = substr($row['clave_acceso'], 0, 2) . "-" . substr($row['clave_acceso'], 2, 2) . "-" . substr($row['clave_acceso'], 4, 4);
					$clave_acceso = $row['clave_acceso'];
				?>
					<tr>
						<td><?php echo $fecha; ?></td>
						<td><?php echo $cliente; ?></td>
						<td><?php echo $clave_acceso; ?></td>
						<td class='text-right'>
							<a class='btn btn-danger btn-xs' title='Eliminar' onclick="eliminar_clave('<?php echo $id_clave; ?>');"><i class="glyphicon glyphicon-trash"></i></a>
						</td>
					</tr>
				<?php
				}
				?>
			</table>
		</div>
	</div>
	<?php
}

//para eliminar las claves de acceso para no descargar
if ($action == 'eliminar_clave_acceso_no_descargar') {
	$id_clave = mysqli_real_escape_string($con, (strip_tags($_GET["id_clave"], ENT_QUOTES)));
	$query_delete = mysqli_query($con, "DELETE FROM claves_sri_no_descargar WHERE id='" . $id_clave . "'");
}

if ($action == 'archivo_xml_notaria') {
	$carpetaTemporalDestino = '../docs_temp/carpetaXml';
	if (isset($_FILES['carpetaXml']) && !empty($_FILES['carpetaXml']['tmp_name'])) {
		// Iterar sobre cada archivo
		foreach ($_FILES['carpetaXml']['tmp_name'] as $index => $rutaArchivoTemporal) {
			$nombreArchivo = $_FILES['carpetaXml']['name'][$index];
			$rutaDestino = $carpetaTemporalDestino . '/' . $nombreArchivo;
			// Mover el archivo a la carpeta temporal de destino
			move_uploaded_file($rutaArchivoTemporal, $rutaDestino);
		}

		$archivos = scandir($carpetaTemporalDestino);
		$contador = 1;

		foreach ($archivos as $archivo) {
			// Ignorar los archivos . y ..
			if ($archivo === '.' || $archivo === '..') {
				continue;
			}
			// Ruta completa del archivo original
			$rutaArchivoOriginal = $carpetaTemporalDestino . DIRECTORY_SEPARATOR . $archivo;
			// Asegurarse de que sea un archivo válido y no un directorio
			if (!is_file($rutaArchivoOriginal)) {
				continue;
			}
			// Obtener la extensión del archivo
			$extension = pathinfo($archivo, PATHINFO_EXTENSION);
			// Construir el nuevo nombre con número secuencial
			do {
				$nuevoNombreArchivo = $carpetaTemporalDestino . DIRECTORY_SEPARATOR . $contador . '.' . $extension;
				$contador++;
			} while (file_exists($nuevoNombreArchivo)); // Evitar sobrescribir archivos existentes

			// Renombrar el archivo
			if (!rename($rutaArchivoOriginal, $nuevoNombreArchivo)) {
				echo "Error al renombrar el archivo: $rutaArchivoOriginal\n";
			}
		}
		// Obtener los nuevos archivos en la carpeta temporal
		$archivos_nuevos = array_values(array_diff(scandir($carpetaTemporalDestino), array('.', '..')));


		foreach ($archivos_nuevos as $archivo_nuevo) {
			$rutaArchivo = $carpetaTemporalDestino . '/' . $archivo_nuevo;

			$FileType = pathinfo($rutaArchivo, PATHINFO_EXTENSION);

			if ($FileType == "xml") {
				$object_xml = $rides_sri->lee_xml_notaria($rutaArchivo);
				$clave_acceso = $object_xml->infoTributaria->claveAcceso;
				$tipo_documento = $object_xml->infoTributaria->codDoc;
				$ruc_emisor = substr($object_xml->infoTributaria->ruc, 0, 10) . "001";

				switch ($tipo_documento) {
					case "01":
						$ruc_comprador = substr($object_xml->infoFactura->identificacionComprador, 0, 10) . "001";
						break;
					case "03":
						$ruc_comprador = $object_xml->infoLiquidacionCompra->identificacionProveedor;
						break;
					case "04":
						$ruc_comprador = substr($object_xml->infoNotaCredito->identificacionComprador, 0, 10) . "001";
						break;
					case "05":
						$ruc_comprador = substr($object_xml->infoNotaDebito->identificacionComprador, 0, 10) . "001";
						break;
					case "07":
						$ruc_comprador = substr($object_xml->infoCompRetencion->identificacionSujetoRetenido, 0, 10) . "001";
						break;
				}
				$array_datos[] = array('tipo' => 'archivo_xml', 'ruta_archivo' => $rutaArchivo, 'clave_acceso' => $clave_acceso, 'ruc_emisor' => $ruc_emisor, 'ruc_receptor' => $ruc_comprador);
			}
		}
		$claves_a_consultar = claves_por_consultar($con, $ruc_empresa, $array_datos);
		$registrar_documentos = registrar_documentos_notaria($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con);
		echo $registrar_documentos;

		$archivos = scandir($carpetaTemporalDestino);
		// Iterar sobre cada archivo
		foreach ($archivos as $archivo) {
			// Ignorar los archivos . y ..
			if ($archivo == '.' || $archivo == '..') {
				continue;
			}

			// Ruta completa del archivo
			$rutaArchivo = $carpetaTemporalDestino . '/' . $archivo;

			// Eliminar el archivo
			if (is_file($rutaArchivo)) {
				unlink($rutaArchivo);
			}
		}
	} else {
	?>
		<div class="alert alert-danger" role="alert">
			<b>No se han seleccionado archivos xml. <br></b>
		</div>
	<?php
	}
}


function registrar_documentos_notaria($array_datos, $claves_a_consultar, $ruc_empresa, $id_usuario, $con)
{

	if (empty($claves_a_consultar)) {
	?>
		<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
			<b>Los documentos que intenta guardar, ya se encuentran registrados en el sistema.</b>
		</div>
	<?php
	} else {
		$rides_sri = new rides_sri();
		//para contar las filas del archivo
		if (is_array($array_datos)) {
			$total_claves = count($array_datos);
		}

		$claves_seleccionadas = count($claves_a_consultar);

		if ($claves_seleccionadas > 100) {
			$claves_a_consultar = array_slice($claves_a_consultar, 0, 100);
		} else {
			$claves_a_consultar = $claves_a_consultar;
		}

		$procesados_ahora = 0;
		$documento_resgistrado = array();
		$documento_no_resgistrado = array();
		if ($array_datos['0']['tipo'] == "archivo_xml") { //para cuando se sube en archivos xml desde una carpeta
			foreach ($array_datos as $archivo_xml) {
				$object_xml = $rides_sri->lee_xml_notaria($archivo_xml['ruta_archivo']);
				if (!empty($object_xml)) {
					$documento_resgistrado[] = $rides_sri->lee_archivo_xml_notaria($object_xml, $ruc_empresa, $id_usuario, $con);
				} else {
					$documento_no_resgistrado[] = $archivo_xml['ruta_archivo'];
				}
			}
		}
		//para contar los documentos que se registraron
		foreach ($documento_resgistrado as $clave) {
			if ($clave['estado'] == '1') {
				$procesados_ahora += 1;
			} else {
				$procesados_ahora = 0;
			}
		}

	?>
		<div style="margin-bottom: -5px;" class="alert alert-info" role="alert">
			<b> Documentos en el archivo o carpeta: <?php echo $total_claves; ?></b>
			<br>
			<b> Documentos registrados ahora: <?php echo $procesados_ahora; ?></b>
			<br>
			<b> Documentos ya registrados anteriormente: <?php echo ($total_claves - $claves_seleccionadas); ?></b>
		</div>
		<br>
		<?php
		if (($total_claves - $claves_seleccionadas - $procesados_ahora) > 0) {
		?>
			<div class="alert alert-danger" role="alert">
				<b>Vuelva a cargar este archivo, quedan pendientes <?php echo $claves_seleccionadas - $procesados_ahora; ?> documentos por registrar</b>
			</div>
		<?php
		}
		?>
		<br>
		<?php
		foreach ($documento_resgistrado as $message) {
			if ($message['estado'] == '0') {
		?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-danger" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
			if ($message['estado'] == '1') {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-success" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
			if ($message['estado'] == '2') {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -10px;" class="alert alert-warning" role="alert">
					<b><?php echo $message['documento'] . " " . $message['numero'] . " de " . strtoupper($message['nombre']) . " " . $message['mensaje']; ?><br></b>
				</div>
			<?php
			}
		}

		if (count($documento_no_resgistrado) > 0) {
			foreach ($documento_no_resgistrado as $message) {
			?>
				<div style="padding: 2px; margin-bottom: 5px; margin-top: -5px;" class="alert alert-danger" role="alert">
					<b><?php echo "No hay respuesta del SRI para el documento " . $message; ?><br></b>
				</div>
<?php
			}
		}
	}
}

?>