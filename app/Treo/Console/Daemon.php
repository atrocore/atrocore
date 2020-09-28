<?php

declare(strict_types=1);

namespace Treo\Console;

use Espo\Entities\User;
use Treo\Core\ORM\EntityManager;
use Treo\Core\QueueManager;
use Treo\Services\Composer;

/**
 * Class Daemon
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
        $log = COMPOSER_LOG;

        while (true) {
            // delete check-up file
            if (file_exists(Composer::CHECK_UP_FILE)) {
                unlink(Composer::CHECK_UP_FILE);
            }

            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (file_exists($log)) {
                /** @var EntityManager $em */
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

                // execute composer update
                exec($this->getPhp() . " composer.phar update >> $log 2>&1", $output, $exitCode);

                // create note
                $note = $em->getEntity('Note');
                $note->set('type', 'composerUpdate');
                $note->set('parentType', 'ModuleManager');
                $note->set('data', ['status' => ($exitCode == 0) ? 0 : 1, 'output' => file_get_contents($log)]);
                $note->set('createdById', $user->get('id'));
                $em->saveEntity($note, ['skipCreatedBy' => true]);

                // remove log file
                unlink($log);
            }

            sleep(1);
        }
    }

    /**
     * @param string $id
     */
    protected function qmDaemon(string $id): void
    {
        /** @var string $stream */
        $stream = explode('-', $id)[0];

        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            if (file_exists(sprintf(QueueManager::QUEUE_PATH, $stream))) {
                exec($this->getPhp() . " index.php qm $stream --run");
            }

            sleep(1);
        }
    }

    /**
     * @param string $id
     */
    protected function notificationDaemon(string $id): void
    {
        while (true) {
            if (file_exists(Cron::DAEMON_KILLER)) {
                break;
            }

            exec($this->getPhp() . " index.php notifications --refresh");

            sleep(5);
        }
    }

    /**
     * @return string
     */
    protected function getPhp(): string
    {
        return (new \Espo\Core\Utils\System())->getPhpBin();
    }
}