<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class EmailFilterManager
 *
 * @author r.ratsun@gmail.com
 */
class EmailFilterManager extends Base
{
    /**
     * @inheritDoc
     */
    public function load()
    {
        $emailFilterManager = new \Espo\Core\Utils\EmailFilterManager(
            $this->getContainer()->get('entityManager')
        );

        return $emailFilterManager;
    }
}
