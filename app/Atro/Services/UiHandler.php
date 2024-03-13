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

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class UiHandler extends Base
{
    protected $mandatorySelectAttributeList = ["conditionsType", "conditions"];

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (property_exists($data, 'conditions') && !is_string($data->conditions)) {
            $data->conditions = @json_encode($data->conditions);
        }

        parent::handleInput($data, $id);
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        if ($entity->get('conditionsType') === 'basic') {
            $entity->set('conditions', @json_decode($entity->get('conditions')));
        }

        parent::prepareEntityForOutput($entity);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }
}
