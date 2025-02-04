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

class V1Dot13Dot11 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-04 12:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            // DROP SEQUENCE user_followed_record_id_seq CASCADE;
            //DROP INDEX idx_user_followed_record_entity;
            //DROP INDEX IDX_USER_FOLLOWED_RECORD_USER_ID;
            //ALTER TABLE user_followed_record ADD deleted BOOLEAN DEFAULT 'false';
            //ALTER TABLE user_followed_record ALTER id TYPE VARCHAR(36);
            //ALTER TABLE user_followed_record ALTER id DROP DEFAULT;
            //CREATE INDEX IDX_USER_FOLLOWED_RECORD_USER_ID ON user_followed_record (user_id, deleted)
        }else{

        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
