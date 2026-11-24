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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Subtype;
use Espo\ORM\Entity;

/**
 * An action of a certain type: the action can not be configured if it is disabled for the target entity, e.g. via
 * "createDisabled", and the records of some types are searched for in the target entity.
 */
abstract class AbstractActionType extends Subtype
{
    /**
     * The flags of the target entity scope that disable the action.
     */
    protected const array DISABLED_TARGET_ACTION_FLAGS = [];

    /**
     * Whether the records are searched for in the target entity.
     */
    protected const bool SEARCH_IN_TARGET_ENTITY = false;

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (static::SEARCH_IN_TARGET_ENTITY && $entity->isAttributeChanged('targetEntity')) {
            $entity->set('searchEntity', $entity->get('targetEntity'));
        }

        $this->validateDisabledTargetActions($entity);
    }

    protected function validateDisabledTargetActions(Entity $entity): void
    {
        $targetEntity = $entity->get('targetEntity');

        if (empty(static::DISABLED_TARGET_ACTION_FLAGS) || empty($targetEntity)) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('targetEntity')) {
            return;
        }

        foreach (static::DISABLED_TARGET_ACTION_FLAGS as $flag) {
            if (empty($this->getMetadata()->get(['scopes', $targetEntity, $flag]))) {
                continue;
            }

            throw new BadRequest(
                sprintf(
                    $this->translateException('actionTypeDisabledForTargetEntity'),
                    $this->getLanguage()->translateOption($this->getTypeOption(), 'type', $this->getSupertypeName()),
                    $this->getLanguage()->translate($targetEntity, 'scopeNames')
                )
            );
        }
    }
}
