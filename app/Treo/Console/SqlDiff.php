<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class SqlDiff
 *
 * @author r.ratsun@teolabs.com
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
