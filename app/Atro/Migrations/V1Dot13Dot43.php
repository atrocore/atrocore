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
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\Core\Utils\Util;

class V1Dot13Dot43 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-24 14:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->execute("DROP INDEX idx_layout_layout_profile;");
            $this->execute("DROP INDEX idx_user_entity_layout_unique;");
        } else {
            $this->execute("DROP INDEX IDX_LAYOUT_LAYOUT_PROFILE ON layout;");
            $this->execute("DROP INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout;");
        }

        $this->execute("ALTER TABLE user_entity_layout ADD hash VARCHAR(255) DEFAULT NULL;");
        $this->execute("ALTER TABLE layout ADD hash VARCHAR(255) DEFAULT NULL;");

        // calculate hash
        foreach (['layout', 'user_entity_layout'] as $table) {
            $limit = 2000;
            $offset = 0;
            while (true) {
                $records = $this->getConnection()->createQueryBuilder()
                    ->from($table)
                    ->select('*')
                    ->where('hash is null')
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();

                if (empty($records)) {
                    break;
                }
                $offset = $offset + $limit;

                foreach ($records as $record) {
                    $this->getConnection()->createQueryBuilder()
                        ->update($table)
                        ->set('hash', ':hash')
                        ->where('id = :id')
                        ->setParameter('id', $record['id'])
                        ->setParameter('hash', $this->generateHash($record, $table === 'user_entity_layout'))
                        ->executeStatement();
                }
            }

            // remove all values with same hash

            $duplicates = $this->getConnection()
                ->createQueryBuilder()
                ->from($table)
                ->select("hash")
                ->groupBy("hash")
                ->having('count(*)>1')
                ->fetchAllAssociative();


            foreach ($duplicates as $duplicate) {
                $records = $this->getConnection()
                    ->createQueryBuilder()
                    ->from($table)
                    ->select("*")
                    ->where('hash=:hash')
                    ->setParameter("hash", $duplicate["hash"])
                    ->fetchAllAssociative();

                foreach ($records as $index => $record) {
                    if ($index === 0) {
                        continue;
                    }
                    $this->getConnection()
                        ->createQueryBuilder()
                        ->delete($table)
                        ->where('id = :id')
                        ->setParameter('id', $record['id'])
                        ->executeStatement();
                }
            }
        }

        if ($this->isPgSQL()) {
            $this->execute('CREATE UNIQUE INDEX IDX_LAYOUT_UNIQUE ON layout (hash, deleted);');
            $this->execute('CREATE UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout (hash, deleted);');
        } else {
            $this->execute('CREATE UNIQUE INDEX IDX_LAYOUT_UNIQUE ON layout (hash, deleted);');
            $this->execute('CREATE UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout (hash, deleted)');
        }
    }

    protected function generateHash($record, $forUser = false): string
    {
        $fields = [
            "layout_profile_id",
            "entity",
            "related_entity",
            "related_link",
            "view_type"
        ];
        if ($forUser) {
            $fields[] = "user_id";
        }
        $text = join("\n", array_map(function ($field) use ($record) {
            return empty($record[$field]) ? "" : $record[$field];
        }, $fields));
        return md5('atrocore_salt' . $text);
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            $e; // ignore all
        }
    }

}
