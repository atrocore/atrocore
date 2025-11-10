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

namespace Atro\Core;

use Atro\Core\Utils\Condition\Condition;
use Espo\ORM\Entity;

class ConditionChecker
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function check(Entity $entity, array $conditions): ?bool
    {
        $entity->fields['__currentUserId'] = ['type' => 'varchar', 'notStorable' => true];
        $entity->set('__currentUserId', $this->container->get('user')->get('id'));

        $res = Condition::isCheck(Condition::prepare($entity, $conditions, $this->container->get('entityManager')));

        unset($entity->fields['__currentUserId']);
        $entity->clear('__currentUserId');

        return $res;
    }
}
