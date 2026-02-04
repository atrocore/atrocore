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

namespace Atro\Seeders;

use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\IdGenerator;
use Atro\Core\Utils\Util;

class HtmlSanitizerSeeder extends AbstractSeeder
{
    public function run(): void
    {
        if (file_exists(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'HtmlSanitizer.json')) {
            return;
        }

        @mkdir(ReferenceData::DIR_PATH);

        $standardSanitizer = $this->getStandardSanitizer();
        $tableOnlySanitizer = $this->getTableOnlySanitizer();

        @file_put_contents(ReferenceData::DIR_PATH . DIRECTORY_SEPARATOR . 'HtmlSanitizer.json', json_encode(
            array_merge([$standardSanitizer['id'] => $standardSanitizer], [$tableOnlySanitizer['id'] => $tableOnlySanitizer])
        ));
    }

    private function getStandardSanitizer(): array
    {
        return [
            "id"            => IdGenerator::unsortableId(),
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

    private function getTableOnlySanitizer(): array
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