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

use Atro\Core\Templates\Services\Subtype;
use Espo\ORM\Entity;

/**
 * The service of the attribute subtypes: the record shows the defaults of the settings of its type.
 */
class AttributeSubtype extends Subtype
{
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        $this->getAttributeService()->loadLocaleUserNames($entity, (bool)$entity->get('isMultilang'));

        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
        if (in_array($entity->get('type'), array_keys($dropdownTypes)) && $entity->get('dropdown') === null) {
            $entity->set('dropdown', false);
        }

        if (!empty($entity->get('htmlSanitizerId'))) {
            $htmlSanitizer = $this->getEntityManager()->getRepository('HtmlSanitizer')->get($entity->get('htmlSanitizerId'));
            if (!empty($htmlSanitizer)) {
                $entity->set('htmlSanitizerName', $htmlSanitizer->get('name'));
            }
        }

        if ($entity->get('fullWidth') === null) {
            $entity->set('fullWidth', in_array($entity->get('type'), ['wysiwyg', 'markdown', 'text', 'composite']));
        }

        if ($entity->get('type') === 'bool') {
            $entity->set('allowNullForBool', empty($entity->get('notNull')));
        }

        if (in_array($entity->get('type'), ['enum', 'multiEnum'])) {
            $translatedOptions = null;
            foreach ($entity->get('options') ?? [] as $option) {
                if (empty($translatedOptions)) {
                    $translatedOptions = new \stdClass();
                }
                $translatedOptions->$option = $this->getInjection('language')->translateOption($option, $entity->get('systemName'), $entity->get('entityId'));
            }
            $entity->set('translatedOptions', $translatedOptions);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function getAttributeService(): Attribute
    {
        return $this->getServiceFactory()->create('Attribute');
    }
}
