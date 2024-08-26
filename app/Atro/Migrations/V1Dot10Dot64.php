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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot64 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-26 15:00:00');
    }

    public function up(): void
    {
        $types = ['list', 'listSmall', 'detail', 'detailSmall', 'relationships', 'sidePanelsDetail', 'sidePanelsEdit', 'sidePanelsDetailSmall', 'sidePanelsEditSmall'];
        // Migrate layout from custom to database
        if (is_dir("data/layouts")) {
            foreach (scandir("data/layouts") as $dir) {
                if (in_array($dir, ['.', '..'])) {
                    continue;
                }

                foreach ($types as $type) {
                    $file = "data/layouts/$dir/$type.json";
                    if (file_exists($file)) {
                        $content = @json_decode(file_get_contents($file), true);
                        if (!empty($content)) {
                            $id = Util::generateId();
                            $this->getConnection()->createQueryBuilder()
                                ->insert('layout')
                                ->values([
                                    'id'     => ':id',
                                    'entity' => ':entity',
                                    'type'   => ':type'
                                ])
                                ->setParameter('id', $id)
                                ->setParameter('entity', $dir)
                                ->setParameter('type', $type)
                                ->executeStatement();

                            $functionName = "create" . ucfirst($type) . "LayoutContent";
                            if (method_exists($this, $functionName)) {
                                $this->$functionName($id, $content);
                            }

                        }
                    }
                }

            }
        }
    }

    public function createListLayoutContent($layoutId, $data)
    {
        foreach ($data as $index => $item) {
            try {
                $qb = $this->getConnection()->createQueryBuilder()
                    ->insert('layout_list_item')
                    ->values([
                        'id'         => ':id',
                        'name'       => ':name',
                        'sort_order' => ':sortOrder',
                        'layout_id'  => ':layoutId',
                    ]);

                if (!empty($item['link'])) {
                    $qb->setValue('link', ':link');
                }
                if (!empty($item['align'])) {
                    $qb->setValue('align', ':align');
                }
                if (!empty($item['width'])) {
                    $qb->setValue('width', ':width');
                }
                if (!empty($item['widthPx'])) {
                    $qb->setValue('width_px', ':widthPx');
                }


                $qb->setParameters(array_merge($item, ['id' => Util::generateId(), 'sortOrder' => $index, 'layoutId' => $layoutId]))
                    ->executeStatement();
            } catch (\Throwable $e) {
            }

        }
    }

    public function createListSmallLayoutContent($layoutId, $data)
    {
        $this->createListLayoutContent($layoutId, $data);
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
