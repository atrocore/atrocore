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

use Atro\Core\Application;
use Atro\Core\Migration\Base;
use Atro\Core\Utils\Util;

class V2Dot3Dot14 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-07-31 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE translation ADD customized_languages TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN translation.customized_languages IS '(DC2Type:jsonArray)'");
        } else {
            $this->exec("ALTER TABLE translation ADD customized_languages LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonArray)'");
        }

        $this->exec("ALTER TABLE translation DROP is_customized");

        $this->calculateInitialCustomizedLanguages();
    }

    /**
     * One-time backfill for existing Translation rows created before per-language customization tracking
     * existed: for each language a module currently provides a value for, if the DB value differs from it,
     * assume it was already customized and protect it going forward.
     */
    private function calculateInitialCustomizedLanguages(): void
    {
        $container = (new Application())->getContainer();

        /** @var \Atro\Core\Utils\Language $language */
        $language = $container->get('language');
        /** @var \Atro\Repositories\Translation $repository */
        $repository = $container->get('entityManager')->getRepository('Translation');

        $fieldToLanguageCode = [];
        foreach ($container->get('metadata')->get(['entityDefs', 'Translation', 'fields', 'customizedLanguages', 'options'], []) as $code) {
            $fieldToLanguageCode[Util::toCamelCase(strtolower($code))] = $code;
        }

        $records = $language->getSimplifiedTranslates($language->getModulesData());
        $existingMap = $repository->fetchExistingCodeMap();

        $toUpdate = [];

        foreach ($existingMap as $code => $entry) {
            if (!isset($records[$code])) {
                continue;
            }

            $customizedLanguages = $entry['customizedLanguages'];
            $changed = false;

            foreach ($records[$code] as $field => $value) {
                if (in_array($field, ['code', 'module', 'createdAt'], true)) {
                    continue;
                }

                $languageCode = $fieldToLanguageCode[$field] ?? null;
                if ($languageCode === null || in_array($languageCode, $customizedLanguages, true)) {
                    continue;
                }

                $currentValue = $entry['values'][$field] ?? null;
                if ($currentValue !== null && $currentValue !== '' && (string)$currentValue !== (string)$value) {
                    $customizedLanguages[] = $languageCode;
                    $changed = true;
                }
            }

            if ($changed) {
                $toUpdate[] = [
                    'id'                  => $entry['id'],
                    'customizedLanguages' => json_encode(array_values(array_unique($customizedLanguages))),
                ];
            }
        }

        foreach (array_chunk($toUpdate, 1000) as $rows) {
            $repository->bulkUpdate($rows);
        }
    }

    private function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
