<?php

declare(strict_types=1);

namespace App\Rules\modulos;

/**
 * CRM: reglas de embudos, etapas y catálogos (orígenes, motivos de pérdida).
 * Cada embudo tiene exactamente una etapa «ganada» y una «perdida»; esas dos no
 * se crean ni se eliminan a mano: nacen con el embudo.
 */
class CrmEmbudoRules
{
    public const TIPOS_CANAL = ['instagram_dm', 'instagram_comentario', 'linkedin', 'whatsapp', 'referido', 'web', 'otro'];

    public function validarEmbudo(array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre del embudo es obligatorio.');
        }
        if (mb_strlen($nombre) > 100) {
            throw new \InvalidArgumentException('El nombre del embudo no puede superar 100 caracteres.');
        }
        if (!in_array($d['estado'] ?? 'activo', ['activo', 'inactivo'], true)) {
            throw new \InvalidArgumentException('Estado del embudo no válido.');
        }
    }

    /** El embudo por defecto no se puede desactivar ni eliminar. */
    public function validarCambioEstadoEmbudo(array $embudo, string $estadoNuevo): void
    {
        if (!empty($embudo['es_defecto']) && $estadoNuevo !== 'activo') {
            throw new \InvalidArgumentException('El embudo por defecto no se puede desactivar. Marque otro como predeterminado primero.');
        }
    }

    public function validarEliminarEmbudo(array $embudo, int $oportunidades): void
    {
        if (!empty($embudo['es_defecto'])) {
            throw new \InvalidArgumentException('El embudo por defecto no se puede eliminar. Marque otro como predeterminado primero.');
        }
        if ($oportunidades > 0) {
            throw new \InvalidArgumentException(
                "El embudo tiene {$oportunidades} oportunidad(es). Muévalas a otro embudo o desactívelo en lugar de eliminarlo."
            );
        }
    }

    public function validarEtapa(array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre de la etapa es obligatorio.');
        }
        if (mb_strlen($nombre) > 100) {
            throw new \InvalidArgumentException('El nombre de la etapa no puede superar 100 caracteres.');
        }
        $p = $d['probabilidad'] ?? 0;
        if (!is_numeric($p) || (int) $p < 0 || (int) $p > 100) {
            throw new \InvalidArgumentException('La probabilidad debe estar entre 0 y 100.');
        }
        if (!empty($d['color']) && !preg_match('/^#[0-9a-fA-F]{6}$/', (string) $d['color'])) {
            throw new \InvalidArgumentException('Color no válido (use el formato #RRGGBB).');
        }
    }

    /** Solo se crean etapas abiertas; ganada y perdida vienen con el embudo. */
    public function validarCrearEtapa(array $d): void
    {
        $this->validarEtapa($d);
        if (($d['tipo'] ?? 'abierta') !== 'abierta') {
            throw new \InvalidArgumentException('Solo se pueden agregar etapas intermedias; Ganado y Perdido ya existen en cada embudo.');
        }
    }

    public function validarEliminarEtapa(array $etapa, int $oportunidades, int $abiertasRestantes): void
    {
        if ($etapa['tipo'] !== 'abierta') {
            throw new \InvalidArgumentException('Las etapas Ganado y Perdido no se pueden eliminar.');
        }
        if ($oportunidades > 0) {
            throw new \InvalidArgumentException(
                "La etapa tiene {$oportunidades} oportunidad(es). Muévalas a otra etapa antes de eliminarla."
            );
        }
        if ($abiertasRestantes < 1) {
            throw new \InvalidArgumentException('El embudo debe conservar al menos una etapa intermedia.');
        }
    }

    public function validarCatalogo(string $catalogo, array $d): void
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $max = $catalogo === 'motivos' ? 150 : 100;
        if ($nombre === '') {
            throw new \InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > $max) {
            throw new \InvalidArgumentException("El nombre no puede superar {$max} caracteres.");
        }
        if (!in_array($d['estado'] ?? 'activo', ['activo', 'inactivo'], true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
        if ($catalogo === 'origenes' && !empty($d['tipo_canal']) && !in_array($d['tipo_canal'], self::TIPOS_CANAL, true)) {
            throw new \InvalidArgumentException('Tipo de canal no válido.');
        }
    }
}
