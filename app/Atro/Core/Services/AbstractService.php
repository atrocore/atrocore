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
use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Base of the services: takes the services it needs from the container.
 */
abstract class AbstractService
{
    public function __construct(protected readonly Container $container)
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

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getLinkedRecordLoader(): LinkedRecordsLoader
    {
        return $this->container->get(LinkedRecordsLoader::class);
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    protected function getUser(): User
    {
        return $this->container->get('user');
    }

    public function getMemoryStorage(): StorageInterface
    {
        return $this->container->get('memoryStorage');
    }

    protected function getUserDisplayName(User $user): string
    {
        if ($user->isSystemUser()) {
            return $this->container->get('language')->translate('System');
        }

        return (string)$user->get('name');
    }
}