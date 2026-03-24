<?php
require_once '../vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;

include("../conexiones/conectalogin.php");
require_once("../helpers/helpers.php");
$con = conenta_login();
session_start();
$id_empresa = $_SESSION['id_empresa'];
$ruc_empresa = $_SESSION['ruc_empresa'];
$id_usuario = $_SESSION['id_usuario'];
ini_set('date.timezone', 'America/Guayaquil');
setlocale(LC_ALL, "es_ES");

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';

/* if ($action == "descarga_documentos") {
	$archivo = base64_decode($_GET['archivo']);
	$nombre = $archivo; //basename($archivo);
	header('Content-Type: application/octet-stream');
	header("Content-Transfer-Encoding: Binary");
	header("Content-disposition: attachment; filename=$nombre");
	readfile($archivo);
} */

if ($action == "descarga_documentos") {
	// 1) Decodifica y valida
	$archivo = isset($_GET['archivo']) ? base64_decode($_GET['archivo']) : '';
	if (!$archivo || !is_file($archivo) || !is_readable($archivo)) {
		http_response_code(404);
		exit('Archivo no encontrado');
	}

	// 2) Nombre visible (¡clave!)
	$nombre = basename($archivo); // p.ej. FAC001-002-000003697.xml
	// Sanitiza por seguridad de cabeceras (evita inyección de saltos de línea, etc.)
	$nombre_seguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);

	// 3) Tipo de contenido según extensión
	$ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
	switch ($ext) {
		case 'xml':
			$ctype = 'application/xml; charset=UTF-8';
			break;
		case 'pdf':
			$ctype = 'application/pdf';
			break;
		case 'zip':
			$ctype = 'application/zip';
			break;
		case 'json':
			$ctype = 'application/json; charset=UTF-8';
			break;
		default:
			$ctype = 'application/octet-stream';
	}

	// 4) Si tu XML aún no viene envuelto y quieres envolver "al vuelo", descomenta esto:
	/*
    if ($ext === 'xml') {
        $contenido = file_get_contents($archivo);
        if ($contenido !== false && strpos($contenido, '<autorizacion>') === false) {
            // Usa tu helper PHP 5.6 que ya te pasé:
            $contenido = $this->envolver_xml_autorizacion_contenido($contenido);
            if (ob_get_length()) { @ob_end_clean(); }
            header('Content-Type: ' . $ctype);
            header("Content-Transfer-Encoding: binary");
            // Content-Disposition con RFC5987 para UTF-8
            header('Content-Disposition: attachment; filename="' . $nombre_seguro . '"; filename*=UTF-8\'\'' . rawurlencode($nombre_seguro));
            header('Content-Length: ' . strlen($contenido));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $contenido;
            exit;
        }
    }
    */

	// 5) Descargar desde disco tal cual
	if (ob_get_length()) {
		@ob_end_clean();
	}
	header('Content-Type: ' . $ctype);
	header("Content-Transfer-Encoding: binary");
	header('Content-Disposition: attachment; filename="' . $nombre_seguro . '"; filename*=UTF-8\'\'' . rawurlencode($nombre_seguro));
	header('Content-Length: ' . filesize($archivo));
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');
	readfile($archivo);
	exit;
}



/* if ((isset($_GET['id_documento'])) && (isset($_GET['tipo_documento'])) && (isset($_GET['tipo_archivo']))) {
	if (empty($_GET['id_documento'])) {
		$errors[] = "Seleccione un documento electrónico para imprimir";
	} else if (empty($_GET['tipo_documento'])) {
		$errors[] = "Seleccione un documento electrónico para imprimir";
	} else if (empty($_GET['tipo_archivo'])) {
		$errors[] = "Seleccione un documento electrónico para imprimir";
	} else if (!empty($_GET['id_documento']) && (!empty($_GET['tipo_documento'])) && (!empty($_GET['tipo_archivo']))) {

		//$id_documento=$_GET['id_documento'];
		$id_documento = base64_decode($_GET['id_documento']);
		$tipo_documento = $_GET['tipo_documento'];
		$extension_archivo = "." . $_GET['tipo_archivo'];

		$imprime_documentos = new imprime_documentos();
		$archivo_a_imprimir = $imprime_documentos->descarga_documento($id_documento, $tipo_documento, $extension_archivo);

		header("Content-disposition: attachment; filename=$archivo_a_imprimir");
		header("Content-type: application/$extension_archivo");
		readfile($archivo_a_imprimir);
		unlink($archivo_a_imprimir);
	}
} */

if ((isset($_GET['id_documento'])) && (isset($_GET['tipo_documento'])) && (isset($_GET['tipo_archivo']))) {
	if (empty($_GET['id_documento']) || empty($_GET['tipo_documento']) || empty($_GET['tipo_archivo'])) {
		http_response_code(400);
		exit('Faltan parámetros');
	}

	$id_documento    = base64_decode($_GET['id_documento']);
	$tipo_documento  = $_GET['tipo_documento'];
	$tipo_archivo    = strtolower(trim($_GET['tipo_archivo'])); // 'xml' | 'pdf' | etc.
	$extension_archivo = '.' . $tipo_archivo;

	$imprime_documentos = new imprime_documentos();
	$archivo_a_imprimir = $imprime_documentos->descarga_documento($id_documento, $tipo_documento, $extension_archivo);

	// ¿existe y es legible?
	if (!$archivo_a_imprimir || !is_file($archivo_a_imprimir) || !is_readable($archivo_a_imprimir)) {
		http_response_code(404);
		exit('Archivo no encontrado');
	}

	// Nombre visible correcto (¡clave!)
	$nombre = basename($archivo_a_imprimir);                     // ej: FAC001-002-000003697.xml
	$nombre_seguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre);

	// Tipo MIME correcto
	switch ($tipo_archivo) {
		case 'xml':
			$ctype = 'application/xml; charset=UTF-8';
			break;
		case 'pdf':
			$ctype = 'application/pdf';
			break;
		case 'zip':
			$ctype = 'application/zip';
			break;
		case 'json':
			$ctype = 'application/json; charset=UTF-8';
			break;
		default:
			$ctype = 'application/octet-stream';
	}

	// OPCIONAL: envolver "al vuelo" si aún no viene con <autorizacion>
	// (solo si tienes el helper dentro de la clase)
	if ($tipo_archivo === 'xml' && method_exists($imprime_documentos, 'envolver_xml_autorizacion_contenido')) {
		$contenido = @file_get_contents($archivo_a_imprimir);
		if ($contenido !== false && strpos($contenido, '<autorizacion>') === false) {
			$contenido = $imprime_documentos->envolver_xml_autorizacion_contenido($contenido);
			@file_put_contents($archivo_a_imprimir, $contenido);
			clearstatcache(true, $archivo_a_imprimir);
		}
	}

	// Evita cualquier salida previa a los headers
	if (ob_get_length()) {
		@ob_end_clean();
	}

	header('Content-Type: ' . $ctype);
	header('Content-Transfer-Encoding: binary');
	// Forzamos el nombre correcto (RFC 5987 para UTF-8)
	header('Content-Disposition: attachment; filename="' . $nombre_seguro . '"; filename*=UTF-8\'\'' . rawurlencode($nombre_seguro));
	header('Content-Length: ' . filesize($archivo_a_imprimir));
	header('Cache-Control: no-store, no-cache, must-revalidate');
	header('Pragma: no-cache');
	header('Expires: 0');

	readfile($archivo_a_imprimir);
	// Si es un temporal, lo puedes borrar:
	@unlink($archivo_a_imprimir);
	exit;
}


if ($action === 'descargar_varios_documentos') {
	$documentos    = isset($_GET['documentos']) ? $_GET['documentos'] : '';
	$tipo_documento = isset($_GET['tipo_documento']) ? $_GET['tipo_documento'] : 'documentos';
	$tipo_formato  = isset($_GET['tipo_formato']) ? $_GET['tipo_formato'] : '1';
	$imp = new imprime_documentos();
	$imp->descargar_documentos_zip($documentos, $tipo_documento, $tipo_formato);
	exit; // importantísimo
}


/* if ($action === "descargar_varios_documentos") {
	// —————————— CONFIG & SEGURIDAD ——————————
	ignore_user_abort(true);
	@set_time_limit(0);
	@ini_set('zlib.output_compression', 'Off');

	// (Activa temporalmente mientras depuras)
	// error_reporting(E_ALL);
	// ini_set('display_errors', 1);

	if (!class_exists('ZipArchive')) {
		header('Content-Type: text/plain; charset=utf-8');
		http_response_code(500);
		echo "Error: La extensión ZipArchive no está habilitada.";
		exit;
	}

	// Lee parámetros (compatibles con PHP 5.6)
	$raw_documentos = isset($_GET['documentos']) ? $_GET['documentos'] : '';
	$tipo_documento_in = isset($_GET['tipo_documento']) ? $_GET['tipo_documento'] : 'documentos';
	$tipo_documento = preg_replace('/[^A-Za-z0-9_\-]/', '', $tipo_documento_in);
	if ($tipo_documento === '') {
		$tipo_documento = 'documentos';
	}
	$tipo_formato = isset($_GET['tipo_formato']) ? (string)$_GET['tipo_formato'] : '1';

	// Normaliza y limpia IDs
	$docs = explode(',', $raw_documentos);
	$documentos = array();
	foreach ($docs as $v) {
		$v = trim($v);
		if ($v !== '' && !in_array($v, $documentos, true)) {
			$documentos[] = $v;
		}
	}

	// Determina extensiones a incluir
	$exts = array('.pdf'); // fallback
	if ($tipo_formato === '1') {
		$exts = array('.pdf');
	} elseif ($tipo_formato === '2') {
		$exts = array('.xml');
	} elseif ($tipo_formato === '3') {
		$exts = array('.pdf', '.xml');
	}

	// —————————— GENERA ARCHIVOS A INCLUIR ——————————
	$imprime_documentos = new imprime_documentos();

	$pathsParaBorrar = array();
	$errores = array();

	// Crea ZIP en archivo temporal (compatible 5.6)
	$zipNombreDescarga = $tipo_documento . '_CaMaGaRe_' . date('Ymd_His') . '.zip';
	$zipPathTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
	$zipPathTemp = $zipPathTempDir . DIRECTORY_SEPARATOR . $zipNombreDescarga;
	if (file_exists($zipPathTemp)) {
		@unlink($zipPathTemp);
	}

	$zip = new ZipArchive();
	$openResult = $zip->open($zipPathTemp, ZipArchive::CREATE); // sin OVERWRITE en 5.6
	if ($openResult !== true) {
		header('Content-Type: text/plain; charset=utf-8');
		http_response_code(500);
		echo "No se pudo crear el archivo ZIP. Código: " . $openResult;
		exit;
	}

	// —— Todos los archivos en UNA sola carpeta dentro del ZIP ——
	// Cambia a '' si quieres que vayan al raíz del ZIP
	$carpetaUnica = $tipo_documento;

	// Control de nombres duplicados
	$usados = array();     // mapa: nombre dentro del ZIP => true
	$contadores = array(); // por basename: para _2, _3, ...

	foreach ($documentos as $id_documento) {
		foreach ($exts as $ext) {
			// Debe devolver una ruta física existente
			$ruta = $imprime_documentos->descarga_documento($id_documento, $tipo_documento, $ext);

			if (is_string($ruta) && $ruta !== '' && file_exists($ruta)) {
				$base = basename($ruta);
				$target = $base;
				$dotPos = strrpos($base, '.');
				$nombreSinExt = ($dotPos !== false) ? substr($base, 0, $dotPos) : $base;
				$extReal = ($dotPos !== false) ? substr($base, $dotPos) : '';

				$prefijo = ($carpetaUnica !== '') ? $carpetaUnica . '/' : '';
				$localName = $prefijo . $target;

				// Evitar colisiones: archivo.pdf -> archivo_2.pdf, archivo_3.pdf, ...
				while ((isset($usados[$localName]) && $usados[$localName] === true) || $zip->locateName($localName) !== false) {
					if (!isset($contadores[$base])) {
						$contadores[$base] = 2;
					} else {
						$contadores[$base]++;
					}
					$target = $nombreSinExt . '_' . $contadores[$base] . $extReal;
					$localName = $prefijo . $target;
				}

				if (!$zip->addFile($ruta, $localName)) {
					$errores[] = "No se pudo añadir: " . $localName;
				} else {
					$usados[$localName] = true;
					$pathsParaBorrar[] = $ruta;
				}
			} else {
				$errores[] = "Archivo no encontrado/generado: " . $id_documento . " (" . $ext . ")";
			}
		}
	}

	// Agrega TXT con errores (si hubo) para no romper la descarga
	if (!empty($errores)) {
		$contenidoErrores = "Durante la generación se encontraron estos problemas:\n\n" . implode("\n", $errores) . "\n";
		$zip->addFromString('LEEME_ERRORES.txt', $contenidoErrores);
	}

	$zip->setArchiveComment("Generado por CaMaGaRe - " . date('Y-m-d H:i:s'));
	$zip->close();

	// —————————— DESCARGA ——————————
	// Asegura que no se hayan enviado headers/salida previa
	if (function_exists('ob_get_level')) {
		while (ob_get_level() > 0) {
			@ob_end_clean();
		}
	}

	if (!file_exists($zipPathTemp)) {
		header('Content-Type: text/plain; charset=utf-8');
		http_response_code(500);
		echo "No se encontró el ZIP temporal para descarga.";
		exit;
	}

	header('Content-Type: application/zip');
	header('Content-Disposition: attachment; filename="' . $zipNombreDescarga . '"');
	header('Content-Length: ' . filesize($zipPathTemp));
	header('X-Content-Type-Options: nosniff');

	// Enviar en chunks (menos memoria)
	$fp = fopen($zipPathTemp, 'rb');
	if ($fp) {
		while (!feof($fp)) {
			echo fread($fp, 1048576); // 1 MB
			flush();
		}
		fclose($fp);
	} else {
		// Fallback
		readfile($zipPathTemp);
	}

	// —————————— LIMPIEZA ——————————
	@unlink($zipPathTemp);
	foreach ($pathsParaBorrar as $p) {
		@unlink($p);
	}

	exit;
} */




//clase para descargar archivos
class imprime_documentos
{

	// Dentro de la clase imprime_documentos (PHP 5.6)
	public function descargar_documentos_zip($documentos_input, $tipo_documento, $tipo_formato)
	{
		// ——— Configuración general ———
		ignore_user_abort(true);
		@set_time_limit(0);
		@ini_set('zlib.output_compression', 'Off');

		if (!class_exists('ZipArchive')) {
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(500);
			echo 'Error: La extensión ZipArchive no está habilitada.';
			return;
		}

		// ——— Normaliza lista de documentos (CSV o array) ———
		$documentos = array();
		if (is_array($documentos_input)) {
			foreach ($documentos_input as $v) {
				$v = trim((string)$v);
				if ($v !== '' && !in_array($v, $documentos, true)) {
					$documentos[] = $v;
				}
			}
		} else {
			$parts = explode(',', (string)$documentos_input);
			foreach ($parts as $v) {
				$v = trim($v);
				if ($v !== '' && !in_array($v, $documentos, true)) {
					$documentos[] = $v;
				}
			}
		}
		if (empty($documentos)) {
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(400);
			echo 'No se recibieron documentos para descargar.';
			return;
		}

		// ——— Formatos a incluir ———
		$exts = array('.pdf'); // fallback
		if ((string)$tipo_formato === '1') {
			$exts = array('.pdf');
		} elseif ((string)$tipo_formato === '2') {
			$exts = array('.xml');
		} elseif ((string)$tipo_formato === '3') {
			$exts = array('.pdf', '.xml');
		}

		// ——— Conexión FTP (una sola vez) ———
		$ftp_server = '64.225.69.65';
		$ftp_user   = 'char';
		$ftp_pass   = 'CmGr1980';

		$conn = @ftp_connect($ftp_server);
		if (!$conn) {
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(500);
			echo 'Error: no se pudo conectar al servidor FTP.';
			return;
		}
		if (!@ftp_login($conn, $ftp_user, $ftp_pass)) {
			@ftp_close($conn);
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(401);
			echo 'Error: credenciales FTP inválidas.';
			return;
		}
		@ftp_pasv($conn, true);
		if (defined('FTP_TIMEOUT_SEC')) {
			@ftp_set_option($conn, FTP_TIMEOUT_SEC, 10);
		}

		// ——— Prepara ZIP temporal ———
		$zipNombreDescarga = $tipo_documento . '_CaMaGaRe_' . date('Ymd_His') . '.zip';
		$zipPathTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $zipNombreDescarga;
		if (file_exists($zipPathTemp)) {
			@unlink($zipPathTemp);
		}

		$zip = new ZipArchive();
		$openResult = $zip->open($zipPathTemp, ZipArchive::CREATE);
		if ($openResult !== true) {
			@ftp_close($conn);
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(500);
			echo 'No se pudo crear el archivo ZIP. Código: ' . $openResult;
			return;
		}

		// ——— Carpeta única dentro del ZIP (cambia a '' para raíz) ———
		$carpetaUnicaZip = $tipo_documento;
		$prefijo = ($carpetaUnicaZip !== '') ? rtrim($carpetaUnicaZip, '/') . '/' : '';

		$usados  = array(); // nombres ya usados en el ZIP
		$errores = array();

		// ——— Recorre cada documento ———
		foreach ($documentos as $id_documento) {
			// Debes implementar este método para mapear ID → metadata (o adapta aquí si ya la tienes)
			// Debe retornar: nombre_carpeta, ruc_empresa, ruc_cli_prov (opcional), abreviatura, serie ('001-001'), secuencial
			$info = $this->resolver_info_documento($id_documento, $tipo_documento);
			if (!is_array($info) || !isset($info['nombre_carpeta'], $info['ruc_empresa'], $info['abreviatura'], $info['serie'], $info['secuencial'])) {
				$errores[] = 'Sin info del documento: ' . $id_documento;
				continue;
			}

			$nombre_carpeta        = $info['nombre_carpeta'];
			$ruc_empresa           = $info['ruc_empresa'];
			$ruc_cliente_proveedor = isset($info['ruc_cli_prov']) ? $info['ruc_cli_prov'] : '';
			$abreviatura_documento = $info['abreviatura'];
			$serie                 = $info['serie'];
			$secuencial            = $info['secuencial'];

			// ——— Normalizaciones como tu función original ———
			$serie_hyph    = trim($serie);                      // "001-001"
			$serie_compact = str_replace('-', '', $serie_hyph); // "001001"

			$sec_digits = preg_replace('/\D+/', '', (string)$secuencial);
			if ($sec_digits === '') {
				$sec_digits = (string)$secuencial;
			}
			$sec_pad9   = str_pad($sec_digits, 9, '0', STR_PAD_LEFT);

			foreach ($exts as $extDot) {
				$extNorm = strtolower(ltrim($extDot, '.')); // 'pdf' | 'xml'
				$extDot  = '.' . $extNorm;

				// Nombres candidatos remotos (mismo orden de probabilidad que tu función)
				$fname_base             = $abreviatura_documento . $serie_hyph    . '-' . $sec_pad9   . $extDot;
				$fname_base_nodash      = $abreviatura_documento . $serie_compact . '-' . $sec_pad9   . $extDot;
				$fname_base_nopad       = $abreviatura_documento . $serie_hyph    . '-' . $sec_digits . $extDot;
				$fname_with_rucprefix   = $ruc_cliente_proveedor . $fname_base;
				$fname_with_rucprefix_n = $ruc_cliente_proveedor . $fname_base_nodash;

				$base         = '/ftp_documentos/' . $nombre_carpeta . '/' . $ruc_empresa . '/';
				$base_con_ruc = ($ruc_cliente_proveedor !== '') ? ($base . $ruc_cliente_proveedor . '/') : '';

				$candidatos = array();
				if ($base_con_ruc !== '') {
					$candidatos[] = $base_con_ruc . $fname_base;
				}
				$candidatos[] = $base . $fname_base;
				if ($base_con_ruc !== '') {
					$candidatos[] = $base_con_ruc . $fname_base_nodash;
				}
				$candidatos[] = $base . $fname_base_nodash;
				if ($base_con_ruc !== '') {
					$candidatos[] = $base_con_ruc . $fname_base_nopad;
					$candidatos[] = $base_con_ruc . $fname_with_rucprefix;
					$candidatos[] = $base_con_ruc . $fname_with_rucprefix_n;
				}

				// Nombre “bonito” dentro del ZIP (sin RUCs)
				$fname_zip = $abreviatura_documento . $serie_hyph . '-' . $sec_pad9 . $extDot;
				$localName = $prefijo . $fname_zip;

				// Evitar colisiones: _2, _3, ...
				if (isset($usados[$localName]) || $zip->locateName($localName) !== false) {
					$dotPos = strrpos($fname_zip, '.');
					$nombreSinExt = ($dotPos !== false) ? substr($fname_zip, 0, $dotPos) : $fname_zip;
					$extReal      = ($dotPos !== false) ? substr($fname_zip, $dotPos) : '';
					$k = 2;
					do {
						$localName = $prefijo . $nombreSinExt . '_' . $k . $extReal;
						$k++;
					} while (isset($usados[$localName]) || $zip->locateName($localName) !== false);
				}

				// ——— FTP → memoria → ZIP (sin disco) ———
				$maxMem = 52428800; // 50 MB de buffer en memoria antes de volcar a temp
				$agregado = false;

				for ($i = 0, $n = count($candidatos); $i < $n; $i++) {
					$remote = $candidatos[$i];
					$tmp = fopen('php://temp/maxmemory:' . $maxMem, 'w+');
					if ($tmp === false) {
						$errores[] = 'No se pudo abrir stream temporal para: ' . $localName;
						break;
					}

					// Intento directo (evita ftp_size): si no existe, falla rápido con 550
					if (@ftp_fget($conn, $tmp, $remote, FTP_BINARY, 0)) {
						rewind($tmp);
						$data = stream_get_contents($tmp);
						fclose($tmp);

						if ($data === false || $data === '') {
							$errores[] = 'Archivo vacío o ilegible: ' . $remote;
							break;
						}

						// Si es XML y tienes el envoltorio, aplícalo
						if ($extNorm === 'xml' && method_exists($this, 'envolver_xml_autorizacion_contenido')) {
							$processed = $this->envolver_xml_autorizacion_contenido($data);
							if (is_string($processed) && $processed !== '') {
								$data = $processed;
							}
						}

						if ($zip->addFromString($localName, $data)) {
							$usados[$localName] = true;
							$agregado = true;
						} else {
							$errores[] = 'No se pudo añadir al ZIP: ' . $localName;
						}
						break; // sea éxito o fallo al addFromString, no tiene sentido seguir probando más candidatos
					}

					fclose($tmp); // cerrar en fallos
				}

				if (!$agregado) {
					$errores[] = 'No encontrado en FTP: ' . $localName;
				}
			}
		}

		if (!empty($errores)) {
			$contenido = "Durante la generación se encontraron estos problemas:\n\n" . implode("\n", $errores) . "\n";
			$zip->addFromString('LEEME_ERRORES.txt', $contenido);
		}

		$zip->setArchiveComment('Generado por CaMaGaRe - ' . date('Y-m-d H:i:s'));
		$zip->close();
		@ftp_close($conn);

		// ——— Enviar ZIP al navegador ———
		if (function_exists('ob_get_level')) {
			while (ob_get_level() > 0) {
				@ob_end_clean();
			}
		}
		if (!file_exists($zipPathTemp)) {
			header('Content-Type: text/plain; charset=utf-8');
			http_response_code(500);
			echo 'No se encontró el ZIP temporal para descarga.';
			return;
		}

		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $zipNombreDescarga . '"');
		header('Content-Length: ' . filesize($zipPathTemp));
		header('X-Content-Type-Options: nosniff');

		$fp = fopen($zipPathTemp, 'rb');
		if ($fp) {
			while (!feof($fp)) {
				echo fread($fp, 1048576); // 1 MB
				flush();
			}
			fclose($fp);
		} else {
			readfile($zipPathTemp);
		}

		@unlink($zipPathTemp);
		// No return; ya enviamos la respuesta
	}


	// Debe devolver: nombre_carpeta, ruc_empresa, ruc_cli_prov (opcional), abreviatura, serie, secuencial
	protected function resolver_info_documento($id_documento, $tipo_documento)
	{

		$con = conenta_login();
		session_start();
		//para establecer la direccion de donde esta trabajando el sistema sea local o web
		$servidor = "internet"; //local o internet
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$tabla = "encabezado_" . $tipo_documento;
		if ($tipo_documento == 'liquidacion') {
			$where = "WHERE id_encabezado_liq";
		} else {
			$where = "WHERE id_encabezado_" . $tipo_documento;
		}
		//consulta datos de los encabezados
		$busca_datos_encabezados = mysqli_query($con, "SELECT * FROM $tabla $where = '" . $id_documento . "' ");
		$datos_encabezados = mysqli_fetch_array($busca_datos_encabezados);

		//para sacar las consultas de variables dependiendo de cada tipo de documento consulta en encabezado_
		switch ($tipo_documento) {
			case "factura":
				$nombre_carpeta = "facturas_autorizadas";
				$abreviatura = "FAC";
				$serie = $datos_encabezados['serie_factura'];
				$secuencial = $datos_encabezados['secuencial_factura'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				break;
			case "retencion":
				$nombre_carpeta = "retenciones_autorizadas";
				$abreviatura = "CR";
				$serie = $datos_encabezados['serie_retencion'];
				$secuencial = $datos_encabezados['secuencial_retencion'];
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'proveedores', 'id_proveedor', $id_cliente_proveedor)['ruc_proveedor'];
				break;
			case "nc":
				$nombre_carpeta = "nc_autorizadas";
				$abreviatura = "NC";
				$serie = $datos_encabezados['serie_nc'];
				$secuencial = $datos_encabezados['secuencial_nc'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				break;
			case "gr":
				$nombre_carpeta = "guias_autorizadas";
				$abreviatura = "GR";
				$serie = $datos_encabezados['serie_gr'];
				$secuencial = $datos_encabezados['secuencial_gr'];
				$id_cliente_proveedor = $datos_encabezados['id_transportista'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				break;
			case "liquidacion":
				$nombre_carpeta = "liquidaciones_autorizadas";
				$abreviatura = "LIQ";
				$serie = $datos_encabezados['serie_liquidacion'];
				$secuencial = $datos_encabezados['secuencial_liquidacion'];
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'proveedores', 'id_proveedor', $id_cliente_proveedor)['ruc_proveedor'];
				break;
			case "proforma":
				$nombre_carpeta = "proformas_autorizadas";
				$abreviatura = "PROFORMA-";
				$serie = $datos_encabezados['serie_proforma'];
				$secuencial = $datos_encabezados['secuencial_proforma'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				break;
			case "compra":
				$nombre_carpeta = "facturas_compras";
				$abreviatura = "FAC";
				$serie = substr($datos_encabezados['numero_documento'], 0, 7);
				$secuencial = substr($datos_encabezados['numero_documento'], -9);
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];
				$busca_datos_compra = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE id_encabezado_compra = '" . $id_documento . "' ");
				$row_datos_compra = mysqli_fetch_array($busca_datos_compra);
				switch ($row_datos_compra['deducible_en']) {
					case '04':
						$ruc_cliente_proveedor = substr($ruc_empresa, 0, 10) . '001';
						break;
					case '05':
						$ruc_cliente_proveedor = substr($ruc_empresa, 0, 10);
						break;
					default:
						$ruc_cliente_proveedor = $ruc_empresa;
				}
				break;
		}
		// Consulta a BD según tu estructura y retorna el array esperado.
		return array(
			'nombre_carpeta' => $nombre_carpeta,
			'ruc_empresa'    => $ruc_empresa,
			'ruc_cli_prov'   => $ruc_cliente_proveedor, // opcional
			'abreviatura'    => $abreviatura,
			'serie'          => $serie,
			'secuencial'     => $secuencial,
		);
		//return false; // si no se encuentra
	}



	public function descarga_documento($id_documento, $tipo_documento, $extension_archivo)
	{
		$con = conenta_login();
		session_start();
		//para establecer la direccion de donde esta trabajando el sistema sea local o web
		$servidor = "internet"; //local o internet
		$ruc_empresa = $_SESSION['ruc_empresa'];
		$tabla = "encabezado_" . $tipo_documento;
		if ($tipo_documento == 'liquidacion') {
			$where = "WHERE id_encabezado_liq";
		} else {
			$where = "WHERE id_encabezado_" . $tipo_documento;
		}
		//consulta datos de los encabezados
		$busca_datos_encabezados = mysqli_query($con, "SELECT * FROM $tabla $where = '" . $id_documento . "' ");
		$datos_encabezados = mysqli_fetch_array($busca_datos_encabezados);

		//para sacar las consultas de variables dependiendo de cada tipo de documento consulta en encabezado_
		switch ($tipo_documento) {
			case "factura":
				$serie = $datos_encabezados['serie_factura'];
				$secuencial = $datos_encabezados['secuencial_factura'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'facturas_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'FAC', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir; // para otros formatos
				break;
			case "retencion":
				$serie = $datos_encabezados['serie_retencion'];
				$secuencial = $datos_encabezados['secuencial_retencion'];
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'proveedores', 'id_proveedor', $id_cliente_proveedor)['ruc_proveedor'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'retenciones_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'CR', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
			case "nc":
				$serie = $datos_encabezados['serie_nc'];
				$secuencial = $datos_encabezados['secuencial_nc'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'nc_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'NC', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
			case "gr":
				$serie = $datos_encabezados['serie_gr'];
				$secuencial = $datos_encabezados['secuencial_gr'];
				//$id_cliente_proveedor =$datos_encabezados['id_cliente'];
				$id_cliente_proveedor = $datos_encabezados['id_transportista'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'guias_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'GR', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
			case "liquidacion":
				$serie = $datos_encabezados['serie_liquidacion'];
				$secuencial = $datos_encabezados['secuencial_liquidacion'];
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'proveedores', 'id_proveedor', $id_cliente_proveedor)['ruc_proveedor'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'liquidaciones_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'LIQ', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
			case "proforma":
				$serie = $datos_encabezados['serie_proforma'];
				$secuencial = $datos_encabezados['secuencial_proforma'];
				$id_cliente_proveedor = $datos_encabezados['id_cliente'];
				$ruc_cliente_proveedor = $this->clientes_proveedores($con, 'clientes', 'id', $id_cliente_proveedor)['ruc'];
				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'proformas_autorizadas', $ruc_empresa, $ruc_cliente_proveedor, 'PROFORMA-', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
			case "compra":
				$serie = substr($datos_encabezados['numero_documento'], 0, 7);
				$secuencial = substr($datos_encabezados['numero_documento'], -9);
				$id_cliente_proveedor = $datos_encabezados['id_proveedor'];

				$busca_datos_compra = mysqli_query($con, "SELECT * FROM encabezado_compra WHERE id_encabezado_compra = '" . $id_documento . "' ");
				$row_datos_compra = mysqli_fetch_array($busca_datos_compra);
				switch ($row_datos_compra['deducible_en']) {
					case '04':
						$NumIdComprador = substr($ruc_empresa, 0, 10) . '001';
						break;
					case '05':
						$NumIdComprador = substr($ruc_empresa, 0, 10);
						break;
					default:
						$NumIdComprador = $ruc_empresa;
				}

				$documento_a_imprimir = $this->copia_documento_tmp($servidor, 'facturas_compras', $ruc_empresa, $NumIdComprador, 'FAC', $serie, $secuencial, $extension_archivo);
				return $documento_a_imprimir;
				break;
		}
	}


	public function clientes_proveedores($con, $tabla, $id_tabla, $id_registro)
	{
		//para buscar datos del cliente
		$busca_datos = mysqli_query($con, "SELECT * FROM $tabla WHERE $id_tabla = '" . $id_registro . "' ");
		$row_datos = mysqli_fetch_array($busca_datos);
		return $row_datos;
	}



	/* 	public function copia_documento_tmp($servidor, $nombre_carpeta, $ruc_empresa, $ruc_cliente_proveedor, $abreviatura_documento, $serie, $secuencial, $extension_archivo)
	{
		$nombre_documento_tmp = $ruc_cliente_proveedor . $abreviatura_documento . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . $extension_archivo;
		if ($servidor == "local") {
			$direccion_documento = "C:\\xampp\\htdocs\\sistema\\facturacion_electronica\\"; //local	
			$nombre_documento = $direccion_documento . $nombre_carpeta . "/" . $ruc_empresa . "/" . $ruc_cliente_proveedor . "/" . $abreviatura_documento . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . $extension_archivo;
			copy($nombre_documento, $nombre_documento_tmp);
			return $nombre_documento_tmp;
		} else {
			$ftp_server = "64.225.69.65";
			$ftp_user_name = "char";
			$ftp_user_pass = "CmGr1980";
			$conn_id = ftp_connect($ftp_server);
			if (@ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
				ftp_pasv($conn_id, true);
				$server_file = "/ftp_documentos/" . $nombre_carpeta . "/" . $ruc_empresa . "/" . $ruc_cliente_proveedor . "/" . $abreviatura_documento . $serie . "-" . str_pad($secuencial, 9, "000000000", STR_PAD_LEFT) . $extension_archivo;
				$local_file = "../docs_temp/" . $ruc_empresa . $nombre_documento_tmp;
				if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
					copy($local_file, $nombre_documento_tmp);
					if (file_exists($nombre_documento_tmp)) {
						unlink($local_file);
						return $nombre_documento_tmp;
					} else {
						return 'No encontrado.pdf';
					}
				} else {
					return 'No encontrado.pdf';
				}
			} else {
				return "No hay conexión con el servidor";
			}

			ftp_close($conn_id);
		}
	} */

	public function copia_documento_tmp($servidor, $nombre_carpeta, $ruc_empresa, $ruc_cliente_proveedor, $abreviatura_documento, $serie, $secuencial, $extension_archivo)
	{
		// --- Normalizaciones ---
		$extNorm = strtolower(ltrim(trim($extension_archivo), ".")); // "xml" o "pdf"
		$extDot  = "." . $extNorm;

		// Serie y secuencial en variantes
		$serie_hyph    = trim($serie);                      // ej: "001-001"
		$serie_compact = str_replace("-", "", $serie_hyph); // ej: "001001"

		// Solo dígitos en secuencial
		$sec_digits = preg_replace('/\D+/', '', (string)$secuencial);
		if ($sec_digits === '') $sec_digits = (string)$secuencial;

		$sec_pad9   = str_pad($sec_digits, 9, '0', STR_PAD_LEFT);
		$sec_nopad  = $sec_digits;

		// Nombres candidatos (archivo en servidor)
		$fname_base             = $abreviatura_documento . $serie_hyph    . "-" . $sec_pad9  . $extDot; // FAC001-001-000001234.xml
		$fname_base_nodash      = $abreviatura_documento . $serie_compact . "-" . $sec_pad9  . $extDot; // FAC001001-000001234.xml
		$fname_base_nopad       = $abreviatura_documento . $serie_hyph    . "-" . $sec_nopad . $extDot; // FAC001-001-1234.xml
		$fname_with_rucprefix   = $ruc_cliente_proveedor . $fname_base;                                  // 1791...FAC001-001-000001234.xml
		$fname_with_rucprefix_n = $ruc_cliente_proveedor . $fname_base_nodash;

		// Carpeta temporal local
		$local_dir = "../docs_temp/";
		if (!is_dir($local_dir)) {
			@mkdir($local_dir, 0775, true);
		}

		// ✅ Nombre final local deseado (sin RUCs, simple)
		$nombre_documento_tmp = $local_dir . $fname_base;

		// --- INTERNET / FTP ---
		$ftp_server    = "64.225.69.65";
		$ftp_user_name = "char";
		$ftp_user_pass = "CmGr1980";

		$conn_id = @ftp_connect($ftp_server);
		if (!$conn_id) {
			return "No hay conexión con el servidor";
		}

		$resultado = 'No encontrado.pdf';
		if (@ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)) {
			@ftp_pasv($conn_id, true);

			// Candidatos en el servidor (orden probabilidad)
			$server_candidates = array(
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$ruc_cliente_proveedor}/{$fname_base}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$fname_base}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$ruc_cliente_proveedor}/{$fname_base_nodash}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$fname_base_nodash}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$ruc_cliente_proveedor}/{$fname_base_nopad}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$fname_base_nopad}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$ruc_cliente_proveedor}/{$fname_with_rucprefix}",
				"/ftp_documentos/{$nombre_carpeta}/{$ruc_empresa}/{$ruc_cliente_proveedor}/{$fname_with_rucprefix_n}",
			);

			// Descarga a un temporal, luego mueve al nombre final
			$local_file_tmp = $local_dir . "dl_" . uniqid() . $extDot;

			foreach ($server_candidates as $server_file) {
				$size = @ftp_size($conn_id, $server_file);
				if ($size > -1) { // existe
					if (@ftp_get($conn_id, $local_file_tmp, $server_file, FTP_BINARY)) {
						if (@copy($local_file_tmp, $nombre_documento_tmp)) {
							@unlink($local_file_tmp);

							// Si es XML, envolver y sobrescribir el final
							if ($extNorm === 'xml') {
								$xmlOriginal = @file_get_contents($nombre_documento_tmp);
								if ($xmlOriginal !== false) {
									$xmlAut = $this->envolver_xml_autorizacion_contenido($xmlOriginal); // helper PHP 5.6
									@file_put_contents($nombre_documento_tmp, $xmlAut);
								}
							}

							if (is_file($nombre_documento_tmp)) {
								$resultado = $nombre_documento_tmp;
								break;
							}
						}
					}
				}
			}
		} else {
			$resultado = "No hay conexión con el servidor";
		}

		@ftp_close($conn_id);
		return $resultado;
	}

	public function envolver_xml_autorizacion_contenido($xmlOriginal)
	{
		/* if ($xmlOriginal === false || trim($xmlOriginal) === '') {
			throw new Exception("XML original vacío o ilegible.");
		}

		// Quitar BOM si existiera
		if (strpos($xmlOriginal, "\xEF\xBB\xBF") === 0) {
			$xmlOriginal = substr($xmlOriginal, 3);
		}

		// Extraer datos para numeroAutorizacion, ambiente y fechaAutorizacion
		$numeroAutorizacion = null;
		$fechaAutorizacion  = null;
		$ambienteTexto      = 'PRODUCCIÓN'; // por defecto

		$dom = new DOMDocument();
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;

		if (@$dom->loadXML($xmlOriginal)) {
			// claveAcceso
			$nClave = $dom->getElementsByTagName('claveAcceso');
			if ($nClave && $nClave->length > 0) {
				$numeroAutorizacion = trim($nClave->item(0)->textContent);
			}

			// ambiente (1=PRUEBAS, 2=PRODUCCIÓN)
			$nAmb = $dom->getElementsByTagName('ambiente');
			if ($nAmb && $nAmb->length > 0) {
				$ambValor = trim($nAmb->item(0)->textContent);
				$ambienteTexto = ($ambValor === '2') ? 'PRODUCCIÓN' : 'PRUEBAS';
			}

			// etsi:SigningTime
			$xp = new DOMXPath($dom);
			$xp->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
			$xp->registerNamespace('etsi', 'http://uri.etsi.org/01903/v1.3.2#');
			$nST = $xp->query('//etsi:SigningTime');
			if ($nST && $nST->length > 0) {
				$stRaw = trim($nST->item(0)->textContent);
				try {
					$dt = new DateTime($stRaw);
					$dt->setTimezone(new DateTimeZone('America/Guayaquil'));
					$fechaAutorizacion = $dt->format('Y-m-d\TH:i:sP'); // ej.: 2025-08-05T09:17:50-05:00
				} catch (Exception $e) {
				}
			}
		} */

		// Fallbacks
		/* if (!$numeroAutorizacion) {
			$numeroAutorizacion = substr(hash('sha256', $xmlOriginal), 0, 49);
		}
		if (!$fechaAutorizacion) {
			$ahoraGye = new DateTime('now', new DateTimeZone('America/Guayaquil'));
			$fechaAutorizacion = $ahoraGye->format('Y-m-d\TH:i:sP');
		} */

		// Construir XML <autorizacion>
		/* $envuelto  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
		$envuelto .= "<autorizacion>\n";
		$envuelto .= "  <estado>AUTORIZADO</estado>\n";
		$envuelto .= "  <numeroAutorizacion>" . htmlspecialchars($numeroAutorizacion, ENT_QUOTES, 'UTF-8') . "</numeroAutorizacion>\n";
		$envuelto .= "  <fechaAutorizacion>" . htmlspecialchars($fechaAutorizacion, ENT_QUOTES, 'UTF-8') . "</fechaAutorizacion>\n";
		$envuelto .= "  <ambiente>" . htmlspecialchars($ambienteTexto, ENT_QUOTES, 'UTF-8') . "</ambiente>\n";
		$envuelto .= "  <comprobante><![CDATA[" . $xmlOriginal . "]]></comprobante>\n";
		$envuelto .= "  <mensajes/>\n";
		$envuelto .= "</autorizacion>"; */
		$envuelto  = $xmlOriginal;

		return $envuelto;
	}
}


if ($action == 'pdf_rol_individual') {
	$id_rol = $_GET['id_rol'];
	$tipo_descarga = $_GET['tipoDescarga'];
	$path = $_GET['path'];
	pdf_rol_individual($con, $id_rol, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
}

function pdf_rol_individual($con, $id_rol, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa)
{
	$id_rol = encrypt_decrypt("decrypt", $id_rol);
	$tipo_descarga = encrypt_decrypt("decrypt", $tipo_descarga);
	$path = encrypt_decrypt("decrypt", $path);

	$sql_rol = mysqli_query($con, "SELECT rol.mes_ano as mes_ano, rol.datecreated as fecha, rol.id_empresa as id_empresa,  det.id_empleado as id_empleado, det.id as id, emp.nombres_apellidos as empleado,
        emp.documento as cedula, det.dias_laborados as dias_laborados, det.sueldo as sueldo, 
        det.ingresos_gravados as ingresos_gravados, det.ingresos_excentos as ingresos_excentos,
        det.aporte_patronal as aporte_patronal, det.quincena as quincena, det.aporte_personal as aporte_personal,  det.total_egresos as total_egresos, det.tercero as tercero, 
        det.cuarto as cuarto, det.fondo_reserva as fondo_reserva, det.a_recibir as a_recibir, 
        sue.cargo_empresa as cargo FROM detalle_rolespago as det INNER JOIN empleados as emp ON
         emp.id=det.id_empleado INNER JOIN rolespago as rol ON rol.id=det.id_rol INNER JOIN sueldos as sue ON sue.id_empleado=det.id_empleado
          WHERE det.id = '" . $id_rol . "'");
	$data_detalle_rol = mysqli_fetch_array($sql_rol);


	$sql_sueldo = mysqli_query($con, "SELECT id FROM sueldos WHERE id_empleado = '" . $data_detalle_rol['id_empleado'] . "' and status = 1 ");
	$row_sueldo = mysqli_fetch_array($sql_sueldo);
	$id_sueldo = $row_sueldo['id'];

	$ingresosDescuentosFijos = mysqli_query($con, "SELECT * FROM detalle_sueldos WHERE id_sueldo = '" . $id_sueldo . "' order by tipo asc ");

	$detalles = mysqli_query($con, "SELECT id_novedad, valor, detalle FROM novedades WHERE id_empleado = '" . $data_detalle_rol['id_empleado'] . "' and id_empresa= '" . $data_detalle_rol['id_empresa'] . "' and mes_ano='" . $data_detalle_rol['mes_ano'] . "' and aplica_en='R' and status !=0 order by id_novedad asc");

	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);

	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);

	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);

	$novedades = novedades_sueldos();
	$calculos_salarios = calculos_rol_pagos(substr($data_detalle_rol['mes_ano'], -4), $con);

	$cedula = $data_detalle_rol['cedula'];
	$meses = Meses();
	$mes_ano = $data_detalle_rol['mes_ano'];
	$empleado = $data_detalle_rol['empleado'];
	$data = array('logo' => $logo, 'ing_des_fijos' => $ingresosDescuentosFijos, 'rol' => $data_detalle_rol, 'meses' => $meses, 'empresa' => $empresa, 'usuario' => $usuario, 'detalle' => $detalles, 'novedades' => $novedades, 'salario' => $calculos_salarios);

	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfroluno.php");
	$html = ob_get_clean();

	$html2pdf = new Html2Pdf('p', 'A4', 'es', 'true', 'UTF-8', array(10, 10, 10, 10));
	$html2pdf->writeHTML($html);
	$html2pdf->output($path . 'Rol_pagos-' . $cedula . "-" . $empleado . '_' . $mes_ano . '.pdf', $tipo_descarga);
	//'I', 'D', 'F', 'S', 'FI','FD', 'E' 
	// I= LO GENERA Y LO ABRE, D SOLO LO DESCARGA, F LO DESCARGA AL SERVIDOR PERO HAY QUE CONFIGURAR LA CARPETA DE DESTINO          
}

if ($action == 'pdf_quincena_individual') {
	$id_quincena = $_GET['id_quincena'];
	$tipo_descarga = $_GET['tipoDescarga'];
	$path = $_GET['path'];
	pdf_quincena_individual($con, $id_quincena, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
}

if ($action == 'pdf_retorno_consignacion_venta') {
	$id_consignacion = $_GET['id_consignacion'];
	$tipo_descarga = $_GET['tipoDescarga'];
	$path = $_GET['path'];
	pdf_retorno_consignacion_venta($con, $id_consignacion, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa);
}

function pdf_retorno_consignacion_venta($con, $id_consignacion, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa)
{
	$id_consignacion = encrypt_decrypt("decrypt", $id_consignacion);
	$tipo_descarga = encrypt_decrypt("decrypt", $tipo_descarga);
	$path = encrypt_decrypt("decrypt", $path);
	$encabezado_retorno = mysqli_query($con, "SELECT * FROM encabezado_consignacion as enc  
	INNER JOIN clientes as cli ON cli.id=enc.id_cli_pro 
	WHERE enc.id_consignacion = '" . $id_consignacion . "'");
	$data_encabezado_retorno = mysqli_fetch_array($encabezado_retorno);

	$sql_detalle_retorno = mysqli_query($con, "SELECT * FROM detalle_consignacion as det 
	INNER JOIN encabezado_consignacion as enc ON enc.codigo_unico=det.codigo_unico 
	WHERE enc.id_consignacion = '" . $id_consignacion . "' order by det.nombre_producto asc");

	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);

	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);

	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);

	$data = array('logo' => $logo, 'encabezado_retorno' => $data_encabezado_retorno, 'detalle_retorno' => $sql_detalle_retorno, 'empresa' => $empresa, 'usuario' => $usuario);
	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfRetornoConsignacionVenta.php");
	$html = ob_get_clean();

	$html2pdf = new Html2Pdf('P', 'A4', 'es', 'true', 'UTF-8', array(10, 10, 10, 10));
	$html2pdf->writeHTML($html);
	$html2pdf->output($path . 'Retorno CV N-' . $data_encabezado_retorno['numero_consignacion'] . '.pdf', $tipo_descarga);
	//'I', 'D', 'F', 'S', 'FI','FD', 'E' 
	// I= LO GENERA Y LO ABRE, D SOLO LO DESCARGA, F LO DESCARGA AL SERVIDOR PERO HAY QUE CONFIGURAR LA CARPETA DE DESTINO          
}

function pdf_quincena_individual($con, $id_quincena, $tipo_descarga, $path, $id_empresa, $id_usuario, $ruc_empresa)
{
	$id_quincena = encrypt_decrypt("decrypt", $id_quincena);
	$tipo_descarga = encrypt_decrypt("decrypt", $tipo_descarga);
	$path = encrypt_decrypt("decrypt", $path);
	$sql_detalle_quincenas = mysqli_query($con, "SELECT qui.mes_ano as mes_ano, qui.datecreated as fecha, det.id as id, emp.nombres_apellidos as empleado, 
        emp.documento as cedula, det.quincena as quincena, det.adicional as adicional, 
        det.descuento as descuento, det.arecibir as arecibir, det.status as status, det.id_pago as pago, 
        det.id_empleado as id_empleado, qui.id_empresa as id_empresa, sue.sueldo as sueldo 
        FROM detalle_quincena as det INNER JOIN empleados as emp ON emp.id=det.id_empleado INNER JOIN quincenas as qui 
        ON qui.id=det.id_quincena INNER JOIN sueldos as sue ON sue.id_empleado=det.id_empleado and sue.status='1' WHERE det.id = '" . $id_quincena . "' order by emp.nombres_apellidos asc");
	$data_detalle_quincena = mysqli_fetch_array($sql_detalle_quincenas);

	$detalles = mysqli_query($con, "SELECT id_novedad, valor, detalle FROM novedades WHERE id_empleado = '" . $data_detalle_quincena['id_empleado'] . "' and id_empresa= '" . $data_detalle_quincena['id_empresa'] . "' and mes_ano='" . $data_detalle_quincena['mes_ano'] . "' and aplica_en='Q' and status !=0 order by id_novedad asc");

	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);

	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);

	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);

	$novedades = novedades_sueldos();
	$calculos_salarios = calculos_rol_pagos(substr($data_detalle_quincena['mes_ano'], -4), $con);

	$mes_ano = $data_detalle_quincena['mes_ano'];
	$empleado = $data_detalle_quincena['empleado'];
	$cedula = $data_detalle_quincena['cedula'];
	$meses = Meses();
	$data = array('logo' => $logo, 'quincena' => $data_detalle_quincena, 'meses' => $meses, 'empresa' => $empresa, 'usuario' => $usuario, 'detalle' => $detalles, 'novedades' => $novedades, 'salario' => $calculos_salarios);
	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfquincenauno.php");
	$html = ob_get_clean();

	$html2pdf = new Html2Pdf('l', 'A5', 'es', 'true', 'UTF-8', array(10, 10, 10, 10));
	$html2pdf->writeHTML($html);
	$html2pdf->output($path . 'Quincena-' . $cedula . "-" . $empleado . '_' . $mes_ano . '.pdf', $tipo_descarga);
	//'I', 'D', 'F', 'S', 'FI','FD', 'E' 
	// I= LO GENERA Y LO ABRE, D SOLO LO DESCARGA, F LO DESCARGA AL SERVIDOR PERO HAY QUE CONFIGURAR LA CARPETA DE DESTINO          
}

if ($action == 'pdf_quincena_general') {
	$id_quincena = intval($_POST['idQuincenaPrint']);
	$mes_ano = $_POST['periodo_quincena'];
	$sql_quincenas = mysqli_query($con, "SELECT qui.mes_ano as mes_ano, det.id as id, emp.nombres_apellidos as empleado, 
            emp.documento as cedula, det.quincena as quincena, det.adicional as adicional, 
            det.descuento as descuento, det.arecibir as arecibir, round(det.arecibir - det.abonos,2) as saldo 
            FROM detalle_quincena as det INNER JOIN empleados as emp ON emp.id=det.id_empleado 
            INNER JOIN quincenas as qui ON qui.id=det.id_quincena WHERE det.id_quincena = '" . $id_quincena . "' order by emp.nombres_apellidos asc");
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);
	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);
	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);
	$meses = Meses();
	$data = array('logo' => $logo, 'quincena' => $sql_quincenas, 'meses' => $meses, 'empresa' => $empresa, 'usuario' => $usuario, 'mes_ano' => $mes_ano);
	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfquincenatodos.php");
	$html = ob_get_clean();
	$html2pdf = new Html2Pdf('l', 'A4', 'es', 'true', 'UTF-8', array(5, 5, 5, 5));
	$html2pdf->writeHTML($html);
	$html2pdf->output('Quincena-' . $mes_ano . '.pdf', 'D');
}

if ($action == 'pdf_decimocuarto_general') {
	$id_dc = intval($_POST['idDcPrint']);
	$anio = $_POST['periodo_decimocuarto'];
	$region = $_POST['region_generada'];
	$sql_decimocuarto = mysqli_query($con, "SELECT round(ddc.decimo,2) as decimo, round(ddc.anticipos,2) as anticipos, 
    emp.nombres_apellidos as empleado, emp.documento as cedula, emp.email as correo_empleado, 
    ddc.dias as dias, ddc.abonos as abonos, ddc.arecibir as arecibir 
	FROM detalle_decimocuarto as ddc 
	INNER JOIN empleados as emp ON emp.id=ddc.id_empleado
	WHERE ddc.id_dc='" . $id_dc . "' order by emp.nombres_apellidos asc");
	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);
	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);
	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);
	$data = array('logo' => $logo, 'decimocuarto' => $sql_decimocuarto, 'empresa' => $empresa, 'usuario' => $usuario, 'anio' => $anio, 'region' => $region);
	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfdecimocuartotodos.php");
	$html = ob_get_clean();
	$html2pdf = new Html2Pdf('l', 'A4', 'es', 'true', 'UTF-8', array(5, 5, 5, 5));
	$html2pdf->writeHTML($html);
	$html2pdf->output('DecimoCuarto-' . $anio . '.pdf', 'D');
}



if ($action == 'pdf_rol_general') {
	$id_rol = intval($_POST['idRolesPagoPrint']);
	$mes_ano = $_POST['periodo_RolesPago'];

	$sql_rol = mysqli_query($con, "SELECT rol.mes_ano as mes_ano, det.dias_laborados as dias_laborados, 
        det.id as id, emp.nombres_apellidos as empleado, emp.documento as cedula, 
        det.sueldo as sueldo, det.ingresos_gravados as ingresos_gravados, det.ingresos_excentos as ingresos_excentos, 
        det.total_egresos as total_egresos, det.tercero as tercero, det.cuarto as cuarto, det.fondo_reserva as fondo_reserva, 
        det.a_recibir as arecibir, round(det.a_recibir - det.abonos,2) as saldo
        FROM detalle_rolespago as det INNER JOIN empleados as emp ON emp.id=det.id_empleado 
        INNER JOIN rolespago as rol ON rol.id=det.id_rol 
        WHERE det.id_rol = '" . $id_rol . "' order by emp.nombres_apellidos asc");

	$sql_empresa = mysqli_query($con, "SELECT * FROM empresas WHERE id = '" . $id_empresa . "' ");
	$empresa = mysqli_fetch_array($sql_empresa);
	$sql_logo = mysqli_query($con, "SELECT logo_sucursal as logo FROM sucursales WHERE ruc_empresa = '" . $ruc_empresa . "' ");
	$logo = mysqli_fetch_array($sql_logo);
	$sql_usuario = mysqli_query($con, "SELECT * FROM usuarios WHERE id = '" . $id_usuario . "' ");
	$usuario = mysqli_fetch_array($sql_usuario);
	$meses = Meses();
	$data = array('logo' => $logo, 'rol' => $sql_rol, 'meses' => $meses, 'empresa' => $empresa, 'usuario' => $usuario, 'mes_ano' => $mes_ano);
	ob_end_clean();
	ob_start();
	require_once("../pdf/pdfrolestodos.php");
	$html = ob_get_clean();
	$html2pdf = new Html2Pdf('l', 'A4', 'es', 'true', 'UTF-8', array(10, 5, 5, 5));
	$html2pdf->writeHTML($html);
	$html2pdf->output('Rol-de-Pagos-' . $mes_ano . '.pdf', 'D');
}
