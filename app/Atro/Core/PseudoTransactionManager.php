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

namespace Atro\Core;

use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;
use Espo\Entities\User;
use Atro\Services\Record;

class PseudoTransactionManager
{
    private const FILE_PATH = 'data/has-transactions-jobs.log';

    private array $canceledJobs = [];

    private Container $container;
    private Container $systemContainer;

    protected Connection $connection;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->connection = \Atro\Core\Factories\Connection::createConnection($container->get('config')->get('database'));

        $this->systemContainer = new Container();
        $auth = new \Espo\Core\Utils\Auth($this->systemContainer);
        $auth->useNoAuth();
    }

    public static function hasJobs(): bool
    {
        return file_exists(self::FILE_PATH);
    }

    public function pushCreateEntityJob(string $entityType, $data, string $parentId = null): string
    {
        return $this->push($entityType, '', 'createEntity', $this->prepareInputData($data), $parentId);
    }

    public function pushUpdateEntityJob(string $entityType, string $entityId, $data, string $parentId = null): string
    {
        return $this->push($entityType, $entityId, 'updateEntity', $this->prepareInputData($data), $parentId);
    }

    public function pushMassUpdateEntityJob(string $entityType, $data, $params, string $parentId = null): string
    {
        return $this->push($entityType, '', 'massUpdate', $this->prepareInputData(['data' => $data, 'params' => $params]), $parentId);
    }

    public function pushDeleteEntityJob(string $entityType, string $entityId, string $parentId = null): string
    {
        return $this->push($entityType, $entityId, 'deleteEntity', '', $parentId);
    }

    public function pushLinkEntityJob(string $entityType, string $entityId, string $link, string $foreignId, string $parentId = null, bool $duplicateForeign = false): string
    {
        return $this->push($entityType, $entityId, 'linkEntity', Json::encode(['link' => $link, 'foreignId' => $foreignId, 'duplicateForeign' => $duplicateForeign], JSON_UNESCAPED_UNICODE), $parentId);
    }

    public function pushUnLinkEntityJob(string $entityType, string $entityId, string $link, string $foreignId, string $parentId = null): string
    {
        return $this->push($entityType, $entityId, 'unlinkEntity', Json::encode(['link' => $link, 'foreignId' => $foreignId], JSON_UNESCAPED_UNICODE), $parentId);
    }

    public function pushCustomJob(string $entityType, string $action, $data, string $parentId = null): string
    {
        return $this->push($entityType, '', $action, $this->prepareInputData($data), $parentId);
    }


    public function run(): void
    {
        $this->canceledJobs = [];
        while (!empty($jobs = $this->fetchJobs())) {
            foreach ($jobs as $job) {
                if (!in_array($job['id'], $this->canceledJobs)) {
                    $this->runJob($job);
                }
            }
        }

        if (self::hasJobs()) {
            unlink(self::FILE_PATH);
        }
    }

    public function runForEntity(string $entityType, string $entityId): void
    {
        while (!empty($job = $this->fetchJob($entityType, $entityId))) {
            $this->runJob($job);
        }
    }

    /**
     * @param mixed $data
     *
     * @return string
     */
    protected function prepareInputData($data): string
    {
        if ($data === null) {
            return '';
        }

        if (is_array($data) || is_object($data)) {
            return Json::encode($data, JSON_UNESCAPED_UNICODE);
        }

        if (is_string($data)) {
            return $data;
        }

        return (string)$data;
    }

    protected function fetchJobs(): array
    {
        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from('pseudo_transaction_job')
            ->where('deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->orderBy('sort_order', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults(50)
            ->fetchAllAssociative();
    }

    protected function fetchJob(string $entityType = '', string $entityId = '', string $parentId = ''): array
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')
            ->from('pseudo_transaction_job')
            ->where('deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false));

        if (!empty($entityType) && empty($parentId)) {
            $qb->andWhere('entity_type = :entityType')->setParameter('entityType', $entityType);
        }

        if (!empty($entityId) && empty($parentId)) {
            $qb->andWhere('entity_id = :entityId')->setParameter('entityId', $entityId);
        }

        if (!empty($parentId)) {
            $qb->andWhere('id = :id')->setParameter('id', $parentId);
        }

        $qb
            ->orderBy('sort_order', 'ASC')
            ->setFirstResult(0)
            ->setMaxResults(1);

        $record = $qb->fetchAssociative();
        $job = empty($record) ? [] : $record;

        if (!empty($job['parent_id']) && !empty($parentJob = $this->fetchJob($entityType, $entityId, $job['parent_id']))) {
            $job = $parentJob;
        }

        return $job;
    }

    protected function push(string $entityType, string $entityId, string $action, string $input, string $parentId = null): string
    {
        $id = Util::generateId();
        $parentId = empty($parentId) ? null : $parentId;
        $md5 = md5("{$entityType}_{$entityId}_{$action}_{$input}_{$parentId}");

        try {
            $this->connection->createQueryBuilder()
                ->insert('pseudo_transaction_job')
                ->setValue('id', ':id')
                ->setParameter('id', $id)
                ->setValue('sort_order', ':sortOrder')
                ->setParameter('sortOrder', time() - (new \DateTime('2023-09-01'))->getTimestamp())
                ->setValue('entity_type', ':entityType')
                ->setParameter('entityType', $entityType)
                ->setValue('entity_id', ':entityId')
                ->setParameter('entityId', $entityId)
                ->setValue($this->connection->quoteIdentifier('action'), ':action')
                ->setParameter('action', $action)
                ->setValue('input_data', ':input')
                ->setParameter('input', $input)
                ->setValue('created_by_id', ':createdById')
                ->setParameter('createdById', $this->getUser()->get('id'))
                ->setValue('parent_id', ':parentId')
                ->setParameter('parentId', $parentId, Mapper::getParameterType($parentId))
                ->setValue('md5', ':md5')
                ->setParameter('md5', $md5)
                ->executeQuery();
        } catch (UniqueConstraintViolationException $e) {
            $row = $this->connection->createQueryBuilder()
                ->select('id')
                ->from('pseudo_transaction_job')
                ->where('md5 = :md5')
                ->setParameter('md5', $md5)
                ->fetchAssociative();

            return isset($row['id']) ? (string)$row['id'] : '';
        }

        file_put_contents(self::FILE_PATH, '1');

        return $id;
    }

    protected function runJob(array $job): void
    {
        $inputIsEmpty = $job['input_data'] === '';

        try {
            $service = $this->getServiceFactory()->create($job['entity_type']);
            if ($service instanceof Record) {
                $service->setPseudoTransactionId($job['id']);
            }

            switch ($job['action']) {
                case 'createEntity':
                    if (!$inputIsEmpty) {
                        $service->createEntity(Json::decode($job['input_data']));
                    }
                    break;
                case 'updateEntity':
                    if (!$inputIsEmpty) {
                        $service->updateEntity($job['entity_id'], Json::decode($job['input_data']));
                    }
                    break;
                case 'massUpdate':
                    if (!$inputIsEmpty) {
                        $payload = Json::decode($job['input_data'], true);
                        if (array_key_exists('data', $payload)) {
                            $data = json_decode(json_encode($payload['data']));
                            $params = array_key_exists('params', $payload) ? $payload['params'] : [];
                            $service->massUpdate($data, $params);
                        }
                    }
                    break;
                case 'deleteEntity':
                    $service->deleteEntity($job['entity_id']);
                    break;
                case 'linkEntity':
                    if (!$inputIsEmpty) {
                        $inputData = Json::decode($job['input_data']);
                        if(!empty($inputData->duplicateForeign)){
                            $service->duplicateAndLinkEntity($job['entity_id'], $inputData->link, $inputData->foreignId);
                        }else{
                            $service->linkEntity($job['entity_id'], $inputData->link, $inputData->foreignId);
                        }
                    }
                    break;
                case 'unlinkEntity':
                    if (!$inputIsEmpty) {
                        $inputData = Json::decode($job['input_data']);
                        $service->unlinkEntity($job['entity_id'], $inputData->link, $inputData->foreignId);
                    }
                    break;
                default:
                    if ($inputIsEmpty) {
                        $service->{$job['action']}();
                    } else {
                        $service->{$job['action']}(Json::decode($job['input_data'], true));
                    }
                    break;
            }
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("PseudoTransaction job failed: ({$e->getCode()}) {$e->getMessage()}");

            $childrenIds = [];
            $this->collectChildren($job['id'], $childrenIds);
            $this->canceledJobs = array_merge($this->canceledJobs, $childrenIds);

            $this->connection->createQueryBuilder()
                ->delete('pseudo_transaction_job')
                ->where('id IN (:ids)')
                ->setParameter('ids', $childrenIds, Mapper::getParameterType($childrenIds))
                ->executeQuery();
        }

        $this->connection->createQueryBuilder()
            ->delete('pseudo_transaction_job')
            ->where('id = :id')
            ->setParameter('id', $job['id'])
            ->executeQuery();
    }

    protected function collectChildren(string $parentId, array &$childrenIds): void
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('pseudo_transaction_job')
            ->where('parent_id = :parentId')
            ->setParameter('parentId', $parentId)
            ->andWhere('deleted = :deleted')
            ->setParameter('deleted', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        $ids = array_column($rows, 'id');

        $childrenIds = array_merge($childrenIds, $ids);

        foreach ($ids as $id) {
            $this->collectChildren($id, $childrenIds);
        }
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->systemContainer->get('serviceFactory');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }
}
