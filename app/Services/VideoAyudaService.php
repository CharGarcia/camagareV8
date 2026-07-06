<?php
/**
 * Servicio VideoAyuda - Lógica de negocio del catálogo global de videos de ayuda.
 *
 * Responsabilidades (CLAUDE.md §3, §7, §8):
 *   - Validar y almacenar el archivo de video en storage/videos_ayuda/.
 *   - Orquestar transacción y auditoría (log_sistema) en crear/actualizar/eliminar.
 *   - Garantizar que un fallo revierta TODO (rollback + limpieza del archivo).
 *
 * Sólo el superadministrador (nivel 3) invoca estas operaciones; la validación
 * de nivel se hace en el controlador.
 */

declare(strict_types=1);

namespace App\Services;

use App\models\VideoAyuda;

class VideoAyudaService
{
    private const TABLA        = 'videos_ayuda';
    private const STORAGE_DIR  = 'storage/videos_ayuda';
    private const MAX_BYTES    = 524288000; // 500 MB por archivo
    private const EXT_PERMITIDAS  = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];
    private const MIME_PERMITIDOS = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-m4v',
    ];

    private \PDO $db;
    private VideoAyuda $model;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->db    = \App\core\Database::getConnection();
        $this->model = new VideoAyuda();
        $this->log   = new LogSistemaService();
    }

    /**
     * Crea un video: valida y guarda el archivo, inserta el registro y audita.
     *
     * @param array<string,mixed> $meta  titulo, descripcion, categoria, orden, estado
     * @param array<string,mixed> $file  entrada de $_FILES['archivo']
     * @return int id del video creado
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function crear(array $meta, array $file, int $idUsuario): int
    {
        $titulo = trim((string) ($meta['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('El título es obligatorio.');
        }

        // Guarda el archivo primero (fuera de la transacción de BD).
        $guardado = $this->guardarArchivo($file);

        try {
            $this->db->beginTransaction();

            $id = $this->model->crear([
                'titulo'          => $titulo,
                'descripcion'     => $this->limpiar($meta['descripcion'] ?? null),
                'categoria'       => $this->limpiar($meta['categoria'] ?? null),
                'etiquetas'       => $this->limpiar($meta['etiquetas'] ?? null),
                'archivo'         => $guardado['archivo'],
                'nombre_original' => $guardado['nombre_original'],
                'mime_type'       => $guardado['mime_type'],
                'tamano_bytes'    => $guardado['tamano_bytes'],
                'orden'           => (int) ($meta['orden'] ?? 0),
                'estado'          => $this->normalizarEstado($meta['estado'] ?? 'activo'),
                'created_by'      => $idUsuario,
            ]);

            $this->log->registrar($idUsuario, null, 'crear', self::TABLA, $id, null, [
                'titulo'   => $titulo,
                'archivo'  => $guardado['archivo'],
            ]);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->borrarFisico($guardado['archivo']); // limpieza: no dejar archivo huérfano
            throw new \RuntimeException('No se pudo registrar el video: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza metadatos y, opcionalmente, reemplaza el archivo del video.
     *
     * @param array<string,mixed> $meta
     * @param array<string,mixed>|null $file  null = conservar archivo actual
     */
    public function actualizar(int $id, array $meta, ?array $file, int $idUsuario): void
    {
        $actual = $this->model->find($id);
        if ($actual === null) {
            throw new \InvalidArgumentException('El video no existe.');
        }
        $titulo = trim((string) ($meta['titulo'] ?? ''));
        if ($titulo === '') {
            throw new \InvalidArgumentException('El título es obligatorio.');
        }

        // ¿Reemplazo de archivo? Sólo si vino un archivo válido.
        $reemplazo = null;
        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $reemplazo = $this->guardarArchivo($file);
        }

        $data = [
            'titulo'      => $titulo,
            'descripcion' => $this->limpiar($meta['descripcion'] ?? null),
            'categoria'   => $this->limpiar($meta['categoria'] ?? null),
            'etiquetas'   => $this->limpiar($meta['etiquetas'] ?? null),
            'orden'       => (int) ($meta['orden'] ?? 0),
            'estado'      => $this->normalizarEstado($meta['estado'] ?? 'activo'),
            'updated_by'  => $idUsuario,
        ];
        if ($reemplazo !== null) {
            $data['archivo']         = $reemplazo['archivo'];
            $data['nombre_original'] = $reemplazo['nombre_original'];
            $data['mime_type']       = $reemplazo['mime_type'];
            $data['tamano_bytes']    = $reemplazo['tamano_bytes'];
        }

        try {
            $this->db->beginTransaction();
            $this->model->actualizar($id, $data);
            $this->log->registrar($idUsuario, null, 'actualizar', self::TABLA, $id, $actual, $data);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($reemplazo !== null) {
                $this->borrarFisico($reemplazo['archivo']); // deshacer el archivo nuevo
            }
            throw new \RuntimeException('No se pudo actualizar el video: ' . $e->getMessage(), 0, $e);
        }

        // Commit OK: si hubo reemplazo, borrar el archivo anterior (liberar disco).
        if ($reemplazo !== null && !empty($actual['archivo'])) {
            $this->borrarFisico((string) $actual['archivo']);
        }
    }

    /**
     * Elimina lógicamente el video y borra el archivo físico (liberar disco del servidor).
     */
    public function eliminar(int $id, int $idUsuario): void
    {
        $actual = $this->model->find($id);
        if ($actual === null) {
            throw new \InvalidArgumentException('El video no existe.');
        }

        try {
            $this->db->beginTransaction();
            $this->model->eliminarLogico($id, $idUsuario);
            $this->log->registrar($idUsuario, null, 'eliminar', self::TABLA, $id, $actual, null);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo eliminar el video: ' . $e->getMessage(), 0, $e);
        }

        // Commit OK: liberar el archivo del disco (la eliminación es lógica en BD).
        if (!empty($actual['archivo'])) {
            $this->borrarFisico((string) $actual['archivo']);
        }
    }

    /**
     * Registra una vista del video: incrementa el contador y guarda el detalle
     * (usuario, empresa activa, IP, user agent) en una transacción.
     * Devuelve true si el video existe/activo y se contó la vista.
     */
    public function registrarVista(int $idVideo, ?int $idUsuario, ?int $idEmpresa, string $ip, string $userAgent): bool
    {
        try {
            $this->db->beginTransaction();
            $contada = $this->model->incrementarVista($idVideo);
            if ($contada) {
                $this->model->insertarVistaDetalle($idVideo, $idUsuario, $idEmpresa, $ip, mb_substr($userAgent, 0, 500));
            }
            $this->db->commit();
            return $contada;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Analítica de uso: un fallo aquí no debe interrumpir la reproducción.
            return false;
        }
    }

    /**
     * Da o quita el "me gusta" del usuario a un video (toggle) y sincroniza el
     * contador. Devuelve el nuevo estado y el total de likes.
     *
     * @return array{liked:bool,likes:int}
     */
    public function toggleLike(int $idVideo, int $idUsuario): array
    {
        $video = $this->model->find($idVideo);
        if ($video === null) {
            throw new \InvalidArgumentException('El video no existe.');
        }

        try {
            $this->db->beginTransaction();
            $yaDio = $this->model->usuarioDioLike($idVideo, $idUsuario);
            if ($yaDio) {
                $this->model->eliminarLike($idVideo, $idUsuario);
            } else {
                $this->model->insertarLike($idVideo, $idUsuario);
            }
            $likes = $this->model->contarLikes($idVideo);
            $this->model->actualizarContadorLikes($idVideo, $likes);
            $this->db->commit();
            return ['liked' => !$yaDio, 'likes' => $likes];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw new \RuntimeException('No se pudo registrar el me gusta: ' . $e->getMessage(), 0, $e);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers internos
    // ────────────────────────────────────────────────────────────────────

    /**
     * Valida y mueve el archivo subido a storage/videos_ayuda/ con nombre único.
     *
     * @param array<string,mixed> $file
     * @return array{archivo:string,nombre_original:string,mime_type:string,tamano_bytes:int}
     */
    private function guardarArchivo(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('Debe seleccionar un archivo de video.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException(
                'El archivo excede el tamaño máximo permitido por el servidor (revise upload_max_filesize / post_max_size en php.ini).'
            );
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }

        $tam = (int) ($file['size'] ?? 0);
        if ($tam <= 0) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }
        if ($tam > self::MAX_BYTES) {
            throw new \InvalidArgumentException(
                'El video supera el máximo de ' . (int) (self::MAX_BYTES / 1048576) . ' MB.'
            );
        }

        $nombreOrig = (string) $file['name'];
        $ext = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT_PERMITIDAS, true)) {
            throw new \InvalidArgumentException(
                'Formato no permitido. Use: ' . implode(', ', self::EXT_PERMITIDAS) . '.'
            );
        }

        // MIME real del archivo (defensa adicional a la extensión).
        $mime = '';
        $tmp  = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_uploaded_file($tmp) && function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $mime = (string) finfo_file($fi, $tmp);
                finfo_close($fi);
            }
        }
        if ($mime === '') {
            $mime = (string) ($file['type'] ?? 'application/octet-stream');
        }
        if (!in_array($mime, self::MIME_PERMITIDOS, true) && !str_starts_with($mime, 'video/')) {
            throw new \InvalidArgumentException('El archivo no es un video válido (' . $mime . ').');
        }

        $dir = $this->storagePath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de almacenamiento.');
        }

        $nombreUnico = uniqid('vid_', true) . '.' . $ext;
        $destino = $dir . '/' . $nombreUnico;
        if (!move_uploaded_file($tmp, $destino)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return [
            'archivo'         => $nombreUnico,
            'nombre_original' => $nombreOrig,
            'mime_type'       => $mime,
            'tamano_bytes'    => $tam,
        ];
    }

    private function storagePath(): string
    {
        return MVC_ROOT . '/' . self::STORAGE_DIR;
    }

    private function borrarFisico(string $archivo): void
    {
        if ($archivo === '') {
            return;
        }
        // Sólo el basename: nunca permitir rutas para evitar borrados fuera del directorio.
        $safe = basename($archivo);
        $ruta = $this->storagePath() . '/' . $safe;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    private function limpiar(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function normalizarEstado(mixed $estado): string
    {
        return ((string) $estado) === 'inactivo' ? 'inactivo' : 'activo';
    }
}
