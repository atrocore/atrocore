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

class V2Dot5Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-06 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->getPDO()->exec("CREATE TABLE IF NOT EXISTS rate_limit_hit (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', route VARCHAR(190) DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_name VARCHAR(255) DEFAULT NULL, request_time DOUBLE PRECISION DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))");
            $this->getPDO()->exec("CREATE INDEX IF NOT EXISTS IDX_RATE_LIMIT_HIT_ROUTE_METHOD_IP_REQUEST_TIME ON rate_limit_hit (route, method, ip_address, request_time)");
            $this->getPDO()->exec("CREATE INDEX IF NOT EXISTS IDX_RATE_LIMIT_HIT_ROUTE_METHOD_USER_NAME_REQUEST_TIME ON rate_limit_hit (route, method, user_name, request_time)");
        } else {
            $this->getPDO()->exec("CREATE TABLE IF NOT EXISTS rate_limit_hit (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', route VARCHAR(190) DEFAULT NULL, method VARCHAR(10) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_name VARCHAR(255) DEFAULT NULL, request_time DOUBLE PRECISION DEFAULT NULL, created_at DATETIME DEFAULT NULL, INDEX IDX_RATE_LIMIT_HIT_ROUTE_METHOD_IP_REQUEST_TIME (route, method, ip_address, request_time), INDEX IDX_RATE_LIMIT_HIT_ROUTE_METHOD_USER_NAME_REQUEST_TIME (route, method, user_name, request_time), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
        }
    }
}
