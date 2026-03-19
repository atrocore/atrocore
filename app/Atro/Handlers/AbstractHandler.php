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

namespace Atro\Handlers;

use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Entities\User;
use \Atro\Services\AbstractService;
use Espo\Core\Acl;
use Espo\Core\ServiceFactory;
use Espo\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractHandler implements MiddlewareInterface
{
    public function __construct(protected readonly ContainerInterface $container)
    {
    }

    protected function getAcl(): Acl
    {
        return $this->container->get('acl');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->container->get('serviceFactory');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getLanguage(): Language
    {
        return $this->container->get('language');
    }

    protected function getEntityName(ServerRequestInterface $request): string
    {
        return (string)$request->getAttribute('entityName', '');
    }

    protected function getRecordService(string $entityName): AbstractService
    {
        if ($this->getServiceFactory()->checkExists($entityName)) {
            return $this->getServiceFactory()->create($entityName);
        }

        $service = $this->getServiceFactory()->create('Record');
        $service->setEntityType($entityName);

        return $service;
    }

    protected function getRequestBody(ServerRequestInterface $request): mixed
    {
        $body = (string)$request->getBody();
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
            'offset'         => isset($qp['offset']) ? (int)$qp['offset'] : null,
            'maxSize'        => !empty($qp['maxSize']) ? (int)$qp['maxSize'] : $maxSizeLimit,
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
