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
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class MasterDataEntity extends Base
{
    public function updateMasterRecord(Entity $staging, ?Entity $master = null): Entity
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
            return $master;
        }

        $user = $this->getInjection('container')->getUser();
        if ($user->get('type') !== 'System') {
            $this->getInjection('container')->setUser($user->getSystemUser());
        }

        try {
            $master = $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), json_decode(json_encode($input['masterRecordData'])));
        } catch (NotModified) {
            // ignore
        }

        $this->getInjection('container')->setUser($user);

        return $master;
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

        $user = $this->getInjection('container')->getUser();
        if ($user->get('type') !== 'System') {
            $this->getInjection('container')->setUser($user->getSystemUser());
        }

        $result = $this->getRecordService($masterEntity)->createEntity(json_decode(json_encode($input['masterRecordData'])));

        $this->getInjection('container')->setUser($user);

        return $result;
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

    protected function init()
    {
        parent::init();

        $this->addDependency('twig');
        $this->addDependency('container');
    }
}
