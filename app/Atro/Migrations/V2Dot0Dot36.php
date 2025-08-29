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
use Doctrine\DBAL\ParameterType;

class V2Dot0Dot36 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-25 10:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE file_type ADD min_size INT DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD max_size INT DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD aspect_ratio VARCHAR(255) DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD min_width INT DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD min_height INT DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD extensions TEXT DEFAULT NULL;");
            $this->exec("ALTER TABLE file_type ADD mime_types TEXT DEFAULT NULL;");
            $this->exec("COMMENT ON COLUMN file_type.extensions IS '(DC2Type:jsonArray)';");
            $this->exec("COMMENT ON COLUMN file_type.mime_types IS '(DC2Type:jsonArray)'");
            $this->exec("ALTER TABLE file_type DROP assign_automatically;");
            $this->exec("ALTER TABLE file_type DROP priority;");
        } else {
            $this->exec("ALTER TABLE file_type ADD min_size INT DEFAULT NULL, ADD max_size INT DEFAULT NULL, ADD aspect_ratio VARCHAR(255) DEFAULT NULL, ADD min_width INT DEFAULT NULL, ADD min_height INT DEFAULT NULL, ADD color_depth LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', ADD color_space LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', ADD extensions LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', ADD mime_types LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)', DROP assign_automatically, DROP priority;");
        }

        $types = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from('file_type')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($types as $type) {
            $validationRules = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('validation_rule')
                ->where('file_type_id = :id')
                ->setParameter('id', $type['id'])
                ->andWhere('deleted = :false')
                ->andWhere('is_active = :true')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $qb = $this->getConnection()->createQueryBuilder()
                ->update('file_type');

            foreach ($validationRules as $rule) {
                switch ($rule['type']) {
                    case "Color Depth":
                    case "Color Space":
                    case "Extension":
                        $qb->set('extensions', ":extensions")
                            ->setParameter('extensions', $rule['extension']);
                        break;
                    case "Mime":
                        $value = $rule['mime_list'];
                        if ($rule['validate_by'] !== 'List' && !empty($rule['pattern'])) {
                            $value = json_encode(['/' . $rule['pattern'] . '/i']);
                        }
                        $qb->set('mime_type', ":mimeTypes")
                            ->setParameter('mimeTypes', $value);
                        break;
                    case "PDF Validation":
                    case "Quality":
                    case "Ratio":
                        $value = null;
                        if (abs($rule['ratio'] - 1.33) <= 0.1) {
                            $value = '4:3';
                        } else if (abs($rule['ratio'] - 1.77) <= 0.1) {
                            $value = '16:9';
                        } else if (!empty($rule['ratio'])) {
                            $value = '1:1';
                        }
                        if (!empty($value)) {
                            $qb->set('aspect_ratio', ":value")
                                ->setParameter('value', $value);
                        }
                        break;
                    case "Scale":
                        $qb->set('min_width', ":minWidth")
                            ->set('min_height', ":minHeight")
                            ->setParameter('minWidth', $rule['min_width'])
                            ->setParameter('minHeight', $rule['min_height']);
                        break;
                    case "Size":
                        $qb->set('min_size', ":minSize")
                            ->set('max_size', ":maxSize")
                            ->setParameter('minSize', $rule['min'])
                            ->setParameter('maxSize', $rule['max']);
                        break;
                }
            }


            if (!empty($qb->getparameters())) {
                $qb->where('id = :id')
                    ->setParameter('id', $type['id'])
                    ->executeStatement();
            }
        }
    }


    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
