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

namespace Atro\Core\ORM;

use Atro\Core\Container;
use Atro\Core\ORM\Repositories\RDB;
use Espo\ORM\Repository;

/**
 * Creates the repository of an entity: the class found by the metadata, otherwise the base repository.
 */
class RepositoryFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    public function create(string $entityName): Repository
    {
        $className = $this->container->get('metadata')->getRepositoryPath($entityName);
        if (!class_exists($className)) {
            $className = RDB::class;
        }

        return new $className($entityName, $this->container);
    }
}
