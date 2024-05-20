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

use Atro\Core\Exceptions\Error;
use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot17 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-05-21 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE storage ADD folder_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_STORAGE_FOLDER_ID ON storage (folder_id, deleted)");

        try {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('folder_storage')
                ->where('deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $records = [];
        }

        foreach ($records as $record) {
            $this->getConnection()->createQueryBuilder()
                ->update('storage')
                ->set('folder_id', ':folderId')
                ->where('id=:storageId')
                ->setParameter('folderId', $record['folder_id'])
                ->setParameter('storageId', $record['storage_id'])
                ->executeQuery();
        }

        $this->exec("DROP TABLE folder_storage");

        $this->updateComposer('atrocore/core', '^1.10.17');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
