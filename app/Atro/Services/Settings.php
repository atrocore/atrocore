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

use Atro\Core\DataManager;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Utils\FieldManager;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\RegexUtil;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Metadata;
use Atro\Repositories\SoftwarePackage as SoftwarePackageRepository;

class Settings extends AbstractService
{
    private const CONFIG_KEYS
        = [
            'actionHistoryDisabled', 'adminPanelIframeHeight', 'assignedUserAttributeOwnership',
            'assignedUserProductOwnership', 'cacheTimestamp', 'chunkFileSize',
            'currencyList', 'disableEmailDelivery', 'fileUploadStreamCount',
            'globalSearchMaxSize', 'inputLanguageList', 'isStreamSide',
            'language', 'locales', 'mainLanguage',
            'massDeleteMaxCountWithoutJob', 'massRestoreMaxCountWithoutJob', 'massUpdateMaxCountWithoutJob',
            'maxComparableItem', 'maxMassLinkCount', 'maxMassUnlinkCount',
            'maxSizeForEntityComparisons', 'notificationsMaxSize', 'ownerUserAttributeOwnership',
            'ownerUserProductOwnership', 'packaged', 'recordListMaxSizeLimit',
            'resetPasswordViaEmailOnly', 'systemUserId', 'unitsOfMeasure',
            'userNameRegularExpression',
        ];

    private string $customHeadCodeDir = 'public/client/custom/html';
    private string $customHeadCodeFilename = 'head-code.html';
    private string $customStylesheetDir = 'public/client/custom/css';
    private string $customStylesheetFileName = 'custom-css.css';

    public function getScriptConfig(): array
    {
        return array_merge($this->getAppService()->getPublicConfig(), $this->getConfigData(), Variable::loadAll());
    }

    public function getConfigData(): array
    {
        $config = $this->getConfig();
        $data = [];

        foreach (array_merge(self::CONFIG_KEYS, $config->getAdditionalConfigKeys()) as $key) {
            if ($config->has($key)) {
                $data[$key] = $config->get($key);
            }
        }

        foreach ($this->getSettingsFieldDefs() as $field => $defs) {
            if (($defs['type'] ?? null) === 'password') {
                continue;
            }

            foreach ($this->getFieldAttributes($field) as $attribute) {
                if ($config->has($attribute)) {
                    $data[$attribute] = $config->get($attribute);
                }
            }
        }

        return $this->getInjection('eventManager')
            ->dispatch('SettingsService', 'afterGetConfigData', new Event(['data' => $data]))
            ->getArgument('data');
    }

    /**
     * What the Settings form edits: the config data plus the custom code, which
     * is kept in files rather than in the config.
     */
    public function getFormData(): array
    {
        $data = $this->getConfigData();
        $data = $this->prepareCustomHeadCodeForOutput($data);

        return $this->prepareStylesheetConfigForOutput($data);
    }

    private function getSettingsFieldDefs(): array
    {
        return $this->getMetadata()->get('entityDefs.Settings.fields', []);
    }

    private function getFieldAttributes(string $field): array
    {
        $attributes = $this->getFieldManager()->getAttributeList('Settings', $field);

        return empty($attributes) ? [$field] : $attributes;
    }

    public function update(\stdClass $data)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        if (!empty($data->fileNameRegexPattern) && !RegexUtil::validate($data->fileNameRegexPattern)) {
            throw new BadRequest(
                sprintf($this->getLanguage()->translate('regexSyntaxError', 'exceptions', 'FieldManager'), 'fileNameRegexPattern')
            );
        }

        if (!empty($data->passwordRegexPattern) && !RegexUtil::validate($data->passwordRegexPattern)) {
            throw new BadRequest(
                sprintf($this->getLanguage()->translate('regexSyntaxError', 'exceptions', 'FieldManager'), 'passwordRegexPattern')
            );
        }

        $this->getInjection('eventManager')->dispatch('SettingsService', 'beforeUpdate', new Event(['data' => $data]));

        if (property_exists($data, 'onlyStableReleases')) {
            if ($data->onlyStableReleases !== $this->getConfig()->get('onlyStableReleases')) {
                SoftwarePackageRepository::setComposerData('minimum-stability', $data->onlyStableReleases ? 'stable' : 'RC');
            }
            unset($data->onlyStableReleases);
        }

        // clear cache
        $this->getDataManager()->clearCache();

        if (!empty($data->siteUrl)) {
            $data->siteUrl = rtrim($data->siteUrl, '/');
        }

        $this->setData($data);
        $result = $this->getConfig()->save();
        if ($result === false) {
            throw new Error('Cannot save settings');
        }

        if (isset($data->inputLanguageList)) {
            $this->getDataManager()->rebuild();
        }

        return $this->getFormData();
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function getDataManager(): DataManager
    {
        return $this->getInjection('dataManager');
    }

    protected function getFieldManager(): FieldManager
    {
        return $this->getInjection('fieldManagerUtil');
    }

    protected function getAppService(): App
    {
        return $this->getInjection('serviceFactory')->create('App');
    }

    private function setData(array|\stdClass $data): void
    {
        $allowedAttributes = [];
        foreach (array_keys($this->getSettingsFieldDefs()) as $field) {
            foreach ($this->getFieldAttributes($field) as $attribute) {
                $allowedAttributes[$attribute] = true;
            }
        }

        $values = [];
        foreach ((array)$data as $key => $value) {
            if (isset($allowedAttributes[$key])) {
                $values[$key] = $value;
            }
        }

        $values = $this->prepareCustomHeadCodeForSave($values);
        $values = $this->prepareStylesheetConfigForSave($values);

        foreach ($values as $key => $value) {
            $this->getConfig()->set($key, $value);
        }
    }


    private function getCustomHeadCode(): ?string
    {
        $path = $this->getCustomHeadCodePath();

        if (!empty($path) && file_exists($path)) {
            return file_get_contents($path);
        }

        return null;
    }

    private function prepareStylesheetConfigForOutput(array $data): array
    {
        if (!empty($data['customStylesheetPath']) && file_exists($data['customStylesheetPath'])) {
            $data['customStylesheet'] = file_get_contents($data['customStylesheetPath']);
        }

        return $data;
    }

    private function prepareCustomHeadCodeForOutput(array $data): array
    {
        $data['customHeadCode'] = $this->getCustomHeadCode();

        return $data;
    }

    private function prepareStylesheetConfigForSave(array $data): array
    {
        // create custom css theme file
        if (array_key_exists('customStylesheet', $data)) {
            $storedPath = $this->getConfig()->get('customStylesheetPath');

            if (empty($data['customStylesheet'])) {
                if (!empty($storedPath) && file_exists($storedPath)) {
                    unlink($storedPath);
                    $data['customStylesheetPath'] = null;
                }
            } else {
                Util::createDir($this->customStylesheetDir);
                file_put_contents($this->getCustomStylesheetPath(), $data['customStylesheet']);

                $data['customStylesheetPath'] = $this->getCustomStylesheetPath();
            }
        }
        unset($data['customStylesheet']);

        return $data;
    }

    private function prepareCustomHeadCodeForSave(array $data): array
    {
        // create custom head scripts file
        if (array_key_exists('customHeadCode', $data)) {
            $storedPath = $this->getConfig()->get('customHeadCodePath');

            if (empty($data['customHeadCode'])) {
                if (!empty($storedPath) && file_exists($storedPath)) {
                    unlink($storedPath);
                    $data['customHeadCodePath'] = null;
                }
            } else {
                Util::createDir($this->customHeadCodeDir);

                $path = $this->getCustomHeadCodePath();

                file_put_contents($path, $data['customHeadCode']);
                $data['customHeadCodePath'] = $path;
            }
        }
        unset($data['customHeadCode']);

        return $data;
    }

    private function getCustomHeadCodePath(): string
    {
        return $this->customHeadCodeDir . '/' . $this->customHeadCodeFilename;
    }

    private function getCustomStylesheetPath(): string
    {
        return $this->customStylesheetDir . '/' . $this->customStylesheetFileName;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
        $this->addDependency('dataManager');
        $this->addDependency('eventManager');
        $this->addDependency('fieldManagerUtil');
        $this->addDependency('serviceFactory');
    }
}
