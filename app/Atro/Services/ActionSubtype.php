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

use Atro\Console\CreateAction;
use Atro\Console\CreateConditionType;
use Atro\Core\Templates\Services\Subtype;
use Espo\ORM\Entity;

/**
 * The service of the action subtypes: the record shows the code of its custom handler and of its custom condition,
 * the fields the action sets are listed once.
 */
class ActionSubtype extends Subtype
{
    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        parent::handleInput($data, $id);

        // the fields to set by the action are listed once
        if (!empty($data->data) && is_object($data->data) && !empty($data->data->fieldList)) {
            $data->data->fieldList = array_values(array_unique((array)$data->data->fieldList));
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('typePhpCode', null);
        if (!empty($entity->get('handler'))) {
            $fileName = CreateAction::DIR . '/' . $entity->get('handler') . '.php';
            if (file_exists($fileName)) {
                $entity->set('typePhpCode', file_get_contents($fileName));
            }
        }

        $fileName = CreateConditionType::DIR . '/' . $entity->get('conditionsType') . '.php';
        $entity->set('conditionPhpCode', null);
        if (file_exists($fileName)) {
            $entity->set('conditionPhpCode', file_get_contents($fileName));
        }
    }
}
