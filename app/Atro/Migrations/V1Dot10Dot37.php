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

class V1Dot10Dot37 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-02 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_note_super_parent");
            $this->exec("DROP INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT");
            $this->exec("ALTER TABLE note DROP super_parent_id");
            $this->exec("ALTER TABLE note DROP super_parent_type");
            $this->exec("CREATE INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note (parent_id, parent_type)");

            $this->exec("DROP INDEX idx_note_number");
            $this->exec("ALTER TABLE note DROP number");
        } else {
            $this->exec("DROP INDEX IDX_NOTE_SUPER_PARENT ON note");
            $this->exec("DROP INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note");
            $this->exec("ALTER TABLE note DROP super_parent_id, DROP super_parent_type");
            $this->exec("CREATE INDEX IDX_NOTE_PARENT_AND_SUPER_PARENT ON note (parent_id, parent_type)");

            $this->exec("DROP INDEX IDX_NOTE_NUMBER ON note");
            $this->exec("ALTER TABLE note DROP number");
        }

        $this->exec("ALTER TABLE note DROP is_internal");
        $this->exec("ALTER TABLE note DROP target_type");

        while (true) {
            $notes = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('note')
                ->where('type=:type')
                ->andWhere('deleted=:false')
                ->andWhere('post IS NOT NULL')
                ->setFirstResult(0)
                ->setMaxResults(30000)
                ->setParameter('type', 'Post')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            if (empty($notes)) {
                break;
            }

            foreach ($notes as $note) {
                $data = @json_decode($note['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }
                $data['post'] = $note['post'];
                $note['data'] = json_encode($data);
                $this->getConnection()->createQueryBuilder()
                    ->update('note')
                    ->set('data', ':data')
                    ->set('post', ':null')
                    ->where('id=:id')
                    ->setParameter('id', $note['id'])
                    ->setParameter('data', $note['data'])
                    ->setParameter('null', null, ParameterType::NULL)
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE note DROP post");

        while (true) {
            $notes = $this->getConnection()->createQueryBuilder()
                ->select('*')
                ->from('note')
                ->where('related_type IS NOT NULL')
                ->andWhere('deleted=:false')
                ->setFirstResult(0)
                ->setMaxResults(30000)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            if (empty($notes)) {
                break;
            }

            foreach ($notes as $note) {
                $data = @json_decode($note['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }

                $data['relatedType'] = $note['related_type'];
                $data['relatedId'] = $note['related_id'];

                $note['data'] = json_encode($data);
                $this->getConnection()->createQueryBuilder()
                    ->update('note')
                    ->set('data', ':data')
                    ->set('related_type', ':null')
                    ->set('related_id', ':null')
                    ->where('id=:id')
                    ->setParameter('id', $note['id'])
                    ->setParameter('data', $note['data'])
                    ->setParameter('null', null, ParameterType::NULL)
                    ->executeQuery();
            }
        }

        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_note_related");
            $this->exec("DROP SEQUENCE autofollow_id_seq CASCADE");
        } else {
            $this->exec("DROP INDEX IDX_NOTE_RELATED ON note");
        }

        $this->exec("DROP TABLE autofollow");

        $this->exec("ALTER TABLE note DROP related_id");
        $this->exec("ALTER TABLE note DROP related_type");
        $this->exec("ALTER TABLE note DROP is_global");

        $this->exec("DROP TABLE note_team");
        $this->exec("DROP TABLE note_user");

        while (true) {
            try {
                $notes = $this->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from('note')
                    ->where('pav_id IS NOT NULL')
                    ->andWhere('deleted=:false')
                    ->setFirstResult(0)
                    ->setMaxResults(30000)
                    ->setParameter('false', false, ParameterType::BOOLEAN)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $notes = [];
            }

            if (empty($notes)) {
                break;
            }

            foreach ($notes as $note) {
                $data = @json_decode($note['data'], true);
                if (!is_array($data)) {
                    $data = [];
                }

                $data['attributeId'] = $note['attribute_id'];
                $data['pavId'] = $note['pav_id'];

                $note['data'] = json_encode($data);
                $this->getConnection()->createQueryBuilder()
                    ->update('note')
                    ->set('data', ':data')
                    ->set('pav_id', ':null')
                    ->set('attribute_id', ':null')
                    ->where('id=:id')
                    ->setParameter('id', $note['id'])
                    ->setParameter('data', $note['data'])
                    ->setParameter('null', null, ParameterType::NULL)
                    ->executeQuery();
            }
        }

        $this->exec("ALTER TABLE note DROP attribute_id");
        $this->exec("ALTER TABLE note DROP pav_id");

        $this->getConnection()->createQueryBuilder()
            ->delete('note')
            ->where('type IN (:types)')
            ->setParameter('types', ['Create', 'Assign', 'Own'], $this->getConnection()::PARAM_STR_ARRAY)
            ->executeQuery();

        $this->updateComposer('atrocore/core', '^1.10.37');
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
