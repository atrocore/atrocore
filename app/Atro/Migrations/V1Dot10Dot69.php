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
use Atro\Core\Utils\Util;

class V1Dot10Dot69 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-12 10:00:00');
    }

    public function up(): void
    {
        $defaultId = 'default';

        $types = ['detail', 'detailSmall'];
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
                            try {
                                $layout = $this->getConnection()->createQueryBuilder()
                                    ->select('*')
                                    ->from('layout')
                                    ->where('layout_profile_id = :defaultId')->andWhere('entity = :entity')
                                    ->andWhere('view_type = :type')
                                    ->setParameter('entity', $dir)
                                    ->setParameter('type', $type)
                                    ->setParameter('defaultId', $defaultId)
                                    ->fetchAssociative();

                                if (!empty($layout)) {
                                    foreach ($content as $index => $item) {
                                        $this->getConnection()->createQueryBuilder()
                                            ->update('layout_section')
                                            ->set('name', ':name')
                                            ->where('name = :empty')
                                            ->andWhere('layout_id = :id')
                                            ->andWhere('sort_order = :order')
                                            ->setParameter('id', $layout['id'])
                                            ->setParameter('order', $index)
                                            ->setParameter('empty', '')
                                            ->setParameter('name', $item['customLabel'] ?? ($item['label'] ?? ''))
                                            ->executeStatement();
                                    }
                                }
                            } catch (\Throwable $exception) {
                            }

                        }
                    }
                }

            }
        }
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
