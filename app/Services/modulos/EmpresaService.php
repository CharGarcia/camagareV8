<?php

namespace App\services\modulos;

use App\repositories\modulos\EmpresaRepository;

class EmpresaService
{
    private $repository;

    public function __construct()
    {
        $this->repository = new EmpresaRepository();
    }

    public function getData(int $idEmpresa): array
    {
        $establecimientos = $this->repository->getEstablecimientos($idEmpresa);
        $empresa          = $this->repository->getEmisorConfig($idEmpresa);

        // Fusionar config del establecimiento principal en $empresa
        // para que las vistas lo consuman igual que antes
        $idEstPrincipal = (int) ($establecimientos[0]['id'] ?? 0);
        if ($idEstPrincipal) {
            try {
                $estConfig = $this->repository->getEstablecimientoConfig($idEstPrincipal);
                if ($estConfig) {
                    $empresa = array_merge($empresa ?? [], $estConfig);
                }
            } catch (\Throwable $e) {
                // Las columnas aún no existen — migración pendiente
                // El sistema funciona con valores por defecto hasta que se ejecute la migración
            }
        }

        return [
            'empresa'               => $empresa,
            'correo'                => $this->repository->getCorreoConfig($idEmpresa),
            'firmas'                => $this->repository->getFirmas($idEmpresa),
            'establecimientos'      => $establecimientos,
            'puntos'                => $this->repository->getPuntosEmision($idEmpresa),
            'iva_casilleros'        => $this->repository->getIvaCasilleros($idEmpresa),
            'ices'                  => $this->repository->getIces($idEmpresa),
            'retenciones_sri_iva'   => $this->repository->getRetencionesSriIva(),
            'retenciones_casilleros' => $this->repository->getRetencionesCasilleros($idEmpresa),
            'usuarios_empresa'      => $this->repository->getUsuariosAsignados($idEmpresa),
        ];
    }
    
    public function saveEstablecimiento(int $idEmpresa, array $data, array $files = []): array
    {
        $idEst = (int) ($data['id'] ?? 0);
        
        // Manejar subida de logo
        if (!empty($files['logo_establecimiento']) && $files['logo_establecimiento']['error'] === UPLOAD_ERR_OK) {
            $file = $files['logo_establecimiento'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $dir = MVC_ROOT . "/public/uploads/logos/empresa_{$idEmpresa}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            
            $filename = "logo_est_" . time() . "." . $ext;
            $dest = $dir . "/" . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                // BASE_URL ya incluye "/public", solo agregar el subpath dentro de public/
                $data['logo_ruta'] = rtrim(BASE_URL, '/') . "/uploads/logos/empresa_{$idEmpresa}/" . $filename;
            }
        }

        if ($idEst > 0) {
            $ok = $this->repository->updateEstablecimiento($idEst, $idEmpresa, $data);
            return ['ok' => $ok];
        } else {
            $id = $this->repository->saveEstablecimiento($idEmpresa, $data);
            return ['ok' => $id > 0, 'id' => $id];
        }
    }

    public function deleteEstablecimiento(int $idEst, int $idEmpresa): bool
    {
        return $this->repository->deleteEstablecimiento($idEst, $idEmpresa);
    }

    public function saveGeneral(int $idEmpresa, array $data): bool
    {
        $fields = [
            'nombre', 'nombre_comercial', 'direccion', 'telefono', 'mail',
            'nom_rep_legal', 'ced_rep_legal', 'nombre_contador', 'ruc_contador',
            'cod_prov', 'cod_ciudad', 'tipo', 'cancelar_renovacion', 'obligado_contabilidad'
        ];
        
        // Manejar checkbox cancelar_renovacion (si no viene es false)
        if (!isset($data['cancelar_renovacion'])) {
            $data['cancelar_renovacion'] = 'false';
        } else {
            $data['cancelar_renovacion'] = 'true';
        }

        // Normalizar obligado_contabilidad a SI/NO
        $data['obligado_contabilidad'] = strtoupper(trim($data['obligado_contabilidad'] ?? 'NO')) === 'SI' ? 'SI' : 'NO';

        $filtered = array_intersect_key($data, array_flip($fields));
        
        return $this->repository->updateEmpresa($idEmpresa, $filtered);
    }

    public function saveEmisor(int $idEmpresa, array $data): bool
    {
        $fields = [
            'resolucion_contribuyente', 'id_tipo_regimen', 'tipo_ambiente', 
            'agente_retencion', 'tipo_emision'
        ];
        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->repository->updateEmpresa($idEmpresa, $filtered);
    }

    public function saveDecimales(int $idEmpresa, array $data): bool
    {
        $idEst = (int) ($data['id_establecimiento'] ?? 0);
        if (!$idEst) $idEst = $this->repository->getPrimerEstablecimientoId($idEmpresa);
        $fields = ['decimales_cantidad', 'decimales_precio'];
        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->repository->updateEstablecimientoConfig($idEst, $filtered);
    }

    public function saveIva(int $idEmpresa, array $data): bool
    {
        $idEst = (int) ($data['id_establecimiento'] ?? 0);
        if (!$idEst) $idEst = $this->repository->getPrimerEstablecimientoId($idEmpresa);

        // Guardar casilleros de IVA por tipo de documento y tarifa
        $casilleros = $data['iva_casilleros'] ?? [];
        foreach ($casilleros as $tipoDocumento => $tarifas) {
            foreach ($tarifas as $idTarifa => $valores) {
                $this->repository->updateIvaCasillero(
                    $idEmpresa,
                    (int) $idTarifa,
                    $tipoDocumento,
                    [
                        'bruto'    => $valores['bruto'] ?? '',
                        'neto'     => $valores['neto'] ?? '',
                        'impuesto' => $valores['impuesto'] ?? '',
                    ]
                );
            }
        }

        // Guardar casilleros de retenciones SRI por empresa
        $retCasilleros = $data['ret_casilleros'] ?? [];
        foreach ($retCasilleros as $idRetencion => $valores) {
            $this->repository->updateRetencionCasillero(
                $idEmpresa,
                (int) $idRetencion,
                [
                    'cas_compras' => $valores['cas_compras'] ?? '',
                    'cas_ventas'  => $valores['cas_ventas']  ?? '',
                ]
            );
        }

        return true;
    }

    public function cargarCasilleros104Default(int $idEmpresa): bool
    {
        $path = MVC_ROOT . '/config/sri_104_defaults.json';
        if (!file_exists($path)) {
            throw new \Exception('El archivo de configuración estándar no existe.');
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);
        if (!$data) {
            throw new \Exception('Error al leer el archivo de configuración estándar.');
        }

        $tarifas = (new \App\models\TarifaIva())->getAll();
        $retenciones = (new \App\models\RetencionSri())->getAll(); // Assuming this model exists or we just use repository

        // Helper map para tarifas: '15' -> ID_TARIFA
        $mapTarifas = [];
        foreach ($tarifas as $t) {
            // El porcentaje_iva a veces viene como "15" o "15.00"
            $pct = (float)$t['porcentaje_iva'];
            if ($pct == 0 && strtoupper($t['tarifa']) === 'NO OBJETO DE IVA') {
                $mapTarifas['NO_OBJETO'] = $t['id'];
            } elseif ($pct == 0 && strtoupper($t['tarifa']) === 'EXENTO DE IVA') {
                $mapTarifas['EXENTO'] = $t['id'];
            } else {
                // Eliminamos decimales para comparar fácil (ej. 15.00 -> 15)
                $mapTarifas[(string)round($pct)] = $t['id'];
            }
        }

        $this->repository->clearIvaCasilleros($idEmpresa);

        // Guardamos los documentos de IVA
        $documentosIva = ['factura_venta', 'nota_credito_venta', 'factura_compra', 'nota_venta_compra', 'nota_credito_compra', 'nota_debito_compra', 'liquidacion_compra'];
        foreach ($documentosIva as $doc) {
            if (isset($data[$doc])) {
                foreach ($data[$doc] as $porcentajeKey => $casilleros) {
                    if (isset($mapTarifas[$porcentajeKey])) {
                        $this->repository->updateIvaCasillero(
                            $idEmpresa,
                            $mapTarifas[$porcentajeKey],
                            $doc,
                            [
                                'bruto'    => $casilleros['bruto'] ?? '',
                                'neto'     => $casilleros['neto'] ?? '',
                                'impuesto' => $casilleros['impuesto'] ?? ''
                            ]
                        );
                    }
                }
            }
        }

        // Guardamos las retenciones
        if (isset($data['retencion_iva'])) {
            $retencionesList = $this->repository->getRetencionesSriIva();
            $mapRetenciones = [];
            foreach ($retencionesList as $r) {
                $pct = (string)round((float)$r['porcentaje']);
                $mapRetenciones[$pct] = $r['id'];
            }

            foreach ($data['retencion_iva'] as $pct => $casilleros) {
                if (isset($mapRetenciones[$pct])) {
                    $this->repository->updateRetencionCasillero(
                        $idEmpresa,
                        $mapRetenciones[$pct],
                        [
                            'cas_compras' => $casilleros['compras'] ?? '',
                            'cas_ventas'  => $casilleros['ventas'] ?? ''
                        ]
                    );
                }
            }
        }

        return true;
    }

    public function saveFacturacionConfig(int $idEmpresa, array $data): bool
    {
        $idEst = (int) ($data['id_establecimiento'] ?? 0);
        if (!$idEst) $idEst = $this->repository->getPrimerEstablecimientoId($idEmpresa);

        $boolFields = [
            'facturacion_inventario', 'facturacion_libre',
            'factura_solo_stock_positivo',
            'obligatorio_lotes', 'obligatorio_caducidad', 'obligatorio_nup',
            'mostrar_cajero_factura', 'mostrar_vendedor_factura',
            'mostrar_unidad_medida',
            'editar_precio_factura', 'editar_iva_factura', 'editar_descuento_factura',
            'mostrar_propina_factura',
        ];
        foreach ($boolFields as $bf) {
            $data[$bf] = isset($data[$bf]) ? 'true' : 'false';
        }

        // Forma de pago SRI predeterminada
        $fpSri = $data['id_forma_pago_sri_def'] ?? '';
        if ($fpSri !== '' && is_numeric($fpSri) && (int)$fpSri > 0) {
            $data['id_forma_pago_sri_def'] = (int)$fpSri;
        } else {
            $data['id_forma_pago_sri_def'] = 'NULL';
        }

        $fields = array_merge($boolFields, ['valor_limite_consumidor_final', 'id_forma_pago_sri_def', 'calculo_iva_facturacion']);
        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->repository->updateEstablecimientoConfig($idEst, $filtered);
    }

    public function saveInventarioConfig(int $idEmpresa, array $data): bool
    {
        $idEst = (int) ($data['id_establecimiento'] ?? 0);
        if (!$idEst) $idEst = $this->repository->getPrimerEstablecimientoId($idEmpresa);
        $fields = ['metodo_costeo'];
        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->repository->updateEstablecimientoConfig($idEst, $filtered);
    }

    public function saveCorreo(int $idEmpresa, array $data): bool
    {
        return $this->repository->saveCorreoConfig($idEmpresa, $data);
    }

    public function uploadFirma(int $idEmpresa, ?array $file, string $password, bool $forzar = false): array
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'msg' => 'Error al subir archivo'];
        }

        $dir = MVC_ROOT . "/storage/firmas/empresa_{$idEmpresa}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $pkcs12 = file_get_contents($file['tmp_name']);
        $certs = [];
        $readOk = openssl_pkcs12_read($pkcs12, $certs, $password);

        if (!$readOk) {
            $errors = [];
            while ($err = openssl_error_string()) {
                $errors[] = $err;
            }
            $errorStr = implode(' ', $errors);

            $opensslCmd = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'c:\\xampp\\apache\\bin\\openssl.exe' : 'openssl';
            
            if (file_exists(str_replace('"', '', $opensslCmd)) || strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                $tmpFile = tempnam(sys_get_temp_dir(), 'p12');
                file_put_contents($tmpFile, $pkcs12);
                
                putenv("P12_PASS=" . $password);

                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' && file_exists('c:\\xampp\\php\\extras\\ssl\\legacy.dll')) {
                    putenv("OPENSSL_MODULES=c:\\xampp\\php\\extras\\ssl");
                }

                $cmd = "$opensslCmd pkcs12 -in " . escapeshellarg($tmpFile) . " -nokeys -clcerts -legacy -passin env:P12_PASS 2>&1";
                $output = shell_exec($cmd);
                
                putenv("P12_PASS=");
                putenv("OPENSSL_MODULES=");
                @unlink($tmpFile);

                if (strpos($output, 'BEGIN CERTIFICATE') !== false) {
                    $certs['cert'] = $output; 
                    $readOk = true;
                }
            }

            if (!$readOk) {
                // Solo devolvemos mensaje amigable, se omite el log detallado por petición del usuario
                return ['ok' => false, 'msg' => 'Contraseña incorrecta o archivo de firma inválido'];
            }
        }

        $certData = openssl_x509_parse($certs['cert']);
        if (!$certData) {
            return ['ok' => false, 'msg' => 'No se pudo leer la información del certificado'];
        }

        $validToTime = $certData['validTo_time_t'] ?? null;
        $validFromTime = $certData['validFrom_time_t'] ?? null;

        $validTo = $validToTime ? date('Y-m-d H:i:s', $validToTime) : null;
        $validFrom = $validFromTime ? date('Y-m-d H:i:s', $validFromTime) : null;

        $now = time();
        if ($validToTime && $validToTime < $now) {
            return ['ok' => false, 'msg' => 'La firma electrónica se encuentra caducada (Venció el ' . date('d-m-Y', $validToTime) . ')'];
        }

        $empresaActual = $this->repository->getEmisorConfig($idEmpresa);
        $rucEmpresa = preg_replace('/\D/', '', $empresaActual['ruc'] ?? '');
        
        $subject = $certData['subject'] ?? [];
        $exts = $certData['extensions'] ?? [];

        $rucFirma = '';
        
        if (isset($exts['1.3.6.1.4.1.47286.102.3.11'])) {
            $rucFirma = $exts['1.3.6.1.4.1.47286.102.3.11'];
        } elseif (isset($exts['1.3.6.1.4.1.37746.3.11'])) {
            $rucFirma = $exts['1.3.6.1.4.1.37746.3.11'];
        } elseif (isset($subject['organizationIdentifier'])) {
            $rucFirma = $subject['organizationIdentifier'];
        }
        
        if (empty($rucFirma) && isset($subject['serialNumber'])) {
            $rucFirma = $subject['serialNumber'];
        }

        $rucFirma = preg_replace('/\D/', '', $rucFirma);

        $validacionDebil = empty($rucFirma) || strlen($rucFirma) < 10;

        if (!$validacionDebil && !empty($rucEmpresa)) {
            if (substr($rucFirma, 0, 10) !== substr($rucEmpresa, 0, 10)) {
                return ['ok' => false, 'msg' => 'La firma electrónica no pertenece a esta empresa (RUC no coincide). RUC en firma: ' . $rucFirma];
            }
        }

        if ($validacionDebil && !$forzar) {
            return [
                'ok' => false, 
                'confirm' => true, 
                'msg' => 'No se pudo validar el RUC en la firma (RUC no detectado). ¿Desea continuar y guardar la firma de todos modos?'
            ];
        }

        // Crear la carpeta con el número de RUC
        $folderName = !empty($rucEmpresa) ? $rucEmpresa : "empresa_{$idEmpresa}";
        $dir = MVC_ROOT . "/storage/firmas/{$folderName}";
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = "firma_" . time() . "_" . uniqid() . ".p12";
        $dest = $dir . "/" . $filename;
        file_put_contents($dest, $pkcs12);

        // Obtener todas las empresas con el mismo RUC
        $empresasRuc = $this->repository->getEmpresasByRuc($rucEmpresa);
        $idsActualizados = [];

        foreach ($empresasRuc as $empresa) {
            $idEmpRuc = (int) $empresa['id'];
            $idsActualizados[] = $idEmpRuc;

            $this->repository->saveFirma($idEmpRuc, [
                'archivo_nombre' => $file['name'],
                'archivo_ruta' => $dest,
                'password_firma' => $password,
                'fecha_emision' => $validFrom,
                'fecha_expiracion' => $validTo
            ]);
        }

        // Asegurarnos de que la empresa actual siempre reciba la firma (incluso si no tiene RUC)
        if (!in_array($idEmpresa, $idsActualizados)) {
            $this->repository->saveFirma($idEmpresa, [
                'archivo_nombre' => $file['name'],
                'archivo_ruta' => $dest,
                'password_firma' => $password,
                'fecha_emision' => $validFrom,
                'fecha_expiracion' => $validTo
            ]);
        }

        return ['ok' => true, 'msg' => 'Firma cargada correctamente'];
    }

    public function savePunto(int $idEmpresa, array $data): array
    {
        $idPunto = (int) ($data['id'] ?? 0);
        if ($idPunto > 0) {
            // No permitir editar un punto de emisión que ya tiene documentos asociados
            $usos = $this->repository->puntoEmisionEnUso($idPunto, $idEmpresa);
            if (!empty($usos)) {
                throw new \Exception(
                    'No se puede editar este punto de emisión porque ya está siendo utilizado en: ' .
                    implode(', ', $usos) . '. Puede crear uno nuevo si lo requiere.'
                );
            }
            $ok = $this->repository->updatePuntoEmision($idPunto, $idEmpresa, $data);
            return ['ok' => $ok];
        } else {
            $id = $this->repository->savePuntoEmision($idEmpresa, $data);
            return ['ok' => $id > 0, 'id' => $id];
        }
    }

    public function deletePunto(int $idPunto, int $idEmpresa): bool
    {
        // No permitir eliminar un punto de emisión que ya tiene documentos asociados
        $usos = $this->repository->puntoEmisionEnUso($idPunto, $idEmpresa);
        if (!empty($usos)) {
            throw new \Exception(
                'No se puede eliminar este punto de emisión porque ya está siendo utilizado en: ' .
                implode(', ', $usos) . '.'
            );
        }
        return $this->repository->deletePuntoEmision($idPunto, $idEmpresa);
    }

    public function saveSecuenciales(int $idPunto, array $secuenciales, int $idEmpresa): bool
    {
        foreach ($secuenciales as $key => $data) {
            $nombre = trim($data['nombre'] ?? '');
            $valor  = (int) ($data['valor'] ?? 1);
            if ($nombre === '') continue;
            if (is_numeric($key) && (int) $key > 0) {
                $this->repository->updateSecuencialById((int) $key, $nombre, $valor, $idEmpresa);
            } else {
                $this->repository->updateSecuencial($idPunto, $nombre, $valor, $idEmpresa);
            }
        }
        return true;
    }

    public function crearSecuencialesIniciales(int $idPunto, int $idEmpresa): bool
    {
        return $this->repository->crearSecuencialesIniciales($idPunto, $idEmpresa);
    }

    public function getSecuencialesByPunto(int $idPunto, int $idEmpresa): array
    {
        return $this->repository->getSecuencialesByPunto($idPunto, $idEmpresa);
    }

    public function saveIce(int $idEmpresa, array $data): bool
    {
        $data['id_empresa'] = $idEmpresa;
        return $this->repository->saveIce($data);
    }

    public function deleteIce(int $id, int $idEmpresa): bool
    {
        return $this->repository->deleteIce($id, $idEmpresa);
    }
}
