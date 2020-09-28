<?php

declare(strict_types=1);

namespace Treo\SelectManagers;

/**
 * User SelectManager
 *
 * @author r.ratsun r.ratsun@zinitsolutions.com
 */
class User extends \Espo\SelectManagers\User
{
    /**
     * @inheritdoc
     */
    protected function filterActive(&$result)
    {
        $result['whereClause'][] = [
            'isActive' => true
        ];
    }
}
