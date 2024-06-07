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

namespace Atro\Controllers;

use Atro\Services\Installer as InstallerService;
use Atro\Core\Exceptions;
use Slim\Http\Request;

class Installer extends AbstractController
{
    public function actionGetTranslations($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is installed
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getTranslations();
    }

    public function actionSetLanguage($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!isset($post['language'])) {
            throw new Exceptions\BadRequest();
        }

        return $installer->setLanguage($post['language']);
    }

    public function actionGetDefaultDbSettings($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getDefaultDbSettings();
    }

    public function actionSetDbSettings($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!$this->isValidDbParams($post)) {
            throw new Exceptions\BadRequest();
        }

        return $installer->setDbSettings($post);
    }

    public function actionCreateAdmin($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (empty($post['username']) || empty($post['password']) || empty($post['confirmPassword'])) {
            throw new Exceptions\BadRequest();
        }

        return $installer->createAdmin($post);
    }

    public function actionCheckDbConnect($params, $data, Request $request): array
    {
        // check method
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        $post = get_object_vars($data);

        // check if input params exists
        if (!$this->isValidDbParams($post)) {
            throw new Exceptions\BadRequest();
        }

        return $installer->checkDbConnect($post);
    }

    public function actionGetLicenseAndLanguages($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        /** @var InstallerService $installer */
        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getLicenseAndLanguages();
    }

    public function actionGetRequiredsList($params, $data, Request $request): array
    {
        // check method
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        $installer = $this->getService('Installer');

        // check if is install
        if ($installer->isInstalled()) {
            throw new Exceptions\Forbidden();
        }

        return $installer->getRequiredsList();
    }

    protected function isValidDbParams(array $post): bool
    {
        return isset($post['host']) && isset($post['dbname']) && isset($post['user']);
    }
}
