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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;

class V2Dot3Dot10 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-06-18 17:00:00');
    }

    public function up(): void
    {
        $metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');

        foreach ($metadata->get('scopes') ?? [] as $scope => $scopeDefs) {
            if (empty($scopeDefs['primaryEntityId'])) {
                continue;
            }

            $primaryEntity = $scopeDefs['primaryEntityId'];

            $entityDefs = $metadata->get("entityDefs.$primaryEntity");

            if (empty($entityDefs['fields'])) {
                continue;
            }

            foreach ($entityDefs['fields'] as $fieldName => $fieldDefs) {
                if (empty($fieldDefs['type'])) {
                    continue;
                }

                if ($fieldDefs['type'] === 'linkMultiple') {
                    $linkDefs = $entityDefs['links'][$fieldName] ?? null;

                    if (!empty($linkDefs['relationName'])) {
                        if ($linkDefs['relationName'] !== "{$linkDefs['entity']}Hierarchy") {
                            $newName = $scope . $linkDefs['entity'];

                            $i = 2;
                            while (!empty($data['entityDefs'][$newName])) {
                                $newName = $newName . $i;
                                $i++;
                            }

                            $oldName = Util::toUnderScore('derivativeMiddle_' . md5("{$linkDefs['relationName']}_$scope"));
                            $newName = Util::toUnderScore($newName);

                            $this->exec("ALTER TABLE $oldName RENAME TO $newName");
                        }
                    }
                }
            }
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}