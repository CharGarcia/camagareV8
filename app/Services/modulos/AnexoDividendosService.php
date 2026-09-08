<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\CatalogoAdi;
use App\models\Empresa;
use App\repositories\modulos\AnexoDividendosRepository;
use App\Rules\modulos\AnexoDividendosRules;
use App\Services\LogSistemaService;
use App\Services\Xml\XmlAnexoDividendosService;
use Exception;
use ZipArchive;

/**
 * Lógica de negocio del Anexo de Dividendos (ADI).
 *
 * Orquesta las tres piezas del módulo:
 *
 *  1. Captura: abrir el anexo de un año, mantener la sección B (utilidades) y
 *     el detalle de beneficiarios y dividendos distribuidos.
 *  2. Derivación contable: leer los asientos del año en las cuentas que
 *     registran la distribución de dividendos y proponer con ellos los
 *     beneficiarios, los montos y las fechas. Todo lo importado queda editable.
 *  3. Generación: validar contra la ficha técnica y escribir ADI-aaaa.xml junto
 *     con su .zip para cargarlo en SRI en Línea.
 *
 * ─── SOBRE LOS CÁLCULOS TRIBUTARIOS ──────────────────────────────────────────
 * El ingreso gravado y la retención se calculan como SUGERENCIA con la
 * normativa vigente y quedan siempre editables. La razón es que dependen de
 * datos que el sistema no conoce con certeza (composición societaria,
 * convenios para evitar la doble imposición, residencia efectiva del
 * beneficiario) y de tablas que el SRI actualiza por resolución. Quien presenta
 * el anexo debe revisarlos.
 */
class AnexoDividendosService
{
    /** Desde este período el ingreso gravado es el 40% del dividendo distribuido. */
    private const ANIO_REGIMEN_40 = 2020;

    /**
     * Vigencia de la Ley Orgánica de Transparencia Social: desde esta fecha el
     * dividendo pasa al impuesto único del art. 39.2 LRTI y el ingreso gravado
     * es el monto distribuido (menos la franja exenta de las personas naturales
     * residentes), no ya el 40%.
     */
    private const FECHA_IMPUESTO_UNICO = '2025-09-01';

    /** Tarifas del impuesto único del art. 39.2 LRTI (desde septiembre de 2025). */
    private const TARIFA_UNICA_RESIDENTE      = 12.0;
    private const TARIFA_UNICA_NO_RESIDENTE   = 10.0;
    private const TARIFA_UNICA_PARAISO_FISCAL = 14.0;

    /**
     * Tabla progresiva de retención sobre dividendos a personas naturales
     * residentes (Resolución NAC-DGERCGC20-00000013), aplicable al régimen
     * 2020 – agosto 2025. Cada tramo: [límite superior, tarifa %].
     */
    private const TABLA_PROGRESIVA_2020 = [
        [20000.0,  0.0],
        [40000.0,  5.0],
        [60000.0, 10.0],
        [80000.0, 15.0],
        [100000.0, 20.0],
        [PHP_FLOAT_MAX, 25.0],
    ];

    /** Retención a no residentes en el régimen 2020 – agosto 2025. */
    private const TARIFA_2020_NO_RESIDENTE = 25.0;

    /** Paraíso fiscal o incumplimiento de informar composición societaria. */
    private const TARIFA_2020_PARAISO_FISCAL = 35.0;

    public function __construct(
        private AnexoDividendosRepository $repo,
        private AnexoDividendosRules $rules,
        private LogSistemaService $log,
        private XmlAnexoDividendosService $xml
    ) {
    }

    // ── Listado y apertura ───────────────────────────────────────────────────

    /**
     * Listado paginado del módulo.
     *
     * @param int $perPage 0 = sin paginar (exportaciones).
     * @return array{total:int, rows:array}
     */
    public function getListado(
        int $idEmpresa,
        string $buscar = '',
        int $page = 1,
        int $perPage = 20,
        string $ordenCol = 'anio',
        string $ordenDir = 'desc',
        ?int $idUsuarioFiltro = null
    ): array {
        return $this->repo->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    public function getAniosDisponibles(int $idEmpresa): array
    {
        $anios = $this->repo->getAniosDisponibles($idEmpresa, $this->repo->getTipoAmbiente($idEmpresa));
        $actual = (int) date('Y');
        for ($a = $actual; $a >= $actual - 5; $a--) {
            if (!in_array($a, $anios, true)) {
                $anios[] = $a;
            }
        }
        rsort($anios);

        return array_values(array_filter($anios, static fn(int $a): bool => $a >= AnexoDividendosRules::ANIO_MINIMO && $a <= $actual));
    }

    /**
     * Devuelve el anexo del año, creándolo si aún no existe con los datos de la
     * empresa y las cuentas contables sugeridas.
     */
    public function abrirAnexo(int $idEmpresa, int $idUsuario, int $anio): array
    {
        $ambiente = $this->repo->getTipoAmbiente($idEmpresa);
        $anexo    = $this->repo->getPorAnio($idEmpresa, $anio, $ambiente);
        if ($anexo !== null) {
            return $anexo;
        }

        if ($anio < AnexoDividendosRules::ANIO_MINIMO || $anio > (int) date('Y')) {
            throw new Exception('El año informado debe estar entre ' . AnexoDividendosRules::ANIO_MINIMO . ' y ' . date('Y') . '.');
        }

        $datos = array_merge($this->informanteDeEmpresa($idEmpresa), [
            'id_empresa'              => $idEmpresa,
            'id_usuario'              => $idUsuario,
            'anio'                    => $anio,
            'tipo_ambiente'           => $ambiente,
            'razon_social'            => $this->razonSocialEmpresa($idEmpresa),
            'cuentas_dividendos'      => $this->cuentasSugeridas($this->repo->getCuentasSugeridasDividendos($idEmpresa)),
            'cuentas_resultados_acum' => $this->cuentasSugeridas($this->repo->getCuentasSugeridasResultados($idEmpresa)),
            'sbu'                     => 0,
            'estado'                  => 'borrador',
        ]);

        $this->rules->validarCabecera($datos);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $id = $this->repo->crear($datos);
            $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'anexo_dividendos', $id, null, ['anio' => $anio]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $this->repo->getPorId($id, $idEmpresa) ?? [];
    }

    /** Anexo completo con su detalle, totales y el resultado de las validaciones. */
    public function getAnexoCompleto(int $id, int $idEmpresa): array
    {
        $anexo = $this->repo->getPorId($id, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $beneficiarios = $this->repo->getBeneficiarios($id, $idEmpresa);
        $detalles      = $this->repo->getDetalles($id, $idEmpresa);
        $validacion    = $this->rules->validarAnexo($anexo, $beneficiarios, $detalles);

        $anexo['cuentas_dividendos']      = $this->decodificarCuentas($anexo['cuentas_dividendos'] ?? '[]');
        $anexo['cuentas_resultados_acum'] = $this->decodificarCuentas($anexo['cuentas_resultados_acum'] ?? '[]');

        return [
            'anexo'         => $anexo,
            'beneficiarios' => $beneficiarios,
            'detalles'      => $detalles,
            'totales'       => $this->repo->getTotales($id, $idEmpresa),
            'errores'       => $validacion['errores'],
            'advertencias'  => $validacion['advertencias'],
        ];
    }

    // ── Cabecera y sección B ─────────────────────────────────────────────────

    public function guardarCabecera(int $id, int $idEmpresa, int $idUsuario, array $data): void
    {
        $actual = $this->repo->getPorId($id, $idEmpresa);
        if ($actual === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        // Los datos del informante NO se editan en la pantalla: el anexo es
        // siempre de la empresa activa, así que se vuelven a leer de ella en
        // cada guardado. Si el usuario corrige el RUC o el tipo de contribuyente
        // en Configuración → Empresa, el anexo se pone al día solo.
        $datos = array_merge($this->informanteDeEmpresa($idEmpresa), [
            'anio'                                => (int) $actual['anio'],
            'razon_social'                        => trim((string) ($data['razon_social'] ?? $actual['razon_social'])),
            'utilidad_ejercicio'                  => $this->num($data['utilidad_ejercicio'] ?? $actual['utilidad_ejercicio']),
            'utilidad_distribuida_distinta_reinv' => $this->num($data['utilidad_distribuida_distinta_reinv'] ?? $actual['utilidad_distribuida_distinta_reinv']),
            'utilidad_reinvertida_con_derecho'    => $this->num($data['utilidad_reinvertida_con_derecho'] ?? $actual['utilidad_reinvertida_con_derecho']),
            'utilidad_reinvertida_sin_derecho'    => $this->num($data['utilidad_reinvertida_sin_derecho'] ?? $actual['utilidad_reinvertida_sin_derecho']),
            'utilidad_pagada_anticipado'          => $this->num($data['utilidad_pagada_anticipado'] ?? $actual['utilidad_pagada_anticipado']),
            'utilidad_no_distrib_ejer_ant'        => $this->num($data['utilidad_no_distrib_ejer_ant'] ?? $actual['utilidad_no_distrib_ejer_ant']),
            'utilidad_distrib_ejercicios_ant'     => $this->num($data['utilidad_distrib_ejercicios_ant'] ?? $actual['utilidad_distrib_ejercicios_ant']),
            'cuentas_dividendos'                  => $this->normalizarCuentas($data['cuentas_dividendos'] ?? null, $actual['cuentas_dividendos']),
            'cuentas_resultados_acum'             => $this->normalizarCuentas($data['cuentas_resultados_acum'] ?? null, $actual['cuentas_resultados_acum']),
            'sbu'                                 => $this->num($data['sbu'] ?? $actual['sbu']),
            'estado'                              => trim((string) ($data['estado'] ?? $actual['estado'])),
            'observaciones'                       => $data['observaciones'] ?? $actual['observaciones'],
            'id_usuario'                          => $idUsuario,
        ]);

        // B.6 es un campo derivado: utilidad del ejercicio menos lo distribuido
        // y lo reinvertido. Calcularlo evita que el portal rechace el archivo
        // por un descuadre de centavos.
        $datos['utilidad_no_distribuida'] = round(
            $datos['utilidad_ejercicio']
            - $datos['utilidad_distribuida_distinta_reinv']
            - $datos['utilidad_reinvertida_con_derecho']
            - $datos['utilidad_reinvertida_sin_derecho'],
            2
        );

        $this->rules->validarCabecera($datos);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->actualizarCabecera($id, $idEmpresa, $datos);
            $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'anexo_dividendos', $id, $actual, $datos);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getPorId($id, $idEmpresa);
        if ($actual === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->softDelete($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'anexo_dividendos', $id, $actual, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Beneficiarios (C.1) ──────────────────────────────────────────────────

    public function guardarBeneficiario(int $idEmpresa, int $idUsuario, array $data): int
    {
        $idAnexo = (int) ($data['id_anexo'] ?? 0);
        $anexo   = $this->repo->getPorId($idAnexo, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $id     = (int) ($data['id'] ?? 0);
        $datos  = $this->normalizarBeneficiario($data, $idEmpresa, $idUsuario, $idAnexo);
        $this->rules->validarBeneficiario($datos, (int) $anexo['anio']);

        // La identificación no puede repetirse dentro del mismo anexo: el SRI la
        // usa como parte de la clave del registro.
        $existente = $this->repo->getBeneficiarioPorIdentificacion($idAnexo, $datos['numero_id_perceptor']);
        if ($existente !== null && (int) $existente['id'] !== $id) {
            throw new Exception('Ya existe un beneficiario con la identificación ' . $datos['numero_id_perceptor'] . ' en este anexo.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            if ($id > 0) {
                $actual = $this->repo->getBeneficiario($id, $idEmpresa);
                if ($actual === null) {
                    throw new Exception('El beneficiario no existe.');
                }
                $this->repo->actualizarBeneficiario($id, $idEmpresa, $datos);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'anexo_dividendos_beneficiario', $id, $actual, $datos);
            } else {
                $datos['secuencial'] = $this->repo->siguienteSecuencialBeneficiario($idAnexo);
                $id = $this->repo->crearBeneficiario($datos);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'anexo_dividendos_beneficiario', $id, null, $datos);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $id;
    }

    public function eliminarBeneficiario(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getBeneficiario($id, $idEmpresa);
        if ($actual === null) {
            throw new Exception('El beneficiario no existe.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->softDeleteBeneficiario($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'anexo_dividendos_beneficiario', $id, $actual, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Detalle de la distribución (C.2) ─────────────────────────────────────

    public function guardarDetalle(int $idEmpresa, int $idUsuario, array $data): int
    {
        $idAnexo = (int) ($data['id_anexo'] ?? 0);
        $anexo   = $this->repo->getPorId($idAnexo, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $idBeneficiario = (int) ($data['id_beneficiario'] ?? 0);
        $beneficiario   = $this->repo->getBeneficiario($idBeneficiario, $idEmpresa);
        if ($beneficiario === null || (int) $beneficiario['id_anexo'] !== $idAnexo) {
            throw new Exception('Seleccione un beneficiario del anexo.');
        }

        $datos = [
            'id_empresa'                  => $idEmpresa,
            'id_anexo'                    => $idAnexo,
            'id_beneficiario'             => $idBeneficiario,
            'anio_genera_utilidad'        => (int) ($data['anio_genera_utilidad'] ?? 0),
            'tipo_dividendo'              => trim((string) ($data['tipo_dividendo'] ?? '')),
            'fecha_registro_contable'     => trim((string) ($data['fecha_registro_contable'] ?? '')),
            'monto_dividendo_distribuido' => $this->num($data['monto_dividendo_distribuido'] ?? 0),
            'ingreso_gravado'             => $this->num($data['ingreso_gravado'] ?? 0),
            'monto_retencion'             => $this->num($data['monto_retencion'] ?? 0),
            'dividendo_pagado'            => trim((string) ($data['dividendo_pagado'] ?? '02')),
            'isd_pagado'                  => $this->num($data['isd_pagado'] ?? 0),
            'origen'                      => 'manual',
            'id_usuario'                  => $idUsuario,
        ];

        $this->rules->validarDetalle($datos, $beneficiario, (int) $anexo['anio']);

        $id = (int) ($data['id'] ?? 0);

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            if ($id > 0) {
                $actual = $this->repo->getDetalle($id, $idEmpresa);
                if ($actual === null) {
                    throw new Exception('El dividendo no existe.');
                }
                $this->repo->actualizarDetalle($id, $idEmpresa, $datos);
                $this->log->registrar($idUsuario, $idEmpresa, 'actualizar', 'anexo_dividendos_detalle', $id, $actual, $datos);
            } else {
                $id = $this->repo->crearDetalle($datos);
                $this->log->registrar($idUsuario, $idEmpresa, 'crear', 'anexo_dividendos_detalle', $id, null, $datos);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $id;
    }

    public function eliminarDetalle(int $id, int $idEmpresa, int $idUsuario): void
    {
        $actual = $this->repo->getDetalle($id, $idEmpresa);
        if ($actual === null) {
            throw new Exception('El dividendo no existe.');
        }

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->softDeleteDetalle($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'eliminar', 'anexo_dividendos_detalle', $id, $actual, null);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ── Derivación desde la contabilidad ─────────────────────────────────────

    /**
     * Arma el anexo a partir de los asientos del año.
     *
     * Reemplaza únicamente lo que se había importado antes (origen =
     * 'contabilidad'): lo que el usuario capturó o corrigió a mano se conserva.
     *
     * @return array{importados:int, beneficiarios:int, sin_tercero:array, mensajes:string[]}
     */
    public function importarDesdeContabilidad(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $anexo = $this->repo->getPorId($idAnexo, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $anio     = (int) $anexo['anio'];
        $ambiente = (string) $anexo['tipo_ambiente'];
        $cuentas  = $this->cuentasComoMapa($anexo['cuentas_dividendos']);

        if ($cuentas === []) {
            throw new Exception(
                'Seleccione al menos una cuenta contable de dividendos en la pestaña "Origen contable" antes de importar.'
            );
        }

        $movimientos = $this->repo->getMovimientosDividendos($idEmpresa, $anio, $cuentas, $ambiente);

        $mensajes   = [];
        $sinTercero = [];
        $importados = 0;
        $creados    = 0;

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            $this->repo->borrarDetalleImportado($idAnexo, $idEmpresa, $idUsuario);

            // El SRI identifica cada dividendo por beneficiario + fecha de
            // distribución, así que varias líneas contables del mismo día al
            // mismo accionista (dos cuentas, dos asientos) son UN registro del
            // anexo: se acumulan antes de escribir.
            $agrupados = [];

            foreach ($movimientos as $mov) {
                $tipoEntidad = (string) ($mov['tipo_entidad'] ?? '');
                $idEntidad   = (int) ($mov['id_entidad'] ?? 0);

                if ($tipoEntidad === '' || $idEntidad <= 0) {
                    $sinTercero[] = [
                        'fecha'      => (string) $mov['fecha_asiento'],
                        'asiento'    => (string) $mov['numero_comprobante'],
                        'concepto'   => (string) ($mov['referencia_detalle'] ?: $mov['concepto']),
                        'cuenta'     => $mov['codigo_cuenta'] . ' ' . $mov['nombre_cuenta'],
                        'monto'      => round((float) $mov['monto'], 2),
                    ];
                    continue;
                }

                $tercero = $this->repo->getTercero($tipoEntidad, $idEntidad, $idEmpresa);
                if ($tercero === null) {
                    $sinTercero[] = [
                        'fecha'    => (string) $mov['fecha_asiento'],
                        'asiento'  => (string) $mov['numero_comprobante'],
                        'concepto' => 'El tercero del asiento ya no existe o fue eliminado',
                        'cuenta'   => $mov['codigo_cuenta'] . ' ' . $mov['nombre_cuenta'],
                        'monto'    => round((float) $mov['monto'], 2),
                    ];
                    continue;
                }

                $beneficiario = $this->obtenerOCrearBeneficiario(
                    $idAnexo,
                    $idEmpresa,
                    $idUsuario,
                    $anio,
                    $tercero,
                    $tipoEntidad,
                    $creados
                );
                if ($beneficiario === null) {
                    $sinTercero[] = [
                        'fecha'    => (string) $mov['fecha_asiento'],
                        'asiento'  => (string) $mov['numero_comprobante'],
                        'concepto' => 'No se pudo deducir el tipo de identificación de ' . $tercero['nombre'],
                        'cuenta'   => $mov['codigo_cuenta'] . ' ' . $mov['nombre_cuenta'],
                        'monto'    => round((float) $mov['monto'], 2),
                    ];
                    continue;
                }

                $fecha = substr((string) $mov['fecha_asiento'], 0, 10);
                $clave = $beneficiario['id'] . '|' . $fecha;

                if (!isset($agrupados[$clave])) {
                    $agrupados[$clave] = [
                        'id_beneficiario'  => (int) $beneficiario['id'],
                        'tipo_beneficiario' => (string) $beneficiario['tipo_beneficiario'],
                        'fecha'            => $fecha,
                        'monto'            => 0.0,
                        'id_asiento'       => (int) $mov['id_asiento'],
                    ];
                }
                $agrupados[$clave]['monto'] += round((float) $mov['monto'], 2);
            }

            foreach ($agrupados as $g) {
                $this->repo->crearDetalle([
                    'id_empresa'                  => $idEmpresa,
                    'id_anexo'                    => $idAnexo,
                    'id_beneficiario'             => $g['id_beneficiario'],
                    // La contabilidad no dice de qué ejercicio proviene la
                    // utilidad repartida; lo habitual es el año anterior, y el
                    // usuario lo corrige en el detalle cuando no sea así.
                    'anio_genera_utilidad'        => $anio - 1,
                    'tipo_dividendo'              => CatalogoAdi::dividendoSugerido($g['tipo_beneficiario'], $anio),
                    'fecha_registro_contable'     => $g['fecha'],
                    'monto_dividendo_distribuido' => round($g['monto'], 2),
                    'ingreso_gravado'             => 0,
                    'monto_retencion'             => 0,
                    'dividendo_pagado'            => '02',
                    'isd_pagado'                  => 0,
                    'id_asiento'                  => $g['id_asiento'],
                    'origen'                      => 'contabilidad',
                    'id_usuario'                  => $idUsuario,
                ]);
                $importados++;
            }

            $this->repo->limpiarBeneficiariosSinDetalle($idAnexo, $idEmpresa, $idUsuario);

            $this->log->registrar(
                $idUsuario,
                $idEmpresa,
                'importar',
                'anexo_dividendos',
                $idAnexo,
                null,
                ['anio' => $anio, 'importados' => $importados, 'sin_tercero' => count($sinTercero)]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        // Los cálculos y el cuadre de la sección B se hacen fuera de la
        // transacción de importación: son idempotentes y se pueden repetir.
        $this->recalcularImpuestos($idAnexo, $idEmpresa, $idUsuario);
        $mensajes = array_merge($mensajes, $this->recalcularSeccionB($idAnexo, $idEmpresa, $idUsuario));

        if ($movimientos === []) {
            $mensajes[] = 'No se encontraron movimientos del año ' . $anio . ' en las cuentas de dividendos seleccionadas.';
        }
        if ($sinTercero !== []) {
            $mensajes[] = count($sinTercero) . ' movimiento(s) no tienen un cliente, proveedor o empleado asignado ' .
                'en el asiento; regístrelos a mano o corrija el asiento y vuelva a importar.';
        }

        return [
            'importados'    => $importados,
            'beneficiarios' => $creados,
            'sin_tercero'   => $sinTercero,
            'mensajes'      => $mensajes,
        ];
    }

    /**
     * Recalcula la sección B con la contabilidad del año: utilidad del
     * ejercicio, utilidades pendientes al inicio del período y los dos campos
     * que deben cuadrar con el detalle de dividendos.
     *
     * @return string[] Notas de lo que cambió.
     */
    public function recalcularSeccionB(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $anexo = $this->repo->getPorId($idAnexo, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $anio     = (int) $anexo['anio'];
        $ambiente = (string) $anexo['tipo_ambiente'];
        $mensajes = [];

        $utilidad = $this->repo->getUtilidadEjercicio($idEmpresa, $anio, $ambiente);
        $mensajes[] = 'Utilidad del ejercicio ' . $anio . ' tomada del estado de resultados: ' . number_format($utilidad, 2) . '.';

        $idsResultados = array_keys($this->cuentasComoMapa($anexo['cuentas_resultados_acum']));
        $pendiente = $idsResultados !== []
            ? $this->repo->getSaldoResultadosAcumulados($idEmpresa, $anio, $idsResultados, $ambiente)
            : 0.0;
        if ($idsResultados === []) {
            $mensajes[] = 'No hay cuentas de resultados acumulados seleccionadas: la utilidad pendiente de ejercicios ' .
                'anteriores quedó en cero.';
        }

        $totales     = $this->repo->getTotales($idAnexo, $idEmpresa);
        $delEjercicio = 0.0;
        $anteriores   = 0.0;
        foreach (($totales['por_anio'] ?? []) as $fila) {
            if ((int) $fila['anio_genera_utilidad'] >= $anio) {
                $delEjercicio += (float) $fila['monto'];
            } else {
                $anteriores += (float) $fila['monto'];
            }
        }

        $datos = array_merge($this->informanteDeEmpresa($idEmpresa), [
            'anio'                                => $anio,
            'razon_social'                        => (string) $anexo['razon_social'],
            'utilidad_ejercicio'                  => $utilidad,
            'utilidad_distribuida_distinta_reinv' => round($delEjercicio, 2),
            'utilidad_reinvertida_con_derecho'    => $this->num($anexo['utilidad_reinvertida_con_derecho']),
            'utilidad_reinvertida_sin_derecho'    => $this->num($anexo['utilidad_reinvertida_sin_derecho']),
            'utilidad_pagada_anticipado'          => $this->num($anexo['utilidad_pagada_anticipado']),
            'utilidad_no_distrib_ejer_ant'        => round(max($pendiente, 0), 2),
            'utilidad_distrib_ejercicios_ant'     => round($anteriores, 2),
            'cuentas_dividendos'                  => $this->decodificarCuentas($anexo['cuentas_dividendos']),
            'cuentas_resultados_acum'             => $this->decodificarCuentas($anexo['cuentas_resultados_acum']),
            'sbu'                                 => $this->num($anexo['sbu']),
            'estado'                              => (string) $anexo['estado'],
            'observaciones'                       => $anexo['observaciones'],
            'id_usuario'                          => $idUsuario,
        ]);
        $datos['utilidad_no_distribuida'] = round(
            $datos['utilidad_ejercicio']
            - $datos['utilidad_distribuida_distinta_reinv']
            - $datos['utilidad_reinvertida_con_derecho']
            - $datos['utilidad_reinvertida_sin_derecho'],
            2
        );

        if ($datos['utilidad_no_distribuida'] < 0) {
            $mensajes[] = 'Lo distribuido supera la utilidad del ejercicio: revise la sección B, porque el SRI ' .
                'exige que la utilidad no distribuida no sea negativa.';
        }
        if ($datos['utilidad_distrib_ejercicios_ant'] > $datos['utilidad_no_distrib_ejer_ant']) {
            $mensajes[] = 'Se distribuyeron ' . number_format($datos['utilidad_distrib_ejercicios_ant'], 2) .
                ' de ejercicios anteriores pero el saldo pendiente al inicio del período es ' .
                number_format($datos['utilidad_no_distrib_ejer_ant'], 2) . '. Ajuste las cuentas de resultados acumulados.';
        }

        $this->repo->actualizarCabecera($idAnexo, $idEmpresa, $datos);
        $this->log->registrar($idUsuario, $idEmpresa, 'recalcular', 'anexo_dividendos', $idAnexo, $anexo, $datos);

        return $mensajes;
    }

    /**
     * Calcula el ingreso gravado y la retención sugeridos de todo el detalle.
     *
     * Se hace en bloque y por beneficiario porque las dos reglas que aplican son
     * anuales, no por documento: la franja exenta de tres salarios básicos y la
     * tabla progresiva del régimen 2020 se miden sobre el acumulado del año de
     * cada persona.
     */
    public function recalcularImpuestos(int $idAnexo, int $idEmpresa, int $idUsuario): int
    {
        $anexo = $this->repo->getPorId($idAnexo, $idEmpresa);
        if ($anexo === null) {
            throw new Exception('El anexo no existe o no pertenece a la empresa activa.');
        }

        $anio          = (int) $anexo['anio'];
        $sbu           = $this->num($anexo['sbu']);
        $beneficiarios = [];
        foreach ($this->repo->getBeneficiarios($idAnexo, $idEmpresa) as $b) {
            $beneficiarios[(int) $b['id']] = $b;
        }

        $franjaUsada = [];   // id beneficiario => franja exenta ya consumida
        $baseAcum    = [];   // id beneficiario => ingreso gravado acumulado
        $actualizados = 0;

        $db = \App\core\Database::getConnection();
        $db->beginTransaction();
        try {
            foreach ($this->repo->getDetalles($idAnexo, $idEmpresa) as $d) {
                $idBenef = (int) $d['id_beneficiario'];
                $benef   = $beneficiarios[$idBenef] ?? null;
                if ($benef === null) {
                    continue;
                }

                $monto   = $this->num($d['monto_dividendo_distribuido']);
                $tipoDiv = (string) $d['tipo_dividendo'];
                $fecha   = substr((string) $d['fecha_registro_contable'], 0, 10);

                $franjaUsada[$idBenef] = $franjaUsada[$idBenef] ?? 0.0;
                $baseAcum[$idBenef]    = $baseAcum[$idBenef] ?? 0.0;

                $gravado = $this->calcularIngresoGravado(
                    $monto,
                    $tipoDiv,
                    (string) $benef['tipo_beneficiario'],
                    $fecha,
                    $anio,
                    $sbu,
                    $franjaUsada[$idBenef]
                );

                $retencion = $this->calcularRetencion(
                    $gravado,
                    $benef,
                    $tipoDiv,
                    $fecha,
                    $baseAcum[$idBenef]
                );

                $baseAcum[$idBenef] += $gravado;

                $this->repo->actualizarDetalle((int) $d['id'], $idEmpresa, [
                    'id_beneficiario'             => $idBenef,
                    'anio_genera_utilidad'        => (int) $d['anio_genera_utilidad'],
                    'tipo_dividendo'              => $tipoDiv,
                    'fecha_registro_contable'     => $fecha,
                    'monto_dividendo_distribuido' => $monto,
                    'ingreso_gravado'             => $gravado,
                    'monto_retencion'             => $retencion,
                    'dividendo_pagado'            => (string) $d['dividendo_pagado'],
                    'isd_pagado'                  => $this->num($d['isd_pagado']),
                    'id_usuario'                  => $idUsuario,
                ]);
                $actualizados++;
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return $actualizados;
    }

    /**
     * Ingreso gravado por dividendos (campo C.2.11).
     *
     * - Dividendo exento: siempre 0,00.
     * - 2020 hasta agosto de 2025: el 40% del monto distribuido.
     * - Desde septiembre de 2025 (Ley Orgánica de Transparencia Social): el
     *   monto distribuido, descontando la franja exenta de tres salarios
     *   básicos que corresponde a la persona natural residente por cada
     *   sociedad y período fiscal.
     *
     * @param float $franjaUsada Franja ya consumida por ese beneficiario en el
     *                           año; se actualiza por referencia.
     */
    public function calcularIngresoGravado(
        float $monto,
        string $tipoDividendo,
        string $tipoBeneficiario,
        string $fecha,
        int $anio,
        float $sbu,
        float &$franjaUsada
    ): float {
        if (!CatalogoAdi::dividendoGravado($tipoDividendo)) {
            return 0.0;
        }
        if ($anio < self::ANIO_REGIMEN_40) {
            return round($monto, 2);
        }
        if ($fecha < self::FECHA_IMPUESTO_UNICO) {
            return round($monto * 0.40, 2);
        }

        $gravado = $monto;

        if ($tipoBeneficiario === '01' && $sbu > 0) {
            $franjaTotal     = round($sbu * 3, 2);
            $franjaDisponible = max($franjaTotal - $franjaUsada, 0.0);
            $aplicar         = min($franjaDisponible, $gravado);
            $franjaUsada    += $aplicar;
            $gravado        -= $aplicar;
        }

        return round(max($gravado, 0.0), 2);
    }

    /**
     * Retención sugerida sobre el ingreso gravado (campo C.2.14).
     *
     * Desde septiembre de 2025 rige el impuesto único del art. 39.2 LRTI: 12%
     * general, 10% cuando el perceptor no es residente y 14% cuando en la cadena
     * de propiedad hay un paraíso fiscal con beneficiario efectivo residente en
     * Ecuador (o cuando no se informó la composición societaria). Hasta agosto
     * de 2025 se aplica la tabla progresiva de la Resolución
     * NAC-DGERCGC20-00000013 para personas naturales residentes y una tarifa
     * fija para el resto.
     *
     * @param float $baseAcumulada Ingreso gravado ya acumulado por el mismo
     *                             beneficiario en el año, para ubicar el tramo.
     */
    public function calcularRetencion(
        float $ingresoGravado,
        array $beneficiario,
        string $tipoDividendo,
        string $fecha,
        float $baseAcumulada
    ): float {
        if ($ingresoGravado <= 0) {
            return 0.0;
        }

        $tipoBenef = (string) $beneficiario['tipo_beneficiario'];
        $residente = in_array($tipoBenef, ['01', '03', '04', '05', '06'], true);
        $paraiso   = in_array($tipoBenef, ['09', '11'], true) || $tipoDividendo === '17';

        if ($fecha >= self::FECHA_IMPUESTO_UNICO) {
            $tarifa = match (true) {
                $paraiso   => self::TARIFA_UNICA_PARAISO_FISCAL,
                $residente => self::TARIFA_UNICA_RESIDENTE,
                default    => self::TARIFA_UNICA_NO_RESIDENTE,
            };
            return round($ingresoGravado * $tarifa / 100, 2);
        }

        if ($paraiso) {
            return round($ingresoGravado * self::TARIFA_2020_PARAISO_FISCAL / 100, 2);
        }
        if ($tipoBenef !== '01') {
            return round($ingresoGravado * self::TARIFA_2020_NO_RESIDENTE / 100, 2);
        }

        return $this->retencionProgresiva($ingresoGravado, $baseAcumulada);
    }

    /**
     * Aplica la tabla progresiva sobre el tramo del acumulado anual que ocupa
     * este dividendo, para que dos pagos del mismo año no se traten cada uno
     * como si empezara desde cero.
     */
    private function retencionProgresiva(float $ingresoGravado, float $baseAcumulada): float
    {
        $retencion = 0.0;
        $restante  = $ingresoGravado;
        $desde     = $baseAcumulada;
        $limiteInferior = 0.0;

        foreach (self::TABLA_PROGRESIVA_2020 as [$limite, $tarifa]) {
            if ($restante <= 0) {
                break;
            }
            $tramoDesde = max($limiteInferior, $desde);
            $tramoHasta = $limite;
            if ($tramoHasta > $tramoDesde) {
                $enTramo    = min($restante, $tramoHasta - $tramoDesde);
                $retencion += $enTramo * $tarifa / 100;
                $restante  -= $enTramo;
                $desde     += $enTramo;
            }
            $limiteInferior = $limite;
        }

        return round($retencion, 2);
    }

    // ── Generación del archivo ───────────────────────────────────────────────

    /**
     * Valida el anexo y escribe ADI-aaaa.xml junto con su .zip.
     *
     * @return array{ok:bool, mensaje?:string, registros?:int, nombre_xml?:string,
     *               nombre_zip?:?string, errores:string[], advertencias:string[]}
     */
    public function generar(int $idAnexo, int $idEmpresa, int $idUsuario): array
    {
        $completo      = $this->getAnexoCompleto($idAnexo, $idEmpresa);
        $anexo         = $completo['anexo'];
        $beneficiarios = $completo['beneficiarios'];
        $detalles      = $completo['detalles'];

        if ($completo['errores'] !== []) {
            return [
                'ok'           => false,
                'mensaje'      => 'El anexo tiene errores que impiden generar el archivo.',
                'errores'      => $completo['errores'],
                'advertencias' => $completo['advertencias'],
            ];
        }
        if ($detalles === []) {
            return [
                'ok'           => false,
                'mensaje'      => 'No hay dividendos registrados: el anexo quedaría vacío.',
                'errores'      => [],
                'advertencias' => $completo['advertencias'],
            ];
        }

        $anio = (int) $anexo['anio'];

        $informante = [
            'anio'               => (string) $anio,
            'tipo_informante'    => (string) $anexo['tipo_informante'],
            'tipo_id_informante' => (string) $anexo['tipo_id_informante'],
            'id_informante'      => (string) $anexo['id_informante'],
            'razon_social'       => $this->limpiarTexto((string) $anexo['razon_social']),
        ];

        $utilidades = in_array((string) $anexo['tipo_informante'], CatalogoAdi::INFORMANTES_SIN_SECCION_B, true)
            ? []
            : [
                'utilidad_ejercicio'                  => $this->fmt($anexo['utilidad_ejercicio']),
                'utilidad_distribuida_distinta_reinv' => $this->fmt($anexo['utilidad_distribuida_distinta_reinv']),
                'utilidad_reinvertida_con_derecho'    => $this->fmt($anexo['utilidad_reinvertida_con_derecho']),
                'utilidad_reinvertida_sin_derecho'    => $this->fmt($anexo['utilidad_reinvertida_sin_derecho']),
                'utilidad_pagada_anticipado'          => $this->fmt($anexo['utilidad_pagada_anticipado']),
                'utilidad_no_distribuida'             => $this->fmt($anexo['utilidad_no_distribuida']),
                'utilidad_no_distrib_ejer_ant'        => $this->fmt($anexo['utilidad_no_distrib_ejer_ant']),
                'utilidad_distrib_ejercicios_ant'     => $this->fmt($anexo['utilidad_distrib_ejercicios_ant']),
            ];

        $porBeneficiario = [];
        foreach ($detalles as $d) {
            $porBeneficiario[(int) $d['id_beneficiario']][] = [
                'anio_genera_utilidad'        => (string) $d['anio_genera_utilidad'],
                'tipo_dividendo'              => (string) $d['tipo_dividendo'],
                'fecha_registro_contable'     => $this->fmtFecha((string) $d['fecha_registro_contable']),
                'monto_dividendo_distribuido' => $this->fmt($d['monto_dividendo_distribuido']),
                'ingreso_gravado'             => $this->fmt($d['ingreso_gravado']),
                'monto_retencion'             => $this->fmt($d['monto_retencion']),
                'dividendo_pagado'            => $this->fmtRespuesta((string) $d['dividendo_pagado']),
                'isd_pagado'                  => $this->fmt($d['isd_pagado']),
            ];
        }

        $dividendos = [];
        $secuencial = 1;
        foreach ($beneficiarios as $b) {
            $idBenef = (int) $b['id'];
            if (empty($porBeneficiario[$idBenef])) {
                continue; // un beneficiario sin dividendos no se reporta
            }
            $dividendos[] = [
                'secuencial'                      => (string) $secuencial++,
                'tipo_id_perceptor'               => (string) $b['tipo_id_perceptor'],
                'numero_id_perceptor'             => (string) $b['numero_id_perceptor'],
                'tipo_beneficiario'               => (string) $b['tipo_beneficiario'],
                'pais_residencia'                 => (string) $b['pais_residencia'],
                'regimen_fiscal_preferente'       => $b['regimen_fiscal_preferente']
                    ? $this->fmtRespuesta((string) $b['regimen_fiscal_preferente'])
                    : '',
                'tipo_id_beneficiario_efectivo'   => (string) ($b['tipo_id_beneficiario_efectivo'] ?? ''),
                'numero_id_beneficiario_efectivo' => (string) ($b['numero_id_beneficiario_efectivo'] ?? ''),
                'distribuciones'                  => $porBeneficiario[$idBenef],
            ];
        }

        $contenido = $this->xml->generar($informante, $utilidades, $dividendos);

        $dir       = $this->dirSalida($idEmpresa);
        $nombreXml = 'ADI-' . $anio . '.xml';
        $nombreZip = 'ADI-' . $anio . '.zip';
        $rutaXml   = $dir . '/' . $nombreXml;
        $rutaZip   = $dir . '/' . $nombreZip;

        if (file_put_contents($rutaXml, $contenido) === false) {
            return [
                'ok'           => false,
                'mensaje'      => 'No se pudo escribir el archivo XML en ' . $dir . '.',
                'errores'      => [],
                'advertencias' => $completo['advertencias'],
            ];
        }
        $this->comprimir($rutaXml, $rutaZip, $nombreXml);

        // Si el esquema oficial está disponible, se valida contra él: es la
        // misma comprobación que hace el portal al recibir el archivo.
        $erroresXsd = $this->xml->validarContraXsd($contenido, $this->rutaXsd());

        $this->repo->marcarEstado($idAnexo, $idEmpresa, 'generado', $idUsuario);
        $this->log->registrar(
            $idUsuario,
            $idEmpresa,
            'generar',
            'anexo_dividendos',
            $idAnexo,
            null,
            ['anio' => $anio, 'registros' => count($detalles), 'archivo' => $nombreXml]
        );

        return [
            'ok'            => true,
            'registros'     => count($detalles),
            'beneficiarios' => count($dividendos),
            'nombre_xml'    => $nombreXml,
            'nombre_zip'    => is_file($rutaZip) ? $nombreZip : null,
            'errores'       => $erroresXsd,
            'advertencias'  => $completo['advertencias'],
        ];
    }

    /** Ruta absoluta de un archivo generado, o null si no existe. */
    public function rutaArchivo(int $idEmpresa, string $nombre): ?string
    {
        // El nombre lo propone el propio módulo; se revalida para que un
        // parámetro manipulado no salga del directorio de la empresa.
        if (!preg_match('/^ADI-\d{4}\.(xml|zip)$/', $nombre)) {
            return null;
        }
        $ruta = $this->dirSalida($idEmpresa) . '/' . $nombre;

        return is_file($ruta) ? $ruta : null;
    }

    /** Buscador de terceros para el modal del beneficiario. */
    public function buscarTerceros(int $idEmpresa, string $texto): array
    {
        if (mb_strlen(trim($texto)) < 2) {
            return [];
        }

        $rows = $this->repo->buscarTerceros($idEmpresa, $texto);
        foreach ($rows as &$row) {
            $tipoId = CatalogoAdi::tipoIdDesdeSistema((string) $row['tipo_id']);
            $row['tipo_id_adi']       = $tipoId ?? '';
            $row['tipo_beneficiario'] = $tipoId !== null
                ? CatalogoAdi::beneficiarioSugerido($tipoId, (string) $row['identificacion'])
                : '';
        }

        return $rows;
    }

    /** Cuentas del plan con la marca de cuáles están seleccionadas en el anexo. */
    public function getCuentasParaConfiguracion(int $idEmpresa, array $anexo): array
    {
        $seleccionDiv = $this->cuentasComoMapa($anexo['cuentas_dividendos'] ?? []);
        $seleccionRes = $this->cuentasComoMapa($anexo['cuentas_resultados_acum'] ?? []);

        $sugeridasDiv = array_column($this->repo->getCuentasSugeridasDividendos($idEmpresa), 'id');
        $sugeridasRes = array_column($this->repo->getCuentasSugeridasResultados($idEmpresa), 'id');

        $cuentas = [];
        foreach ($this->repo->getPlanCuentas($idEmpresa) as $c) {
            $id = (int) $c['id'];
            $cuentas[] = [
                'id'              => $id,
                'codigo'          => (string) $c['codigo'],
                'nombre'          => (string) $c['nombre'],
                'lado_sugerido'   => $this->ladoPorCodigo((string) $c['codigo']),
                'sel_dividendos'  => isset($seleccionDiv[$id]),
                'lado_dividendos' => $seleccionDiv[$id] ?? $this->ladoPorCodigo((string) $c['codigo']),
                'sel_resultados'  => isset($seleccionRes[$id]),
                'sugerida_div'    => in_array($c['id'], $sugeridasDiv, false),
                'sugerida_res'    => in_array($c['id'], $sugeridasRes, false),
            ];
        }

        return $cuentas;
    }

    // ── Auxiliares ───────────────────────────────────────────────────────────

    /**
     * Reutiliza el beneficiario ya registrado con esa identificación o lo crea
     * a partir del tercero del asiento.
     */
    private function obtenerOCrearBeneficiario(
        int $idAnexo,
        int $idEmpresa,
        int $idUsuario,
        int $anio,
        array $tercero,
        string $tipoEntidad,
        int &$creados
    ): ?array {
        $identificacion = trim((string) $tercero['identificacion']);
        $existente      = $this->repo->getBeneficiarioPorIdentificacion($idAnexo, $identificacion);
        if ($existente !== null) {
            return $existente;
        }

        $tipoId = CatalogoAdi::tipoIdDesdeSistema((string) $tercero['tipo_id']);
        if ($tipoId === null) {
            return null;
        }

        $tipoBenef = CatalogoAdi::beneficiarioSugerido($tipoId, $identificacion);
        $datos = [
            'id_empresa'                      => $idEmpresa,
            'id_anexo'                        => $idAnexo,
            'secuencial'                      => $this->repo->siguienteSecuencialBeneficiario($idAnexo),
            'tipo_id_perceptor'               => $tipoId,
            'numero_id_perceptor'             => $identificacion,
            'nombre_beneficiario'             => (string) $tercero['nombre'],
            'tipo_beneficiario'               => $tipoBenef,
            'pais_residencia'                 => CatalogoAdi::PAIS_ECUADOR,
            'regimen_fiscal_preferente'       => in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_REGIMEN_FISCAL, true) ? '02' : null,
            'tipo_id_beneficiario_efectivo'   => null,
            'numero_id_beneficiario_efectivo' => null,
            'tipo_entidad'                    => $tipoEntidad,
            'id_entidad'                      => (int) $tercero['id'],
            'id_usuario'                      => $idUsuario,
        ];

        // Si el tercero no permite un beneficiario coherente (identificación
        // del exterior con país Ecuador, por ejemplo), se deja fuera para que el
        // usuario lo registre a mano en lugar de guardar algo que el SRI rechaza.
        if (!CatalogoAdi::paisValido($tipoBenef, $datos['pais_residencia'])) {
            return null;
        }

        $id = $this->repo->crearBeneficiario($datos);
        $creados++;

        return $this->repo->getBeneficiario($id, $idEmpresa);
    }

    private function normalizarBeneficiario(array $data, int $idEmpresa, int $idUsuario, int $idAnexo): array
    {
        $tipoBenef = trim((string) ($data['tipo_beneficiario'] ?? ''));

        // Los condicionales se limpian según lo que habilita la ficha, para que
        // un cambio de tipo de beneficiario no deje datos huérfanos.
        $regimen = in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_REGIMEN_FISCAL, true)
            ? trim((string) ($data['regimen_fiscal_preferente'] ?? ''))
            : '';
        $aplicaEfec   = in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_CON_BENEF_EFECTIVO, true);
        $tipoIdEfec   = $aplicaEfec ? trim((string) ($data['tipo_id_beneficiario_efectivo'] ?? '')) : '';
        $numeroIdEfec = $tipoIdEfec !== '' ? trim((string) ($data['numero_id_beneficiario_efectivo'] ?? '')) : '';

        $pais = in_array($tipoBenef, CatalogoAdi::BENEFICIARIOS_PAIS_ECUADOR, true)
            ? CatalogoAdi::PAIS_ECUADOR
            : trim((string) ($data['pais_residencia'] ?? ''));

        return [
            'id_empresa'                      => $idEmpresa,
            'id_anexo'                        => $idAnexo,
            'secuencial'                      => (int) ($data['secuencial'] ?? 1),
            'tipo_id_perceptor'               => strtoupper(trim((string) ($data['tipo_id_perceptor'] ?? ''))),
            'numero_id_perceptor'             => trim((string) ($data['numero_id_perceptor'] ?? '')),
            'nombre_beneficiario'             => $this->limpiarTexto((string) ($data['nombre_beneficiario'] ?? '')),
            'tipo_beneficiario'               => $tipoBenef,
            'pais_residencia'                 => $pais,
            'regimen_fiscal_preferente'       => $regimen,
            'tipo_id_beneficiario_efectivo'   => $tipoIdEfec,
            'numero_id_beneficiario_efectivo' => $numeroIdEfec,
            'tipo_entidad'                    => trim((string) ($data['tipo_entidad'] ?? '')),
            'id_entidad'                      => (int) ($data['id_entidad'] ?? 0),
            'id_usuario'                      => $idUsuario,
        ];
    }

    /**
     * Identificación del informante (sección A.2) tomada de la empresa activa.
     *
     * El anexo siempre se presenta a nombre de la empresa con la que se está
     * trabajando, así que estos tres campos no se capturan: se derivan en cada
     * guardado. El tipo de informante sale del tipo de contribuyente configurado
     * en la empresa y, si falta, del propio RUC.
     *
     * La razón social queda fuera a propósito: es el único dato que a veces hay
     * que ajustar para que coincida con el registro del SRI, así que se edita en
     * la pantalla.
     *
     * @return array{tipo_informante:string, tipo_id_informante:string, id_informante:string}
     */
    private function informanteDeEmpresa(int $idEmpresa): array
    {
        $empresa = (new Empresa())->getPorId($idEmpresa) ?: [];
        $ruc     = (string) preg_replace('/\D/', '', (string) ($empresa['ruc'] ?? ''));

        return [
            'tipo_informante'    => CatalogoAdi::informantePorEmpresa($empresa['tipo'] ?? null, $ruc),
            // Una empresa del sistema siempre se identifica con RUC.
            'tipo_id_informante' => 'R',
            'id_informante'      => $ruc,
        ];
    }

    /** Razón social con la que se abre un anexo nuevo. */
    private function razonSocialEmpresa(int $idEmpresa): string
    {
        $empresa = (new Empresa())->getPorId($idEmpresa) ?: [];

        return trim((string) ($empresa['nombre'] ?? ''));
    }

    /** Cuentas sugeridas → formato de almacenamiento [{id, lado}]. */
    private function cuentasSugeridas(array $cuentas): array
    {
        $out = [];
        foreach ($cuentas as $c) {
            $out[] = ['id' => (int) $c['id'], 'lado' => $this->ladoPorCodigo((string) $c['codigo'])];
        }
        return $out;
    }

    /**
     * Lado del asiento que representa el movimiento de la cuenta. En el plan de
     * cuentas del SRI/SuperCías el primer dígito es el grupo: 2 = pasivo (los
     * dividendos por pagar se acreditan al distribuir) y 3 = patrimonio (los
     * resultados acumulados se debitan). Así el pago posterior del dividendo,
     * que va por el lado contrario, no vuelve a contarse como distribución.
     */
    private function ladoPorCodigo(string $codigo): string
    {
        return str_starts_with($codigo, '3') ? 'debe' : 'haber';
    }

    /** JSONB de la base → arreglo [{id, lado}]. */
    private function decodificarCuentas($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }
        $decodificado = json_decode((string) $valor, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    /** Cuentas en cualquiera de sus formas → mapa id => lado. */
    private function cuentasComoMapa($valor): array
    {
        $mapa = [];
        foreach ($this->decodificarCuentas($valor) as $c) {
            if (!is_array($c) || empty($c['id'])) {
                continue;
            }
            $mapa[(int) $c['id']] = ($c['lado'] ?? 'haber') === 'debe' ? 'debe' : 'haber';
        }
        return $mapa;
    }

    /** Selección enviada por la pantalla → formato de almacenamiento. */
    private function normalizarCuentas($enviado, $actual): array
    {
        if ($enviado === null) {
            return $this->decodificarCuentas($actual);
        }
        if (is_string($enviado)) {
            $enviado = json_decode($enviado, true);
        }
        if (!is_array($enviado)) {
            return [];
        }

        $out = [];
        foreach ($enviado as $c) {
            if (is_array($c) && !empty($c['id'])) {
                $out[] = ['id' => (int) $c['id'], 'lado' => ($c['lado'] ?? 'haber') === 'debe' ? 'debe' : 'haber'];
            } elseif (is_numeric($c)) {
                $out[] = ['id' => (int) $c, 'lado' => 'haber'];
            }
        }

        return $out;
    }

    private function num($valor): float
    {
        if (is_string($valor)) {
            $valor = str_replace([' ', ','], ['', '.'], $valor);
        }
        return round((float) $valor, 2);
    }

    /** Importe con el formato del anexo: hasta 10 enteros y 2 decimales. */
    private function fmt($valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    /** El SRI espera las fechas del anexo en dd/mm/aaaa. */
    private function fmtFecha(string $fecha): string
    {
        $fecha = substr($fecha, 0, 10);
        $p     = explode('-', $fecha);

        return count($p) === 3 ? $p[2] . '/' . $p[1] . '/' . $p[0] : $fecha;
    }

    /** Campos SI/NO: el código de la tabla 4 o su texto, según el esquema. */
    private function fmtRespuesta(string $codigo): string
    {
        if (!XmlAnexoDividendosService::RESPUESTA_COMO_TEXTO) {
            return $codigo;
        }
        return CatalogoAdi::RESPUESTA[$codigo] ?? $codigo;
    }

    /** El anexo no admite símbolos extraños en los campos de texto. */
    private function limpiarTexto(string $texto): string
    {
        $texto = (string) preg_replace('/\s+/u', ' ', trim($texto));

        return (string) preg_replace('/[^\p{L}\p{N}\s\.\,\-\&\/]/u', '', $texto);
    }

    private function dirSalida(int $idEmpresa): string
    {
        $dir = rtrim(dirname(__DIR__, 3), '/\\') . '/storage/anexos/dividendos/' . $idEmpresa;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** Esquema oficial, si el usuario lo dejó junto a la documentación del SRI. */
    private function rutaXsd(): string
    {
        return rtrim(dirname(__DIR__, 3), '/\\') . '/storage/anexos/dividendos/ADI.xsd';
    }

    private function comprimir(string $rutaXml, string $rutaZip, string $nombreInterno): void
    {
        if (!class_exists(ZipArchive::class)) {
            return;
        }
        $zip = new ZipArchive();
        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFile($rutaXml, $nombreInterno);
            $zip->close();
        }
    }
}
