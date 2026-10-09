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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\ExpressionLanguage\Compiled\CompiledConsolidationSkipCondition;
use Atro\Core\ExpressionLanguage\Compiled\ConsolidationSkipConditionContext;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\Entities\User;
use Atro\Repositories\Consolidation as ConsolidationRepository;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Consolidation extends Base
{
    public function updateMasterForContributor(Entity $contributor): bool
    {
        $master = $contributor->get('masterRecord');

        if (empty($master)) {
            throw new Forbidden();
        }

        return $this->updateMasterRecord($master, new EntityCollection([$contributor], $contributor->getEntityName()));
    }

    public function refreshMasterRecord(Entity $master): bool
    {
        $consolidation = $this->getRepository()->getByEntityName($master->getEntityName());
        if (empty($consolidation) || empty($consolidation->get('updateMasterAutomatically'))) {
            return false;
        }

        $contributorEntityName = (string)$this->getRepository()->getContributorEntityName($master->getEntityName());

        return $this->updateMasterRecord($master, new EntityCollection([], $contributorEntityName));
    }

    public function updateMasterRecord(Entity $master, EntityCollection $candidates): bool
    {
        if (!$this->getAcl()->check($master->getEntityName(), 'edit')) {
            throw new Forbidden();
        }

        $consolidation = $this->getConsolidation($master->getEntityName());

        $payload = $this->buildMasterRecordPayload($consolidation, $candidates, $master, (string)$consolidation->get('consolidationScript'));

        if ($payload === null) {
            return false;
        }

        $this->executeAsMergeUser($consolidation, function () use ($payload, $master) {
            try {
                $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), json_decode(json_encode($payload)));
            } catch (NotModified) {
                // ignore
            }
        });

        return true;
    }

    public function createMasterRecord(EntityCollection $candidates): ?Entity
    {
        $masterEntity = $this->getMetadata()->get(['scopes', $candidates->getEntityName(), 'primaryEntityId']);

        if (empty($masterEntity) || !$this->getAcl()->check($masterEntity, 'create')) {
            throw new Forbidden();
        }

        $consolidation = $this->getConsolidation($masterEntity);

        $payload = $this->buildMasterRecordPayload($consolidation, $candidates, null, (string)$consolidation->get('consolidationScript'));

        if ($payload === null) {
            return null;
        }

        $id = null;

        $this->executeAsMergeUser($consolidation, function () use ($payload, $masterEntity, &$id) {
            $id = $this->getRecordService($masterEntity)->createEntity(json_decode(json_encode($payload)));
        });

        if (empty($id)) {
            return null;
        }

        return $this->getEntityManager()->getEntity($masterEntity, $id);
    }

    public function buildMasterRecordPayload(Entity $consolidation, EntityCollection $candidates, ?Entity $master, string $consolidationScript): ?array
    {
        if (empty($consolidationScript)) {
            throw new BadRequest($this->translate('consolidationScriptIsMissing', 'exceptions', 'Consolidation'));
        }

        $contributorEntityName = $candidates->getEntityName();

        $filteredCandidates = $this->filterSkippedCandidates($consolidation, $candidates, $master);
        if (count($candidates) > 0 && count($filteredCandidates) === 0) {
            return null;
        }

        $candidates = $filteredCandidates;

        $templateData = [
            'candidates'   => $candidates,
            'contributors' => $master !== null
                ? $master->get("derived{$contributorEntityName}Records", ['noCache' => true])
                : new EntityCollection([], $contributorEntityName),
            'masterRecord' => $master,
        ];

        $res = $this->getTwig()->renderTemplate($consolidationScript, $templateData);
        $masterRecordData = json_decode((string)$res, true);

        if (!is_array($masterRecordData)) {
            throw new BadRequest(sprintf($this->translate('consolidationScriptIsNotValid', 'exceptions', 'Consolidation'), $res));
        }

        return $masterRecordData;
    }

    public function filterSkippedCandidates(Entity $consolidation, EntityCollection $candidates, ?Entity $master): EntityCollection
    {
        if (empty($consolidation->get('skipCondition'))) {
            return $candidates;
        }

        $className = ConsolidationRepository::getCompiledSkipConditionClassName($consolidation);
        if (!is_a($className, CompiledConsolidationSkipCondition::class, true)) {
            throw new Error("'$className' must be an instance of " . CompiledConsolidationSkipCondition::class);
        }

        $skipCondition = $this->getContainer()->get($className);

        $filteredCandidates = new EntityCollection([], $candidates->getEntityName());
        foreach ($candidates as $candidate) {
            try {
                $skipped = $skipCondition->eval(new ConsolidationSkipConditionContext($candidate, $master));
            } catch (\Throwable $e) {
                throw new BadRequest(sprintf($this->translate('skipConditionFailed', 'exceptions', 'Consolidation'), $e->getMessage()));
            }

            if (!$skipped) {
                $filteredCandidates->append($candidate);
            }
        }

        return $filteredCandidates;
    }

    public function findCandidates(Entity $cluster, ?Entity $master): EntityCollection
    {
        $contributorEntityName = (string)$this->getRepository()->getContributorEntityName((string)$cluster->get('masterEntity'));

        $candidates = new EntityCollection([], $contributorEntityName);

        $clusterItems = $this->getEntityManager()->getRepository('ClusterItem')
            ->where(['clusterId' => $cluster->get('id'), 'entityName' => $contributorEntityName])
            ->find();

        foreach ($clusterItems as $clusterItem) {
            $record = $this->getEntityManager()->getEntity($contributorEntityName, $clusterItem->get('entityId'));
            if (empty($record)) {
                continue;
            }

            if ($master !== null && $record->get('masterRecordId') === $master->get('id')) {
                continue;
            }

            $candidates->append($record);
        }

        return $candidates;
    }

    public function buildMasterRecordPayloadForCluster(Entity $cluster, ?string $consolidationScript = null): ?array
    {
        $masterEntityName = (string)$cluster->get('masterEntity');

        if (!$this->getAcl()->check($masterEntityName, 'read')) {
            throw new Forbidden();
        }

        $consolidation = $this->getConsolidation($masterEntityName);
        if ($consolidationScript === null) {
            $consolidationScript = (string)$consolidation->get('consolidationScript');
        }

        $master = $cluster->get('goldenRecord');

        $candidates = $this->findCandidates($cluster, $master);
        if (count($candidates) > 0) {
            return $this->buildMasterRecordPayload($consolidation, $candidates, $master, $consolidationScript);
        }

        $contributorEntityName = (string)$this->getRepository()->getContributorEntityName($masterEntityName);

        $contributor = null;
        foreach ($this->getEntityManager()->getRepository('ClusterItem')->where(['clusterId' => $cluster->get('id'), 'entityName' => $contributorEntityName])->find() as $clusterItem) {
            $contributor = $this->getEntityManager()->getEntity($contributorEntityName, $clusterItem->get('entityId')) ?? $contributor;
        }

        if (empty($contributor)) {
            throw new BadRequest($this->translate('noClusterItemsToPreview', 'exceptions', 'Cluster'));
        }

        return $this->buildMasterRecordPayload($consolidation, new EntityCollection([$contributor], $contributorEntityName), $master, $consolidationScript);
    }

    public function getConsolidation(string $masterEntityName): Entity
    {
        $consolidation = $this->getRepository()->getByEntityName($masterEntityName);
        if (empty($consolidation)) {
            throw new BadRequest("Consolidation for entity {$masterEntityName} not found.");
        }

        return $consolidation;
    }

    private function executeAsMergeUser(Entity $consolidation, $callback): void
    {
        if ($consolidation->get('executeConsolidationAs') === 'system') {
            $executeAsUser = $this->getEntityManager()->getRepository('User')->getGlobalSystemUser();
        } else {
            $executeAsUser = $this->getContainer()->get('user')->getSystemUser();
        }

        $currentUser = $this->getContainer()->get('user');

        $userChanged = $currentUser !== $executeAsUser;

        if ($userChanged) {
            $this->auth($executeAsUser);
        }

        $callback();

        if ($userChanged) {
            // auth as current user again
            $this->auth($currentUser);
        }
    }

    protected function auth(User $user): void
    {
        if ($user->isSystemUser()) {
            $user->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? null);
        }

        $this->getEntityManager()->setUser($user);
        $this->getContainer()->get(\Atro\Core\UserContext::class)->set($user);
    }

    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($label, $category, $scope);
    }

    protected function getTwig(): Twig
    {
        return $this->getInjection('twig');
    }

    protected function getContainer(): \Atro\Core\Container
    {
        return $this->getInjection('container');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
        $this->addDependency('container');
    }
}
