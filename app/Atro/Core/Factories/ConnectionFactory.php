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
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonArrayType;
use Atro\Core\Utils\Database\DBAL\FieldTypes\JsonObjectType;
use Doctrine\DBAL\Types\Type;

class ConnectionFactory implements FactoryInterface
{
    public function create(Container $container)
    {
        return new \Atro\Core\ConnectionFactory($container);
    }
}
