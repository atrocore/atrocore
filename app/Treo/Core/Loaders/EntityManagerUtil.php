<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class EntityManagerUtil
 *
 * @author r.ratsun@treolabs.com
 */
class EntityManagerUtil extends Base
{
    /**
     * @inheritDoc
     */
    public function load()
    {
        $entityManager = new \Espo\Core\Utils\EntityManager(
            $this->getContainer()->get('metadata'),
            $this->getContainer()->get('language'),
            $this->getContainer()->get('fileManager'),
            $this->getContainer()->get('config'),
            $this->getContainer()
        );

        return $entityManager;
    }
}
