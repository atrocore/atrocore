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

namespace Atro\Services;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\KeyValueStorages\StorageInterface;
use Espo\Core\Interfaces\Injectable;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

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

    public static function getHeader(string $name): ?string
    {
        try {
            $headers = \getallheaders();
        } catch (\Throwable $e) {
            $headers = [];
        }

        foreach ($headers as $k => $v) {
            if (strtolower($name) === strtolower($k)) {
                return $v;
            }
        }

        return null;
    }

    public static function getLanguagePrism(): ?string
    {
        $language = self::getHeader('language');
        if (!empty($GLOBALS['languagePrism'])) {
            $language = $GLOBALS['languagePrism'];
        }

        return $language;
    }

    protected function init()
    {
    }

    protected function getHeaderLanguage(): ?string
    {
        $language = self::getLanguagePrism();
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
}