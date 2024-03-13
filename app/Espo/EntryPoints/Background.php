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

use Espo\Core\Utils\Util;

class Background extends AbstractEntryPoint
{
    public static $authRequired = false;

    public static function setBackground(): void
    {
        $imagesPath = 'client/img/background';
        if (!file_exists($imagesPath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        session_start();
        if (!isset($_SESSION['background']) || $_SESSION['background']['till'] < new \DateTime() || !file_exists($_SESSION['background']['imagePath'])) {
            $images = Util::scanDir($imagesPath);

            $_SESSION['background']['till'] = (new \DateTime())->modify('+2 hours');
            $_SESSION['background']['imageName'] = $images[array_rand($images)];
            $_SESSION['background']['imagePath'] = $imagesPath . '/' . $_SESSION['background']['imageName'];

            $imageMetadata = \exif_read_data($_SESSION['background']['imagePath']);

            $_SESSION['background']['authorName'] = isset($imageMetadata['Artist']) ? $imageMetadata['Artist'] : '';
            $_SESSION['background']['authorLink'] = isset($imageMetadata['COMPUTED']['UserComment']) ? $imageMetadata['COMPUTED']['UserComment'] : '';
        }
    }

    public function run()
    {
        self::setBackground();

        $content = file_get_contents($_SESSION['background']['imagePath']);

        header('Content-Disposition:inline;filename="' . $_SESSION['background']['imageName'] . '"');
        header('Content-Type: ' . mime_content_type($_SESSION['background']['imagePath']));
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        header('Content-Length: ' . mb_strlen($content, "8bit"));

        echo $content;
        exit;
    }
}
