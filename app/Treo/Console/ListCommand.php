<?php

declare(strict_types=1);

namespace Treo\Console;

use Treo\Core\ConsoleManager;

/**
 * ListCommand console
 *
 * @author r.ratsun@zinitsolutions.com
 */
class ListCommand extends AbstractConsole
{
    /**
     * Get console command description
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Show all existing commands and their descriptions.';
    }

    /**
     * Run action
     *
     * @param array $data
     */
    public function run(array $data): void
    {
        // get console config
        $config = $this->getConsoleConfig();

        // prepare data
        foreach ($config as $command => $class) {
            if (method_exists($class, 'getDescription') && empty($class::$isHidden)) {
                $data[$command] = [$command, $class::getDescription()];
            }
        }

        // sorting
        $sorted = array_keys($data);
        natsort($sorted);
        foreach ($sorted as $command) {
            $result[] = $data[$command];
        }

        // render
        self::show('Available commands:', self::INFO);
        echo self::arrayToTable($result);
    }

    /**
     * Get console config
     *
     * @return array
     */
    protected function getConsoleConfig(): array
    {
        return include CORE_PATH . '/Treo/Configs/Console.php';
    }
}
