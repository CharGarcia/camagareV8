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
 * algo falla no se crea ninguna novedad: se devuelve el detalle por número de
 * fila (y novedad) para que el usuario corrija la plantilla y la vuelva a subir.
 *
 * Formatos aceptados (se reconocen por los encabezados, en cualquier orden):
 *  - Plantilla actual (NovedadPlantillaService): una fila por empleado y una
 *    columna por tipo de novedad. Columnas IDENTIFICACION, NOMBRE, una por tipo,
 *    MES, ANIO, AFECTA_A, FECHA y OBSERVACION. Cada celda de novedad con valor es
 *    una novedad; en la de Aviso de salida va el motivo en lugar del valor.
 *  - Formato anterior: una fila por novedad, con IDENTIFICACION, NOMBRE, TIPO,
 *    VALOR, MES, ANIO, AFECTA_A, FECHA, OBSERVACION y MOTIVO. Se sigue aceptando
 *    para las plantillas que ya se descargaron así.
 *
 * Cada importación queda registrada como una CARGA (novedades_cargas) y todas sus
 * novedades guardan el id_carga, para poder revertirla completa desde el módulo
 * mientras ninguna haya sido usada (ver NovedadCargaService).
 */
class NovedadImportService
{
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
        $hoja = $spreadsheet->getSheetByName('Novedades') ?? $spreadsheet->getActiveSheet();
        // Valores crudos, no el texto formateado: un monto con formato de moneda o de
        // miles ("$ 1,250.00") sigue siendo el número 1250, y una fecha, su serial.
        $filas = $hoja->toArray(null, true, false, false);
        if (count($filas) <= 1) {
            throw new Exception('El archivo está vacío o solo contiene los encabezados.');
        }

        $cols = $this->mapearColumnas($filas[0]);
        $porEmpleado = !empty($cols['tipos']); // plantilla actual: una columna por novedad
        $this->validarColumnas($cols['campos'], $porEmpleado);

        // ── Fase 1: leer y validar TODO el archivo, sin escribir nada ────────
        $preparadas = [];   // ['fila' => nº de fila, 'data' => datos listos para crear]
        $errores    = [];   // ver agregarError()
        $leidas     = 0;    // novedades encontradas en el archivo, válidas o no
        $omitidas   = 0;    // filas sin ningún valor: a ese empleado no le aplica nada
        $enArchivo  = [];   // clave duplicidad => nº de fila donde apareció primero

        for ($i = 1; $i < count($filas); $i++) {
            $fila = $filas[$i];
            if (empty(array_filter($fila, fn($v) => trim((string) $v) !== ''))) {
                continue; // fila vacía
            }
            $nf = $i + 1;

            $res = $porEmpleado
                ? $this->leerFilaEmpleado($fila, $cols, $idEmpresa, $idUsuario)
                : $this->leerFilaNovedad($fila, $cols['campos'], $idEmpresa, $idUsuario);

            if ($res['leidas'] === 0) {
                $omitidas++;
                continue;
            }
            $leidas += $res['leidas'];
            foreach ($res['errores'] as [$novedad, $msg]) {
                $this->agregarError($errores, $nf, $novedad, $msg);
            }

            foreach ($res['novedades'] as $data) {
                $clave = $this->claveNovedad($data);
                if (isset($enArchivo[$clave])) {
                    $this->agregarError($errores, $nf, $this->nombreTipo($data),
                        'Repetida en la plantilla: ese empleado ya la tiene para el mismo período en la fila ' . $enArchivo[$clave] . '.');
                    continue;
                }
                $enArchivo[$clave] = $nf;
                $preparadas[] = ['fila' => $nf, 'data' => $data];
            }
        }

        // Duplicados contra lo ya registrado (una sola consulta para todo el archivo).
        if (!empty($preparadas)) {
            $datos = array_column($preparadas, 'data');
            $existentes = $this->repo->getClavesExistentes(
                $idEmpresa,
                array_column($datos, 'id_empleado'),
                array_column($datos, 'periodo_anio')
            );
            foreach ($preparadas as $k => $p) {
                if (isset($existentes[$this->claveNovedad($p['data'])])) {
                    $this->agregarError($errores, $p['fila'], $this->nombreTipo($p['data']),
                        'Ya está registrada para ' . $p['data']['_nombre_empleado'] . ' en ' . $this->nombrePeriodo($p['data']) . '.');
                    unset($preparadas[$k]);
                }
            }
        }

        // Reglas de negocio y candado de rol pagado (tampoco escribe nada).
        foreach ($preparadas as $k => $p) {
            $msg = $this->svc->validarParaCrear($p['data']);
            if ($msg !== null) {
                $this->agregarError($errores, $p['fila'], $this->nombreTipo($p['data']), $msg);
                unset($preparadas[$k]);
            }
        }

        // ── Todo o nada: con un solo error no se importa NINGUNA novedad ─────
        if (!empty($errores)) {
            return [
                'creadas'  => 0,
                'errores'  => $this->listarErrores($errores),
                'total'    => $leidas,
                'omitidas' => $omitidas,
                'id_carga' => null,
                'abortada' => true,
            ];
        }
        if (empty($preparadas)) {
            if ($omitidas === 0) {
                throw new Exception('El archivo no tiene ninguna fila con datos.');
            }
            throw new Exception($porEmpleado
                ? 'Ningún empleado tiene novedades: escriba el valor en la columna de la novedad que le corresponda (monto, horas, días o, en Aviso de salida, el motivo).'
                : 'Ninguna fila tiene VALOR: complete la columna VALOR de los empleados a los que aplica la novedad.');
        }

        // ── Fase 2: grabar la carga completa en una sola transacción ─────────
        $idCarga = $this->cargaSvc?->abrirCarga($idEmpresa, $idUsuario, $nombreArchivo);

        // La clave es la etiqueta con la que crearLote() informa un error ("5 · Descuento").
        $aCrear = [];
        foreach ($preparadas as $p) {
            $data = $p['data'];
            $etiqueta = $p['fila'] . ' · ' . $this->nombreTipo($data);
            unset($data['_nombre_empleado']);
            $data['id_carga'] = $idCarga;
            $aCrear[$etiqueta] = $data;
        }

        try {
            $ids = $this->svc->crearLote($aCrear, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            $this->cargaSvc?->cerrarCarga($idCarga, $idEmpresa, $idUsuario, $leidas, 0, $leidas);
            throw new Exception('No se importó ninguna novedad: ' . $e->getMessage());
        }

        $creadas = count($ids);
        $this->cargaSvc?->cerrarCarga($idCarga, $idEmpresa, $idUsuario, $leidas, $creadas, 0);

        return [
            'creadas'  => $creadas,
            'errores'  => [],
            'total'    => $leidas,
            'omitidas' => $omitidas,
            'id_carga' => $idCarga,
            'abortada' => false,
        ];
    }

    /**
     * Plantilla actual: la fila es un empleado y hay una columna por tipo de
     * novedad. Cada celda de novedad con valor es una novedad; las vacías o en 0
     * no (a ese empleado no le aplica ese tipo). En la columna de Aviso de salida
     * va el motivo de salida en lugar del valor. MES, ANIO, AFECTA_A, FECHA y
     * OBSERVACION valen para todas las novedades de la fila.
     *
     * Revisa la fila entera aunque encuentre un error, para informarlos todos de
     * una vez: cada error es [nombre de la novedad, o null si es de toda la fila,
     * mensaje].
     */
    private function leerFilaEmpleado(array $f, array $cols, int $idEmpresa, int $idUsuario): array
    {
        $celdas = []; // código del tipo => texto de la celda
        foreach ($cols['tipos'] as $idx => $codigo) {
            $txt = trim((string) ($f[$idx] ?? ''));
            if ($txt !== '' && !$this->esCero($txt)) {
                $celdas[$codigo] = $txt;
            }
        }
        if (empty($celdas)) {
            return ['novedades' => [], 'errores' => [], 'leidas' => 0];
        }

        $campos  = $cols['campos'];
        $errores = [];

        $ident  = trim((string) $this->celda($f, $campos, 'identificacion'));
        $nombre = trim((string) $this->celda($f, $campos, 'nombre'));
        $idEmp  = null;
        if ($ident === '') {
            $errores[] = [null, 'Falta la IDENTIFICACION del empleado.'];
        } else {
            $idEmp = $this->resolverEmpleado($idEmpresa, $ident);
            if (!$idEmp) {
                $errores[] = [null, "No existe un empleado con identificación '{$ident}'"
                    . ($nombre !== '' ? " ({$nombre})" : '') . '.'];
            }
        }
        $aplicaEn = null;
        try {
            $aplicaEn = $this->resolverAplicaEn(trim((string) $this->celda($f, $campos, 'aplica_en', 'rol')));
        } catch (\Throwable $e) {
            $errores[] = [null, $e->getMessage()];
        }
        $mes  = (int) $this->celda($f, $campos, 'mes', 0);
        $anio = (int) $this->celda($f, $campos, 'anio', 0);
        $obs  = trim((string) $this->celda($f, $campos, 'observacion'));

        // Lo común a todas las novedades de la fila.
        $base = [
            'id_empresa'       => $idEmpresa,
            'id_usuario'       => $idUsuario,
            'id_empleado'      => $idEmp,
            'periodo_mes'      => $mes,
            'periodo_anio'     => $anio,
            'aplica_en'        => $aplicaEn,
            'fecha'            => $this->normalizarFecha($this->celda($f, $campos, 'fecha', '')),
            'estado'           => 'activo',
            // Solo para los mensajes de error; se quita antes de guardar.
            '_nombre_empleado' => $nombre !== '' ? $nombre : $ident,
        ];

        $novedades = [];
        foreach ($celdas as $codigo => $txt) {
            $codigo  = (string) $codigo;
            $esAviso = CatalogoNovedades::esAvisoSalida($codigo);
            try {
                $valor  = $esAviso ? 0.0 : $this->numero($txt);
                $motivo = $esAviso ? $this->resolverMotivo($txt) : '';
            } catch (\Throwable $e) {
                $errores[] = [CatalogoNovedades::nombreTipo($codigo), $e->getMessage()];
                continue;
            }
            if ($idEmp === null || $aplicaEn === null) {
                continue; // la fila ya tiene su error: no se arma la novedad
            }
            $novedades[] = $base + [
                'tipo_codigo'   => $codigo,
                'valor'         => $valor,
                'motivo_codigo' => $motivo,
                'observacion'   => $obs !== '' ? $obs : $this->observacionPorDefecto($codigo, $mes, $anio),
            ];
        }

        return ['novedades' => $novedades, 'errores' => $errores, 'leidas' => count($celdas)];
    }

    /**
     * Formato anterior: la fila es UNA novedad (columnas TIPO y VALOR). Una fila
     * sin VALOR no cuenta: esa plantilla traía a todo el personal y se completaba
     * solo a quien le aplicaba la novedad.
     */
    private function leerFilaNovedad(array $f, array $campos, int $idEmpresa, int $idUsuario): array
    {
        try {
            $data = $this->mapearFila($f, $campos, $idEmpresa, $idUsuario);
        } catch (\Throwable $e) {
            return ['novedades' => [], 'errores' => [[null, $e->getMessage()]], 'leidas' => 1];
        }
        return $data === null
            ? ['novedades' => [], 'errores' => [], 'leidas' => 0]
            : ['novedades' => [$data], 'errores' => [], 'leidas' => 1];
    }

    /**
     * Suma un error a la fila $fila. Si esa fila ya tiene el mismo mensaje para
     * otra de sus novedades (p. ej. el rol del período ya está pagado), se agrega
     * el nombre de la novedad a ese error en vez de repetirlo.
     */
    private function agregarError(array &$errores, int $fila, ?string $novedad, string $msg): void
    {
        $clave = $fila . '|' . $msg;
        if (!isset($errores[$clave])) {
            $errores[$clave] = ['fila' => $fila, 'novedades' => [], 'error' => $msg];
        }
        if ($novedad !== null && !in_array($novedad, $errores[$clave]['novedades'], true)) {
            $errores[$clave]['novedades'][] = $novedad;
        }
    }

    /** Errores para la respuesta, en orden de fila: [fila, novedad (o null), error]. */
    private function listarErrores(array $errores): array
    {
        $lista = array_map(fn(array $e) => [
            'fila'    => $e['fila'],
            'novedad' => $e['novedades'] ? implode(', ', $e['novedades']) : null,
            'error'   => $e['error'],
        ], array_values($errores));
        usort($lista, fn($a, $b) => $a['fila'] <=> $b['fila']);
        return $lista;
    }

    /** Clave de duplicidad: mismo empleado, mismo tipo y mismo período (mes/año). */
    private function claveNovedad(array $d): string
    {
        return ((int) $d['id_empleado']) . '|' . trim((string) $d['tipo_codigo'])
            . '|' . ((int) $d['periodo_mes']) . '|' . ((int) $d['periodo_anio']);
    }

    private function nombreTipo(array $d): string
    {
        $codigo = (string) $d['tipo_codigo'];
        return CatalogoNovedades::nombreTipo($codigo) ?? $codigo;
    }

    private function nombrePeriodo(array $d): string
    {
        return (CatalogoNovedades::MESES[(int) $d['periodo_mes']] ?? (string) $d['periodo_mes'])
            . ' ' . (int) $d['periodo_anio'];
    }

    /** "Tipo - Mes Año": el mismo texto que autocompleta el modal de novedades. */
    private function observacionPorDefecto(string $tipo, int $mes, int $anio): string
    {
        $texto = (CatalogoNovedades::nombreTipo($tipo) ?? '') . ' - ' . (CatalogoNovedades::MESES[$mes] ?? '') . ' ' . $anio;
        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Resuelve en qué columna está cada campo leyendo la fila de encabezados:
     * 'campos' => campo => índice, y 'tipos' => índice => código del tipo de
     * novedad de esa columna (solo en la plantilla actual; vacío en el formato
     * anterior, que trae una columna TIPO).
     */
    private function mapearColumnas(array $encabezados): array
    {
        $porNombre = [];
        foreach (CatalogoNovedades::TIPOS as $t) {
            $porNombre[$this->normalizarEncabezado($t['nombre'])] = (string) $t['codigo'];
        }

        $campos = [];
        $tipos  = [];
        foreach ($encabezados as $idx => $texto) {
            $norm = $this->normalizarEncabezado((string) $texto);
            if ($norm === '') {
                continue;
            }
            if (isset($porNombre[$norm])) {
                if (!in_array($porNombre[$norm], $tipos, true)) {
                    $tipos[(int) $idx] = $porNombre[$norm];
                }
                continue;
            }
            foreach (self::SINONIMOS as $campo => $alias) {
                if (!isset($campos[$campo]) && in_array($norm, $alias, true)) {
                    $campos[$campo] = (int) $idx;
                    break;
                }
            }
        }
        return ['campos' => $campos, 'tipos' => $tipos];
    }

    /** Columnas sin las que el archivo no se puede leer: se avisa antes de revisar fila por fila. */
    private function validarColumnas(array $campos, bool $porEmpleado): void
    {
        $recarga = ' Descargue la plantilla de nuevo desde este cuadro y no cambie sus encabezados.';
        if (!$porEmpleado) {
            if (!isset($campos['identificacion'], $campos['tipo'])) {
                throw new Exception('No se reconocen las columnas del archivo.' . $recarga);
            }
            return;
        }
        foreach (['identificacion' => 'IDENTIFICACION', 'mes' => 'MES', 'anio' => 'ANIO'] as $campo => $titulo) {
            if (!isset($campos[$campo])) {
                throw new Exception("Falta la columna {$titulo}." . $recarga);
            }
        }
    }

    /**
     * MAYÚSCULAS sin tildes, separadores ni lo que vaya entre paréntesis:
     * "Afecta_a" → "AFECTAA", "PRÉSTAMO EMPRESA ($)" → "PRESTAMOEMPRESA".
     */
    private function normalizarEncabezado(string $texto): string
    {
        $t = mb_strtoupper(trim($texto), 'UTF-8');
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        $t = (string) preg_replace('/\([^)]*\)/', '', $t);
        return (string) preg_replace('/[^A-Z0-9]/', '', $t);
    }

    /** Valor crudo de la celda del campo, o $porDefecto si la columna no está o la celda está vacía. */
    private function celda(array $f, array $campos, string $campo, $porDefecto = '')
    {
        $idx = $campos[$campo] ?? null;
        return $idx !== null && array_key_exists($idx, $f) && $f[$idx] !== null ? $f[$idx] : $porDefecto;
    }

    /**
     * Formato anterior: convierte la fila en datos de novedad. Devuelve null cuando
     * la fila viene SIN valor: la plantilla traía a todo el personal y se
     * completaba solo a quien le aplicaba, así que una fila en blanco no es un
     * error, se salta.
     */
    private function mapearFila(array $f, array $campos, int $idEmpresa, int $idUsuario): ?array
    {
        $ident    = trim((string) $this->celda($f, $campos, 'identificacion'));
        $nombre   = trim((string) $this->celda($f, $campos, 'nombre'));
        $tipoRaw  = trim((string) $this->celda($f, $campos, 'tipo'));
        $valorTxt = trim((string) $this->celda($f, $campos, 'valor')); // vacío = no aplica a ese empleado
        $mes      = (int) $this->celda($f, $campos, 'mes', 0);
        $anio     = (int) $this->celda($f, $campos, 'anio', 0);
        $afecta   = trim((string) $this->celda($f, $campos, 'aplica_en', 'rol'));
        $obs      = trim((string) $this->celda($f, $campos, 'observacion'));
        $motivo   = trim((string) $this->celda($f, $campos, 'motivo'));

        if ($ident === '') {
            throw new Exception('Falta la IDENTIFICACION del empleado.');
        }

        $tipo  = $this->resolverTipo($tipoRaw);
        $valor = 0.0;
        if (!CatalogoNovedades::esAvisoSalida($tipo)) {
            if ($valorTxt === '') {
                return null; // a este empleado no le aplica la novedad
            }
            $valor = $this->numero($valorTxt);
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
            'valor'            => $valor,
            'periodo_mes'      => $mes,
            'periodo_anio'     => $anio,
            'aplica_en'        => $this->resolverAplicaEn($afecta),
            'fecha'            => $this->normalizarFecha($this->celda($f, $campos, 'fecha', '')),
            'observacion'      => $obs !== '' ? $obs : $this->observacionPorDefecto($tipo, $mes, $anio),
            'motivo_codigo'    => $motivo !== '' ? $this->resolverMotivo($motivo) : '',
            'estado'           => 'activo',
            // Solo para los mensajes de error; se quita antes de guardar.
            '_nombre_empleado' => $nombre !== '' ? $nombre : $ident,
        ];
    }

    /** ¿La celda es un 0? En la plantilla por empleado, un 0 equivale a dejarla vacía. */
    private function esCero(string $txt): bool
    {
        $n = str_replace(',', '.', $txt);
        return is_numeric($n) && (float) $n == 0.0;
    }

    /** Número de la celda; acepta coma decimal ("2,5"). */
    private function numero(string $txt): float
    {
        $n = str_replace(',', '.', trim($txt));
        if (!is_numeric($n)) {
            throw new Exception("El valor «{$txt}» no es un número.");
        }
        return (float) $n;
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

    /**
     * Motivo de salida por código ("T", también en minúscula), por nombre, o como
     * lo pone la lista desplegable de la plantilla: "T - Terminación del contrato".
     */
    private function resolverMotivo(string $raw): string
    {
        $raw    = trim($raw);
        $codigo = mb_strtoupper($raw, 'UTF-8');
        if (CatalogoNovedades::esMotivoValido($codigo)) return $codigo;
        if (preg_match('/^([A-Za-z])\s*-\s*\S/', $raw, $m) && CatalogoNovedades::esMotivoValido(strtoupper($m[1]))) {
            return strtoupper($m[1]);
        }
        foreach (CatalogoNovedades::MOTIVOS_SALIDA as $mot) {
            if (mb_strtolower($mot['nombre']) === mb_strtolower($raw)) return $mot['codigo'];
        }
        $codigos = array_column(CatalogoNovedades::MOTIVOS_SALIDA, 'codigo');
        $ultimo  = array_pop($codigos);
        throw new Exception("Motivo de salida no reconocido: «{$raw}». Elíjalo de la lista o escriba su código ("
            . implode(', ', $codigos) . " o {$ultimo}).");
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
