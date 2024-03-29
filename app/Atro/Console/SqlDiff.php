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

namespace Atro\Console;

/**
 * Class SqlDiff
 */
class SqlDiff extends AbstractConsole
{
    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Show SQL diff.';
    }

    /**
     * @inheritDoc
     */
    public function run(array $data): void
    {
        try {
            /** @var array $queries */
            $queries = $this->getContainer()->get('schema')->getDiffQueries();
        } catch (\Throwable $e) {
            echo $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
            die();
        }

        if (empty($queries)) {
            self::show('No database changes were detected.', self::SUCCESS, true);
        }

        echo implode(';' . PHP_EOL, $queries) . PHP_EOL;
        die();
    }
}
