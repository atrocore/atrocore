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

use Atro\Core\Container;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\Core\UserContext;
use Espo\ORM\Entity;

class DataPipeline extends Base
{
    public function pushToTarget(Entity $sourceRecord): void
    {
        $pipeline = $this->getEntityManager()
            ->getRepository('DataPipeline')
            ->where(['sourceEntityId' => $sourceRecord->getEntityName()])
            ->findOne();

        if (empty($pipeline) || empty($pipeline->get('mergingScript'))) {
            return;
        }

        $targetEntityType = $this->getMetadata()->get(['entityDefs', $sourceRecord->getEntityName(), 'links', 'targetRecord', 'entity']);
        if (empty($targetEntityType)) {
            return;
        }

        $targetId = $sourceRecord->get('targetRecordId');
        $targetRecord = !empty($targetId) ? $this->getEntityManager()->getEntity($targetEntityType, $targetId) : null;

        $em = $this->getEntityManager();
        $userContext = $this->getContainer()->get(UserContext::class);
        $previousUser = $userContext->getUser();
        $em->setUser($em->getRepository('User')->getGlobalSystemUser());
        $userContext->set($em->getRepository('User')->getGlobalSystemUser());

        try {
            if (empty($targetRecord)) {
                $this->createTargetRecord($pipeline->get('mergingScript'), $sourceRecord, $targetEntityType);
            } else {
                $this->updateTargetRecord($pipeline->get('mergingScript'), $sourceRecord, $targetRecord);
            }
        } finally {
            if ($previousUser !== null) {
                $em->setUser($previousUser);
                $userContext->set($previousUser);
            }
        }
    }

    public function pushAllToTarget(Entity $targetRecord): void
    {
        $sources = $this->getEntityManager()
            ->getRepository('DataPipeline')
            ->where(['targetEntityId' => $targetRecord->getEntityName()])
            ->find();

        foreach ($sources as $pipeline) {
            $sourceEntityType = $pipeline->get('sourceEntityId');
            if (empty($sourceEntityType)) {
                continue;
            }

            $sourceRecords = $this->getEntityManager()
                ->getRepository($sourceEntityType)
                ->where(['targetRecordId' => $targetRecord->get('id')])
                ->find();

            foreach ($sourceRecords as $sourceRecord) {
                $em = $this->getEntityManager();
                $userContext = $this->getContainer()->get(UserContext::class);
                $previousUser = $userContext->getUser();
                $em->setUser($em->getRepository('User')->getGlobalSystemUser());
                $userContext->set($em->getRepository('User')->getGlobalSystemUser());

                try {
                    $this->updateTargetRecord($pipeline->get('mergingScript'), $sourceRecord, $targetRecord);
                } catch (\Throwable $e) {
                } finally {
                    if ($previousUser !== null) {
                        $em->setUser($previousUser);
                        $userContext->set($previousUser);
                    }
                }
            }
        }
    }

    private function createTargetRecord(string $mergingScript, Entity $sourceRecord, string $targetEntityType): ?Entity
    {
        $res = $this->getTwig()->renderTemplate($mergingScript, [
            'sourceRecord'  => $sourceRecord,
            'targetRecord' => null,
        ]);

        $input = json_decode($res, true);
        if (!is_array($input) || empty($input['targetRecordData'])) {
            return null;
        }

        $targetId = $this
            ->getRecordService($targetEntityType)
            ->createEntity(json_decode(json_encode($input['targetRecordData'])));

        if (empty($targetId)) {
            return null;
        }

        $this
            ->getRecordService($sourceRecord->getEntityName())
            ->updateEntity($sourceRecord->get('id'), (object)['targetRecordId' => $targetId]);

        return $this->getEntityManager()->getEntity($targetEntityType, $targetId);
    }

    private function updateTargetRecord(string $mergingScript, Entity $sourceRecord, Entity $targetRecord): void
    {
        $res = $this->getTwig()->renderTemplate($mergingScript, [
            'sourceRecord' => $sourceRecord,
            'targetRecord' => $targetRecord,
        ]);

        $input = json_decode($res, true);
        if (!is_array($input) || empty($input['targetRecordData'])) {
            return;
        }

        try {
            $this
                ->getRecordService($targetRecord->getEntityName())
                ->updateEntity($targetRecord->get('id'), json_decode(json_encode($input['targetRecordData'])));
        } catch (NotModified) {
        }
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getTwig(): Twig
    {
        return $this->getContainer()->get('twig');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
