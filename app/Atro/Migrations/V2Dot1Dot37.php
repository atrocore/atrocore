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
use Atro\Core\Utils\Metadata;

class V2Dot1Dot37 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-12-09 18:00:00');
    }

    public function up(): void
    {
        /** @var Metadata $metadata */
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
        $save = false;

        foreach ($metadata->get('entityDefs') ?? [] as $scope => $entityDefs) {
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if ($fieldDefs['type'] === 'url' && empty($fieldDefs['urlLabel'])) {
                    $metadata->set('entityDefs', $scope, [
                        'fields' => [
                            $field => [
                                'urlLabel' => 'noLabel'
                            ],
                        ],
                    ]);
                    $save = true;
                }
            }
        }

        if ($save) {
            $metadata->save();
        }
    }
}
