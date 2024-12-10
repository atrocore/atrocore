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

class QueueItem extends Base
{
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $fileName = $this->getFilePath($entity->get('sortOrder'), $entity->get('priority'), $entity->get('id'));
        if (!empty($fileName) && file_exists($fileName)) {
            unlink($fileName);
        }

        if ($entity->get('serviceName') === 'MassActionCreator') {
            $actionItems = $this
                ->where([
                    'data*'  => '%"creatorId":"' . $entity->get('id') . '"%',
                    'status' => 'Pending'
                ])
                ->find();
            foreach ($actionItems as $qi) {
                $qi->set('status', 'Canceled');
                $this->getEntityManager()->saveEntity($qi);
            }
        }

        $this->deleteFromDb($entity->get('id'));
    }
}
