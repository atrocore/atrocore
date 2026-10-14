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

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Metadata;
use Atro\DTOs\DataLineage\DataLineageEndpointDTO;
use Atro\DTOs\DataLineage\DataLineageLinkDTO;
use Atro\DTOs\DataLineage\DataLineageRecordDTO;
use Atro\DTOs\DataLineage\DataLineageRecordEdgeDTO;
use Atro\DTOs\DataLineage\DataLineageScopeEdgeDTO;
use Atro\DTOs\DataLineage\DataLineageTreeDTO;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;
use Espo\ORM\Entity;

class DataLineage extends AbstractService
{
    private ?array $pipelines = null;

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
        $this->addDependency('acl');
        $this->addDependency('serviceFactory');
    }

    public function getTree(string $scope, string $id, ?string $endpointScope = null, ?string $endpointId = null): DataLineageTreeDTO
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

        $service = $this->getServiceFactory()->create($entity->getEntityName());
        $value = $service instanceof Record ? $service->getLocalizedNameValue($entity, $entity->getEntityName()) : null;

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

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function getAcl(): Acl
    {
        return $this->getInjection('acl');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getInjection('serviceFactory');
    }
}
