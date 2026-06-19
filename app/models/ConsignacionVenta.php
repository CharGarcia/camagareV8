<?php
declare(strict_types=1);

namespace App\models;

use App\core\Database;
use PDO;

class ConsignacionVenta
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }
}
