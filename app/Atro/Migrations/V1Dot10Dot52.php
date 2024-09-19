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
use Atro\Core\Utils\Util;

class V1Dot10Dot52 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-01 15:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if (!$toSchema->hasTable('country')) {
            $table = $toSchema->createTable('country');
            $table->addColumn('id', 'string', ['length' => 24]);
            $table->addColumn('name', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('deleted', 'boolean', ['default' => false, 'notnull' => false]);
            $table->addColumn('code', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('is_active', 'boolean', ['default' => false]);
            $table->addColumn('eu', 'boolean', ['default' => false]);

            $table->addUniqueIndex(['code', 'deleted'], 'UNIQ_5373C96677153098EB3B4E33');

            $table->addIndex(['name', 'deleted'], 'IDX_COUNTRY_NAME');

            $table->setPrimaryKey(['id']);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }

            $qb = $this->getConnection()->createQueryBuilder();

            foreach ($this->getCountriesList() as $country) {
                if (isset($country['name'])) {
                    $qb
                        ->insert('country')
                        ->setValue('id', ':id')
                        ->setValue('name', ':name')
                        ->setValue('code', ':code')
                        ->setValue('deleted', ':false')
                        ->setValue('is_active', ':true')
                        ->setValue('eu', ':eu')
                        ->setParameter('id', !empty($country['code']) ? strtolower($country['code']) : Util::generateId())
                        ->setParameter('name', $country['name'])
                        ->setParameter('code', $country['code'] ?? null)
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('true', true, ParameterType::BOOLEAN)
                        ->setParameter('eu', $country['eu'] ?? false, ParameterType::BOOLEAN)
                        ->executeStatement();
                }
            }
        }

        if ($toSchema->hasTable('address')) {
            $table = $toSchema->getTable('address');

            if ($table->hasColumn('country')) {
                $table->dropColumn('country');
            }

            if ($table->hasColumn('country_code')) {
                $table->dropColumn('country_code');
            }

            if (!$table->hasColumn('country_id')) {
                $table->addColumn('country_id', 'string', ['length' => 24, 'notnull' => false]);
                $table->addIndex(['country_id', 'deleted'], 'IDX_ADDRESS_COUNTRY_ID');
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
        }

        $table = $toSchema->getTable('address_account');
        $field = $this->getConnection()->quoteIdentifier('default');
        if (!$table->hasColumn($field)) {
            $table->addColumn($field, 'boolean', ['default' => false]);

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->exec($sql);
            }
        }

        $this->updateComposer('atrocore/core', '^1.10.52');
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function getCountriesList(): array
    {
        return [
            ["code" => "AF", "name" => "Afghanistan", "eu" => false],
            ["code" => "AL", "name" => "Albania", "eu" => true],
            ["code" => "DZ", "name" => "Algeria", "eu" => false],
            ["code" => "AD", "name" => "Andorra", "eu" => true],
            ["code" => "AO", "name" => "Angola", "eu" => false],
            ["code" => "AG", "name" => "Antigua and Barbuda", "eu" => false],
            ["code" => "AR", "name" => "Argentina", "eu" => false],
            ["code" => "AM", "name" => "Armenia", "eu" => false],
            ["code" => "AU", "name" => "Australia", "eu" => false],
            ["code" => "AT", "name" => "Austria", "eu" => true],
            ["code" => "AZ", "name" => "Azerbaijan", "eu" => false],
            ["code" => "BS", "name" => "Bahamas", "eu" => false],
            ["code" => "BH", "name" => "Bahrain", "eu" => false],
            ["code" => "BD", "name" => "Bangladesh", "eu" => false],
            ["code" => "BB", "name" => "Barbados", "eu" => false],
            ["code" => "BY", "name" => "Belarus", "eu" => true],
            ["code" => "BE", "name" => "Belgium", "eu" => true],
            ["code" => "BZ", "name" => "Belize", "eu" => false],
            ["code" => "BJ", "name" => "Benin", "eu" => false],
            ["code" => "BT", "name" => "Bhutan", "eu" => false],
            ["code" => "BO", "name" => "Bolivia", "eu" => false],
            ["code" => "BA", "name" => "Bosnia and Herzegovina", "eu" => true],
            ["code" => "BW", "name" => "Botswana", "eu" => false],
            ["code" => "BR", "name" => "Brazil", "eu" => false],
            ["code" => "BN", "name" => "Brunei", "eu" => false],
            ["code" => "BG", "name" => "Bulgaria", "eu" => true],
            ["code" => "BF", "name" => "Burkina Faso", "eu" => false],
            ["code" => "BI", "name" => "Burundi", "eu" => false],
            ["code" => "CV", "name" => "Cabo Verde", "eu" => false],
            ["code" => "KH", "name" => "Cambodia", "eu" => false],
            ["code" => "CM", "name" => "Cameroon", "eu" => false],
            ["code" => "CA", "name" => "Canada", "eu" => false],
            ["code" => "CF", "name" => "Central African Republic", "eu" => false],
            ["code" => "TD", "name" => "Chad", "eu" => false],
            ["code" => "CL", "name" => "Chile", "eu" => false],
            ["code" => "CN", "name" => "China", "eu" => false],
            ["code" => "CO", "name" => "Colombia", "eu" => false],
            ["code" => "KM", "name" => "Comoros", "eu" => false],
            ["code" => "CG", "name" => "Congo", "eu" => false],
            ["code" => "CR", "name" => "Costa Rica", "eu" => false],
            ["code" => "HR", "name" => "Croatia", "eu" => true],
            ["code" => "CU", "name" => "Cuba", "eu" => false],
            ["code" => "CY", "name" => "Cyprus", "eu" => true],
            ["code" => "CZ", "name" => "Czech Republic", "eu" => true],
            ["code" => "CD", "name" => "Democratic Republic of the Congo", "eu" => false],
            ["code" => "DK", "name" => "Denmark", "eu" => true],
            ["code" => "DJ", "name" => "Djibouti", "eu" => false],
            ["code" => "DM", "name" => "Dominica", "eu" => false],
            ["code" => "DO", "name" => "Dominican Republic", "eu" => false],
            ["code" => "EC", "name" => "Ecuador", "eu" => false],
            ["code" => "EG", "name" => "Egypt", "eu" => false],
            ["code" => "SV", "name" => "El Salvador", "eu" => false],
            ["code" => "GQ", "name" => "Equatorial Guinea", "eu" => false],
            ["code" => "ER", "name" => "Eritrea", "eu" => false],
            ["code" => "EE", "name" => "Estonia", "eu" => true],
            ["code" => "SZ", "name" => "Eswatini", "eu" => false],
            ["code" => "ET", "name" => "Ethiopia", "eu" => false],
            ["code" => "FJ", "name" => "Fiji", "eu" => false],
            ["code" => "FI", "name" => "Finland", "eu" => true],
            ["code" => "FR", "name" => "France", "eu" => true],
            ["code" => "GA", "name" => "Gabon", "eu" => false],
            ["code" => "GM", "name" => "Gambia", "eu" => false],
            ["code" => "GE", "name" => "Georgia", "eu" => true],
            ["code" => "DE", "name" => "Germany", "eu" => true],
            ["code" => "GH", "name" => "Ghana", "eu" => false],
            ["code" => "GR", "name" => "Greece", "eu" => true],
            ["code" => "GD", "name" => "Grenada", "eu" => false],
            ["code" => "GT", "name" => "Guatemala", "eu" => false],
            ["code" => "GN", "name" => "Guinea", "eu" => false],
            ["code" => "GW", "name" => "Guinea-Bissau", "eu" => false],
            ["code" => "GY", "name" => "Guyana", "eu" => false],
            ["code" => "HT", "name" => "Haiti", "eu" => false],
            ["code" => "HN", "name" => "Honduras", "eu" => false],
            ["code" => "HU", "name" => "Hungary", "eu" => true],
            ["code" => "IS", "name" => "Iceland", "eu" => true],
            ["code" => "IN", "name" => "India", "eu" => false],
            ["code" => "ID", "name" => "Indonesia", "eu" => false],
            ["code" => "IR", "name" => "Iran", "eu" => false],
            ["code" => "IQ", "name" => "Iraq", "eu" => false],
            ["code" => "IE", "name" => "Ireland", "eu" => true],
            ["code" => "IL", "name" => "Israel", "eu" => false],
            ["code" => "IT", "name" => "Italy", "eu" => true],
            ["code" => "CI", "name" => "Ivory Coast", "eu" => false],
            ["code" => "JM", "name" => "Jamaica", "eu" => false],
            ["code" => "JP", "name" => "Japan", "eu" => false],
            ["code" => "JO", "name" => "Jordan", "eu" => false],
            ["code" => "KZ", "name" => "Kazakhstan", "eu" => false],
            ["code" => "KE", "name" => "Kenya", "eu" => false],
            ["code" => "KI", "name" => "Kiribati", "eu" => false],
            ["code" => "KW", "name" => "Kuwait", "eu" => false],
            ["code" => "KG", "name" => "Kyrgyzstan", "eu" => false],
            ["code" => "LA", "name" => "Laos", "eu" => false],
            ["code" => "LV", "name" => "Latvia", "eu" => true],
            ["code" => "LB", "name" => "Lebanon", "eu" => false],
            ["code" => "LS", "name" => "Lesotho", "eu" => false],
            ["code" => "LR", "name" => "Liberia", "eu" => false],
            ["code" => "LY", "name" => "Libya", "eu" => false],
            ["code" => "LI", "name" => "Liechtenstein", "eu" => true],
            ["code" => "LT", "name" => "Lithuania", "eu" => true],
            ["code" => "LU", "name" => "Luxembourg", "eu" => true],
            ["code" => "MG", "name" => "Madagascar", "eu" => false],
            ["code" => "MW", "name" => "Malawi", "eu" => false],
            ["code" => "MY", "name" => "Malaysia", "eu" => false],
            ["code" => "MV", "name" => "Maldives", "eu" => false],
            ["code" => "ML", "name" => "Mali", "eu" => false],
            ["code" => "MT", "name" => "Malta", "eu" => true],
            ["code" => "MH", "name" => "Marshall Islands", "eu" => false],
            ["code" => "MR", "name" => "Mauritania", "eu" => false],
            ["code" => "MU", "name" => "Mauritius", "eu" => false],
            ["code" => "MX", "name" => "Mexico", "eu" => false],
            ["code" => "FM", "name" => "Micronesia", "eu" => false],
            ["code" => "MD", "name" => "Moldova", "eu" => true],
            ["code" => "MC", "name" => "Monaco", "eu" => true],
            ["code" => "MN", "name" => "Mongolia", "eu" => false],
            ["code" => "ME", "name" => "Montenegro", "eu" => true],
            ["code" => "MA", "name" => "Morocco", "eu" => false],
            ["code" => "MZ", "name" => "Mozambique", "eu" => false],
            ["code" => "MM", "name" => "Myanmar", "eu" => false],
            ["code" => "NA", "name" => "Namibia", "eu" => false],
            ["code" => "NR", "name" => "Nauru", "eu" => false],
            ["code" => "NP", "name" => "Nepal", "eu" => false],
            ["code" => "NL", "name" => "Netherlands", "eu" => true],
            ["code" => "NZ", "name" => "New Zealand", "eu" => false],
            ["code" => "NI", "name" => "Nicaragua", "eu" => false],
            ["code" => "NE", "name" => "Niger", "eu" => false],
            ["code" => "NG", "name" => "Nigeria", "eu" => false],
            ["code" => "KP", "name" => "North Korea", "eu" => false],
            ["code" => "MK", "name" => "North Macedonia", "eu" => true],
            ["code" => "NO", "name" => "Norway", "eu" => true],
            ["code" => "OM", "name" => "Oman", "eu" => false],
            ["code" => "PK", "name" => "Pakistan", "eu" => false],
            ["code" => "PW", "name" => "Palau", "eu" => false],
            ["code" => "PS", "name" => "Palestine", "eu" => false],
            ["code" => "PA", "name" => "Panama", "eu" => false],
            ["code" => "PG", "name" => "Papua New Guinea", "eu" => false],
            ["code" => "PY", "name" => "Paraguay", "eu" => false],
            ["code" => "PE", "name" => "Peru", "eu" => false],
            ["code" => "PH", "name" => "Philippines", "eu" => false],
            ["code" => "PL", "name" => "Poland", "eu" => true],
            ["code" => "PT", "name" => "Portugal", "eu" => true],
            ["code" => "QA", "name" => "Qatar", "eu" => false],
            ["code" => "RO", "name" => "Romania", "eu" => true],
            ["code" => "RU", "name" => "Russia", "eu" => false],
            ["code" => "RW", "name" => "Rwanda", "eu" => false],
            ["code" => "KN", "name" => "Saint Kitts and Nevis", "eu" => false],
            ["code" => "LC", "name" => "Saint Lucia", "eu" => false],
            ["code" => "VC", "name" => "Saint Vincent and the Grenadines", "eu" => false],
            ["code" => "WS", "name" => "Samoa", "eu" => false],
            ["code" => "SM", "name" => "San Marino", "eu" => true],
            ["code" => "ST", "name" => "Sao Tome and Principe", "eu" => false],
            ["code" => "SA", "name" => "Saudi Arabia", "eu" => false],
            ["code" => "SN", "name" => "Senegal", "eu" => false],
            ["code" => "RS", "name" => "Serbia", "eu" => true],
            ["code" => "SC", "name" => "Seychelles", "eu" => false],
            ["code" => "SL", "name" => "Sierra Leone", "eu" => false],
            ["code" => "SG", "name" => "Singapore", "eu" => false],
            ["code" => "SK", "name" => "Slovakia", "eu" => true],
            ["code" => "SI", "name" => "Slovenia", "eu" => true],
            ["code" => "SB", "name" => "Solomon Islands", "eu" => false],
            ["code" => "SO", "name" => "Somalia", "eu" => false],
            ["code" => "ZA", "name" => "South Africa", "eu" => false],
            ["code" => "KR", "name" => "South Korea", "eu" => false],
            ["code" => "SS", "name" => "South Sudan", "eu" => false],
            ["code" => "ES", "name" => "Spain", "eu" => true],
            ["code" => "LK", "name" => "Sri Lanka", "eu" => false],
            ["code" => "SD", "name" => "Sudan", "eu" => false],
            ["code" => "SR", "name" => "Suriname", "eu" => false],
            ["code" => "SE", "name" => "Sweden", "eu" => true],
            ["code" => "CH", "name" => "Switzerland", "eu" => true],
            ["code" => "SY", "name" => "Syria", "eu" => false],
            ["code" => "TJ", "name" => "Tajikistan", "eu" => false],
            ["code" => "TZ", "name" => "Tanzania", "eu" => false],
            ["code" => "TH", "name" => "Thailand", "eu" => false],
            ["code" => "TL", "name" => "Timor-Leste", "eu" => false],
            ["code" => "TG", "name" => "Togo", "eu" => false],
            ["code" => "TO", "name" => "Tonga", "eu" => false],
            ["code" => "TT", "name" => "Trinidad and Tobago", "eu" => false],
            ["code" => "TN", "name" => "Tunisia", "eu" => false],
            ["code" => "TR", "name" => "Turkey", "eu" => true],
            ["code" => "TM", "name" => "Turkmenistan", "eu" => false],
            ["code" => "TV", "name" => "Tuvalu", "eu" => false],
            ["code" => "UG", "name" => "Uganda", "eu" => false],
            ["code" => "UA", "name" => "Ukraine", "eu" => true],
            ["code" => "AE", "name" => "United Arab Emirates", "eu" => false],
            ["code" => "GB", "name" => "United Kingdom", "eu" => true],
            ["code" => "US", "name" => "United States of America", "eu" => false],
            ["code" => "UY", "name" => "Uruguay", "eu" => false],
            ["code" => "UZ", "name" => "Uzbekistan", "eu" => false],
            ["code" => "VU", "name" => "Vanuatu", "eu" => false],
            ["code" => "VE", "name" => "Venezuela", "eu" => false],
            ["code" => "VN", "name" => "Vietnam", "eu" => false],
            ["code" => "YE", "name" => "Yemen", "eu" => false],
            ["code" => "ZM", "name" => "Zambia", "eu" => false],
            ["code" => "ZW", "name" => "Zimbabwe", "eu" => false]
        ];
    }
}
