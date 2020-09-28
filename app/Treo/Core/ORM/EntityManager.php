<?php

declare(strict_types=1);

namespace Treo\Core\ORM;

use Treo\ORM\DB\MysqlMapper;

/**
 * Class EntityManager
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class EntityManager extends \Espo\Core\ORM\EntityManager
{
    /**
     * @inheritdoc
     */
    protected function getMapperClassName($name)
    {
        $className = null;

        switch ($name) {
            case 'RDB':
                $className = $this->getMysqlMapperClassName();
                break;
        }

        return $className;
    }


    /**
     * @return string
     */
    protected function getMysqlMapperClassName(): string
    {
        return MysqlMapper::class;
    }
}
