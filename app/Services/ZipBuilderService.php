<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Arma un archivo ZIP en disco a partir de contenidos en memoria (bytes de PDF,
 * texto de XML, etc.), sin necesidad de escribir cada archivo por separado antes
 * de comprimir. Pensado para descargas efímeras: quien lo usa es responsable de
 * borrar el ZIP resultante una vez enviado al navegador.
 */
class ZipBuilderService
{
    private \ZipArchive $zip;
    private bool $abierto = false;
    private string $rutaZip = '';

    public function abrir(string $rutaZip): void
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('La extensión ZipArchive de PHP no está disponible en este servidor.');
        }

        $dir = dirname($rutaZip);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal para el ZIP.');
        }

        $this->zip = new \ZipArchive();
        if ($this->zip->open($rutaZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el archivo ZIP.');
        }

        $this->rutaZip = $rutaZip;
        $this->abierto = true;
    }

    /**
     * Agrega un archivo al ZIP a partir de su contenido binario/texto en memoria.
     * Si ya existe un archivo con el mismo nombre interno, el llamador debe
     * garantizar nombres únicos (ver DescargaMasivaService::nombreArchivo()).
     */
    public function agregarDesdeString(string $nombreInterno, string $contenido): void
    {
        if (!$this->abierto) {
            throw new \RuntimeException('El ZIP no está abierto.');
        }
        $this->zip->addFromString($nombreInterno, $contenido);
    }

    public function cerrar(): void
    {
        if ($this->abierto) {
            $this->zip->close();
            $this->abierto = false;
        }
    }

    public function getRutaZip(): string
    {
        return $this->rutaZip;
    }
}
