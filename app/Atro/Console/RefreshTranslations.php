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

namespace Atro\Console;

use Atro\Core\DataManager;
use Espo\ORM\EntityManager;

class RefreshTranslations extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Refresh translations.';
    }

    public function run(array $data): void
    {
        $this->getEntityManager()->getRepository('Translation')->refreshToDefault();
        $this->getDataManager()->clearCache();

        self::show('Translations refreshed successfully.', self::SUCCESS);
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getContainer()->get('dataManager');
    }
}
