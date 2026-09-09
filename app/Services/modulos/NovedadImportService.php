<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NovedadRepository;
use App\models\CatalogoNovedades;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Exception;

/**
 * Importa novedades desde la plantilla Excel.
 *
 * Es TODO O NADA: primero se revisa el archivo completo (formato, empleados,
 * catálogos, reglas de negocio, rol pagado y duplicados) y solo si NO hay ni un
 * error se graba, en una sola transacción vía NovedadService::crearLote(). Si
 * algo falla no se crea ninguna fila: se devuelve el detalle por número de fila
 * para que el usuario corrija la plantilla y la vuelva a subir.
 *
 * Columnas de la plantilla: IDENTIFICACION, NOMBRE, TIPO, VALOR, MES, ANIO,
 * AFECTA_A, FECHA, OBSERVACION, MOTIVO. El orden real se resuelve leyendo los
 * encabezados, así que también se aceptan plantillas antiguas (sin NOMBRE).
 *
 * Cada importación queda registrada como una CARGA (novedades_cargas) y todas sus
 * novedades guardan el id_carga, para poder revertirla completa desde el módulo
 * mientras ninguna haya sido usada (ver NovedadCargaService).
 */
class NovedadImportService
{
    /** Orden por defecto si la plantilla no trae encabezados reconocibles. */
    private const ORDEN_DEFECTO = [
        'identificacion' => 0, 'nombre' => 1, 'tipo' => 2, 'valor' => 3, 'mes' => 4,
        'anio' => 5, 'aplica_en' => 6, 'fecha' => 7, 'observacion' => 8, 'motivo' => 9,
    ];

    /** Encabezados aceptados por campo (normalizados: mayúsculas, sin tildes ni separadores). */
    private const SINONIMOS = [
        'identificacion' => ['IDENTIFICACION', 'CEDULA', 'RUC', 'CEDULARUC', 'DOCUMENTO'],
        'nombre'         => ['NOMBRE', 'NOMBRES', 'EMPLEADO', 'NOMBRESAPELLIDOS', 'NOMBRESYAPELLIDOS'],
        'tipo'           => ['TIPO', 'TIPONOVEDAD', 'CODIGO', 'CODIGOTIPO'],
        'valor'          => ['VALOR', 'MONTO', 'CANTIDAD'],
        'mes'            => ['MES'],
        'anio'           => ['ANIO', 'ANO', 'YEAR'],
        'aplica_en'      => ['AFECTAA', 'AFECTA', 'APLICAEN', 'APLICA'],
        'fecha'          => ['FECHA', 'FECHAREGISTRO'],
        'observacion'    => ['OBSERVACION', 'OBSERVACIONES', 'DETALLE', 'CONCEPTO'],
        'motivo'         => ['MOTIVO', 'MOTIVOSALIDA'],
    ];

    private NovedadService $svc;
    private NovedadRepository $repo;
    private ?NovedadCargaService $cargaSvc;

    public function __construct(NovedadService $svc, NovedadRepository $repo, ?NovedadCargaService $cargaSvc = null)
    {
        $this->svc = $svc;
        $this->repo = $repo;
        $this->cargaSvc = $cargaSvc;
    }

    public function procesar(string $archivoTmp, int $idEmpresa, int $idUsuario, string $nombreArchivo = ''): array
    {
        $spreadsheet = IOFactory::load($archivoTmp);
        // La plantilla trae la hoja "Novedades" y una de "Referencia": se lee siempre
        // la de datos, aunque el usuario guarde el archivo con otra hoja activa.
        $hoja  = $spreadsheet->getSheetByName('Novedades') ?? $spreadsheet->getActiveSheet();
        $filas = $hoja->toArray();
        if (count($filas) <= 1) {
            throw new Exception('El archivo está vacío o solo contiene los encabezados.');
        }

        $cols = $this->mapearColumnas($filas[0]);

        // ── Fase 1: leer y validar TODO el archivo, sin escribir nada ────────
        $preparadas = [];   // nº de fila => datos listos para crear
        $errores    = [];
        $omitidas   = 0;    // filas de la plantilla sin VALOR (no aplican)
        $enArchivo  = [];   // clave duplicidad => nº de fila donde apareció primero

        for ($i = 1; $i < count($filas); $i++) {
            $fila = $filas[$i];
            if (empty(array_filter($fila, fn($v) => trim((string) $v) !== ''))) {
                continue; // fila vacía
            }
            $nf = $i + 1;

            try {
                $data = $this->mapearFila($fila, $cols, $idEmpresa, $idUsuario);
            } catch (\Throwable $e) {
                $errores[] = ['fila' => $nf, 'error' => $e->getMessage()];
                continue;
            }
            if ($data === null) {
                $omitidas++;   // sin VALOR: a ese empleado no le aplica esta novedad
                continue;
            }

            $clave = $this->claveNovedad($data);
            if (isset($enArchivo[$clave])) {
                $errores[] = [
                    'fila'  => $nf,
                    'error' => 'Repetida en la plantilla: ya está la misma novedad (empleado, tipo y período) en la fila '
                        . $enArchivo[$clave] . '.',
                ];
                continue;
            }
            $enArchivo[$clave] = $nf;
            $preparadas[$nf] = $data;
        }

        // Duplicados contra lo ya registrado (una sola consulta para todo el archivo).
        if (!empty($preparadas)) {
            $existentes = $this->repo->getClavesExistentes(
                $idEmpresa,
                array_column($preparadas, 'id_empleado'),
                array_column($preparadas, 'periodo_anio')
            );
            foreach ($preparadas as $nf => $data) {
                if (isset($existentes[$this->claveNovedad($data)])) {
                    $mes = CatalogoNovedades::MESES[(int) $data['periodo_mes']] ?? $data['periodo_mes'];
                    $errores[] = [
                        'fila'  => $nf,
                        'error' => 'Ya existe una novedad de "' . CatalogoNovedades::nombreTipo((string) $data['tipo_codigo'])
                            . '" para ' . $data['_nombre_empleado'] . ' en ' . $mes . ' ' . (int) $data['periodo_anio'] . '.',
                    ];
                    unset($preparadas[$nf]);
                }
            }
        }

        // Reglas de negocio y candado de rol pagado (tampoco escribe nada).
        foreach ($preparadas as $nf => $data) {
            $msg = $this->svc->validarParaCrear($data);
            if ($msg !== null) {
                $errores[] = ['fila' => $nf, 'error' => $msg];
                unset($preparadas[$nf]);
            }
        }

        $total = count($errores) + count($preparadas);

        // ── Todo o nada: con un solo error no se importa NINGUNA fila ────────
        if (!empty($errores)) {
            usort($errores, fn($a, $b) => $a['fila'] <=> $b['fila']);
            return [
                'creadas'  => 0,
                'errores'  => $errores,
                'total'    => $total,
                'omitidas' => $omitidas,
                'id_carga' => null,
                'abortada' => true,
            ];
        }
        if (empty($preparadas)) {
            throw new Exception($omitidas > 0
                ? 'Ninguna fila tiene VALOR: complete la columna VALOR de los empleados a los que aplica la novedad.'
                : 'El archivo no tiene ninguna fila con datos.');
        }

        // ── Fase 2: grabar la carga completa en una sola transacción ─────────
        $idCarga = $this->cargaSvc?->abrirCarga($idEmpresa, $idUsuario, $nombreArchivo);

        $aCrear = [];
        foreach ($preparadas as $nf => $data) {
            unset($data['_nombre_empleado']);
            $data['id_carga'] = $idCarga;
            $aCrear[$nf] = $data;
        }

        try {
            $ids = $this->svc->crearLote($aCrear, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            $this->cargaSvc?->cerrarCarga($idCarga, $idEmpresa, $idUsuario, $total, 0, $total);
            throw new Exception('No se importó ninguna fila: ' . $e->getMessage());
        }

        $creadas = count($ids);
        $this->cargaSvc?->cerrarCarga($idCarga, $idEmpresa, $idUsuario, $total, $creadas, 0);

        return [
            'creadas'  => $creadas,
            'errores'  => [],
            'total'    => $total,
            'omitidas' => $omitidas,
            'id_carga' => $idCarga,
            'abortada' => false,
        ];
    }

    /** Clave de duplicidad: mismo empleado, mismo tipo y mismo período (mes/año). */
    private function claveNovedad(array $d): string
    {
        return ((int) $d['id_empleado']) . '|' . trim((string) $d['tipo_codigo'])
            . '|' . ((int) $d['periodo_mes']) . '|' . ((int) $d['periodo_anio']);
    }

    /**
     * Resuelve en qué columna está cada campo leyendo la fila de encabezados. Si la
     * plantilla no trae encabezados reconocibles se usa el orden por defecto, de
     * modo que una plantilla antigua (sin la columna NOMBRE) también se importa.
     */
    private function mapearColumnas(array $encabezados): array
    {
        $cols = [];
        foreach ($encabezados as $idx => $texto) {
            $norm = $this->normalizarEncabezado((string) $texto);
            if ($norm === '') {
                continue;
            }
            foreach (self::SINONIMOS as $campo => $alias) {
                if (!isset($cols[$campo]) && in_array($norm, $alias, true)) {
                    $cols[$campo] = (int) $idx;
                    break;
                }
            }
        }

        // Sin encabezados útiles (identificación y tipo son los mínimos), se asume
        // el orden de la plantilla actual.
        if (!isset($cols['identificacion']) || !isset($cols['tipo'])) {
            return self::ORDEN_DEFECTO;
        }
        return $cols;
    }

    /** MAYÚSCULAS sin tildes, espacios ni separadores: "Afecta_a" → "AFECTAA". */
    private function normalizarEncabezado(string $texto): string
    {
        $t = mb_strtoupper(trim($texto), 'UTF-8');
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        return (string) preg_replace('/[^A-Z0-9]/', '', $t);
    }

    /**
     * Convierte una fila de la plantilla en datos de novedad. Devuelve null cuando
     * la fila viene SIN valor: la plantilla trae a todo el personal y se completa
     * solo a quien le aplica, así que una fila en blanco no es un error, se salta.
     */
    private function mapearFila(array $f, array $cols, int $idEmpresa, int $idUsuario): ?array
    {
        $val = function (string $campo, $porDefecto = '') use ($f, $cols) {
            $idx = $cols[$campo] ?? null;
            return $idx !== null && array_key_exists($idx, $f) && $f[$idx] !== null ? $f[$idx] : $porDefecto;
        };

        $ident   = trim((string) $val('identificacion'));
        $nombre  = trim((string) $val('nombre'));
        $tipoRaw = trim((string) $val('tipo'));
        $valor   = $val('valor', '');  // vacío = no aplica a ese empleado
        $mes     = (int) $val('mes', 0);
        $anio    = (int) $val('anio', 0);
        $afecta  = trim((string) $val('aplica_en', 'rol'));
        $fecha   = $val('fecha', '');
        $obs     = trim((string) $val('observacion'));
        $motivo  = trim((string) $val('motivo'));

        if ($ident === '') {
            throw new Exception('Falta la IDENTIFICACION del empleado.');
        }

        $tipo = $this->resolverTipo($tipoRaw);
        $valorTxt = trim((string) $valor);
        if (!CatalogoNovedades::esAvisoSalida($tipo)) {
            if ($valorTxt === '') {
                return null; // a este empleado no le aplica la novedad
            }
            if (!is_numeric(str_replace(',', '.', $valorTxt))) {
                throw new Exception("El VALOR «{$valorTxt}» no es un número.");
            }
            $valor = (float) str_replace(',', '.', $valorTxt);
        }
        $idEmp = $this->resolverEmpleado($idEmpresa, $ident);
        if (!$idEmp) {
            throw new Exception("No existe un empleado con identificación '{$ident}'"
                . ($nombre !== '' ? " ({$nombre})" : '') . '.');
        }

        return [
            'id_empresa'       => $idEmpresa,
            'id_usuario'       => $idUsuario,
            'id_empleado'      => $idEmp,
            'tipo_codigo'      => $tipo,
            'valor'            => (float) $valor,
            'periodo_mes'      => $mes,
            'periodo_anio'     => $anio,
            'aplica_en'        => $this->resolverAplicaEn($afecta),
            'fecha'            => $this->normalizarFecha($fecha),
            'observacion'      => $obs,
            'motivo_codigo'    => $motivo !== '' ? $this->resolverMotivo($motivo) : '',
            'estado'           => 'activo',
            // Solo para los mensajes de error; se quita antes de guardar.
            '_nombre_empleado' => $nombre !== '' ? $nombre : $ident,
        ];
    }

    /**
     * Busca el empleado por identificación. Si Excel guardó la cédula como número
     * y le comió los ceros de la izquierda (0705210052 → 705210052), reintenta
     * rellenando a 10 y 13 dígitos.
     */
    private function resolverEmpleado(int $idEmpresa, string $ident): ?int
    {
        $id = $this->repo->getIdEmpleadoPorIdentificacion($idEmpresa, $ident);
        if ($id) {
            return $id;
        }
        if (ctype_digit($ident)) {
            foreach ([10, 13] as $largo) {
                if (strlen($ident) < $largo) {
                    $id = $this->repo->getIdEmpleadoPorIdentificacion($idEmpresa, str_pad($ident, $largo, '0', STR_PAD_LEFT));
                    if ($id) {
                        return $id;
                    }
                }
            }
        }
        return null;
    }

    private function resolverTipo(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') throw new Exception('Falta el TIPO de novedad.');
        if (CatalogoNovedades::esTipoValido($raw)) return $raw;
        foreach (CatalogoNovedades::TIPOS as $t) {
            if (mb_strtolower($t['nombre']) === mb_strtolower($raw)) return $t['codigo'];
        }
        throw new Exception("TIPO de novedad no reconocido: '{$raw}'.");
    }

    private function resolverAplicaEn(string $raw): string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') return 'rol';
        if (CatalogoNovedades::esAplicaEnValido($raw)) return $raw;
        foreach (CatalogoNovedades::APLICA_EN as $k => $label) {
            if (mb_strtolower($label) === $raw) return $k;
        }
        if (in_array($raw, ['mensual', 'rol de pagos'], true)) return 'rol';
        if (str_contains($raw, 'semana')) return 'semanal';
        if (str_contains($raw, 'quincena')) return 'quincena';
        throw new Exception("AFECTA_A no reconocido: '{$raw}' (use rol, quincena o semanal).");
    }

    private function resolverMotivo(string $raw): string
    {
        $raw = trim($raw);
        if (CatalogoNovedades::esMotivoValido($raw)) return $raw;
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $m) {
            if (mb_strtolower($m['nombre']) === mb_strtolower($raw)) return $m['codigo'];
        }
        throw new Exception("MOTIVO de salida no reconocido: '{$raw}'.");
    }

    private function normalizarFecha($valor): string
    {
        if (is_numeric($valor) && $valor > 30000) {
            try { return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d'); } catch (\Throwable $e) {}
        }
        $s = trim((string) $valor);
        if ($s === '') return date('Y-m-d');
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
        $ts = strtotime(str_replace('/', '-', $s));
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }
}
