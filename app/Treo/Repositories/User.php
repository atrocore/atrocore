<?php

declare(strict_types=1);

namespace Treo\Repositories;

use Espo\Repositories\User as Base;

/**
 * Class User
 *
 * @author r.ratsun@gmail.com
 */
class User extends Base
{
    /**
     * Get admin users
     *
     * @return array
     */
    public function getAdminUsers(): array
    {
        $sql
            = 'SELECT 
                 u.id AS id, p.data AS data
               FROM user AS u
               LEFT JOIN preferences AS p ON u.id = p.id
               WHERE u.deleted = 0 AND u.is_admin = 1 AND u.is_active = 1';

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
}
