<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\EntryPoints;

use Atro\EntryPoints\AbstractEntryPoint;
use Espo\Core\Exceptions\NotFound;

class Sharing extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        if (empty($_GET['id'])) {
            throw new NotFound();
        }

        $sharing = $this->getEntityManager()->getRepository('Sharing')->get($_GET['id']);
        if (empty($sharing)) {
            throw new NotFound();
        }

        if (empty($sharing->get('active'))) {
            throw new NotFound();
        }

        if (!empty($sharing->get('validTill')) && $sharing->get('validTill') < (new \DateTime())->format('Y-m-d H:i:s')) {
            throw new NotFound();
        }

        if (!empty($sharing->get('allowedUsage'))) {
            $used = (int)$sharing->get('used');
            if ($used >= $sharing->get('allowedUsage')) {
                throw new NotFound();
            }
            $sharing->set('used', $used + 1);
            $this->getEntityManager()->saveEntity($sharing);
        }

        $entity = $this->getEntityManager()->getRepository($sharing->get('entityType'))->get($sharing->get('entityId'));
        if (empty($entity)) {
            throw new NotFound();
        }

        switch ($sharing->get('type')) {
            case 'download':
                if ($entity->getEntityType() === 'Asset') {
                    $attachment = $entity->get('file');
                    $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
                    if (!file_exists($fileName)) {
                        throw new NotFound();
                    }

                    header($_SERVER["SERVER_PROTOCOL"] . " 200 OK");
                    header("Cache-Control: public");
                    header('Content-Type: ' . $attachment->get('type'));
                    header("Content-Transfer-Encoding: Binary");
                    header('Content-Length: ' . filesize($fileName));
                    header("Content-Disposition: attachment; filename={$attachment->get('name')}");
                    readfile($fileName);
                    exit;
                }
                break;
        }

        throw new NotFound();
    }
}
