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

namespace Atro\Entities;

use Atro\Core\Utils\Language;
use Espo\ORM\Entity;

class User extends \Espo\Core\ORM\Entity
{
    public function isAdmin()
    {
        return $this->get('isAdmin');
    }

    public function isGlobalSystemUser(): bool
    {
        return $this->get('userName') === 'system';
    }

    public function isSystemUser(): bool
    {
        return $this->get('type') === 'System';
    }

    public function _getActor(): User
    {
        if ($this->id === $this->get('actorId')) {
            return $this;
        }

        return $this->getEntityManager()->getRepository('User')->get($this->get('actorId'));
    }

    public function _getDelegator(): User
    {
        if ($this->id === $this->get('delegatorId')) {
            return $this;
        }

        return $this->getEntityManager()->getRepository('User')->get($this->get('delegatorId'));
    }

    public function getSystemUser(): User
    {
        return $this->getEntityManager()->getRepository('User')->getSystemUser($this);
    }

    public function isActive()
    {
        return $this->get('isActive');
    }

    public function getTeamIdList()
    {
        if (!$this->has('teamsIds')) {
            $this->loadLinkMultipleField('teams');
        }
        return $this->get('teamsIds');
    }

    public function needToUpdatePassword(int $expireDays): bool
    {
        $updatedAt = \DateTime::createFromFormat('Y-m-d H:i:s',
            $this->get('passwordUpdatedAt') ?: $this->get('createdAt'));
        $now = (new \DateTime())->setTime(0, 0);
        return $expireDays > 0 && $updatedAt->diff($now)->days >= abs($expireDays);
    }

    public function getLocale(): ?Entity
    {
        if (empty($this->get('localeId'))) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Locale')->get($this->get('localeId'));
    }

    public function getStyle(): ?Entity
    {
        if (empty($this->get('styleId'))) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Style')->get($this->get('styleId'));
    }

    public function getLanguage(): string
    {
        if (empty($locale = $this->getLocale())) {
            return Language::DEFAULT_LANGUAGE;
        }

        return $locale->get('languageCode');
    }

    public function getTeamsUsersIds(array $teamsIds): array
    {
        if (empty($teamsIds)) {
            return [];
        }

        $collection = $this->getEntityManager()->getRepository('TeamUser')->select(['userId'])->where(['teamId' => $teamsIds])->find();
        return array_column($collection->toArray(), 'userId');
    }
}
