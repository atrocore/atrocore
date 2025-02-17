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

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Atro\Core\Factories\FactoryInterface as Factory;
use Atro\Core\Monolog\Handler\ReportingHandler;
use Espo\Core\Utils\Config;
use Monolog\ErrorHandler;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class Log implements Factory
{
    public function create(Container $container)
    {
        /** @var Config $config */
        $config = $container->get('config');

        switch ($config->get('logger.level', 'WARNING')) {
            case 'DEBUG':
                $levelCode = Level::Debug;
                break;
            case 'INFO':
                $levelCode = Level::Info;
                break;
            case 'NOTICE':
                $levelCode = Level::Notice;
                break;
            case 'WARNING':
                $levelCode = Level::Warning;
                break;
            case 'ERROR':
                $levelCode = Level::Error;
                break;
            case 'CRITICAL':
                $levelCode = Level::Critical;
                break;
            case 'ALERT':
                $levelCode = Level::Alert;
                break;
            case 'EMERGENCY':
                $levelCode = Level::Emergency;
                break;
            default:
                $levelCode = Level::Warning;
        }

        $log = new Logger('Log');

        $path = $config->get('logger.path', 'data/logs/log.log');
        if ($config->get('logger.rotation', true)) {
            $handler = new RotatingFileHandler($path, $config->get('logger.maxFileNumber', 30), $levelCode);
        } else {
            $handler = new StreamHandler($path, $levelCode);
        }

        $log->pushHandler($handler);
        if ($config->get('reportingEnabled', false)) {
            $log->pushHandler(new ReportingHandler($levelCode, (string)$config->get('appId')));
        }
        $errorHandler = new ErrorHandler($log);
        $errorHandler->registerExceptionHandler([], false);
        $errorHandler->registerErrorHandler([], false);

        return $log;
    }
}
