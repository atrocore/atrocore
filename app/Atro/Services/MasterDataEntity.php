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
use Atro\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class MasterDataEntity extends Base
{
    public function updateMasterRecord(Entity $staging, ?Entity $master = null): bool
    {
        if ($master === null) {
            $master = $staging->get('masterRecord');
        }

        if (empty($master) || !$this->getAcl()->check($master->getEntityName(), 'edit')) {
            throw new Forbidden();
        }

        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $staging->getEntityName());
        if (empty($masterDataEntity)) {
            throw new BadRequest("MasterDataEntity with entityType {$staging->getEntityName()} not found.");
        }

        $mergingScript = $masterDataEntity->get('mergingScript');
        if (empty($mergingScript)) {
            throw new BadRequest($this->translate('mergingScriptIsMissing', 'exceptions', 'MasterDataEntity'));
        }

        $templateData = [
            'stagingRecord'  => $staging,
            'masterRecord'   => $master,
            'stagingRecords' => $master->get("derived{$staging->getEntityName()}Records")
        ];

        $res = $this->getTwig()->renderTemplate($mergingScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input['masterRecordData'])) {
            throw new BadRequest(sprintf($this->translate('mergingScriptIsNotValid', 'exceptions', 'MasterDataEntity'), $res));
        }

        if (!empty($input['skipped'])) {
            return false;
        }

        $this->executeAsMergeUser($masterDataEntity, function () use ($input, $master) {
            try {
                $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), json_decode(json_encode($input['masterRecordData'])));
            } catch (NotModified) {
                // ignore
            }
        });

        return true;
    }

    public function createMasterRecord(Entity $staging): ?Entity
    {
        $masterEntity = $this->getMetadata()->get(['scopes', $staging->getEntityName(), 'primaryEntityId']);

        if (empty($masterEntity) || !$this->getAcl()->check($masterEntity, 'create')) {
            throw new Forbidden();
        }

        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $staging->getEntityName());
        if (empty($masterDataEntity)) {
            throw new BadRequest("MasterDataEntity with entityType {$staging->getEntityName()} not found.");
        }

        $mergingScript = $masterDataEntity->get('mergingScript');
        if (empty($mergingScript)) {
            throw new BadRequest($this->translate('mergingScriptIsMissing', 'exceptions', 'MasterDataEntity'));
        }

        $templateData = [
            'stagingRecord'  => $staging,
            'masterRecord'   => null,
            'stagingRecords' => new EntityCollection([], $staging->getEntityName())
        ];

        $res = $this->getTwig()->renderTemplate($mergingScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input['masterRecordData'])) {
            throw new BadRequest(sprintf($this->translate('mergingScriptIsNotValid', 'exceptions', 'MasterDataEntity'), $res));
        }

        if (!empty($input['skipped'])) {
            return null;
        }

        $id = null;

        $this->executeAsMergeUser($masterDataEntity, function () use ($input, $masterEntity, &$id) {
            $id = $this->getRecordService($masterEntity)->createEntity(json_decode(json_encode($input['masterRecordData'])));
        });

        if (empty($id)) {
            return null;
        }

        return $this->getEntityManager()->getEntity($masterEntity, $id);
    }

    private function executeAsMergeUser(Entity $masterDataEntity, $callback): void
    {
        if ($masterDataEntity->get('executeMergeAs') === 'system') {
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

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $entity->set('name', $this->getInjection('language')->translate($entity->id, 'scopeNames', 'Global'));
        $entity->set('masterEntity', $this->getMetadata()->get("scopes.{$entity->id}.primaryEntityId"));
    }

    protected function checkProtectedFields(Entity $entity, \stdClass $data): void
    {
        $entity->set('masterEntity', $this->getMetadata()->get("scopes.{$entity->id}.primaryEntityId"));

        parent::checkProtectedFields($entity, $data);
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
