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

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\OpenApiGenerator;

class OpenApiCoverage extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Show routes not documented in the OpenAPI schema.';
    }

    public function run(array $data): void
    {
        $schema = $this->getContainer()->get(OpenApiGenerator::class)->getFullSchema();
        $schemaPaths = $schema['paths'] ?? [];

        $undocumented = [];

        foreach ($this->getContainer()->get('route')->getAll() as $route) {
            // Skip generic catch-all routes where :controller is a variable —
            // these are covered by per-entity paths generated from metadata
            if (str_contains($route['route'], ':controller')) {
                continue;
            }

            $path = preg_replace('/:([\w]+)/', '{$1}', $route['route']);
            $method = strtolower($route['method']);

            if (!isset($schemaPaths[$path][$method])) {
                $undocumented[] = [
                    strtoupper($method),
                    $route['route'],
                    $route['params']['controller'] ?? '—',
                    $route['params']['action'] ?? '—',
                ];
            }
        }

        if (empty($undocumented)) {
            self::show('All routes are documented in the OpenAPI schema.', self::SUCCESS);
            return;
        }

        self::show(count($undocumented) . ' undocumented route(s) found:', self::INFO);
        echo self::arrayToTable($undocumented, ['METHOD', 'ROUTE', 'CONTROLLER', 'ACTION']);
    }
}
