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
use Atro\Core\Utils\Language;
use Atro\Core\Utils\RegexUtil;
use Atro\Core\Utils\Util;
use Atro\Core\Utils\Metadata;
use Atro\Repositories\SoftwarePackage as SoftwarePackageRepository;

class Settings extends AbstractService
{
    /**
     * Config parameters that may leave the backend. Anything not listed here
     * stays server-side, so a new parameter is private until someone adds it
     * on purpose - never by forgetting to exclude it.
     *
     * Keys contributed through AbstractModule::getConfigAdditionalData() are
     * exposed on top of this list, since that mechanism exists for the frontend.
     */
    private const PUBLIC_CONFIG_KEYS
        = [
            'actionHistoryDisabled', 'adminPanelIframeHeight', 'applicationName',
            'assignedUserAttributeOwnership', 'assignedUserProductOwnership', 'avatarsDisabled',
            'cacheTimestamp', 'changeStatusAfterTranslation', 'chunkFileSize',
            'companyLogoId', 'currencyList', 'dashletsOptions',
            'dateFormat', 'defaultNotificationProfileId', 'defaultStyleId',
            'disableEmailDelivery', 'disableNavigationPath', 'disableToolbarLogo',
            'displayListViewRecordCount', 'faviconId', 'favoritesIconsDisabled',
            'fileNameRegexPattern', 'fileUploadStreamCount', 'fuzzySearchAvailable',
            'globalSearchEntityList', 'globalSearchMaxSize', 'hasApproved',
            'hasNotTranslateFrom', 'hasNotTranslateTo', 'inputLanguageList',
            'isMultilangActive', 'isStreamSide', 'language',
            'lastViewedCount', 'locale', 'locales',
            'mainLanguage', 'massDeleteMaxCountWithoutJob', 'massRestoreMaxCountWithoutJob',
            'massUpdateMaxCountWithoutJob', 'maxComparableItem', 'maxMassLinkCount',
            'maxMassUnlinkCount', 'maxSizeForEntityComparisons', 'notificationsMaxSize',
            'notificationSmtpConnectionId', 'ownerUserAttributeOwnership', 'ownerUserProductOwnership',
            'packaged', 'readableDateFormatDisabled', 'recordListMaxSizeLimit',
            'recordsPerPage', 'recordsPerPageSmall', 'resetPasswordViaEmailOnly',
            'scopeColorsDisabled', 'siteUrl', 'systemUserId',
            'tabIconsDisabled', 'timeFormat', 'timeZone',
            'unitsOfMeasure', 'userNameRegularExpression', 'userThemesDisabled',
            'weekStart',
        ];

    private string $customHeadCodeDir = 'public/client/custom/html';
    private string $customHeadCodeFilename = 'head-code.html';
    private string $customStylesheetDir = 'public/client/custom/css';
    private string $customStylesheetFileName = 'custom-css.css';

    /**
     * Config for server-side script contexts - Twig templates, PDF and export
     * rendering. Same as the public config plus the user-defined variables:
     * those may hold secrets, which is fine here because the script runs on the
     * backend, and is exactly why they never go to the frontend.
     */
    public function getScriptConfig(): array
    {
        return array_merge($this->getPublicConfig(), Variable::loadAll());
    }

    /**
     * Everything the Settings UI needs: the public config plus the parameters
     * declared as Settings fields, which is what the form edits. Password
     * fields never leave the backend.
     */
    public function getConfigData(): array
    {
        $config = $this->getConfig();
        $data = $this->getPublicConfig();

        foreach ($this->getSettingsFieldDefs() as $field => $defs) {
            if (($defs['type'] ?? null) === 'password') {
                unset($data[$field]);
                continue;
            }

            if (!array_key_exists($field, $data) && $config->has($field)) {
                $data[$field] = $config->get($field);
            }
        }

        $data = $this->prepareCustomHeadCodeForOutput($data);
        $data = $this->prepareStylesheetConfigForOutput($data);

        $data['jsLibs'] = $this->getMetadata()->get('app.jsLibs');
        $data['themes'] = $this->getMetadata()->get('themes');
        $data['coreVersion'] = SoftwarePackage::getCoreVersion();

        $data['matchingRules'] = $this->getEntityManager()->getRepository('MatchingRule')
            ->select(['id', 'name', 'type', 'matchingRuleSetId', 'matchingId'])
            ->find()->toArray();

        return $this->getInjection('eventManager')
            ->dispatch('SettingsService', 'afterGetConfigData', new Event(['data' => $data]))
            ->getArgument('data');
    }

    private function getSettingsFieldDefs(): array
    {
        return $this->getMetadata()->get('entityDefs.Settings.fields', []);
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

        return $this->getConfigData();
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

    /**
     * The part of the config that may leave the backend - the UI, Twig
     * templates, PDF and export contexts. Built from an explicit allow list
     * plus whatever the providers contribute: nothing else ever leaves.
     */
    public function getPublicConfig(): array
    {
        $config = $this->getConfig();

        $keys = array_merge(self::PUBLIC_CONFIG_KEYS, $config->getAdditionalConfigKeys());

        $data = [];
        foreach (array_unique($keys) as $key) {
            if ($config->has($key)) {
                $data[$key] = $config->get($key);
            }
        }

        return $data;
    }

    /**
     * Applies incoming data. Only parameters declared as Settings fields can be
     * written: anything else in the payload is ignored, so a request can never
     * reach a config key that the UI does not own.
     */
    private function setData(array|\stdClass $data): void
    {
        $fieldDefs = $this->getSettingsFieldDefs();

        $values = [];
        foreach ((array)$data as $key => $value) {
            if (isset($fieldDefs[$key])) {
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
    }
}
