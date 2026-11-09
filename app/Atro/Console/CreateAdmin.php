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

use Atro\Core\Utils\PasswordHash;

/**
 * Creates an administrator. The password is read from the standard input, so that it is not visible in the
 * list of the processes and in the shell history: `echo "$PASSWORD" | php console.php create admin john`.
 */
class CreateAdmin extends AbstractConsole
{
    public static function getDescription(): string
    {
        return 'Create an administrator, the password is read from the standard input.';
    }

    public function run(array $data): void
    {
        $userName = trim((string)($data['username'] ?? ''));
        if ($userName === '') {
            self::show('The username is required.', self::ERROR, true);
        }

        $entityManager = $this->getContainer()->get('entityManager');

        if (!empty($entityManager->getRepository('User')->where(['userName' => $userName])->findOne())) {
            self::show("The user '$userName' already exists.", self::ERROR, true);
        }

        $password = rtrim((string)fgets(STDIN), "\r\n");
        if ($password === '') {
            self::show('The password is required, it is read from the standard input.', self::ERROR, true);
        }

        $user = $entityManager->getRepository('User')->get();
        $user->set([
            'userName' => $userName,
            'lastName' => 'Admin',
            'password' => $this->getContainer()->get(PasswordHash::class)->hash($password),
            'isAdmin'  => true,
            'isActive' => true,
        ]);

        try {
            $entityManager->saveEntity($user);
        } catch (\Throwable $e) {
            self::show('The administrator is not created: ' . $e->getMessage(), self::ERROR, true);
        }

        // an administrator created outside of the user interface must leave a trace
        $GLOBALS['log']->warning("Administrator '$userName' created from the console.");

        self::show("The administrator '$userName' is created.", self::SUCCESS);
    }
}
