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

namespace Atro\Core\Services\Traits;

use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Exceptions\NotUnique;
use Atro\Core\Utils\Util;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\IEntity;

/**
 * Linking and unlinking the records of the relations.
 */
trait LinkableTrait
{
    public function linkEntity($id, $link, $foreignId)
    {
        if (in_array($link, $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit'))) {
            throw new Forbidden();
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data                = new \stdClass();
            $data->{"{$link}Id"} = $foreignId;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $this->handleLinkOneToManyRelation($id, $link, $foreignId, $linkData);
            return true;
        }

        $event = $this
            ->dispatchEvent('beforeLinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignId' => $foreignId]));

        $id        = $event->getArgument('id');
        $link      = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($foreignEntity, 'read')) {
            throw new Forbidden();
        }

        $this->validateArchivedRelation($entity, $foreignEntity);

        $this->getRepository()->relate($entity, $link, $foreignEntity, null, [
            "pseudoTransactionId"      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ]);

        return $this
            ->dispatchEvent('afterLinkEntity', new Event(['id' => $id, 'service' => $this, 'entity' => $entity, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
            ->getArgument('result');
    }

    public function duplicateAndLinkEntity($id, $link, $foreignId): bool
    {
        // Link entity with duplicate only support oneToMany Relation

        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $this->handleLinkOneToManyRelation($id, $link, $foreignId, $linkData, true);
            return true;
        }

        return false;
    }

    public function unlinkEntity($id, $link, $foreignId)
    {
        if (in_array($link, $this->getAcl()->getScopeForbiddenFieldList($this->entityType, 'edit'))) {
            throw new Forbidden();
        }

        /**
         * Delegate to Update if ManyToOne or OneToOne relation
         */
        if ($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'type']) === 'belongsTo') {
            $data                = new \stdClass();
            $data->{"{$link}Id"} = null;
            try {
                $this->updateEntity($id, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        /**
         * Delegate to Update if OneToMany relation
         */
        if (!empty($linkData = $this->getOneToManyRelationData($link))) {
            $data                               = new \stdClass();
            $data->{"{$linkData['foreign']}Id"} = null;
            try {
                $this->getServiceFactory()->create($linkData['entity'])->updateEntity($foreignId, $data);
            } catch (NotModified $e) {
                // ignore
            }

            return true;
        }

        $event = $this
            ->dispatchEvent('beforeUnlinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignId' => $foreignId]));

        $id        = $event->getArgument('id');
        $link      = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($foreignEntity, 'read')) {
            throw new Forbidden();
        }

        $this->validateArchivedRelation($entity, $foreignEntity);

        $result = $this->getRepository()->unrelate($entity, $link, $foreignEntity, [
            "pseudoTransactionId"      => $this->getPseudoTransactionId(),
            'pseudoTransactionManager' => $this->getPseudoTransactionManager()
        ]);

        return $this
            ->dispatchEvent('afterUnlinkEntity', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => $result]))
            ->getArgument('result');
    }

    protected function getOneToManyRelationData(string $link): ?array
    {
        $linkData = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link], []);
        if (
            array_key_exists('type', $linkData)
            && $linkData['type'] === 'hasMany'
            && array_key_exists('entity', $linkData)
            && array_key_exists('foreign', $linkData)
            && $this->getMetadata()->get(['entityDefs', $linkData['entity'], 'links', $linkData['foreign'], 'type']) === 'belongsTo'
        ) {
            return $linkData;
        }

        return null;
    }

    public function linkEntityMass($id, $link, $where, $selectData = null)
    {
        $massLinkCallback = function ($foreignIds) use ($id, $link) {
            $maxMassLinkCount = $this->getConfig()->get('maxMassLinkCount', 20);
            $key              = $this->getCreateLinkDataKey($id, $link);
            $errorData        = $this->getMemoryStorage()->get($key) ?? [];

            foreach ($foreignIds as $k => $foreignId) {
                if ($k < $maxMassLinkCount) {
                    try {
                        $this->linkEntity($id, $link, $foreignId);
                    } catch (NotUnique $e) {
                        // ignore
                    } catch (\Throwable $e) {
                        $errorData['entity']               = $this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $link, 'entity']);
                        $errorData['messages'][$foreignId] = $e->getMessage();
                        $errorData['unableToCreateIds'][]  = $foreignId;
                    }
                } else {
                    $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId, null);
                }
            }

            if (!empty($errorData)) {
                $this->getMemoryStorage()->set($key, $errorData);
            }
        };
        return $this->processDuplicateOrAndLinkEntityMass($id, $link, $where, $massLinkCallback, $selectData);
    }

    public function duplicateAndLinkEntityMass($id, $link, $where, $selectData = null)
    {
        $massLinkCallback = function ($foreignIds) use ($id, $link) {
            $maxMassLinkCount = $this->getConfig()->get('maxMassLinkCount', 20);
            foreach ($foreignIds as $k => $foreignId) {
                if ($k < $maxMassLinkCount) {
                    $this->duplicateAndLinkEntity($id, $link, $foreignId);
                } else {
                    $this->getPseudoTransactionManager()->pushLinkEntityJob($this->entityType, $id, $link, $foreignId, null, true);
                }
            }
        };
        return $this->processDuplicateOrAndLinkEntityMass($id, $link, $where, $massLinkCallback, $selectData);
    }

    public function unlinkAll(string $id, string $link): bool
    {
        $event = $this->dispatchEvent('beforeUnlinkAll', new Event(['id' => $id, 'service' => $this, 'link' => $link]));

        $id   = $event->getArgument('id');
        $link = $event->getArgument('link');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        if (empty($entity = $this->getRepository()->get($id))) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        if (empty($foreignEntityType = $entity->getRelationParam($link, 'entity'))) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, 'read')) {
            throw new Forbidden();
        }

        /**
         * A one-to-one relation has no link multiple id list. Both its sides keep the id of the single
         * related record in the `<link>Id` attribute: a real column on the `belongsTo` side and a foreign
         * attribute taken from the joined table on the `hasOne` side.
         */
        if (in_array($entity->getRelationParam($link, 'type'), [IEntity::BELONGS_TO, IEntity::HAS_ONE])) {
            $foreignIds = array_values(array_filter([$entity->get("{$link}Id")]));
        } else {
            $foreignIds = $entity->getLinkMultipleIdList($link) ?? [];
        }

        $maxMassUnlinkCount = $this->getConfig()->get('maxMassUnlinkCount', 20);

        foreach ($foreignIds as $k => $foreignId) {
            if ($k < $maxMassUnlinkCount) {
                $this->unlinkEntity($id, $link, $foreignId);
            } else {
                $this->getPseudoTransactionManager()->pushUnLinkEntityJob($this->entityType, $id, $link, $foreignId);
            }
        }

        return $this->dispatchEvent('afterUnlinkAll', new Event(['entity' => $entity, 'service' => $this, 'link' => $link, 'result' => true]))->getArgument('result');
    }

    protected function getCreateLinkDataKey(string $id, string $link): string
    {
        return "createLinkData{$this->entityType}$id$link";
    }

    public function handleLinkEntitiesErrors(string $id, string $link, bool $shouldDuplicateForeign): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return;
        }

        $data = $this->getMemoryStorage()->get($this->getCreateLinkDataKey($id, $link));

        if (empty($data['entity']) || empty($data['unableToCreateIds'])) {
            return;
        }

        $message = $this->getInjection('language')->translate($shouldDuplicateForeign ? 'someCouldNotDuplicatedAndLinked' : 'someCouldNotLinked', 'exceptions');

        try {
            $conn    = $this->getEntityManager()->getConnection();
            $results = $conn->createQueryBuilder()
                ->select('c.id, c.name')
                ->from($conn->quoteIdentifier(Util::toUnderScore($data['entity'])), 'c')
                ->where('c.id in (:ids)')
                ->andWhere('c.deleted = :false')
                ->setParameter('ids', $data['unableToCreateIds'], Mapper::getParameterType($data['unableToCreateIds']))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setMaxResults(20)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {

        }

        if (!empty($results)) {
            $message .= ':<ul>';
            foreach ($results as $result) {
                $url     = $this->getConfig()->get('siteUrl') . '#' . $data['entity'] . '/view/' . $result['id'];
                $message .= '<li><a href="' . $url . '" target="_blank">' . $result['name'] . ': ' . (!empty($data['messages'][$result['id']]) ? $data['messages'][$result['id']] : '') . '</a></li>';
            }
            $message .= '</ul>';
        }

        throw new BadRequest($message);
    }

    protected function handleLinkOneToManyRelation(string $id, string $link, string $foreignId, array $linkData, bool $duplicateForeign = false): void
    {
        if (empty($linkData)) {
            return;
        }

        $data                               = new \stdClass();
        $data->{"{$linkData['foreign']}Id"} = $id;
        $key                                = $this->getCreateLinkDataKey($id, $link);
        $createLinkData                     = $this->getMemoryStorage()->get($key) ?? [];
        try {
            $foreignService = $this->getServiceFactory()->create($linkData['entity']);
            if ($duplicateForeign) {
                $data->_duplicatingEntityId = $foreignId;
                foreach ($foreignService->getDuplicateAttributes($foreignId) as $field => $value) {
                    if (property_exists($data, $field)) {
                        continue 1;
                    }
                    $data->$field = $value;
                }
                $foreignService->createEntity($data);
            } else {
                $foreignService->updateEntity($foreignId, $data);
            }
        } catch (NotModified $e) {
            // ignore
        } catch (BadRequest $e) {
            $createLinkData['entity']               = $linkData['entity'];
            $createLinkData['messages'][$foreignId] = $e->getMessage();
            $createLinkData['unableToCreateIds'][]  = $foreignId;
            $this->getMemoryStorage()->set($key, $createLinkData);
        }
    }

    protected function processDuplicateOrAndLinkEntityMass($id, $link, $where, \Closure $linkOrdDuplicateCallback, $selectData = null)
    {
        $event = $this->dispatchEvent('beforeLinkEntityMass', new Event(['id' => $id, 'service' => $this, 'link' => $link, 'where' => $where]));

        $id    = $event->getArgument('id');
        $link  = $event->getArgument('link');
        $where = $event->getArgument('where');

        if (empty($id) || empty($link)) {
            throw new BadRequest("'id' and 'link' is required parameters.");
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');

        if (empty($foreignEntityType)) {
            throw new Error();
        }

        if (!$this->getAcl()->check($foreignEntityType, 'read')) {
            throw new Forbidden();
        }

        if (empty($where) || !is_array($where)) {
            $where = [];
        }

        $selectParams = $this->getRecordService($foreignEntityType)->getSelectParams(['where' => $where]);
        $this->getEntityManager()->getRepository($foreignEntityType)->handleSelectParams($selectParams);

        $collection = $this->getEntityManager()->getRepository($foreignEntityType)->find(array_merge($selectParams, ['select' => ['id']]));

        $foreignIds = array_column($collection->toArray(), 'id');

        $linkOrdDuplicateCallback($foreignIds);

        $result = count($foreignIds) <= $this->getConfig()->get('maxMassLinkCount', 20);

        return $this->dispatchEvent('afterLinkEntityMass', new Event(['entity' => $entity, 'service' => $this, 'link' => $link, 'result' => $result]))->getArgument('result');
    }
}
