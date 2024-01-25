<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core;

use Atro\ConnectionType\ConnectionInterface;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\Core\Utils\Metadata;

class ConnectionFactory
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function create(Entity $connectionEntity): ConnectionInterface
    {
        /** @var Metadata $metadata */
        $metadata = $this->container->get('metadata');

        $connectionType = $this->container->get($metadata->get(['app', 'connectionTypes', $connectionEntity->get('type')]));
        $connectionType->setConnectionEntity($connectionEntity);

        return $connectionType;
    }

    public function createById(string $connectionEntityId): ConnectionInterface
    {
        if (empty($connectionEntityId)) {
            throw new Error('$connectionEntityId is empty.');
        }

        $connectionEntity = $this->container->get('entityManager')->getEntity('Connection', $connectionEntityId);
        if (empty($connectionEntity)) {
            throw new Error("Connection with ID '$connectionEntityId' does not exist.");
        }

        return $this->create($connectionEntity);
    }
}
