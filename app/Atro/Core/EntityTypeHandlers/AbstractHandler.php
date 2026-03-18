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

namespace Atro\Core\EntityTypeHandlers;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Metadata;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;
use Espo\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractHandler implements MiddlewareInterface
{
    public function __construct(
        protected readonly ServiceFactory  $serviceFactory,
        protected readonly ContainerInterface $container,
        protected readonly Config          $config,
        protected readonly Metadata        $metadata,
        protected readonly EntityManager   $entityManager,
    ) {
    }

    protected function getEntityName(ServerRequestInterface $request): string
    {
        return (string) $request->getAttribute('entityName', '');
    }

    protected function getRecordService(string $entityName)
    {
        if ($this->serviceFactory->checkExists($entityName)) {
            return $this->serviceFactory->create($entityName);
        }

        $service = $this->serviceFactory->create('Record');
        $service->setEntityType($entityName);

        return $service;
    }

    protected function getAcl(): Acl
    {
        return $this->container->get('acl');
    }

    protected function getUser(): \Atro\Entities\User
    {
        return $this->container->get('user');
    }

    protected function getRequestBody(ServerRequestInterface $request): mixed
    {
        $body = (string) $request->getBody();
        if ($body !== '' && stristr($request->getHeaderLine('Content-Type'), 'application/json')) {
            return json_decode($body);
        }

        return new \stdClass();
    }

    protected function prepareWhereQuery(mixed $where): mixed
    {
        if (is_string($where)) {
            $where = json_decode(
                str_replace(['"{', '}"', '\"', '\n', '\t'], ['{', '}', '"', '', ''], $where),
                true
            );
        }

        return $where;
    }

    protected function buildListParams(ServerRequestInterface $request, int $maxSizeLimit = 200): array
    {
        $qp = $request->getQueryParams();

        $params = [
            'where'          => $this->prepareWhereQuery($qp['where'] ?? null),
            'offset'         => isset($qp['offset']) ? (int) $qp['offset'] : null,
            'maxSize'        => !empty($qp['maxSize']) ? (int) $qp['maxSize'] : $maxSizeLimit,
            'asc'            => ($qp['asc'] ?? 'true') === 'true',
            'sortBy'         => $qp['sortBy'] ?? null,
            'q'              => $qp['q'] ?? null,
            'textFilter'     => $qp['textFilter'] ?? null,
            'totalOnly'      => ($qp['totalOnly'] ?? null) === 'true',
            'collectionOnly' => ($qp['collectionOnly'] ?? null) === 'true',
        ];

        if (!empty($qp['primaryFilter'])) {
            $params['primaryFilter'] = $qp['primaryFilter'];
        }
        if (!empty($qp['boolFilterList'])) {
            $params['boolFilterList'] = $qp['boolFilterList'];
        }
        if (!empty($qp['filterList'])) {
            $params['filterList'] = $qp['filterList'];
        }
        if (!empty($qp['select']) && is_string($qp['select'])) {
            $params['select'] = explode(',', $qp['select']);
        }
        if (!empty($qp['attributes'])) {
            $params['attributesIds'] = explode(',', $qp['attributes']);
        }
        if (($qp['allAttributes'] ?? null) === 'true' || ($qp['allAttributes'] ?? null) === '1') {
            $params['allAttributes'] = true;
        }
        if (($qp['completeAttrDefs'] ?? null) === 'true' || ($qp['completeAttrDefs'] ?? null) === '1') {
            $params['completeAttrDefs'] = true;
        }

        return $params;
    }

    protected function buildListResult(array $result, array $params): array
    {
        if (!empty($params['totalOnly'])) {
            return ['total' => $result['total']];
        }

        if (isset($result['collection'])) {
            $list = $result['collection']->getValueMapList();
        } elseif (isset($result['list'])) {
            $list = $result['list'];
        } else {
            $list = [];
        }

        if (!empty($params['collectionOnly'])) {
            return ['list' => $list];
        }

        return [
            'total' => $result['total'] ?? null,
            'list'  => $list,
        ];
    }

    protected function buildMassParams(\stdClass $data): array
    {
        $params = [];

        if (property_exists($data, 'where') && !empty($data->byWhere)) {
            $params['where'] = json_decode(json_encode($data->where), true);
            if (property_exists($data, 'selectData')) {
                $params['selectData'] = json_decode(json_encode($data->selectData), true);
            }
        } elseif (property_exists($data, 'ids')) {
            $params['ids'] = $data->ids;
        }

        return $params;
    }
}