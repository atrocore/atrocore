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

use Atro\Core\Templates\Services\Hierarchy;
use Espo\ORM\Entity;

class Folder extends Hierarchy
{
    public function findLinkedEntities($id, $link, $params)
    {
        if ($link === 'parents' || $link === 'children' || $link === 'files') {
            $params['where'][] = [
                'type'  => 'bool',
                'value' => ['hiddenAndUnHidden']
            ];
        }

        return parent::findLinkedEntities($id, $link, $params);
    }

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (property_exists($data, 'parentId')) {
            $data->parentsIds = [$data->parentId];
            if (property_exists($data, 'parentName')) {
                $data->parentsNames = json_decode(json_encode([$data->parentId => $data->parentName]));
            }
        }

        parent::handleInput($data, $id);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        $parentId = $entity->get('parentsIds')[0] ?? null;
        if (!empty($parentId)) {
            $entity->set('parentId', $parentId);
            $entity->set('parentName', $entity->get('parentsNames')->{$parentId} ?? null);
        }

        parent::prepareEntityForOutput($entity);
    }
}
