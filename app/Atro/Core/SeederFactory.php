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

namespace Atro\Core;

use Atro\Core\Exceptions\Error;
use Atro\Seeders\AbstractSeeder;

class SeederFactory
{
    public function __construct(
        private readonly Container $container
    )
    {
    }

    /**
     * @param class-string<AbstractSeeder> $className
     */
    public function create(string $className): AbstractSeeder
    {
        if (!is_a($className, AbstractSeeder::class, true)) {
            throw new Error($className . ' is not a valid seeder class.');
        }

        $config = $this->getContainer()->get('config');
        $connection = $this->getContainer()->get('entityManager')->getConnection();

        return new $className($config, $connection);
    }

    private function getContainer(): Container
    {
        return $this->container;
    }
}