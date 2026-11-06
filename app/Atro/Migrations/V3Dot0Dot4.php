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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Atro\Core\Utils\IdGenerator;
use Doctrine\DBAL\ParameterType;

class V3Dot0Dot4 extends Base
{
    /**
     * The supertypes provided by the modules. A subtype of a custom supertype keeps its supertype in its own scope file.
     */
    protected const MODULE_SUPERTYPES = ['Action', 'Attribute', 'Connection', 'Entity', 'EntityField', 'ExportFeed', 'ImportFeed', 'Storage'];

    protected const SCOPES_DIR = 'data/metadata/scopes';

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-11-05 12:00:00');
    }

    /**
     * The description of an entity is a translation like its name: "Global.scopeDescriptions.<Entity>", for a subtype
     * "Global.subtypeDescriptions.<Entity>". The descriptions stored in the scopes are moved to the custom translations.
     */
    public function up(): void
    {
        if (!is_dir(self::SCOPES_DIR)) {
            return;
        }

        $language = $this->getConfig()->get('mainLanguage') ?: 'en_US';
        $column = strtolower($language);
        $supertypesPattern = '/^(' . implode('|', self::MODULE_SUPERTYPES) . ')Type[A-Z]/';

        foreach (scandir(self::SCOPES_DIR) as $fileName) {
            if (!preg_match('/^([A-Z][A-Za-z0-9]*)\.json$/', $fileName, $matches)) {
                continue;
            }

            $path = self::SCOPES_DIR . '/' . $fileName;
            $data = json_decode((string)file_get_contents($path), true);
            if (!is_array($data) || !array_key_exists('description', $data)) {
                continue;
            }

            $scope = $matches[1];
            $description = $data['description'];
            unset($data['description']);

            if (is_string($description) && trim($description) !== '') {
                $isSubtype = !empty($data['supertypeId']) || preg_match($supertypesPattern, $scope);
                $category = $isSubtype ? 'subtypeDescriptions' : 'scopeDescriptions';
                $this->setTranslation("Global.$category.$scope", $column, $language, $description);
            }

            if (empty($data)) {
                unlink($path);
            } else {
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    protected function setTranslation(string $code, string $column, string $language, string $value): void
    {
        $dbal = $this->getDbal();

        $existing = $dbal->createQueryBuilder()
            ->select('id', $column, 'customized_languages')
            ->from($dbal->quoteIdentifier('translation'))
            ->where('code = :code')
            ->andWhere('deleted = :false')
            ->setParameter('code', $code)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if (empty($existing)) {
            $dbal->createQueryBuilder()
                ->insert($dbal->quoteIdentifier('translation'))
                ->setValue('id', ':id')
                ->setValue('code', ':code')
                ->setValue('module', ':module')
                ->setValue($column, ':value')
                ->setValue('customized_languages', ':customizedLanguages')
                ->setValue('created_at', ':createdAt')
                ->setValue('deleted', ':false')
                ->setParameter('id', IdGenerator::sortableId())
                ->setParameter('code', $code)
                ->setParameter('module', 'custom')
                ->setParameter('value', $value)
                ->setParameter('customizedLanguages', json_encode([$language]))
                ->setParameter('createdAt', date('Y-m-d H:i:s'))
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeStatement();

            return;
        }

        if (!empty($existing[$column])) {
            return;
        }

        $customizedLanguages = json_decode((string)$existing['customized_languages'], true);
        $customizedLanguages = is_array($customizedLanguages) ? $customizedLanguages : [];
        if (!in_array($language, $customizedLanguages, true)) {
            $customizedLanguages[] = $language;
        }

        $dbal->createQueryBuilder()
            ->update($dbal->quoteIdentifier('translation'))
            ->set($column, ':value')
            ->set('customized_languages', ':customizedLanguages')
            ->where('id = :id')
            ->setParameter('value', $value)
            ->setParameter('customizedLanguages', json_encode(array_values($customizedLanguages)))
            ->setParameter('id', $existing['id'])
            ->executeStatement();
    }
}
