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

namespace Atro\Console;

use Atro\Core\Utils\Util;

class SafeUuidPkMigration extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Converts primary key columns to UUID for all tables where it is safe to do so.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled')) || $this->getConfig()->get('database.driver') !== 'pdo_pgsql') {
            exit(1);
        }

        $nonUuidTables = $this->getConfig()->get('nonUuidTables') ?? [];

        /** @var \PDO $pdo */
        $pdo = $this->getContainer()->get('pdo');

        /** @var \Espo\Core\Utils\Metadata\OrmMetadata $ormMetadata */
        $ormMetadata = $this->getContainer()->get('ormMetadata');

        foreach ($ormMetadata->getData() as $entityName => $entityParams) {
            $tableName = Util::toUnderScore(lcfirst($entityName));
            if (in_array($tableName, $nonUuidTables)) {
                continue;
            }

            try {
                $sth = $pdo->prepare("SELECT data_type FROM information_schema.columns WHERE table_name = :table AND column_name = 'id'");
                $sth->execute([
                    'table' => $tableName,
                ]);

                $res = $sth->fetch(\PDO::FETCH_ASSOC);

                if ($res['data_type'] === 'uuid') {
                    continue;
                }

                $pdo->exec("ALTER TABLE $tableName ALTER COLUMN id TYPE UUID USING id::uuid");

                self::show("Table '{$tableName}' has been converted successfully.", self::SUCCESS);
            } catch (\PDOException $e) {
                $nonUuidTables[] = $tableName;

                self::show("Table '{$tableName}' hasn't been converted.", self::ERROR);
            }
        }

        $this->getConfig()->set('nonUuidTables', $nonUuidTables);
        $this->getConfig()->save();

        self::show('Done!', self::SUCCESS);
    }
}
