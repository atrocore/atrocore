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
use Espo\Core\Utils\Log\Monolog\Handler\RotatingFileHandler;
use Espo\Core\Utils\Log\Monolog\Handler\StreamHandler;
use Monolog\ErrorHandler;
use Monolog\Logger;

class Log implements Factory
{
    public function create(Container $container)
    {
        /** @var Config $config */
        $config = $container->get('config');

        switch ($config->get('logger.level', 'WARNING')) {
            case 'DEBUG':
                $levelCode = Logger::DEBUG;
                break;
            case 'INFO':
                $levelCode = Logger::INFO;
                break;
            case 'NOTICE':
                $levelCode = Logger::NOTICE;
                break;
            case 'WARNING':
                $levelCode = Logger::WARNING;
                break;
            case 'ERROR':
                $levelCode = Logger::ERROR;
                break;
            case 'CRITICAL':
                $levelCode = Logger::CRITICAL;
                break;
            case 'ALERT':
                $levelCode = Logger::ALERT;
                break;
            case 'EMERGENCY':
                $levelCode = Logger::EMERGENCY;
                break;
            default:
                $levelCode = Logger::WARNING;
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
