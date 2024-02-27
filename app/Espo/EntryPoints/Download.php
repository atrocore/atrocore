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

use Atro\Core\Download\Custom;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\Attachment;

class Download extends AbstractEntryPoint
{
    public static $authRequired = true;

    protected $fileTypesToShowInline = array(
        'application/pdf',
        'application/vnd.ms-word',
        'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'text/plain',
        'application/msword',
        'application/msexcel'
    );

    public function run()
    {
        if (!empty($_GET['type']) && $_GET['type'] === 'custom') {
            $this->custom();
        }

        if (empty($_GET['id'])) {
            throw new BadRequest();
        }
        $id = $_GET['id'];

        $attachment = $this->getEntityManager()->getEntity('Attachment', $id);

        if (!$attachment) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($attachment)) {
            throw new Forbidden();
        }

        $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);

        if (!file_exists($fileName)) {
            throw new NotFound();
        }

        header("Location: $fileName", true, 302);
        exit;
    }

    protected function custom(): void
    {
        $converter = $this
            ->getContainer()
            ->get(Custom::class)
            ->setAttachment($this->getAttachment())
            ->setParams($_GET)
            ->convert();

        header("Location: " . $converter->getFilePath(), true, 302);
        exit;
    }

    protected function getAttachment(): Attachment
    {
        if (empty($_GET['id'])) {
            throw new BadRequest();
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $_GET['id']);
        if (empty($attachment)) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($attachment)) {
            throw new Forbidden();
        }

        return $attachment;
    }
}

