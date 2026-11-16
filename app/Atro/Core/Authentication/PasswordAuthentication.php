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

use Atro\Core\Utils\PasswordHash;
use Atro\Entities\User;
use Espo\ORM\EntityManager;

/**
 * The local password of the users of the type "Password".
 */
class PasswordAuthentication implements AuthenticationMethodInterface
{
    public const USER_TYPE = 'Password';

    public function __construct(private readonly EntityManager $entityManager, private readonly PasswordHash $passwordHash)
    {
    }

    public function authenticate(string $username, string $password): ?User
    {
        $user = $this->entityManager->getRepository('User')
            ->where([
                'type'     => self::USER_TYPE,
                'userName' => $username,
            ])
            ->findOne();

        if (empty($user)) {
            // spend the same time as a real verification, so the response time does not reveal whether the user exists
            $this->passwordHash->hash($password);
            return null;
        }

        $hash = (string)$user->get('password');
        if (!$this->passwordHash->verify($password, $hash)) {
            return null;
        }

        if ($this->passwordHash->needsRehash($hash)) {
            $this->rehash($user, $password);
        }

        return $user;
    }

    private function rehash(User $user, string $password): void
    {
        $hash = $this->passwordHash->hash($password);

        $connection = $this->entityManager->getDbal();
        $connection->createQueryBuilder()
            ->update($connection->quoteIdentifier('user'))
            ->set('password', ':password')
            ->where('id = :id')
            ->setParameter('password', $hash)
            ->setParameter('id', $user->id)
            ->executeStatement();

        $user->set('password', $hash);
        $user->setFetched('password', $hash);
    }
}
