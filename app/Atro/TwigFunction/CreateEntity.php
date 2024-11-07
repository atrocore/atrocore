<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class CreateEntity extends AbstractTwigFunction
{
    protected EntityManager $entityManager;

    protected array $dependencyList = ['entityManager'];

    public function run(string $entityName, array $data): ?Entity
    {
        $entityManager = $this->getInjection('entityManager');
        $nowString = date('Y-m-d H:i:s');
        $user = $entityManager->getUser();

        $entity = $entityManager->getRepository($entityName)->get();
        $entity->set($data);

        if ($entity->hasAttribute('createdAt')) {
            $entity->set('createdAt', $nowString);
        }
        if ($entity->hasAttribute('createdById') && $user) {
            $entity->set('createdById', $user->get('id'));
        }
        if ($entity->hasAttribute('modifiedAt')) {
            $entity->set('modifiedAt', $nowString);
        }
        if ($entity->hasAttribute('modifiedById') && $user) {
            $entity->set('modifiedById', $user->get('id'));
        }

        try {
            $entityManager->saveEntity($entity);
        } catch (\Throwable $e) {
            $entity = null;
            $GLOBALS['log']->error("CreateEntity Twig function failed: " . $e->getMessage());
        }

        return $entity;
    }
}
