<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core;

use Espo\Core\Injectable;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Services\Record;
use PDO;

class PseudoTransactionManager extends Injectable
{
    private const FILE_PATH = 'data/has-transactions-jobs.log';

    private array $canceledJobs = [];

    private ?\PDO $pdo = null;

    public function __construct()
    {
        $this->addDependency('container');
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

    public function pushLinkEntityJob(string $entityType, string $entityId, string $link, string $foreignId, string $parentId = null): string
    {
        return $this->push($entityType, $entityId, 'linkEntity', Json::encode(['link' => $link, 'foreignId' => $foreignId], JSON_UNESCAPED_UNICODE), $parentId);
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
        return $this
            ->getPDO()
            ->query("SELECT * FROM `pseudo_transaction_job` WHERE deleted=0 ORDER BY sort_order ASC LIMIT 0,50")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    protected function fetchJob(string $entityType = '', string $entityId = '', string $parentId = ''): array
    {
        $query = "SELECT * FROM `pseudo_transaction_job` WHERE deleted=0";

        if (!empty($entityType) && empty($parentId)) {
            $query .= " AND entity_type=" . $this->getPDO()->quote($entityType);
        }

        if (!empty($entityId) && empty($parentId)) {
            $query .= " AND entity_id=" . $this->getPDO()->quote($entityId);
        }

        if (!empty($parentId)) {
            $query .= " AND id=" . $this->getPDO()->quote($parentId);
        }

        $query .= " ORDER BY sort_order ASC LIMIT 0,1";

        $record = $this->getPDO()->query($query)->fetch(PDO::FETCH_ASSOC);
        $job = empty($record) ? [] : $record;

        if (!empty($job['parent_id']) && !empty($parentJob = $this->fetchJob($entityType, $entityId, $job['parent_id']))) {
            $job = $parentJob;
        }

        return $job;
    }

    protected function push(string $entityType, string $entityId, string $action, string $input, string $parentId = null): string
    {
        $md5 = md5("{$entityType}_{$entityId}_{$action}_{$input}_{$parentId}");
        $id = Util::generateId();
        $entityType = $this->getPDO()->quote($entityType);
        $entityId = $this->getPDO()->quote($entityId);
        $input = $this->getPDO()->quote($input);
        $createdById = $this->getUser()->get('id');
        $parentId = empty($parentId) ? 'NULL' : $this->getPDO()->quote($parentId);

        try {
            $this->getPDO()->exec(
                "INSERT INTO `pseudo_transaction_job` (id,entity_type,entity_id,`action`,input_data,created_by_id,parent_id,md5) VALUES ('$id',$entityType,$entityId,'$action',$input,'$createdById',$parentId,'$md5')"
            );
        } catch (\PDOException $e) {
            if (!empty($e->errorInfo[1]) && $e->errorInfo[1] == 1062) {
                $id = $this->getPDO()->query("SELECT id FROM `pseudo_transaction_job` WHERE md5='$md5'")->fetch(PDO::FETCH_COLUMN);
                return is_string($id) ? $id : '';
            }
            throw $e;
        }

        file_put_contents(self::FILE_PATH, '1');

        return $id;
    }

    protected function runJob(array $job): void
    {
        $inputIsEmpty = $job['input_data'] === '';

        try {
            $user = $this->getEntityManager()->getEntity('User', $job['created_by_id']);
//            $user->set('isAdmin', true);

            $this->getInjection('container')->setUser($user);
            $this->getEntityManager()->setUser($user);
          
            if (!empty($user->get('portalId'))) {
                $this->getInjection('container')->setPortal($user->get('portal'));
            }

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
                        $service->linkEntity($job['entity_id'], $inputData->link, $inputData->foreignId);
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
            $GLOBALS['log']->error("PseudoTransaction job failed: {$e->getMessage()}");

            $childrenIds = [];
            $this->collectChildren($job['id'], $childrenIds);
            $this->canceledJobs = array_merge($this->canceledJobs, $childrenIds);
            $this->getPDO()->exec("DELETE FROM `pseudo_transaction_job` WHERE id IN ('" . implode("','", $childrenIds) . "')");
        }

        $this->getPDO()->exec("DELETE FROM `pseudo_transaction_job` WHERE id='{$job['id']}'");
    }

    protected function collectChildren(string $parentId, array &$childrenIds): void
    {
        $ids = $this
            ->getPDO()
            ->query("SELECT id FROM `pseudo_transaction_job` WHERE parent_id='$parentId' AND deleted=0")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $childrenIds = array_merge($childrenIds, $ids);

        foreach ($ids as $id) {
            $this->collectChildren($id, $childrenIds);
        }
    }

    protected function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = (new \Espo\Core\Application())->getContainer()->get('pdo');
        }

        return $this->pdo;
    }

    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getInjection('container')->get('serviceFactory');
    }

    protected function getUser(): User
    {
        return $this->getInjection('container')->get('user');
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('container')->get('entityManager');
    }
}
