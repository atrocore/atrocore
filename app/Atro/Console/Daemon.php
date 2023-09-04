<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.md, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

use Atro\Core\Application;
use Espo\Core\PseudoTransactionManager;
use Espo\Entities\User;
use Espo\Services\Composer;

/**
 * Class Daemon
 */
class Daemon extends AbstractConsole
{
    /**
     * @var bool
     */
    public static $isHidden = true;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function run(array $data): void
    {
        $method = $data['name'] . 'Daemon';
        if (method_exists($this, $method)) {
            $this->$method($data['id']);
        }
    }

    /**
     * @param string $id
     */
    protected function composerDaemon(string $id): void
    {
        /** @var string $log */
        $log = Application::COMPOSER_LOG_FILE;

        while (true) {
            // delete check-up file
            if (file_exists(Composer::CHECK_UP_FILE)) {
                unlink(Composer::CHECK_UP_FILE);
            }

            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (file_exists($log)) {
                $em = $this->getContainer()->get('entityManager');

                /** @var User $user */
                $user = $em
                    ->getRepository('User')
                    ->select(['id'])
                    ->where(['id' => file_get_contents($log)])
                    ->findOne();

                // skip if no such user
                if (empty($user)) {
                    // remove log file
                    unlink($log);
                    continue 1;
                }

                // cleanup
                file_put_contents($log, '');

                exec($this->getPhpBin() . " composer.phar self-update 2>/dev/null", $output, $exitCode);
                if (empty($exitCode)) {
                    exec($this->getPhpBin() . " composer.phar update >> $log 2>&1", $output, $exitCode);
                } else {
                    file_put_contents($log, "Failed! The new version of the composer can't be copied.");
                }

                try {
                    // create note
                    $note = $em->getEntity('Note');
                    $note->set('type', 'composerUpdate');
                    $note->set('parentType', 'ModuleManager');
                    $note->set('data', ['status' => ($exitCode == 0) ? 0 : 1, 'output' => file_get_contents($log)]);
                    $note->set('createdById', $user->get('id'));
                    $em->saveEntity($note, ['skipAll' => true]);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error('Creating composer update log failed: ' . $e->getMessage());
                }

                // remove log file
                unlink($log);
            }

            sleep(1);
        }
    }

    protected function qmDaemon(string $id): void
    {
        /** @var string $stream */
        $stream = explode('-', $id)[0];

        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            $expectedStream = time() % (int)$this->getConfig()->get('queueManagerWorkersCount', 4);
            if (file_exists(\Espo\Core\QueueManager::FILE_PATH) && $expectedStream == $stream) {
                exec($this->getPhpBin() . " index.php qm $stream --run");
            }

            sleep(1);
        }
    }

    protected function ptDaemon(string $id): void
    {
        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (PseudoTransactionManager::hasJobs()) {
                exec($this->getPhpBin() . " index.php pt --run");
            }

            sleep(1);
        }
    }
}