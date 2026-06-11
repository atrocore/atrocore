<?php
/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class SourceToStagingPipeline extends Base
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $stagingEntityId = $entity->get('stagingEntityId');
        if (!empty($stagingEntityId)) {
            $entity->set('stagingEntityName', $this->getInjection('language')->translate($stagingEntityId, 'scopeNames', 'Global'));
        }
    }

    public function syncFromSource(Entity $sourceRecord): void
    {
        $mdes = $this->getEntityManager()
            ->getRepository('SourceToStagingPipeline')
            ->where(['sourceEntity' => $sourceRecord->getEntityName()])
            ->findOne();

        if (empty($mdes) || empty($mdes->get('mergingScript'))) {
            return;
        }

        $stagingEntityType = $this->getMetadata()->get(['entityDefs', $sourceRecord->getEntityName(), 'links', 'stagingRecord', 'entity']);
        if (empty($stagingEntityType)) {
            return;
        }

        $stagingId = $sourceRecord->get('stagingRecordId');
        $stagingRecord = !empty($stagingId)
            ? $this->getEntityManager()->getEntity($stagingEntityType, $stagingId)
            : null;

        if (empty($stagingRecord)) {
            $stagingRecord = $this->createStagingRecord($mdes, $sourceRecord, $stagingEntityType);
            if (empty($stagingRecord)) {
                return;
            }
        } else {
            $this->applyScript($mdes, $sourceRecord, $stagingRecord);
        }
    }

    private function createStagingRecord(Entity $mdes, Entity $sourceRecord, string $stagingEntityType): ?Entity
    {
        $res = $this->getTwig()->renderTemplate($mdes->get('mergingScript'), [
            'sourceRecord'  => $sourceRecord,
            'stagingRecord' => null,
        ]);

        $input = json_decode($res, true);
        if (!is_array($input) || empty($input['stagingRecordData'])) {
            return null;
        }

        $stagingId = $this->getRecordService($stagingEntityType)
            ->createEntity(json_decode(json_encode($input['stagingRecordData'])));

        if (empty($stagingId)) {
            return null;
        }

        $this->getRecordService($sourceRecord->getEntityName())
            ->updateEntity($sourceRecord->get('id'), (object)['stagingRecordId' => $stagingId]);

        return $this->getEntityManager()->getEntity($stagingEntityType, $stagingId);
    }

    public function syncAllSourcesOfStaging(Entity $stagingRecord): void
    {
        $sources = $this->getEntityManager()
            ->getRepository('SourceToStagingPipeline')
            ->where(['stagingEntityId' => $stagingRecord->getEntityName()])
            ->find();

        foreach ($sources as $mdes) {
            $sourceEntityType = $mdes->get('sourceEntity');
            if (empty($sourceEntityType)) {
                continue;
            }

            $sourceRecords = $this->getEntityManager()
                ->getRepository($sourceEntityType)
                ->where(['stagingRecordId' => $stagingRecord->get('id')])
                ->find();

            foreach ($sourceRecords as $sourceRecord) {
                try {
                    $this->applyScript($mdes, $sourceRecord, $stagingRecord);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    private function applyScript(Entity $mdes, Entity $sourceRecord, Entity $stagingRecord): void
    {
        $res = $this->getTwig()->renderTemplate($mdes->get('mergingScript'), [
            'sourceRecord'  => $sourceRecord,
            'stagingRecord' => $stagingRecord,
        ]);

        $input = json_decode($res, true);
        if (!is_array($input) || empty($input['stagingRecordData'])) {
            return;
        }

        try {
            $this->getRecordService($stagingRecord->getEntityName())
                ->updateEntity($stagingRecord->get('id'), json_decode(json_encode($input['stagingRecordData'])));
        } catch (NotModified) {
        }
    }

    protected function getTwig(): Twig
    {
        return $this->getInjection('twig');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('twig');
    }
}
