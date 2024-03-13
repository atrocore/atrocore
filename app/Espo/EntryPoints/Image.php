<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Entities\Attachment;

/**
 * Class Image
 */
class Image extends AbstractEntryPoint
{
    public const TYPES = ['image/jpeg', 'image/png', 'image/gif'];

    /**
     * @throws BadRequest
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    public function run()
    {
        if (empty($_GET['id'])) {
            throw new BadRequest();
        }

        $this->show($_GET['id'], $_GET['size'] ?? null);
    }

    /**
     * @param string      $id
     * @param string|null $size
     *
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     */
    protected function show($id, $size)
    {
        /** @var Attachment $attachment */
        $attachment = $this->getEntityManager()->getEntity("Attachment", $id);
        if (empty($attachment)) {
            throw new NotFound();
        }

        if (!$this->checkAttachment($attachment)) {
            throw new Forbidden();
        }

        $filePath = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
        if (!file_exists($filePath)) {
            throw new NotFound();
        }

        $fileType = $attachment->get('type');
        if (!in_array($fileType, self::TYPES)) {
            throw new Error();
        }

        if (!empty($size)) {
            if (empty($this->getImageSize($size))) {
                throw new NotFound();
            }

            $filePath = $attachment->getThumbPath($size);
            if (!file_exists($filePath)) {
                $this->getContainer()->get('thumbnail')->createThumbnail($attachment, $size);
            }
        }

        $content = file_get_contents($filePath);

        header('Content-Disposition:inline;filename="' . $attachment->get('name') . '"');
        if (!empty($fileType)) {
            header('Content-Type: ' . $fileType);
        }
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        $fileSize = mb_strlen($content, "8bit");
        if ($fileSize) {
            header('Content-Length: ' . $fileSize);
        }
        echo $content;
        exit;
    }

    /**
     * @param Attachment $attachment
     *
     * @return bool
     */
    protected function checkAttachment(Attachment $attachment): bool
    {
        return $this->getAcl()->checkEntity($attachment);
    }

    /**
     * @param string $size
     *
     * @return array|null
     */
    protected function getImageSize(string $size): ?array
    {
        return $this->getMetadata()->get(['app', 'imageSizes', $size], null);
    }
}
