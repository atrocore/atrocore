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
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Exceptions\NotModified;
use Atro\Core\Templates\Services\Base;
use Atro\Core\Twig\Twig;
use Espo\ORM\Entity;

class MasterDataEntity extends Base
{
    public function updateMasterRecordByStagingEntity(string $stagingEntityName, string $stagingEntityId): bool
    {
        $staging = $this->getEntityManager()->getRepository($stagingEntityName)->get($stagingEntityId);
        if (empty($staging)) {
            throw new NotFound();
        }

        $master = $staging->get('goldenRecord');
        if (empty($master)) {
            throw new Forbidden();
        }

        $this->updateMasterRecord($staging, $master);

        return true;
    }

    public function updateMasterRecord(Entity $staging, Entity $master): Entity
    {
        if (!$this->getAcl()->check($master->getEntityName(), 'edit')) {
            throw new Forbidden();
        }

        $masterDataEntity = $this->getEntityManager()->getEntity('MasterDataEntity', $staging->getEntityType());
        if (empty($masterDataEntity)) {
            throw new BadRequest("MasterDataEntity with entityType {$staging->getEntityType()} not found.");
        }

        $mergingScript = $masterDataEntity->get('mergingScript');
        if (empty($mergingScript)) {
            throw new BadRequest($this->translate('mergingScriptIsMissing', 'exceptions', 'MasterDataEntity'));
        }

        $templateData = [
            'staging'  => $staging,
            'master'   => $master,
            'stagings' => $master->get('derivedRecords')
        ];

        $res = $this->getTwig()->renderTemplate($mergingScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input)) {
            throw new BadRequest(sprintf($this->translate('mergingScriptIsNotValid', 'exceptions', 'MasterDataEntity'), $res));
        }

        try {
            $master = $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), $input);
        } catch (NotModified) {
            // ignore
        }

        return $master;
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
    }
}
