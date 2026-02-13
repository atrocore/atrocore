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

class V2Dot2Dot21 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-13 10:00:00');
    }

    public function up(): void
    {
        $this->getDbal()->createQueryBuilder()
            ->update($this->getDbal()->quoteIdentifier('user'))
            ->set('type', ':type')
            ->where('user_name = :userName')
            ->setParameter('userName', 'system')
            ->setParameter('type', 'System')
            ->executeQuery();

        $systemUser = $this->getDbal()->createQueryBuilder()
            ->select('*')
            ->from($this->getDbal()->quoteIdentifier('user'))
            ->where('user_name = :userName')
            ->setParameter('userName', 'system')
            ->fetchAssociative();

        if (!empty($systemUser)) {
            $this->getConfig()->set('systemUserId', $systemUser['id']);
            $this->getConfig()->save();
        }

        if ($this->isPgSQL()) {
            $this->exec('CREATE UNIQUE INDEX UNIQ_8D93D64924A232CFEB3B4E33 ON "user" (user_name, deleted)');

            $this->exec('ALTER TABLE "user" ADD actor_id VARCHAR(36) DEFAULT NULL');
            $this->exec('ALTER TABLE "user" ADD delegator_id VARCHAR(36) DEFAULT NULL');
            $this->exec('ALTER TABLE "user" ADD CONSTRAINT FK_USER_ACTOR_ID FOREIGN KEY (actor_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->exec('ALTER TABLE "user" ADD CONSTRAINT FK_USER_DELEGATOR_ID FOREIGN KEY (delegator_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            $this->exec('CREATE UNIQUE INDEX IDX_USER_UNIQUE_ACTOR ON "user" (deleted, actor_id, delegator_id)');
            $this->exec('CREATE INDEX IDX_USER_ACTOR_ID ON "user" (actor_id, deleted)');
            $this->exec('CREATE INDEX IDX_USER_DELEGATOR_ID ON "user" (delegator_id, deleted)');
            $this->exec('CREATE INDEX FK_USER_ACTOR_ID ON "user" (actor_id)');
            $this->exec('CREATE INDEX FK_USER_DELEGATOR_ID ON "user" (delegator_id)');
        } else {
            $this->exec('CREATE UNIQUE INDEX UNIQ_8D93D64924A232CFEB3B4E33 ON user (user_name, deleted)');

            $this->exec('ALTER TABLE user ADD actor_id VARCHAR(36) DEFAULT NULL, ADD delegator_id VARCHAR(36) DEFAULT NULL');
            $this->exec('ALTER TABLE user ADD CONSTRAINT FK_USER_ACTOR_ID FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE CASCADE');
            $this->exec('ALTER TABLE user ADD CONSTRAINT FK_USER_DELEGATOR_ID FOREIGN KEY (delegator_id) REFERENCES user (id) ON DELETE CASCADE');
            $this->exec('CREATE UNIQUE INDEX IDX_USER_UNIQUE_ACTOR ON user (deleted, actor_id, delegator_id)');
            $this->exec('CREATE INDEX IDX_USER_ACTOR_ID ON user (actor_id, deleted)');
            $this->exec('CREATE INDEX IDX_USER_DELEGATOR_ID ON user (delegator_id, deleted)');
            $this->exec('CREATE INDEX FK_USER_ACTOR_ID ON user (actor_id)');
            $this->exec('CREATE INDEX FK_USER_DELEGATOR_ID ON user (delegator_id)');
        }

        $this->getDbal()->createQueryBuilder()
            ->update($this->getDbal()->quoteIdentifier('user'))
            ->set('actor_id', 'id')
            ->set('delegator_id', 'id')
            ->executeQuery();
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
