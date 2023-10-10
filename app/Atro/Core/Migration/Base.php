<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\Core\Migration;

use Doctrine\DBAL\Schema\Schema as DoctrineSchema;
use Atro\Core\Utils\Database\Schema\Schema;
use Espo\Services\App;
use PDO;
use Espo\Core\Utils\Config;

class Base
{
    private ?Schema $schema;

    private Config $config;

    private PDO $pdo;

    public function __construct(PDO $pdo, Config $config, ?Schema $schema)
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
        App::createRebuildJob($this->getPDO());
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
