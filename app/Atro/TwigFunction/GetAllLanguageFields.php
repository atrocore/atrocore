<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Atro\Core\Utils\Util;

class GetAllLanguageFields extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('metadata');
        $this->addDependency('config');
    }

    public function run(string $entityType, string|array $fields): array
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        $langList = $this->getInjection('config')->get('inputLanguageList', []);
        $metadata = $this->getInjection('metadata')->get(['entityDefs', $entityType]);
        if (empty($metadata)) {
            return $fields;
        }

        $result = [];
        foreach ($fields as $field) {
            $result[] = $field;
            foreach ($langList as $lang) {
                $fieldName = $field . ucfirst(Util::toCamelCase(strtolower($lang)));
                if (array_key_exists($fieldName, $metadata['fields'] ?? [])) {
                    $result[] = $fieldName;
                }
            }
        }

        return $result;
    }
}