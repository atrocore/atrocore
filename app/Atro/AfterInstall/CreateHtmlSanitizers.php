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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Espo\ORM\EntityManager;

/**
 * Creates the default HTML sanitizers.
 */
class CreateHtmlSanitizers implements AfterInstallInterface
{
    public function __construct(private readonly EntityManager $entityManager)
    {
    }

    public static function getSortOrder(): int
    {
        return 50;
    }

    public function run(): void
    {
        $sanitizers = [
            [
                'code'          => 'standard',
                'name'          => 'Standard',
                'configuration' => "allow_elements:
    ul: \"*\"
    li: \"*\"

drop_elements: ['img']

drop_attributes:
    style: \"*\"

block_elements: ['h1', 'h2', 'a', 'span', 'div', 'font']

max_input_length: 16000",
            ],
            [
                'id'            => 'flat_table',
                'code'          => 'flat_table',
                'name'          => 'Flat table',
                'configuration' => "allow_elements:
    table: \"*\"
    tr: \"*\"
    td: \"*\"
    th: \"*\"
    thead: \"*\"
    tbody: \"*\"
    tfoot: \"*\"

drop_attributes:
    style: \"*\"",
            ],
        ];

        foreach ($sanitizers as $data) {
            $sanitizer = $this->entityManager->getRepository('HtmlSanitizer')->get();
            $sanitizer->set($data);

            $this->entityManager->saveEntity($sanitizer);
        }
    }
}
