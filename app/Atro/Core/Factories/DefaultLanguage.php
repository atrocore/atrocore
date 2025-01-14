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
use Atro\Core\Utils\Language;

class DefaultLanguage implements Factory
{
    public function create(Container $container)
    {
        return new Language($container, Language::detectLocale($container->get('config')));
    }
}
