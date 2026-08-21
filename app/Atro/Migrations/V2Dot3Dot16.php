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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\HTMLSanitizer;
use Atro\Core\Utils\YamlDuplicateKeys;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class V2Dot3Dot16 extends Base
{
    private const string SANITIZERS_PATH = 'data/reference-data/HtmlSanitizer.json';

    private YamlDuplicateKeys $yamlDuplicateKeys;

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-08-20 12:00:00');
    }

    public function up(): void
    {
        $this->setLegacyParserDefault();
        $this->repairSanitizerConfigurations();
    }

    private function setLegacyParserDefault(): void
    {
        if ($this->getConfig()->get(HTMLSanitizer::LEGACY_PARSER_CONFIG_KEY) !== null) {
            return;
        }

        $this->getConfig()->set(HTMLSanitizer::LEGACY_PARSER_CONFIG_KEY, true);
        $this->getConfig()->save();
    }

    private function repairSanitizerConfigurations(): void
    {
        if (!file_exists(self::SANITIZERS_PATH)) {
            return;
        }

        $items = @json_decode(file_get_contents(self::SANITIZERS_PATH), true);
        if (!is_array($items)) {
            return;
        }

        $this->yamlDuplicateKeys = new YamlDuplicateKeys();

        $modified = false;
        foreach ($items as $code => $item) {
            $configuration = $item['configuration'] ?? null;
            if (!is_string($configuration) || trim($configuration) === '') {
                continue;
            }

            $repaired = $this->yamlDuplicateKeys->remove($configuration);

            if ($repaired === null) {
                $this->reportUnrepairedConfiguration((string)$code, $configuration);
                continue;
            }

            $items[$code]['configuration'] = $repaired;
            $modified = true;

            $this->reportRepairedConfiguration((string)$code);
        }

        if ($modified) {
            file_put_contents(self::SANITIZERS_PATH, json_encode($items));
        }
    }

    private function reportRepairedConfiguration(string $code): void
    {
        if (!isset($GLOBALS['log'])) {
            return;
        }

        $GLOBALS['log']->warning(
            "HTML sanitizer '$code': dropped the duplicate null-valued YAML key(s) "
            . implode(', ', $this->yamlDuplicateKeys->getDroppedKeys())
            . ' so the configuration keeps parsing on Symfony Yaml 8. The sanitizing result is unchanged.'
        );
    }

    private function reportUnrepairedConfiguration(string $code, string $configuration): void
    {
        if (!isset($GLOBALS['log'])) {
            return;
        }

        if ($this->parse($configuration) === null) {
            $GLOBALS['log']->error(
                "HTML sanitizer '$code' has an unparsable YAML configuration, so content it guards is stored unsanitized."
            );

            return;
        }

        $keys = $this->yamlDuplicateKeys->find($configuration);
        if ($keys === []) {
            return;
        }

        $GLOBALS['log']->warning(
            "HTML sanitizer '$code' repeats the null-valued YAML key(s) " . implode(', ', $keys)
            . ', which could not be de-duplicated automatically. Review it before moving to Symfony Yaml 8.'
        );
    }

    private function parse(string $configuration): ?array
    {
        try {
            $parsed = @Yaml::parse($configuration);
        } catch (ParseException) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }
}
