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
use Atro\DTOs\DataLineage\DataLineageTreeDTO;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;

class DataLineage extends AbstractService
{
    private ?array $pipelines = null;
    private array $entities = [];
    private array $names = [];

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

        $currentKey = "$scope:$id";
        $this->entities[$currentKey] = $current;

        [$endpointKeys, $previous] = $this->findEndpoints($currentKey);

        $endpoints = array_map(function (string $key) {
            $entity = $this->entities[$key];

            return new DataLineageEndpointDTO($entity->getEntityName(), $entity->get('id'), $this->getRecordName($key));
        }, $endpointKeys);

        usort($endpoints, fn(DataLineageEndpointDTO $a, DataLineageEndpointDTO $b) => [$a->scope, $a->name ?? ''] <=> [$b->scope, $b->name ?? '']);

        $selected = $endpoints[0];
        foreach ($endpoints as $endpoint) {
            if ($endpoint->scope === $endpointScope && $endpoint->id === $endpointId) {
                $selected = $endpoint;
            }
        }

        $selectedKey = "$selected->scope:$selected->id";

        $pathKeys = [];
        for ($key = $selectedKey; $key !== null; $key = $previous[$key]) {
            $pathKeys[$key] = true;
        }

        $maxNodes = (int)$this->getConfig()->get('dataLineageMaxNodes', 500);
        $records = [$this->createRecord($selectedKey, null)];
        $included = [$selectedKey => true];
        $links = [];
        $truncated = false;

        $frontier = [$selectedKey];
        while (!empty($frontier)) {
            $candidates = [];
            foreach ($this->groupIdsByScope($frontier) as $frontierScope => $ids) {
                foreach ($this->getUpstreamEdges($frontierScope) as $edge) {
                    $map = $this->getEntityManager()->getRepository($edge['scope'])->getReferencingIds($edge['field'], $ids);
                    foreach ($map as $sourceId => $targetId) {
                        $candidates[] = [
                            'key'  => "{$edge['scope']}:$sourceId",
                            'to'   => "$frontierScope:$targetId",
                            'type' => $edge['type']
                        ];
                    }
                }
            }

            usort($candidates, fn(array $a, array $b) => isset($pathKeys[$b['key']]) <=> isset($pathKeys[$a['key']]));

            $accepted = [];
            foreach ($candidates as $candidate) {
                if (isset($included[$candidate['key']])) {
                    continue;
                }
                if (!isset($pathKeys[$candidate['key']]) && count($included) >= $maxNodes) {
                    $truncated = true;
                    continue;
                }
                $accepted[] = $candidate;
                $included[$candidate['key']] = true;
            }

            $this->loadEntities(array_column($accepted, 'key'));

            $accepted = array_values(array_filter($accepted, fn(array $candidate) => isset($this->entities[$candidate['key']])));

            usort($accepted, function (array $a, array $b) use ($pathKeys) {
                return [isset($pathKeys[$b['key']]), $this->entities[$a['key']]->getEntityName(), $this->getRecordName($a['key']) ?? '']
                    <=> [isset($pathKeys[$a['key']]), $this->entities[$b['key']]->getEntityName(), $this->getRecordName($b['key']) ?? ''];
            });

            $frontier = [];
            foreach ($accepted as $candidate) {
                $records[] = $this->createRecord($candidate['key'], $candidate['to']);

                $from = $this->entities[$candidate['key']]->getEntityName();
                $to = $this->entities[$candidate['to']]->getEntityName();
                $links["{$candidate['type']}|$from|$to"] = [$candidate['type'], $from, $to];

                if (isset($pathKeys[$candidate['key']]) || $this->getRecordName($candidate['key']) !== null) {
                    $frontier[] = $candidate['key'];
                }
            }
        }

        return new DataLineageTreeDTO(
            $selected,
            $endpoints,
            $records,
            array_map(fn(array $link) => new DataLineageLinkDTO($link[0], $link[1], $link[2], $this->getLinkConfigId(...$link)), array_values($links)),
            $truncated
        );
    }

    private function findEndpoints(string $currentKey): array
    {
        $previous = [$currentKey => null];
        $endpointKeys = [];

        $frontier = [$currentKey];
        while (!empty($frontier)) {
            $candidates = [];
            foreach ($this->groupIdsByScope($frontier) as $frontierScope => $ids) {
                foreach ($this->getDownstreamEdges($frontierScope) as $edge) {
                    $map = $this->getEntityManager()->getRepository($frontierScope)->getLinkedIds($edge['field'], $ids);
                    foreach ($map as $sourceId => $targetId) {
                        $candidates[] = ['key' => "{$edge['scope']}:$targetId", 'from' => "$frontierScope:$sourceId"];
                    }
                }
            }

            $this->loadEntities(array_column($candidates, 'key'));

            $hasDownstream = [];
            $next = [];
            foreach ($candidates as $candidate) {
                if (!isset($this->entities[$candidate['key']])) {
                    continue;
                }

                $hasDownstream[$candidate['from']] = true;

                if (!array_key_exists($candidate['key'], $previous)) {
                    $previous[$candidate['key']] = $candidate['from'];
                    $next[] = $candidate['key'];
                }
            }

            foreach ($frontier as $key) {
                if (!isset($hasDownstream[$key])) {
                    $endpointKeys[] = $key;
                }
            }

            $frontier = $next;
        }

        return [$endpointKeys, $previous];
    }

    private function getDownstreamEdges(string $scope): array
    {
        $edges = [];

        foreach ($this->getPipelines() as $pipeline) {
            if ($pipeline['source'] === $scope) {
                $edges[] = [
                    'type'  => DataLineageLinkDTO::TYPE_DATA_PIPELINE,
                    'field' => 'target' . ucfirst($pipeline['target']) . 'Id',
                    'scope' => $pipeline['target']
                ];
            }
        }

        $scopeDefs = $this->getMetadata()->get(['scopes', $scope]) ?? [];
        if (!empty($scopeDefs['primaryEntityId']) && ($scopeDefs['role'] ?? null) === 'contributor') {
            $edges[] = [
                'type'  => DataLineageLinkDTO::TYPE_CONSOLIDATION,
                'field' => 'masterRecordId',
                'scope' => $scopeDefs['primaryEntityId']
            ];
        }

        return $edges;
    }

    private function getUpstreamEdges(string $scope): array
    {
        $edges = [];

        foreach ($this->getPipelines() as $pipeline) {
            if ($pipeline['target'] === $scope) {
                $edges[] = [
                    'type'  => DataLineageLinkDTO::TYPE_DATA_PIPELINE,
                    'field' => 'target' . ucfirst($scope) . 'Id',
                    'scope' => $pipeline['source']
                ];
            }
        }

        foreach ($this->getMetadata()->get('scopes') ?? [] as $contributorScope => $scopeDefs) {
            if (($scopeDefs['primaryEntityId'] ?? null) === $scope && ($scopeDefs['role'] ?? null) === 'contributor') {
                $edges[] = [
                    'type'  => DataLineageLinkDTO::TYPE_CONSOLIDATION,
                    'field' => 'masterRecordId',
                    'scope' => $contributorScope
                ];
            }
        }

        return $edges;
    }

    private function getPipelines(): array
    {
        if ($this->pipelines === null) {
            $this->pipelines = [];
            foreach ($this->getEntityManager()->getRepository('DataPipeline')->find() as $pipeline) {
                $source = $pipeline->get('sourceEntityId');
                $target = $pipeline->get('targetEntityId');

                if (empty($this->getMetadata()->get(['entityDefs', $source, 'links', 'target' . ucfirst($target)]))) {
                    continue;
                }

                $this->pipelines[] = ['id' => $pipeline->get('id'), 'source' => $source, 'target' => $target];
            }
        }

        return $this->pipelines;
    }

    private function getLinkConfigId(string $type, string $from, string $to): ?string
    {
        if ($type === DataLineageLinkDTO::TYPE_DATA_PIPELINE) {
            if (!$this->getAcl()->check('DataPipeline', 'read')) {
                return null;
            }

            foreach ($this->getPipelines() as $pipeline) {
                if ($pipeline['source'] === $from && $pipeline['target'] === $to) {
                    return $pipeline['id'];
                }
            }

            return null;
        }

        if (!$this->getAcl()->check('Consolidation', 'read')) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Consolidation')->getByEntityName($to)?->get('id');
    }

    private function loadEntities(array $keys): void
    {
        $missing = array_filter($keys, fn(string $key) => !isset($this->entities[$key]));

        foreach ($this->groupIdsByScope($missing) as $scope => $ids) {
            foreach ($this->getEntityManager()->getRepository($scope)->where(['id' => $ids])->find() as $entity) {
                $this->entities["$scope:{$entity->get('id')}"] = $entity;
            }
        }
    }

    private function createRecord(string $key, ?string $toKey): DataLineageRecordDTO
    {
        $entity = $this->entities[$key];
        $name = $this->getRecordName($key);
        $to = $toKey !== null ? $this->entities[$toKey] : null;

        return new DataLineageRecordDTO(
            $entity->getEntityName(),
            $entity->get('id'),
            $name,
            $to?->getEntityName(),
            $to?->get('id'),
            $name === null
        );
    }

    private function getRecordName(string $key): ?string
    {
        if (!array_key_exists($key, $this->names)) {
            $entity = $this->entities[$key];
            $scope = $entity->getEntityName();

            $this->names[$key] = null;
            if ($this->getAcl()->check($entity, 'read')) {
                $service = $this->getServiceFactory()->create($scope);
                $value = $service instanceof Record ? $service->getLocalizedNameValue($entity, $scope) : null;
                $this->names[$key] = !empty($value) ? $value : $entity->get('id');
            }
        }

        return $this->names[$key];
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
