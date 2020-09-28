<?php

declare(strict_types=1);

namespace Treo\Console;

use Treo\Core\Utils\Metadata;
use Treo\Core\Utils\Config;
use Treo\Traits\ContainerTrait;

/**
 * AbtractConsole class
 *
 * @author r.ratsun@zinitsolutions.com
 */
abstract class AbstractConsole
{
    use ContainerTrait;

    const SUCCESS = 1;
    const ERROR = 2;
    const INFO = 3;

    /**
     * @var bool
     */
    public static $isHidden = false;

    /**
     * Run action
     *
     * @param array $data
     */
    abstract public function run(array $data): void;

    /**
     * Get console command description
     *
     * @return string
     */
    abstract public static function getDescription(): string;

    /**
     * Echo CLI message
     *
     * @param string $message
     * @param int    $status
     * @param bool   $stop
     */
    public static function show(string $message, int $status = 0, bool $stop = false): void
    {
        switch ($status) {
            // success
            case self::SUCCESS:
                echo "\033[0;32m{$message}\033[0m" . PHP_EOL;
                if ($stop) {
                    exit(0);
                }
                break;
            // error
            case self::ERROR:
                echo "\033[1;31m{$message}\033[0m" . PHP_EOL;
                if ($stop) {
                    exit(1);
                }
                break;
            // info
            case self::INFO:
                echo "\033[0;36m{$message}\033[0m" . PHP_EOL;
                if ($stop) {
                    exit();
                }
                break;
            // default
            default:
                echo $message . PHP_EOL;
                if ($stop) {
                    exit();
                }
                break;
        }
    }

    /**
     * Array to table
     *
     * @param array $data
     * @param array $header
     *
     * @return string
     */
    public static function arrayToTable(array $data, array $header = []): string
    {
        // prepare data
        $data = array_merge([$header], $data);
        foreach ($data as $rowKey => $row) {
            $isHeader = (!empty($header) && $rowKey == 0);
            foreach ($row as $cellKey => $cell) {
                // prepare color
                if ($isHeader) {
                    $color = '0;31';
                } else {
                    $color = (!empty($cellKey % 2)) ? '0;37' : '0;32';
                }

                // inject breaklines and color
                $data[$rowKey][$cellKey] = '| ' . "\033[{$color}m{$cell}\033[0m";
            }
            $data[$rowKey][] = '|';
        }

        // Find longest string in each column
        $columns = [];
        foreach ($data as $rowKey => $row) {
            foreach ($row as $cellKey => $cell) {
                $length = strlen($cell);
                if (empty($columns[$cellKey]) || $columns[$cellKey] < $length) {
                    $columns[$cellKey] = $length;
                }
            }
        }

        // Output table, padding columns
        $table = '';
        foreach ($data as $rowKey => $row) {
            foreach ($row as $cellKey => $cell) {
                $table .= str_pad($cell, $columns[$cellKey]) . '   ';
            }
            $table .= PHP_EOL;
        }

        return $table;
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    /**
     * Get translated message
     *
     * @param string $label
     * @param string $category
     * @param string $scope
     * @param null   $requiredOptions
     *
     * @return string
     */
    protected function translate(string $label, string $category, string $scope, $requiredOptions = null): string
    {
        return $this->getContainer()->get('language')->translate($label, $category, $scope, $requiredOptions);
    }
}
