<?php

namespace Espo\Core\Utils\Database\DBAL\Driver\PDOMySql;

class Driver extends \Doctrine\DBAL\Driver\PDOMySql\Driver
{
    public function getDatabasePlatform()
    {
        return new \Espo\Core\Utils\Database\DBAL\Platforms\MySqlPlatform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn)
    {
        return new \Espo\Core\Utils\Database\DBAL\Schema\MySqlSchemaManager($conn);
    }
}