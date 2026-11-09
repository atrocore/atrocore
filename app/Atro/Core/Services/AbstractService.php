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

namespace Atro\Core\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Http\RequestHeaders;
use Atro\Core\LinkedRecords\LinkedRecordsLoader;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Interfaces\Injectable;
use Atro\Core\Utils\Config;
use Atro\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Base of the services: receives the dependencies listed in $dependencies from the container.
 */
abstract class AbstractService implements Injectable
{
    protected $dependencies = ['config', 'entityManager', 'user', 'language', 'memoryStorage'];
    protected $injections = [];

    public function __construct()
    {
        $this->init();
    }

    public function inject($name, $object): void
    {
        $this->injections[$name] = $object;
    }

    protected function init()
    {
    }

    protected function getHeaderLanguage(): ?string
    {
        $language = RequestHeaders::getLanguagePrism();
        if (!empty($language)) {
            $languages = ['main'];
            if ($this->getConfig()->get('isMultilangActive')) {
                $languages = array_merge($languages, $this->getConfig()->get('inputLanguageList', []));
            }

            if (in_array($language, $languages)) {
                return $language;
            }

            throw new BadRequest('No such language is available.');
        }

        return null;
    }

    protected function getInjection(string $name)
    {
        return $this->injections[$name];
    }

    protected function addDependency(string $name): void
    {
        $this->dependencies[] = $name;
    }

    protected function addDependencyList(array $list): void
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    public function getDependencyList(): array
    {
        return $this->dependencies;
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getInjection('entityManager');
    }

    protected function getLinkedRecordLoader(): LinkedRecordsLoader
    {
        return $this->getEntityManager()->getContainer()->get(LinkedRecordsLoader::class);
    }

    protected function getConfig(): Config
    {
        return $this->getInjection('config');
    }

    protected function getUser(): User
    {
        return $this->getInjection('user');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->getInjection('memoryStorage');
    }

    protected function getUserDisplayName(User $user): string
    {
        if ($user->isSystemUser()) {
            return $this->getInjection('language')->translate('System');
        }

        return (string)$user->get('name');
    }
}