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

use Atro\Core\Templates\Services\Base;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Style extends Base
{
    protected ?bool $isCollectionRequest;

    public function updateEntity($id, $data)
    {
        $data->_skipCheckForConflicts = true;
        $data->_skipIsEntityUpdated = true;
        return parent::updateEntity($id, $data);
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);
        $this->isCollectionRequest = true;
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if(!empty($this->isCollectionRequest)) {
            return;
        }

        if (!empty($entity->get('customStylesheetPath')) && file_exists($entity->get('customStylesheetPath'))) {
            $entity->set('customStylesheet', file_get_contents($entity->get('customStylesheetPath')));
        }

        if (!empty($path = $entity->get('customHeadCodePath')) && file_exists($path)) {
             $entity->set('customHeadCode', file_get_contents($path));
        }
    }
}
