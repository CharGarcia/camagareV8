<?php
declare(strict_types=1);

namespace App\Services\modulos;

use App\repositories\modulos\IaDocumentoRepository;
use Smalot\PdfParser\Parser;

/**
 * Worker de indexado de documentos PDF: extrae texto por página con
 * smalot/pdfparser y lo trocea en fragmentos (ia_documento_chunks) para
 * búsqueda de texto completo. Se invoca desde el script CLI
 * scripts/procesar_documento_ia.php, lanzado en segundo plano (fire-and-forget)
 * para no bloquear el request de subida.
 */
class IaDocumentoProcesadorService
{
    private const CHUNK_SIZE    = 1000; // caracteres aprox. por fragmento
    private const CHUNK_OVERLAP = 150;

    private \PDO $db;
    private IaDocumentoRepository $repo;

    public function __construct()
    {
        $this->db   = \App\core\Database::getConnection();
        $this->repo = new IaDocumentoRepository();
    }

    /**
     * Procesa (o reprocesa) un documento. Solo evita ejecutarse si ya hay un
     * procesamiento en curso para el mismo documento (concurrencia); reprocesar
     * uno ya 'listo' es válido (ej. al mejorar el trozado) y borra sus
     * fragmentos anteriores antes de regenerarlos.
     */
    public function procesar(int $idDocumento): void
    {
        $doc = $this->obtenerDocumento($idDocumento);
        if ($doc === null) {
            throw new \RuntimeException("Documento {$idDocumento} no encontrado.");
        }
        if ($doc['estado'] === 'procesando') {
            return;
        }

        $this->repo->eliminarChunks($idDocumento);
        $this->repo->updateEstado($idDocumento, 'procesando');

        $ruta = MVC_ROOT . '/storage/ia_soporte/' . $doc['id_empresa'] . '/' . $doc['archivo'];
        if (!is_file($ruta)) {
            $this->repo->updateEstado($idDocumento, 'error', 'El archivo no existe en el servidor.');
            return;
        }

        try {
            $autoload = MVC_ROOT . '/vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            $parser  = new Parser();
            $pdf     = $parser->parseFile($ruta);
            $paginas = $pdf->getPages();

            $chunkIndex = 0;
            foreach ($paginas as $indicePagina => $pagina) {
                $texto = trim($pagina->getText());
                if ($texto === '') {
                    continue;
                }
                foreach ($this->trocear($texto) as $trozo) {
                    $this->repo->insertarChunk((int) $doc['id_empresa'], $idDocumento, $chunkIndex, $indicePagina + 1, $trozo);
                    $chunkIndex++;
                }
            }

            if ($chunkIndex === 0) {
                $this->repo->updateEstado(
                    $idDocumento,
                    'error',
                    'No se pudo extraer texto del PDF (¿es una imagen escaneada sin texto?).',
                    count($paginas)
                );
                return;
            }

            $this->repo->updateEstado($idDocumento, 'listo', null, count($paginas));
        } catch (\Throwable $e) {
            $this->repo->updateEstado($idDocumento, 'error', 'Error al procesar el PDF: ' . $e->getMessage());
        }
    }

    /**
     * Trocea un texto en fragmentos de tamaño acotado con solape, agrupando
     * por PALABRAS COMPLETAS (nunca corta una palabra a la mitad al inicio o
     * al final de un fragmento) — a diferencia de cortar por cantidad fija de
     * caracteres, que podía partir palabras arbitrariamente.
     * @return string[]
     */
    private function trocear(string $texto): array
    {
        $palabras = preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($palabras)) {
            return [];
        }

        $chunks = [];
        $actual = [];
        $largoActual = 0;

        foreach ($palabras as $palabra) {
            $largoPalabra = mb_strlen($palabra) + 1; // +1 por el espacio separador
            if ($largoActual + $largoPalabra > self::CHUNK_SIZE && !empty($actual)) {
                $chunks[] = implode(' ', $actual);
                $actual = $this->ultimasPalabras($actual, self::CHUNK_OVERLAP);
                $largoActual = empty($actual) ? 0 : mb_strlen(implode(' ', $actual)) + 1;
            }
            $actual[] = $palabra;
            $largoActual += $largoPalabra;
        }
        if (!empty($actual)) {
            $chunks[] = implode(' ', $actual);
        }
        return $chunks;
    }

    /**
     * Devuelve las últimas palabras de un fragmento que quepan dentro de
     * $maxCaracteres, para usarlas como solape con el siguiente fragmento.
     * @param string[] $palabras
     * @return string[]
     */
    private function ultimasPalabras(array $palabras, int $maxCaracteres): array
    {
        $resultado = [];
        $largo = 0;
        for ($i = count($palabras) - 1; $i >= 0; $i--) {
            $largo += mb_strlen($palabras[$i]) + 1;
            if ($largo > $maxCaracteres) {
                break;
            }
            array_unshift($resultado, $palabras[$i]);
        }
        return $resultado;
    }

    private function obtenerDocumento(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM ia_documentos WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
