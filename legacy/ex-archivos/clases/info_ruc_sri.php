<?php
$dir = dirname(__FILE__);
include_once($dir . "/../conexiones/conectalogin.php");
include_once($dir . "/../helpers/helpers.php");
$con = conenta_login();

$action = (isset($_REQUEST['action']) && $_REQUEST['action'] != NULL) ? $_REQUEST['action'] : '';
if (($action == 'info_ruc') && isset($_POST['numero']) && (!empty($_POST['numero']))) {
	$clientes = mysqli_query($con, "SELECT * FROM clientes WHERE mid(ruc,1,10) = '" . substr($_POST['numero'], 0, 10) . "' order by id desc LIMIT 1");
	$row_clientes = mysqli_fetch_array($clientes);

	$proveedores = mysqli_query($con, "SELECT * FROM proveedores WHERE mid(ruc_proveedor,1,10) = '" . substr($_POST['numero'], 0, 10) . "' order by id_proveedor desc LIMIT 1");
	$row_proveedores = mysqli_fetch_array($proveedores);

	//para sacar el tipo de empresa
	$tercerDigito = substr($_POST['numero'], 2, 1);
	switch ($tercerDigito) {
		case 9:
			$tipo = "03"; //sociedad
			break;
		case 6:
			$tipo = "05"; //sector publico
			break;
		default:
			$tipo = "01"; //personal natural
	}

	if ($clientes->num_rows > 0) {
		$datos_ruc[] = array(
			'nombre' => mb_convert_encoding(strClean($row_clientes['nombre']), 'UTF-8', 'ISO-8859-1'),
			'tipo' => $tipo,
			'direccion' => strClean($row_clientes['direccion']),
			'nombre_comercial' => mb_convert_encoding(strClean($row_clientes['nombre']), 'UTF-8', 'ISO-8859-1'),
			'codigo_provincia' => $row_clientes['provincia'],
			'codigo_ciudad' => $row_clientes['ciudad'],
			'email' => $row_clientes['email'],
			'telefono' => $row_clientes['telefono']
		);
	} else if ($proveedores->num_rows > 0) {
		$datos_ruc[] = array(
			'nombre' => mb_convert_encoding(strClean($row_proveedores['razon_social']), 'UTF-8', 'ISO-8859-1'),
			'tipo' => $row_proveedores['tipo_empresa'],
			'direccion' => strClean($row_proveedores['dir_proveedor']),
			'nombre_comercial' => mb_convert_encoding(strClean($row_proveedores['nombre_comercial']), 'UTF-8', 'ISO-8859-1'),
			'codigo_provincia' => '17',
			'codigo_ciudad' => '189',
			'email' => $row_proveedores['mail_proveedor'],
			'telefono' => $row_proveedores['telf_proveedor']
		);
	} else {
		$datos_ruc = array();
		$url_almacenados = "http://137.184.159.242:4000/api/sri-identification";
		$data_almacenados = array_map('strval', array(
			"identification" => $_POST['numero']
		));

		$longitud = strlen($_POST['numero']);
		//documentos descargados en la carpeta temporal
		$ch = curl_init($url_almacenados);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_almacenados));
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		$response = curl_exec($ch);

		if (curl_errno($ch)) {
			header('Content-Type: application/json');
			echo json_encode(array('error' => 'Error en la petición: ' . curl_error($ch)));
			curl_close($ch);
			die();
		}
		curl_close($ch);

		$responseData = json_decode($response, true);
		if (!is_array($responseData) || !isset($responseData['data']) || !is_array($responseData['data'])) {
			header('Content-Type: application/json');
			echo json_encode(array());
			die();
		}

		$data = $responseData['data'];

		// Cédula (10 dígitos): { data: { identificacion, nombreCompleto, fechaDefuncion } }
		if ($longitud === 10) {
			$nombre = $data['nombreCompleto'] ?? '';
			$nombre = mb_convert_encoding(strClean($nombre), 'UTF-8', 'ISO-8859-1');
			$datos_ruc[] = array(
				'nombre' => $nombre,
				'tipo' => '01',
				'direccion' => '',
				'nombre_comercial' => $nombre,
				'codigo_provincia' => '17',
				'codigo_ciudad' => '189',
				'email' => '',
				'telefono' => ''
			);
		}

		// RUC (13 dígitos): { data: { datosContribuyente, establecimientos } }
		if ($longitud === 13) {
			$contrib = $data['datosContribuyente'][0] ?? null;
			$razonSocial = $contrib['razonSocial'] ?? '';
			$nombre_comercial = $razonSocial;
			$ubicacion_establecimiento = '';

			if (!empty($data['establecimientos'])) {
				foreach ($data['establecimientos'] as $est) {
					$estado = $est['estado'] ?? '';
					$matriz = strtoupper($est['matriz'] ?? '');
					if ($estado === 'ABIERTO' && $matriz === 'SI') {
						$nombre_comercial = $est['nombreFantasiaComercial'] ?? $razonSocial;
						$ubicacion_establecimiento = $est['direccionCompleta'] ?? '';
						break;
					}
				}
				if (empty($ubicacion_establecimiento)) {
					foreach ($data['establecimientos'] as $est) {
						if (($est['estado'] ?? '') === 'ABIERTO') {
							$nombre_comercial = $est['nombreFantasiaComercial'] ?? $razonSocial;
							$ubicacion_establecimiento = $est['direccionCompleta'] ?? '';
							break;
						}
					}
				}
			}

			$datos_ruc[] = array(
				'nombre' => mb_convert_encoding(strClean($razonSocial), 'UTF-8', 'ISO-8859-1'),
				'tipo' => $tipo,
				'direccion' => strClean($ubicacion_establecimiento),
				'nombre_comercial' => mb_convert_encoding(strClean($nombre_comercial), 'UTF-8', 'ISO-8859-1'),
				'codigo_provincia' => '17',
				'codigo_ciudad' => '189',
				'email' => '',
				'telefono' => ''
			);
		}
	}

	header('Content-Type: application/json');
	echo json_encode($datos_ruc, JSON_UNESCAPED_UNICODE);

	die();
}


/* 

//cuando se consulta cedula bota este resultado
Array
(
    [data] => Array
        (
            [contribuyente] => Array
                (
                    [identificacion] => 1717136574
                    [denominacion] => 
                    [tipo] => 
                    [clase] => NO REGISTRADO
                    [tipoIdentificacion] => C
                    [resolucion] => 
                    [nombreComercial] => GARCIA REVELO CARLOS MAURICIO
                    [direccionMatriz] => 
                    [fechaInformacion] => 1738386000000
                    [mensaje] => 
                    [estado] => 
                )

            [deuda] => 
            [impugnacion] => 
            [remision] => 
        )

    [status] => 1
)

//cuando se consulta ruc 
Array
(
    [data] => Array
        (
            [contribuyente] => Array
                (
                    [identificacion] => 1717136574001
                    [denominacion] => 
                    [tipo] => 
                    [clase] => PERSONA NATURAL
                    [tipoIdentificacion] => R
                    [resolucion] => 
                    [nombreComercial] => GARCIA REVELO CARLOS MAURICIO
                    [direccionMatriz] => 
                    [fechaInformacion] => 1738386000000
                    [mensaje] => 
                    [estado] => 
                )

            [deuda] => 
            [impugnacion] => 
            [remision] => 
            [infoRuc] => Array
                (
                    [Razon Social:] => GARCIA REVELO CARLOS MAURICIO
                    [RUC:] => 1717136574001
                    [Nombre Comercial:] => 
                    [Estado del Contribuyente en el RUC] => Activo
                    [Clase de Contribuyente] => Otro
                    [Tipo de Contribuyente] => Persona Natural
                    [Obligado a llevar Contabilidad] => NO
                    [Actividad Economica Principal] => OTRAS ACTIVIDADES DE CONTABILIDAD, TENEDURÍA DE LIBROS.
                    [Fecha de inicio de actividades] => 10-05-2006
                    [Categoria Mi PYMES] => Micro
                    [establecimiento] => Array
                        (
                            [0] => Array
                                (
                                    [establecimiento_0] => Array
                                        (
                                            [numero_establecimiento] => 001
                                            [nombre_comercial] => CMG SERVICIOS ADMINISTRATIVOS Y CONTABLES
                                            [ubicacion_establecimiento] => PICHINCHA / QUITO / MANUEL SERRANO N50-198 Y HOMERO SALAS
                                            [estado_establecimiento] => Abierto
                                        )

                                    [establecimiento_2] => Array
                                        (
                                            [numero_establecimiento] => 002
                                            [nombre_comercial] => CAMAGARE
                                            [ubicacion_establecimiento] => PICHINCHA / QUITO / PEDRO BARRIOS E4-61 Y GALO PLAZA
                                            [estado_establecimiento] => Abierto
                                        )

                                )

                        )

                )

        )

    [status] => 1
)
 */