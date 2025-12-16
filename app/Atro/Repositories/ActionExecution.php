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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class ActionExecution extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('status') === 'running') {
            $entity->set('startedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        }

        if (in_array($entity->get('status'), ['done', 'failed'])) {
            $entity->set('finishedAt', (new \DateTime())->format('Y-m-d H:i:s'));
        }
    }
}
