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

use Atro\Core\Services\AbstractService;
use Atro\Core\Application;
use Atro\Core\AttributeFieldConverter;
use Atro\Core\DataManager;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Metadata;
use Atro\Repositories\SoftwarePackage;
use Atro\Services\SoftwarePackage as SoftwarePackageService;
use Espo\Core\Acl;
use Atro\DTOs\DataLineage\DataLineageEndpointDTO;
use Atro\DTOs\DataLineage\DataLineageLinkDTO;
use Atro\DTOs\DataLineage\DataLineageRecordDTO;
use Atro\DTOs\DataLineage\DataLineageRecordEdgeDTO;
use Atro\DTOs\DataLineage\DataLineageScopeEdgeDTO;
use Atro\DTOs\DataLineage\DataLineageTreeDTO;
use Espo\ORM\Entity;
use Atro\Core\Utils\NameField;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Utils\Util;

/**
 * The service of the general API of the system, not bound to any entity: the endpoints in Handlers/Global.
 * It is available as the service `Global`.
 */
class GlobalService extends AbstractService
{
    public const GLOBAL_SEARCH_MAX = 20;

    private const PUBLIC_CONFIG_KEYS
        = [
            'applicationName', 'companyLogoId', 'dateFormat',
            'faviconId', 'frontendTimeout', 'passwordChangeRequestAvailable',
            'passwordRegexPattern', 'siteUrl', 'timeFormat',
            'timeZone', 'weekStart',
        ];

    private ?array $pipelines = null;

    public function getBootstrapData(): array
    {
        $data = $this->getPublicConfig();

        $data['jsLibs'] = $this->getMetadata()->get('app.jsLibs');
        $data['coreVersion'] = SoftwarePackageService::getCoreVersion();

        return $this->getInjection('container')->get('eventManager')
            ->dispatch('AppService', 'afterGetBootstrapData', new Event(['data' => $data]))
            ->getArgument('data');
    }

    /**
     * The part of the config an anonymous caller may receive.
     */
    public function getPublicConfig(): array
    {
        $config = $this->getConfig();

        $data = [];
        foreach (self::PUBLIC_CONFIG_KEYS as $key) {
            if ($config->has($key)) {
                $data[$key] = $config->get($key);
            }
        }

        return $data;
    }

    public function prepareScriptFields(string $entityName, array $fields): array
    {
        $entity = $this->getEntityManager()->getRepository($entityName)->get();

        $res = [];

        foreach ($fields as $item) {
            foreach ($entity->fields ?? [] as $field => $defs) {
                if ($field === $item && empty($defs['notStorable'])) {
                    $res[$field] = null;
                }

                if (!empty($defs['originalName']) && $defs['originalName'] === $item) {
                    if ((!empty($defs['isLinkMultipleIdList']) || empty($defs['notStorable'])) && empty($defs['isLinkEntityName'])) {
                        $res[$field] = null;
                    }
                }
            }
        }

        return [
            'text' => !empty($res) ? json_encode($res) : ''
        ];
    }

    public function prepareScriptAttributes(string $entityName, array $attributesIds): array
    {
        $entity = $this->getEntityManager()->getRepository($entityName)->get();

        $attributes = $this->getEntityManager()->getRepository('Attribute')->getAttributesByIds($attributesIds);

        $attributesDefs = [];
        foreach ($attributes as $attribute) {
            $this->getAttributeFieldConverter()->convert($entity, $attribute, $attributesDefs);
        }

        $res = [];
        foreach ($entity->fields ?? [] as $field => $defs) {
            if (!empty($defs['attributeId'])) {
                $res[$field] = null;
            }
        }

        if (!empty($res)) {
            $res['__attributes'] = $attributesIds;
            return [
                'text' => json_encode($res)
            ];
        }

        return [
            'text' => ''
        ];
    }

    public function rebuild(): void
    {
        if (Application::isSystemUpdating()) {
            DataManager::pushPublicData('isNeedToRebuildDatabase', true);
        } else {
            $this->getDataManager()->rebuild();
        }
    }

    public function getUserData(): array
    {
        $user = $this->getUser();
        if (!$user->has('teamsIds')) {
            $user->loadLinkMultipleField('teams');
        }

        $ids = [];
        $names = new \stdClass();
        foreach ($user->get('roles') ?? [] as $role) {
            $ids[] = $role->get('id');
            $names->{$role->get('id')} = $role->get('name');
        }
        $user->set('rolesIds', $ids);
        $user->set('rolesNames', $names);

        $userData = $user->getValueMap();

        // the config a logged-in frontend works with, on top of the bootstrap data it started from
        $settings = $this->getService('Settings')->getConfigData();
        $settings['matchingRules'] = $this->getEntityManager()->getRepository('MatchingRule')
            ->select(['id', 'name', 'type', 'matchingRuleSetId', 'matchingId'])
            ->find()->toArray();
        $settings = (object)$settings;

        unset($userData->authTokenId);
        unset($userData->password);

        return [
            'user'        => $userData,
            'acl'         => $this->getAcl()->getMap(),
            'preferences' => $this->getPreferencesData(),
            'settings'    => $settings,
            'appParams'   => [
                'maxUploadSize'        => $this->getMaxUploadSize() / 1024.0 / 1024.0,
                'expiredRentedModules' => $this->getSoftwarePackageRepository()->getExpiredRentedModules()
            ],
        ];
    }

    public function getPreferencesData(): \stdClass
    {
        $user = $this->getUser();

        $this->getUserProfileService()->prepareLayoutProfileData($user);

        $preferencesData = new \stdClass();
        $preferencesData->id = $user->id;
        $preferencesData->language = $user->getLanguage();
        $preferencesData->locale = $user->get('localeId');
        $preferencesData->dashboardLayout = $user->get('dashboardLayout');
        $preferencesData->dashletsOptions = $user->get('dashletsOptions');
        $preferencesData->favoritesList = $user->get('favoritesList');
        $preferencesData->followCreatedEntities = $user->get('followCreatedEntities');
        $preferencesData->followEntityOnStreamPost = $user->get('followEntityOnStreamPost');
        $preferencesData->useCustomTabList = $user->get('useCustomTabList');
        $preferencesData->lpNavigation = $user->get('lpNavigation');
        $preferencesData->hideShowFullList = $user->get('hideShowFullList');
        $preferencesData->layoutProfileId = $user->get('layoutProfileId');
        $preferencesData->dashboardLayout = $user->get('dashboardLayout');
        $preferencesData->dashletsOptions = $user->get('dashletsOptions');
        $preferencesData->favoritesList = $user->get('favoritesList');
        $preferencesData->closedPanelOptions = $user->get('closedPanelOptions');

        if (!empty($locale = $user->getLocale())) {
            $preferencesData->decimalMark = $locale->get('decimalMark');
            $preferencesData->timeFormat = $locale->get('timeFormat');
            $preferencesData->thousandSeparator = $locale->get('thousandSeparator');
            $preferencesData->weekStart = $locale->get('weekStart') === 'monday' ? 1 : 0;
            $preferencesData->dateFormat = $locale->get('dateFormat');
            $preferencesData->timeZone = $locale->get('timeZone');
            $preferencesData->fallbackLanguage = $locale->get('fallbackLanguageCode');
        } else {
            if (!empty($this->getConfig()->get('locale'))) {
                $locale = $this->getEntityManager()->getRepository('Locale')->get($this->getConfig()->get('locale'));
                if (!empty($locale)) {
                    $preferencesData->language = $locale->get('language');
                    $preferencesData->fallbackLanguage = $locale->get('fallbackLanguageCode');
                }
            }
        }

        if (!empty($style = $user->getStyle())) {
            $preferencesData->styleId = $style->get('id');
            $preferencesData->styleName = $style->get('name');
        }

        return $preferencesData;
    }

    public function computeEntityScriptField(string $entityName, string $id, string $field): void
    {
        if (in_array($field, $this->getAcl()->getScopeForbiddenAttributeList($entityName, 'edit'))) {
            throw new Forbidden();
        }

        $repository = $this->getEntityManager()->getRepository($entityName);
        $entity = $repository->get($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $this->getAttributeFieldConverter()->putAttributesToEntity($entity);

        $fieldDefs = $entity->entityDefs['fields'][$field];

        if (empty($fieldDefs)) {
            throw new BadRequest('No such Field');
        }

        if (!empty($fieldDefs['type']) && $fieldDefs['type'] === 'script' && !empty($fieldDefs['script'])) {
            $repository->calculateScriptFields($entity, $field);
        }
    }

    public function markFieldAsPersonalData(string $entityName, string $recordId, string $field): bool
    {
        if ($this->getMetadata()->get(['scopes', $entityName, 'type']) === 'ReferenceData') {
            throw new BadRequest("Entity '{$entityName}' does not contain personal data.");
        }

        $repository = $this->getEntityManager()->getRepository($entityName);

        $entity = $repository->get($recordId);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $repository->createPersonalDataRecord($recordId, $field);

        return true;
    }

    public function unmarkFieldAsPersonalData(string $entityName, string $recordId, string $field): bool
    {
        if ($this->getMetadata()->get(['scopes', $entityName, 'type']) === 'ReferenceData') {
            throw new BadRequest("Entity '{$entityName}' does not contain personal data.");
        }

        $repository = $this->getEntityManager()->getRepository($entityName);

        $entity = $repository->get($recordId);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $repository->deletePersonalDataRecord($recordId, $field);

        return true;
    }

    /**
     * Searches the records of the entities of the global search by the text.
     */
    public function globalSearch(string $query): array
    {
        $list = [];

        foreach ($this->getConfig()->get('globalSearchEntityList', []) as $entityName) {
            if (count($list) >= self::GLOBAL_SEARCH_MAX) {
                break;
            }

            if (!$this->getAcl()->checkScope($entityName, 'read')) {
                continue;
            }
            if (!$this->getMetadata()->get(['scopes', $entityName])) {
                continue;
            }

            $selectManager = $this->getInjection('container')->get('selectManagerFactory')->create($entityName);
            $params = ['select' => ['id']];

            if ($this->getMetadata()->get(['entityDefs', $entityName, 'fields', 'name'])) {
                $params['select'][] = 'name';
            }

            $selectManager->manageAccess($params);
            $selectManager->applyTextFilter($query, $params);

            $collection = $this->getEntityManager()->getRepository($entityName)->limit(0, self::GLOBAL_SEARCH_MAX - count($list))->find($params);
            foreach ($collection as $entity) {
                $list[] = array_merge($entity->toArray(), ['_scope' => $entity->getEntityName()]);
            }
        }

        return [
            'count' => count($list),
            'list'  => $list
        ];
    }

    /**
     * The records connected with the record through the data pipelines and the contributor-master relations,
     * from the endpoint (the final record downstream) up to the data sources.
     */
    public function getDataLineageTree(string $scope, string $id, ?string $endpointScope = null, ?string $endpointId = null): DataLineageTreeDTO
    {
        $current = $this->getEntityManager()->getEntity($scope, $id);
        if (empty($current)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($current, 'read')) {
            throw new Forbidden();
        }

        $previous = $this->findDownstream($this->buildRecordKey($scope, $id));

        $endpoints = [];
        foreach ($this->loadRecords(array_keys(array_diff_key($previous, array_flip(array_filter($previous))))) as $entity) {
            $endpoints[] = new DataLineageEndpointDTO($entity->getEntityName(), $entity->get('id'), $this->getRecordName($entity));
        }

        usort($endpoints, fn(DataLineageEndpointDTO $a, DataLineageEndpointDTO $b) => [$a->scope, $a->name ?? ''] <=> [$b->scope, $b->name ?? '']);

        $selected = $endpoints[0];
        foreach ($endpoints as $endpoint) {
            if ($endpoint->scope === $endpointScope && $endpoint->id === $endpointId) {
                $selected = $endpoint;
            }
        }

        $selectedKey = $this->buildRecordKey($selected->scope, $selected->id);

        $pathKeys = [];
        for ($key = $selectedKey; $key !== null; $key = $previous[$key]) {
            $pathKeys[$key] = true;
        }

        return $this->buildUpstreamTree($selected, $endpoints, $pathKeys);
    }

    /**
     * @return array<string, ?string>
     */
    private function findDownstream(string $currentKey): array
    {
        $previous = [$currentKey => null];

        $frontier = [$currentKey];
        while (!empty($frontier)) {
            $edges = [];
            foreach ($this->groupIdsByScope($frontier) as $frontierScope => $ids) {
                foreach ($this->getDownstreamEdges($frontierScope) as $scopeEdge) {
                    $map = $this->getEntityManager()->getRepository($frontierScope)->getLinkedIds($scopeEdge->field, $ids);
                    foreach ($map as $sourceId => $targetId) {
                        $edges[] = new DataLineageRecordEdgeDTO($scopeEdge->type, $frontierScope, $sourceId, $scopeEdge->scope, $targetId);
                    }
                }
            }

            $loaded = $this->loadRecords(array_map(fn(DataLineageRecordEdgeDTO $edge) => $edge->getToKey(), $edges));

            $next = [];
            foreach ($edges as $edge) {
                $key = $edge->getToKey();
                if (isset($loaded[$key]) && !array_key_exists($key, $previous)) {
                    $previous[$key] = $edge->getKey();
                    $next[] = $key;
                }
            }

            $frontier = $next;
        }

        return $previous;
    }

    /**
     * @param DataLineageEndpointDTO[] $endpoints
     * @param array<string, bool> $pathKeys
     */
    private function buildUpstreamTree(DataLineageEndpointDTO $selected, array $endpoints, array $pathKeys): DataLineageTreeDTO
    {
        $maxNodes = (int)$this->getConfig()->get('dataLineageMaxNodes', 500);
        $selectedKey = $this->buildRecordKey($selected->scope, $selected->id);
        $names = [$selectedKey => $selected->name];
        $records = [new DataLineageRecordDTO($selected->scope, $selected->id, $selected->name, null, null, $selected->name === null)];
        $included = [$selectedKey => true];
        $links = [];
        $truncated = false;

        $frontier = [$selectedKey];
        while (!empty($frontier)) {
            $edges = [];
            foreach ($this->groupIdsByScope($frontier) as $frontierScope => $ids) {
                foreach ($this->getUpstreamEdges($frontierScope) as $scopeEdge) {
                    $map = $this->getEntityManager()->getRepository($scopeEdge->scope)->getReferencingIds($scopeEdge->field, $ids);
                    foreach ($map as $sourceId => $targetId) {
                        $edges[] = new DataLineageRecordEdgeDTO($scopeEdge->type, $scopeEdge->scope, $sourceId, $frontierScope, $targetId);
                    }
                }
            }

            $accepted = [];
            foreach ($edges as $edge) {
                if (isset($included[$edge->getKey()])) {
                    continue;
                }
                if (!isset($pathKeys[$edge->getKey()]) && count($included) >= $maxNodes) {
                    $truncated = true;
                    continue;
                }
                $accepted[] = $edge;
                $included[$edge->getKey()] = true;
            }

            $names += array_map(fn(Entity $entity) => $this->getRecordName($entity), $this->loadRecords(array_map(fn(DataLineageRecordEdgeDTO $edge) => $edge->getKey(), $accepted)));

            $accepted = array_values(array_filter($accepted, fn(DataLineageRecordEdgeDTO $edge) => array_key_exists($edge->getKey(), $names)));

            usort($accepted, fn(DataLineageRecordEdgeDTO $a, DataLineageRecordEdgeDTO $b) => [$a->scope, $names[$a->getKey()] ?? ''] <=> [$b->scope, $names[$b->getKey()] ?? '']);

            $frontier = [];
            foreach ($accepted as $edge) {
                $name = $names[$edge->getKey()];
                $records[] = new DataLineageRecordDTO($edge->scope, $edge->id, $name, $edge->toScope, $edge->toId, $name === null);

                $linkKey = "$edge->type|$edge->scope|$edge->toScope";
                if (!isset($links[$linkKey])) {
                    $config = $this->getLinkConfig($edge->type, $edge->scope, $edge->toScope);
                    $links[$linkKey] = new DataLineageLinkDTO($edge->type, $edge->scope, $edge->toScope, $config?->get('id'), $config?->get('number'));
                }

                if (isset($pathKeys[$edge->getKey()]) || $name !== null) {
                    $frontier[] = $edge->getKey();
                }
            }
        }

        return new DataLineageTreeDTO(
            $selected,
            $endpoints,
            $records,
            array_values($links),
            $truncated
        );
    }

    /**
     * @return DataLineageScopeEdgeDTO[]
     */
    private function getDownstreamEdges(string $scope): array
    {
        $edges = [];

        foreach ($this->getPipelines() as $pipeline) {
            if ($pipeline->get('sourceEntityId') === $scope) {
                $edges[] = new DataLineageScopeEdgeDTO(DataLineageLinkDTO::TYPE_DATA_PIPELINE, 'target' . ucfirst($pipeline->get('targetEntityId')) . 'Id', $pipeline->get('targetEntityId'));
            }
        }

        $scopeDefs = $this->getMetadata()->get(['scopes', $scope]) ?? [];
        if (!empty($scopeDefs['primaryEntityId']) && ($scopeDefs['role'] ?? null) === 'contributor') {
            $edges[] = new DataLineageScopeEdgeDTO(DataLineageLinkDTO::TYPE_CONSOLIDATION, 'masterRecordId', $scopeDefs['primaryEntityId']);
        }

        return $edges;
    }

    /**
     * @return DataLineageScopeEdgeDTO[]
     */
    private function getUpstreamEdges(string $scope): array
    {
        $edges = [];

        foreach ($this->getPipelines() as $pipeline) {
            if ($pipeline->get('targetEntityId') === $scope) {
                $edges[] = new DataLineageScopeEdgeDTO(DataLineageLinkDTO::TYPE_DATA_PIPELINE, 'target' . ucfirst($scope) . 'Id', $pipeline->get('sourceEntityId'));
            }
        }

        foreach ($this->getMetadata()->get('scopes') ?? [] as $contributorScope => $scopeDefs) {
            if (($scopeDefs['primaryEntityId'] ?? null) === $scope && ($scopeDefs['role'] ?? null) === 'contributor') {
                $edges[] = new DataLineageScopeEdgeDTO(DataLineageLinkDTO::TYPE_CONSOLIDATION, 'masterRecordId', $contributorScope);
            }
        }

        return $edges;
    }

    private function getPipelines(): array
    {
        if ($this->pipelines === null) {
            $this->pipelines = [];
            foreach ($this->getEntityManager()->getRepository('DataPipeline')->find() as $pipeline) {
                if (empty($this->getMetadata()->get(['entityDefs', $pipeline->get('sourceEntityId'), 'links', 'target' . ucfirst($pipeline->get('targetEntityId'))]))) {
                    continue;
                }

                $this->pipelines[] = $pipeline;
            }
        }

        return $this->pipelines;
    }

    private function getLinkConfig(string $type, string $from, string $to): ?Entity
    {
        if ($type === DataLineageLinkDTO::TYPE_DATA_PIPELINE) {
            if (!$this->getAcl()->check('DataPipeline', 'read')) {
                return null;
            }

            foreach ($this->getPipelines() as $pipeline) {
                if ($pipeline->get('sourceEntityId') === $from && $pipeline->get('targetEntityId') === $to) {
                    return $pipeline;
                }
            }

            return null;
        }

        if (!$this->getAcl()->check('Consolidation', 'read')) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Consolidation')->getByEntityName($to);
    }

    /**
     * @return array<string, Entity>
     */
    private function loadRecords(array $keys): array
    {
        $records = [];
        foreach ($this->groupIdsByScope(array_unique($keys)) as $scope => $ids) {
            foreach ($this->getEntityManager()->getRepository($scope)->where(['id' => $ids])->find() as $entity) {
                $records[$this->buildRecordKey($scope, $entity->get('id'))] = $entity;
            }
        }

        return $records;
    }

    private function getRecordName(Entity $entity): ?string
    {
        if (!$this->getAcl()->check($entity, 'read')) {
            return null;
        }

        $value = $this->getEntityManager()->getContainer()->get(NameField::class)->getLocalizedValue($entity, $entity->getEntityName());

        return !empty($value) ? $value : $entity->get('id');
    }

    private function buildRecordKey(string $scope, string $id): string
    {
        return "$scope:$id";
    }

    private function groupIdsByScope(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            [$scope, $id] = explode(':', $key, 2);
            $result[$scope][] = $id;
        }

        return $result;
    }

    /**
     * Creates or updates the records of any entities: a record is found by its ID, its unique fields or its unique
     * indexes and updated, otherwise created.
     */
    public function upsert(array $data): array
    {
        $result = [];
        foreach ($data as $k => $node) {
            if (!property_exists($node, 'entity')) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => "'entity' parameter is required."
                ];
                continue 1;
            }

            if (!property_exists($node, 'payload')) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => "'payload' parameter is required."
                ];
                continue 1;
            }

            try {
                $service = $this->getService($node->entity);
            } catch (\Throwable $e) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'message' => $e->getMessage()
                ];
                continue 1;
            }

            $existed = null;
            if (property_exists($node->payload, 'id')) {
                $existed = $this->getEntityManager()->getEntity($node->entity, $node->payload->id);
            }
            if (empty($existed)) {
                // Check if entity exists
                $fields = $this->getMetadata()->get(['entityDefs', $node->entity, 'fields']);
                $uniqueFields = [];
                foreach ($fields as $name => $field) {
                    if (!empty($field['unique'])) {
                        $uniqueFields[$name] = $field;
                    }
                }

                $uniqueIndexes = [];
                if (empty($uniqueFields)) {
                    foreach ($this->getMetadata()->get(['entityDefs', $node->entity, 'uniqueIndexes']) ?? [] as $indexes) {
                        $camelCaseIndexes = [];
                        foreach (array_diff($indexes, ['deleted']) as $index) {
                            $camelCaseIndexes[] = Util::toCamelCase($index);
                        }
                        $uniqueIndexes[] = $camelCaseIndexes;
                    }
                }

                $whereClause = [];
                if (count($uniqueFields) > 0) {
                    foreach ($uniqueFields as $key => $field) {
                        $value = $node->payload->{$key} ?? null;
                        if ($value === null && $this->getMetadata()->get(['entityDefs', $node->entity, 'fields', $key, 'notNull'])) {
                            $value = '';
                        }

                        if ($value !== null) {
                            $whereClause[] = [$key => $value];
                        }
                    }
                } else if (count($uniqueIndexes) > 0) {
                    foreach ($uniqueIndexes as $indexes) {
                        if ($this->hasAllProperties($node->payload, $indexes)) {
                            $where = [];
                            foreach ($indexes as $index) {
                                $value = $node->payload->{$index};
                                if ($value === null && $this->getMetadata()->get(['entityDefs', $node->entity, 'fields', $index, 'notNull'])) {
                                    $value = '';
                                }

                                $where[$index] = $value;
                            }

                            if (!empty($where)) {
                                $whereClause[] = $where;
                            }
                        }
                    }
                }

                if (count($whereClause) > 0) {
                    $existed = $this->getEntityManager()
                        ->getRepository($node->entity)
                        ->where($whereClause)
                        ->findOne();
                }
            }

            if (!empty($existed)) {
                $result[$k] = [
                    'entity' => $node->entity,
                    'id'     => $existed->get('id'),
                    'stored' => true,
                ];

                try {
                    $service->updateEntity($existed->get('id'), $node->payload);

                    $result[$k]['status'] = 'Updated';
                } catch (NotModified $e) {
                    $result[$k]['status'] = 'NotModified';
                } catch (\Throwable $e) {
                    $result[$k]['status'] = 'Failed';
                    $result[$k]['stored'] = false;
                    $result[$k]['code'] = $e->getCode();
                    $result[$k]['message'] = $e->getMessage();
                }
                continue;
            }

            try {
                $id = $service->createEntity($node->payload);
                $result[$k] = [
                    'status' => 'Created',
                    'stored' => true,
                    'entity' => $node->entity,
                    'id'     => $id
                ];
            } catch (\Throwable $e) {
                $result[$k] = [
                    'status'  => 'Failed',
                    'stored'  => false,
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage()
                ];
            }
        }

        return $result;
    }

    /**
     * Creates the job upserting the records in the background.
     */
    public function upsertViaJob(array $data): array
    {
        $job = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'name'    => 'Action Upsert',
            'type'    => 'Upsert',
            'payload' => $data,
        ]);
        $this->getEntityManager()->saveEntity($job);

        return ['jobId' => $job->get('id')];
    }

    /**
     * Creates the job relating the records found by the conditions of the payload in the background.
     */
    public function addRelationViaJob(string $entityName, string $link, array $payload): array
    {
        return $this->createRelationJob('add', $entityName, $link, $payload);
    }

    /**
     * Creates the job unrelating the records found by the conditions of the payload in the background.
     */
    public function removeRelationViaJob(string $entityName, string $link, array $payload): array
    {
        return $this->createRelationJob('remove', $entityName, $link, $payload);
    }

    protected function getMaxUploadSize(): int
    {
        $maxSize = 0;

        $postMaxSize = $this->convertPHPSizeToBytes(ini_get('post_max_size'));
        if ($postMaxSize > 0) {
            $maxSize = $postMaxSize;
        }
        $attachmentUploadMaxSize = $this->getConfig()->get('attachmentUploadMaxSize');
        if ($attachmentUploadMaxSize && (!$maxSize || $attachmentUploadMaxSize < $maxSize)) {
            $maxSize = $attachmentUploadMaxSize;
        }

        return $maxSize;
    }

    /**
     * @param mixed $size
     *
     * @return int
     */
    protected function convertPHPSizeToBytes($size): int
    {
        if (is_numeric($size)) {
            return $size;
        }

        $suffix = substr($size, -1);
        $value = substr($size, 0, -1);
        switch (strtoupper($suffix)) {
            case 'P':
                $value *= 1024;
            case 'T':
                $value *= 1024;
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
                break;
        }

        return $value;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getUserProfileService(): UserProfile
    {
        return $this->getService('UserProfile');
    }

    protected function getService(string $name)
    {
        return $this->getInjection('container')->get('serviceFactory')->create($name);
    }

    protected function getAcl(): Acl
    {
        return $this->getInjection('container')->get('acl');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('container')->get('metadata');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('container')->get('dataManager');
    }

    protected function getAttributeFieldConverter(): AttributeFieldConverter
    {
        return $this->getInjection('container')->get(AttributeFieldConverter::class);
    }

    protected function getSoftwarePackageRepository(): SoftwarePackage
    {
        return $this->getEntityManager()->getRepository('SoftwarePackage');
    }

    private function hasAllProperties(\stdClass $object, array $properties): bool
    {
        foreach ($properties as $property) {
            if (!property_exists($object, $property)) {
                return false;
            }
        }

        return true;
    }

    private function createRelationJob(string $action, string $entityName, string $link, array $payload): array
    {
        if (!$this->getAcl()->check($entityName, 'edit')) {
            throw new Forbidden();
        }

        $job = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'name'    => ($action === 'add' ? 'Add' : 'Remove') . ' relation creator: ' . $entityName . '.' . $link,
            'type'    => 'EntityRelationBulkCreator',
            'payload' => array_merge($payload, ['action' => $action, 'entityType' => $entityName, 'link' => $link]),
        ]);
        $this->getEntityManager()->saveEntity($job);

        return ['jobId' => $job->get('id')];
    }
}
