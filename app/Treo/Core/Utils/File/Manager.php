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
 * Website: https://treolabs.com
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

declare(strict_types=1);

namespace Treo\Core\Utils\File;

use Espo\Core\Exceptions\Error;

/**
 * Class Manager
 *
 * @author r.ratsun <rr@atrocore.com>
 */
class Manager extends \Espo\Core\Utils\File\Manager
{
    /**
     * @inheritdoc
     */
    public function wrapForDataExport($content, $withObjects = false)
    {
        if (!isset($content) || !is_array($content)) {
            return false;
        }

        // prepare data
        $data = (!$withObjects) ? var_export($content, true) : $this->varExport($content);

        if ($data == '1' || $data == '0') {
            return false;
        }

        return "<?php\nreturn {$data};\n?>";
    }

    /**
     * @param $oldPath
     * @param $newPath
     * @param bool $removeEmptyDirs
     * @return bool
     * @throws Error
     */
    public function move($oldPath, $newPath, $removeEmptyDirs = true): bool
    {
        if (!file_exists($oldPath)) {
            throw new Error("File not found");
        }

        if ($this->checkCreateFile($newPath) === false) {
            throw new Error('Permission denied for ' . $newPath);
        }

        if (!rename($oldPath, $newPath)) {
            return false;
        }

        if ($removeEmptyDirs) {
            $this->removeEmptyDirs($oldPath);
        }

        return true;
    }

    /**
     * @param $contents
     * @return string
     */
    public function createOnTemp($contents): string
    {
        $tmpfile = tempnam("", uniqid());

        if ($tmpfile && file_put_contents($tmpfile, $contents) !== false) {
            return $tmpfile;
        }

        return '';
    }
}
