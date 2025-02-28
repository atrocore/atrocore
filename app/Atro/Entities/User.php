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

    public function isSystem()
    {
        return $this->id === 'system';
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
}
