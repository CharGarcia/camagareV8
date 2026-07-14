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
     * Procesa (o reprocesa, si quedó en error) un documento. Idempotente si
     * ya está 'listo'.
     */
    public function procesar(int $idDocumento): void
    {
        $doc = $this->obtenerDocumento($idDocumento);
        if ($doc === null) {
            throw new \RuntimeException("Documento {$idDocumento} no encontrado.");
        }
        if ($doc['estado'] === 'listo') {
            return;
        }

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
     * Trocea un texto en fragmentos de tamaño acotado con solape, para dar
     * mejor contexto a la búsqueda de texto completo sin cortar frases a la mitad.
     * @return string[]
     */
    private function trocear(string $texto): array
    {
        $chunks = [];
        $largo  = mb_strlen($texto);
        $inicio = 0;
        $paso   = self::CHUNK_SIZE - self::CHUNK_OVERLAP;

        while ($inicio < $largo) {
            $trozo = trim(mb_substr($texto, $inicio, self::CHUNK_SIZE));
            if ($trozo !== '') {
                $chunks[] = $trozo;
            }
            $inicio += $paso;
        }
        return $chunks;
    }

    private function obtenerDocumento(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM ia_documentos WHERE id = :id');
        $st->execute([':id' => $id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
