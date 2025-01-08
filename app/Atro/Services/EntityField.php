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

use Atro\Core\EventManager\Event;
use Atro\Core\EventManager\Manager;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Services\ReferenceData;
use Atro\Core\Twig\Twig;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class EntityField extends ReferenceData
{
    public function resetToDefault(string $scope, string $field): bool
    {
        if ($this->getMetadata()->get("scopes.$scope.isCustom") || $this->getMetadata()->get("entityDefs.$scope.fields.$field.isCustom")) {
            throw new Error("Can't reset to defaults custom entity field '$field'.");
        }

        $entity = $this->getEntity("{$scope}_{$field}");
        if (!empty($entity)) {
            $this->getEntityManager()->removeEntity($entity);
        }

        return true;
    }

    public function renderScriptPreview(\stdClass $data): array
    {
        if (!property_exists($data, 'scope') || !property_exists($data, 'script') || !property_exists($data, 'field')) {
            throw new BadRequest();
        }

        $event = new Event(['data' => $data, 'result' => null]);
        $event = $this->getEventManager()->dispatch('FieldManagerController', 'renderScriptPreview', $event);
        if (!empty($event->getArgument('result'))) {
            return $event->getArgument('result');
        }

        $outputType = property_exists($data, 'outputType') ? $data->outputType : 'text';
        $entity = $this->getEntityManager()->getRepository($data->scope)->order('id', 'ASC')->findOne();
        $preview = $this->twig()->renderTemplate($data->script, ['entity' => $entity], $outputType);
        if (is_string($preview)) {
            $outputType = 'text';
        }

        return [
            'preview'    => $preview,
            'entityType' => $entity->getEntityType(),
            'entity'     => $entity->toArray(),
            'outputType' => $outputType
        ];
    }

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        foreach ($collection as $entity) {
            $entity->_collectionPrepared = true;
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (empty($entity->_collectionPrepared)) {
            $this->prepareFileTypesField($entity);
            $this->prepareDefaultField($entity);
        }
    }

    protected function prepareFileTypesField(Entity $entity): void
    {
        if (empty($entity->get('fileTypes'))) {
            return;
        }

        $fileTypes = $this->getEntityManager()->getRepository('FileType')
            ->where(['id' => $entity->get('fileTypes')])
            ->find();

        $fileTypesNames = [];
        foreach ($fileTypes as $fileType) {
            $fileTypesNames[$fileType->get('id')] = $fileType->get('name');
        }
        $entity->set('fileTypesNames', $fileTypesNames);
    }

    protected function prepareDefaultField(Entity $entity): void
    {
        if (empty($entity->get('default'))) {
            return;
        }

        $foreignEntity = null;
        switch ($entity->get('type')) {
            case 'link':
            case 'linkMultiple':
                $foreignEntity = $this
                    ->getMetadata()
                    ->get(['entityDefs', $entity->get('entityId'), 'links', $entity->get('code'), 'entity']);
                break;
            case 'measure':
                $foreignEntity = 'Unit';
                break;
            case 'file':
                $foreignEntity = 'File';
                break;
            case 'extensibleEnum':
            case 'extensibleMultiEnum':
                $foreignEntity = 'ExtensibleEnumOption';
                break;
        }

        if (empty($foreignEntity)) {
            return;
        }

        $repository = $this->getEntityManager()->getRepository($foreignEntity);
        if (in_array($entity->get('type'), ['linkMultiple', 'extensibleMultiEnum'])) {
            $defaultNames = [];
            foreach ($repository->where(['id' => $entity->get('default')])->find() as $foreign) {
                $defaultNames[$foreign->get('id')] = $foreign->get('name');
            }
            $entity->set('defaultNames', $defaultNames);
        } else {
            if (!empty($foreign = $repository->get($entity->get('default')))) {
                $entity->set('defaultName', $foreign->get('name'));
            }
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('eventManager');
        $this->addDependency('twig');
        $this->addDependency('dataManager');
    }

    protected function getEventManager(): Manager
    {
        return $this->getInjection('eventManager');
    }

    protected function twig(): Twig
    {
        return $this->getInjection('twig');
    }
}
