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

class V2Dot0Dot13 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-17 15:00:00');
    }

    public function up(): void
    {
        $this->createDefaultAttributePanel();

        // create Attribute table
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function createDefaultAttributePanel(): void
    {
        @mkdir('data/reference-data');

        $result = [];
        if (file_exists('data/reference-data/AttributePanel.json')) {
            $result = @json_decode(file_get_contents('data/reference-data/AttributePanel.json'), true);
            if (!is_array($result)) {
                $result = [];
            }
        }

        $result['attributeValues'] = [
            'id'        => 'attributeValues',
            'code'      => 'attributeValues',
            'name'      => 'Attributes',
            'sortOrder' => 0,
            'entityId'  => 'Product',
            'default'   => true
        ];

        file_put_contents('data/reference-data/AttributePanel.json', json_encode($result));
    }
}
