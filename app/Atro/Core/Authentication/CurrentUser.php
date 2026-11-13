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

namespace Atro\Core\Authentication;

use Atro\Core\UserContext;
use Atro\Core\Utils\Config;
use Atro\Entities\User;
use Espo\ORM\EntityManager;

/**
 * The user the request or the job acts as.
 */
class CurrentUser
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly UserContext $userContext,
        private readonly Config $config
    ) {
    }

    public function set(User $user): void
    {
        $user->loadLinkMultipleField('teams');
        $user->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? '');

        $this->userContext->set($user);
        $this->entityManager->setUser($user);
    }

    /**
     * The system user, an administrator: the console commands, the jobs and the requests that need no authentication
     * act as it.
     */
    public function setSystemUser(): void
    {
        if (!$this->config->isInstalled()) {
            return;
        }

        $user = $this->entityManager->getRepository('User')->getGlobalSystemUser();
        $user->set('isAdmin', true);
        $user->set('ipAddress', $_SERVER['REMOTE_ADDR'] ?? '');

        $this->entityManager->setUser($user);
        $this->userContext->set($user);
    }
}
