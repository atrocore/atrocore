<?php

namespace Treo\Core\Migration;

use PDO;
use Treo\Core\Utils\Config;

/**
 * Base class
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Base
{
    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param PDO    $pdo
     * @param Config $config
     */
    public function __construct(PDO $pdo, Config $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Up to current
     */
    public function up(): void
    {
    }

    /**
     * Down to previous version
     */
    public function down(): void
    {
    }

    /**
     * @param string $message
     * @param bool   $break
     */
    protected function renderLine(string $message, bool $break = true)
    {
        \Treo\Composer\PostUpdate::renderLine($message, $break);
    }

    /**
     * Get config
     *
     * @return Config
     */
    protected function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get PDO
     *
     * @return PDO
     */
    protected function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * @param string $version
     */
    protected function updateCoreVersion(string $version): void
    {
        $data = json_decode(file_get_contents('composer.json'), true);
        $data['require']['atrocore/core'] = $version;
        file_put_contents('composer.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        copy('composer.json', 'data/stable-composer.json');
    }
}
