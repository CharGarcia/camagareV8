<?php

declare(strict_types=1);

namespace App\Services;

use App\repositories\ConciliacionTarjetasPerfilRepository;
use App\Rules\ConciliacionTarjetasPerfilRules;
use App\Services\modulos\ConciliacionTarjetasImportService;

/**
 * Lógica de negocio del catálogo global de Perfiles de lectura del estado de cuenta de
 * las procesadoras de tarjeta (config/conciliacion-tarjetas-perfiles, nivel 3). Cada
 * perfil describe cómo leer el archivo de Payphone, Nuvei o el datáfono de un banco
 * (Excel/CSV por columnas o PDF por patrón de línea) y lo eligen todas las empresas en
 * modulos/conciliacion-tarjetas. Auditoría con id_empresa NULL: es configuración global.
 */
class ConciliacionTarjetasPerfilService
{
    private ConciliacionTarjetasPerfilRepository $repo;
    private ConciliacionTarjetasPerfilRules $rules;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->repo = new ConciliacionTarjetasPerfilRepository();
        $this->rules = new ConciliacionTarjetasPerfilRules();
        $this->log = new LogSistemaService();
    }

    public function listar(string $buscar = ''): array
    {
        return $this->repo->getAll($buscar);
    }

    /** Perfiles activos que se ofrecen al cargar el estado de cuenta de una procesadora. */
    public function getActivosPara(string $tipoProcesadora, ?int $idBanco): array
    {
        return $this->repo->getActivosPara($tipoProcesadora, $idBanco);
    }

    /**
     * Perfil para leer un estado de cuenta: debe existir, estar activo y servir para la
     * procesadora (tipo y banco de la forma de cobro) de la conciliación.
     */
    public function getActivoPara(int $id, string $tipoProcesadora, ?int $idBanco): array
    {
        $perfil = $this->repo->getById($id, true);
        if ($perfil === null) {
            throw new \Exception('El perfil de lectura seleccionado no existe o está inactivo.');
        }
        $this->rules->validarPerfilParaProcesadora($perfil, $tipoProcesadora, $idBanco);
        return $perfil;
    }

    public function guardar(array $data, int $idUsuario): array
    {
        if (is_string($data['mapeo_columnas'] ?? null)) {
            $data['mapeo_columnas'] = json_decode($data['mapeo_columnas'], true) ?: [];
        }
        $data['tipo_archivo'] = strtoupper((string) ($data['tipo_archivo'] ?? ''));
        $data['tipo_procesadora'] = strtoupper(trim((string) ($data['tipo_procesadora'] ?? ''))) ?: null;
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
            $this->log->registrar($idUsuario, null, $accion, 'conciliacion_tarjetas_perfiles', $id, $antes, $perfil);
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
            $this->log->registrar($idUsuario, null, 'eliminar', 'conciliacion_tarjetas_perfiles', $id, $antes, null);
            $this->repo->commit();
        } catch (\Throwable $e) {
            $this->repo->rollBack();
            throw $e;
        }
    }

    // ── Prueba del mapeo con un archivo de muestra (no guarda nada) ──────────

    public function previsualizarArchivo(array $file, string $tipoArchivo, int $filaInicio, ?array $mapeoPrueba, string $formatoFecha, string $separador): array
    {
        $tipoArchivo = strtoupper($tipoArchivo);
        $ruta = $this->copiarTemporal($file, $tipoArchivo);
        try {
            return (new ConciliacionTarjetasImportService())->previsualizar(
                $ruta, $tipoArchivo, $filaInicio, 60, $mapeoPrueba,
                $formatoFecha !== '' ? $formatoFecha : 'd/m/Y', $separador === ',' ? ',' : '.'
            );
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * Copia el archivo subido a un temporal CON su extensión: el lector de hojas de
     * cálculo decide el formato (xlsx / xls / csv) por la extensión del archivo.
     */
    private function copiarTemporal(array $file, string $tipoArchivo): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo.');
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }
        if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('El archivo supera el tamaño máximo permitido (20 MB).');
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $extsValidas = $tipoArchivo === 'PDF' ? ['pdf'] : ['xlsx', 'xls', 'csv'];
        if (!in_array($ext, $extsValidas, true)) {
            throw new \InvalidArgumentException('El archivo debe ser de tipo: ' . implode(', ', $extsValidas) . '.');
        }

        $destino = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . uniqid('ctperf_', true) . '.' . $ext;
        if (!move_uploaded_file((string) $file['tmp_name'], $destino)) {
            throw new \RuntimeException('No se pudo leer el archivo en el servidor.');
        }
        return $destino;
    }
}
