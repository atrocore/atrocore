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

declare(strict_types = 1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot12Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-12 11:00:00');
    }

    public function up(): void
    {
        @mkdir(ReferenceData::DIR_PATH);

        $filePath = ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'HtmlSanitizer.json';

        if (file_exists($filePath)) {
            $fileData = @json_decode(file_get_contents($filePath), true);

            if (empty($fileData)) {
                $fileData = [];
            }

            $fileData[] = self::getDefaultHtmlSanitizer();
        } else {
            $fileData = [self::getDefaultHtmlSanitizer()];
        }

        file_put_contents($filePath, json_encode($fileData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function down(): void
    {
    }

    public static function getDefaultHtmlSanitizer(): array
    {
        return [
            "id"            => Util::generateUniqueHash(),
            "code"          => "standard",
            "name"          => "Standard",
            "configuration" => "allow_elements:
    ul: \"*\"
    li: \"*\"
            
drop_elements: ['img']
            
drop_attributes:
    style: \"*\"
            
block_elements: ['h1', 'h2', 'a', 'span', 'div', 'font']
            
max_input_length: 16000"
        ];
    }
}
