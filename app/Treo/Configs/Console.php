<?php

declare(strict_types=1);

namespace Treo\Configs;

use Treo\Console;

return [
    "list"                         => Console\ListCommand::class,
    "clear cache"                  => Console\ClearCache::class,
    "cleanup"                      => Console\Cleanup::class,
    "rebuild"                      => Console\Rebuild::class,
    "sql diff --show"              => Console\SqlDiff::class,
    "cron"                         => Console\Cron::class,
    "store --refresh"              => Console\StoreRefresh::class,
    "migrate <module> <from> <to>" => Console\Migrate::class,
    "apidocs --generate"           => Console\GenerateApidocs::class,
    "qm <stream> --run"            => Console\QueueManager::class,
    "qm item <id> --run"           => Console\QueueItem::class,
    "notifications --refresh"      => Console\Notification::class,
    "kill daemons"                 => Console\KillDaemons::class,
    "daemon <name> <id>"           => Console\Daemon::class,
];
