<?php
/**
* AtroCore Software
*
* This source file is available under GNU General Public License version 3 (GPLv3).
* Full copyright and license information is available in LICENSE.txt, located in the root directory.
*
*  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
*  @license    GPLv3 (https://www.gnu.org/licenses/)
*/

declare(strict_types=1);

namespace Atro\Console;

/**
 * InstallDemoProject console
 */
class InstallDemoProject extends AbstractConsole
{
    /**
     * @var bool
     */
    public static $isHidden = true;

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function run(array $data): void
    {
        if ($this->getConfig()->get('isInstalled', false)) {
            self::show('System is already installed.', self::ERROR, true);
        }

        // fill config via environment variables
        if (isset($_SERVER['DB_NAME']) && isset($_SERVER['DB_USER']) && isset($_SERVER['DB_PASS'])) {
            $this->getConfig()->set(
                'database', [
                    'driver'   => 'pdo_mysql',
                    'host'     => !empty($_SERVER['DB_HOST']) ? $_SERVER['DB_HOST'] : 'localhost',
                    'port'     => '',
                    'charset'  => 'utf8mb4',
                    'dbname'   => $_SERVER['DB_NAME'],
                    'user'     => $_SERVER['DB_USER'],
                    'password' => $_SERVER['DB_PASS']
                ]
            );

            if (!empty($_SERVER['PASSWORD_SALT'])) {
                $this->getConfig()->set('passwordSalt', $_SERVER['PASSWORD_SALT']);
            }

            if (isset($_SERVER['CRYPT_KEY'])) {
                $this->getConfig()->set('cryptKey', $_SERVER['CRYPT_KEY'] == 'empty' ? '' : $_SERVER['CRYPT_KEY']);
            }

            if (!empty($_SERVER['LANGUAGE'])) {
                $this->getConfig()->set('language', $_SERVER['LANGUAGE']);
            }

            if (!empty($_SERVER['SITE_URL'])) {
                $this->getConfig()->set('siteUrl', 'https://' . $_SERVER['SITE_URL']);
            }

            if (!empty($_SERVER['ADMIN_USER']) && !empty($_SERVER['ADMIN_PASS'])) {
                $this->getConfig()->set(
                    'demo', [
                        'username' => $_SERVER['ADMIN_USER'],
                        'password' => $_SERVER['ADMIN_PASS'],
                    ]
                );
            }
            $this->getConfig()->save();
        }

        $this->getContainer()->get('serviceFactory')->create('Installer')->createAdmin(
            [
                'username'        => $this->getConfig()->get('demo.username'),
                'password'        => $this->getConfig()->get('demo.password'),
                'confirmPassword' => $this->getConfig()->get('demo.password'),
            ]
        );

        self::show('Demo project installed successfully.', self::SUCCESS, true);
    }
}
