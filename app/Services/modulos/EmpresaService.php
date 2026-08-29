<?php

namespace App\services\modulos;

use App\repositories\modulos\EmpresaRepository;
use App\repositories\modulos\SuscripcionesRepository;
use App\repositories\SecuencialRepository;

class EmpresaService
{
    private $repository;
    private SecuencialRepository $secuencialRepository;

    public function __construct()
    {
        $this->repository = new EmpresaRepository();
        $this->secuencialRepository = new SecuencialRepository();
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
            // Recargo por servicio: consulta aparte porque se tolera que su
            // migración todavía no esté (ver getConfigServicioRestaurante).
            $empresa = array_merge($empresa ?? [], $this->repository->getConfigServicioRestaurante($idEstPrincipal));
        }

        // Suscripción del sistema — regla de resolución:
        //   a) Si la empresa tiene una SUSCRIPCIÓN específica vinculada (id_suscripcion),
        //      esa manda: se muestra solo esa. Resuelve el caso de reventa en que el
        //      cliente facturado tiene varias suscripciones (una por cada empresa suya).
        //   b) Si hay CLIENTE de reventa seleccionado (id_cliente_facturado), se buscan
        //      las suscripciones de ese cliente y la tarjeta muestra solo estado/
        //      periodicidad/vigencia (sin montos, para no exponer precio). Si hay MÁS DE
        //      UNA y ninguna está vinculada, no se muestra detalle: se avisa que debe
        //      asignarse cuál corresponde a esta empresa.
        //   c) Si no hay selección, se aplica la regla por RUC propio contra la controladora
        //      (con montos y detalle).
        //   d) Si no encuentra nada, la vista cae al fallback manual (datos de `empresas`).
        // La controladora se resuelve por RUC (vínculo directo, empresa hermana con el mismo
        // RUC, o administradora por defecto).
        $suscripcionInfo = [];
        $rucEmpresa = trim((string) ($empresa['ruc'] ?? ''));
        $idControladora = 0;
        $sinValores = false;
        $variasSuscripciones = 0; // >0 => hay que elegir cuál corresponde a esta empresa
        if ($rucEmpresa !== '') {
            try {
                $idDirecto = isset($empresa['id_empresa_suscripciones']) && (int) $empresa['id_empresa_suscripciones'] > 0
                    ? (int) $empresa['id_empresa_suscripciones'] : null;
                $idControladora = (int) ($this->repository->resolverEmpresaControladoraSuscripciones($rucEmpresa, $idDirecto) ?? 0);

                if ($idControladora > 0) {
                    $suscRepo = new SuscripcionesRepository();
                    $idClienteFact = isset($empresa['id_cliente_facturado']) && (int) $empresa['id_cliente_facturado'] > 0
                        ? (int) $empresa['id_cliente_facturado'] : 0;
                    $idSuscripcion = isset($empresa['id_suscripcion']) && (int) $empresa['id_suscripcion'] > 0
                        ? (int) $empresa['id_suscripcion'] : 0;

                    if ($idSuscripcion > 0) {
                        // 1) Suscripción específica vinculada a esta empresa: solo esa.
                        $suscripcionInfo = $suscRepo->getResumenPorSuscripcion($idControladora, $idSuscripcion);
                        // Sin montos solo si es reventa (se factura a un tercero).
                        $sinValores = !empty($suscripcionInfo) && $idClienteFact > 0;
                    } elseif ($idClienteFact > 0) {
                        // 2) Reventa sin suscripción vinculada.
                        $lista = $suscRepo->getResumenPorControladoraYCliente($idControladora, $idClienteFact);
                        if (count($lista) > 1) {
                            // Varias: no se puede saber cuál es la de esta empresa.
                            $variasSuscripciones = count($lista);
                            $suscripcionInfo = [];
                        } else {
                            $suscripcionInfo = $lista;
                            $sinValores = !empty($lista);
                        }
                    } else {
                        // 3) Sin selección: regla por RUC propio contra la controladora.
                        $suscripcionInfo = $suscRepo->getResumenPorControladoraYRuc($idControladora, $rucEmpresa);
                    }
                }
            } catch (\Throwable $e) {
                // Módulo de suscripciones o migración no disponible: se usa el fallback manual.
                $suscripcionInfo = [];
                $sinValores = false;
                $variasSuscripciones = 0;
            }
        }

        // Puntos de emisión, marcando cuáles ya tienen documentos (para la UI:
        // bloquear código en esos) y cuáles se pueden eliminar (sin documentos,
        // o con documentos pero con otro punto del mismo número disponible en
        // otro establecimiento — ver EmpresaService::deletePunto()).
        $puntos = $this->repository->getPuntosEmision($idEmpresa);
        foreach ($puntos as &$pto) {
            $enUso = !empty($this->repository->puntoEmisionEnUso((int) $pto['id'], $idEmpresa));
            $pto['en_uso'] = $enUso;
            $pto['puede_eliminar'] = !$enUso || $this->repository->existeOtroPuntoConCodigo(
                $idEmpresa,
                (string) ($pto['codigo_punto'] ?? ''),
                (int) $pto['id']
            );
        }
        unset($pto);

        return [
            'empresa'               => $empresa,
            'suscripcion_info'      => $suscripcionInfo,
            'suscripcion_controladora' => $idControladora,
            'suscripcion_sin_valores'  => $sinValores,
            'suscripcion_varias'       => $variasSuscripciones,
            'correo'                => $this->repository->getCorreoConfig($idEmpresa),
            'firmas'                => $this->repository->getFirmas($idEmpresa),
            'establecimientos'      => $establecimientos,
            'puntos'                => $puntos,
            'iva_casilleros'        => $this->repository->getIvaCasilleros($idEmpresa),
            'ices'                  => $this->repository->getIces($idEmpresa),
            'retenciones_sri_iva'   => $this->repository->getRetencionesSriIva(),
            'retenciones_casilleros' => $this->repository->getRetencionesCasilleros($idEmpresa),
            'usuarios_empresa'      => $this->repository->getUsuariosAsignados($idEmpresa),
        ];
    }
    
    /**
     * Marca $idEmpresa como matriz de su grupo RUC. Si otra empresa del grupo ya es matriz y
     * $forzar es false, no cambia nada y devuelve confirm=true (mismo patrón que uploadFirma:
     * el JS genérico de la vista reintenta automáticamente con forzar=1 tras confirmar) — una
     * sola empresa puede ser matriz por RUC.
     */
    public function marcarEstablecimientoMatriz(int $idEmpresa, int $idUsuario, bool $forzar = false): array
    {
        $otra = $this->repository->getOtraMatrizDelGrupo($idEmpresa);
        if ($otra && !$forzar) {
            return [
                'ok' => false,
                'confirm' => true,
                'msg' => 'Ya hay otro establecimiento marcado como matriz (' . $otra['establecimiento'] . ' - «' . $otra['nombre'] . '»). ¿Desea cambiarlo por este?',
            ];
        }

        $this->repository->marcarMatriz($idEmpresa);
        (new \App\Services\LogSistemaService())->registrar(
            $idUsuario, $idEmpresa, 'MARCAR_MATRIZ', 'empresas', $idEmpresa,
            $otra ? ['matriz_anterior' => $otra['id']] : null,
            ['es_matriz' => true]
        );

        return ['ok' => true, 'msg' => 'Establecimiento marcado como matriz.'];
    }

    public function saveEstablecimiento(int $idEmpresa, array $data, array $files = []): array
    {
        $idEst = (int) ($data['id'] ?? 0);
        
        // Manejar subida de logo
        if (!empty($files['logo_establecimiento']) && $files['logo_establecimiento']['error'] === UPLOAD_ERR_OK) {
            $file = $files['logo_establecimiento'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            // El destino está dentro de public/: sin lista blanca, un archivo
            // .php subido aquí quedaría accesible por URL y el servidor lo
            // ejecutaría. Mismo criterio que productos y menú.
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $permitidas, true)) {
                throw new \Exception('Formato de logo no permitido. Use JPG, PNG, GIF o WEBP.');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new \Exception('El logo excede los 2MB.');
            }

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
        // Agente de retención: el SRI (XSD, tipo agenteRetencion) exige SOLO
        // dígitos, máximo 8. Se normaliza y valida antes de guardar para no
        // almacenar texto que luego rechazaría el SRI.
        if (array_key_exists('agente_retencion', $data)) {
            $raw = trim((string) $data['agente_retencion']);
            if ($raw === '' || in_array(strtoupper($raw), ['NO', 'N/A', '0'], true)) {
                // Empresa que NO es agente de retención.
                $data['agente_retencion'] = '';
            } elseif (preg_match('/^\d{1,8}$/', $raw)) {
                $data['agente_retencion'] = $raw;
            } else {
                throw new \InvalidArgumentException(
                    'El campo «Agente de Retención» debe ser el número de resolución: solo dígitos, máximo 8 '
                    . '(según la ficha técnica del SRI). Ejemplo: 1. Déjelo vacío si la empresa no es agente de retención.'
                );
            }
        }

        // tipo_emision: único valor SRI soportado hoy es '1' (Normal). El <select>
        // del formulario solo ofrece esa opción, pero sin validar aquí, cualquier
        // otro valor que llegara se guardaba tal cual — y como la columna
        // ventas_cabecera.tipo_emision es VARCHAR(5), algo más largo que eso
        // (p. ej. el texto "Normal" en vez del código "1") revienta al emitir
        // cualquier factura con un error críptico de Postgres, no al guardar
        // esta configuración.
        if (array_key_exists('tipo_emision', $data) && (string) $data['tipo_emision'] !== '1') {
            throw new \InvalidArgumentException(
                "El campo «Tipo Emisión» solo admite el valor '1' (Normal) según el SRI."
            );
        }

        $fields = [
            'resolucion_contribuyente', 'id_tipo_regimen', 'tipo_ambiente',
            'agente_retencion', 'tipo_emision'
        ];
        $filtered = array_intersect_key($data, array_flip($fields));

        // Empresa demo (p. ej. la cuenta de prueba para revisores de Apple/Google):
        // nunca debe poder pasar a Producción, sin importar quién lo intente ni
        // qué envíe el formulario — refuerzo del lado servidor, no solo deshabilitar
        // el <select> en la vista.
        if (array_key_exists('tipo_ambiente', $filtered)
            && (string) $filtered['tipo_ambiente'] !== '1'
            && $this->repository->esDemo($idEmpresa)) {
            throw new \InvalidArgumentException(
                'Esta empresa está marcada como demo y no puede cambiar a ambiente de Producción.'
            );
        }

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

        // Interruptor del régimen de liquidación diferida de IVA por ventas a plazo (480-499).
        // Apagado por defecto: no afecta a las empresas que no lo usan.
        $this->repository->updateUsaLiquidacionDiferidaIva($idEmpresa, !empty($data['usa_liquidacion_diferida_iva']));

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
        $documentosIva = ['factura_venta', 'nota_credito_venta', 'factura_compra', 'nota_venta_compra', 'nota_credito_compra', 'nota_debito_compra', 'liquidacion_compra', 'importacion', 'importacion_activo_fijo'];
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
            'factura_item_mostrar_unidad', 'factura_item_mostrar_lote',
            'factura_item_mostrar_caducidad', 'factura_item_mostrar_nup',
        ];
        foreach ($boolFields as $bf) {
            $data[$bf] = isset($data[$bf]) ? 'true' : 'false';
        }

        // Agrupación de ítems en PDF/XML: solo valores del catálogo cerrado.
        $agrupar = strtolower(trim((string) ($data['factura_agrupar_items'] ?? 'no')));
        $data['factura_agrupar_items'] = in_array($agrupar, ['lote', 'nup'], true) ? $agrupar : 'no';

        // Forma de pago SRI predeterminada
        $fpSri = $data['id_forma_pago_sri_def'] ?? '';
        if ($fpSri !== '' && is_numeric($fpSri) && (int)$fpSri > 0) {
            $data['id_forma_pago_sri_def'] = (int)$fpSri;
        } else {
            $data['id_forma_pago_sri_def'] = 'NULL';
        }

        // Tarifa de IVA que se preselecciona al crear un ítem libre (solo aplica
        // si facturacion_libre está activo, pero se guarda igual aunque esté
        // apagado por si se vuelve a activar después).
        $ivaLibreDef = $data['id_tarifa_iva_defecto_libre'] ?? '';
        if ($ivaLibreDef !== '' && is_numeric($ivaLibreDef) && (int)$ivaLibreDef > 0) {
            $data['id_tarifa_iva_defecto_libre'] = (int)$ivaLibreDef;
        } else {
            $data['id_tarifa_iva_defecto_libre'] = 'NULL';
        }

        $fields = array_merge($boolFields, [
            'valor_limite_consumidor_final', 'id_forma_pago_sri_def', 'calculo_iva_facturacion',
            'factura_agrupar_items', 'id_tarifa_iva_defecto_libre',
        ]);

        // Recargo por servicio del POS Restaurante: se emite como propina, y la
        // Ficha Técnica del SRI la topa al 10% del subtotal — de ahí el máximo.
        // Solo se guarda si la migración ya creó las columnas; si no, guardar el
        // resto de la configuración de Facturación seguiría funcionando igual.
        if ($this->repository->tieneColumnasServicioRestaurante()) {
            $modoServicio = strtolower(trim((string) ($data['servicio_restaurante'] ?? 'no')));
            $data['servicio_restaurante'] = in_array($modoServicio, ['obligatorio', 'opcional'], true) ? $modoServicio : 'no';
            // El recargo se emite EN el campo de propina del comprobante: si ese
            // campo está desactivado, no hay dónde ponerlo. Se guarda apagado en
            // vez de dejar una configuración que la operación ignoraría.
            if ($data['mostrar_propina_factura'] !== 'true') {
                $data['servicio_restaurante'] = 'no';
            }
            $fields[] = 'servicio_restaurante';

            // El porcentaje solo se toca si vino en el formulario: con la
            // propina apagada el campo va deshabilitado y el navegador no lo
            // envía, y no hay razón para pisar el valor que el local tenía
            // configurado.
            if (isset($data['servicio_restaurante_porcentaje']) && $data['servicio_restaurante_porcentaje'] !== '') {
                $pctServicio = (float) $data['servicio_restaurante_porcentaje'];
                // Sin recargo no hay porcentaje que guardar: se deja en 0 para
                // que lo almacenado diga lo mismo que la pantalla.
                $data['servicio_restaurante_porcentaje'] = $data['servicio_restaurante'] === 'no'
                    ? 0.0
                    : round(max(0.0, min(10.0, $pctServicio)), 2);
                $fields[] = 'servicio_restaurante_porcentaje';
            }
        }

        // Producto con el que se emite la propina VOLUNTARIA de las comandas
        // (una línea más del detalle). Va aparte del recargo de arriba y no
        // depende de él ni del campo de propina del comprobante. Vacío = la
        // función queda desactivada.
        if ($this->repository->tieneColumnaProductoPropina()) {
            $idProdPropina = $data['id_producto_propina'] ?? '';
            $data['id_producto_propina'] = ($idProdPropina !== '' && is_numeric($idProdPropina) && (int) $idProdPropina > 0)
                ? (int) $idProdPropina
                : 'NULL';
            $fields[] = 'id_producto_propina';
        }

        $filtered = array_intersect_key($data, array_flip($fields));
        return $this->repository->updateEstablecimientoConfig($idEst, $filtered);
    }

    /**
     * Configuración de inventario del establecimiento. La aprobación de cargas
     * (y la de pagos al banco, que tenía su propia pestaña) se movió al módulo
     * Aprobaciones: se configura por empresa en /modulos/aprobaciones-config.
     */
    public function saveInventarioConfig(int $idEmpresa, array $data): bool
    {
        $idEst = (int) ($data['id_establecimiento'] ?? 0);
        if (!$idEst) $idEst = $this->repository->getPrimerEstablecimientoId($idEmpresa);

        return $this->repository->updateEstablecimientoConfig($idEst, [
            'metodo_costeo' => $data['metodo_costeo'] ?? 'promedio',
        ]);
    }

    public function saveCorreo(int $idEmpresa, array $data): bool
    {
        return $this->repository->saveCorreoConfig($idEmpresa, $data);
    }

    public function testCorreo(int $idEmpresa, array $data, string $destino): array
    {
        $tipoCorreo = $data['tipo_correo'] ?? 'camagare';
        $emisor = $this->repository->getEmisorConfig($idEmpresa);
        $nombreEmpresa = $emisor['nombre_comercial'] ?? $emisor['nombre'] ?? '';

        $envioService = new \App\Services\EnvioDocumentosSRIService();
        return $envioService->enviarCorreoPrueba($tipoCorreo, $data, $destino, $nombreEmpresa);
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
        } elseif (isset($exts['1.3.6.1.4.1.37947.3.11'])) {
            // ECIBCE - Banco Central del Ecuador: el RUC va en esta extensión
            // (el serialNumber del subject es la cédula de la persona, no el RUC).
            $rucFirma = $exts['1.3.6.1.4.1.37947.3.11'];
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
            // Si el punto ya tiene documentos, se puede cambiar el NOMBRE y el ESTADO
            // (activar/inhabilitar), pero NO el código ni el establecimiento, porque eso
            // rompería la numeración/identidad de los documentos ya emitidos.
            $usos = $this->repository->puntoEmisionEnUso($idPunto, $idEmpresa);
            if (!empty($usos)) {
                $actual = $this->repository->getPuntoEmision($idPunto, $idEmpresa) ?? [];
                $normCod = static fn($v) => str_pad((string) (int) preg_replace('/\D/', '', (string) $v), 3, '0', STR_PAD_LEFT);
                $codNuevo = isset($data['codigo_punto']) ? $normCod($data['codigo_punto']) : $normCod($actual['codigo_punto'] ?? '');
                $codActual = $normCod($actual['codigo_punto'] ?? '');
                $estNuevo = (int) ($data['id_establecimiento'] ?? ($actual['id_establecimiento'] ?? 0));
                $estActual = (int) ($actual['id_establecimiento'] ?? 0);

                if ($codNuevo !== $codActual || $estNuevo !== $estActual) {
                    throw new \Exception(
                        'Este punto de emisión ya está siendo utilizado en: ' . implode(', ', $usos) .
                        '. No se puede cambiar su código ni su establecimiento (rompería la numeración). ' .
                        'Sí puede cambiar el nombre o inhabilitarlo. Para otro código, cree un punto nuevo.'
                    );
                }
                // Solo cambió nombre/estado: se fuerza a conservar código y establecimiento.
                $data['codigo_punto']       = $codActual;
                $data['id_establecimiento'] = $estActual;
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
        // Un punto con documentos solo se puede eliminar si queda al menos otro
        // punto con el MISMO número en otro establecimiento de la empresa (decisión
        // explícita del usuario: acepta que, si ese establecimiento+punto específico
        // se reutiliza más adelante, el secuencial podría chocar con los documentos
        // que ya tenía). Si no queda ningún otro con ese número, se sigue bloqueando.
        $usos = $this->repository->puntoEmisionEnUso($idPunto, $idEmpresa);
        if (!empty($usos)) {
            $punto = $this->repository->getPuntoEmision($idPunto, $idEmpresa);
            $codigo = (string) ($punto['codigo_punto'] ?? '');
            if ($codigo === '' || !$this->repository->existeOtroPuntoConCodigo($idEmpresa, $codigo, $idPunto)) {
                throw new \Exception(
                    'No se puede eliminar este punto de emisión porque ya está siendo utilizado en: ' .
                    implode(', ', $usos) . '. Además, no queda ningún otro punto con el mismo número en esta empresa.'
                );
            }
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
                // Tipo nuevo en este punto: bloquear si comparte codDoc SRI con un tipo
                // ya configurado aquí (ej. Facturas de venta / Facturas de reembolso son
                // ambas codDoc=01 — compartir punto duplicaría el número de documento).
                $conflicto = $this->secuencialRepository->getConflictoCodDoc($idPunto, $nombre, $idEmpresa);
                if ($conflicto !== null) {
                    throw new \Exception(
                        "No se puede configurar \"{$nombre}\" en este punto de emisión: ya tiene configurado " .
                        "\"{$conflicto}\", que ante el SRI es el mismo tipo de comprobante (mismo codDoc). " .
                        "Compartir el punto duplicaría el número de documento entre ambos. Use una serie distinta."
                    );
                }

                // Tipos de un único punto por empresa (ej. Facturas de reembolso):
                // bloquear si ya está configurado en otro punto de esta empresa.
                $idPuntoConTipo = $this->secuencialRepository->getPuntoConTipoUnico($nombre, $idEmpresa, $idPunto);
                if ($idPuntoConTipo !== null) {
                    throw new \Exception(
                        "No se puede configurar \"{$nombre}\" en este punto de emisión: ya está configurado en otro " .
                        "punto de esta empresa, y este tipo de documento solo puede existir en un único punto."
                    );
                }

                $this->repository->updateSecuencial($idPunto, $nombre, $valor, $idEmpresa);
            }
        }
        return true;
    }

    public function getSecuencialesByPunto(int $idPunto, int $idEmpresa): array
    {
        return $this->repository->getSecuencialesByPunto($idPunto, $idEmpresa);
    }

    /**
     * Elimina (lógicamente) un tipo de secuencial de un punto de emisión.
     * Bloquea si ya hay documentos emitidos con ese tipo en ese punto: borrarlo
     * dejaría sin control la numeración de comprobantes ya generados.
     */
    public function deleteSecuencial(int $id, int $idEmpresa): bool
    {
        $row = $this->repository->getSecuencialById($id, $idEmpresa);
        if (!$row) {
            throw new \Exception('El tipo de secuencial no existe o no pertenece a esta empresa.');
        }

        $maxUsado = $this->secuencialRepository->getMaxSecuencialUsado((int) $row['id_punto_emision'], (string) $row['tipo_documento']);
        if ($maxUsado > 0) {
            throw new \Exception(
                'No se puede eliminar "' . $row['tipo_documento'] . '": ya hay documentos emitidos con este tipo en este punto de emisión.'
            );
        }

        return $this->repository->deleteSecuencial($id, $idEmpresa);
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
