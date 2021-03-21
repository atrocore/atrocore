<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Treo\Console;

use Espo\Core\Utils\Util;
use Espo\Repositories\Notification as NotificationRepository;
use Treo\Core\Application;

/**
 * Class Notification
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
        if (empty($this->getConfig()->get('isInstalled')) || Application::isSystemUpdating()) {
            exit(1);
        }

        $path = NotificationRepository::UPDATE_COUNT_PATH;

        if (!empty($files = Util::scanDir($path))) {
            $this->refresh();
            foreach ($files as $file) {
                unlink("{$path}/$file");
            }
        }

        self::show('Users notifications cache refreshed successfully', self::SUCCESS, true);
    }

    /**
     * Refresh notReadCount
     */
    protected function refresh(): void
    {
        // get data
        $sth = $this->getPdo()->prepare("SELECT n.user_id as userId, COUNT(n.id) as total FROM notification AS n WHERE n.read=0 GROUP BY n.user_id");
        $sth->execute();
        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            file_put_contents(NotificationRepository::NOT_READ_COUNT_FILE, json_encode(array_column($data, 'total', 'userId')));
        }
    }

    private function getPdo(): \Pdo
    {
        return $this->getContainer()->get('pdo');
    }
}
