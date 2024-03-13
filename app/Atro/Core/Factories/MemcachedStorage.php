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

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Espo\Core\Utils\Config;

class MemcachedStorage implements FactoryInterface
{
    public function create(Container $container)
    {
        /** @var Config $config */
        $config = $container->get('config');

        $memcachedConf = $config->get('memcached');

        if (isset($memcachedConf['host']) && isset($memcachedConf['port'])) {
            return new \Atro\Core\KeyValueStorages\MemcachedStorage($container, $memcachedConf['host'], $memcachedConf['port']);
        }

        return $container->get('memoryStorage');
    }
}
