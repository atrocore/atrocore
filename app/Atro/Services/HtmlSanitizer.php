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

use Atro\Core\Exceptions\Conflict;
use Atro\Core\Templates\Services\ReferenceData;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\YamlDuplicateKeys;
use Espo\ORM\Entity;

class HtmlSanitizer extends ReferenceData
{
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->checkDuplicateYamlKeys($entity, $data);

        parent::beforeCreateEntity($entity, $data);
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->checkDuplicateYamlKeys($entity, $data);

        parent::beforeUpdateEntity($entity, $data);
    }

    protected function checkDuplicateYamlKeys(Entity $entity, $data): void
    {
        if (!$entity->isAttributeChanged('configuration') || empty($entity->get('configuration'))) {
            return;
        }

        if (!empty($data->_ignoreConflict ?? null)) {
            return;
        }

        $configuration = $entity->get('configuration');

        // an unparsable configuration is rejected outright by the repository, this only warns about
        // configurations that still parse today and will stop parsing on Symfony Yaml 8
        if (!is_array($this->getHtmlSanitizer()->parse($configuration))) {
            return;
        }

        $keys = (new YamlDuplicateKeys())->find($configuration);
        if ($keys === []) {
            return;
        }

        throw new Conflict(
            str_replace(
                '{keys}',
                implode(', ', $keys),
                $this->getLanguage()->translate('duplicateYamlKeys', 'exceptions', 'HtmlSanitizer')
            )
        );
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
        $this->addDependency('htmlSanitizer');
    }

    protected function getHtmlSanitizer(): \Atro\Core\Utils\HTMLSanitizer
    {
        return $this->getInjection('htmlSanitizer');
    }

    protected function getLanguage(): Language
    {
        return $this->getInjection('container')->get('language');
    }
}
