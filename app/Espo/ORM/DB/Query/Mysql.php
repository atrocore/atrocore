<?php

namespace Espo\ORM\DB\Query;

use Espo\ORM\Entity;
use Espo\ORM\IEntity;
use Espo\ORM\EntityFactory;
use PDO;

class Mysql extends Base
{
    public function limit($sql, $offset, $limit)
    {
        if (!is_null($offset) && !is_null($limit)) {
            $offset = intval($offset);
            $limit = intval($limit);
            $sql .= " LIMIT {$offset}, {$limit}";
        }
        return $sql;
    }
}
