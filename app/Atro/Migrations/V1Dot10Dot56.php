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

class V1Dot10Dot56 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-08 15:00:00');
    }

    public function up(): void
    {
        // Update Templates
        foreach (V1Dot10Dot50::getDefaultRules() as $rule) {
            if (!empty($rule['templates'])) {
                $templates = $rule['templates'];
                foreach ($templates as $type => $template) {
                    try {
                        $this->getConnection()->createQueryBuilder()
                            ->update('notification_template')
                            ->set('data', ':data')
                            ->set('name', ':name')
                            ->where('id = :id')
                            ->setParameter('id', $template['id'])
                            ->setParameter('name', $template['name'])
                            ->setParameter('data', json_encode($template['data']))
                            ->executeStatement();
                    } catch (\Throwable $e) {

                    }
                }

            }
        }

        $this->updateComposer('atrocore/core', '^1.10.53');
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
