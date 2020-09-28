<?php
declare(strict_types=1);

use Treo\Composer\PostUpdate;
use Treo\Core\Utils\Util;

/**
 * Class ComposerCmd
 *
 * @author r.ratsun@gmail.com
 */
class ComposerCmd
{
    const DIFF_PATH = 'data/composer-diff';

    /**
     * Before update
     */
    public static function preUpdate(): void
    {
        if (class_exists(Util::class)) {
            // delete diff cache
            Util::removeDir(self::DIFF_PATH);
        }
    }

    /**
     * After update
     */
    public static function postUpdate(): void
    {
        // change directory
        chdir(dirname(__FILE__));

        // set the include_path
        set_include_path(dirname(__FILE__));

        // autoload
        require_once 'vendor/autoload.php';

        // run post update actions
        (new PostUpdate())->run();
    }

    /**
     * After package install
     *
     * @param mixed $event
     *
     * @return void
     */
    public static function postPackageInstall($event): void
    {
        try {
            $name = $event->getOperation()->getPackage()->getName();
        } catch (\Throwable $e) {
        }

        if (isset($name)) {
            self::createPackageActionFile($name, 'install');
        }
    }

    /**
     * @param mixed $event
     *
     * @return void
     */
    public static function postPackageUpdate($event): void
    {
        // get composer update pretty line
        $prettyLine = (string)$event->getOperation();

        preg_match_all("/^Updating (.*) \((.*)\) to (.*) \((.*)\)$/", $prettyLine, $matches);
        if (count($matches) == 5) {
            self::createPackageActionFile($matches[1][0], 'update', $matches[2][0] . '_' . $matches[4][0]);
        }
    }

    /**
     * Before package uninstall
     *
     * @param mixed $event
     *
     * @return void
     */
    public static function prePackageUninstall($event): void
    {
        try {
            $name = $event->getOperation()->getPackage()->getName();
        } catch (\Throwable $e) {
        }

        if (isset($name)) {
            self::createPackageActionFile($name, 'delete');
        }
    }

    /**
     * @param string $name
     * @param string $dir
     * @param string $content
     *
     * @return bool
     */
    protected static function createPackageActionFile(string $name, string $dir, string $content = ''): bool
    {
        // find composer.json file
        $file = "vendor/$name/composer.json";
        if (!file_exists($file)) {
            return false;
        }

        // try to parse composer.json file
        try {
            $data = json_decode(file_get_contents($file), true);
        } catch (\Throwable $e) {
            return false;
        }

        // exit if is not treo package
        if (!isset($data['extra']['treoId'])) {
            return false;
        }

        // prepare dir path
        $dirPath = self::DIFF_PATH . "/$dir";

        // create dir if it needs
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        // prepare content
        $content = (empty($content)) ? $name : $name . '_' . $content;

        // save
        file_put_contents("$dirPath/{$data['extra']['treoId']}.txt", $content);

        return true;
    }
}
