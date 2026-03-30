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

namespace Atro\Repositories;

use Espo\Core\AclManager;
use Espo\ORM\Entity;

class Team extends \Espo\Core\ORM\Repositories\RDB
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->isAttributeChanged('languageRestricted')) {
            if ($entity->get('languageRestricted')) {
                $this->createMainLanguageEntry($entity->get('id'));
            }
            $this->getAclManager()->clearAclCache();
        }
    }

    protected function createMainLanguageEntry(string $teamId): void
    {
        $mainLanguage = $this->getEntityManager()->getRepository('Language')
            ->where(['role' => 'main'])
            ->findOne();

        if (empty($mainLanguage)) {
            return;
        }

        $exists = $this->getEntityManager()->getRepository('TeamLanguage')
            ->where(['teamId' => $teamId, 'languageId' => $mainLanguage->get('id')])
            ->findOne();

        if (!empty($exists)) {
            return;
        }

        $entry = $this->getEntityManager()->getEntity('TeamLanguage');
        $entry->set('teamId', $teamId);
        $entry->set('languageId', $mainLanguage->get('id'));
        $entry->set('editAction', false);
        $this->getEntityManager()->saveEntity($entry);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
