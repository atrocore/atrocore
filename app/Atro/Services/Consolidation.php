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

class Consolidation extends Base
{
    public function updateMasterRecord(Entity $contributor, ?Entity $master = null): bool
    {
        if ($master === null) {
            $master = $contributor->get('masterRecord');
        }

        if (empty($master) || !$this->getAcl()->check($master->getEntityName(), 'edit')) {
            throw new Forbidden();
        }

        $consolidation = $this->getRepository()->getByEntityName($master->getEntityName());
        if (empty($consolidation)) {
            throw new BadRequest("Consolidation for entity {$master->getEntityName()} not found.");
        }

        $consolidationScript = $consolidation->get('consolidationScript');
        if (empty($consolidationScript)) {
            throw new BadRequest($this->translate('consolidationScriptIsMissing', 'exceptions', 'Consolidation'));
        }

        $templateData = [
            'contributorRecord'  => $contributor,
            'masterRecord'       => $master,
            'contributorRecords' => $master->get("derived{$contributor->getEntityName()}Records", ['noCache' => true])
        ];

        $res = $this->getTwig()->renderTemplate($consolidationScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input['masterRecordData'])) {
            throw new BadRequest(sprintf($this->translate('consolidationScriptIsNotValid', 'exceptions', 'Consolidation'), $res));
        }

        if (!empty($input['skipped'])) {
            return false;
        }

        $this->executeAsMergeUser($consolidation, function () use ($input, $master) {
            try {
                $this->getRecordService($master->getEntityName())->updateEntity($master->get('id'), json_decode(json_encode($input['masterRecordData'])));
            } catch (NotModified) {
                // ignore
            }
        });

        return true;
    }

    public function createMasterRecord(Entity $contributor): ?Entity
    {
        $masterEntity = $this->getMetadata()->get(['scopes', $contributor->getEntityName(), 'primaryEntityId']);

        if (empty($masterEntity) || !$this->getAcl()->check($masterEntity, 'create')) {
            throw new Forbidden();
        }

        $consolidation = $this->getRepository()->getByEntityName($masterEntity);
        if (empty($consolidation)) {
            throw new BadRequest("Consolidation for entity {$masterEntity} not found.");
        }

        $consolidationScript = $consolidation->get('consolidationScript');
        if (empty($consolidationScript)) {
            throw new BadRequest($this->translate('consolidationScriptIsMissing', 'exceptions', 'Consolidation'));
        }

        $templateData = [
            'contributorRecord'  => $contributor,
            'masterRecord'       => null,
            'contributorRecords' => new EntityCollection([], $contributor->getEntityName())
        ];

        $res = $this->getTwig()->renderTemplate($consolidationScript, $templateData);
        $input = json_decode($res, true);

        if (!is_array($input) || empty($input['masterRecordData'])) {
            throw new BadRequest(sprintf($this->translate('consolidationScriptIsNotValid', 'exceptions', 'Consolidation'), $res));
        }

        if (!empty($input['skipped'])) {
            return null;
        }

        $id = null;

        $this->executeAsMergeUser($consolidation, function () use ($input, $masterEntity, &$id) {
            $id = $this->getRecordService($masterEntity)->createEntity(json_decode(json_encode($input['masterRecordData'])));
        });

        if (empty($id)) {
            return null;
        }

        return $this->getEntityManager()->getEntity($masterEntity, $id);
    }

    private function executeAsMergeUser(Entity $consolidation, $callback): void
    {
        if ($consolidation->get('executeMergeAs') === 'system') {
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
