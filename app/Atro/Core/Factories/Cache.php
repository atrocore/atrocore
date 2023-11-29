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

namespace Atro\Core\Factories;

use Atro\Core\Cache\CacheInterface;
use Atro\Core\Container;
use Espo\Core\Utils\Config;

class Cache implements FactoryInterface
{
    public function create(Container $container)
    {
        /** @var Config $config */
        $config = $container->get('config');

        $className = "\\Atro\\Core\\Cache\\" . $config->get('cacheSystem', 'Memory');

        $cache = new $className($container);

        if (!$cache instanceof CacheInterface) {
            throw new \Error("$className must be instance of CacheInterface");
        }

        return $cache;
    }
}