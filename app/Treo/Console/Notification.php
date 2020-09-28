<?php

declare(strict_types=1);

namespace Treo\Console;

use Treo\Core\ServiceFactory;

/**
 * Class Notification
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Notification extends AbstractConsole
{
    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Refresh users notifications cache.';
    }

    /**
     * @inheritdoc
     */
    public function run(array $data): void
    {
        if (empty($this->getConfig()->get('isInstalled'))) {
            exit(1);
        }

        // refresh notReadCount
        $this->notReadCount();

        // refresh popupNotifications
        $this->popupNotifications();

        self::show('Users notifications cache refreshed successfully', self::SUCCESS, true);
    }

    /**
     * Refresh notReadCount
     */
    protected function notReadCount(): void
    {
        // prepare sql
        $sql
            = "SELECT
                  u.id as userId, COUNT(n.id) as count
                FROM
                  user AS u
                LEFT JOIN notification AS n ON n.user_id=u.id AND n.deleted=0 AND n.read=0
                WHERE u.deleted=0
                  AND u.is_active=1
                  AND u.user_name!='system'
                GROUP BY u.id";

        // get data
        $sth = $this->getContainer()->get('entityManager')->getPDO()->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            // prepare content
            $content = [];
            foreach ($data as $row) {
                $content[$row['userId']] = (int)$row['count'];
            }

            // set to file
            file_put_contents('data/notReadCount.json', json_encode($content));
        }
    }

    /**
     * Refresh popupNotifications
     */
    protected function popupNotifications(): void
    {
        // prepare content
        $content = [];

        if (!empty($users = $this->getPopupNotificationsUsers())) {
            // auth
            $auth = new \Treo\Core\Utils\Auth($this->getContainer());
            $auth->useNoAuth();

            if ($this->getServiceFactory()->checkExists('Activities')) {
                // create service
                $service = $this->getServiceFactory()->create('Activities');
                // prepare content
                foreach ($users as $userId) {
                    $content[$userId] = $service->getPopupNotifications($userId);
                }
            }
        }

        // set to file
        file_put_contents('data/popupNotifications.json', json_encode($content));
    }

    protected function getPopupNotificationsUsers(): array
    {
        // get users
        $sth = $this
            ->getContainer()
            ->get('entityManager')
            ->getPDO()
            ->prepare("SELECT user_id FROM reminder WHERE type='Popup' AND deleted=0");
        $sth->execute();
        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        if (!empty($data)) {
            $result = array_unique(array_column($data, 'user_id'));
        }

        return $result;
    }

    /**
     * Get service factory
     *
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }
}
