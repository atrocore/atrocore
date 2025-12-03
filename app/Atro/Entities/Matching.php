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

namespace Atro\Entities;

use Atro\Core\Templates\Entities\Base;

class Matching extends Base
{
    protected $entityType = "Matching";

    public function toPayload(): array
    {
        $res = $this->toArray();

        $toRemoveKeys = [
            'name',
            'description',
            'isActive',
            'deleted',
            'createdAt',
            'modifiedAt',
            'createdById',
            'createdByName',
            'modifiedById',
            'modifiedByName',
            'ownerUserId',
            'ownerUserName',
            'assignedUserId',
            'assignedUserName',
            'matchingRuleSetName',
            'matchingName'
        ];

        foreach ($toRemoveKeys as $key) {
            if (array_key_exists($key, $res)) {
                unset($res[$key]);
            }
        }
        $res['rules'] = [];

        foreach ($this->get('matchingRules')->toArray() as $item) {
            foreach ($toRemoveKeys as $key) {
                if (array_key_exists($key, $item)) {
                    unset($item[$key]);
                }
            }
            $res['rules'][] = $item;
        }

        return $res;
    }

    public function activate(): void
    {
        $this->getEntityManager()->getRepository($this->entityType)->activate($this->id, $this->get('code'));
    }

    public function deactivate(): void
    {
        $this->getEntityManager()->getRepository($this->entityType)->deactivate($this->id, $this->get('code'));
    }
}
