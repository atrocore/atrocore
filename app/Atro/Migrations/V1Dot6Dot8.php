<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot6Dot8 extends Base
{
    public function up(): void
    {
        $path = 'custom/Espo/Custom/Resources/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                $filePath = "$path/$file";
                if (!is_file($filePath)) {
                    continue;
                }

                $contents = file_get_contents($filePath);

                if (strpos($contents, '"measureId"') !== false) {
                    $data = json_decode($contents, true);
                    if (empty($data['fields'])) {
                        continue;
                    }

                    $hasChanges = false;
                    foreach ($data['fields'] as $field => $fieldDefs) {
                        if (empty($fieldDefs['measureId']) || !empty($fieldDefs['measureName'])) {
                            continue;
                        }

                        $measure = $this->getPDO()
                            ->query("SELECT * FROM measure WHERE name=" . $this->getPDO()->quote($fieldDefs['measureId']) . " AND deleted=0")
                            ->fetch(\PDO::FETCH_ASSOC);

                        if (empty($measure)) {
                            continue;
                        }

                        $data['fields'][$field]['measureId'] = $measure['id'];
                        $data['fields'][$field]['measureName'] = $measure['name'];

                        $hasChanges = true;
                    }
                    if ($hasChanges) {
                        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
                    }
                }
            }
        }

        $this->updateComposer('atrocore/core', '^1.6.8');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }
}
