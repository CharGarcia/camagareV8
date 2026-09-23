<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\ConciliacionPerfilRepository;
use App\Rules\ConciliacionPerfilRules;
use App\Services\modulos\ConciliacionImportService;

/**
 * Lógica de negocio del catálogo global de Perfiles de Mapeo de extractos bancarios
 * (config/conciliacion-perfiles, nivel 3). Cada perfil describe cómo leer el estado de
 * cuenta de un banco (Excel/CSV por columnas o PDF por patrón de línea) y lo eligen
 * todas las empresas en modulos/conciliacion-cobros al subir un extracto.
 * Auditoría con id_empresa NULL: es configuración global.
 */
class ConciliacionPerfilService
{
    private ConciliacionPerfilRepository $repo;
    private ConciliacionPerfilRules $rules;
    private ConciliacionImportService $importService;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->repo = new ConciliacionPerfilRepository();
        $this->rules = new ConciliacionPerfilRules();
        $this->importService = new ConciliacionImportService();
        $this->log = new LogSistemaService();
    }

    public function listar(string $buscar = ''): array
    {
        return $this->repo->getAll($buscar);
    }

    /** Perfiles activos para el selector "Formato del banco" de Conciliación de Cobros. */
    public function getActivos(): array
    {
        return $this->repo->getActivos();
    }

    /** Perfil para procesar un extracto: solo si existe, no está eliminado y está activo. */
    public function getActivoPorId(int $id): ?array
    {
        return $this->repo->getById($id, true);
    }

    public function guardar(array $data, int $idUsuario): array
    {
        $data['tipo_archivo'] = strtoupper((string) ($data['tipo_archivo'] ?? ''));
        $this->rules->validarPerfil($data);

        $data['nombre_perfil'] = trim((string) $data['nombre_perfil']);
        $data['id_banco'] = (int) ($data['id_banco'] ?? 0) ?: null;
        $data['activo'] = !array_key_exists('activo', $data) || !empty($data['activo']);
        $data['usuario_id'] = $idUsuario;

        $this->repo->beginTransaction();
        try {
            if (!empty($data['id'])) {
                $id = (int) $data['id'];
                $antes = $this->repo->getById($id);
                if (!$antes) {
                    throw new \Exception('El perfil indicado no existe.');
                }
                $this->repo->actualizar($id, $data);
                $accion = 'actualizar';
            } else {
                $antes = null;
                $id = $this->repo->crear($data);
                $accion = 'crear';
            }

            $perfil = $this->repo->getById($id) ?? [];
            $this->log->registrar($idUsuario, null, $accion, 'conciliacion_perfiles', $id, $antes, $perfil);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }

        return $perfil;
    }

    public function eliminar(int $id, int $idUsuario): void
    {
        $antes = $this->repo->getById($id);
        if (!$antes) {
            throw new \Exception('El perfil indicado no existe.');
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->eliminar($id, $idUsuario);
            $this->log->registrar($idUsuario, null, 'eliminar', 'conciliacion_perfiles', $id, $antes, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Prueba del mapeo con un archivo de muestra (no guarda nada) ──────────

    public function previsualizarArchivo(array $file, string $tipoArchivo, int $filaInicio = 0, ?string $regexPrueba = null, ?string $tipoCreditoPrueba = null): array
    {
        $tipoArchivo = strtoupper($tipoArchivo);
        $rutaTemporal = $this->validarYObtenerTmp($file, $tipoArchivo);
        return $this->importService->previsualizar($rutaTemporal, $tipoArchivo, $filaInicio, 60, $regexPrueba, $tipoCreditoPrueba);
    }

    /** Analiza un PDF de muestra y propone un patrón (regex) de línea de datos (ver ConciliacionImportService::sugerirRegexPdf). */
    public function sugerirRegexPdf(array $file): array
    {
        $rutaTemporal = $this->validarYObtenerTmp($file, 'PDF');
        return $this->importService->sugerirRegexPdf($rutaTemporal);
    }

    private function validarYObtenerTmp(array $file, string $tipoArchivo): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $extsValidas = $tipoArchivo === 'PDF' ? ['pdf'] : ['xlsx', 'xls', 'csv'];
        if (!in_array($ext, $extsValidas, true)) {
            throw new \InvalidArgumentException('El archivo debe ser de tipo: ' . implode(', ', $extsValidas) . '.');
        }

        return (string) $file['tmp_name'];
    }
}
