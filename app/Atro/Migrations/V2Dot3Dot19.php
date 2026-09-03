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

class V2Dot3Dot19 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-09-03 10:00:00');
    }

    public function up(): void
    {
        try {
            $matchings = $this->getDbal()->createQueryBuilder()
                ->select('id, code, entity')
                ->from('matching')
                ->orderBy('created_at', 'ASC')
                ->fetchAllAssociative();
        } catch (\Throwable) {
            $matchings = [];
        }

        $this->exec("ALTER TABLE matching ADD number INT DEFAULT NULL");

        $number = 1;
        foreach ($matchings as $matching) {
            $this->getDbal()->createQueryBuilder()
                ->update('matching')
                ->set('number', ':number')
                ->where('id = :id')
                ->setParameter('number', $number)
                ->setParameter('id', $matching['id'])
                ->executeQuery();

            if (!empty($matching['code']) && !empty($matching['entity'])) {
                $this->renameMatchingColumn($matching['code'], $matching['entity'], $number);
            }

            $number++;
        }

        if ($this->isPgSQL()) {
            $this->exec("CREATE SEQUENCE matching_number_seq INCREMENT BY 1 MINVALUE 1 START {$number}");
            $this->exec("ALTER TABLE matching ALTER COLUMN number SET DEFAULT nextval('matching_number_seq')");
            $this->exec("ALTER TABLE matching ALTER COLUMN number SET NOT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_MATCHING_NUMBER ON matching (number)");
        } else {
            $this->exec("ALTER TABLE matching MODIFY number INT NOT NULL");
            $this->exec("CREATE UNIQUE INDEX UNIQ_MATCHING_NUMBER ON matching (number)");
            $this->exec("ALTER TABLE matching MODIFY number INT AUTO_INCREMENT NOT NULL");
            $this->exec("ALTER TABLE matching AUTO_INCREMENT = {$number}");
        }

        $this->exec("DROP INDEX UNIQ_DC10F28977153098EB3B4E33 ON matching");
        $this->exec("DROP INDEX uniq_dc10f28977153098eb3b4e33 ON matching");
        $this->exec("ALTER TABLE matching DROP name");
        $this->exec("ALTER TABLE matching DROP code");
    }

    /**
     * Renames the dynamic "matched" tracking column on the target entity table from the old
     * code-based name (e.g. "product_family_d2d") to the new number-based one (e.g. "matching5"),
     * preserving the already-collected matching data.
     */
    protected function renameMatchingColumn(string $code, string $entity, int $number): void
    {
        $parts = explode('-', $code);
        if (count($parts) < 2) {
            return;
        }

        $oldFieldName = ucfirst($parts[0]) . ucfirst(strtolower($parts[1]));
        $oldColumn = Util::toUnderScore($oldFieldName);
        $newColumn = Util::toUnderScore('matching' . $number);
        $tableName = Util::toUnderScore(lcfirst($entity));

        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " RENAME COLUMN {$oldColumn} TO {$newColumn}");
        } else {
            $this->exec("ALTER TABLE " . $this->getDbal()->quoteIdentifier($tableName) . " CHANGE {$oldColumn} {$newColumn} DATETIME DEFAULT NULL");
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable) {
        }
    }
}