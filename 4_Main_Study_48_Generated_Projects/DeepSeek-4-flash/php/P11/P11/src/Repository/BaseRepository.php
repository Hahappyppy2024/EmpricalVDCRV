<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use PDO;

abstract class BaseRepository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Connection::db();
    }
}
