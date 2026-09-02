<?php
declare(strict_types=1);

namespace App\Rules\modulos;

use Exception;

/**
 * Validaciones de negocio de la configuración del restaurante (estaciones de
 * preparación y su impresora).
 */
class ConfiguracionRestauranteRules
{
    /** Tipos permitidos. Solo definen ícono y color; no restringen nada. */
    public const TIPOS = ['cocina', 'barra', 'otro'];

    /** Anchos de papel térmico usuales. */
    public const ANCHOS = [58, 80];

    public const COPIAS_MAX = 5;

    /**
     * Normaliza y valida los datos de una estación. Devuelve el arreglo listo
     * para el repositorio: así el Service no repite castings ni el controlador
     * decide formatos.
     */
    public function validarEstacion(array $data, bool $existeNombre): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la estación es obligatorio.');
        }
        if (mb_strlen($nombre) > 60) {
            throw new Exception('El nombre de la estación no puede superar los 60 caracteres.');
        }
        if ($existeNombre) {
            throw new Exception("Ya existe una estación con el nombre '{$nombre}'.");
        }

        $tipo = (string) ($data['tipo'] ?? 'cocina');
        if (!in_array($tipo, self::TIPOS, true)) {
            $tipo = 'cocina';
        }

        $ancho = (int) ($data['ancho_papel'] ?? 80);
        if (!in_array($ancho, self::ANCHOS, true)) {
            $ancho = 80;
        }

        $copias = (int) ($data['copias'] ?? 1);
        $copias = max(1, min(self::COPIAS_MAX, $copias ?: 1));

        return [
            'nombre'          => mb_strtoupper($nombre, 'UTF-8'),
            'tipo'            => $tipo,
            'orden'           => max(0, (int) ($data['orden'] ?? 0)),
            'activo'          => !empty($data['activo']),
            'imprime_ordenes' => !empty($data['imprime_ordenes']),
            'imprimir_auto'   => !empty($data['imprimir_auto']),
            'ancho_papel'     => $ancho,
            'copias'          => $copias,
        ];
    }

    /**
     * Una estación en uso no se borra: los ítems que enrutan a ella quedarían
     * apuntando a nada y dejarían de llegar a preparación sin aviso.
     */
    public function validarPuedeEliminar(?array $estacion, int $usos): void
    {
        if (!$estacion) {
            throw new Exception('La estación no existe.');
        }
        if ($usos > 0) {
            throw new Exception("No se puede eliminar: {$usos} ítem(s) o categoría(s) preparan en esta estación.");
        }
    }

    /**
     * La estación que recoge lo que no tiene estación propia debe existir y
     * estar activa; si no, los ítems del stock general seguirían sin llegar a
     * cocina y nadie entendería por qué.
     */
    public function validarPredeterminada(int $idEstacion, ?array $estacion): void
    {
        if ($idEstacion <= 0) {
            return; // quitar la predeterminada siempre es válido
        }
        if (!$estacion) {
            throw new Exception('La estación elegida no existe.');
        }
        if (empty($estacion['activo'])) {
            throw new Exception('No se puede usar una estación inactiva como predeterminada.');
        }
    }
}
