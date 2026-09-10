<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use App\repositories\modulos\RetencionCompraRepository;
use Exception;

/**
 * Validaciones de negocio del comprobante de retención en compras.
 *
 * Dos niveles, a propósito distintos (mismo criterio que AnexoDividendosRules):
 *
 *  - ERRORES: lo que hace inválido al comprobante y el SRI rechaza, o lo que
 *    dejaría un registro inconsistente (documento de otro proveedor, base mayor
 *    que la del documento, código inexistente…). Lanzan excepción: no se guarda.
 *
 *  - ADVERTENCIAS: criterio tributario, donde el contador puede tener razón y el
 *    sistema no puede decidir por él (plazo de emisión, porcentaje distinto al
 *    del catálogo, base bajo el mínimo…). No lanzan: se devuelven para que el
 *    usuario las confirme expresamente antes de guardar. NO se dejan pasar solas
 *    — sin confirmación el guardado se detiene igual (ver RetencionCompraService).
 *
 * Los documentos importados desde el SRI (`origen = electronico`) ya están
 * autorizados: se registran tal cual llegan y no generan advertencias.
 */
class RetencionCompraRules
{
    /**
     * Sustento normativo de cada advertencia, para que el usuario pueda ir a
     * comprobarlo en lugar de creerse el aviso. Se muestran junto al mensaje.
     *
     * Están centralizadas a propósito: cuando el SRI reforma una resolución solo
     * hay que corregir la cita aquí, no perseguirla por los mensajes.
     */
    private const REF_PLAZO = 'Art. 50 de la Ley de Régimen Tributario Interno: '
        . 'el comprobante de retención se entrega dentro de un término de cinco días de recibido el comprobante de venta.';

    private const REF_PORCENTAJES_RENTA = 'Resolución NAC-DGERCGC14-00787 y sus reformas '
        . '(porcentajes de retención en la fuente de impuesto a la renta).';

    private const REF_PORCENTAJES_IVA = 'Resolución NAC-DGERCGC20-00000061 '
        . '(porcentajes de retención en la fuente de IVA).';

    private const REF_BASE_MINIMA = 'Resolución NAC-DGERCGC14-00787: no procede retención en la fuente '
        . 'cuando el pago o crédito en cuenta no supera los USD 50, salvo pagos periódicos al mismo proveedor.';

    private const REF_BASE_IVA = 'Resolución NAC-DGERCGC20-00000061: la retención de IVA se calcula sobre '
        . 'el IVA causado en la transacción, no sobre la base imponible del comprobante.';

    private const REF_CATALOGO = 'Catálogo de códigos de retención del SRI vigente a la fecha del comprobante '
        . '(Configuración → Retenciones SRI).';

    /** Tolerancia en dólares al comparar bases contra el documento de sustento. */
    private const TOLERANCIA = 0.02;

    /**
     * Base mínima para que proceda la retención en la fuente de renta
     * (Res. NAC-DGERCGC14-00787): bajo este valor no se retiene, salvo pagos
     * periódicos al mismo proveedor. Por eso es advertencia y no error.
     */
    public const BASE_MINIMA_RENTA = 50.00;

    /** Plazo legal para entregar el comprobante (Art. 50 LRTI): término de 5 días. */
    public const PLAZO_DIAS_HABILES = 5;

    private RetencionCompraRepository $repo;

    public function __construct(?RetencionCompraRepository $repo = null)
    {
        $this->repo = $repo ?? new RetencionCompraRepository();
    }

    /**
     * Arma una advertencia con su sustento normativo.
     *
     * @return array{texto:string, base_legal:string}
     */
    private static function aviso(string $texto, string $baseLegal = ''): array
    {
        return ['texto' => $texto, 'base_legal' => $baseLegal];
    }

    /**
     * @return array<int, array{texto:string, base_legal:string}> Advertencias que el
     *         usuario debe confirmar (vacío si no hay).
     * @throws Exception Si hay errores que impiden guardar.
     */
    public function validar(array $data): array
    {
        $esElectronico = ($data['origen'] ?? '') === 'electronico';

        $errores      = [];
        $advertencias = [];

        $this->validarCabecera($data, $esElectronico, $errores);
        $this->validarFechas($data, $esElectronico, $errores, $advertencias);
        $this->validarPeriodoFiscal($data, $esElectronico, $errores);
        $this->validarLineas($data, $esElectronico, $errores, $advertencias);

        // Un comprobante importado del SRI ya está autorizado: es un hecho
        // consumado y el sistema tiene que poder registrarlo aunque no cuadre con
        // lo que hay en la empresa (catálogo desactualizado, compra vinculada por
        // un cruce imperfecto…). Rechazarlo solo dejaría al sistema sin un
        // documento que el SRI sí tiene. Estas comprobaciones son, por eso, para
        // lo que se emite desde aquí.
        if (!$esElectronico) {
            $this->validarSujetos($data, $errores, $advertencias);
            $this->validarDocumentoVinculado($data, $errores, $advertencias);
            $this->validarBasesContraDocumento($data, $errores, $advertencias);
        }

        // Longitudes de la Ficha Técnica del SRI. Las líneas de una retención no
        // llevan texto libre (son códigos y bases), así que lo único que puede
        // exceder es la información adicional, que el generador de XML escribe
        // tal cual sin comprobar nada.
        $errores = array_merge(
            $errores,
            \App\Helpers\SriFichaTecnica::erroresInfoAdicional($data['info_adicional'] ?? [])
        );

        if (!empty($errores)) {
            throw new Exception(implode(' ', $errores));
        }

        return $esElectronico ? [] : $advertencias;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CABECERA
    // ─────────────────────────────────────────────────────────────────────────

    private function validarCabecera(array $data, bool $esElectronico, array &$errores): void
    {
        if (empty($data['id_empresa']))    $errores[] = 'El identificador de empresa es obligatorio.';
        if (empty($data['id_proveedor']))  $errores[] = 'El proveedor es obligatorio.';
        if (empty($data['fecha_emision'])) $errores[] = 'La fecha de emisión es obligatoria.';

        if (empty($data['tipo_doc_sustento'])) {
            $errores[] = 'El tipo de documento de sustento es obligatorio.';
        } elseif (!in_array($data['tipo_doc_sustento'], ['01','03','05'], true)) {
            $errores[] = 'El tipo de documento de sustento no es válido.';
        }

        if (empty($data['num_doc_sustento'])) {
            $errores[] = 'El número del documento de sustento es obligatorio.';
        } elseif (!$esElectronico && !preg_match('/^\d{3}-\d{3}-\d{9}$/', trim((string) $data['num_doc_sustento']))) {
            // El SRI exige el formato 000-000-000000000 en numDocSustento; sin esto
            // el comprobante se rechaza por estructura.
            $errores[] = 'El número del documento de sustento debe tener el formato 000-000-000000000.';
        }

        if (empty($data['fecha_emision_doc_sustento'])) {
            $errores[] = 'La fecha de emisión del documento de sustento es obligatoria.';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FECHAS Y PLAZO
    // ─────────────────────────────────────────────────────────────────────────

    private function validarFechas(array $data, bool $esElectronico, array &$errores, array &$advertencias): void
    {
        $fechaRet = trim((string) ($data['fecha_emision'] ?? ''));
        $fechaDoc = trim((string) ($data['fecha_emision_doc_sustento'] ?? ''));
        if ($fechaRet === '' || $fechaDoc === '') {
            return;
        }

        $hoy = date('Y-m-d');

        // El SRI rechaza comprobantes con fecha de emisión futura.
        if (!$esElectronico && substr($fechaRet, 0, 10) > $hoy) {
            $errores[] = 'La fecha de emisión no puede ser posterior a hoy.';
        }
        if (!$esElectronico && substr($fechaDoc, 0, 10) > $hoy) {
            $errores[] = 'La fecha del documento de sustento no puede ser posterior a hoy.';
        }

        $fRet = new \DateTime(substr($fechaRet, 0, 10));
        $fDoc = new \DateTime(substr($fechaDoc, 0, 10));

        if ($fRet < $fDoc) {
            $errores[] = 'La fecha de la retención no puede ser anterior a la del documento de sustento.';
            return;
        }

        // Plazo del Art. 50 LRTI: TÉRMINO de cinco días, es decir días hábiles
        // (sin sábados ni domingos; los feriados no se consideran). Fuera de plazo
        // la retención sigue siendo válida y hay que poder registrarla —con su
        // fecha real—, así que es advertencia y no error: el usuario la confirma.
        if (!$esElectronico) {
            $habiles = $this->diasHabilesEntre($fDoc, $fRet);
            if ($habiles > self::PLAZO_DIAS_HABILES) {
                $advertencias[] = self::aviso(
                    sprintf(
                        'La retención se está emitiendo %d días hábiles después del documento de sustento (%s). '
                        . 'El plazo legal es de %d días hábiles desde que se recibe el comprobante de venta.',
                        $habiles,
                        $fDoc->format('d-m-Y'),
                        self::PLAZO_DIAS_HABILES
                    ),
                    self::REF_PLAZO
                );
            }
        }
    }

    /**
     * Días hábiles transcurridos entre dos fechas, sin contar el día del
     * documento (el plazo empieza a correr al día siguiente) y descartando
     * sábados y domingos. No contempla feriados: no hay calendario oficial en
     * el sistema y contar de más solo adelantaría la advertencia.
     */
    private function diasHabilesEntre(\DateTime $desde, \DateTime $hasta): int
    {
        $cursor  = (clone $desde);
        $habiles = 0;
        while ($cursor < $hasta) {
            $cursor->modify('+1 day');
            $dia = (int) $cursor->format('N'); // 1=lunes … 7=domingo
            if ($dia <= 5) {
                $habiles++;
            }
        }
        return $habiles;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERÍODO FISCAL
    // ─────────────────────────────────────────────────────────────────────────

    private function validarPeriodoFiscal(array $data, bool $esElectronico, array &$errores): void
    {
        $periodo = trim((string) ($data['periodo_fiscal'] ?? ''));

        if ($periodo === '') {
            $errores[] = 'El período fiscal es obligatorio.';
            return;
        }
        if (!preg_match('/^\d{2}\/\d{4}$/', $periodo)) {
            $errores[] = 'El período fiscal debe tener el formato MM/YYYY.';
            return;
        }

        // El período fiscal es el mes en que se efectúa la retención: siempre el de
        // su fecha de emisión. El campo llega del formulario (readonly en pantalla,
        // pero editable en la petición) y si no coincide descuadra el Formulario 103.
        $fechaRet = trim((string) ($data['fecha_emision'] ?? ''));
        if (!$esElectronico && $fechaRet !== '') {
            $esperado = date('m/Y', strtotime(substr($fechaRet, 0, 10)));
            if ($periodo !== $esperado) {
                $errores[] = "El período fiscal ({$periodo}) no corresponde a la fecha de emisión de la retención "
                    . "(debe ser {$esperado}).";
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LÍNEAS Y CATÁLOGO DEL SRI
    // ─────────────────────────────────────────────────────────────────────────

    private function validarLineas(array $data, bool $esElectronico, array &$errores, array &$advertencias): void
    {
        if (empty($data['lineas']) || !is_array($data['lineas'])) {
            $errores[] = 'Debe agregar al menos una línea de retención.';
            return;
        }

        $fechaRet = substr(trim((string) ($data['fecha_emision'] ?? '')), 0, 10);

        foreach ($data['lineas'] as $i => $linea) {
            $n = $i + 1;

            // Documentos importados desde el SRI (XML autorizado) se registran tal cual
            // llegan: el SRI permite líneas de retención con base imponible 0 (p. ej. ISD,
            // casos puntuales de retención sobre valores ya retenidos en 100%), así que
            // aquí solo se exige que el dato exista y no sea negativo. Para captura
            // manual se mantiene la exigencia > 0.
            if (empty($linea['codigo_retencion'])) {
                $errores[] = "Línea {$n}: el código de retención es obligatorio.";
            }
            if (!isset($linea['base_imponible']) || (float) $linea['base_imponible'] < 0) {
                $errores[] = "Línea {$n}: la base imponible es obligatoria.";
            } elseif (!$esElectronico && (float) $linea['base_imponible'] <= 0) {
                $errores[] = "Línea {$n}: la base imponible debe ser mayor a 0.";
            }

            // El porcentaje SÍ puede ser 0: buena parte del catálogo del SRI son
            // conceptos que se informan pero no retienen —compras no sujetas a
            // retención (332), dividendos exentos, transporte público, pagos con
            // tarjeta de crédito…—. Que un 0% sea correcto o no depende del código
            // elegido, y de eso se encarga validarCodigoSri() comparando contra el
            // catálogo; aquí solo se descartan los valores imposibles.
            $porcentaje = isset($linea['porcentaje_retener']) ? (float) $linea['porcentaje_retener'] : -1;
            if ($porcentaje < 0) {
                $errores[] = "Línea {$n}: el porcentaje de retención es obligatorio y no puede ser negativo.";
            } elseif ($porcentaje > 100) {
                $errores[] = "Línea {$n}: el porcentaje de retención no puede ser mayor a 100.";
            }

            if (!$esElectronico && !empty($linea['codigo_retencion'])) {
                $this->validarCodigoSri($linea, $n, $fechaRet, $errores, $advertencias);
            }
        }
    }

    /**
     * Contrasta la línea contra el catálogo de retenciones del SRI
     * (Configuración → Retenciones SRI).
     */
    private function validarCodigoSri(
        array $linea,
        int $n,
        string $fechaRet,
        array &$errores,
        array &$advertencias
    ): void {
        $codigo = trim((string) $linea['codigo_retencion']);
        $filas  = $this->repo->getRetencionesSriPorCodigo($codigo);

        if (empty($filas)) {
            // Sin código válido la retención no se puede declarar: el Formulario 103
            // resuelve el casillero por el código, y uno inventado se pierde.
            $errores[] = "Línea {$n}: el código de retención {$codigo} no existe en el catálogo del SRI.";
            return;
        }

        // El impuesto de la línea debe ser el del código: un código de renta
        // declarado como IVA sale mal en el XML y en la declaración.
        $impuestoLinea = $this->normalizarImpuesto((string) ($linea['codigo_impuesto'] ?? ''));
        $delImpuesto   = array_values(array_filter(
            $filas,
            fn($f) => $this->normalizarImpuesto((string) ($f['impuesto_ret'] ?? '')) === $impuestoLinea
        ));

        if ($impuestoLinea === '' || empty($delImpuesto)) {
            $impuestosCodigo = array_unique(array_map(
                fn($f) => $this->normalizarImpuesto((string) ($f['impuesto_ret'] ?? '')),
                $filas
            ));
            $errores[] = "Línea {$n}: el código {$codigo} corresponde a "
                . implode(' / ', array_filter($impuestosCodigo))
                . ($impuestoLinea !== '' ? ", no a {$impuestoLinea}." : '.');
            return;
        }

        // Porcentaje: manda el catálogo (Configuración → Retenciones SRI).
        //
        //  - Con un porcentaje DEFINIDO, ese es el que se aplica y no se cambia: si la
        //    línea trae otro, se corta. El catálogo es administrable, así que corregir
        //    una tarifa reformada por el SRI se hace ahí, no retención por retención.
        //  - Con el porcentaje en 0 el concepto es de tarifa VARIABLE —dividendos y
        //    pagos al exterior, donde depende del convenio o del beneficiario— y se
        //    admite el que corresponda al caso, incluido 0.
        //
        // Un código puede tener varias filas (distintas vigencias, distintas tarifas):
        // vale cualquiera de ellas.
        $porcentaje = round((float) ($linea['porcentaje_retener'] ?? 0), 2);
        $esperados  = array_values(array_unique(array_map(
            fn($f) => round((float) ($f['porcentaje_ret'] ?? 0), 2),
            $delImpuesto
        )));
        $esVariable = $esperados === [] || max($esperados) <= 0;

        if (!$esVariable && !in_array($porcentaje, $esperados, true)) {
            $errores[] = sprintf(
                'Línea %d: el código %s retiene %s%% y no admite otro porcentaje (se aplicó %s%%). '
                . 'Si esa tarifa cambió, corríjala en Configuración → Retenciones SRI; los conceptos '
                . 'de porcentaje variable se registran ahí con 0%%. [%s]',
                $n,
                $codigo,
                implode('% / ', array_map(fn($p) => self::pct($p), $esperados)),
                self::pct($porcentaje),
                $impuestoLinea === 'IVA' ? self::REF_PORCENTAJES_IVA : self::REF_PORCENTAJES_RENTA
            );
        }

        // Vigencia (columnas Desde / Hasta del catálogo).
        if ($fechaRet !== '' && !$this->hayFilaVigente($delImpuesto, $fechaRet)) {
            $advertencias[] = self::aviso(
                "Línea {$n}: el código {$codigo} no está vigente al "
                    . date('d-m-Y', strtotime($fechaRet)) . ' según el catálogo.',
                self::REF_CATALOGO
            );
        }
    }

    /** Porcentaje en texto, sin ceros de relleno: 1.75, 10, 0. */
    private static function pct(float $p): string
    {
        return rtrim(rtrim(number_format($p, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** ¿Alguna fila del código está vigente a la fecha de la retención? */
    private function hayFilaVigente(array $filas, string $fecha): bool
    {
        foreach ($filas as $f) {
            $desde = trim((string) ($f['desde'] ?? ''));
            $hasta = trim((string) ($f['hasta'] ?? ''));
            if ($desde !== '' && substr($desde, 0, 10) > $fecha) continue;
            if ($hasta !== '' && substr($hasta, 0, 10) < $fecha) continue;
            return true;
        }
        return false;
    }

    /**
     * Código de impuesto canónico. Conviven el formato numérico del SRI y el
     * literal según si el documento se capturó a mano o se importó; el ISD llega
     * como 6 (tabla 16 del SRI) y también como 3 en importaciones antiguas.
     */
    private function normalizarImpuesto(string $codigo): string
    {
        $c = strtoupper(trim($codigo));
        return match ($c) {
            '1', 'RENTA' => 'RENTA',
            '2', 'IVA'   => 'IVA',
            '3', '6', 'ISD' => 'ISD',
            default      => $c,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SUJETOS: EMPRESA (AGENTE) Y PROVEEDOR (RETENIDO)
    // ─────────────────────────────────────────────────────────────────────────

    private function validarSujetos(array $data, array &$errores, array &$advertencias): void
    {
        $idEmpresa   = (int) ($data['id_empresa'] ?? 0);
        $idProveedor = (int) ($data['id_proveedor'] ?? 0);
        if ($idEmpresa <= 0 || $idProveedor <= 0) {
            return; // ya lo reportó validarCabecera()
        }

        $proveedor = $this->repo->getProveedorValidacion($idProveedor, $idEmpresa);
        if ($proveedor === null) {
            $errores[] = 'El proveedor seleccionado no pertenece a la empresa activa.';
            return;
        }

        $empresa = $this->repo->getEmpresaValidacion($idEmpresa);
        if ($empresa === null) {
            return;
        }

        $rucEmpresa   = preg_replace('/\D+/', '', (string) ($empresa['ruc'] ?? ''));
        $idProveedorN = preg_replace('/\D+/', '', (string) ($proveedor['identificacion'] ?? ''));

        // Nadie se retiene a sí mismo: el comprobante de retención documenta un pago
        // a un tercero.
        if ($rucEmpresa !== '' && $rucEmpresa === $idProveedorN) {
            $errores[] = 'No se puede emitir una retención a la propia empresa: '
                . 'el proveedor tiene la misma identificación que el emisor.';
        }

        // Nota: aquí NO se avisa de que la empresa no tenga cargado su número de
        // agente de retención. Es un dato de configuración, no del documento: el
        // aviso saldría en todas y cada una de las retenciones y acabaría
        // aceptándose sin leer, restando fuerza a las advertencias que sí hablan
        // del comprobante que se está emitiendo. Además hay retenciones que
        // proceden sin ser agente designado (pagos al exterior, liquidaciones de
        // compra), así que tampoco sería concluyente.
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOCUMENTO DE SUSTENTO VINCULADO
    // ─────────────────────────────────────────────────────────────────────────

    private function validarDocumentoVinculado(array $data, array &$errores, array &$advertencias): void
    {
        $idCompra      = (int) ($data['id_compra'] ?? 0);
        $idLiquidacion = (int) ($data['id_liquidacion'] ?? 0);
        $idEmpresa     = (int) ($data['id_empresa'] ?? 0);

        if (($idCompra <= 0 && $idLiquidacion <= 0) || $idEmpresa <= 0) {
            return;
        }

        $doc = $this->repo->getDocumentoVinculadoValidacion($idCompra, $idLiquidacion, $idEmpresa);
        if ($doc === null) {
            $errores[] = 'El documento de sustento vinculado no existe en la empresa activa.';
            return;
        }

        if (!empty($doc['eliminado']) && $doc['eliminado'] !== 'f') {
            $errores[] = 'El documento de sustento vinculado está eliminado.';
            return;
        }
        if (in_array(strtolower((string) ($doc['estado'] ?? '')), ['anulado', 'anulada'], true)) {
            $errores[] = 'No se puede retener sobre un documento anulado.';
            return;
        }

        // La retención declara al SRI un pago a un proveedor concreto: el documento
        // que la sustenta tiene que ser de ese mismo proveedor. Si el usuario cambió
        // el proveedor después de abrir la retención desde la compra, aquí se corta.
        $idProveedorDoc = (int) ($doc['id_proveedor'] ?? 0);
        if ($idProveedorDoc > 0 && $idProveedorDoc !== (int) ($data['id_proveedor'] ?? 0)) {
            $errores[] = 'El documento de sustento vinculado es del proveedor '
                . ($doc['proveedor_razon_social'] ?? '')
                . ', distinto del proveedor de la retención.';
        }

        $tipoDoc = str_pad(trim((string) ($doc['tipo_comprobante'] ?? '')), 2, '0', STR_PAD_LEFT);
        if ($tipoDoc !== '' && $tipoDoc !== '00' && $tipoDoc !== (string) ($data['tipo_doc_sustento'] ?? '')) {
            // Sin referencia legal: no es una regla tributaria, es una discrepancia
            // entre dos datos del propio sistema.
            $advertencias[] = self::aviso(
                "El tipo de documento de la retención ({$data['tipo_doc_sustento']}) no coincide "
                . "con el del documento vinculado ({$tipoDoc})."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BASES CONTRA EL DOCUMENTO DE SUSTENTO
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * La base de la retención de renta es el subtotal sin impuestos del documento
     * y la de IVA es el IVA de ese documento — nunca el subtotal. Retener el 70%
     * "del IVA" sobre el subtotal multiplica la retención y descuadra el XML, que
     * lleva en el mismo docSustento los impuestos del documento y las retenciones.
     */
    private function validarBasesContraDocumento(array $data, array &$errores, array &$advertencias): void
    {
        if (empty($data['lineas']) || !is_array($data['lineas'])) {
            return;
        }

        $doc = $this->repo->getDatosDocSustento($data);

        $subtotalDoc = round((float) ($doc['totalSinImpuestos'] ?? 0), 2);
        $ivaDoc      = 0.0;
        foreach (($doc['impuestos'] ?? []) as $imp) {
            $ivaDoc += (float) ($imp['valor'] ?? 0);
        }
        $ivaDoc = round($ivaDoc, 2);

        $baseRenta = 0.0;
        $baseIva   = 0.0;
        foreach ($data['lineas'] as $linea) {
            $base = (float) ($linea['base_imponible'] ?? 0);
            switch ($this->normalizarImpuesto((string) ($linea['codigo_impuesto'] ?? ''))) {
                case 'RENTA': $baseRenta += $base; break;
                case 'IVA':   $baseIva   += $base; break;
            }
        }
        $baseRenta = round($baseRenta, 2);
        $baseIva   = round($baseIva, 2);

        if ($subtotalDoc > 0 && $baseRenta > $subtotalDoc + self::TOLERANCIA) {
            $errores[] = sprintf(
                'La base de la retención de renta (%s) supera el subtotal del documento de sustento (%s).',
                number_format($baseRenta, 2), number_format($subtotalDoc, 2)
            );
        }

        if ($ivaDoc > 0) {
            if ($baseIva > $ivaDoc + self::TOLERANCIA) {
                $errores[] = sprintf(
                    'La base de la retención de IVA (%s) supera el IVA del documento de sustento (%s). '
                    . 'La retención de IVA se calcula sobre el IVA, no sobre el subtotal. [%s]',
                    number_format($baseIva, 2), number_format($ivaDoc, 2), self::REF_BASE_IVA
                );
            } elseif ($baseIva > 0 && abs($baseIva - $ivaDoc) > self::TOLERANCIA) {
                $advertencias[] = self::aviso(
                    sprintf(
                        'La base de la retención de IVA (%s) no es el IVA completo del documento (%s): '
                        . 'se está reteniendo sobre una parte.',
                        number_format($baseIva, 2), number_format($ivaDoc, 2)
                    ),
                    self::REF_BASE_IVA
                );
            }
        } elseif ($baseIva > 0 && ($subtotalDoc > 0 || (float) ($doc['importeTotal'] ?? 0) > 0)) {
            $advertencias[] = self::aviso(
                'Se está reteniendo IVA sobre un documento que no registra IVA. '
                . 'Compruebe los impuestos del documento de sustento.',
                self::REF_BASE_IVA
            );
        }

        // Base mínima de renta: bajo USD 50 no procede la retención, salvo pagos
        // periódicos al mismo proveedor. Por eso se avisa y el usuario decide.
        if ($baseRenta > 0 && $baseRenta < self::BASE_MINIMA_RENTA) {
            $advertencias[] = self::aviso(
                sprintf(
                    'La base de la retención de renta (%s) es menor a %s: no procede retención en la fuente, '
                    . 'salvo pagos periódicos al mismo proveedor.',
                    number_format($baseRenta, 2), number_format(self::BASE_MINIMA_RENTA, 2)
                ),
                self::REF_BASE_MINIMA
            );
        }
    }
}
