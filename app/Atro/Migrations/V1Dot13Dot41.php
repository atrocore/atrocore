<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot13Dot41 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-24 12:00:00');
    }

    public function up(): void
    {
        if (!is_dir(ReferenceData::DIR_PATH)) {
            @mkdir(ReferenceData::DIR_PATH);
        }

        $filePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'HtmlSanitizer.json';

        $data = self::getDefaultHtmlSanitizer();

        if (file_exists($filePath)) {
            $fileData = @json_decode(file_get_contents($filePath), true);

            if (empty($fileData)) {
                $fileData = [];
            }

            $fileData[$data['id']] = $data;
        } else {
            $fileData = [$data['id'] =>  $data];
        }

        file_put_contents($filePath, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function getDefaultHtmlSanitizer(): array
    {
        return [
            "id"            => 'flat_table',
            "code"          => "flat_table",
            "name"          => "Flat table",
            "configuration" => "allow_elements:
    table: \"*\"
    tr: \"*\"
    td: \"*\"
    th: \"*\"
    thead: \"*\"
    tbody: \"*\"
    tfoot: \"*\"
    
drop_attributes:
    style: \"*\""
        ];
    }
}
