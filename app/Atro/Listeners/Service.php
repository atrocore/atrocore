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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;

class Service extends AbstractListener
{
    public function prepareEntityForOutput(Event $event): void
    {
        $entity = $event->getArgument('entity');

        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $field => $defs) {
            if (!empty($defs['entity']) && $this->getMetadata()->get(['scopes', $defs['entity'], 'type'], '') == 'ReferenceData') {
                $referenceEntity = $this->getEntityManager()->getEntity($defs['entity'], $entity->get($field . 'Id'));

                if (!empty($referenceEntity)) {
                    $entity->set($field . 'Name', $referenceEntity->get('name'));
                }
            }
        }
    }
}
