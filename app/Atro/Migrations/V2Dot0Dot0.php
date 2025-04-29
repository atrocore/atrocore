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

class V2Dot0Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-04-23 17:00:00');
    }

    public function up(): void
    {
        if($this->isPgSQL()) {
            $this->exec("ALTER TABLE saved_search ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD created_by_id VARCHAR(36) DEFAULT NULL");
            $this->exec("ALTER TABLE saved_search ADD modified_by_id VARCHAR(36) DEFAULT NULL");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_CREATED_BY_ID ON saved_search (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_MODIFIED_BY_ID ON saved_search (modified_by_id, deleted)");

            $this->exec("ALTER TABLE action_history_record ALTER target_id TYPE VARCHAR(61)");
        }else{
            $this->exec("ALTER TABLE saved_search ADD created_at DATETIME DEFAULT NULL, ADD modified_at DATETIME DEFAULT NULL, ADD created_by_id VARCHAR(36) DEFAULT NULL, ADD modified_by_id VARCHAR(36) DEFAULT NULL;");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_CREATED_BY_ID ON saved_search (created_by_id, deleted)");
            $this->exec("CREATE INDEX IDX_SAVED_SEARCH_MODIFIED_BY_ID ON saved_search (modified_by_id, deleted)");

            $this->exec("ALTER TABLE action_history_record CHANGE target_id target_id VARCHAR(61) DEFAULT NULL");
        }

        if ($this->isPgSQL()) {
            $this->exec("DELETE FROM extensible_enum_extensible_enum_option WHERE extensible_enum_option_id NOT IN (SELECT id FROM extensible_enum_option WHERE deleted = false);");
        } else {
            $this->exec("DELETE FROM extensible_enum_extensible_enum_option WHERE extensible_enum_option_id NOT IN (SELECT id FROM extensible_enum_option WHERE deleted = 0);");
        }

        rename('client', 'public/client');
        rename('apidocs', 'public/apidocs');

        @mkdir('public/upload');
        @rename('upload/thumbnails', 'public/upload/thumbnails');

        copy('vendor/atrocore/core/copy/public/.htaccess', 'public/.htaccess');
        copy('vendor/atrocore/core/copy/public/index.php', 'public/index.php');
        copy('vendor/atrocore/core/copy/public/robots.txt', 'public/robots.txt');

        copy('vendor/atrocore/core/copy/console.php', 'console.php');

        $content = <<<'EOD'
<?php
if (substr(php_sapi_name(), 0, 3) != 'cli') {
    echo 'Webserver configuration has been deprecated. Please reconfigure your webserver to use public/index.php as document root. How to configure virtual host you can find <a href="https://help.atrocore.com/installation-and-maintenance/installation/apache-web-server#5-creating-a-virtual-host-for-your-application">here</a>.';
    exit;
}

chdir(dirname(__FILE__));
set_include_path(dirname(__FILE__));

require_once 'vendor/autoload.php';

$app = new \Atro\Core\Application();
$app->runConsole($argv);
EOD;

        file_put_contents('index.php', $content);

        // reload daemons
        file_put_contents('data/process-kill.txt', '1');

        $path = 'data/metadata/entityDefs';
        if (file_exists($path)) {
            foreach (scandir($path) as $file) {
                if (in_array($file, ['.', '..'])) {
                    continue;
                }

                $customDefs = @json_decode(file_get_contents("$path/$file"), true);

                if (!empty($customDefs['fields'])) {
                    $toUpdate = false;
                    foreach ($customDefs['fields'] as $field => $fieldDefs) {
                        if (!empty($fieldDefs['type']) && $fieldDefs['type'] == 'bool' && !isset($fieldDefs['notNull'])) {
                            $customDefs['fields'][$field]['notNull'] = false;
                            $toUpdate = true;
                        }
                    }
                    if ($toUpdate) {
                        file_put_contents("$path/$file", json_encode($customDefs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
            }
        }

    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
