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

namespace Atro\Services;

use Espo\ORM\Entity;
use Atro\Core\Templates\Services\Base;

class Classification extends Base
{
    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateClassificationAttributes(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($classificationAttributes = $duplicatingEntity->get('classificationAttributes')->toArray())) {
            // get service
            $service = $this->getInjection('serviceFactory')->create('ClassificationAttribute');

            foreach ($classificationAttributes as $classificationAttribute) {
                // prepare data
                $data = $service->getDuplicateAttributes($classificationAttribute['id']);
                $data->classificationId = $entity->get('id');

                // create entity
                $service->createEntity($data);
            }
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}
