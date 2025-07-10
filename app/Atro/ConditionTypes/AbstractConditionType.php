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

namespace Atro\ConditionTypes;

use Atro\Core\Container;
use Espo\ORM\EntityManager;

abstract class AbstractConditionType
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    abstract public static function getTypeLabel(): string;

    abstract public static function getEntityName(): string;

    abstract public function proceed(\stdClass $input): bool;

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }
}