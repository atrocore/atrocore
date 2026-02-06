<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Seeders;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\Connection;

abstract class AbstractSeeder
{
    public function __construct(
        private readonly Config     $config,
        private readonly Connection $connection
    )
    {
    }

    abstract public function run(): void;

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getConnection(): Connection
    {
        return $this->connection;
    }
}