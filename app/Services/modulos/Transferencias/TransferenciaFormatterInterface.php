<?php
declare(strict_types=1);

namespace App\Services\modulos\Transferencias;

/**
 * Contrato de un generador de archivo de transferencias para un banco específico.
 * Cada banco define su propio layout (columnas, orden, delimitador, cabecera);
 * implementar esta interfaz en una clase nueva dentro de Formatters/ para
 * agregar soporte a un banco sin tocar el resto del módulo.
 */
interface TransferenciaFormatterInterface
{
    /**
     * Genera el archivo y retorna la ruta absoluta donde quedó guardado.
     *
     * @param array $lote   Fila de transferencias_lotes (cabecera).
     * @param array $lineas Filas de transferencias_lotes_detalle.
     * @param string $rutaDestino Ruta absoluta (sin extensión) donde guardar el archivo.
     */
    public function generar(array $lote, array $lineas, string $rutaDestino): string;

    /** Extensión del archivo generado, sin punto (ej. 'xlsx', 'txt'). */
    public function getExtension(): string;
}
