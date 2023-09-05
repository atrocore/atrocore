<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Espo\Core\Interfaces\Factory;

class EventManager implements Factory
{
    public function create(Container $container)
    {
        $eventManager = new \Atro\Core\EventManager\Manager($container);
        $eventManager->loadListeners();

        return $eventManager;
    }
}
