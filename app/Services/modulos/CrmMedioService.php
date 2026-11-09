<?php

declare(strict_types=1);

namespace App\Services\modulos;

use App\core\Database;
use App\Helpers\CryptoHelper;
use App\Helpers\Mp4Info;
use App\repositories\modulos\CrmMedioRepository;
use App\Rules\modulos\CrmMedioRules;
use App\Services\LogSistemaService;

/**
 * CRM fase 3: biblioteca de medios para publicar (docs/diseno/crm-redes-fase3.md).
 *
 * - Imágenes: se validan por contenido y se guardan SIEMPRE como JPEG (lo único
 *   que publica Instagram), giradas según su EXIF, sin transparencia (fondo
 *   blanco) y con el lado mayor en 1440 px como máximo.
 * - Videos (reels): MP4/MOV H.264 o H.265, hasta 100 MB y 90 s; se leen sus
 *   datos sin herramientas externas (Mp4Info).
 * - Instagram descarga el archivo por un enlace PÚBLICO firmado y temporal
 *   (/instagram-datos/medio?t=…); LinkedIn recibe el archivo subido por el sistema.
 * - Lo que no se usa en 90 días y ninguna publicación pendiente necesita se purga.
 */
class CrmMedioService
{
    public const DIR = '/storage/crm_media';
    private const CONTEXTO_FIRMA = 'crm_medio_publicacion';
    /** Instagram puede tardar en descargar un reel: el enlace dura 2 horas. */
    public const MINUTOS_ENLACE = 120;
    private const CALIDAD_JPEG = 88;

    public function __construct(
        private CrmMedioRepository $repo,
        private CrmMedioRules $rules,
        private LogSistemaService $log
    ) {
    }

    public static function crear(): self
    {
        return new self(new CrmMedioRepository(), new CrmMedioRules(), new LogSistemaService());
    }

    public function disponible(): bool
    {
        return $this->repo->disponible();
    }

    // ── Subir ────────────────────────────────────────────────────────────────

    /**
     * Guarda el archivo subido en la biblioteca de la empresa.
     * @return array{id:int, tipo:string, avisos:list<string>}
     */
    public function subir(string $rutaTmp, ?string $nombre, int $bytes, int $idEmpresa, int $idUsuario, ?string $alt = null, bool $esIa = false): array
    {
        $tipo = $this->rules->tipoDe($rutaTmp, $bytes);
        $alt = $this->rules->validarTextoAlternativo($alt);
        $sub = $idEmpresa . '/pub/' . date('Y-m');
        $dir = MVC_ROOT . self::DIR . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo guardar el archivo en el servidor.');
        }
        $base = bin2hex(random_bytes(12));

        if ($tipo === 'imagen') {
            $this->rules->validarImagen($rutaTmp, $bytes);
            $archivo = $base . '.jpg';
            [$ancho, $alto] = $this->aJpeg($rutaTmp, $dir . '/' . $archivo);
            $datos = ['mime' => 'image/jpeg', 'ancho' => $ancho, 'alto' => $alto, 'duracion_seg' => null];
        } else {
            $info = $this->rules->validarVideo(Mp4Info::leer($rutaTmp), $bytes);
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTmp);
            $archivo = $base . ($mime === 'video/quicktime' ? '.mov' : '.mp4');
            $ok = is_uploaded_file($rutaTmp) ? move_uploaded_file($rutaTmp, $dir . '/' . $archivo) : copy($rutaTmp, $dir . '/' . $archivo);
            if (!$ok) {
                throw new \RuntimeException('No se pudo guardar el video en el servidor.');
            }
            $datos = ['mime' => $mime, 'ancho' => $info['ancho'] ?: null, 'alto' => $info['alto'] ?: null, 'duracion_seg' => $info['duracion']];
        }
        $abs = $dir . '/' . $archivo;
        $rel = 'crm_media/' . $sub . '/' . $archivo;
        try {
            $id = $this->enTransaccion(function () use ($idEmpresa, $idUsuario, $tipo, $rel, $nombre, $abs, $datos, $alt, $esIa) {
                $d = $datos + [
                    'tipo' => $tipo, 'ruta' => $rel, 'nombre_original' => $nombre !== null ? mb_substr(basename($nombre), 0, 200) : null,
                    'bytes' => (int) filesize($abs), 'texto_alternativo' => $alt, 'es_ia' => $esIa,
                ];
                $id = $this->repo->insertar($idEmpresa, $d, $idUsuario);
                $this->log->registrar($idUsuario, $idEmpresa, 'CREAR', 'crm_medios', $id, null,
                    array_intersect_key($d, array_flip(['tipo', 'nombre_original', 'mime', 'bytes', 'ancho', 'alto', 'duracion_seg', 'es_ia'])));
                return $id;
            });
        } catch (\Throwable $e) {
            @unlink($abs);
            throw $e;
        }
        return ['id' => $id, 'tipo' => $tipo, 'avisos' => $this->rules->avisos($tipo, (int) $datos['ancho'], (int) $datos['alto'])];
    }

    /**
     * Imagen → JPEG: orientación EXIF, fondo blanco (sin transparencia) y lado
     * mayor de IMAGEN_MAX_LADO. Devuelve [ancho, alto] finales.
     */
    private function aJpeg(string $origen, string $destino): array
    {
        $img = @imagecreatefromstring((string) file_get_contents($origen));
        if (!$img) {
            throw new \InvalidArgumentException('No se pudo leer la imagen.');
        }
        try {
            // Fotos de celular: la orientación viene en el EXIF, no en los píxeles.
            if (function_exists('exif_read_data') && (new \finfo(FILEINFO_MIME_TYPE))->file($origen) === 'image/jpeg') {
                $exif = @exif_read_data($origen);
                $rot = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
                if ($rot !== 0 && ($girada = imagerotate($img, $rot, 0))) {
                    imagedestroy($img);
                    $img = $girada;
                }
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $f = min(1, CrmMedioRules::IMAGEN_MAX_LADO / max($w, $h));
            $nw = max(1, (int) round($w * $f));
            $nh = max(1, (int) round($h * $f));
            $lienzo = imagecreatetruecolor($nw, $nh);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = imagejpeg($lienzo, $destino, self::CALIDAD_JPEG);
            imagedestroy($lienzo);
            if (!$ok) {
                throw new \RuntimeException('No se pudo guardar la imagen en el servidor.');
            }
            return [$nw, $nh];
        } finally {
            imagedestroy($img);
        }
    }

    // ── Consultar y editar ───────────────────────────────────────────────────

    public function listar(int $idEmpresa, string $tipo, int $page, int $perPage): array
    {
        return $this->repo->listar($idEmpresa, $tipo, $page, $perPage);
    }

    public function getPorId(int $id, int $idEmpresa): ?array
    {
        return $this->repo->getPorId($id, $idEmpresa);
    }

    public function actualizar(int $id, ?string $alt, bool $esIa, int $idEmpresa, int $idUsuario): void
    {
        $alt = $this->rules->validarTextoAlternativo($alt);
        $this->enTransaccion(function () use ($id, $alt, $esIa, $idEmpresa, $idUsuario) {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Archivo no encontrado.');
            }
            $this->repo->actualizarDatos($id, $idEmpresa, $alt, $esIa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ACTUALIZAR', 'crm_medios', $id,
                ['texto_alternativo' => $antes['texto_alternativo'], 'es_ia' => $antes['es_ia']], ['texto_alternativo' => $alt, 'es_ia' => $esIa]);
        });
    }

    /**
     * Eliminación lógica (la fila queda para la auditoría) y, ya confirmada, se
     * borra el archivo del disco. No si una publicación pendiente lo usa.
     */
    public function eliminar(int $id, int $idEmpresa, int $idUsuario): void
    {
        $antes = $this->enTransaccion(function () use ($id, $idEmpresa, $idUsuario) {
            $antes = $this->repo->getPorId($id, $idEmpresa);
            if (!$antes) {
                throw new \RuntimeException('Archivo no encontrado.');
            }
            $n = $this->repo->publicacionesPendientes($id, $idEmpresa);
            if ($n > 0) {
                throw new \InvalidArgumentException("Lo usa(n) {$n} publicación(es) que aún no salen. Quítelo de ellas primero.");
            }
            $this->repo->eliminar($id, $idEmpresa, $idUsuario);
            $this->log->registrar($idUsuario, $idEmpresa, 'ELIMINAR', 'crm_medios', $id, $antes, null);
            return $antes;
        });
        // Fuera de la transacción: si algo hubiera fallado, el archivo seguiría ahí.
        $abs = Database::getConnection()->inTransaction() ? null : $this->rutaAbsoluta($antes);
        if ($abs) {
            @unlink($abs);
        }
    }

    /** Ruta absoluta del archivo (solo dentro de storage/crm_media). */
    public function rutaAbsoluta(array $medio): ?string
    {
        $rel = (string) ($medio['ruta'] ?? '');
        if ($rel === '' || !str_starts_with($rel, 'crm_media/') || str_contains($rel, '..')) {
            return null;
        }
        $base = realpath(MVC_ROOT . self::DIR);
        $abs = realpath(MVC_ROOT . '/storage/' . $rel);
        return ($base && $abs && str_starts_with($abs, $base) && is_file($abs)) ? $abs : null;
    }

    // ── Enlace público para Instagram ────────────────────────────────────────

    /** Token {id}.{vence}.{firma}. */
    public static function tokenPublico(int $idMedio, ?int $vence = null): string
    {
        $vence ??= time() + self::MINUTOS_ENLACE * 60;
        return $idMedio . '.' . $vence . '.' . CryptoHelper::firma(self::CONTEXTO_FIRMA, $idMedio . '.' . $vence);
    }

    /** Archivo de un token válido y vigente; null en cualquier otro caso (sin decir el motivo). */
    public function medioPorToken(string $token): ?array
    {
        if (!preg_match('/^(\d{1,12})\.(\d{10})\.([A-Za-z0-9_-]{43})$/', $token, $m)) {
            return null;
        }
        if ((int) $m[2] < time() || !hash_equals(CryptoHelper::firma(self::CONTEXTO_FIRMA, $m[1] . '.' . $m[2]), $m[3])) {
            return null;
        }
        $medio = $this->repo->getPublico((int) $m[1]);
        $abs = $medio ? $this->rutaAbsoluta($medio) : null;
        return $abs ? ['ruta' => $abs, 'mime' => (string) $medio['mime']] : null;
    }

    // ── Purga ────────────────────────────────────────────────────────────────

    /** Borra archivos sin uso (decisión 4: 90 días). Devuelve cuántos purgó. */
    public function purgar(int $dias = CrmMedioRules::DIAS_PURGA): int
    {
        if (!$this->disponible()) {
            return 0;
        }
        $n = 0;
        foreach ($this->repo->porPurgar($dias) as $m) {
            $abs = $this->rutaAbsoluta($m);
            if ($abs) {
                @unlink($abs);
            }
            $this->repo->marcarPurgado((int) $m['id']);
            $n++;
        }
        return $n;
    }

    private function enTransaccion(callable $fn)
    {
        $db = Database::getConnection();
        $managed = !$db->inTransaction();
        if ($managed) $db->beginTransaction();
        try {
            $r = $fn();
            if ($managed) $db->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($managed && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
