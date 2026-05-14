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

namespace Atro\Listeners;

use Atro\Core\DataManager;
use Atro\Core\KeyValueStorages\StorageInterface;
use Atro\Core\Utils\Config;
use Doctrine\DBAL\Connection;

abstract class AbstractMetadataListener
{
    public function __construct(
        protected readonly Config $config,
        protected readonly Connection $dbal,
        protected readonly DataManager $dataManager,
        protected readonly StorageInterface $memoryStorage
    ) {
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getDbal(): Connection
    {
        return $this->dbal;
    }

    protected function getDataManager(): DataManager
    {
        return $this->dataManager;
    }

    protected function getMemoryStorage(): StorageInterface
    {
        return $this->memoryStorage;
    }

    /**
     * @deprecated Use getDbal() instead
     */
    protected function getConnection(): Connection
    {
        return $this->getDbal();
    }
}
