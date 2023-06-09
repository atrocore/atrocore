<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Treo\Core\Migration;

use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Espo\Core\Utils\Database\Schema\Schema;
use Espo\Core\Utils\Util;
use PDO;
use Espo\Core\Utils\Config;

class Base
{
    private ?Schema $schema;

    private Config $config;

    private ?PDO $pdo;

    public function __construct(?PDO $pdo, Config $config, ?Schema $schema)
    {
        $this->schema = $schema;
        $this->config = $config;
        $this->pdo = $pdo;
    }

    public function up(): void
    {
    }

    public function down(): void
    {
    }

    protected function getSchema(): Schema
    {
        return $this->schema;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function migrateSchema(DoctrineSchema $fromSchema, DoctrineSchema $toSchema): void
    {
        foreach ($this->getSchema()->getMigrateToSql($fromSchema, $toSchema) as $sql) {
            $this->getSchema()->getConnection()->executeQuery($sql);
        }
    }

    protected function getDbFieldParams(array $params): array
    {
        return $this->getSchema()->getSchemaConverter()->getDbFieldParams($params);
    }

    protected function getPDO(): PDO
    {
        return $this->pdo;
    }

    protected function rebuildByCronJob()
    {
        $id = Util::generateId();
        $executeTime = (new \DateTime())->format('Y-m-d H:i:s');

        $this->getPDO()->exec("INSERT INTO job (id, execute_time, created_at, method_name, service_name) VALUES ('$id', '$executeTime', '$executeTime', 'rebuild', 'App')");
    }

    protected function updateComposer(string $package, string $version): void
    {
        foreach (['composer.json', 'data/stable-composer.json'] as $filename) {
            if (!file_exists($filename)) {
                continue;
            }
            $data = json_decode(file_get_contents($filename), true);
            $data['require'] = array_merge($data['require'], [$package => $version]);
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }
}
