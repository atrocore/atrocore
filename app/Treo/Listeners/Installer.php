<?php
declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Installer listener
 *
 * @author r.ratsun@gmail.com
 */
class Installer extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterInstallSystem(Event $event)
    {
        // generate Treo ID
        $this->generateTreoId();

        // create files in data dir
        $this->createDataFiles();

        // create scheduled jobs
        $this->createScheduledJobs();

        /**
         * Run after install script if it needs
         */
        $file = 'data/after_install_script.php';
        if (file_exists($file)) {
            include_once $file;
            unlink($file);
        }
    }

    /**
     * Generate Treo ID
     */
    protected function generateTreoId(): void
    {
        // generate id
        $treoId = \Treo\Services\Installer::generateTreoId();

        // set to config
        $this->getConfig()->set('treoId', $treoId);
        $this->getConfig()->save();

        // set treo ID to packagist repository
        $composeData = json_decode(file_get_contents('composer.json'), true);
        $composeData['repositories'][0]['url'] = str_replace('common', $treoId, $composeData['repositories'][0]['url']);
        file_put_contents('composer.json', json_encode($composeData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Create needed files in data directory
     */
    protected function createDataFiles(): void
    {
        file_put_contents('data/notReadCount.json', '{}');
        file_put_contents('data/popupNotifications.json', '{}');
    }

    /**
     * Create scheduled jobs
     */
    protected function createScheduledJobs(): void
    {
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('ComposerAutoUpdate', 'Auto-updating of modules', 'ComposerAutoUpdate', 'Active', '0 0 * * SUN')"
            );
        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('TreoCleanup','Unused data cleanup. Deleting old data and unused db tables, db columns, etc.','TreoCleanup','Active','0 0 1 * *')"
            );

        $this
            ->getEntityManager()
            ->nativeQuery(
                "INSERT INTO scheduled_job (id, name, job, status, scheduling) VALUES ('RestApiDocs','Generate REST API docs','RestApiDocs','Active','0 */2 * * *')"
            );
    }
}
