<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Console;

class ScanFiles extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Scan files. The scanner will automatically add or delete records from the File entity.';
    }

    public function run(array $data): void
    {
        $this->getContainer()->get('fileSystemStorage')->scan($this->getConfig()->get('filesPath', 'upload/files'));

        self::show('Files has benn scanned successfully.', self::SUCCESS);
    }
}
