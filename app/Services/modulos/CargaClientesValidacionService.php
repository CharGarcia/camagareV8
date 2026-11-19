<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\Helpers\DiasVisita;
use App\repositories\modulos\CargaClientesRepository;
use App\Rules\modulos\CargaClientesRules;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Valida un libro de Excel de carga de clientes SIN escribir nada en la base.
 *
 * Devuelve un informe con el resumen, los errores/avisos por fila y el payload ya
 * normalizado de cada cliente, listo para ClienteService::crear()/actualizar().
 *
 * Cada fila termina en una de cuatro acciones:
 *   crear | actualizar | sin_cambios (idéntica a lo guardado: no se escribe) | con errores (se omite).
 */
class CargaClientesValidacionService
{
    private CargaClientesRepository $repository;
    private CargaClientesRules $rules;

    private ?CargaClientesCatalogos $catalogos = null;

    /** Clientes vigentes de la empresa, por identificación. */
    private array $clientes = [];

    /** Identificaciones de clientes eliminados (no se pueden volver a crear). */
    private array $eliminadas = [];

    private bool $tieneObservaciones = true;

    /** Modo "Actualizar solo algunas columnas": [índice => true]. null = carga completa. */
    private ?array $parcial = null;

    /** Permisos y alcance del usuario (§6). */
    private bool $puedeCrear = true;
    private bool $puedeActualizar = true;
    private ?int $idUsuarioFiltro = null;

    public function __construct(CargaClientesRepository $repository, CargaClientesRules $rules)
    {
        $this->repository = $repository;
        $this->rules      = $rules;
    }

    /**
     * @param array|null $parcial  string[] con nombres de columnas a actualizar; null = carga completa.
     * @param array      $contexto ['puede_crear' => bool, 'puede_actualizar' => bool, 'id_usuario_filtro' => ?int]
     */
    public function validar(string $rutaArchivo, int $idEmpresa, ?array $parcial = null, array $contexto = []): array
    {
        $this->parcial         = $parcial !== null ? self::normalizarParcial($parcial) : null;
        $this->puedeCrear      = (bool) ($contexto['puede_crear'] ?? true);
        $this->puedeActualizar = (bool) ($contexto['puede_actualizar'] ?? true);
        $this->idUsuarioFiltro = $contexto['id_usuario_filtro'] ?? null;

        $informe = [
            'modo'             => $this->parcial !== null ? 'parcial' : 'completa',
            'parcial'          => $parcial,
            'ok'               => false,
            'errores_globales' => [],
            'resumen'          => [
                'total'       => 0,
                'crear'       => 0,
                'actualizar'  => 0,
                'sin_cambios' => 0,
                'bloqueados'  => 0,
                'con_aviso'   => 0,
            ],
            'filas'    => [],
            'clientes' => [],
        ];

        if (!is_file($rutaArchivo)) {
            $informe['errores_globales'][] = 'No se encontró el archivo subido.';
            return $informe;
        }

        try {
            $libro = IOFactory::load($rutaArchivo);
        } catch (\Throwable $e) {
            $informe['errores_globales'][] = 'El archivo no es un Excel válido o está dañado.';
            return $informe;
        }

        $errores = $this->validarHojas($libro);
        if (!$errores) {
            $error = $this->validarEmpresa($libro, $idEmpresa);
            $errores = $error !== null ? [$error] : $this->validarEncabezados($libro);
        }
        if ($errores) {
            $informe['errores_globales'] = $errores;
            return $informe;
        }

        $this->precargar($idEmpresa);

        $clientes = $this->procesarHojaClientes($libro, $informe);
        if (!$clientes) {
            $informe['errores_globales'][] = 'La hoja "' . CargaClientesEsquema::HOJA_CLIENTES
                . '" no tiene ninguna fila con datos.';
            return $informe;
        }

        foreach ($clientes as $c) {
            $informe['resumen']['total']++;
            if ($c['errores']) {
                $informe['resumen']['bloqueados']++;
            } else {
                $informe['resumen'][$c['accion']]++;
            }
            if ($c['avisos']) {
                $informe['resumen']['con_aviso']++;
            }
        }

        $informe['clientes'] = $clientes;
        $informe['ok'] = $informe['resumen']['bloqueados'] === 0;

        return $informe;
    }

    /**
     * Convierte la elección del usuario (nombres de columna) en índices y descarta
     * lo que no esté en la lista blanca del esquema.
     *
     * @return array<int,true>
     */
    public static function normalizarParcial(array $columnas): array
    {
        $elegibles = CargaClientesEsquema::columnasElegibles();
        $indices = [];
        foreach ($columnas as $col) {
            $col = strtoupper(trim((string) $col));
            if (isset($elegibles[$col])) {
                $indices[CargaClientesEsquema::indice($col)] = true;
            }
        }
        return $indices;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Estructura del libro
    // ─────────────────────────────────────────────────────────────────────────

    /** @return string[] */
    private function validarHojas(Spreadsheet $libro): array
    {
        $esperadas = CargaClientesEsquema::todasLasHojas();
        $presentes = $libro->getSheetNames();

        $errores = [];
        if ($faltantes = array_diff($esperadas, $presentes)) {
            $errores[] = 'Al archivo le faltan hojas que no se deben borrar: '
                . implode(', ', $faltantes) . '. Descargue la plantilla nuevamente.';
        }
        if ($sobrantes = array_diff($presentes, $esperadas)) {
            $errores[] = 'El archivo tiene hojas que no pertenecen a la plantilla: '
                . implode(', ', $sobrantes) . '. Elimínelas o descargue la plantilla nuevamente.';
        }
        return $errores;
    }

    private function validarEmpresa(Spreadsheet $libro, int $idEmpresa): ?string
    {
        $hoja = $libro->getSheetByName(CargaClientesEsquema::HOJA_CONFIG);
        $idArchivo = $hoja ? trim((string) $hoja->getCell([2, 1])->getValue()) : '';
        if ($idArchivo === '') {
            return 'El archivo no indica a qué empresa pertenece. Descargue la plantilla nuevamente.';
        }
        if ((int) $idArchivo !== $idEmpresa) {
            $nombre = trim((string) $hoja->getCell([2, 2])->getValue());
            return 'Esta plantilla se generó para otra empresa'
                . ($nombre !== '' ? ' (' . $nombre . ')' : '')
                . '. Descargue la plantilla desde la empresa en la que desea cargar.';
        }
        return null;
    }

    /** @return string[] */
    private function validarEncabezados(Spreadsheet $libro): array
    {
        $hoja = $libro->getSheetByName(CargaClientesEsquema::HOJA_CLIENTES);
        $reales = [];
        foreach (array_keys(CargaClientesEsquema::COLUMNAS) as $i) {
            $reales[] = strtoupper(trim((string) $hoja->getCell([$i + 1, 1])->getValue()));
        }
        return $reales === CargaClientesEsquema::COLUMNAS
            ? []
            : ['Los encabezados de la hoja "' . CargaClientesEsquema::HOJA_CLIENTES
                . '" fueron modificados. Se esperaba: ' . implode(' | ', CargaClientesEsquema::COLUMNAS) . '.'];
    }

    private function precargar(int $idEmpresa): void
    {
        $this->catalogos          = new CargaClientesCatalogos($this->repository, $idEmpresa);
        $this->eliminadas         = $this->repository->getIdentificacionesEliminadas($idEmpresa);
        $this->tieneObservaciones = $this->repository->tieneObservaciones();

        // Todos los clientes vigentes (sin el filtro de registros propios): hay que
        // reconocer como "existente" también al de otro usuario, para bloquearlo
        // en vez de intentar crearlo de nuevo.
        $this->clientes = [];
        foreach ($this->repository->getClientes($idEmpresa) as $c) {
            $this->clientes[trim((string) $c['identificacion'])] = $c;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hoja Clientes
    // ─────────────────────────────────────────────────────────────────────────

    private function procesarHojaClientes(Spreadsheet $libro, array &$informe): array
    {
        $clientes = [];
        $vistos   = [];

        foreach ($this->leerFilas($libro) as $nFila => $celdas) {
            $ident     = $celdas[0];
            $existente = $this->clientes[$ident] ?? null;
            $errores   = [];
            $avisos    = [];

            // Modo parcial: las columnas NO elegidas toman el valor actual del
            // cliente; lo que traiga el archivo en ellas no se escribe.
            if ($this->parcial !== null && $existente !== null) {
                foreach (CargaClientesPlantillaService::valoresFila($existente, $this->catalogos) as $i => $valor) {
                    if ($i > 0 && !isset($this->parcial[$i])) {
                        $celdas[$i] = $this->texto($valor, $i === CargaClientesEsquema::indice('OBSERVACIONES'));
                    }
                }
            }

            [$datos, $invalidos, $avisosLectura] = $this->interpretar($celdas);
            $avisos = array_merge($avisos, $avisosLectura);

            $errores = $this->rules->validarCliente($datos, $invalidos);

            // Duplicado dentro del propio archivo.
            if ($ident !== '') {
                if (isset($vistos[$ident])) {
                    $errores[] = 'IDENTIFICACION repetida en el archivo (ya aparece en la fila ' . $vistos[$ident] . ').';
                } else {
                    $vistos[$ident] = $nFila;
                }
            }

            $accion = $existente ? 'actualizar' : 'crear';

            if ($existente === null) {
                if ($this->parcial !== null && $ident !== '') {
                    $errores[] = 'La identificación no existe. En "Actualizar solo algunas columnas" no se'
                        . ' crean clientes; use la carga completa para darlo de alta.';
                } elseif (isset($this->eliminadas[$ident])) {
                    $errores[] = 'Existe un cliente ELIMINADO con esta identificación; no se puede crear otro igual.';
                } elseif (!$this->puedeCrear && $ident !== '') {
                    $errores[] = 'No tiene permiso para crear clientes en este módulo.';
                }
            } else {
                if ($this->firma($datos) === $this->firma($this->datosDesdeExistente($existente))) {
                    // Idéntica a lo guardado: no se escribe, así que tampoco se
                    // reclama lo que ya estaba (p. ej. el consumidor final sin correo).
                    $accion  = 'sin_cambios';
                    $errores = array_values(array_filter(
                        $errores,
                        static fn($e) => str_starts_with($e, 'IDENTIFICACION repetida')
                    ));
                } elseif ($this->idUsuarioFiltro !== null && (int) $existente['created_by'] !== $this->idUsuarioFiltro) {
                    $errores[] = 'Este cliente lo registró otro usuario y usted no tiene acceso total para modificarlo.';
                } elseif (!$this->puedeActualizar) {
                    $errores[] = 'No tiene permiso para actualizar clientes en este módulo.';
                }
            }

            // Lo que la plantilla no trae y ClienteRepository::update() sí
            // sobrescribe: se reenvía el valor actual para no borrarlo.
            $conservar = $existente ? [
                'id_forma_cobro_predeterminada'          => $existente['id_forma_cobro_predeterminada'],
                'tipo_operacion_bancaria_predeterminada' => $existente['tipo_operacion_bancaria_predeterminada'],
                'monto_minimo_auto_cobro'                => $existente['monto_minimo_auto_cobro'],
                'monto_maximo_auto_cobro'                => $existente['monto_maximo_auto_cobro'],
                'id_ingreso_concepto_predeterminado'     => $existente['id_ingreso_concepto_predeterminado'],
                'latitud'                                => $existente['latitud'],
                'longitud'                               => $existente['longitud'],
            ] : [];

            $clientes[] = [
                'fila'           => $nFila,
                'identificacion' => $ident,
                'nombre'         => $datos['nombre'],
                'accion'         => $accion,
                'id_cliente'     => $existente ? (int) $existente['id'] : null,
                'datos'          => $datos,
                'conservar'      => $conservar,
                'errores'        => $errores,
                'avisos'         => $avisos,
            ];

            if ($errores || $avisos) {
                $informe['filas'][] = [
                    'hoja'    => CargaClientesEsquema::HOJA_CLIENTES,
                    'fila'    => $nFila,
                    'codigo'  => $ident,
                    'nombre'  => $datos['nombre'],
                    'accion'  => $accion,
                    'errores' => $errores,
                    'avisos'  => $avisos,
                ];
            }
        }

        return $clientes;
    }

    /**
     * Traduce las celdas de una fila a los datos que espera ClienteService.
     *
     * @return array{0:array,1:array<string,string>,2:string[]} [datos, inválidos por columna, avisos]
     */
    private function interpretar(array $c): array
    {
        $idx = static fn(string $col) => CargaClientesEsquema::indice($col);
        $v   = static fn(string $col) => $c[$idx($col)] ?? '';

        $invalidos = [];
        $avisos    = [];

        $ident  = $v('IDENTIFICACION');
        $tipoId = $this->aTipo($v('TIPO_IDENTIFICACION'), $ident);
        if ($ident === '') {
            $invalidos['IDENTIFICACION'] = 'IDENTIFICACION está vacía: es obligatoria.';
        }
        if ($tipoId === false) {
            $invalidos['TIPO_IDENTIFICACION'] = $v('TIPO_IDENTIFICACION') === ''
                ? 'Indique TIPO_IDENTIFICACION: no se pudo deducir de la identificación.'
                : 'TIPO_IDENTIFICACION "' . $v('TIPO_IDENTIFICACION') . '" no es válido. Use: '
                    . implode(', ', CargaClientesEsquema::TIPOS) . '.';
            $tipoId = '';
        }

        [$provincia, $err] = $this->catalogos->resolverProvincia($v('PROVINCIA'));
        if ($err) {
            $invalidos['PROVINCIA'] = $err;
        }
        $ciudad = null;
        if (!$err) {
            [$ciudad, $err] = $this->catalogos->resolverCiudad($provincia, $v('CIUDAD'));
            if ($err) {
                $invalidos['CIUDAD'] = $err;
            }
        }

        $plazo = $this->aEntero($v('PLAZO'), 0);
        if ($plazo === false) {
            $invalidos['PLAZO'] = 'PLAZO debe ser un número entero de días.';
            $plazo = 0;
        }

        [$idVendedor, $err, $aviso] = $this->catalogos->resolverVendedor($v('VENDEDOR'));
        if ($err) {
            $invalidos['VENDEDOR'] = $err;
        }
        if ($aviso) {
            $avisos[] = $aviso;
        }

        [$idFormaPago, $err] = $this->catalogos->resolverFormaPago($v('FORMA_PAGO_SRI'));
        if ($err) {
            $invalidos['FORMA_PAGO_SRI'] = $err;
        }

        $status = $this->aEstado($v('ESTADO'));
        if ($status === null) {
            $invalidos['ESTADO'] = 'ESTADO debe ser Activo o Inactivo.';
            $status = 1;
        }

        $dias = $this->aLista($v('DIAS_VISITA'), static fn($t) => DiasVisita::parsearDia($t));
        if ($dias === false) {
            $invalidos['DIAS_VISITA'] = 'DIAS_VISITA no es válido. Use Lun, Mar, Mie, Jue, Vie, Sab, Dom separados por coma.';
            $dias = null;
        }

        $frecuencia = null;
        $textoFrec  = CargaClientesCatalogos::normalizar($v('FRECUENCIA_VISITA'));
        if ($textoFrec !== '') {
            if (isset(DiasVisita::FRECUENCIAS[$textoFrec])) {
                $frecuencia = $textoFrec;
            } else {
                $invalidos['FRECUENCIA_VISITA'] = 'FRECUENCIA_VISITA debe ser Semanal, Quincenal o Mensual.';
            }
        }

        $semanas = $this->aLista($v('SEMANAS_VISITA'), static function ($t) {
            return (ctype_digit($t) && (int) $t >= 1 && (int) $t <= 5) ? (int) $t : null;
        });
        if ($semanas === false) {
            $invalidos['SEMANAS_VISITA'] = 'SEMANAS_VISITA debe tener números del 1 al 5 separados por coma.';
            $semanas = null;
        }

        $horas = [];
        foreach (['HORA_VISITA_DESDE', 'HORA_VISITA_HASTA'] as $col) {
            $hora = $this->aHora($v($col));
            if ($hora === false) {
                $invalidos[$col] = $col . ' debe tener el formato HH:MM (por ejemplo 08:30).';
                $hora = null;
            }
            $horas[$col] = $hora;
        }

        $orden = $this->aEntero($v('ORDEN_VISITA'), null);
        if ($orden === false || ($orden !== null && $orden < 0)) {
            $invalidos['ORDEN_VISITA'] = 'ORDEN_VISITA debe ser un número entero mayor o igual a cero.';
            $orden = null;
        }

        $datos = [
            'nombre'             => $v('NOMBRE'),
            'tipo_id'            => $tipoId,
            'identificacion'     => $ident,
            'telefono'           => $v('TELEFONO') !== '' ? $v('TELEFONO') : null,
            'email'              => $v('EMAIL'),
            'direccion'          => $v('DIRECCION') !== '' ? $v('DIRECCION') : null,
            'plazo'              => $plazo,
            'provincia'          => $provincia,
            'ciudad'             => $ciudad,
            'status'             => $status,
            'id_vendedor'        => $idVendedor,
            'id_forma_pago_sri'  => $idFormaPago,
            'dias_visita'        => $dias,
            'frecuencia_visita'  => $frecuencia,
            'semanas_visita'     => $semanas,
            'orden_visita'       => $orden,
            'hora_visita_desde'  => $horas['HORA_VISITA_DESDE'],
            'hora_visita_hasta'  => $horas['HORA_VISITA_HASTA'],
            'observacion_visita' => $v('OBSERVACION_VISITA') !== '' ? $v('OBSERVACION_VISITA') : null,
        ];
        if ($this->tieneObservaciones) {
            $datos['observaciones'] = $v('OBSERVACIONES') !== '' ? $v('OBSERVACIONES') : null;
        }

        // Mismas normalizaciones que la ficha (ClientesController::recogerDatosVisita).
        if ($datos['dias_visita'] === null && ($datos['frecuencia_visita'] || $datos['semanas_visita']
                || $datos['orden_visita'] !== null || $datos['hora_visita_desde'] || $datos['hora_visita_hasta'])) {
            $avisos[] = 'Sin DIAS_VISITA no hay ruta: se ignoran la frecuencia, semanas, horario y orden de visita.';
        }
        $datos = self::normalizarVisita($datos);

        return [$datos, $invalidos, $avisos];
    }

    /** Sin días no hay ruta; con frecuencia semanal las semanas del mes no aplican. */
    private static function normalizarVisita(array $d): array
    {
        if ($d['dias_visita'] === null) {
            $d['frecuencia_visita'] = null;
            $d['semanas_visita']    = null;
            $d['orden_visita']      = null;
            $d['hora_visita_desde'] = null;
            $d['hora_visita_hasta'] = null;
        } elseif ($d['frecuencia_visita'] === 'SEMANAL') {
            $d['semanas_visita'] = null;
        }
        return $d;
    }

    /** Lo guardado en la BD, en la misma forma que interpretar() (para comparar). */
    private function datosDesdeExistente(array $c): array
    {
        $d = [
            'nombre'             => (string) $c['nombre'],
            'tipo_id'            => trim((string) $c['tipo_id']),
            'identificacion'     => trim((string) $c['identificacion']),
            'telefono'           => $c['telefono'],
            'email'              => (string) ($c['email'] ?? ''),
            'direccion'          => $c['direccion'],
            'plazo'              => (int) ($c['plazo'] ?? 0),
            'provincia'          => $c['provincia'],
            'ciudad'             => $c['ciudad'],
            'status'             => (int) ($c['status'] ?? 1),
            'id_vendedor'        => $c['id_vendedor'] !== null ? (int) $c['id_vendedor'] : null,
            'id_forma_pago_sri'  => $c['id_forma_pago_sri'] !== null ? (int) $c['id_forma_pago_sri'] : null,
            'dias_visita'        => DiasVisita::normalizarDias($c['dias_visita'] ?? null),
            'frecuencia_visita'  => ($f = strtoupper(trim((string) ($c['frecuencia_visita'] ?? '')))) !== '' ? $f : null,
            'semanas_visita'     => DiasVisita::normalizarSemanas($c['semanas_visita'] ?? null),
            'orden_visita'       => $c['orden_visita'] !== null ? (int) $c['orden_visita'] : null,
            'hora_visita_desde'  => $c['hora_visita_desde'] ?: null,
            'hora_visita_hasta'  => $c['hora_visita_hasta'] ?: null,
            'observacion_visita' => $c['observacion_visita'],
        ];
        if ($this->tieneObservaciones) {
            $d['observaciones'] = $c['observaciones'];
        }
        return self::normalizarVisita($d);
    }

    /** Huella comparable de un cliente: si coincide, la fila no cambió nada. */
    private function firma(array $d): string
    {
        $campos = ['nombre', 'tipo_id', 'telefono', 'email', 'direccion', 'plazo', 'provincia', 'ciudad',
            'status', 'id_vendedor', 'id_forma_pago_sri', 'dias_visita', 'frecuencia_visita', 'semanas_visita',
            'orden_visita', 'hora_visita_desde', 'hora_visita_hasta', 'observacion_visita', 'observaciones'];

        $huella = [];
        foreach ($campos as $k) {
            $x = $d[$k] ?? null;
            if (is_array($x)) {
                $huella[$k] = implode(',', $x);
            } elseif (is_int($x)) {
                $huella[$k] = (string) $x;
            } else {
                // Espacios dobles o un salto de línea de más no cuentan como cambio.
                $huella[$k] = trim((string) preg_replace('/\s+/u', ' ', (string) $x));
            }
        }
        return json_encode($huella, JSON_UNESCAPED_UNICODE);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lectura y conversión de celdas
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filas con datos de la hoja Clientes (sin el encabezado), ya como texto
     * limpio e indexadas por su número de fila real en Excel.
     */
    private function leerFilas(Spreadsheet $libro): array
    {
        $hoja = $libro->getSheetByName(CargaClientesEsquema::HOJA_CLIENTES);
        $nColumnas = count(CargaClientesEsquema::COLUMNAS);
        // Solo OBSERVACIONES admite varias líneas; en el resto un salto es un espacio.
        $multilinea = CargaClientesEsquema::indice('OBSERVACIONES');
        $filas = [];

        foreach ($hoja->toArray(null, true, false, false) as $i => $celdas) {
            if ($i === 0) {
                continue;
            }
            $celdas = array_slice(array_pad($celdas, $nColumnas, ''), 0, $nColumnas);
            foreach ($celdas as $k => $x) {
                $celdas[$k] = $this->texto($x, $k === $multilinea);
            }

            if (implode('', $celdas) !== '') {
                $filas[$i + 1] = $celdas;
            }
        }
        return $filas;
    }

    /** Texto limpio. Un número entero no sale en notación científica (RUC de 13 dígitos). */
    private function texto($valor, bool $multilinea = false): string
    {
        if (is_float($valor) && floor($valor) === $valor && abs($valor) < 1e15) {
            $valor = sprintf('%.0f', $valor);
        }
        $v = str_replace(["\r\n", "\r"], "\n", (string) $valor);
        $v = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $v) ?? '';
        if (!$multilinea) {
            $v = str_replace("\n", ' ', $v);
        }
        $v = preg_replace('/[ \t\x{00A0}]+/u', ' ', $v) ?? $v;
        return trim($v);
    }

    /**
     * Código de tipo de identificación. Vacío = se deduce de la identificación.
     * @return string|false
     */
    private function aTipo(string $texto, string $ident)
    {
        if ($texto === '') {
            if ($ident === CargaClientesEsquema::CONSUMIDOR_FINAL) {
                return '07';
            }
            if (preg_match('/^\d{13}$/', $ident)) {
                return '04';
            }
            if (preg_match('/^\d{10}$/', $ident)) {
                return '05';
            }
            return false;
        }

        if (isset(CargaClientesEsquema::TIPOS[$texto])) {
            return $texto;
        }
        $norm = CargaClientesCatalogos::normalizar($texto);
        foreach (CargaClientesEsquema::TIPOS as $codigo => $rotulo) {
            if (CargaClientesCatalogos::normalizar($rotulo) === $norm) {
                return (string) $codigo;
            }
        }
        $sinonimos = [
            'R.U.C.' => '04', 'CI' => '05', 'C.I.' => '05', 'CEDULA DE IDENTIDAD' => '05',
            'CONSUMIDOR FINAL' => '07', 'IDENTIFICACION DEL EXTERIOR' => '08', 'VENTA A CONSUMIDOR FINAL' => '07',
        ];
        return $sinonimos[$norm] ?? false;
    }

    /** "Activo"/"Inactivo" => 1/0; vacío = Activo; null si no se reconoce. */
    private function aEstado(string $texto): ?int
    {
        $v = CargaClientesCatalogos::normalizar($texto);
        if ($v === '' || in_array($v, ['ACTIVO', 'ACTIVA', 'A', '1', 'SI'], true)) {
            return 1;
        }
        if (in_array($v, ['INACTIVO', 'INACTIVA', 'I', '0', 'NO'], true)) {
            return 0;
        }
        return null;
    }

    /**
     * Entero; vacío = $vacio; false si no es un entero.
     * @return int|null|false
     */
    private function aEntero(string $texto, ?int $vacio)
    {
        if ($texto === '') {
            return $vacio;
        }
        $t = str_replace(',', '.', $texto);
        if (!is_numeric($t) || floor((float) $t) !== (float) $t) {
            return false;
        }
        return (int) $t;
    }

    /**
     * Lista separada por comas (también ; / espacio o punto) con su traductor por
     * elemento. Vacío = null; false si algún elemento no se reconoce.
     *
     * @return int[]|null|false
     */
    private function aLista(string $texto, callable $traducir)
    {
        if ($texto === '') {
            return null;
        }
        $valores = [];
        foreach (preg_split('/[,;\/\s.]+/u', $texto) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            $n = $traducir($token);
            if ($n === null) {
                return false;
            }
            $valores[$n] = $n;
        }
        if (!$valores) {
            return null;
        }
        ksort($valores);
        return array_values($valores);
    }

    /**
     * Hora 'HH:MM'. Acepta "8:30", "08:30:00" y la fracción del día con la que
     * Excel guarda una hora. Vacío = null; false si no es una hora.
     *
     * @return string|null|false
     */
    private function aHora(string $texto)
    {
        if ($texto === '') {
            return null;
        }
        if (is_numeric($texto) && (float) $texto >= 0 && (float) $texto < 1) {
            $minutos = (int) round((float) $texto * 1440);
            return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
        }
        if (preg_match('/^(\d{1,2})[:h.](\d{2})(?::\d{2})?$/i', $texto, $m)
            && (int) $m[1] <= 23 && (int) $m[2] <= 59) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        return false;
    }
}
