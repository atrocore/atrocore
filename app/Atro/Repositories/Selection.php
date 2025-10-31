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

use Atro\Core\Exceptions\Error;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;


class Selection extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isNew() && empty($entity->get('name'))) {

            $user = $this->getEntityManager()->getUser();

            if (!empty($user)) {

                $locale = $user?->getLocale();
                $date = (new \DateTime());

                if (!empty($locale) && !empty($this->getConfig()->get('locale'))) {
                    $locale = $this->getEntityManager()->getEntity('Locale', $locale);
                }

                if (!empty($locale) && !empty($locale->get('timeZone'))) {
                    $date->setTimezone(new \DateTimeZone($locale->get('timeZone')));
                }

                $entity->set('name', $date->format('Y-m-d H:i') . ' By ' . $user->get('name'));
            }
        }

        parent::beforeSave($entity, $options);
    }
}
