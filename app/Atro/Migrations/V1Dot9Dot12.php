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

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

class V1Dot9Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-01 15:00:00');
    }

    public function up(): void
    {
        $limit = 2000;
        $offset = 0;

        $this->execute("drop table if exists address_account;");
        $this->execute("drop table if exists address_contact;");

        if ($this->isPgSQL()) {
            $this->execute("CREATE TABLE address_account (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, account_id VARCHAR(24) DEFAULT NULL, address_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->execute("CREATE UNIQUE INDEX IDX_ADDRESS_ACCOUNT_UNIQUE_RELATION ON address_account (deleted, account_id, address_id);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_CREATED_BY_ID ON address_account (created_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_MODIFIED_BY_ID ON address_account (modified_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_ACCOUNT_ID ON address_account (account_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_ADDRESS_ID ON address_account (address_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_CREATED_AT ON address_account (created_at, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_ACCOUNT_MODIFIED_AT ON address_account (modified_at, deleted);");
            $this->execute("CREATE TABLE address_contact (id VARCHAR(24) NOT NULL, deleted BOOLEAN DEFAULT 'false', created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, address_id VARCHAR(24) DEFAULT NULL, contact_id VARCHAR(24) DEFAULT NULL, PRIMARY KEY(id));");
            $this->execute("CREATE UNIQUE INDEX IDX_ADDRESS_CONTACT_UNIQUE_RELATION ON address_contact (deleted, address_id, contact_id);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_CREATED_BY_ID ON address_contact (created_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_MODIFIED_BY_ID ON address_contact (modified_by_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_ADDRESS_ID ON address_contact (address_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_CONTACT_ID ON address_contact (contact_id, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_CREATED_AT ON address_contact (created_at, deleted);");
            $this->execute("CREATE INDEX IDX_ADDRESS_CONTACT_MODIFIED_AT ON address_contact (modified_at, deleted);");

            // create records in address_account
            while (true) {

                $entities = $this->getConnection()->createQueryBuilder()
                    ->from('address')
                    ->select('id', 'account_id')
                    ->where("account_id is not null")
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();

                if (empty($entities)) {
                    break;
                }
                $offset = $offset + $limit;

                foreach ($entities as $entity) {
                    $this->getConnection()->createQueryBuilder()
                        ->insert('address_account')
                        ->values([
                            [
                                'account_id' => '?',
                                'address_id' => '?'
                            ]
                        ])
                        ->setParameter(0, $entity['account_id'])
                        ->setParameter(1, $entity['id'])
                        ->executeStatement();
                }
            }

            $this->execute("DROP INDEX idx_address_account_id;");
            $this->execute("ALTER TABLE address DROP account_id;");

            // create records in address_contact
            $offset = 0;
            while (true) {

                $entities = $this->getConnection()->createQueryBuilder()
                    ->from('contact')
                    ->select('*')
                    ->where("street is not null or zip is not null or city is not null or country is not null or country_code is not null")
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();

                if (empty($entities)) {
                    break;
                }
                $offset = $offset + $limit;

                foreach ($entities as $entity) {
                    try {
                        $id = Util::generateId();
                        $this->getConnection()->createQueryBuilder()
                            ->insert('address')
                            ->values([
                                [
                                    'id'           => '?',
                                    'name'         => '?',
                                    'street'       => '?',
                                    'zip'          => '?',
                                    'city'         => '?',
                                    'country'      => '?',
                                    'country_code' => '?',
                                    'phone'        => '?',
                                    'email'        => '?',
                                    'type'         => '?'
                                ]
                            ])
                            ->setParameter(0, $id)
                            ->setParameter(1, "Address for contact: " . (empty($entity['name']) ? $entity['id'] : $entity['name']))
                            ->setParameter(2, $entity['street'])
                            ->setParameter(3, $entity['zip'])
                            ->setParameter(4, $entity['city'])
                            ->setParameter(5, $entity['country'])
                            ->setParameter(6, $entity['country_code'])
                            ->setParameter(7, $entity['phone'])
                            ->setParameter(8, $entity['email'])
                            ->setParameter(9, "billing")
                            ->executeStatement();

                        $this->getConnection()->createQueryBuilder()
                            ->insert('address_contact')
                            ->values([
                                [
                                    'contact_id' => '?',
                                    'address_id' => '?'
                                ]
                            ])
                            ->setParameter(0, $entity['id'])
                            ->setParameter(1, $id)
                            ->executeStatement();
                    } catch (\Exception $e) {

                    }
                }
            }

            $this->execute("ALTER TABLE contact DROP street;");
            $this->execute("ALTER TABLE contact DROP zip;");
            $this->execute("ALTER TABLE contact DROP city;");
            $this->execute("ALTER TABLE contact DROP country;");
            $this->execute("ALTER TABLE contact DROP country_code");

            // remove all uniques values


            $this->execute("CREATE UNIQUE INDEX IDX_ADDRESS_UNIQUE ON address (deleted, phone, email, type, street, zip, box, city, country, country_code);");
        } else {

        }

    }

    public function down(): void
    {
        throw new Error("Downgrade prohibited");
    }

    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {

        }
    }
}
