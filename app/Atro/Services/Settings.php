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
    private string $customHeadCodeDir = 'public/client/custom/html';
    private string $customHeadCodeFilename = 'head-code.html';
    private string $customStylesheetDir = 'public/client/custom/css';
    private string $customStylesheetFileName = 'custom-css.css';

    private array $adminItems = [];

    public function getConfigData(): array
    {
        if ($this->getUser()->isGlobalSystemUser()) {
            $data = $this->getPublicConfig();
        } else {
            $data = $this->getPublicConfig($this->getUser()->isAdmin());
        }

        $fieldDefs = $this->getMetadata()->get('entityDefs.Settings.fields');

        foreach ($fieldDefs as $field => $d) {
            if ($d['type'] === 'password') {
                unset($data[$field]);
            }
        }

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

        $this->setData($data, $this->getUser()->isAdmin());
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
     * The part of the config that may leave the backend - for the UI, Twig
     * templates, PDF and export contexts. Strips whatever the given role
     * must not see.
     *
     * @param bool|null $isAdmin null - hide system, admin and user items;
     *                           true - hide system items only;
     *                           false - hide system and admin items.
     */
    public function getPublicConfig(?bool $isAdmin = null): array
    {
        $data = $this->getConfig()->getAll();
        $data = $this->prepareCustomHeadCodeForOutput($data);
        $data = $this->prepareStylesheetConfigForOutput($data);

        foreach ($this->getRestrictItems($isAdmin) as $name) {
            if (isset($data[$name])) {
                unset($data[$name]);
            }
        }

        if (isset($data['clickhouse']['database'])) {
            unset($data['clickhouse']['database']);
        }

        return $data;
    }

    /**
     * Apply incoming data, dropping whatever the given role is not allowed to write.
     */
    private function setData(array|\stdClass $data, ?bool $isAdmin = null): void
    {
        $restrictItems = $this->getRestrictItems($isAdmin);

        $values = [];
        foreach ((array)$data as $key => $item) {
            if (!in_array($key, $restrictItems, true)) {
                $values[$key] = $item;
            }
        }

        $values = $this->prepareCustomHeadCodeForSave($values);
        $values = $this->prepareStylesheetConfigForSave($values);

        foreach ($values as $key => $value) {
            $this->getConfig()->set($key, $value);
        }
    }

    private function getRestrictItems(?bool $onlySystemItems = null): array
    {
        $config = $this->getConfig();

        if ($onlySystemItems) {
            return $config->get('systemItems', []);
        }

        if (empty($this->adminItems)) {
            $this->adminItems = array_merge($config->get('systemItems', []), $config->get('adminItems', []));
        }

        if ($onlySystemItems === false) {
            return $this->adminItems;
        }

        return array_merge($this->adminItems, $config->get('userItems', []));
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
