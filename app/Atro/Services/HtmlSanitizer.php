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

use Atro\Core\Templates\Services\ReferenceData;
use Espo\ORM\Entity;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class HtmlSanitizer extends ReferenceData
{
    public function parseConfiguration(Entity $entity): ?array
    {
        $result = null;

        try {
            $result = Yaml::parse($entity->get('configuration'));
        } catch (ParseException $e) {
            $GLOBALS['log']->error("Failed to parse HTML sanitizer YAML configuration: " . $e->getMessage());
        }

        return $result;
    }

    public function sanitize(string $id, ?string $content = null): ?string
    {
        if (empty($content)) {
            return $content;
        }

        if (empty($entity = $this->getEntity($id))) {
            return null;
        }

        if (empty($configuration = $this->parseConfiguration($entity))) {
            return null;
        }

        /** @var \Atro\Core\Utils\HTMLSanitizer $sanitizer */
        $sanitizer = $this->getInjection('htmlSanitizer');

        return $sanitizer->sanitize($content, $configuration);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('htmlSanitizer');
    }
}
