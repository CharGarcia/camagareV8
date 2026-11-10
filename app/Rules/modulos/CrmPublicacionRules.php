<?php

declare(strict_types=1);

namespace App\Rules\modulos;

use App\Services\LinkedInApiService;

/**
 * CRM fase 3: reglas de las publicaciones (docs/diseno/crm-redes-fase3.md).
 *
 * Estados: borrador → [en_revision → aprobada] → programada → publicando →
 * publicada | parcial | con_error. Solo se edita en borrador o en revisión.
 */
class CrmPublicacionRules
{
    public const EDITABLES = ['borrador', 'en_revision', 'aprobada'];
    public const MAX_TEXTO_INSTAGRAM = 2200;
    public const MAX_HASHTAGS_INSTAGRAM = 30;
    public const MAX_DIAS_PROGRAMAR = 180;

    /** Formatos por tipo de canal. */
    public const FORMATOS = [
        'instagram'       => ['imagen', 'carrusel', 'reel'],
        'linkedin_perfil' => ['texto', 'imagen', 'enlace'],
        'linkedin_pagina' => ['texto', 'imagen', 'enlace'],
    ];

    public const MAX_LINK_URL = 2000;
    public const MAX_LINK_TITULO = 200;

    /**
     * Formato «Enlace» (LinkedIn): dirección http(s) completa y título de la tarjeta.
     * Devuelve [url, título] limpios. Con $exigir = false (guardar un borrador) admite
     * que falten, pero lo que venga debe ser válido.
     * @return array{0:?string,1:?string}
     */
    public function validarEnlace(?string $url, ?string $titulo, bool $exigir = true): array
    {
        $url = trim((string) $url);
        $titulo = trim(preg_replace('/\s+/u', ' ', (string) $titulo) ?? '');
        if ($url === '' && $exigir) {
            throw new \InvalidArgumentException('El formato Enlace necesita la dirección web (https://…).');
        }
        if ($url !== '') {
            $partes = parse_url($url);
            if (mb_strlen($url) > self::MAX_LINK_URL || preg_match('/\s/u', $url) || !$partes
                || !in_array(strtolower((string) ($partes['scheme'] ?? '')), ['http', 'https'], true)
                || !str_contains((string) ($partes['host'] ?? ''), '.')) {
                throw new \InvalidArgumentException('La dirección del enlace no es válida. Escríbala completa, por ejemplo https://www.miempresa.com/oferta.');
            }
        }
        if ($titulo === '' && $exigir) {
            throw new \InvalidArgumentException('El formato Enlace necesita un título para la tarjeta.');
        }
        if (mb_strlen($titulo) > self::MAX_LINK_TITULO) {
            throw new \InvalidArgumentException('El título del enlace admite hasta ' . self::MAX_LINK_TITULO . ' caracteres.');
        }
        return [$url !== '' ? $url : null, $titulo !== '' ? $titulo : null];
    }

    public function validarCabecera(array $d): void
    {
        $t = trim((string) ($d['titulo'] ?? ''));
        if ($t === '') {
            throw new \InvalidArgumentException('Escriba un nombre para la publicación (uso interno).');
        }
        if (mb_strlen($t) > 150) {
            throw new \InvalidArgumentException('El nombre admite hasta 150 caracteres.');
        }
        if (mb_strlen((string) ($d['texto'] ?? '')) > LinkedInApiService::MAX_TEXTO) {
            throw new \InvalidArgumentException('El texto admite hasta ' . LinkedInApiService::MAX_TEXTO . ' caracteres.');
        }
    }

    public function validarEditable(array $pub): void
    {
        if (!in_array($pub['estado'], self::EDITABLES, true)) {
            throw new \InvalidArgumentException('La publicación ya está programada o publicada; no se puede modificar. Desprográmela primero.');
        }
    }

    /**
     * Destino listo para publicar: formato válido para la red, medios correctos
     * y texto dentro de los límites de esa red.
     * $medios: filas de crm_medios en orden; $texto: el que saldrá en esa red.
     */
    public function validarDestino(array $canal, string $formato, array $medios, string $texto, ?string $linkUrl = null, ?string $linkTitulo = null): void
    {
        $tipoCanal = (string) $canal['tipo'];
        $red = $tipoCanal === 'instagram' ? 'Instagram' : 'LinkedIn';
        if (!in_array($formato, self::FORMATOS[$tipoCanal] ?? [], true)) {
            throw new \InvalidArgumentException("Formato no válido para {$red}.");
        }
        $imgs = count(array_filter($medios, fn($m) => $m['tipo'] === 'imagen'));
        $vids = count($medios) - $imgs;
        if ($tipoCanal === 'instagram') {
            if (mb_strlen($texto) > self::MAX_TEXTO_INSTAGRAM) {
                throw new \InvalidArgumentException('En Instagram el texto admite hasta ' . self::MAX_TEXTO_INSTAGRAM . ' caracteres.');
            }
            if (preg_match_all('/#[\p{L}\p{N}_]+/u', $texto) > self::MAX_HASHTAGS_INSTAGRAM) {
                throw new \InvalidArgumentException('Instagram admite hasta ' . self::MAX_HASHTAGS_INSTAGRAM . ' hashtags.');
            }
            if ($formato === 'imagen' && !($imgs === 1 && $vids === 0)) {
                throw new \InvalidArgumentException('Una publicación de imagen en Instagram lleva exactamente una imagen.');
            }
            if ($formato === 'carrusel' && (count($medios) < 2 || count($medios) > 20)) {
                throw new \InvalidArgumentException('Un carrusel de Instagram lleva de 2 a 20 imágenes o videos.');
            }
            if ($formato === 'reel' && !($vids === 1 && $imgs === 0)) {
                throw new \InvalidArgumentException('Un reel lleva exactamente un video.');
            }
            return;
        }
        if (mb_strlen($texto) > LinkedInApiService::MAX_TEXTO) {
            throw new \InvalidArgumentException('En LinkedIn el texto admite hasta ' . LinkedInApiService::MAX_TEXTO . ' caracteres.');
        }
        if ($vids > 0) {
            throw new \InvalidArgumentException('Por ahora LinkedIn se publica con texto e imágenes (sin video).');
        }
        if ($formato === 'texto' && ($medios || trim($texto) === '')) {
            throw new \InvalidArgumentException('Una publicación de solo texto en LinkedIn no lleva imágenes y necesita texto.');
        }
        if ($formato === 'imagen' && ($imgs < 1 || $imgs > 20)) {
            throw new \InvalidArgumentException('LinkedIn admite de 1 a 20 imágenes por publicación.');
        }
        if ($formato === 'enlace') {
            if ($imgs > 1) {
                throw new \InvalidArgumentException('Una publicación de enlace en LinkedIn lleva una sola imagen (la de la tarjeta) o ninguna.');
            }
            $this->validarEnlace($linkUrl, $linkTitulo);
        }
    }

    /** La fecha programada: futura (con 1 minuto de tolerancia) y dentro de 180 días. */
    public function validarFecha(?string $fecha, ?int $ahora = null): ?string
    {
        $ahora ??= time();
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;   // publicar ahora
        }
        $ts = strtotime(str_replace('T', ' ', $fecha));
        if ($ts === false) {
            throw new \InvalidArgumentException('Fecha de publicación no válida.');
        }
        if ($ts < $ahora - 60) {
            throw new \InvalidArgumentException('La fecha de publicación ya pasó.');
        }
        if ($ts > $ahora + self::MAX_DIAS_PROGRAMAR * 86400) {
            throw new \InvalidArgumentException('Solo se programa hasta ' . self::MAX_DIAS_PROGRAMAR . ' días adelante.');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Estado de la publicación según sus destinos.
     * @param list<string> $estadosDestinos
     */
    public static function estadoSegunDestinos(array $estadosDestinos): string
    {
        $vivos = array_values(array_filter($estadosDestinos, fn($e) => $e !== 'cancelada'));
        if (!$vivos) {
            return 'cancelada';
        }
        if (array_intersect($vivos, ['pendiente', 'preparando', 'publicando'])) {
            return 'publicando';
        }
        $pub = count(array_filter($vivos, fn($e) => $e === 'publicada'));
        if ($pub === count($vivos)) {
            return 'publicada';
        }
        return $pub > 0 ? 'parcial' : 'con_error';
    }
}
