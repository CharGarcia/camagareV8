<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\NovedadRepository;
use App\Rules\modulos\NovedadRules;
use App\Services\LogSistemaService;
use App\models\CatalogoNovedades;
use Exception;

class NovedadService
{
    private NovedadRepository $repository;
    private NovedadRules $rules;
    private LogSistemaService $logService;

    /** "idEmpresa|idEmpleado" => ¿aporta al IESS? (la importación normaliza muchas novedades por persona). */
    private array $aportaIessEmpleado = [];

    public function __construct(NovedadRepository $repository, NovedadRules $rules, LogSistemaService $logService)
    {
        $this->repository = $repository;
        $this->rules = $rules;
        $this->logService = $logService;
    }

    public function getListado(int $idEmpresa, string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, ?int $idUsuarioFiltro = null): array
    {
        return $this->repository->getListado($idEmpresa, $buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuarioFiltro);
    }

    /** Años de período usados por la empresa (modal de filtros del listado). */
    public function getAniosUsados(int $idEmpresa): array
    {
        return $this->repository->getAniosUsados($idEmpresa);
    }

    /** Usuarios que registraron novedades en la empresa (modal de filtros del listado). */
    public function getUsuariosConNovedades(int $idEmpresa): array
    {
        return $this->repository->getUsuariosConNovedades($idEmpresa);
    }

    public function getDetalle(int $id, int $idEmpresa): ?array
    {
        $data = $this->repository->getDetalle($id, $idEmpresa);
        if ($data) {
            $data['bloqueada'] = $this->rolYaPagado($idEmpresa, $data['id_empleado'] ?? 0, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0)
                || $this->yaDesembolsada($idEmpresa, $data);
        }
        return $data;
    }

    public function crear(array $data): int
    {
        $data = $this->aplicarMarcaPago($data, null);
        $data = $this->normalizar($data, (int) $data['id_empresa']);
        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        $this->repository->beginTransaction();
        try {
            $id = $this->crearInterno($data);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, $idUsuario);
        return $id;
    }

    /**
     * Crea VARIAS novedades en UNA sola transacción: o entran todas o no entra
     * ninguna. Lo usa la importación masiva, que valida el archivo completo antes
     * de escribir (ver NovedadImportService): si algo falla a mitad, revierte todo
     * y el usuario corrige la plantilla y la vuelve a subir.
     *
     * Devuelve los ids creados. Los roles afectados se regeneran una sola vez por
     * período, ya fuera de la transacción.
     */
    public function crearLote(array $filas, int $idEmpresa, int $idUsuario): array
    {
        if (empty($filas)) {
            return [];
        }

        $ids = [];
        $periodos = [];

        $this->repository->beginTransaction();
        try {
            foreach ($filas as $i => $data) {
                $data = $this->normalizar($data, $idEmpresa);
                try {
                    $ids[] = $this->crearInterno($data);
                } catch (\Throwable $e) {
                    // La clave del array es la etiqueta de la fila que arma el
                    // llamador (el importador usa "5 · Descuento").
                    throw new Exception('Fila ' . $i . ': ' . $e->getMessage(), 0, $e);
                }
                $clave = ($data['aplica_en'] ?? 'rol') . '|' . (int) $data['periodo_anio'] . '|' . (int) $data['periodo_mes'];
                $periodos[$clave] = [
                    'aplica_en' => (string) ($data['aplica_en'] ?? 'rol'),
                    'anio'      => (int) $data['periodo_anio'],
                    'mes'       => (int) $data['periodo_mes'],
                ];
            }
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        foreach ($periodos as $p) {
            $this->sincronizarRol($idEmpresa, $p['aplica_en'], $p['anio'], $p['mes'], $idUsuario);
        }

        return $ids;
    }

    /**
     * Registro para TODOS los empleados desde el modal (modo "Todos los empleados"): una
     * novedad por cada celda con valor de la cuadrícula empleado × tipo. Cada una se guarda
     * igual que la registrada a mano con "Nuevo" (sin carga: origen manual), con las mismas
     * reglas, y todo o nada.
     *
     * $comun: fecha, periodo_mes, periodo_anio, aplica_en (para todas).
     * $items: [id_empleado, tipo_codigo, valor, aporta_iess ('si'|'no'|null),
     *          ya_pagado (bool, solo Anticipo/Quincena/Préstamo Empresa), motivo_codigo (Aviso de salida),
     *          observacion (la del empleado; vacía = "Tipo - Mes Año")].
     *
     * Devuelve uno de:
     *  - ['errores' => [[empleado, novedad, error], …]]   nada se guardó
     *  - ['requiere_confirmacion' => true, 'avisos' => […]] el modal reenvía con $confirmado
     *  - ['ids' => [...], 'ya_registrado' => bool]          guardado (o reintento del mismo formulario)
     */
    public function crearMasivo(array $comun, array $items, int $idEmpresa, int $idUsuario, bool $confirmado = false, string $token = ''): array
    {
        if (empty($items)) {
            throw new Exception('No escribió ninguna novedad: ponga el valor en la columna de la novedad que corresponde a cada empleado.');
        }

        // Reintento del mismo formulario (se perdió la respuesta, pero el servidor sí guardó):
        // se responde con lo ya creado ANTES de validar, que si no lo rechazaría como duplicado
        // (CLAUDE.md §8). Se repite dentro de la transacción final por si hay dos envíos a la vez.
        $guardado = new \App\Services\GuardadoUnicoService();
        $this->repository->beginTransaction();
        try {
            $previo = $guardado->previo($token, $idEmpresa, 'novedades_masivo');
        } finally {
            $this->repository->rollBack();
        }
        if ($previo) {
            return ['ids' => (array) ($previo['respuesta']['ids'] ?? [$previo['id_registro']]), 'ya_registrado' => true];
        }

        $empleados = [];
        foreach ($this->repository->getEmpleadosActivosParaNovedades($idEmpresa) as $e) {
            $empleados[$e['id']] = $e['nombres_apellidos'];
        }
        $mes  = (int) ($comun['periodo_mes'] ?? 0);
        $anio = (int) ($comun['periodo_anio'] ?? 0);
        // La observación va por empleado (en cada item); vacía = "Tipo - Mes Año" de cada novedad.

        // Lo común se revisa una vez, no repetido en cada empleado.
        $fecha = trim((string) ($comun['fecha'] ?? ''));
        if ($fecha === '' || strtotime($fecha) === false) {
            throw new Exception('La fecha de la novedad es obligatoria y debe ser válida.');
        }
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes del período debe estar entre 1 y 12.');
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año del período no es válido.');
        }
        if (!CatalogoNovedades::esAplicaEnValido(trim((string) ($comun['aplica_en'] ?? 'rol')))) {
            throw new Exception('El campo "Afecta a" no es válido.');
        }

        $errores = [];
        $error = function (string $empleado, ?string $novedad, string $msg) use (&$errores): void {
            $errores[] = ['empleado' => $empleado, 'novedad' => $novedad, 'error' => $msg];
        };

        // ── Armar y normalizar cada novedad ──────────────────────────────────
        $preparadas = [];
        $hayYaPagado = false;
        $vistos = [];
        foreach ($items as $it) {
            $idEmp  = (int) ($it['id_empleado'] ?? 0);
            $tipo   = trim((string) ($it['tipo_codigo'] ?? ''));
            $nombre = $empleados[$idEmp] ?? null;
            if ($nombre === null) {
                $error('Empleado #' . $idEmp, CatalogoNovedades::nombreTipo($tipo), 'El empleado no existe o ya no está activo en la empresa.');
                continue;
            }
            if (!CatalogoNovedades::esTipoValido($tipo)) {
                $error($nombre, null, 'El tipo de novedad no es válido.');
                continue;
            }
            // Una sola novedad por empleado y tipo en la cuadrícula (la pantalla no permite más).
            if (isset($vistos[$idEmp . '|' . $tipo])) {
                continue;
            }
            $vistos[$idEmp . '|' . $tipo] = true;

            $entrega  = NovedadPlantillaService::requiereEntrega($tipo);
            $yaPagado = $entrega && !empty($it['ya_pagado']);
            $hayYaPagado = $hayYaPagado || $yaPagado;
            $iess = $it['aporta_iess'] ?? null;
            // Observación del empleado (columna de la cuadrícula); vacía = "Tipo - Mes Año".
            $obsEmp = mb_substr(trim((string) ($it['observacion'] ?? '')), 0, 250);

            $data = [
                'id_empresa'    => $idEmpresa,
                'id_usuario'    => $idUsuario,
                'id_empleado'   => $idEmp,
                'tipo_codigo'   => $tipo,
                'fecha'         => trim((string) ($comun['fecha'] ?? '')),
                'periodo_mes'   => $mes,
                'periodo_anio'  => $anio,
                'valor'         => (float) ($it['valor'] ?? 0),
                'aplica_en'     => trim((string) ($comun['aplica_en'] ?? 'rol')),
                'motivo_codigo' => trim((string) ($it['motivo_codigo'] ?? '')),
                'observacion'   => $obsEmp !== '' ? $obsEmp : $this->observacionPorDefecto($tipo, $mes, $anio),
                'estado'        => 'activo',
                'aporta_iess'   => $iess === null || $iess === '' ? null : (string) $iess,
                // Anticipo / Quincena / Préstamo Empresa ya entregado fuera del sistema: el rol
                // lo descuenta directo, sin egreso (como en la plantilla Excel).
                'desembolsado_migrado' => $yaPagado,
                'desembolso_manual'    => $yaPagado,
            ];
            $data = $this->normalizar($data, $idEmpresa);
            $preparadas[] = ['empleado' => $nombre, 'novedad' => self::etiquetaNovedad($data), 'data' => $data];
        }

        if ($hayYaPagado && !$this->repository->tieneDesembolsoManual()) {
            throw new Exception('Para registrar novedades "Ya pagado" falta aplicar en la base de datos el script database/2026-11-18_novedades_desembolso_manual.sql. Márquelas como "Por pagar" o pida que se aplique.');
        }

        // ── Duplicados: la misma novedad ya registrada (empleado, tipo, período, marca y valor) ──
        if ($preparadas) {
            $datos = array_column($preparadas, 'data');
            $existentes = [];
            foreach ($this->repository->getParaDuplicados($idEmpresa, array_column($datos, 'id_empleado'), array_column($datos, 'periodo_anio')) as $r) {
                $existentes[self::claveDuplicado($r)] = true;
            }
            foreach ($preparadas as $k => $p) {
                if (isset($existentes[self::claveDuplicado($p['data'])])) {
                    $error($p['empleado'], $p['novedad'], 'Ya está registrada en ' . $this->nombrePeriodo($mes, $anio)
                        . ' con el mismo valor (' . number_format((float) $p['data']['valor'], 2, '.', '') . '). Borre ese valor de la cuadrícula.');
                    unset($preparadas[$k]);
                }
            }
        }

        // ── Reglas de negocio, rol pagado y quincena repetida (sin escribir nada) ──
        foreach ($preparadas as $k => $p) {
            try {
                $this->rules->validate($p['data']);
                $this->bloquearSiRolPagado($idEmpresa, $p['data']['id_empleado'], $p['data']['aplica_en'] ?? 'rol', $anio, $mes);
                $this->bloquearQuincenaRepetida($p['data']);
            } catch (\Throwable $e) {
                $error($p['empleado'], $p['novedad'], $e->getMessage());
                unset($preparadas[$k]);
            }
        }

        if ($errores) {
            return ['errores' => $errores];
        }
        if (empty($preparadas)) {
            throw new Exception('No escribió ninguna novedad.');
        }

        // ── Avisos que se confirman (los mismos del registro de a uno) ──
        if (!$confirmado) {
            $avisos = [];
            foreach ($preparadas as $p) {
                foreach ($this->avisosAntesDeGuardar($p['data'], $idEmpresa) as $a) {
                    $avisos[] = $p['empleado'] . ' — ' . $a;
                }
            }
            if ($avisos) {
                return ['requiere_confirmacion' => true, 'avisos' => $avisos];
            }
        }

        // ── Guardar todo en una transacción (y una sola vez por formulario) ──
        $ids = [];
        $this->repository->beginTransaction();
        try {
            if ($previo = $guardado->previo($token, $idEmpresa, 'novedades_masivo')) {
                $this->repository->rollBack();
                return ['ids' => (array) ($previo['respuesta']['ids'] ?? [$previo['id_registro']]), 'ya_registrado' => true];
            }
            foreach ($preparadas as $p) {
                try {
                    $ids[] = $this->crearInterno($p['data']);
                } catch (\Throwable $e) {
                    throw new Exception($p['empleado'] . ' · ' . $p['novedad'] . ': ' . $e->getMessage(), 0, $e);
                }
            }
            $guardado->registrar($token, $idEmpresa, 'novedades_masivo', $ids[0], null, $idUsuario, ['ids' => $ids]);
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollBack();
            throw $e;
        }

        // Regenerar una sola vez cada rol afectado (normalmente uno; los tipos que solo
        // afectan al rol mensual pueden sumar otro).
        $periodos = [];
        foreach ($preparadas as $p) {
            $periodos[(string) ($p['data']['aplica_en'] ?? 'rol')] = true;
        }
        foreach (array_keys($periodos) as $aplica) {
            $this->sincronizarRol($idEmpresa, $aplica, $anio, $mes, $idUsuario);
        }

        return ['ids' => $ids, 'ya_registrado' => false];
    }

    /**
     * Clave de duplicidad contra lo ya registrado: mismo empleado, tipo, período (mes/año),
     * valor y, en Otros Ingresos y horas, misma marca de IESS; en Anticipo / Quincena /
     * Préstamo Empresa, ya entregado y por pagar son novedades distintas. La usan la
     * importación Excel y el registro para todos los empleados, con las novedades ya
     * registradas (NovedadRepository::getParaDuplicados).
     */
    public static function claveDuplicado(array $d): string
    {
        $tipo  = trim((string) $d['tipo_codigo']);
        $marca = CatalogoNovedades::admiteOpcionIess($tipo)
            ? (CatalogoNovedades::aportaIess($tipo, $d['aporta_iess'] ?? null) ? '|I' : '|N')
            : '';
        if (NovedadPlantillaService::requiereEntrega($tipo)) {
            $marca .= in_array($d['desembolsado_migrado'] ?? null, ['t', true, 1, '1', 'true'], true) ? '|E' : '|P';
        }
        // Aviso de salida: el motivo distingue un aviso de otro (su valor siempre es 0).
        if (CatalogoNovedades::esAvisoSalida($tipo)) {
            $marca .= '|' . trim((string) ($d['motivo_codigo'] ?? ''));
        }
        return ((int) $d['id_empleado']) . '|' . $tipo
            . '|' . ((int) $d['periodo_mes']) . '|' . ((int) $d['periodo_anio']) . $marca
            . '|' . number_format((float) ($d['valor'] ?? 0), 2, '.', '');
    }

    /** "Horas Nocturnas con IESS", "Anticipo ya pagado", "Aviso de salida"… para los mensajes. */
    private static function etiquetaNovedad(array $d): string
    {
        $tipo   = (string) $d['tipo_codigo'];
        $nombre = CatalogoNovedades::nombreTipo($tipo) ?? $tipo;
        if (NovedadPlantillaService::requiereEntrega($tipo)) {
            return $nombre . (!empty($d['desembolsado_migrado']) ? ' ya pagado' : ' por pagar');
        }
        if (CatalogoNovedades::admiteOpcionIess($tipo)) {
            return $nombre . (!empty($d['aporta_iess']) ? ' con IESS' : ' sin IESS');
        }
        return $nombre;
    }

    private function nombrePeriodo(int $mes, int $anio): string
    {
        return (CatalogoNovedades::MESES[$mes] ?? (string) $mes) . ' ' . $anio;
    }

    /** "Tipo - Mes Año": el mismo texto que autocompleta el modal de novedades. */
    private function observacionPorDefecto(string $tipo, int $mes, int $anio): string
    {
        $texto = (CatalogoNovedades::nombreTipo($tipo) ?? '') . ' - ' . (CatalogoNovedades::MESES[$mes] ?? '') . ' ' . $anio;
        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Validación + INSERT + auditoría de UNA novedad, SIN abrir transacción ni
     * regenerar el rol: eso lo decide el llamador (crear() por registro,
     * crearLote() para toda una carga). $data ya viene normalizado.
     */
    private function crearInterno(array $data): int
    {
        $this->rules->validate($data);

        $idEmpresa = (int) $data['id_empresa'];
        $idUsuario = (int) $data['id_usuario'];

        // No permitir registrar una novedad sobre un rol ya pagado.
        $this->bloquearSiRolPagado($idEmpresa, $data['id_empleado'] ?? 0, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0);
        // Ni una segunda quincena del mismo mes (novedad o rol de QUINCENA).
        $this->bloquearQuincenaRepetida($data);

        $id = $this->repository->create($data);
        $this->logService->registrar($idUsuario, $idEmpresa, 'CREAR', 'novedades', $id, null, $data);
        return $id;
    }

    /**
     * Valida una novedad SIN escribir nada: mismas reglas y mismo candado de rol
     * pagado que crearInterno(). Devuelve el mensaje de error, o null si es válida.
     * Lo usa la importación para revisar el archivo entero antes de guardar.
     */
    public function validarParaCrear(array $data): ?string
    {
        try {
            $data = $this->normalizar($data, (int) $data['id_empresa']);
            $this->rules->validate($data);
            $this->bloquearSiRolPagado(
                (int) $data['id_empresa'],
                $data['id_empleado'] ?? 0,
                $data['aplica_en'] ?? 'rol',
                $data['periodo_anio'] ?? 0,
                $data['periodo_mes'] ?? 0
            );
            $this->bloquearQuincenaRepetida($data);
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    public function actualizar(int $id, int $idEmpresa, array $data): void
    {
        $old = $this->repository->getDetalle($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Novedad no encontrada.');
        }
        // Sin marca de IESS en la edición (selector deshabilitado, o la regeneración
        // desde asistencia que no la conoce) se conserva la que ya tenía.
        if (($data['aporta_iess'] ?? null) === null) {
            $data['aporta_iess'] = $old['aporta_iess'] ?? null;
        }
        $data = $this->aplicarMarcaPago($data, $old);
        $data = $this->normalizar($data, $idEmpresa);
        $this->rules->validate($data);

        // Bloquear si el destino ANTERIOR o el NUEVO corresponden a un rol ya pagado.
        $this->bloquearSiRolPagado($idEmpresa, $old['id_empleado'] ?? 0, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0);
        $this->bloquearSiRolPagado($idEmpresa, $data['id_empleado'] ?? 0, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0);
        // Bloquear si esta novedad (Anticipo o cuota de Préstamo Empresa) ya fue desembolsada.
        $this->bloquearSiYaDesembolsada($idEmpresa, $old);
        // Ni una segunda quincena del mismo mes (sin contar esta misma novedad).
        $this->bloquearQuincenaRepetida($data + ['id_empresa' => $idEmpresa], $id);

        $idUsuario = (int) $data['id_usuario'];
        $this->repository->beginTransaction();
        try {
            $this->repository->update($id, $idEmpresa, $data);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'novedades', $id, $old, $data);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        // Regenerar el rol del período nuevo y, si cambió, también el del período anterior.
        $this->sincronizarRol($idEmpresa, $data['aplica_en'] ?? 'rol', $data['periodo_anio'] ?? 0, $data['periodo_mes'] ?? 0, $idUsuario);
        if (($old['aplica_en'] ?? '') !== ($data['aplica_en'] ?? '')
            || (int) ($old['periodo_mes'] ?? 0) !== (int) ($data['periodo_mes'] ?? 0)
            || (int) ($old['periodo_anio'] ?? 0) !== (int) ($data['periodo_anio'] ?? 0)) {
            $this->sincronizarRol($idEmpresa, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0, $idUsuario);
        }
    }

    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $old = $this->repository->getDetalle($id, $idEmpresa);
        if (!$old) {
            throw new Exception('Novedad no encontrada.');
        }

        // No permitir eliminar una novedad que ya afecta a un rol pagado.
        $this->bloquearSiRolPagado($idEmpresa, $old['id_empleado'] ?? 0, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0);
        // No permitir eliminar un Anticipo o cuota de Préstamo Empresa ya desembolsados.
        $this->bloquearSiYaDesembolsada($idEmpresa, $old);

        $this->repository->beginTransaction();
        try {
            $this->repository->deleteLogic($id, $idEmpresa, $idUsuario);
            $this->logService->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'novedades', $id, $old, null);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
        $this->sincronizarRol($idEmpresa, $old['aplica_en'] ?? 'rol', $old['periodo_anio'] ?? 0, $old['periodo_mes'] ?? 0, $idUsuario);
    }

    /**
     * Una Quincena (CatalogoNovedades::COD_QUINCENA) no se acepta si el empleado ya tiene la
     * quincena de ese mes: otra novedad Quincena (cargada a mano o por plantilla) o una línea
     * en un rol de QUINCENA vigente. En ambos casos se le descontaría dos veces en el mensual.
     * `$excluirId`: la novedad que se está editando.
     */
    private function bloquearQuincenaRepetida(array $data, ?int $excluirId = null): void
    {
        if (!CatalogoNovedades::esQuincena((string) ($data['tipo_codigo'] ?? ''))) {
            return;
        }
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);
        $idEmp     = (int) ($data['id_empleado'] ?? 0);
        $anio      = (int) ($data['periodo_anio'] ?? 0);
        $mes       = (int) ($data['periodo_mes'] ?? 0);
        if ($idEmpresa <= 0 || $idEmp <= 0 || $mes < 1 || $anio < 2000) {
            return; // las reglas ya rechazan el dato incompleto
        }
        $periodo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;

        $previas = $this->repository->getMismoTipoPeriodo($idEmpresa, $idEmp, CatalogoNovedades::COD_QUINCENA, $anio, $mes, $excluirId);
        if ($previas) {
            $p = $previas[0];
            $cuando = !empty($p['created_at']) ? ' el ' . date('d-m-Y H:i:s', strtotime((string) $p['created_at'])) : '';
            throw new Exception("El empleado ya tiene la quincena de {$periodo}: novedad Quincena de $"
                . number_format((float) $p['valor'], 2) . " registrada{$cuando}. Edítela o elimínela en lugar de registrar otra.");
        }

        try {
            $conRol = (new \App\repositories\modulos\RolPagoRepository())->getEmpleadosConRolQuincena($idEmpresa, $anio, $mes, [$idEmp]);
        } catch (\Throwable $e) {
            $conRol = []; // roles no desplegado → sin rol de quincena que cruzar
        }
        if (isset($conRol[$idEmp])) {
            throw new Exception("El empleado ya tiene la quincena de {$periodo}: está en el rol de QUINCENA de ese mes. "
                . 'Anule ese rol o quite al empleado de él si quiere registrarla como novedad.');
        }
    }

    /**
     * Avisos que NO impiden guardar pero que el usuario debe confirmar (el controlador los
     * devuelve como `requiere_confirmacion` y el modal vuelve a enviar con la confirmación):
     *  - Quincena de un empleado que tiene VALOR QUINCENA en su ficha: el rol de QUINCENA de
     *    ese mes lo omitirá (su quincena ya está en Novedades).
     *  - Otra novedad del mismo tipo para el empleado en ese mes (concepto repetido; a veces es
     *    legítimo, p. ej. dos descuentos distintos).
     * Al editar (`$old`), solo se avisa si cambió el empleado, el tipo o el período: si no, ya
     * se confirmó al crearla.
     */
    public function avisosAntesDeGuardar(array $data, int $idEmpresa, ?array $old = null): array
    {
        $tipo  = trim((string) ($data['tipo_codigo'] ?? ''));
        $idEmp = (int) ($data['id_empleado'] ?? 0);
        $anio  = (int) ($data['periodo_anio'] ?? 0);
        $mes   = (int) ($data['periodo_mes'] ?? 0);
        if ($tipo === '' || $idEmp <= 0 || $mes < 1 || $anio < 2000 || CatalogoNovedades::esAvisoSalida($tipo)) {
            return [];
        }
        if ($old !== null
            && (int) ($old['id_empleado'] ?? 0) === $idEmp && (string) ($old['tipo_codigo'] ?? '') === $tipo
            && (int) ($old['periodo_anio'] ?? 0) === $anio && (int) ($old['periodo_mes'] ?? 0) === $mes) {
            return [];
        }
        $periodo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
        $avisos  = [];

        if (CatalogoNovedades::esQuincena($tipo)) {
            $ficha = $this->repository->getValorQuincenaFicha($idEmpresa, [$idEmp]);
            if (isset($ficha[$idEmp])) {
                $avisos[] = 'El empleado ya tiene quincenas: su ficha tiene un valor quincena de $' . number_format($ficha[$idEmp], 2)
                    . ". Si continúa, al generar el rol de QUINCENA de {$periodo} se le omitirá, porque su quincena ya estará en Novedades.";
            }
            return $avisos; // una segunda Quincena del mes no es aviso: es error (bloquearQuincenaRepetida)
        }

        $previas = $this->repository->getMismoTipoPeriodo($idEmpresa, $idEmp, $tipo, $anio, $mes, isset($old['id']) ? (int) $old['id'] : null);
        if ($previas) {
            $detalle = implode(', ', array_map(
                fn($p) => CatalogoNovedades::formatValor($tipo, $p['valor'])
                    . (!empty($p['created_at']) ? ' (registrada el ' . date('d-m-Y H:i:s', strtotime((string) $p['created_at'])) . ')' : ''),
                $previas
            ));
            $avisos[] = 'El empleado ya tiene ' . (count($previas) === 1 ? 'una novedad' : count($previas) . ' novedades')
                . ' de ' . CatalogoNovedades::nombreTipo($tipo) . " en {$periodo}: {$detalle}. ¿Registrar otra?";
        }
        return $avisos;
    }

    /**
     * Lanza excepción si el empleado ya tiene un rol PAGADO para el tipo (según aplica_en)
     * y período de la novedad: no se puede crear/editar/eliminar una novedad que afectaría
     * un rol ya pagado.
     */
    private function bloquearSiRolPagado(int $idEmpresa, $idEmpleado, ?string $aplicaEn, $anio, $mes): void
    {
        if (!$this->rolYaPagado($idEmpresa, $idEmpleado, $aplicaEn, $anio, $mes)) return;

        $tipo = match (strtolower((string) ($aplicaEn ?: 'rol'))) {
            'semanal'  => 'SEMANAL',
            'quincena' => 'QUINCENA',
            default    => 'MENSUAL',
        };
        $etiqueta = match ($tipo) { 'SEMANAL' => 'semanal', 'QUINCENA' => 'quincena', default => 'mensual' };
        throw new Exception('No se puede crear, editar ni eliminar esta novedad: el rol ' . $etiqueta
            . ' de ' . str_pad((string) (int) $mes, 2, '0', STR_PAD_LEFT) . '/' . (int) $anio
            . ' del empleado ya está pagado. Anule el pago/rol de ese período si necesita modificarla.');
    }

    /**
     * ¿El rol (según aplica_en) del empleado para ese período ya está pagado/contabilizado
     * o tiene algún pago registrado? A diferencia de bloquearSiRolPagado(), no lanza
     * excepción: se usa para que la UI (modal) se muestre bloqueada de entrada, en vez de
     * dejar editar y recién avisar al guardar. Silencioso si roles/egresos no está desplegado.
     */
    private function rolYaPagado(int $idEmpresa, $idEmpleado, ?string $aplicaEn, $anio, $mes): bool
    {
        $idEmpleado = (int) $idEmpleado;
        $anio = (int) $anio;
        $mes  = (int) $mes;
        if ($idEmpleado <= 0 || $mes < 1 || $anio < 2000) return false;

        $tipo = match (strtolower((string) ($aplicaEn ?: 'rol'))) {
            'semanal'  => 'SEMANAL',
            'quincena' => 'QUINCENA',
            default    => 'MENSUAL',
        };

        try {
            return (new \App\repositories\modulos\RolPagoRepository())
                ->existeRolPagadoPeriodo($idEmpresa, $idEmpleado, $tipo, $anio, $mes);
        } catch (\Throwable $e) {
            return false; // roles/egresos no desplegado → sin restricción
        }
    }

    /**
     * Lanza excepción si esta novedad (Anticipo código 3, o cuota de Préstamo Empresa
     * código 9) ya tiene un desembolso registrado por egreso: el rol descuenta lo
     * REALMENTE pagado (no el valor de este registro), así que editarla/eliminarla
     * dejaría ese pago sin respaldo o descuadraría el cálculo de cuotas pendientes.
     */
    private function bloquearSiYaDesembolsada(int $idEmpresa, array $novedad): void
    {
        if (!$this->yaDesembolsada($idEmpresa, $novedad)) return;

        $cod = (string) ($novedad['tipo_codigo'] ?? '');
        if (NovedadRepository::esDesembolsoMigrado($novedad)) {
            throw new Exception('No se puede editar ni eliminar esta novedad: viene migrada del sistema anterior, donde ya fue pagada/desembolsada. Registre una novedad nueva de ajuste si necesita corregirla.');
        }
        $msg = \App\models\CatalogoNovedades::esPagoPorEgreso($cod)
            ? 'No se puede editar ni eliminar ' . (\App\models\CatalogoNovedades::esQuincena($cod) ? 'esta Quincena' : 'este Anticipo') . ': ya fue pagado por egreso.'
            : 'No se puede editar ni eliminar esta cuota: el Préstamo Empresa de este empleado ya tiene desembolsos registrados por egreso.';
        throw new Exception($msg . ' Anule ese egreso si necesita corregirlo, o registre una novedad nueva de ajuste.');
    }

    /**
     * ¿Esta novedad (Anticipo o cuota de Préstamo Empresa) ya tiene un desembolso
     * registrado por egreso? No lanza excepción: se usa para bloquear la UI de entrada
     * (ver bloquearSiYaDesembolsada, que sí la lanza al guardar). Silencioso si
     * roles/egresos no está desplegado.
     */
    private function yaDesembolsada(int $idEmpresa, array $novedad): bool
    {
        $cod = (string) ($novedad['tipo_codigo'] ?? '');
        if (!\App\models\CatalogoNovedades::esPagoPorEgreso($cod) && $cod !== '9') return false;

        $idNovedad = (int) ($novedad['id'] ?? 0);
        $idEmpleado = (int) ($novedad['id_empleado'] ?? 0);
        if ($idEmpleado <= 0 || $idNovedad <= 0) return false;

        // Con la marca de desembolso decide la marca, no los egresos (mismo criterio que el listado):
        // migrada del sistema anterior → bloqueada; cargada por Excel (que asume ya entregados los
        // anticipos y préstamos) → libre, como cualquier novedad: no tiene egreso que respalde.
        if (NovedadRepository::tieneMarcaDesembolso($novedad)) {
            return NovedadRepository::esDesembolsoMigrado($novedad);
        }

        try {
            $rolRepo = new \App\repositories\modulos\RolPagoRepository();
            $monto = \App\models\CatalogoNovedades::esPagoPorEgreso($cod)
                ? ($rolRepo->getAnticiposPagadosMasivo($idEmpresa, [$idNovedad])[$idNovedad] ?? 0.0)
                : $rolRepo->getDesembolsadoPrestamoEmpleado($idEmpresa, $idEmpleado, '9');
            return $monto > 0.001;
        } catch (\Throwable $e) {
            return false; // roles/egresos no desplegado → sin restricción
        }
    }

    /**
     * Auto-regenera el rol 'generado' afectado por el cambio de novedad (silencioso
     * si falla). Público para que la reversión de una carga masiva
     * (NovedadCargaService) resincronice los mismos períodos sin duplicar la lógica.
     */
    public function sincronizarRol(int $idEmpresa, ?string $aplicaEn, $anio, $mes, int $idUsuario): void
    {
        if ((int) $mes < 1 || (int) $anio < 2000) return;
        try {
            $rolSvc = new RolPagoService(
                new \App\repositories\modulos\RolPagoRepository(),
                new \App\Rules\modulos\RolPagoRules(),
                $this->logService
            );
            $rolSvc->regenerarAfectados($idEmpresa, (string) ($aplicaEn ?: 'rol'), (int) $anio, (int) $mes, $idUsuario);
        } catch (\Throwable $e) {
            // Silencioso: la novedad ya se guardó.
        }
    }

    /**
     * Selector "Por pagar / Ya pagado" del modal (Anticipo, Quincena y Préstamo Empresa):
     * convierte `ya_pagado` en las marcas de la tabla.
     *  - Ya pagado: entregado fuera del sistema → `desembolsado_migrado` (el rol lo descuenta
     *    directo, sin egreso) + `desembolso_manual` (no es migrado: se sigue editando).
     *  - Por pagar: sin marcas → se paga en Egresos → Nómina y el rol descuenta lo pagado.
     *  - Sin dato (selector bloqueado, o llamadas que no lo conocen como la generación desde
     *    asistencia): al editar se conserva lo que tenía; al crear, por pagar.
     * En los demás tipos la marca no aplica: al editar se limpia (p. ej. un Anticipo que se
     * cambia a Descuento). Si `ya_pagado` no viene en $data no se toca nada.
     * El candado de lo ya desembolsado por egreso lo pone bloquearSiYaDesembolsada().
     */
    private function aplicarMarcaPago(array $data, ?array $old): array
    {
        if (!array_key_exists('ya_pagado', $data)) {
            return $data;
        }
        $ya = $data['ya_pagado'];
        unset($data['ya_pagado']);
        $tipo = trim((string) ($data['tipo_codigo'] ?? ''));

        if (!NovedadPlantillaService::requiereEntrega($tipo)) {
            if ($old !== null) {
                $data['desembolsado_migrado'] = false;
                $data['desembolso_manual'] = false;
            }
            return $data;
        }
        if ($ya === null) {
            $ya = $old !== null && NovedadRepository::tieneMarcaDesembolso($old);
        }
        $ya = (bool) $ya;
        // Lo cargado por Excel (id_carga) ya se distingue de lo migrado sin esta marca.
        $manual = $ya && empty($old['id_carga']);
        if ($manual && !$this->repository->tieneDesembolsoManual()) {
            throw new Exception('Para marcar una novedad como "Ya pagado" falta aplicar en la base de datos el script database/2026-11-18_novedades_desembolso_manual.sql. Márquela como "Por pagar" o pida que se aplique.');
        }
        $data['desembolsado_migrado'] = $ya;
        $data['desembolso_manual'] = $manual;
        return $data;
    }

    /**
     * Denormaliza nombres desde el catálogo y limpia campos según el tipo.
     */
    private function normalizar(array $data, int $idEmpresa): array
    {
        $tipo = trim((string) ($data['tipo_codigo'] ?? ''));
        $data['tipo_nombre'] = CatalogoNovedades::nombreTipo($tipo) ?? '';

        if (CatalogoNovedades::esAvisoSalida($tipo)) {
            $motivo = trim((string) ($data['motivo_codigo'] ?? ''));
            $data['motivo_codigo'] = $motivo !== '' ? $motivo : null;
            $data['motivo_nombre'] = $motivo !== '' ? CatalogoNovedades::nombreMotivo($motivo) : null;
            $data['valor'] = 0; // Aviso de salida no lleva valor
        } else {
            $data['motivo_codigo'] = null;
            $data['motivo_nombre'] = null;
        }

        // Días no laborados y aviso de salida solo afectan al rol mensual: en la
        // quincena y la semana solo hay ingresos y descuentos.
        if (CatalogoNovedades::soloRolMensual($tipo)) {
            $data['aplica_en'] = 'rol';
        }

        // Aporta al IESS: solo Otros Ingresos y horas; sin marca, la del tipo. Igual
        // que en los rubros fijos, si el empleado no aporta al IESS sus ingresos
        // tampoco. En los demás tipos la marca no aplica (NULL).
        if (CatalogoNovedades::admiteOpcionIess($tipo)) {
            $aporta = CatalogoNovedades::aportaIess($tipo, $data['aporta_iess'] ?? null);
            if ($aporta && $this->empleadoAportaIess($idEmpresa, (int) ($data['id_empleado'] ?? 0)) === false) {
                $aporta = false;
            }
            $data['aporta_iess'] = $aporta;
        } else {
            $data['aporta_iess'] = null;
        }

        return $data;
    }

    /** ¿El empleado aporta al IESS? null si no existe (las reglas lo rechazan después). */
    private function empleadoAportaIess(int $idEmpresa, int $idEmpleado): ?bool
    {
        if ($idEmpleado <= 0) {
            return null;
        }
        $clave = $idEmpresa . '|' . $idEmpleado;
        if (!array_key_exists($clave, $this->aportaIessEmpleado)) {
            $this->aportaIessEmpleado[$clave] = $this->repository->empleadoAportaIess($idEmpleado, $idEmpresa);
        }
        return $this->aportaIessEmpleado[$clave];
    }
}
