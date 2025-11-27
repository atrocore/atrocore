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

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Services\Composer as ComposerService;

class Settings extends AbstractService
{
    public function getConfigData(): array
    {
        if ($this->getUser()->id == 'system') {
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
            ->select(['id', 'name', 'type', 'code', 'sourceEntity', 'masterEntity'])
            ->find()->toArray();

        $data['matchingRules'] = $this->getEntityManager()->getRepository('MatchingRule')
            ->select(['id', 'name', 'type', 'matchingRuleSetId'])
            ->find()->toArray();

        return $data;
    }

    public function validate(\stdClass $data): bool
    {
        if (isset($data->inputLanguageList) && count($data->inputLanguageList) == 0) {
            $isMultilangActive = $data->isMultilangActive ?? $this->getConfig()->get('isMultilangActive', false);

            if ($isMultilangActive) {
                throw new BadRequest($this->getLanguage()->translate('languageMustBeSelected', 'messages', 'Settings'));
            }
        }

        return true;
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('language');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getInjection('metadata');
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }
}
