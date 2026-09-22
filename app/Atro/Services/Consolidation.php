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
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Atro\DTOs\MasterRecordPayloadDTO;
use Atro\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Consolidation extends Base
{
    public function updateMasterRecord(Entity $contributor, ?Entity $master = null): bool
    {
        if ($master === null) {
            $master = $contributor->get('masterRecord');
        }

        if (empty($master) || !$this->getAcl()->check($master->getEntityName(), 'edit')) {
            throw new Forbidden();
        }

        $consolidation = $this->getConsolidation($master->getEntityName());

        $payload = $this->buildMasterRecordPayload($contributor, $master, (string)$consolidation->get('consolidationScript'));

        if ($payload->isSkipped()) {
            return false;
        }

        $this->executeAsMergeUser($consolidation, function () use ($payload, $master) {
            try {
                $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), json_decode(json_encode($payload->getMasterRecordData())));
            } catch (NotModified) {
                // ignore
            }
        });

        return true;
    }

    public function createMasterRecord(Entity $contributor): ?Entity
    {
        $masterEntity = $this->getMetadata()->get(['scopes', $contributor->getEntityName(), 'primaryEntityId']);

        if (empty($masterEntity) || !$this->getAcl()->check($masterEntity, 'create')) {
            throw new Forbidden();
        }

        $consolidation = $this->getConsolidation($masterEntity);

        $payload = $this->buildMasterRecordPayload($contributor, null, (string)$consolidation->get('consolidationScript'));

        if ($payload->isSkipped()) {
            return null;
        }

        $id = null;

        $this->executeAsMergeUser($consolidation, function () use ($payload, $masterEntity, &$id) {
            $id = $this->getRecordService($masterEntity)->createEntity(json_decode(json_encode($payload->getMasterRecordData())));
        });

        if (empty($id)) {
            return null;
        }

        return $this->getEntityManager()->getEntity($masterEntity, $id);
    }

    public function buildMasterRecordPayload(Entity $contributor, ?Entity $master, string $consolidationScript, ?EntityCollection $contributorRecords = null): MasterRecordPayloadDTO
    {
        if (empty($consolidationScript)) {
            throw new BadRequest($this->translate('consolidationScriptIsMissing', 'exceptions', 'Consolidation'));
        }

        if ($contributorRecords === null) {
            $contributorRecords = $master !== null
                ? $master->get("derived{$contributor->getEntityName()}Records", ['noCache' => true])
                : new EntityCollection([], $contributor->getEntityName());
        }

        $templateData = [
            'contributorRecord'  => $contributor,
            'masterRecord'       => $master,
            'contributorRecords' => $contributorRecords
        ];

        $res = $this->getTwig()->renderTemplate($consolidationScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input['masterRecordData'])) {
            throw new BadRequest(sprintf($this->translate('consolidationScriptIsNotValid', 'exceptions', 'Consolidation'), $res));
        }

        return new MasterRecordPayloadDTO($input['masterRecordData'], !empty($input['skipped']));
    }

    public function buildMasterRecordPayloadForCluster(Entity $cluster, string $consolidationScript): MasterRecordPayloadDTO
    {
        $masterEntityName = (string)$cluster->get('masterEntity');

        if (!$this->getAcl()->check($masterEntityName, 'read')) {
            throw new Forbidden();
        }

        $clusterItemService = $this->getRecordService('ClusterItem');

        $clusterItems = [];
        $unconfirmedItems = [];
        foreach ($this->getEntityManager()->getRepository('ClusterItem')->where(['clusterId' => $cluster->get('id')])->find() as $clusterItem) {
            if ($clusterItem->get('entityName') === $masterEntityName) {
                continue;
            }
            $clusterItems[] = $clusterItem;
            if (!$clusterItemService->isClusterItemConfirmed($clusterItem)) {
                $unconfirmedItems[] = $clusterItem;
            }
        }

        // unconfirmed items answer "what happens when I confirm them"; with none left the question becomes
        // "what does the script produce for what is already in the cluster", which is the updateMasterRecord run
        if (!empty($unconfirmedItems)) {
            $clusterItems = $unconfirmedItems;
        }

        if (empty($clusterItems)) {
            throw new BadRequest($this->translate('noClusterItemsToPreview', 'exceptions', 'Cluster'));
        }

        $records = [];
        foreach ($clusterItems as $clusterItem) {
            $record = $this->getEntityManager()->getEntity($clusterItem->get('entityName'), $clusterItem->get('entityId'));
            if (!empty($record)) {
                $records[] = $record;
            }
        }

        if (empty($records)) {
            throw new BadRequest($this->translate('noClusterItemsToPreview', 'exceptions', 'Cluster'));
        }

        $contributor = array_pop($records);
        $master = $cluster->get('goldenRecord');

        $contributorRecords = new EntityCollection([], $contributor->getEntityName());
        $addedIds = [];

        if (!empty($master)) {
            foreach ($master->get("derived{$contributor->getEntityName()}Records", ['noCache' => true]) as $linkedRecord) {
                $contributorRecords->append($linkedRecord);
                $addedIds[] = $linkedRecord->get('id');
            }
        }

        foreach ($records as $record) {
            if ($record->getEntityName() !== $contributor->getEntityName() || in_array($record->get('id'), $addedIds, true)) {
                continue;
            }
            $contributorRecords->append($record);
            $addedIds[] = $record->get('id');
        }

        return $this->buildMasterRecordPayload($contributor, $master, $consolidationScript, $contributorRecords);
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
        if ($consolidation->get('executeMergeAs') === 'system') {
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
