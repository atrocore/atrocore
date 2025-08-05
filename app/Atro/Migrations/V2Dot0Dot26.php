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

class V2Dot0Dot26 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-04 11:00:00');
    }

    public function up(): void
    {
        $entityDefsPath = 'data/metadata/entityDefs';

        if (!file_exists($entityDefsPath)) {
            return;
        }

        foreach (array_diff(scandir($entityDefsPath), ['.', '..']) as $file) {
            $entity = explode('.', $file, 2)[0];

            if (!$this->isReferenceDataEntity($entity)) {
                continue;
            }

            $data = json_decode(@file_get_contents($entityDefsPath . '/' . $file), true);
            if (empty($data['fields']['code'])) {
                continue;
            }

            $data['fields']['code']['customizable'] = false;

            file_put_contents($entityDefsPath . '/' . $file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function isReferenceDataEntity(string $entityName): bool
    {
        $scopesPath = 'data/metadata/scopes';
        $referenceDataPath = 'data/reference-data';

        if (file_exists($referenceDataPath . '/' . $entityName . '.json')) {
            return true;
        }

        if (file_exists($scopesPath . '/' . $entityName . '.json')) {
            $data = json_decode(@file_get_contents($scopesPath . '/' . $entityName . '.json'), true);
            if (!empty($data['type']) && $data['type'] === 'ReferenceData') {
                return true;
            }
        }

        return false;
    }
}