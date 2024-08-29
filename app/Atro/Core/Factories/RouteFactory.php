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
use Atro\Core\Factories\FactoryInterface as Factory;
use Espo\Core\Utils\Route;

class RouteFactory implements Factory
{
    /**
     * @param Container $container
     * @return Route
     */
    public function create(Container $container)
    {
        return new Route(
            $container->get('fileManager'),
            $container->get('moduleManager'),
            $container->get('dataManager')
        );
    }
}
