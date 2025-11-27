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

namespace Atro\Controllers;

use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\BadRequest;
use Atro\Services\Composer;
use Atro\Core\Utils\Language;

class Settings extends AbstractController
{
    public function actionRead($params, $data)
    {
        return $this->getService('Settings')->getConfigData();
    }

    public function actionUpdate($params, $data, $request)
    {
        return $this->actionPatch($params, $data, $request);
    }

    public function actionPatch($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!$request->isPut() && !$request->isPatch()) {
            throw new BadRequest();
        }

        if (!empty($data->fileNameRegexPattern) && !preg_match('/^\/((?:(?:[^?+*{}()[\]\\\\|]+|\\\\.|\[(?:\^?\\\\.|\^[^\\\\]|[^\\\\^])(?:[^\]\\\\]+|\\\\.)*\]|\((?:\?[:=!]|\?<[=!]|\?>)?(?1)??\)|\(\?(?:R|[+-]?\d+)\))(?:(?:[?+*]|\{\d+(?:,\d*)?\})[?+]?)?|\|)*)\/[gmixsuAJD]*$/', $data->fileNameRegexPattern)) {
            throw new BadRequest($this->getLanguage()->translate('regexNotValid', 'exceptions', 'FieldManager'));
        }

        if (!empty($data->passwordRegexPattern) && preg_match($data->passwordRegexPattern, '') === false) {
            throw new BadRequest($this->getLanguage()->translate('regexNotValid', 'exceptions', 'FieldManager'));
        }

        $this->getServiceFactory()->create('Settings')->validate($data);

        if (property_exists($data, 'onlyStableReleases')) {
            if ($data->onlyStableReleases !== $this->getConfig()->get('onlyStableReleases')) {
                Composer::setMinimumStability($data->onlyStableReleases ? 'stable' : 'RC');
            }
            unset($data->onlyStableReleases);
        }

        // clear cache
        $this->getContainer()->get('dataManager')->clearCache();

        if (property_exists($data, 'siteUrl')) {
            $data->siteUrl = rtrim($data->siteUrl, '/');
        }

        $this->getConfig()->setData($data, $this->getUser()->isAdmin());
        $result = $this->getConfig()->save();
        if ($result === false) {
            throw new Error('Cannot save settings');
        }

        if (isset($data->inputLanguageList)) {
            $this->getContainer()->get('dataManager')->rebuild();
        }

        return $this->getService('Settings')->getConfigData();
    }

    protected function getLanguage(): Language
    {
        return $this->getContainer()->get('language');
    }
}
