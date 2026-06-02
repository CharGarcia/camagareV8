<?php
declare(strict_types=1);

namespace App\Services\modulos\Handlers;

use App\core\Database;

abstract class BaseHandler
{
    protected \PDO $db;

    public function __construct(
        protected string $modulo,
        protected string $accion
    ) {
        $this->db = Database::getConnection();
    }

    /**
     * Ejecuta la acción.
     *
     * @return array{registros: int, mensaje: string}
     */
    abstract public function ejecutar(int $idEmpresa, ?int $idEstablecimiento, int $idUsuario, array $parametros): array;
}
