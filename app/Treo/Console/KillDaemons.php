<?php

declare(strict_types=1);

namespace Treo\Console;

/**
 * Class KillDaemons
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class KillDaemons extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Kill all daemons.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        file_put_contents('data/process-kill.txt', '1');
        self::show("All daemons killed successfully", self::SUCCESS, true);
    }
}
