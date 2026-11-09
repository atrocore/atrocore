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

namespace Atro\AfterInstall;

use Atro\Core\ModuleManager\AfterInstallInterface;
use Atro\Core\Utils\Config;
use Espo\ORM\EntityManager;

/**
 * Creates the main locale and the main language. The language and the time zone of the main locale are the
 * ones chosen during the installation (`language` and `timeZone` of the config), English and UTC otherwise.
 */
class CreateLocalization implements AfterInstallInterface
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config
    ) {
    }

    public static function getSortOrder(): int
    {
        return 10;
    }

    public function run(): void
    {
        $this->create('Locale', [
            'id'                             => 'main',
            'name'                           => 'Main',
            'code'                           => 'en_US',
            'languageCode'                   => $this->config->get('language') ?: 'en_US',
            'dateFormat'                     => 'DD.MM.YYYY',
            'timeZone'                       => $this->config->get('timeZone') ?: 'UTC',
            'weekStart'                      => 'monday',
            'timeFormat'                     => 'HH:mm',
            'thousandSeparator'              => '.',
            'decimalMark'                    => ',',
            'displayLabelsInContentLanguage' => false,
        ]);

        $this->create('Language', [
            'id'   => 'main',
            'name' => 'English',
            'code' => 'en_US',
            'role' => 'main',
        ]);
    }

    private function create(string $entityName, array $data): void
    {
        $entity = $this->entityManager->getRepository($entityName)->get();
        $entity->set($data);

        $this->entityManager->saveEntity($entity);
    }
}
