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
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\Core\UserContext;
use Atro\Core\Utils\Util;
use Espo\Core\Acl;
use Espo\ORM\Entity;

class DataPipeline extends Base
{
    public function pushToTarget(Entity $sourceRecord): void
    {
        $pipelines = $this->getEntityManager()
            ->getRepository('DataPipeline')
            ->where(['sourceEntityId' => $sourceRecord->getEntityName()])
            ->find();

        $targetEntities = array_map(fn($pipeline) => $pipeline->get('targetEntityId'), iterator_to_array($pipelines));
        foreach ($pipelines as $pipeline) {
            if (empty($pipeline->get('transformationScript'))) {
                continue;
            }

            $field = 'target' . ucfirst(Util::toCamelCase($pipeline->get('targetEntityId')));
            $targetEntityType = $this->getMetadata()->get(['entityDefs', $sourceRecord->getEntityName(), 'links', $field, 'entity']);
            if (empty($targetEntityType)) {
                continue;
            }

            $targetId = $sourceRecord->get($field . 'Id');
            $targetRecord = !empty($targetId) ? $this->getEntityManager()->getEntity($targetEntityType, $targetId) : null;

            try {
                $targetRecordData = $this->buildTargetRecordPayload($pipeline->get('transformationScript'), $sourceRecord, $targetRecord);
            } catch (BadRequest) {
                continue;
            }

            if (empty($targetRecordData)) {
                continue;
            }

            $this->executeTransformationAs($pipeline, function (Acl $acl) use ($targetRecordData, $targetRecord, $targetEntityType, $field, $sourceRecord) {
                if (empty($targetRecord)) {
                    if ($acl->check($targetEntityType, 'create')) {
                        $targetEntity = $this->createTargetRecord($targetRecordData, $targetEntityType);
                        if ($targetEntity) {
                            $sourceRecord->set($field . 'Id', $targetEntity->get('id'));
                        }
                    }
                } elseif ($acl->check($targetRecord, 'edit')) {
                    $this->updateTargetRecord($targetRecordData, $targetRecord);
                }
            });
        }

        foreach ($targetEntities as $targetEntity) {
            if ($sourceRecord->isAttributeChanged('target' . ucfirst(Util::toCamelCase($targetEntity)) . 'Id')) {
                $this->getEntityManager()->saveEntity($sourceRecord, ['skipDataPipeline' => true]);
                break;
            }
        }
    }

    public function pushAllToTarget(Entity $targetRecord): void
    {
        $pipelines = $this->getEntityManager()
            ->getRepository('DataPipeline')
            ->where(['targetEntityId' => $targetRecord->getEntityName()])
            ->find();

        foreach ($pipelines as $pipeline) {
            $sourceEntityType = $pipeline->get('sourceEntityId');
            if (empty($sourceEntityType)) {
                continue;
            }

            $field = 'target' . ucfirst(Util::toCamelCase($pipeline->get('targetEntityId')));
            $sourceRecord = $this->getEntityManager()
                ->getRepository($sourceEntityType)
                ->where([$field . 'Id' => $targetRecord->get('id')])
                ->findOne();

            if (empty($sourceRecord)) {
                continue;
            }

            try {
                $targetRecordData = $this->buildTargetRecordPayload((string)$pipeline->get('transformationScript'), $sourceRecord, $targetRecord);
            } catch (BadRequest) {
                continue;
            }

            if (empty($targetRecordData)) {
                continue;
            }

            $this->executeTransformationAs($pipeline, function (Acl $acl) use ($targetRecordData, $targetRecord) {
                if ($acl->check($targetRecord, 'edit')) {
                    $this->updateTargetRecord($targetRecordData, $targetRecord);
                }
            });
        }
    }

    public function buildTargetRecordPreview(string $pipelineId, string $sourceRecordId, ?string $transformationScript = null): Entity
    {
        $pipeline = $this->getEntity($pipelineId);
        if (empty($pipeline)) {
            throw new NotFound();
        }

        $sourceEntityType = (string)$pipeline->get('sourceEntityId');
        $targetEntityType = (string)$pipeline->get('targetEntityId');

        if (!$this->getAcl()->check($targetEntityType, 'read')) {
            throw new Forbidden();
        }

        if ($transformationScript === null) {
            $transformationScript = (string)$pipeline->get('transformationScript');
        }

        if (empty($transformationScript)) {
            throw new BadRequest($this->getInjection('language')->translate('transformationScriptIsMissing', 'exceptions', 'DataPipeline'));
        }

        $sourceRecord = $this->getEntityManager()->getEntity($sourceEntityType, $sourceRecordId);
        if (empty($sourceRecord)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($sourceRecord, 'read')) {
            throw new Forbidden();
        }

        $targetId = $sourceRecord->get('target' . ucfirst($targetEntityType) . 'Id');
        $targetRecord = !empty($targetId) ? $this->getEntityManager()->getEntity($targetEntityType, $targetId) : null;

        $targetRecordData = $this->buildTargetRecordPayload($transformationScript, $sourceRecord, $targetRecord);

        $preview = $this->getEntityManager()->getRepository($targetEntityType)->get();

        if (!empty($targetRecord)) {
            $preview->set($targetRecord->toArray());
        } else {
            foreach ($preview->getAttributes() as $attribute => $defs) {
                if (!$preview->has($attribute)) {
                    $preview->set($attribute, ($defs['type'] ?? null) === Entity::JSON_ARRAY ? [] : null);
                }
            }
        }

        $preview->set($targetRecordData);

        return $preview;
    }

    public function buildTargetRecordPayload(string $transformationScript, Entity $sourceRecord, ?Entity $targetRecord): array
    {
        $res = $this->getTwig()->renderTemplate($transformationScript, [
            'sourceRecord' => $sourceRecord,
            'targetRecord' => $targetRecord,
        ]);

        $targetRecordData = json_decode((string)$res, true);
        if (!is_array($targetRecordData)) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('transformationScriptIsNotValid', 'exceptions', 'DataPipeline'), (string)$res));
        }

        /* @deprecated the "targetRecordData" wrapper will be removed in 3.0.0 */
        if (array_keys($targetRecordData) === ['targetRecordData'] && is_array($targetRecordData['targetRecordData'])) {
            $targetRecordData = $targetRecordData['targetRecordData'];
        }

        return $targetRecordData;
    }

    private function createTargetRecord(array $targetRecordData, string $targetEntityType): ?Entity
    {
        $targetId = $this
            ->getRecordService($targetEntityType)
            ->createEntity(json_decode(json_encode($targetRecordData)));

        if (empty($targetId)) {
            return null;
        }

        return $this->getEntityManager()->getEntity($targetEntityType, $targetId);
    }

    private function updateTargetRecord(array $targetRecordData, Entity $targetRecord): void
    {
        try {
            $this
                ->getRecordService($targetRecord->getEntityName())
                ->updateEntity($targetRecord->get('id'), json_decode(json_encode($targetRecordData)));
        } catch (NotModified) {
        }
    }

    private function executeTransformationAs(Entity $pipeline, callable $callback): void
    {
        $em = $this->getEntityManager();
        $userContext = $this->getContainer()->get(UserContext::class);
        $previousUser = $userContext->getUser();

        $executeAsUser = $pipeline->get('executeTransformationAs') === 'sameUser' && $previousUser !== null
            ? $previousUser->getSystemUser()
            : $em->getRepository('User')->getGlobalSystemUser();

        $em->setUser($executeAsUser);
        $userContext->set($executeAsUser);

        try {
            $callback($this->getContainer()->get('acl'));
        } finally {
            if ($previousUser !== null) {
                $em->setUser($previousUser);
                $userContext->set($previousUser);
            }
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
