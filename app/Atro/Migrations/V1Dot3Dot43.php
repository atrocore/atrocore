<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot3Dot43 extends Base
{
    public function up(): void
    {
        $this->execute("DROP TABLE account_contact");
        $this->execute("DROP TABLE contact");
        $this->execute("DROP INDEX IDX_CONTACT_ID ON `user`");
        $this->execute("ALTER TABLE `user` DROP contact_id");
        $this->execute("ALTER TABLE `account` DROP website, DROP type, DROP industry, DROP sic_code, DROP billing_address_street, DROP billing_address_city, DROP billing_address_state, DROP billing_address_country, DROP billing_address_postal_code, DROP shipping_address_street, DROP shipping_address_city, DROP shipping_address_state, DROP shipping_address_country, DROP shipping_address_postal_code, ADD email_address VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD phone_number VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("ALTER TABLE `user` ADD email_address VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");

        $data = $this
            ->getPDO()
            ->query("SELECT ea.name as email, eea.entity_id as user_id FROM `entity_email_address` eea JOIN email_address ea ON eea.email_address_id = ea.id AND ea.deleted=0 WHERE eea.deleted=0 AND eea.entity_type='User'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $v) {
            $this->execute("UPDATE `user` SET email_address='{$v['email']}' WHERE id='{$v['user_id']}'");
        }

        $this->execute("DROP TABLE email_address");
        $this->execute("DROP TABLE entity_email_address");

        $this->execute("ALTER TABLE `user` ADD phone_number VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");

        $data = $this
            ->getPDO()
            ->query("SELECT pn.name as phone, epn.entity_id as user_id FROM `entity_phone_number` epn JOIN phone_number pn ON epn.phone_number_id = pn.id AND pn.deleted=0 WHERE epn.deleted=0 AND epn.entity_type='User'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($data as $v) {
            $this->execute("UPDATE `user` SET phone_number='{$v['phone']}' WHERE id='{$v['user_id']}'");
        }

        $this->execute("DROP TABLE entity_phone_number");
        $this->execute("DROP TABLE phone_number");
    }

    public function down(): void
    {
        $this->execute("CREATE TABLE `contact` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `salutation_name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `first_name` VARCHAR(100) DEFAULT '' COLLATE utf8mb4_unicode_ci, `last_name` VARCHAR(100) DEFAULT '' COLLATE utf8mb4_unicode_ci, `account_id` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `description` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `do_not_call` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, `address_street` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `address_city` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `address_state` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `address_country` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `address_postal_code` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `assigned_user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_ACCOUNT_ID` (account_id), INDEX `IDX_CREATED_BY_ID` (created_by_id), INDEX `IDX_MODIFIED_BY_ID` (modified_by_id), INDEX `IDX_ASSIGNED_USER_ID` (assigned_user_id), INDEX `IDX_CREATED_AT` (created_at, deleted), INDEX `IDX_FIRST_NAME` (first_name, deleted), INDEX `IDX_NAME` (first_name, last_name), INDEX `IDX_ASSIGNED_USER` (assigned_user_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("CREATE TABLE `account_contact` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `account_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `contact_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `role` VARCHAR(100) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `is_inactive` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_8549F2709B6B5FBA` (account_id), INDEX `IDX_8549F270E7A1254A` (contact_id), UNIQUE INDEX `UNIQ_8549F2709B6B5FBAE7A1254A` (account_id, contact_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `user` ADD contact_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_CONTACT_ID ON `user` (contact_id)");
        $this->execute("ALTER TABLE `account` DROP email_address, DROP phone_number, ADD website VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD type VARCHAR(255) DEFAULT '' COLLATE utf8mb4_unicode_ci, ADD industry VARCHAR(255) DEFAULT '' COLLATE utf8mb4_unicode_ci, ADD sic_code VARCHAR(40) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD billing_address_street VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD billing_address_city VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD billing_address_state VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD billing_address_country VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD billing_address_postal_code VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD shipping_address_street VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD shipping_address_city VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD shipping_address_state VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD shipping_address_country VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD shipping_address_postal_code VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE TABLE `email_address` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `lower` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `invalid` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, `opt_out` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_LOWER` (lower), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("CREATE TABLE `entity_email_address` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `entity_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `email_address_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `entity_type` VARCHAR(100) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `primary` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_9125AB4281257D5D` (entity_id), INDEX `IDX_9125AB4259045DAA` (email_address_id), UNIQUE INDEX `UNIQ_9125AB4281257D5D59045DAAC412EE02` (entity_id, email_address_id, entity_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `user` DROP email_address");
        $this->execute("CREATE TABLE `phone_number` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `name` VARCHAR(36) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `type` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `numeric` VARCHAR(36) DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_NAME` (name), INDEX `IDX_NUMERIC` (`numeric`), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("CREATE TABLE `entity_phone_number` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `entity_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `phone_number_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `entity_type` VARCHAR(100) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `primary` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_7459056F81257D5D` (entity_id), INDEX `IDX_7459056F39DFD528` (phone_number_id), UNIQUE INDEX `UNIQ_7459056F81257D5D39DFD528C412EE02` (entity_id, phone_number_id, entity_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("ALTER TABLE `user` DROP phone_number");
    }

    protected function execute(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
