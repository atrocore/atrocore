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

namespace Atro\Core\Utils\Database\DBAL;

class LoggingConnection extends \Doctrine\DBAL\Connection
{
    public function executeQuery(string $sql, array $params = [], $types = [], ?\Doctrine\DBAL\Cache\QueryCacheProfile $qcp = null): \Doctrine\DBAL\Result
    {
        if (isset($GLOBALS['debugSQL'])) {
            $start = microtime(true);
            try{
                $result = parent::executeQuery($sql, $params, $types);
            }catch (\Exception $e){
                throw $e;
            }
            $executionTime = number_format((microtime(true) - $start) * 1000, 2);

            $GLOBALS['debugSQL'][] = "[{$executionTime}ms] " . $sql .
                (!empty($params) ? " | Parameters: " . json_encode($params) : "");

            return $result;
        }

        return parent::executeQuery($sql, $params, $types);
    }

    public function executeStatement($sql, array $params = [], array $types = []): int
    {
        if (isset($GLOBALS['debugSQL'])) {
            $start = microtime(true);
            $result = parent::executeStatement($sql, $params, $types);
            $executionTime = number_format((microtime(true) - $start) * 1000, 2);

            $GLOBALS['debugSQL'][] = "[{$executionTime}ms] " . $sql .
                (!empty($params) ? " | Parameters: " . json_encode($params) : "");

            return $result;
        }

        return parent::executeStatement($sql, $params, $types);
    }
}