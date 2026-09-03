<?php
/**
 * Servicio NovedadSistema - Lógica de negocio de las novedades globales.
 *
 * Responsabilidades (CLAUDE.md §3, §7, §8):
 *   - Validar y limpiar los datos (el contenido HTML del editor se reduce a
 *     una lista blanca de etiquetas: solo texto con formato, sin scripts).
 *   - Transacción + auditoría (log_sistema, id_empresa = NULL por ser global)
 *     en crear / actualizar / cambiar estado / eliminar.
 *   - Registrar lecturas ("Entendido").
 *
 * Solo el superadministrador (nivel 3) crea/edita; la validación de nivel se
 * hace en el controlador.
 */

declare(strict_types=1);

namespace App\Services;

use App\models\NovedadSistema;

class NovedadSistemaService
{
    private const TABLA = 'novedades_sistema';

    /** Etiquetas HTML que se conservan del editor (solo texto con formato). */
    private const TAGS_PERMITIDOS = '<p><br><b><strong><i><em><u><s><ul><ol><li><h2><h3><a><blockquote><code>';

    /** Adjuntos: carpeta, tamaño máximo por archivo y formatos admitidos. */
    public const STORAGE_DIR = 'storage/novedades_sistema';
    public const MAX_ADJUNTO_BYTES = 20971520; // 20 MB
    private const EXT_ADJUNTO = [
        'pdf', 'xls', 'xlsx', 'csv', 'doc', 'docx', 'ppt', 'pptx', 'txt',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip',
    ];
    /** MIME reales admitidos (además de los que empiezan por image/). */
    private const MIME_ADJUNTO = [
        'application/pdf', 'text/plain', 'text/csv', 'application/csv',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip', 'application/x-zip-compressed', 'application/octet-stream',
    ];

    private \PDO $db;
    private NovedadSistema $model;
    private LogSistemaService $log;

    public function __construct()
    {
        $this->db    = \App\core\Database::getConnection();
        $this->model = new NovedadSistema();
        $this->log   = new LogSistemaService();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Lado del usuario
    // ────────────────────────────────────────────────────────────────────

    /**
     * Estado para la ventana: lista de novedades vigentes (con marca de leída),
     * cuántas faltan por leer y si corresponde abrir la ventana sola ahora
     * (hay pendientes).
     *
     * @return array{mostrar:bool,pendientes:int,novedades:array<int,array<string,mixed>>}
     */
    public function estadoParaUsuario(int $idUsuario): array
    {
        $novedades  = $this->model->getPublicadas($idUsuario);
        $pendientes = 0;
        foreach ($novedades as &$n) {
            $n['leida'] = filter_var($n['leida'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (!$n['leida']) {
                $pendientes++;
            }
        }
        unset($n);

        // Regla única: mientras haya novedades sin leer, la tarjeta se muestra
        // al ingresar (el controlador limita a una vez por inicio de sesión).
        return ['mostrar' => $pendientes > 0, 'pendientes' => $pendientes, 'novedades' => $novedades];
    }

    /**
     * "Entendido": marca como leídas TODAS las novedades vigentes del usuario
     * (o solo las indicadas en $ids, si vienen).
     *
     * @param int[] $ids
     */
    public function marcarLeidas(int $idUsuario, ?int $idEmpresa, array $ids, string $ip, string $ua): int
    {
        $vigentes = $this->model->getIdsVigentes();
        $ids = $ids === [] ? $vigentes : array_values(array_intersect(array_map('intval', $ids), $vigentes));
        if ($ids === []) {
            return 0;
        }
        try {
            $this->db->beginTransaction();
            $nuevas = $this->model->marcarLeidas($ids, $idUsuario, $idEmpresa, $ip, $ua);
            $this->db->commit();
            return $nuevas;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Gestión (superadmin)
    // ────────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $in datos del formulario
     * @return int id creado
     * @throws \InvalidArgumentException
     */
    public function crear(array $in, int $idUsuario): int
    {
        $d = $this->prepararDatos($in, null);
        $d['created_by'] = $idUsuario;
        try {
            $this->db->beginTransaction();
            $id = $this->model->crear($d);
            $this->log->registrar($idUsuario, null, 'crear', self::TABLA, $id, null, $this->resumenLog($d));
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $in
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function actualizar(int $id, array $in, int $idUsuario): void
    {
        $antes = $this->model->find($id);
        if ($antes === null) {
            throw new \RuntimeException('La novedad no existe o fue eliminada.');
        }
        $d = $this->prepararDatos($in, $antes);
        $d['updated_by'] = $idUsuario;
        try {
            $this->db->beginTransaction();
            $this->model->actualizar($id, $d);
            $this->log->registrar($idUsuario, null, 'actualizar', self::TABLA, $id, $this->resumenLog($antes), $this->resumenLog($d));
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Publicar / archivar / devolver a borrador.
     *
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public function cambiarEstado(int $id, string $estado, int $idUsuario): void
    {
        $estado = strtolower(trim($estado));
        if (!in_array($estado, NovedadSistema::ESTADOS, true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
        $antes = $this->model->find($id);
        if ($antes === null) {
            throw new \RuntimeException('La novedad no existe o fue eliminada.');
        }
        // Al publicar por primera vez se fija la fecha de publicación (ahora).
        $publicadoAt = ($estado === 'publicada' && empty($antes['publicado_at'])) ? date('Y-m-d H:i:s') : null;
        try {
            $this->db->beginTransaction();
            $this->model->cambiarEstado($id, $estado, $publicadoAt, $idUsuario);
            $this->log->registrar($idUsuario, null, 'cambiar_estado', self::TABLA, $id,
                ['estado' => $antes['estado']], ['estado' => $estado, 'titulo' => $antes['titulo']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Eliminación lógica. */
    public function eliminar(int $id, int $idUsuario): void
    {
        $antes = $this->model->find($id);
        if ($antes === null) {
            throw new \RuntimeException('La novedad no existe o ya fue eliminada.');
        }
        try {
            $this->db->beginTransaction();
            $this->model->eliminar($id, $idUsuario);
            $this->log->registrar($idUsuario, null, 'eliminar', self::TABLA, $id, $this->resumenLog($antes), null);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Adjuntos (superadmin)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Sube uno o varios archivos a una novedad. Cada archivo se valida, se
     * guarda en storage/novedades_sistema/ y se registra (transacción +
     * auditoría por archivo). Si un archivo falla, se informa y se sigue con
     * el resto.
     *
     * @param array<int,array<string,mixed>> $files lista de entradas tipo $_FILES (una por archivo)
     * @return array{subidos:array<int,array<string,mixed>>,errores:string[]}
     */
    public function subirAdjuntos(int $idNovedad, array $files, int $idUsuario): array
    {
        $novedad = $this->model->find($idNovedad);
        if ($novedad === null) {
            throw new \RuntimeException('La novedad no existe o fue eliminada.');
        }
        $subidos = [];
        $errores = [];
        foreach ($files as $file) {
            $nombre = (string) ($file['name'] ?? 'archivo');
            try {
                $g = $this->guardarArchivo($file);
                try {
                    $this->db->beginTransaction();
                    $id = $this->model->crearAdjunto([
                        'id_novedad'      => $idNovedad,
                        'nombre_original' => $g['nombre_original'],
                        'archivo'         => $g['archivo'],
                        'mime_type'       => $g['mime_type'],
                        'tamano_bytes'    => $g['tamano_bytes'],
                        'orden'           => $this->model->siguienteOrdenAdjunto($idNovedad),
                        'created_by'      => $idUsuario,
                    ]);
                    $this->log->registrar($idUsuario, null, 'adjuntar', self::TABLA, $idNovedad, null, [
                        'id_adjunto' => $id, 'archivo' => $g['nombre_original'], 'bytes' => $g['tamano_bytes'],
                    ]);
                    $this->db->commit();
                    $subidos[] = ['id' => $id, 'nombre_original' => $g['nombre_original']];
                } catch (\Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    @unlink($this->storagePath() . '/' . $g['archivo']);
                    throw $e;
                }
            } catch (\Throwable $e) {
                $errores[] = $nombre . ': ' . $e->getMessage();
            }
        }
        return ['subidos' => $subidos, 'errores' => $errores];
    }

    /** Elimina (lógico) un adjunto y borra el archivo físico para liberar disco. */
    public function eliminarAdjunto(int $idAdjunto, int $idUsuario): void
    {
        $a = $this->model->findAdjunto($idAdjunto);
        if ($a === null) {
            throw new \RuntimeException('El adjunto no existe o ya fue eliminado.');
        }
        try {
            $this->db->beginTransaction();
            $this->model->eliminarAdjunto($idAdjunto, $idUsuario);
            $this->log->registrar($idUsuario, null, 'eliminar_adjunto', self::TABLA, (int) $a['id_novedad'],
                ['id_adjunto' => $idAdjunto, 'archivo' => $a['nombre_original']], null);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        $ruta = $this->storagePath() . '/' . basename((string) $a['archivo']);
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /** Ruta absoluta de la carpeta de adjuntos. */
    public function storagePath(): string
    {
        return MVC_ROOT . '/' . self::STORAGE_DIR;
    }

    /**
     * Valida y mueve un archivo subido. Devuelve nombre_original, archivo
     * (nombre físico único), mime_type y tamano_bytes.
     *
     * @param array<string,mixed> $file entrada tipo $_FILES
     * @return array{nombre_original:string,archivo:string,mime_type:string,tamano_bytes:int}
     * @throws \InvalidArgumentException|\RuntimeException
     */
    private function guardarArchivo(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || empty($file['name'])) {
            throw new \InvalidArgumentException('No se recibió el archivo.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new \InvalidArgumentException('Excede el tamaño máximo que admite el servidor.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Error al recibir el archivo (código ' . $error . ').');
        }
        $tam = (int) ($file['size'] ?? 0);
        if ($tam <= 0) {
            throw new \InvalidArgumentException('El archivo está vacío.');
        }
        if ($tam > self::MAX_ADJUNTO_BYTES) {
            throw new \InvalidArgumentException('Supera el máximo de ' . (int) (self::MAX_ADJUNTO_BYTES / 1048576) . ' MB.');
        }

        $nombreOrig = trim((string) $file['name']);
        $ext = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXT_ADJUNTO, true)) {
            throw new \InvalidArgumentException('Formato no permitido. Use: ' . implode(', ', self::EXT_ADJUNTO) . '.');
        }

        $tmp  = (string) ($file['tmp_name'] ?? '');
        $mime = '';
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
        if (!in_array($mime, self::MIME_ADJUNTO, true) && !str_starts_with($mime, 'image/')) {
            throw new \InvalidArgumentException('El contenido del archivo no coincide con un formato permitido (' . $mime . ').');
        }

        $dir = $this->storagePath();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta de adjuntos.');
        }
        $nombreUnico = uniqid('nov_', true) . '.' . $ext;
        if (!move_uploaded_file($tmp, $dir . '/' . $nombreUnico)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return [
            'nombre_original' => mb_substr($nombreOrig, 0, 255),
            'archivo'         => $nombreUnico,
            'mime_type'       => mb_substr($mime, 0, 120),
            'tamano_bytes'    => $tam,
        ];
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Valida y normaliza los datos del formulario.
     *
     * @param array<string,mixed> $in
     * @param array<string,mixed>|null $antes registro actual (al editar)
     * @return array<string,mixed>
     * @throws \InvalidArgumentException
     */
    private function prepararDatos(array $in, ?array $antes): array
    {
        $titulo = trim(strip_tags((string) ($in['titulo'] ?? '')));
        if ($titulo === '') {
            throw new \InvalidArgumentException('El título es obligatorio.');
        }
        if (mb_strlen($titulo) > 200) {
            throw new \InvalidArgumentException('El título no puede superar 200 caracteres.');
        }

        $tipo = strtolower(trim((string) ($in['tipo'] ?? 'nuevo')));
        if (!in_array($tipo, NovedadSistema::TIPOS, true)) {
            throw new \InvalidArgumentException('Tipo de novedad no válido.');
        }

        $estado = strtolower(trim((string) ($in['estado'] ?? 'borrador')));
        if (!in_array($estado, NovedadSistema::ESTADOS, true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }

        $contenido = $this->limpiarHtml((string) ($in['contenido'] ?? ''));
        if (trim(strip_tags($contenido)) === '') {
            throw new \InvalidArgumentException('Escriba el contenido de la novedad.');
        }

        // Vigencia obligatoria: toda novedad tiene fecha de caducidad.
        $vigente = trim((string) ($in['vigente_hasta'] ?? ''));
        if ($vigente === '') {
            throw new \InvalidArgumentException('La fecha "Vigente hasta" es obligatoria.');
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $vigente);
        if (!$dt || $dt->format('Y-m-d') !== $vigente) {
            throw new \InvalidArgumentException('La fecha "Vigente hasta" no es válida.');
        }

        // Fecha de publicación: se conserva la existente; si pasa a publicada y
        // no tenía, se fija ahora.
        $publicadoAt = $antes['publicado_at'] ?? null;
        if ($estado === 'publicada' && empty($publicadoAt)) {
            $publicadoAt = date('Y-m-d H:i:s');
        }

        // Enlace libre: URL http/https o ruta interna del sistema (empieza con "/").
        // Nunca javascript:, data:, etc.
        $enlace = trim((string) ($in['enlace'] ?? ''));
        if ($enlace !== '') {
            if (mb_strlen($enlace) > 500) {
                throw new \InvalidArgumentException('El enlace no puede superar 500 caracteres.');
            }
            if (!preg_match('#^(https?://[^\s<>"\']+|/[^\s<>"\']*)$#i', $enlace)) {
                throw new \InvalidArgumentException('El enlace debe empezar con https:// (o http://) o ser una ruta interna que empiece con "/".');
            }
        }

        // Módulo relacionado: se elige del catálogo de submódulos (submodulos_menu).
        // Se guarda la ruta (para el enlace "Abrir módulo" y el manual contextual)
        // y se copia el nombre para mostrarlo sin volver a consultar el catálogo.
        $rutaModulo = trim((string) ($in['ruta_modulo'] ?? ''), "/ \t");
        $nombreModulo = null;
        if ($rutaModulo !== '') {
            $sub = $this->buscarSubmodulo($rutaModulo);
            if ($sub === null) {
                throw new \InvalidArgumentException('El módulo relacionado no existe en el catálogo de submódulos.');
            }
            $nombreModulo = mb_substr((string) $sub['nombre_submodulo'], 0, 120);
        } else {
            $rutaModulo = null;
        }

        return [
            'tipo'          => $tipo,
            'titulo'        => $titulo,
            'resumen'       => $this->limpiar($in['resumen'] ?? null, 300),
            'contenido'     => $contenido,
            'modulo'        => $nombreModulo,
            'ruta_modulo'   => $rutaModulo,
            'enlace'        => $enlace !== '' ? $enlace : null,
            'estado'        => $estado,
            'publicado_at'  => $publicadoAt,
            'vigente_hasta' => $vigente,
        ];
    }

    /**
     * Deja solo texto con formato: lista blanca de etiquetas, sin atributos
     * salvo href seguro en <a> (http/https o ruta interna).
     */
    private function limpiarHtml(string $html): string
    {
        // Bloques <script>/<style> se van completos (etiqueta Y contenido).
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = strip_tags($html, self::TAGS_PERMITIDOS);
        // Quitar todos los atributos salvo href.
        $html = preg_replace_callback('#<([a-z0-9]+)([^>]*)>#i', static function (array $m): string {
            $tag = strtolower($m[1]);
            $attrs = '';
            if ($tag === 'a' && preg_match('#href\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $m[2], $h)) {
                $href = trim($h[2] !== '' ? $h[2] : ($h[3] ?? ''));
                if (preg_match('#^(https?://|/)#i', $href)) {
                    $attrs = ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"';
                }
            }
            return '<' . $tag . $attrs . '>';
        }, $html) ?? '';
        return trim($html);
    }

    /** Submódulo del catálogo por su ruta (null si no existe o está inactivo). */
    private function buscarSubmodulo(string $ruta): ?array
    {
        foreach ((new \App\models\ModuloSubmodulo())->getRutasConNombre() as $s) {
            if (trim((string) ($s['ruta'] ?? ''), '/') === $ruta) {
                return $s;
            }
        }
        return null;
    }

    private function limpiar(mixed $v, int $max): ?string
    {
        $s = trim(strip_tags((string) ($v ?? '')));
        if ($s === '') {
            return null;
        }
        return mb_substr($s, 0, $max);
    }

    /** Datos que se guardan en log_sistema (sin el HTML completo). */
    private function resumenLog(array $d): array
    {
        return [
            'tipo'          => $d['tipo'] ?? null,
            'titulo'        => $d['titulo'] ?? null,
            'estado'        => $d['estado'] ?? null,
            'modulo'        => $d['modulo'] ?? null,
            'ruta_modulo'   => $d['ruta_modulo'] ?? null,
            'vigente_hasta' => $d['vigente_hasta'] ?? null,
            'publicado_at'  => $d['publicado_at'] ?? null,
        ];
    }
}
