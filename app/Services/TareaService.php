<?php
declare(strict_types=1);

namespace App\Services;

use App\repositories\TareaRepository;
use App\Rules\TareaRules;
use App\Services\LogSistemaService;
use Exception;

class TareaService
{
    private TareaRepository  $repository;
    private TareaRules       $rules;
    private LogSistemaService $logService;

    public function __construct(
        TareaRepository   $repository,
        TareaRules        $rules,
        LogSistemaService $logService
    ) {
        $this->repository = $repository;
        $this->rules      = $rules;
        $this->logService = $logService;
    }

    // ─── Listados ─────────────────────────────────────────────

    public function getListado(
        string $buscar,
        int    $page,
        int    $perPage,
        string $ordenCol,
        string $ordenDir,
        bool   $incluirArchivadas = false,
        int    $idUsuario = 0,
        array  $filtros   = [],
        int    $nivel     = 1
    ): array {
        $result = $this->repository->getListado($buscar, $page, $perPage, $ordenCol, $ordenDir, $incluirArchivadas, $idUsuario, $filtros, $nivel);

        // Adjuntar responsables a cada tarea
        foreach ($result['rows'] as &$row) {
            $row['responsables'] = $this->repository->getResponsables((int) $row['id']);
        }
        unset($row);

        return $result;
    }

    // ─── Clientes (listado + combo de obligaciones vigentes) ─────

    public function getClientesListado(string $buscar, int $page, int $perPage, string $ordenCol, string $ordenDir, int $idUsuario, int $nivel): array
    {
        return $this->repository->getClientesListado($buscar, $page, $perPage, $ordenCol, $ordenDir, $idUsuario, $nivel);
    }

    /**
     * "Combo" de un cliente: una fila por cada obligación que tiene vigente
     * (su tarea activa más reciente por obligación), con sus responsables.
     */
    public function getComboCliente(int $idCliente): array
    {
        $rows = $this->repository->getComboVigentePorCliente($idCliente);
        foreach ($rows as &$row) {
            $row['responsables'] = $this->repository->getResponsables((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    // ─── Duplicar combo hacia otro cliente ─────────────────────

    /**
     * Crea una tarea por cada item del combo para el cliente destino (auto-catalogado
     * si no existe). Atómico: todo o nada salvo los items omitidos por ya existir.
     *
     * @param array $destino ['id_cliente'?, 'cliente_nombre', 'cliente_correo']
     * @param array $items   [['id_obligacion','obligacion_nombre'?,'periodicidad','fecha_tarea','responsables'], ...]
     * @return array ['creadas' => int, 'omitidas' => array<string>]
     */
    public function copiarCombo(array $destino, array $items, int $idUsuario): array
    {
        $creadas  = 0;
        $omitidas = [];

        $this->repository->beginTransaction();
        try {
            // Resolver (o crear) el cliente destino una sola vez para todo el lote
            $destinoResuelto = $this->autoCatalogarCliente([
                'id_cliente'     => $destino['id_cliente'] ?? null,
                'cliente_nombre' => trim($destino['cliente_nombre'] ?? ''),
                'cliente_correo' => trim($destino['cliente_correo'] ?? ''),
            ], $idUsuario);
            $idClienteDestino = (int) ($destinoResuelto['id_cliente'] ?? 0);
            if ($idClienteDestino <= 0) {
                throw new Exception('No se pudo determinar el cliente destino.');
            }

            foreach ($items as $item) {
                $idObligacion = (int) ($item['id_obligacion'] ?? 0);

                if ($this->repository->existeObligacionActivaParaCliente($idClienteDestino, $idObligacion)) {
                    $omitidas[] = ($item['obligacion_nombre'] ?? "Obligación #{$idObligacion}") . ' (ya la tiene activa)';
                    continue;
                }

                $data = [
                    'id_obligacion'      => $idObligacion,
                    'id_cliente'         => $idClienteDestino,
                    'cliente_nombre'     => $destinoResuelto['cliente_nombre'],
                    'cliente_correo'     => $destinoResuelto['cliente_correo'],
                    'periodicidad'       => trim($item['periodicidad'] ?? ''),
                    'fecha_tarea'        => trim($item['fecha_tarea'] ?? ''),
                    'estado'             => 'por_realizar',
                    'notas'              => null,
                    'resumen'            => null,
                    'motivo_cancelacion' => null,
                    'id_tarea_origen'    => null,
                    'created_by'         => $idUsuario,
                    'responsables'       => $item['responsables'] ?? [],
                ];

                try {
                    $this->rules->validar($data);
                } catch (\InvalidArgumentException $e) {
                    $omitidas[] = ($item['obligacion_nombre'] ?? "Obligación #{$idObligacion}") . ' (' . $e->getMessage() . ')';
                    continue;
                }

                $this->crearInterno($data);
                $creadas++;
            }

            $this->repository->commit();
            return ['creadas' => $creadas, 'omitidas' => $omitidas];
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── Crear ────────────────────────────────────────────────

    public function crear(array $data): int
    {
        $this->rules->validar($data);

        $this->repository->beginTransaction();
        try {
            $id = $this->crearInterno($data);
            $this->repository->commit();

            // Evaluar notificaciones de tarea en estados aplicables
            if (in_array(trim($data['estado'] ?? ''), ['realizada_continua', 'realizada_finalizada', 'cancelada'], true)) {
                $insertData = $this->prepararDatos($data, (int) $data['created_by'], false);
                $this->intentarNotificarTarea($id, [], $insertData);
            }

            return $id;
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    /**
     * Igual que crear(), sin abrir/cerrar transacción propia: para usar dentro de un
     * lote (ver copiarCombo) que ya maneja su propia transacción externa.
     */
    private function crearInterno(array $data): int
    {
        $idUsuario    = (int) $data['created_by'];
        $responsables = $data['responsables'] ?? [];

        // Auto-catalogar cliente si es nuevo
        $data = $this->autoCatalogarCliente($data, $idUsuario);

        $insertData = $this->prepararDatos($data, $idUsuario, false);
        $id = $this->repository->create($insertData);

        // Responsables (pueden ser usuarios del sistema o propios)
        $this->asignarResponsables($id, $responsables, $idUsuario);

        $this->logService->registrar($idUsuario, null, 'crear', 'tareas', $id, null, $insertData);

        return $id;
    }

    // ─── Actualizar ──────────────────────────────────────────

    public function actualizar(int $id, array $data): void
    {
        $this->rules->validar($data);

        $antes = $this->repository->findByIdGlobal($id);
        if (!$antes) {
            throw new Exception('La tarea no existe o fue eliminada.');
        }

        $idUsuario    = (int) $data['updated_by'];
        $responsables = $data['responsables'] ?? [];
        $estadoNuevo  = trim($data['estado'] ?? '');
        $estadoAntes  = $antes['estado'];

        $this->repository->beginTransaction();
        try {
            // Auto-catalogar cliente si es nuevo
            $data = $this->autoCatalogarCliente($data, $idUsuario);

            $updateData = $this->prepararDatos($data, $idUsuario, true);
            $this->repository->update($id, $updateData);

            // Re-asignar responsables
            $this->repository->deleteResponsables($id);
            $this->asignarResponsables($id, $responsables, $idUsuario);

            $this->logService->registrar($idUsuario, null, 'actualizar', 'tareas', $id, $antes, $updateData);

            // ─── Lógica especial: Realizada y Continua ───────────
            // Solo crear copia si el estado *cambia* a realizada_continua
            if ($estadoNuevo === 'realizada_continua' && $estadoAntes !== 'realizada_continua') {
                $this->crearCopiaRecurrente($id, $antes, $responsables, $idUsuario);
            }

            $this->repository->commit();
            
            // Interceptar notificaciones
            if (in_array($estadoNuevo, ['realizada_continua', 'realizada_finalizada', 'cancelada'], true) && $estadoNuevo !== $estadoAntes) {
                // updateData no tiene obligacion_nombre, etc., fusionamos
                $mergedData = array_merge($antes, $updateData);
                $this->intentarNotificarTarea($id, $antes, $mergedData);
            }
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── Eliminar ─────────────────────────────────────────────

    public function eliminar(int $id, int $idUsuario): void
    {
        $antes = $this->repository->findByIdGlobal($id);
        if (!$antes) {
            throw new Exception('La tarea no existe o ya fue eliminada.');
        }

        $this->repository->beginTransaction();
        try {
            $this->repository->delete($id, $idUsuario);
            $this->logService->registrar($idUsuario, null, 'eliminar', 'tareas', $id, $antes, ['eliminado' => true]);
            $this->repository->commit();
        } catch (Exception $e) {
            $this->repository->rollBack();
            throw $e;
        }
    }

    // ─── Private: Copia Recurrente ────────────────────────────

    /**
     * Crea una nueva tarea copia cuando el estado cambia a "realizada_continua".
     * La nueva tarea nace en estado "por_realizar" con fecha calculada según periodicidad.
     */
    private function crearCopiaRecurrente(int $idOriginal, array $original, array $responsables, int $idUsuario): int
    {
        $proximaFecha = TareaRules::calcularProximaFecha(
            (string) $original['fecha_tarea'],
            (string) $original['periodicidad']
        );

        $copiaData = [
            'id_obligacion'      => $original['id_obligacion'],
            'id_cliente'         => $original['id_cliente'] ?? null,
            'cliente_nombre'     => $original['cliente_nombre'],
            'cliente_correo'     => $original['cliente_correo'],
            'periodicidad'       => $original['periodicidad'],
            'fecha_tarea'        => $proximaFecha,
            'estado'             => 'por_realizar',
            'notas'              => $original['notas'] ?? null,
            'resumen'            => null,
            'motivo_cancelacion' => null,
            'archivada'          => false,
            'id_tarea_origen'    => $idOriginal,
            'created_by'         => $idUsuario,
        ];

        $nuevaId = $this->repository->create($copiaData);

        // Copiar mismos responsables
        $responsablesOriginales = $this->repository->getResponsables($idOriginal);
        foreach ($responsablesOriginales as $r) {
            $this->repository->vincularResponsable($nuevaId, $r);
        }

        $this->logService->registrar(
            $idUsuario,
            null,
            'crear_copia_recurrente',
            'tareas',
            $nuevaId,
            null,
            array_merge($copiaData, ['origen' => $idOriginal])
        );

        return $nuevaId;
    }

    // ─── Private: Asignar Responsables ───────────────────────────

    private function asignarResponsables(int $idTarea, array $responsables, int $idUsuario): void
    {
        $vistos = []; // Evitar duplicados por ID o Correo en el mismo request

        foreach ($responsables as $resp) {
            $rData = [
                'id_usuario'    => null,
                'id_resp_tarea' => null,
                'nombre'        => '',
                'correo'        => ''
            ];

            if (is_numeric($resp)) {
                $rData['id_usuario'] = (int) $resp;
            } elseif (is_array($resp)) {
                $rData['id_usuario']    = !empty($resp['id_usuario']) ? (int) $resp['id_usuario'] : (!empty($resp['id']) && ($resp['tipo']??'') === 'usuario' ? (int)$resp['id'] : null);
                $rData['id_resp_tarea'] = !empty($resp['id_resp_tarea']) ? (int) $resp['id_resp_tarea'] : (!empty($resp['id']) && ($resp['tipo']??'') === 'propio' ? (int)$resp['id'] : null);
                $rData['nombre']        = trim($resp['nombre'] ?? '');
                $rData['correo']        = trim($resp['correo'] ?? $resp['mail'] ?? '');

                // Si es un externo sin ID, intentar catalogarlo o encontrarlo
                if (!$rData['id_usuario'] && !$rData['id_resp_tarea'] && $rData['nombre'] !== '') {
                    $existente = $this->repository->findResponsableTareaByNameEmail($rData['nombre'], $rData['correo']);
                    if ($existente) {
                        $rData['id_resp_tarea'] = (int) $existente['id'];
                        $rData['nombre']        = $existente['nombre'];
                        $rData['correo']        = $existente['correo'];
                    } else {
                        // Crear nuevo en catálogo de responsables
                        $rData['id_resp_tarea'] = $this->repository->createResponsableTarea([
                            'nombre'     => $rData['nombre'],
                            'correo'     => $rData['correo'],
                            'created_by' => $idUsuario
                        ]);
                    }
                }
            }

            // Clave única para evitar duplicados en la misma inserción
            $key = $rData['id_usuario'] ? "u_{$rData['id_usuario']}" : ($rData['id_resp_tarea'] ? "r_{$rData['id_resp_tarea']}" : "n_{$rData['nombre']}");
            if (isset($vistos[$key])) continue;
            $vistos[$key] = true;

            if ($rData['id_usuario'] || $rData['id_resp_tarea'] || $rData['nombre'] !== '') {
                $this->repository->vincularResponsable($idTarea, $rData);
            }
        }
    }

    private function autoCatalogarCliente(array $data, int $idUsuario): array
    {
        $idClient = !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null;
        $nombre   = trim($data['cliente_nombre'] ?? '');
        $correo   = trim($data['cliente_correo'] ?? '');

        // Si hay ID, verificar que existe en clientes_tareas (catálogo local)
        if ($idClient > 0) {
            $propio = $this->repository->findClienteTareaById($idClient);
            if (!$propio) {
                // El ID es de la tabla operativa (empresa), lo ignoramos y buscamos por nombre/correo
                $idClient = null; 
            }
        }

        if (!$idClient && $nombre !== '') {
            $existente = $this->repository->findClienteTareaByNameEmail($nombre, $correo);
            if ($existente) {
                $data['id_cliente'] = (int) $existente['id'];
                $data['cliente_nombre'] = $existente['nombre'];
                $data['cliente_correo'] = $existente['correo'];
            } else {
                // Crear en catálogo de clientes_tareas para satisfacer FK
                $newId = $this->repository->createClienteTarea([
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'created_by' => $idUsuario
                ]);
                $data['id_cliente'] = $newId;
            }
        }
        return $data;
    }

    // ─── Private: Preparar datos ──────────────────────────────


    private function prepararDatos(array $data, int $idUsuario, bool $esUpdate): array
    {
        $estado      = trim($data['estado'] ?? 'por_realizar');
        // Auto-archivar si el estado corresponde a un final de ciclo:
        $archivada   = in_array($estado, ['cancelada', 'realizada_continua', 'realizada_finalizada'], true);
        $fechaTarea  = trim($data['fecha_tarea']);
        $hoy         = date('Y-m-d');

        // REGLA: Gestión automática de estados según fecha (solo para estados pendientes)
        if (in_array($estado, ['por_realizar', 'vencida'])) {
            if ($fechaTarea < $hoy) {
                $estado = 'vencida';
            } else {
                $estado = 'por_realizar';
            }
        }

        $prepared = [
            'id_obligacion'      => (int) $data['id_obligacion'],
            'id_cliente'         => !empty($data['id_cliente']) ? (int) $data['id_cliente'] : null,
            'cliente_nombre'     => trim($data['cliente_nombre']),
            'cliente_correo'     => strtolower(trim($data['cliente_correo'])),
            'periodicidad'       => trim($data['periodicidad']),
            'fecha_tarea'        => $fechaTarea,
            'estado'             => $estado,
            'notas'              => !empty(trim($data['notas'] ?? '')) ? trim($data['notas']) : null,
            'resumen'            => !empty(trim($data['resumen'] ?? '')) ? trim($data['resumen']) : null,
            'motivo_cancelacion' => !empty(trim($data['motivo_cancelacion'] ?? '')) ? trim($data['motivo_cancelacion']) : null,
            'archivada'          => $archivada,
            'id_tarea_origen'    => !empty($data['id_tarea_origen']) ? (int) $data['id_tarea_origen'] : null,
        ];

        if ($esUpdate) {
            $prepared['updated_by'] = $idUsuario;
        } else {
            $prepared['created_by'] = $idUsuario;
        }

        return $prepared;
    }

    // ─── Adjuntos ─────────────────────────────────────────────

    public function getAdjuntos(int $idTarea): array
    {
        return $this->repository->getAdjuntos($idTarea);
    }

    public function addAdjunto(array $data): int
    {
        return $this->repository->addAdjunto($data);
    }

    public function deleteAdjunto(int $idAdjunto, int $idUsuario): ?string
    {
        return $this->repository->deleteAdjunto($idAdjunto, $idUsuario);
    }

    // ─── Busquedas ────────────────────────────────────────────

    public function buscarUsuarios(string $buscar): array
    {
        return [
            'sistema' => $this->repository->buscarUsuariosSistema($buscar),
            'propios' => $this->repository->buscarResponsablesPropios($buscar),
        ];
    }

    public function buscarClientesTareas(string $buscar): array
    {
        return $this->repository->buscarClientesTareas($buscar);
    }

    public function buscarClientesEmpresa(string $buscar, ?int $idEmpresa = null): array
    {
        return $this->repository->buscarClientesEmpresa($buscar, $idEmpresa);
    }

    public function getCorreosClienteTarea(string $nombre): array
    {
        return $this->repository->getCorreosClienteTarea($nombre);
    }

    public function createClienteTarea(array $data): int
    {
        return $this->repository->createClienteTarea($data);
    }

    public function findClienteTareaByRuc(string $ruc): ?array
    {
        return $this->repository->findClienteTareaByRuc($ruc);
    }

    public function updateClienteTarea(int $id, array $data): bool
    {
        return $this->repository->updateClienteTarea($id, $data);
    }

    // ─── Responsables propios ─────────────────────────────────────

    public function createResponsableTarea(array $data): int
    {
        return $this->repository->createResponsableTarea($data);
    }

    public function findResponsableTareaByCedula(string $cedula): ?array
    {
        return $this->repository->findResponsableTareaByCedula($cedula);
    }

    public function updateResponsableTarea(int $id, array $data): bool
    {
        return $this->repository->updateResponsableTarea($id, $data);
    }

    public function getTareaCompleta(int $id): ?array
    {
        $tarea = $this->repository->findByIdCompleto($id);
        if ($tarea) {
            $tarea['responsables'] = $this->repository->getResponsables($id);
            $tarea['adjuntos']     = $this->repository->getAdjuntos($id);
        }
        return $tarea;
    }

    public function getAlertaTareasCount(int $idUsuario): int
    {
        return $this->repository->getAlertaTareasCount($idUsuario);
    }

    public function getResponsablesParaFiltro(): array
    {
        $usuarios = $this->repository->buscarUsuariosSistema('', 100);
        $propios  = $this->repository->buscarResponsablesPropios('', 100);
        return [
            'usuarios' => $usuarios,
            'propios'  => $propios
        ];
    }

    // ─── Utilidades Privadas de Notificación ──────────────────────

    private function intentarNotificarTarea(int $idTarea, array $antes, array $datosRaw): void
    {
        try {
            $logFile = MVC_ROOT . '/debug_mail.log';
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Intentando notificar tarea $idTarea\n", FILE_APPEND);

            $tareaCompleta = $this->getTareaCompleta($idTarea);
            if (!$tareaCompleta) {
                file_put_contents($logFile, "  - Tarea no encontrada\n", FILE_APPEND);
                return;
            }

            $destinatarios = array_map('trim', explode(',', $tareaCompleta['cliente_correo'] ?? ''));
            $destinatarios = array_filter($destinatarios);
            if (empty($destinatarios)) {
                file_put_contents($logFile, "  - Sin destinatarios\n", FILE_APPEND);
                return;
            }

            // Obtener strings de responsables
            $respNombres = [];
            foreach (($tareaCompleta['responsables'] ?? []) as $r) {
                $respNombres[] = trim($r['nombre'] ?? '');
            }
            $tareaCompleta['responsables_str'] = implode(', ', array_filter($respNombres));

            // Fechas
            $tareaCompleta['fecha_realizacion'] = date('Y-m-d');
            $tareaCompleta['proxima_fecha']     = TareaRules::calcularProximaFecha(
                (string)$tareaCompleta['fecha_tarea'],
                (string)$tareaCompleta['periodicidad']
            );

            // Adjuntos físicos
            $adjuntosPaths = [];
            foreach (($tareaCompleta['adjuntos'] ?? []) as $adj) {
                $path = MVC_ROOT . '/' . ($adj['ruta_archivo'] ?? '');
                if (is_file($path)) {
                    $adjuntosPaths[] = $path;
                }
            }

            file_put_contents($logFile, "  - Destinatarios: " . implode(', ', $destinatarios) . "\n", FILE_APPEND);
            file_put_contents($logFile, "  - Adjuntos: " . count($adjuntosPaths) . "\n", FILE_APPEND);

            require_once MVC_APP . '/helpers/mail.php';
            $sent = enviar_correo_notificacion_tarea($destinatarios, $tareaCompleta, $adjuntosPaths);
            
            if (!$sent) {
                file_put_contents($logFile, "  - ERROR envíando. LAST_EMAIL_ERROR: " . ($GLOBALS['LAST_EMAIL_ERROR'] ?? 'Desconocido') . "\n", FILE_APPEND);
            } else {
                file_put_contents($logFile, "  - Éxito\n", FILE_APPEND);
            }

        } catch (Exception $e) {
            $logFile = MVC_ROOT . '/debug_mail.log';
            file_put_contents($logFile, "  - ERROR Excepción: " . $e->getMessage() . "\n", FILE_APPEND);
            error_log("Error al intentar notificar tarea {$idTarea}: " . $e->getMessage());
        }
    }
}
