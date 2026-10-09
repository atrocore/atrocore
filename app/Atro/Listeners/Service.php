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
use Atro\Core\Utils\Language;
use Atro\Services\AbstractService;
use Espo\ORM\Entity;

class Service extends AbstractListener
{
    public function beforePutMeta(Event $event): void
    {
        /* @var Entity $entity */
        $entity = $event->getArgument('entity');

        /* @var AbstractService $service */
        $service = $event->getArgument('service');

        if (empty($entity->id) || !$service->isMetaGroupRequested('personalData')) {
            return;
        }

        if ($this->getMetadata()->get(['scopes', $entity->getEntityName(), 'type']) === 'ReferenceData') {
            return;
        }

        $repository = $this->getEntityManager()->getRepository($entity->getEntityName());
        if (!$repository->hasPersonalDataTable()) {
            return;
        }

        $entity->setMeta('personalData', 'fields', $repository->getPersonalDataFields($entity->id));
    }

    public function prepareEntityForOutput(Event $event): void
    {
        $entity = $event->getArgument('entity');

        foreach ($this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields'], []) as $field => $defs) {
            if (!empty($defs['entity']) && $this->getMetadata()->get(['scopes', $defs['entity'], 'type'], '') == 'ReferenceData') {
                $referenceEntity = $this->getEntityManager()->getEntity($defs['entity'], $entity->get($field . 'Id'));

                if (!empty($referenceEntity)) {
                    $localizedName = Language::getLocalizedFieldName($this->getEntityManager()->getContainer(), $referenceEntity->getEntityType(), 'name');
                    $entity->set($field . 'Name', !empty($referenceEntity->get($localizedName)) ? $referenceEntity->get($localizedName) : $referenceEntity->get('name'));
                }
            }
        }
    }
}
