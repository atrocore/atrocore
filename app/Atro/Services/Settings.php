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
use Atro\Core\Utils\Metadata;
use Atro\Services\Composer as ComposerService;

class Settings extends AbstractService
{
    public function getConfigData(): array
    {
        if ($this->getUser()->isGlobalSystemUser()) {
            $data = $this->getConfig()->getData();
        } else {
            $data = $this->getConfig()->getData($this->getUser()->isAdmin());
        }

        $fieldDefs = $this->getMetadata()->get('entityDefs.Settings.fields');

        foreach ($fieldDefs as $field => $d) {
            if ($d['type'] === 'password') {
                unset($data[$field]);
            }
        }

        $data['jsLibs'] = $this->getMetadata()->get('app.jsLibs');
        $data['themes'] = $this->getMetadata()->get('themes');
        $data['coreVersion'] = ComposerService::getCoreVersion();

        $data['matchings'] = $this->getEntityManager()->getRepository('Matching')
            ->select(['id', 'type', 'entity', 'masterEntity'])
            ->find()->toArray();

        $data['matchingRules'] = $this->getEntityManager()->getRepository('MatchingRule')
            ->select(['id', 'name', 'type', 'matchingRuleSetId', 'matchingId'])
            ->find()->toArray();

        return $data;
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
                Composer::setMinimumStability($data->onlyStableReleases ? 'stable' : 'RC');
            }
            unset($data->onlyStableReleases);
        }

        // clear cache
        $this->getDataManager()->clearCache();

        if (!empty($data->siteUrl)) {
            $data->siteUrl = rtrim($data->siteUrl, '/');
        }

        $this->getConfig()->setData($data, $this->getUser()->isAdmin());
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

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
        $this->addDependency('dataManager');
        $this->addDependency('eventManager');
    }
}
