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
        } else {
            $this->execute("CREATE TABLE address_account (id VARCHAR(24) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, account_id VARCHAR(24) DEFAULT NULL, address_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_ADDRESS_ACCOUNT_UNIQUE_RELATION (deleted, account_id, address_id), INDEX IDX_ADDRESS_ACCOUNT_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ADDRESS_ACCOUNT_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ADDRESS_ACCOUNT_ACCOUNT_ID (account_id, deleted), INDEX IDX_ADDRESS_ACCOUNT_ADDRESS_ID (address_id, deleted), INDEX IDX_ADDRESS_ACCOUNT_CREATED_AT (created_at, deleted), INDEX IDX_ADDRESS_ACCOUNT_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
            $this->execute("CREATE TABLE address_contact (id VARCHAR(24) NOT NULL, deleted TINYINT(1) DEFAULT '0', created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, created_by_id VARCHAR(24) DEFAULT NULL, modified_by_id VARCHAR(24) DEFAULT NULL, address_id VARCHAR(24) DEFAULT NULL, contact_id VARCHAR(24) DEFAULT NULL, UNIQUE INDEX IDX_ADDRESS_CONTACT_UNIQUE_RELATION (deleted, address_id, contact_id), INDEX IDX_ADDRESS_CONTACT_CREATED_BY_ID (created_by_id, deleted), INDEX IDX_ADDRESS_CONTACT_MODIFIED_BY_ID (modified_by_id, deleted), INDEX IDX_ADDRESS_CONTACT_ADDRESS_ID (address_id, deleted), INDEX IDX_ADDRESS_CONTACT_CONTACT_ID (contact_id, deleted), INDEX IDX_ADDRESS_CONTACT_CREATED_AT (created_at, deleted), INDEX IDX_ADDRESS_CONTACT_MODIFIED_AT (modified_at, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB;");
        }
        // create records in address_account
        while (true) {
            try {
                $entities = $this->getConnection()->createQueryBuilder()
                    ->from('address')
                    ->select('id', 'account_id')
                    ->where("account_id is not null")
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $entities = [];
            }

            if (empty($entities)) {
                break;
            }
            $offset = $offset + $limit;

            foreach ($entities as $entity) {
                try {
                    $this->getConnection()->createQueryBuilder()
                        ->insert('address_account')
                        ->values(
                            [
                                'account_id' => '?',
                                'address_id' => '?',
                                'id'         => '?'
                            ]
                        )
                        ->setParameter(0, $entity['account_id'])
                        ->setParameter(1, $entity['id'])
                        ->setParameter(2, Util::generateId())
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }

        if ($this->isPgSQL()) {
            $this->execute("DROP INDEX idx_address_account_id;");
            $this->execute("ALTER TABLE address DROP account_id;");
        } else {
            $this->execute("DROP INDEX IDX_ADDRESS_ACCOUNT_ID ON address;");
            $this->execute("ALTER TABLE address DROP account_id;");
        }
        // create records in address_contact
        $offset = 0;
        while (true) {

            try {
                $entities = $this->getConnection()->createQueryBuilder()
                    ->from('contact')
                    ->select('*')
                    ->where("street is not null or zip is not null or city is not null or country is not null or country_code is not null")
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $entities = [];
            }

            if (empty($entities)) {
                break;
            }
            $offset = $offset + $limit;

            foreach ($entities as $entity) {
                try {
                    $id = Util::generateId();
                    $this->getConnection()->createQueryBuilder()
                        ->insert('address')
                        ->values(
                            [
                                'id'           => '?',
                                'contact_name' => '?',
                                'street'       => '?',
                                'zip'          => '?',
                                'city'         => '?',
                                'country'      => '?',
                                'country_code' => '?',
                                'phone'        => '?',
                                'email'        => '?',
                                'type'         => '?'
                            ]
                        )
                        ->setParameter(0, $id)
                        ->setParameter(1, empty($entity['name']) ? '' : $entity['name'])
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
                        ->values(
                            [
                                'contact_id' => '?',
                                'address_id' => '?',
                                'id'         => '?'
                            ]
                        )
                        ->setParameter(0, $entity['id'])
                        ->setParameter(1, $id)
                        ->setParameter(2, Util::generateId())
                        ->executeStatement();
                } catch (\Exception $e) {

                }
            }
        }

        if ($this->isPgSQL()) {
            $this->execute("ALTER TABLE contact DROP street;");
            $this->execute("ALTER TABLE contact DROP zip;");
            $this->execute("ALTER TABLE contact DROP city;");
            $this->execute("ALTER TABLE contact DROP country;");
            $this->execute("ALTER TABLE contact DROP country_code");
        } else {
            $this->execute("ALTER TABLE contact DROP street, DROP zip, DROP city, DROP country, DROP country_code");
        }


        $this->execute("ALTER TABLE address ADD hash VARCHAR(255) DEFAULT NULL;");

        // update hash
        $offset = 0;
        while (true) {
            try {
                $entities = $this->getConnection()->createQueryBuilder()
                    ->from('address')
                    ->select('*')
                    ->setMaxResults($limit)
                    ->setFirstResult($offset)
                    ->fetchAllAssociative();
            } catch (\Throwable $e) {
                $entities = [];
            }

            if (empty($entities)) {
                break;
            }
            $offset = $offset + $limit;

            foreach ($entities as $entity) {
                try {
                    $this->getConnection()->createQueryBuilder()
                        ->update('address')
                        ->set('hash', ':hash')
                        ->where('id = :id')
                        ->setParameter('id', $entity['id'])
                        ->setParameter('hash', $this->generateHash($entity))
                        ->executeStatement();
                } catch (\Exception $e) {

                }
            }
        }

        // remove all values with same hash
        try {
            $duplicates = $this->getConnection()
                ->createQueryBuilder()
                ->from('address')
                ->select("hash")
                ->groupBy("hash")
                ->having('count(*)>1')
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            $duplicates = [];
        }

        foreach ($duplicates as $duplicate) {
            $records = $this->getConnection()
                ->createQueryBuilder()
                ->from('address')
                ->select("*")
                ->where('hash=:hash')
                ->setParameter("hash", $duplicate["hash"])
                ->fetchAllAssociative();

            $index = 1;
            foreach ($records as $record) {
                $street = $this->generateStreetName($record, $index);
                $this->getConnection()
                    ->createQueryBuilder()
                    ->update('address')
                    ->set('street', ':street')
                    ->set('hash', ':hash')
                    ->where('id = :id')
                    ->setParameter('street', $street)
                    ->setParameter('hash', $this->generateHash(array_merge($record, ['street' => $street])))
                    ->setParameter('id', $record['id'])
                    ->executeStatement();
            }
        }

        $this->execute("CREATE UNIQUE INDEX IDX_ADDRESS_UNIQUE ON address (deleted, hash);");

        $this->updateComposer('atrocore/core', '^1.9.12');
    }

    protected function generateStreetName($record, &$index)
    {
        $street = (empty($record['street']) ? "" : $record['street']) . "($index)";
        $index++;

        $address = $this->getConnection()
            ->createQueryBuilder()
            ->from('address')
            ->select("id")
            ->where('hash=:hash')
            ->setParameter("hash", $this->generateHash(array_merge($record, ['street' => $street])))
            ->fetchOne();

        if (!empty($address)) {
            $street = $this->generateStreetName($record, $index);
        }

        return $street;
    }

    protected function generateHash($record)
    {
        $fields = ["phone", "email", "type", "street", "zip", "box", "city", "country", "country_code"];
        $text = join("\n", array_map(function ($field) use ($record) {
            return empty($record[$field]) ? "" : $record[$field];
        }, $fields));
        return md5('atrocore_salt' . $text);
    }


    public function down(): void
    {
        throw new Error("Downgrade prohibited");
    }

    protected
    function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {

        }
    }
}
